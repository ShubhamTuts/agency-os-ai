<?php
/**
 * Time Entry Model for Agency OS AI
 *
 * @package Agency_OS_AI
 * @since 1.5.0
 */

if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

/**
 * AOSAI_Time_Entry class
 *
 * Handles time tracking entry CRUD operations.
 *
 * @since 1.5.0
 */
class AOSAI_Time_Entry {
    use AOSAI_Singleton;
    
    private function __construct() {}
    
    public function get_table(): string {
        global $wpdb;
        return esc_sql( $wpdb->prefix . 'aosai_time_entries' );
    }
    
    public function get_all( array $args = array() ): array {
        global $wpdb;
        $table = $this->get_table();
        
        $defaults = array(
            'page'       => 1,
            'per_page'   => 50,
            'user_id'    => '',
            'task_id'    => '',
            'project_id' => '',
            'billable'   => '',
            'invoiced'   => '',
            'date_from'  => '',
            'date_to'    => '',
            'orderby'    => 'start_time',
            'order'      => 'DESC',
        );
        $args = wp_parse_args( $args, $defaults );
        
        $per_page = max( 1, (int) $args['per_page'] );
        $page     = max( 1, (int) $args['page'] );
        $offset   = ( $page - 1 ) * $per_page;
        $params   = array();
        $sql      = 'SELECT * FROM ' . $table . ' WHERE 1=1';
        
        if ( ! empty( $args['user_id'] ) ) {
            $sql .= ' AND user_id = %d';
            $params[] = absint( $args['user_id'] );
        }
        
        if ( ! empty( $args['task_id'] ) ) {
            $sql .= ' AND task_id = %d';
            $params[] = absint( $args['task_id'] );
        }
        
        if ( ! empty( $args['project_id'] ) ) {
            $sql .= ' AND project_id = %d';
            $params[] = absint( $args['project_id'] );
        }
        
        if ( $args['billable'] !== '' ) {
            $sql .= ' AND billable = %d';
            $params[] = (int) $args['billable'];
        }
        
        if ( $args['invoiced'] !== '' ) {
            $sql .= ' AND invoiced = %d';
            $params[] = (int) $args['invoiced'];
        }
        
        if ( ! empty( $args['date_from'] ) ) {
            $sql .= ' AND DATE(start_time) >= %s';
            $params[] = sanitize_text_field( $args['date_from'] );
        }
        
        if ( ! empty( $args['date_to'] ) ) {
            $sql .= ' AND DATE(start_time) <= %s';
            $params[] = sanitize_text_field( $args['date_to'] );
        }

        $sql .= ' ORDER BY ' . $this->get_order_clause( (string) $args['orderby'], (string) $args['order'] );
        $sql .= ' LIMIT %d OFFSET %d';
        $params[] = $per_page;
        $params[] = $offset;
        $sql = $wpdb->prepare( $sql, $params );
        
        $entries = $wpdb->get_results( $sql, ARRAY_A );
        
        foreach ( $entries as &$entry ) {
            $entry = $this->enrich( $entry );
        }
        
        return $entries;
    }
    
    public function get( int $id ): ?array {
        global $wpdb;
        $table = $this->get_table();
        
        $entry = $wpdb->get_row(
            $wpdb->prepare( 'SELECT * FROM ' . $table . ' WHERE id = %d', $id ),
            ARRAY_A
        );
        
        if ( ! $entry ) {
            return null;
        }
        
        return $this->enrich( $entry );
    }
    
    public function create( array $data ): int|\WP_Error {
        global $wpdb;
        $table = $this->get_table();
        
        $sanitized = $this->sanitize_input( $data );
        
        if ( empty( $sanitized['start_time'] ) ) {
            return new \WP_Error( 'missing_start', esc_html__( 'Start time is required.', 'agency-os-ai' ) );
        }
        
        if ( ! empty( $sanitized['end_time'] ) && strtotime( $sanitized['end_time'] ) < strtotime( $sanitized['start_time'] ) ) {
            return new \WP_Error( 'invalid_time', esc_html__( 'End time cannot be before start time.', 'agency-os-ai' ) );
        }
        
        if ( ! empty( $sanitized['end_time'] ) ) {
            $sanitized['duration'] = $this->calculate_duration( $sanitized['start_time'], $sanitized['end_time'] );
        }
        
        $result = $wpdb->insert(
            $table,
            array(
                'task_id'    => $sanitized['task_id'] ?? null,
                'project_id' => $sanitized['project_id'] ?? null,
                'user_id'    => $sanitized['user_id'] ?? get_current_user_id(),
                'description'=> $sanitized['description'] ?? '',
                'start_time' => $sanitized['start_time'],
                'end_time'   => $sanitized['end_time'] ?? null,
                'duration'   => $sanitized['duration'] ?? 0,
                'billable'   => $sanitized['billable'] ?? 1,
                'invoiced'   => 0,
                'invoice_id' => null,
            ),
            array( '%d', '%d', '%d', '%s', '%s', '%s', '%d', '%d', '%d', '%d' )
        );
        
        if ( false === $result ) {
            return new \WP_Error( 'db_error', esc_html__( 'Failed to create time entry.', 'agency-os-ai' ) );
        }
        
        return $wpdb->insert_id;
    }
    
