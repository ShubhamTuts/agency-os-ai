<?php
if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

class AOSAI_Task {
    use AOSAI_Singleton;
    
    private function __construct() {}
    
    public function get_table(): string {
        global $wpdb;
        return $wpdb->prefix . 'aosai_tasks';
    }
    
    public function get_project_tasks( int $project_id, array $args = array() ): array {
        global $wpdb;
        $table = $this->get_table();
        
        $defaults = array(
            'page'        => 1,
            'per_page'    => 50,
            'task_list_id'=> '',
            'status'      => '',
            'priority'    => '',
            'assigned_to' => '',
            'search'      => '',
            'due_before'  => '',
            'due_after'   => '',
            'orderby'     => 'sort_order',
            'order'       => 'ASC',
        );
        $args = wp_parse_args( $args, $defaults );
        
        $offset = ( $args['page'] - 1 ) * $args['per_page'];
        
        $where = "WHERE project_id = %d";
        $params = array( $project_id );
        
        if ( ! empty( $args['task_list_id'] ) ) {
            $where .= " AND task_list_id = %d";
            $params[] = absint( $args['task_list_id'] );
        }
        
        if ( ! empty( $args['status'] ) ) {
            $statuses = array_map( 'trim', explode( ',', $args['status'] ) );
            $placeholders = implode( ',', array_fill( 0, count( $statuses ), '%s' ) );
            $where .= " AND status IN ({$placeholders})";
            foreach ( $statuses as $s ) {
                $params[] = sanitize_key( $s );
            }
        }
        
        if ( ! empty( $args['priority'] ) ) {
            $priorities = array_map( 'trim', explode( ',', $args['priority'] ) );
            $placeholders = implode( ',', array_fill( 0, count( $priorities ), '%s' ) );
            $where .= " AND priority IN ({$placeholders})";
            foreach ( $priorities as $p ) {
                $params[] = sanitize_key( $p );
            }
        }
        
        if ( ! empty( $args['assigned_to'] ) ) {
            $tu_table = $wpdb->prefix . 'aosai_task_users';
            $where .= " AND id IN (SELECT task_id FROM {$tu_table} WHERE user_id = %d)";
            $params[] = absint( $args['assigned_to'] );
        }
        
        if ( ! empty( $args['search'] ) ) {
            $search = '%' . $wpdb->esc_like( sanitize_text_field( $args['search'] ) ) . '%';
            $where .= " AND (title LIKE %s OR description LIKE %s)";
            $params[] = $search;
            $params[] = $search;
        }
        
        if ( ! empty( $args['due_before'] ) ) {
            $where .= " AND due_date <= %s";
            $params[] = sanitize_text_field( $args['due_before'] );
        }
        
        if ( ! empty( $args['due_after'] ) ) {
            $where .= " AND due_date >= %s";
            $params[] = sanitize_text_field( $args['due_after'] );
        }
        
        $orderby = sanitize_sql_orderby( $args['orderby'] . ' ' . $args['order'] );
        
        $params[] = $args['per_page'];
        $params[] = $offset;

        $sql = $wpdb->prepare(
            "SELECT * FROM {$table} {$where} ORDER BY {$orderby} LIMIT %d OFFSET %d",
            ...$params
        );

        $tasks = $wpdb->get_results( $sql, ARRAY_A );

        foreach ( $tasks as &$task ) {
            $task = $this->enrich( $task );
        }

        return $tasks;
    }
    
    public function get_my_tasks( int $user_id, array $args = array() ): array {
        global $wpdb;
        $table = $this->get_table();
        $tu_table = $wpdb->prefix . 'aosai_task_users';
        $pu_table = $wpdb->prefix . 'aosai_project_users';
        
        $defaults = array(
            'page'     => 1,
            'per_page' => 20,
            'status'   => '',
        );
        $args = wp_parse_args( $args, $defaults );
        
        $offset = ( $args['page'] - 1 ) * $args['per_page'];
        
        $where = "WHERE tu.user_id = %d AND p.id IS NOT NULL";
        // Params for the WHERE clause
        $params = array( $user_id );
        
        if ( ! empty( $args['status'] ) ) {
            $statuses = array_map('trim', explode(',', $args['status']));
            $placeholders = implode( ',', array_fill( 0, count( $statuses ), '%s' ) );
            $where .= " AND t.status IN ($placeholders)";
            $params = array_merge($params, $statuses);
        }
        
        // Final ordered parameters for wpdb->prepare
        $final_params = array_merge( array( $user_id ), $params, array( $args['per_page'], $offset ) );

        $sql = $wpdb->prepare(
            "SELECT t.*, p.title as project_name, p.color as project_color
            FROM {$table} t
            INNER JOIN {$tu_table} tu ON t.id = tu.task_id
            INNER JOIN {$pu_table} pu ON t.project_id = pu.project_id AND pu.user_id = %d
            LEFT JOIN {$wpdb->prefix}aosai_projects p ON t.project_id = p.id
            {$where}
            ORDER BY t.due_date ASC, FIELD(t.priority, 'urgent', 'high', 'medium', 'low')
            LIMIT %d OFFSET %d",
            ...$final_params
        );

        $tasks = $wpdb->get_results( $sql, ARRAY_A );

        foreach ( $tasks as &$task ) {
            $task = $this->enrich( $task );
        }

        return $tasks;
    }
    
