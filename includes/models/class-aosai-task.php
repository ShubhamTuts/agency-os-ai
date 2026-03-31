<?php
if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

class AOSAI_Task {
    use AOSAI_Singleton;
    
    private function __construct() {}
    
    public function get_table(): string {
        global $wpdb;
        return esc_sql( $wpdb->prefix . 'aosai_tasks' );
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
        
        $per_page = max( 1, (int) $args['per_page'] );
        $page     = max( 1, (int) $args['page'] );
        $offset   = ( $page - 1 ) * $per_page;
        $params   = array( $project_id );
        $sql      = 'SELECT * FROM ' . $table . ' WHERE project_id = %d';
        
        if ( ! empty( $args['task_list_id'] ) ) {
            $sql .= ' AND task_list_id = %d';
            $params[] = absint( $args['task_list_id'] );
        }
        
        if ( ! empty( $args['status'] ) ) {
            $statuses = array_map( 'trim', explode( ',', $args['status'] ) );
            $placeholders = implode( ',', array_fill( 0, count( $statuses ), '%s' ) );
            $sql .= ' AND status IN (' . $placeholders . ')';
            foreach ( $statuses as $s ) {
                $params[] = sanitize_key( $s );
            }
        }
        
        if ( ! empty( $args['priority'] ) ) {
            $priorities = array_map( 'trim', explode( ',', $args['priority'] ) );
            $placeholders = implode( ',', array_fill( 0, count( $priorities ), '%s' ) );
            $sql .= ' AND priority IN (' . $placeholders . ')';
            foreach ( $priorities as $p ) {
                $params[] = sanitize_key( $p );
            }
        }
        
        if ( ! empty( $args['assigned_to'] ) ) {
            $tu_table = esc_sql( $wpdb->prefix . 'aosai_task_users' );
            $sql .= ' AND id IN (SELECT task_id FROM ' . $tu_table . ' WHERE user_id = %d)';
            $params[] = absint( $args['assigned_to'] );
        }
        
        if ( ! empty( $args['search'] ) ) {
            $search = '%' . $wpdb->esc_like( sanitize_text_field( $args['search'] ) ) . '%';
            $sql .= ' AND (title LIKE %s OR description LIKE %s)';
            $params[] = $search;
            $params[] = $search;
        }
        
        if ( ! empty( $args['due_before'] ) ) {
            $sql .= ' AND due_date <= %s';
            $params[] = sanitize_text_field( $args['due_before'] );
        }
        
        if ( ! empty( $args['due_after'] ) ) {
            $sql .= ' AND due_date >= %s';
            $params[] = sanitize_text_field( $args['due_after'] );
        }

        $sql .= ' ORDER BY ' . $this->get_order_clause( (string) $args['orderby'], (string) $args['order'] );
        $sql .= ' LIMIT %d OFFSET %d';
        $params[] = $per_page;
        $params[] = $offset;
        $sql = $wpdb->prepare( $sql, $params );

        $tasks = $wpdb->get_results( $sql, ARRAY_A );

        foreach ( $tasks as &$task ) {
            $task = $this->enrich( $task );
        }

        return $tasks;
    }
    
