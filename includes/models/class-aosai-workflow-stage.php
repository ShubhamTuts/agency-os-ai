<?php
/**
 * Workflow Stage Model for Agency OS AI
 *
 * @package Agency_OS_AI
 * @since 1.5.0
 */

if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

/**
 * AOSAI_Workflow_Stage class
 *
 * Handles workflow stage CRUD operations for Kanban boards.
 *
 * @since 1.5.0
 */
class AOSAI_Workflow_Stage {
    use AOSAI_Singleton;
    
    private function __construct() {}
    
    public function get_table(): string {
        global $wpdb;
        return esc_sql( $wpdb->prefix . 'aosai_workflow_stages' );
    }
    
    public function get_all( string $type = 'task' ): array {
        global $wpdb;
        $table = $this->get_table();
        
        $stages = $wpdb->get_results(
            $wpdb->prepare( 'SELECT * FROM ' . $table . ' WHERE type = %s ORDER BY sort_order ASC', $type ),
            ARRAY_A
        );
        
        return $stages;
    }
    
    public function get( int $id ): ?array {
        global $wpdb;
        $table = $this->get_table();
        
        $stage = $wpdb->get_row(
            $wpdb->prepare( 'SELECT * FROM ' . $table . ' WHERE id = %d', $id ),
            ARRAY_A
        );
        
        return $stage ?: null;
    }
    
    public function create( array $data ): int|\WP_Error {
        global $wpdb;
        $table = $this->get_table();
        
        $sanitized = $this->sanitize_input( $data );
        
        if ( empty( $sanitized['name'] ) ) {
            return new \WP_Error( 'missing_name', esc_html__( 'Stage name is required.', 'agency-os-ai' ) );
        }
        
        if ( empty( $sanitized['slug'] ) ) {
            $sanitized['slug'] = sanitize_title( $sanitized['name'] );
        }
        
        $max_order = (int) $wpdb->get_var(
            $wpdb->prepare( 'SELECT MAX(sort_order) FROM ' . $table . ' WHERE type = %s', $sanitized['type'] )
        );
        
        $result = $wpdb->insert(
            $table,
            array(
                'name'       => $sanitized['name'],
                'slug'       => $sanitized['slug'],
                'type'       => $sanitized['type'] ?? 'task',
                'color'      => $sanitized['color'] ?? '#6366f1',
                'icon'       => $sanitized['icon'] ?? '',
                'sort_order' => $max_order + 1,
                'is_default' => $sanitized['is_default'] ?? 0,
                'is_completed' => $sanitized['is_completed'] ?? 0,
                'created_at' => current_time( 'mysql' ),
            ),
            array( '%s', '%s', '%s', '%s', '%s', '%d', '%d', '%d', '%s' )
        );
        
        if ( false === $result ) {
            return new \WP_Error( 'db_error', esc_html__( 'Failed to create workflow stage.', 'agency-os-ai' ) );
        }
        
        return $wpdb->insert_id;
    }
    
    public function update( int $id, array $data ): bool|\WP_Error {
        global $wpdb;
        $table = $this->get_table();
        
        $stage = $this->get( $id );
        if ( ! $stage ) {
            return new \WP_Error( 'not_found', esc_html__( 'Stage not found.', 'agency-os-ai' ) );
        }
        
        $sanitized = $this->sanitize_input( $data );
        
        if ( empty( $sanitized ) ) {
            return true;
        }
        
        $format = array();
        foreach ( $sanitized as $key => $value ) {
            if ( in_array( $key, array( 'sort_order', 'is_default', 'is_completed' ), true ) ) {
                $format[] = '%d';
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
            return new \WP_Error( 'db_error', esc_html__( 'Failed to update workflow stage.', 'agency-os-ai' ) );
        }
        
        return true;
    }
    
    public function delete( int $id ): bool {
        global $wpdb;
        $table = $this->get_table();
        
        $stage = $this->get( $id );
        if ( ! $stage ) {
            return false;
        }
        
        if ( (int) $stage['is_default'] === 1 ) {
            return false;
        }
        
        $result = $wpdb->delete( $table, array( 'id' => $id ), array( '%d' ) );
        
        return $result !== false;
    }
    
    public function reorder( array $stage_ids ): bool {
        global $wpdb;
        $table = $this->get_table();
        
        foreach ( $stage_ids as $index => $id ) {
            $wpdb->update(
                $table,
                array( 'sort_order' => $index + 1 ),
                array( 'id' => absint( $id ) ),
                array( '%d' ),
                array( '%d' )
            );
        }
        
        return true;
    }
    
    public function get_default_stages(): array {
        return array(
            array(
                'name'         => 'Backlog',
                'slug'         => 'backlog',
                'type'         => 'task',
                'color'        => '#64748b',
                'icon'         => 'inbox',
                'sort_order'   => 1,
                'is_default'   => 1,
                'is_completed' => 0,
            ),
            array(
                'name'         => 'To Do',
                'slug'         => 'todo',
                'type'         => 'task',
                'color'        => '#3b82f6',
                'icon'         => 'list-todo',
                'sort_order'   => 2,
                'is_default'   => 1,
                'is_completed' => 0,
            ),
            array(
                'name'         => 'In Progress',
                'slug'         => 'in_progress',
                'type'         => 'task',
                'color'        => '#f59e0b',
                'icon'         => 'loader',
                'sort_order'   => 3,
                'is_default'   => 1,
                'is_completed' => 0,
            ),
            array(
                'name'         => 'In Review',
                'slug'         => 'in_review',
                'type'         => 'task',
                'color'        => '#8b5cf6',
                'icon'         => 'eye',
                'sort_order'   => 4,
                'is_default'   => 1,
                'is_completed' => 0,
            ),
            array(
                'name'         => 'Completed',
                'slug'         => 'completed',
                'type'         => 'task',
                'color'        => '#10b981',
                'icon'         => 'check-circle',
                'sort_order'   => 5,
                'is_default'   => 1,
                'is_completed' => 1,
            ),
        );
    }
    
    private function sanitize_input( array $input ): array {
        $sanitized = array();
        
        $allowed_fields = array( 'name', 'slug', 'type', 'color', 'icon', 'sort_order', 'is_default', 'is_completed' );
        
        foreach ( $input as $key => $value ) {
            if ( ! in_array( $key, $allowed_fields, true ) ) {
                continue;
            }
            
            switch ( $key ) {
                case 'name':
                    $sanitized[ $key ] = sanitize_text_field( (string) $value );
                    break;
                case 'slug':
                    $sanitized[ $key ] = sanitize_title( (string) $value );
                    break;
                case 'type':
                    $allowed_types = array( 'task', 'ticket', 'project' );
                    $sanitized[ $key ] = in_array( $value, $allowed_types, true ) ? $value : 'task';
                    break;
                case 'color':
                    $sanitized[ $key ] = preg_match( '/^#[a-fA-F0-9]{6}$/', $value ) ? $value : '#6366f1';
                    break;
                case 'sort_order':
                case 'is_default':
                case 'is_completed':
                    $sanitized[ $key ] = absint( $value );
                    break;
                default:
                    $sanitized[ $key ] = sanitize_text_field( (string) $value );
            }
        }
        
        return $sanitized;
    }
}
