<?php
if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

class AOSAI_Milestone {
    use AOSAI_Singleton;
    
    public function get_table(): string {
        global $wpdb;
        return esc_sql( $wpdb->prefix . 'aosai_milestones' );
    }
    
    public function get_all_for_user( int $user_id, array $args = array() ): array {
        global $wpdb;
        $table    = $this->get_table();
        $pu_table = esc_sql( $wpdb->prefix . 'aosai_project_users' );
        $p_table  = esc_sql( $wpdb->prefix . 'aosai_projects' );

        $defaults = array(
            'page'     => 1,
            'per_page' => 50,
            'status'   => '',
        );
        $args   = wp_parse_args( $args, $defaults );
        $offset = ( $args['page'] - 1 ) * $args['per_page'];

        $params = array( $user_id );
        $sql    = 'SELECT m.*, p.title as project_title
                FROM ' . $table . ' m
                INNER JOIN ' . $p_table . ' p ON m.project_id = p.id
                INNER JOIN ' . $pu_table . ' pu ON m.project_id = pu.project_id
                WHERE pu.user_id = %d';

        if ( ! empty( $args['status'] ) ) {
            $sql     .= ' AND m.status = %s';
            $params[] = sanitize_key( $args['status'] );
        }

        $sql     .= ' ORDER BY m.due_date ASC LIMIT %d OFFSET %d';
        $params[] = $args['per_page'];
        $params[] = $offset;

        $milestones = $wpdb->get_results(
            $wpdb->prepare( $sql, $params ),
            ARRAY_A
        );

        foreach ( $milestones as &$milestone ) {
            $milestone = $this->enrich( $milestone );
        }

        return $milestones;
    }

    public function get_project_milestones( int $project_id ): array {
        global $wpdb;
        $table = $this->get_table();

        $milestones = $wpdb->get_results(
            $wpdb->prepare(
                'SELECT * FROM ' . $table . ' WHERE project_id = %d ORDER BY sort_order ASC, due_date ASC',
                $project_id
            ),
            ARRAY_A
        );

        foreach ( $milestones as &$milestone ) {
            $milestone = $this->enrich( $milestone );
        }

        return $milestones;
    }

    public function get( int $id ): ?array {
        global $wpdb;
        $table = $this->get_table();

        $milestone = $wpdb->get_row(
            $wpdb->prepare( 'SELECT * FROM ' . $table . ' WHERE id = %d', $id ),
            ARRAY_A
        );

        if ( ! $milestone ) {
            return null;
        }

        return $this->enrich( $milestone );
    }

    public function enrich( array $milestone ): array {
        $id = (int) $milestone['id'];
        $milestone['name'] = $milestone['title'];

        if ( empty( $milestone['project_name'] ) && ! empty( $milestone['project_id'] ) ) {
            global $wpdb;
            $projects_table = esc_sql( $wpdb->prefix . 'aosai_projects' );
            $milestone['project_name'] = (string) $wpdb->get_var(
                $wpdb->prepare( 'SELECT title FROM ' . $projects_table . ' WHERE id = %d', $milestone['project_id'] )
            );
        }
        
        $progress = $this->calculate_progress( $id );
        $milestone['task_count']           = $progress['total'];
        $milestone['completed_task_count'] = $progress['completed'];
        $milestone['progress']             = $progress['percentage'];

        return $milestone;
    }
    
    private function calculate_progress( int $milestone_id ): array {
        global $wpdb;
        $tasks_table = esc_sql( $wpdb->prefix . 'aosai_tasks' );
        $lists_table = esc_sql( $wpdb->prefix . 'aosai_task_lists' );
        
        $stats = $wpdb->get_row(
            $wpdb->prepare(
                "SELECT 
                    COUNT(t.id) as total,
                    SUM(CASE WHEN t.status IN ('done','completed') THEN 1 ELSE 0 END) as completed
                FROM " . $tasks_table . " t
                INNER JOIN " . $lists_table . " l ON t.task_list_id = l.id
                WHERE l.milestone_id = %d",
                $milestone_id
            ),
            ARRAY_A
        );
        
        $total = (int) ($stats['total'] ?? 0);
        $completed = (int) ($stats['completed'] ?? 0);
        
        return array(
            'total' => $total,
            'completed' => $completed,
            'percentage' => $total > 0 ? round( ( $completed / $total ) * 100, 1 ) : 0.0,
        );
    }