    public function get( int $id ): ?array {
        global $wpdb;
        $table = $this->get_table();
        
        $task = $wpdb->get_row(
            $wpdb->prepare( "SELECT * FROM {$table} WHERE id = %d", $id ),
            ARRAY_A
        );
        
        if ( ! $task ) {
            return null;
        }
        
        return $this->enrich( $task );
    }
    
    public function create( array $data ): int|\WP_Error {
        global $wpdb;
        $table = $this->get_table();
        
        $sanitized = $this->sanitize_input( $data );
        
        if ( empty( $sanitized['title'] ) ) {
            return new \WP_Error( 'missing_title', esc_html__( 'Task title is required.', 'agency-os-ai' ) );
        }
        
        $max_order = $wpdb->get_var(
            $wpdb->prepare(
                "SELECT COALESCE(MAX(sort_order), 0) FROM {$table} WHERE task_list_id = %d",
                $sanitized['task_list_id']
            )
        );
        
        $result = $wpdb->insert(
            $table,
            array(
                'project_id'     => $sanitized['project_id'],
                'task_list_id'   => $sanitized['task_list_id'],
                'parent_id'      => $sanitized['parent_id'] ?? null,
                'title'          => $sanitized['title'],
                'description'    => $sanitized['description'] ?? '',
                'status'         => $sanitized['status'] ?? 'open',
                'priority'       => $sanitized['priority'] ?? 'medium',
                'start_date'     => $sanitized['start_date'] ?? null,
                'due_date'       => $sanitized['due_date'] ?? null,
                'estimated_hours'=> $sanitized['estimated_hours'] ?? 0,
                'sort_order'     => $max_order + 1,
                'is_private'     => $sanitized['is_private'] ?? 0,
                'kanban_column'  => $sanitized['kanban_column'] ?? 'open',
                'created_by'    => get_current_user_id(),
            ),
            array( '%d', '%d', '%d', '%s', '%s', '%s', '%s', '%s', '%s', '%f', '%d', '%d', '%s', '%d' )
        );
        
        if ( false === $result ) {
            return new \WP_Error( 'db_error', esc_html__( 'Failed to create task.', 'agency-os-ai' ) );
        }
        
        $task_id = $wpdb->insert_id;
        
        $assignees = ! empty( $data['assignees'] ) ? (array) $data['assignees'] : array();
        if ( empty( $assignees ) && ! empty( $data['assignee_id'] ) ) {
            $assignees = array( absint( $data['assignee_id'] ) );
        }

        if ( ! empty( $assignees ) ) {
            $this->set_assignees( $task_id, $assignees );
        }
        
        aosai_log_activity( array(
            'project_id'  => $sanitized['project_id'],
            'action'     => 'created',
            'object_type'=> 'task',
            'object_id'  => $task_id,
        ) );

        do_action( 'aosai_task_created', $task_id, $this->get( $task_id ) );
        
        return $task_id;
    }
    