    public function update( int $id, array $data ): bool|\WP_Error {
        global $wpdb;
        $table = $this->get_table();
        
        $entry = $this->get( $id );
        if ( ! $entry ) {
            return new \WP_Error( 'not_found', esc_html__( 'Time entry not found.', 'agency-os-ai' ) );
        }
        
        if ( (int) $entry['invoiced'] === 1 ) {
            return new \WP_Error( 'invoiced', esc_html__( 'Cannot edit an invoiced time entry.', 'agency-os-ai' ) );
        }
        
        $sanitized = $this->sanitize_input( $data );
        
        if ( empty( $sanitized ) ) {
            return true;
        }
        
        $sanitized['updated_at'] = current_time( 'mysql' );
        
        if ( ! empty( $sanitized['end_time'] ) && ! empty( $sanitized['start_time'] ) ) {
            $sanitized['duration'] = $this->calculate_duration( $sanitized['start_time'], $sanitized['end_time'] );
        }
        
        $format = array();
        foreach ( $sanitized as $key => $value ) {
            if ( in_array( $key, array( 'task_id', 'project_id', 'user_id', 'duration', 'billable', 'invoiced', 'invoice_id' ), true ) ) {
                $format[] = is_int( $value ) ? '%d' : '%s';
            } else {
                $format[] = '%s';
            }
        }
        
        $result = $wpdb->update(
            $table,
            $sanitized,
            array( 'id' => $id ),
            $format,
            array( '%d' )
        );
        
        if ( false === $result ) {
            return new \WP_Error( 'db_error', esc_html__( 'Failed to update time entry.', 'agency-os-ai' ) );
        }
        
        return true;
    }
    
    public function delete( int $id ): bool {
        global $wpdb;
        $table = $this->get_table();
        
        $entry = $this->get( $id );
        if ( ! $entry ) {
            return false;
        }
        
        if ( (int) $entry['invoiced'] === 1 ) {
            return false;
        }
        
        $result = $wpdb->delete( $table, array( 'id' => $id ), array( '%d' ) );
        
        return $result !== false;
    }
    
    public function start_timer( int $task_id = 0, int $project_id = 0, string $description = '' ): int|\WP_Error {
        global $wpdb;
        
        $active = $this->get_active_timer();
        if ( $active ) {
            return new \WP_Error( 'timer_active', esc_html__( 'A timer is already running.', 'agency-os-ai' ) );
        }
        
        return $this->create( array(
            'task_id'     => $task_id,
            'project_id'  => $project_id,
            'description' => $description,
            'start_time'  => current_time( 'mysql' ),
            'billable'    => 1,
        ) );
    }
    
    public function stop_timer( int $id ): bool|\WP_Error {
        $entry = $this->get( $id );
        if ( ! $entry ) {
            return new \WP_Error( 'not_found', esc_html__( 'Time entry not found.', 'agency-os-ai' ) );
        }
        
        if ( ! empty( $entry['end_time'] ) ) {
            return true;
        }
        
        return $this->update( $id, array(
            'end_time' => current_time( 'mysql' ),
        ) );
    }
    
    public function get_active_timer( int $user_id = 0 ): ?array {
        global $wpdb;
        $table = $this->get_table();
        
        if ( $user_id === 0 ) {
            $user_id = get_current_user_id();
        }
        
        $entry = $wpdb->get_row(
            $wpdb->prepare(
                'SELECT * FROM ' . $table . ' WHERE user_id = %d AND end_time IS NULL ORDER BY start_time DESC LIMIT 1',
                $user_id
            ),
            ARRAY_A
        );
        
        return $entry ? $this->enrich( $entry ) : null;
    }
    