    public function get_my_tasks( int $user_id, array $args = array() ): array {
        global $wpdb;
        $table = $this->get_table();
        $tu_table = esc_sql( $wpdb->prefix . 'aosai_task_users' );
        $pu_table = esc_sql( $wpdb->prefix . 'aosai_project_users' );
        $projects_table = esc_sql( $wpdb->prefix . 'aosai_projects' );
        
        $defaults = array(
            'page'                   => 1,
            'per_page'               => 20,
            'status'                 => '',
            'include_all_if_manager' => false,
        );
        $args = wp_parse_args( $args, $defaults );
        
        $per_page = max( 1, (int) $args['per_page'] );
        $page     = max( 1, (int) $args['page'] );
        $offset   = ( $page - 1 ) * $per_page;

        $has_workspace_scope = ! empty( $args['include_all_if_manager'] ) && (
            user_can( $user_id, 'manage_options' ) ||
            user_can( $user_id, 'aosai_manage_projects' ) ||
            user_can( $user_id, 'aosai_manage_tickets' )
        );

        if ( $has_workspace_scope ) {
            $params = array();
            $sql = 'SELECT t.*, p.title as project_name, p.color as project_color
                FROM ' . $table . ' t
                LEFT JOIN ' . $projects_table . ' p ON t.project_id = p.id
                WHERE p.id IS NOT NULL';

            if ( ! empty( $args['status'] ) ) {
                $statuses = array_map( 'trim', explode( ',', $args['status'] ) );
                $placeholders = implode( ',', array_fill( 0, count( $statuses ), '%s' ) );
                $sql .= ' AND t.status IN (' . $placeholders . ')';
                foreach ( $statuses as $status ) {
                    $params[] = sanitize_key( $status );
                }
            }

            $sql .= " ORDER BY t.due_date ASC, FIELD(t.priority, 'urgent', 'high', 'medium', 'low') LIMIT %d OFFSET %d";
            $params[] = $per_page;
            $params[] = $offset;
            $sql = $wpdb->prepare( $sql, $params );
        } else {
            $params = array( $user_id, $user_id );
            $sql = 'SELECT t.*, p.title as project_name, p.color as project_color
                FROM ' . $table . ' t
                INNER JOIN ' . $tu_table . ' tu ON t.id = tu.task_id
                INNER JOIN ' . $pu_table . ' pu ON t.project_id = pu.project_id AND pu.user_id = %d
                LEFT JOIN ' . $projects_table . ' p ON t.project_id = p.id
                WHERE tu.user_id = %d AND p.id IS NOT NULL';

            if ( ! empty( $args['status'] ) ) {
                $statuses = array_map( 'trim', explode( ',', $args['status'] ) );
                $placeholders = implode( ',', array_fill( 0, count( $statuses ), '%s' ) );
                $sql .= ' AND t.status IN (' . $placeholders . ')';
                foreach ( $statuses as $status ) {
                    $params[] = sanitize_key( $status );
                }
            }

            $sql .= " ORDER BY t.due_date ASC, FIELD(t.priority, 'urgent', 'high', 'medium', 'low') LIMIT %d OFFSET %d";
            $params[] = $per_page;
            $params[] = $offset;
            $sql = $wpdb->prepare( $sql, $params );
        }

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
            $wpdb->prepare( 'SELECT * FROM ' . $table . ' WHERE id = %d', $id ),
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
        $status    = $this->normalize_status_value( (string) ( $sanitized['status'] ?? 'todo' ) );
        $kanban    = $this->normalize_kanban_value( (string) ( $sanitized['kanban_column'] ?? '' ), $status );
        
        if ( empty( $sanitized['title'] ) ) {
            return new \WP_Error( 'missing_title', esc_html__( 'Task title is required.', 'agency-os-ai' ) );
        }
        
        $max_order = $wpdb->get_var(
            $wpdb->prepare(
                'SELECT COALESCE(MAX(sort_order), 0) FROM ' . $table . ' WHERE task_list_id = %d',
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
                'status'         => $status,
                'priority'       => $sanitized['priority'] ?? 'medium',
                'start_date'     => $sanitized['start_date'] ?? null,
                'due_date'       => $sanitized['due_date'] ?? null,
                'estimated_hours'=> $sanitized['estimated_hours'] ?? 0,
                'sort_order'     => $max_order + 1,
                'is_private'     => $sanitized['is_private'] ?? 0,
                'kanban_column'  => $kanban,
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

        $task = $this->get( $task_id );
        do_action( 'aosai_task_created', $task_id, $task );

        if ( $task ) {
            AOSAI_Webhook_Service::get_instance()->dispatch( 'task.created', $task );
        }
        
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

        $done_statuses = array( 'completed' );
        if ( isset( $sanitized['status'] ) && in_array( $sanitized['status'], $done_statuses, true ) && ! in_array( $task['status'], $done_statuses, true ) ) {
            $sanitized['completed_at'] = current_time( 'mysql' );
            $sanitized['completed_by'] = get_current_user_id();
        }

        if ( isset( $sanitized['status'] ) && ! in_array( $sanitized['status'], $done_statuses, true ) && in_array( $task['status'], $done_statuses, true ) ) {
            $sanitized['completed_at'] = null;
            $sanitized['completed_by'] = null;
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

        $updated_task = $this->get( $id );
        do_action( 'aosai_task_updated', $id, $task, $updated_task );

        if ( $updated_task ) {
            AOSAI_Webhook_Service::get_instance()->dispatch( 'task.updated', $updated_task );
        }

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
        
        $tu_table = esc_sql( $wpdb->prefix . 'aosai_task_users' );
        $tm_table = esc_sql( $wpdb->prefix . 'aosai_task_meta' );
        
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
        $tu_table = esc_sql( $wpdb->prefix . 'aosai_task_users' );
        $users_table = esc_sql( $wpdb->users );
        
        return $wpdb->get_results(
            $wpdb->prepare(
                'SELECT u.ID as user_id, u.display_name, u.user_email
                FROM ' . $tu_table . ' tu
                INNER JOIN ' . $users_table . ' u ON tu.user_id = u.ID
                WHERE tu.task_id = %d',
                $task_id
            ),
            ARRAY_A
        );
    }
    
    public function assign_user( int $task_id, int $user_id ): bool {
        global $wpdb;
        $table = esc_sql( $wpdb->prefix . 'aosai_task_users' );
        
        $existing = $wpdb->get_row(
            $wpdb->prepare(
                'SELECT id FROM ' . $table . ' WHERE task_id = %d AND user_id = %d',
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
        $table = esc_sql( $wpdb->prefix . 'aosai_task_users' );
        
        $result = $wpdb->delete(
            $table,
            array( 'task_id' => $task_id, 'user_id' => $user_id ),
            array( '%d', '%d' )
        );
        
        return $result !== false;
    }
    
    public function set_assignees( int $task_id, array $user_ids ): void {
        global $wpdb;
        $table = esc_sql( $wpdb->prefix . 'aosai_task_users' );
        
        $wpdb->delete( $table, array( 'task_id' => $task_id ), array( '%d' ) );
        
        foreach ( array_filter( array_map( 'absint', (array) $user_ids ) ) as $user_id ) {
            $this->assign_user( $task_id, $user_id );
        }
    }
    
    public function enrich( array $task ): array {
        $id = (int) $task['id'];
        $raw_status = (string) ( $task['status'] ?? '' );
        $normalized_status = $this->normalize_status_value( $raw_status );

        $task['assignees']         = $this->get_assignees( $id );
        $task['comments_count']    = $this->get_comments_count( $id );
        $task['attachments_count'] = $this->get_attachments_count( $id );
        $task['position']          = isset( $task['sort_order'] ) ? (int) $task['sort_order'] : 0;
        $task['status_raw']        = $raw_status;
        $task['status']            = $normalized_status;
        $task['kanban_column']     = $this->normalize_kanban_value( (string) ( $task['kanban_column'] ?? '' ), $normalized_status );
        $task['is_overdue']        = $this->is_task_overdue( $task );

        // Convenient single-assignee fields
        $first = $task['assignees'][0] ?? null;
        $task['assignee_id']   = $first ? (int) $first['user_id'] : null;
        $task['assignee_name'] = $first ? $first['display_name'] : '';

        // Project name
        if ( empty( $task['project_name'] ) && ! empty( $task['project_id'] ) ) {
            global $wpdb;
            $projects_table = esc_sql( $wpdb->prefix . 'aosai_projects' );
            $task['project_name'] = (string) $wpdb->get_var(
                $wpdb->prepare( 'SELECT title FROM ' . $projects_table . ' WHERE id = %d', $task['project_id'] )
            );
        }

        // Task list name
        if ( ! empty( $task['task_list_id'] ) && empty( $task['task_list_name'] ) ) {
            global $wpdb;
            $task_lists_table = esc_sql( $wpdb->prefix . 'aosai_task_lists' );
            $task['task_list_name'] = (string) $wpdb->get_var(
                $wpdb->prepare( 'SELECT title FROM ' . $task_lists_table . ' WHERE id = %d', $task['task_list_id'] )
            );
        } else {
            $task['task_list_name'] = $task['task_list_name'] ?? '';
        }
        $task['tags'] = AOSAI_Tag::get_instance()->get_object_tags( 'task', $id );

        return $task;
    }

    private function get_comments_count( int $task_id ): int {
        global $wpdb;
        $comments_table = esc_sql( $wpdb->prefix . 'aosai_comments' );
        return (int) $wpdb->get_var(
            $wpdb->prepare(
                'SELECT COUNT(*) FROM ' . $comments_table . " WHERE commentable_type = 'task' AND commentable_id = %d",
                $task_id
            )
        );
    }
    
    private function get_attachments_count( int $task_id ): int {
        global $wpdb;
        $files_table = esc_sql( $wpdb->prefix . 'aosai_files' );
        return (int) $wpdb->get_var(
            $wpdb->prepare(
                'SELECT COUNT(*) FROM ' . $files_table . " WHERE fileable_type = 'task' AND fileable_id = %d",
                $task_id
            )
        );
    }

    private function get_order_clause( string $orderby, string $order ): string {
        $allowed = array(
            'sort_order'      => 'sort_order',
            'created_at'      => 'created_at',
            'updated_at'      => 'updated_at',
            'due_date'        => 'due_date',
            'start_date'      => 'start_date',
            'priority'        => 'priority',
            'status'          => 'status',
            'estimated_hours' => 'estimated_hours',
            'title'           => 'title',
        );

        $column    = $allowed[ sanitize_key( $orderby ) ] ?? 'sort_order';
        $direction = 'DESC' === strtoupper( $order ) ? 'DESC' : 'ASC';

        return $column . ' ' . $direction;
    }
    
    private function sanitize_input( array $input ): array {
        $sanitized = array();

        if ( isset( $input['position'] ) && ! isset( $input['sort_order'] ) ) {
            $input['sort_order'] = $input['position'];
        }

        $allowed_fields = [
            'title' => 'sanitize_text_field', 'description' => 'wp_kses_post', 'status' => 'sanitize_key',
            'priority' => 'sanitize_key', 'due_date' => 'sanitize_text_field', 'start_date' => 'sanitize_text_field',
            'estimated_hours' => 'floatval', 'sort_order' => 'intval', 'is_private' => 'intval',
            'kanban_column' => 'sanitize_key', 'task_list_id' => 'absint', 'project_id' => 'absint',
            'parent_id' => 'absint'
        ];
        $priority_options = array( 'low', 'medium', 'high', 'urgent' );

        foreach ( $input as $key => $value ) {
            if ( array_key_exists( $key, $allowed_fields ) ) {
                $sanitized[ $key ] = call_user_func( $allowed_fields[ $key ], wp_unslash( $value ) );
            }
        }
        
        if ( isset( $sanitized['status'] ) ) {
            $sanitized['status'] = $this->normalize_status_value( (string) $sanitized['status'] );
        }

        if ( isset( $sanitized['kanban_column'] ) ) {
            $status_source = isset( $sanitized['status'] ) ? (string) $sanitized['status'] : '';
            $sanitized['kanban_column'] = $this->normalize_kanban_value( (string) $sanitized['kanban_column'], $status_source );
        }

        if ( isset( $sanitized['status'] ) && ! isset( $sanitized['kanban_column'] ) ) {
            $sanitized['kanban_column'] = $this->normalize_kanban_value( '', (string) $sanitized['status'] );
        }

        if ( isset( $sanitized['kanban_column'] ) && ! isset( $sanitized['status'] ) ) {
            $sanitized['status'] = $this->normalize_status_value( (string) $sanitized['kanban_column'] );
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

    private function normalize_status_value( string $status ): string {
        $status = sanitize_key( $status );

        if ( '' === $status ) {
            return 'todo';
        }

        $map = array(
            'open'       => 'todo',
            'done'       => 'completed',
            'overdue'    => 'todo',
            'cancelled'  => 'backlog',
        );

        if ( isset( $map[ $status ] ) ) {
            return $map[ $status ];
        }

        $allowed = array( 'backlog', 'todo', 'in_progress', 'in_review', 'completed' );
        return in_array( $status, $allowed, true ) ? $status : 'todo';
    }

    private function normalize_kanban_value( string $kanban_column, string $status = '' ): string {
        $kanban_column = sanitize_key( $kanban_column );

        if ( '' !== $kanban_column ) {
            return $this->normalize_status_value( $kanban_column );
        }

        return $this->normalize_status_value( $status );
    }

    private function is_task_overdue( array $task ): bool {
        $status = (string) ( $task['status'] ?? '' );
        $due_date = (string) ( $task['due_date'] ?? '' );

        if ( '' === $due_date || in_array( $status, array( 'completed', 'done' ), true ) ) {
            return false;
        }

        $due_timestamp = strtotime( $due_date . ' 23:59:59' );
        if ( false === $due_timestamp ) {
            return false;
        }

        return $due_timestamp < current_time( 'timestamp' );
    }
}