    public function update( int $id, array $data ): bool|\WP_Error {
        global $wpdb;
        $table = $this->get_table();
        
        $task = $this->get( $id );
        if ( ! $task ) {
            return new \WP_Error( 'not_found', esc_html__( 'Task not found.', 'agency-os-ai' ) );
        }
        
        $sanitized = $this->sanitize_input( $data );
        
        if (empty($sanitized)) {
            return true; // Nothing to update
        }

        $sanitized['updated_at'] = current_time( 'mysql' );
        
        // Handle status mapping for backwards compatibility
        if (isset($sanitized['status']) && $sanitized['status'] === 'done') {
            $sanitized['status'] = 'completed';
        }

        $done_statuses = array( 'completed' );
        if ( isset( $sanitized['status'] ) && in_array( $sanitized['status'], $done_statuses, true ) && ! in_array( $task['status'], $done_statuses, true ) ) {
            $sanitized['completed_at'] = current_time( 'mysql' );
            $sanitized['completed_by'] = get_current_user_id();
        }
        
        $result = $wpdb->update(
            $table,
            $sanitized,
            array( 'id' => $id )
        );
        
        if ( false === $result ) {
            return new \WP_Error( 'db_error', esc_html__( 'Failed to update task.', 'agency-os-ai' ) );
        }
        
        if ( isset( $data['assignees'] ) ) {
            $this->set_assignees( $id, $data['assignees'] );
        } elseif ( isset( $data['assignee_id'] ) ) {
            $assignee_id = absint( $data['assignee_id'] );
            $this->set_assignees( $id, $assignee_id ? array( $assignee_id ) : array() );
        }
        
        aosai_log_activity( array(
            'project_id'  => $task['project_id'],
            'action'     => 'updated',
            'object_type'=> 'task',
            'object_id'  => $id,
            'meta'       => array_diff_key( $data, array_flip( array( 'assignees' ) ) ),
        ) );

        do_action( 'aosai_task_updated', $id, $task, $this->get( $id ) );

        if ( isset( $sanitized['status'] ) && in_array( $sanitized['status'], array( 'completed', 'done' ), true ) && ! in_array( $task['status'], array( 'completed', 'done' ), true ) ) {
            do_action( 'aosai_task_completed', $id, get_current_user_id() );
        }
        
        return true;
    }
    
    public function delete( int $id ): bool {
        global $wpdb;
        $table = $this->get_table();
        
        $task = $this->get( $id );
        if ( ! $task ) {
            return false;
        }
        
        $tu_table = $wpdb->prefix . 'aosai_task_users';
        $tm_table = $wpdb->prefix . 'aosai_task_meta';
        
        $wpdb->delete( $tu_table, array( 'task_id' => $id ), array( '%d' ) );
        $wpdb->delete( $tm_table, array( 'task_id' => $id ), array( '%d' ) );
        
        $result = $wpdb->delete( $table, array( 'id' => $id ), array( '%d' ) );
        
        if ( $result !== false ) {
            aosai_log_activity( array(
                'project_id'  => $task['project_id'],
                'action'     => 'deleted',
                'object_type'=> 'task',
                'object_id'  => $id,
            ) );
            do_action( 'aosai_task_deleted', $id );
        }
        
        return $result !== false;
    }
    
    public function get_assignees( int $task_id ): array {
        global $wpdb;
        $tu_table = $wpdb->prefix . 'aosai_task_users';
        
        return $wpdb->get_results(
            $wpdb->prepare(
                "SELECT u.ID as user_id, u.display_name, u.user_email 
                FROM {$tu_table} tu 
                INNER JOIN {$wpdb->users} u ON tu.user_id = u.ID 
                WHERE tu.task_id = %d",
                $task_id
            ),
            ARRAY_A
        );
    }
    
    public function assign_user( int $task_id, int $user_id ): bool {
        global $wpdb;
        $table = $wpdb->prefix . 'aosai_task_users';
        
        $existing = $wpdb->get_row(
            $wpdb->prepare(
                "SELECT id FROM {$table} WHERE task_id = %d AND user_id = %d",
                $task_id,
                $user_id
            )
        );
        
        if ( $existing ) {
            return true;
        }
        
        $wpdb->insert(
            $table,
            array( 'task_id' => $task_id, 'user_id' => $user_id ),
            array( '%d', '%d' )
        );
        
        $task = $this->get( $task_id );
        if ( $task ) {
            do_action( 'aosai_task_assigned', $task_id, $user_id );
        }
        
        return true;
    }
    
    public function unassign_user( int $task_id, int $user_id ): bool {
        global $wpdb;
        $table = $wpdb->prefix . 'aosai_task_users';
        
        $result = $wpdb->delete(
            $table,
            array( 'task_id' => $task_id, 'user_id' => $user_id ),
            array( '%d', '%d' )
        );
        
        return $result !== false;
    }
    
    public function set_assignees( int $task_id, array $user_ids ): void {
        global $wpdb;
        $table = $wpdb->prefix . 'aosai_task_users';
        
        $wpdb->delete( $table, array( 'task_id' => $task_id ), array( '%d' ) );
        
        foreach ( array_filter( array_map( 'absint', (array) $user_ids ) ) as $user_id ) {
            $this->assign_user( $task_id, $user_id );
        }
    }
    