    public function get_user_totals( int $user_id = 0, array $args = array() ): array {
        global $wpdb;
        $table = $this->get_table();
        
        if ( $user_id === 0 ) {
            $user_id = get_current_user_id();
        }
        
        $params = array( $user_id );
        $sql    = 'SELECT 
                    COUNT(*) as total_entries,
                    SUM(duration) as total_duration,
                    SUM(CASE WHEN billable = 1 THEN duration ELSE 0 END) as billable_duration,
                    SUM(CASE WHEN invoiced = 1 THEN duration ELSE 0 END) as invoiced_duration
                FROM ' . $table . ' WHERE user_id = %d';
        
        if ( ! empty( $args['date_from'] ) ) {
            $sql .= ' AND DATE(start_time) >= %s';
            $params[] = sanitize_text_field( $args['date_from'] );
        }
        
        if ( ! empty( $args['date_to'] ) ) {
            $sql .= ' AND DATE(start_time) <= %s';
            $params[] = sanitize_text_field( $args['date_to'] );
        }
        
        if ( ! empty( $args['project_id'] ) ) {
            $sql .= ' AND project_id = %d';
            $params[] = absint( $args['project_id'] );
        }

        $totals = $wpdb->get_row(
            $wpdb->prepare( $sql, $params ),
            ARRAY_A
        );
        
        return $totals;
    }
    
    public function get_project_totals( int $project_id, array $args = array() ): array {
        global $wpdb;
        $table = $this->get_table();
        
        $params = array( $project_id );
        $sql    = 'SELECT 
                    COUNT(*) as total_entries,
                    SUM(duration) as total_duration,
                    SUM(CASE WHEN billable = 1 THEN duration ELSE 0 END) as billable_duration,
                    SUM(CASE WHEN invoiced = 1 THEN duration ELSE 0 END) as invoiced_duration
                FROM ' . $table . ' WHERE project_id = %d';
        
        if ( ! empty( $args['date_from'] ) ) {
            $sql .= ' AND DATE(start_time) >= %s';
            $params[] = sanitize_text_field( $args['date_from'] );
        }
        
        if ( ! empty( $args['date_to'] ) ) {
            $sql .= ' AND DATE(start_time) <= %s';
            $params[] = sanitize_text_field( $args['date_to'] );
        }

        $totals = $wpdb->get_row(
            $wpdb->prepare( $sql, $params ),
            ARRAY_A
        );
        
        return $totals;
    }
    
    public function get_unbilled_entries( int $client_id = 0, int $project_id = 0 ): array {
        global $wpdb;
        $table = $this->get_table();
        $params = array();
        $sql = 'SELECT * FROM ' . $table . ' WHERE invoiced = 0 AND end_time IS NOT NULL';
        
        if ( $client_id > 0 ) {
            // Get time entries for projects linked to this client
            $client_projects_table = esc_sql( $wpdb->prefix . 'aosai_client_projects' );
            $sql .= ' AND project_id IN (SELECT project_id FROM ' . $client_projects_table . ' WHERE client_id = %d)';
            $params[] = $client_id;
        }
        
        if ( $project_id > 0 ) {
            $sql .= ' AND project_id = %d';
            $params[] = $project_id;
        }
        
        $sql .= ' ORDER BY start_time DESC';
        
        if ( ! empty( $params ) ) {
            $entries = $wpdb->get_results( $wpdb->prepare( $sql, $params ), ARRAY_A );
        } else {
            $entries = $wpdb->get_results( $sql, ARRAY_A );
        }
        
        foreach ( $entries as &$entry ) {
            $entry = $this->enrich( $entry );
        }
        
        return $entries;
    }
    
    public function mark_as_invoiced( int $entry_id, int $invoice_id ): bool {
        global $wpdb;
        $table = $this->get_table();
        
        $result = $wpdb->update(
            $table,
            array(
                'invoiced'   => 1,
                'invoice_id'  => $invoice_id,
                'updated_at' => current_time( 'mysql' ),
            ),
            array( 'id' => $entry_id ),
            array( '%d', '%d', '%s' ),
            array( '%d' )
        );
        
        return $result !== false;
    }
    
