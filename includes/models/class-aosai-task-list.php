<?php
if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

class AOSAI_Task_List {
    use AOSAI_Singleton;
    
    public function get_table(): string {
        global $wpdb;
        return esc_sql( $wpdb->prefix . 'aosai_task_lists' );
    }
    
    public function get_project_lists( int $project_id ): array {
        global $wpdb;
        $table = $this->get_table();
        
        $lists = $wpdb->get_results(
            $wpdb->prepare(
                'SELECT * FROM ' . $table . ' WHERE project_id = %d ORDER BY sort_order ASC',
                $project_id
            ),
            ARRAY_A
        );
        
        foreach ( $lists as &$list ) {
            $list = $this->enrich_list( $list );
        }
        
        return $lists;
    }
    
    public function get( int $id ): ?array {
        global $wpdb;
        $table = $this->get_table();
        
        $list = $wpdb->get_row(
            $wpdb->prepare( 'SELECT * FROM ' . $table . ' WHERE id = %d', $id ),
            ARRAY_A
        );
        
        if ( ! $list ) {
            return null;
        }
        
        return $this->enrich_list( $list );
    }
    
    public function create( array $data ): int|\WP_Error {
        global $wpdb;
        $table = $this->get_table();
        
        $title = sanitize_text_field( wp_unslash( $data['title'] ?? $data['name'] ?? '' ) );
        if ( empty( $title ) ) {
            return new \WP_Error( 'missing_title', esc_html__( 'Task list title is required.', 'agency-os-ai' ) );
        }
        
        $project_id = absint( $data['project_id'] ?? 0 );
        if ( ! $project_id ) {
            return new \WP_Error( 'missing_project', esc_html__( 'Project ID is required.', 'agency-os-ai' ) );
        }
        
        $max_order = $wpdb->get_var(
            $wpdb->prepare(
                'SELECT COALESCE(MAX(sort_order), 0) FROM ' . $table . ' WHERE project_id = %d',
                $project_id
            )
        );
        
        $result = $wpdb->insert(
            $table,
            array(
                'project_id'   => $project_id,
                'milestone_id' => isset( $data['milestone_id'] ) ? absint( $data['milestone_id'] ) : null,
                'title'       => $title,
                'description' => wp_kses_post( wp_unslash( $data['description'] ?? '' ) ),
                'sort_order'  => $max_order + 1,
                'created_by' => get_current_user_id(),
            ),
            array( '%d', '%d', '%s', '%s', '%d', '%d' )
        );
        
        if ( false === $result ) {
            return new \WP_Error( 'db_error', esc_html__( 'Failed to create task list.', 'agency-os-ai' ) );
        }
        
        $list_id = $wpdb->insert_id;
        
        aosai_log_activity( array(
            'project_id'  => $project_id,
            'action'     => 'created',
            'object_type'=> 'task_list',
            'object_id'  => $list_id,
        ) );
        
        return $list_id;
    }
    
    public function update( int $id, array $data ): bool|\WP_Error {
        global $wpdb;
        $table = $this->get_table();
        
        $list = $this->get( $id );
        if ( ! $list ) {
            return new \WP_Error( 'not_found', esc_html__( 'Task list not found.', 'agency-os-ai' ) );
        }
        
        $update = array( 'updated_at' => current_time( 'mysql' ) );
        
        if ( isset( $data['title'] ) || isset( $data['name'] ) ) {
            $update['title'] = sanitize_text_field( wp_unslash( $data['title'] ?? $data['name'] ) );
        }
        if ( isset( $data['description'] ) ) {
            $update['description'] = wp_kses_post( wp_unslash( $data['description'] ) );
        }
        if ( isset( $data['milestone_id'] ) ) {
            $update['milestone_id'] = $data['milestone_id'] ? absint( $data['milestone_id'] ) : null;
        }
        if ( isset( $data['sort_order'] ) ) {
            $update['sort_order'] = intval( $data['sort_order'] );
        }
        
        $format = array();
        foreach ( $update as $key => $value ) {
            $format[] = is_int( $value ) || is_null( $value ) ? '%d' : '%s';
        }
        
        $result = $wpdb->update(
            $table,
            $update,
            array( 'id' => $id ),
            $format,
            array( '%d' )
        );
        
        if ( false === $result ) {
            return new \WP_Error( 'db_error', esc_html__( 'Failed to update task list.', 'agency-os-ai' ) );
        }
        
        aosai_log_activity( array(
            'project_id'  => $list['project_id'],
            'action'     => 'updated',
            'object_type'=> 'task_list',
            'object_id'  => $id,
        ) );
        
        return true;
    }
    
    public function delete( int $id ): bool {
        global $wpdb;
        $table = $this->get_table();
        
        $list = $this->get( $id );
        if ( ! $list ) {
            return false;
        }
        
        $tasks_table = esc_sql( $wpdb->prefix . 'aosai_tasks' );
        $wpdb->delete( $tasks_table, array( 'task_list_id' => $id ), array( '%d' ) );
        
        $result = $wpdb->delete( $table, array( 'id' => $id ), array( '%d' ) );
        
        if ( $result !== false ) {
            aosai_log_activity( array(
                'project_id'  => $list['project_id'],
                'action'     => 'deleted',
                'object_type'=> 'task_list',
                'object_id'  => $id,
            ) );
        }
        
        return $result !== false;
    }
    
    private function get_list_tasks( int $list_id ): array {
        global $wpdb;
        $tasks_table = esc_sql( $wpdb->prefix . 'aosai_tasks' );
        $users_table = esc_sql( $wpdb->users );
        
        return $wpdb->get_results(
            $wpdb->prepare(
                'SELECT t.*, u.display_name as creator_name
                FROM ' . $tasks_table . ' t
                LEFT JOIN ' . $users_table . ' u ON t.created_by = u.ID
                WHERE t.task_list_id = %d
                ORDER BY t.sort_order ASC',
                $list_id
            ),
            ARRAY_A
        );
    }
    
    private function calculate_progress( int $list_id ): float {
        global $wpdb;
        $tasks_table = esc_sql( $wpdb->prefix . 'aosai_tasks' );
        
        $total = $wpdb->get_var(
            $wpdb->prepare( 'SELECT COUNT(*) FROM ' . $tasks_table . ' WHERE task_list_id = %d', $list_id )
        );
        
        if ( (int) $total === 0 ) {
            return 0.0;
        }
        
        $completed = $wpdb->get_var(
            $wpdb->prepare( 'SELECT COUNT(*) FROM ' . $tasks_table . " WHERE task_list_id = %d AND status IN ('done','completed')", $list_id )
        );
        
        return round( ( $completed / $total ) * 100, 1 );
    }

    private function enrich_list( array $list ): array {
        $list_id = (int) $list['id'];
        $tasks   = $this->get_list_tasks( $list_id );

        $list['name']                 = $list['title'];
        $list['position']             = isset( $list['sort_order'] ) ? (int) $list['sort_order'] : 0;
        $list['tasks']                = $tasks;
        $list['task_count']           = count( $tasks );
        $list['completed_task_count'] = count(
            array_filter(
                $tasks,
                static fn( array $task ): bool => in_array( $task['status'] ?? '', array( 'done', 'completed' ), true )
            )
        );
        $list['progress']             = $this->calculate_progress( $list_id );

        return $list;
    }
}