    public function enrich( array $task ): array {
        $id = (int) $task['id'];

        $task['assignees']         = $this->get_assignees( $id );
        $task['comments_count']    = $this->get_comments_count( $id );
        $task['attachments_count'] = $this->get_attachments_count( $id );
        $task['position']          = isset( $task['sort_order'] ) ? (int) $task['sort_order'] : 0;

        // Convenient single-assignee fields
        $first = $task['assignees'][0] ?? null;
        $task['assignee_id']   = $first ? (int) $first['user_id'] : null;
        $task['assignee_name'] = $first ? $first['display_name'] : '';

        // Project name
        if ( empty( $task['project_name'] ) && ! empty( $task['project_id'] ) ) {
            global $wpdb;
            $task['project_name'] = (string) $wpdb->get_var(
                $wpdb->prepare( "SELECT title FROM {$wpdb->prefix}aosai_projects WHERE id = %d", $task['project_id'] )
            );
        }

        // Task list name
        if ( ! empty( $task['task_list_id'] ) && empty( $task['task_list_name'] ) ) {
            global $wpdb;
            $task['task_list_name'] = (string) $wpdb->get_var(
                $wpdb->prepare( "SELECT title FROM {$wpdb->prefix}aosai_task_lists WHERE id = %d", $task['task_list_id'] )
            );
        } else {
            $task['task_list_name'] = $task['task_list_name'] ?? '';
        }
        $task['tags'] = AOSAI_Tag::get_instance()->get_object_tags( 'task', $id );

        return $task;
    }

    private function get_comments_count( int $task_id ): int {
        global $wpdb;
        return (int) $wpdb->get_var(
            $wpdb->prepare(
                "SELECT COUNT(*) FROM {$wpdb->prefix}aosai_comments WHERE commentable_type = 'task' AND commentable_id = %d",
                $task_id
            )
        );
    }
    
    private function get_attachments_count( int $task_id ): int {
        global $wpdb;
        return (int) $wpdb->get_var(
            $wpdb->prepare(
                "SELECT COUNT(*) FROM {$wpdb->prefix}aosai_files WHERE fileable_type = 'task' AND fileable_id = %d",
                $task_id
            )
        );
    }
    
    private function sanitize_input( array $input ): array {
        $sanitized = array();
        $allowed_fields = [
            'title' => 'sanitize_text_field', 'description' => 'wp_kses_post', 'status' => 'sanitize_key',
            'priority' => 'sanitize_key', 'due_date' => 'sanitize_text_field', 'start_date' => 'sanitize_text_field',
            'estimated_hours' => 'floatval', 'sort_order' => 'intval', 'is_private' => 'intval',
            'kanban_column' => 'sanitize_key', 'task_list_id' => 'absint', 'project_id' => 'absint',
            'parent_id' => 'absint'
        ];
        $status_options = array( 'open', 'backlog', 'todo', 'in_progress', 'in_review', 'done', 'completed', 'cancelled' );
        $priority_options = array( 'low', 'medium', 'high', 'urgent' );

        foreach ( $input as $key => $value ) {
            if ( array_key_exists( $key, $allowed_fields ) ) {
                $sanitized[ $key ] = call_user_func( $allowed_fields[ $key ], wp_unslash( $value ) );
            }
        }
        
        if ( isset( $sanitized['status'] ) ) {
            if ($sanitized['status'] === 'done') {
                $sanitized['status'] = 'completed'; // Compatibility
            }
            if ( ! in_array( $sanitized['status'], $status_options, true ) ) {
                $sanitized['status'] = 'todo';
            }
        }
        if ( isset($sanitized['priority']) && !in_array($sanitized['priority'], $priority_options, true) ) {
            $sanitized['priority'] = 'medium';
        }
        if ( isset( $sanitized['due_date'] ) && ! preg_match( '/^\d{4}-\d{2}-\d{2}$/', $sanitized['due_date'] ) ) {
            $sanitized['due_date'] = null;
        }
        if ( isset( $sanitized['start_date'] ) && ! preg_match( '/^\d{4}-\d{2}-\d{2}$/', $sanitized['start_date'] ) ) {
            $sanitized['start_date'] = null;
        }
        if ( isset( $sanitized['parent_id'] ) && $sanitized['parent_id'] === 0 ) {
            $sanitized['parent_id'] = null;
        }
        
        return $sanitized;
    }
}