    public function mark_as_invoiced_batch( array $entry_ids, int $invoice_id ): bool {
        global $wpdb;
        $table = $this->get_table();
        
        if ( empty( $entry_ids ) ) {
            return false;
        }
        
        $ids = array_map( 'absint', $entry_ids );
        $placeholders = implode( ',', array_fill( 0, count( $ids ), '%d' ) );
        $sql = 'UPDATE ' . $table . ' SET invoiced = 1, invoice_id = %d WHERE id IN (' . $placeholders . ') AND invoiced = 0';
        
        $result = $wpdb->query(
            $wpdb->prepare( $sql, array_merge( array( $invoice_id ), $ids ) )
        );
        
        return $result !== false;
    }
    
    private function calculate_duration( string $start, string $end ): int {
        $start_ts = strtotime( $start );
        $end_ts = strtotime( $end );
        
        return max( 0, $end_ts - $start_ts );
    }

    private function get_order_clause( string $orderby, string $order ): string {
        $allowed = array(
            'start_time' => 'start_time',
            'end_time'   => 'end_time',
            'duration'   => 'duration',
            'created_at' => 'created_at',
            'updated_at' => 'updated_at',
        );

        $column    = $allowed[ sanitize_key( $orderby ) ] ?? 'start_time';
        $direction = 'ASC' === strtoupper( $order ) ? 'ASC' : 'DESC';

        return $column . ' ' . $direction;
    }
    
    private function enrich( array $entry ): array {
        $entry['duration'] = (int) ( $entry['duration'] ?? 0 );

        if ( ! empty( $entry['task_id'] ) ) {
            $task = AOSAI_Task::get_instance()->get( (int) $entry['task_id'] );
            $entry['task_title'] = $task ? $task['title'] : '';
        }
        
        if ( ! empty( $entry['project_id'] ) ) {
            $project = AOSAI_Project::get_instance()->get( (int) $entry['project_id'] );
            $entry['project_name'] = $project ? $project['name'] : '';
        }
        
        if ( ! empty( $entry['user_id'] ) ) {
            $user = get_userdata( (int) $entry['user_id'] );
            $entry['user_name'] = $user ? $user->display_name : '';
            $entry['user_email'] = $user ? $user->user_email : '';
        }
        
        $entry['date'] = ! empty( $entry['start_time'] ) ? gmdate( 'Y-m-d', strtotime( $entry['start_time'] ) ) : '';
        $entry['formatted_duration'] = $this->format_duration( (int) $entry['duration'] );
        
        if ( empty( $entry['end_time'] ) ) {
            $entry['is_running'] = true;
            $entry['elapsed'] = $this->calculate_duration( $entry['start_time'], current_time( 'mysql' ) );
            $entry['formatted_elapsed'] = $this->format_duration( $entry['elapsed'] );
        } else {
            $entry['is_running'] = false;
            $entry['elapsed'] = 0;
            $entry['formatted_elapsed'] = '00:00:00';
        }
        
        return $entry;
    }
    
    private function format_duration( int $seconds ): string {
        $hours = floor( $seconds / 3600 );
        $minutes = floor( ( $seconds % 3600 ) / 60 );
        $secs = $seconds % 60;
        
        return sprintf( '%02d:%02d:%02d', $hours, $minutes, $secs );
    }
    
    private function sanitize_input( array $input ): array {
        $sanitized = array();
        
        $allowed_fields = array( 'task_id', 'project_id', 'user_id', 'description', 'start_time', 'end_time', 'duration', 'billable' );
        
        foreach ( $input as $key => $value ) {
            if ( ! in_array( $key, $allowed_fields, true ) ) {
                continue;
            }
            
            switch ( $key ) {
                case 'task_id':
                case 'project_id':
                case 'user_id':
                    $sanitized[ $key ] = $value ? absint( $value ) : null;
                    break;
                case 'description':
                    $sanitized[ $key ] = sanitize_text_field( (string) $value );
                    break;
                case 'start_time':
                case 'end_time':
                    if ( ! empty( $value ) ) {
                        $sanitized[ $key ] = gmdate( 'Y-m-d H:i:s', strtotime( $value ) );
                    }
                    break;
                case 'duration':
                    $sanitized[ $key ] = absint( $value );
                    break;
                case 'billable':
                    $sanitized[ $key ] = $value ? 1 : 0;
                    break;
            }
        }
        
        return $sanitized;
    }
}