    public function create( array $data ): int|\WP_Error {
        global $wpdb;
        $table = $this->get_table();

        $sanitized = $this->sanitize_input($data);
        
        if ( empty( $sanitized['title'] ) ) {
            return new \WP_Error( 'missing_title', esc_html__( 'Milestone name is required.', 'agency-os-ai' ) );
        }
        
        if ( empty( $sanitized['project_id'] ) ) {
            return new \WP_Error( 'missing_project', esc_html__( 'Project ID is required.', 'agency-os-ai' ) );
        }
        
        $max_order = $wpdb->get_var(
            $wpdb->prepare(
                'SELECT COALESCE(MAX(sort_order), 0) FROM ' . $table . ' WHERE project_id = %d',
                $sanitized['project_id']
            )
        );
        
        $sanitized['sort_order'] = $max_order + 1;
        $sanitized['created_by'] = get_current_user_id();
        $sanitized['status'] = 'upcoming';

        $result = $wpdb->insert( $table, $sanitized );
        
        if ( false === $result ) {
            return new \WP_Error( 'db_error', esc_html__( 'Failed to create milestone.', 'agency-os-ai' ) );
        }
        
        $milestone_id = $wpdb->insert_id;
        
        aosai_log_activity( array(
            'project_id'  => $sanitized['project_id'],
            'action'     => 'created',
            'object_type'=> 'milestone',
            'object_id'  => $milestone_id,
        ) );
        
        return $milestone_id;
    }
    
    public function update( int $id, array $data ): bool|\WP_Error {
        global $wpdb;
        $table = $this->get_table();
        
        $milestone = $this->get( $id );
        if ( ! $milestone ) {
            return new \WP_Error( 'not_found', esc_html__( 'Milestone not found.', 'agency-os-ai' ) );
        }
        
        $sanitized = $this->sanitize_input( $data );
        if ( empty($sanitized) ) {
            return true;
        }
        $sanitized['updated_at'] = current_time( 'mysql' );
        
        if ( isset( $sanitized['status'] ) && $sanitized['status'] === 'completed' && $milestone['status'] !== 'completed' ) {
            $sanitized['completed_at'] = current_time( 'mysql' );
        }
        
        $this->auto_update_status( $id, $sanitized );
        
        $result = $wpdb->update( $table, $sanitized, array( 'id' => $id ) );
        
        if ( false === $result ) {
            return new \WP_Error( 'db_error', esc_html__( 'Failed to update milestone.', 'agency-os-ai' ) );
        }
        
        aosai_log_activity( array(
            'project_id'  => $milestone['project_id'],
            'action'     => 'updated',
            'object_type'=> 'milestone',
            'object_id'  => $id,
        ) );
        
        return true;
    }
    
    public function delete( int $id ): bool {
        global $wpdb;
        $table = $this->get_table();
        
        $milestone = $this->get( $id );
        if ( ! $milestone ) {
            return false;
        }
        
        $lists_table = esc_sql( $wpdb->prefix . 'aosai_task_lists' );
        $wpdb->update( $lists_table, array( 'milestone_id' => null ), array( 'milestone_id' => $id ), array( '%d' ), array( '%d' ) );
        
        $result = $wpdb->delete( $table, array( 'id' => $id ), array( '%d' ) );
        
        if ( $result !== false ) {
            aosai_log_activity( array(
                'project_id'  => $milestone['project_id'],
                'action'     => 'deleted',
                'object_type'=> 'milestone',
                'object_id'  => $id,
            ) );
        }
        
        return $result !== false;
    }
    
    private function auto_update_status( int $milestone_id, array &$update_data ): void {
        $progress = $this->calculate_progress( $milestone_id );
        $now = current_time( 'Y-m-d' );
        
        $due_date = $update_data['due_date'] ?? $this->get($milestone_id)['due_date'];
        
        $new_status = 'upcoming'; // Default
        
        if ( $progress['percentage'] >= 100 ) {
            $new_status = 'completed';
            if (empty($update_data['completed_at'])) {
                $update_data['completed_at'] = current_time( 'mysql' );
            }
        } elseif ( $due_date && $due_date < $now ) {
            $new_status = 'overdue';
        } elseif ( $progress['percentage'] > 0 ) {
            $new_status = 'in_progress';
        }
        
        if ( !isset($update_data['status']) || $update_data['status'] !== $new_status ) {
            $update_data['status'] = $new_status;
            
            if ( $new_status === 'completed' ) {
                do_action( 'aosai_milestone_completed', $milestone_id, $this->get($milestone_id) );
            }
        }
    }

    private function sanitize_input(array $data): array 
    {
        $sanitized = [];
        $allowed_fields = [
            'project_id' => 'absint',
            'title' => 'sanitize_text_field',
            'name' => 'sanitize_text_field', // Alias for title
            'description' => 'wp_kses_post',
            'due_date' => 'sanitize_text_field',
            'sort_order' => 'intval',
            'status' => 'sanitize_key',
        ];
        $status_options = array( 'upcoming', 'in_progress', 'completed', 'overdue' );

        if (isset($data['name']) && !isset($data['title'])) {
            $data['title'] = $data['name'];
        }
        unset($data['name']);

        foreach ($data as $key => $value) {
            if (array_key_exists($key, $allowed_fields)) {
                 $sanitized[$key] = call_user_func($allowed_fields[$key], wp_unslash($value));
            }
        }

        if ( isset( $sanitized['due_date'] ) && ! preg_match( '/^\d{4}-\d{2}-\d{2}$/', $sanitized['due_date'] ) ) {
            $sanitized['due_date'] = null;
        }
        if (isset($sanitized['status']) && !in_array($sanitized['status'], $status_options, true)) {
            unset($sanitized['status']);
        }

        return $sanitized;
    }
}
