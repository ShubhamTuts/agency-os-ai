<?php
if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

class AOSAI_Project {
    use AOSAI_Singleton;
    
    private function __construct() {}
    
    public function get_table(): string {
        global $wpdb;
        return esc_sql( $wpdb->prefix . 'aosai_projects' );
    }
    
    public function get_user_projects( int $user_id, array $args = array() ): array {
        global $wpdb;
        $table = $this->get_table();
        $pu_table = esc_sql( $wpdb->prefix . 'aosai_project_users' );
        
        $defaults = array(
            'page'     => 1,
            'per_page' => 20,
            'status'   => '',
            'search'   => '',
            'orderby'  => 'created_at',
            'order'    => 'DESC',
        );
        $args = wp_parse_args( $args, $defaults );
        
        $per_page = max( 1, (int) $args['per_page'] );
        $page     = max( 1, (int) $args['page'] );
        $offset   = ( $page - 1 ) * $per_page;

        $has_manager_access = user_can( $user_id, 'manage_options' ) || user_can( $user_id, 'aosai_manage_projects' );
        $params = array();
        $sql    = 'SELECT p.* FROM ' . $table . ' p';

        if ( ! $has_manager_access ) {
            $sql .= ' INNER JOIN ' . $pu_table . ' pu ON p.id = pu.project_id';
        }

        $sql .= ' WHERE 1=1';
        if ( ! $has_manager_access ) {
            $sql .= ' AND pu.user_id = %d';
            $params[] = $user_id;
        }
        
        if ( ! empty( $args['status'] ) ) {
            $sql .= ' AND p.status = %s';
            $params[] = sanitize_key( $args['status'] );
        }
        
        if ( ! empty( $args['search'] ) ) {
            $search = '%' . $wpdb->esc_like( sanitize_text_field( $args['search'] ) ) . '%';
            $sql .= ' AND (p.title LIKE %s OR p.description LIKE %s)';
            $params[] = $search;
            $params[] = $search;
        }

        $sql .= ' ORDER BY ' . $this->get_order_clause( (string) $args['orderby'], (string) $args['order'] );
        $sql .= ' LIMIT %d OFFSET %d';
        $params[] = $per_page;
        $params[] = $offset;
        $sql = $wpdb->prepare( $sql, $params );
        
        $projects = $wpdb->get_results( $sql, ARRAY_A );

        foreach ( $projects as &$project ) {
            $project = $this->enrich( $project );
        }

        return $projects;
    }
    
    public function get( int $id ): ?array {
        global $wpdb;
        $table = $this->get_table();
        
        $project = $wpdb->get_row(
            $wpdb->prepare( 'SELECT * FROM ' . $table . ' WHERE id = %d', $id ),
            ARRAY_A
        );
        
        if ( ! $project ) {
            return null;
        }

        return $this->enrich( $project );
    }
    
    public function create( array $data ): int|\WP_Error {
        global $wpdb;
        $table = $this->get_table();
        
        $sanitized = $this->sanitize_input( $data );

        if ( empty( $sanitized['title'] ) ) {
            return new \WP_Error( 'missing_title', esc_html__( 'Project name is required.', 'agency-os-ai' ) );
        }
        
        $result = $wpdb->insert(
            $table,
            array(
                'title'       => $sanitized['title'],
                'description' => $sanitized['description'] ?? '',
                'status'      => $sanitized['status'] ?? 'active',
                'category'    => $sanitized['category'] ?? '',
                'color'       => $sanitized['color'] ?? '#6366f1',
                'budget'      => $sanitized['budget'] ?? 0,
                'currency'    => $sanitized['currency'] ?? 'USD',
                'start_date'  => $sanitized['start_date'] ?? null,
                'end_date'    => $sanitized['end_date'] ?? null,
                'created_by'  => get_current_user_id(),
            ),
            array( '%s', '%s', '%s', '%s', '%s', '%f', '%s', '%s', '%s', '%d' )
        );
        
        if ( false === $result ) {
            return new \WP_Error( 'db_error', esc_html__( 'Failed to create project.', 'agency-os-ai' ) );
        }
        
        $project_id = $wpdb->insert_id;
        
        $this->add_member( $project_id, get_current_user_id(), 'manager' );
        
        aosai_log_activity( array(
            'project_id'  => $project_id,
            'user_id'    => get_current_user_id(),
            'action'     => 'created',
            'object_type'=> 'project',
            'object_id'  => $project_id,
        ) );

        $project = $this->get( $project_id );
        do_action( 'aosai_project_created', $project_id, $project );

        if ( $project ) {
            AOSAI_Webhook_Service::get_instance()->dispatch( 'project.created', $project );
        }
        
        return $project_id;
    }
    
    public function update( int $id, array $data ): bool|\WP_Error {
        global $wpdb;
        $table = $this->get_table();
        $project = $this->get( $id );
        
        $sanitized = $this->sanitize_input( $data );
        $sanitized['updated_at'] = current_time( 'mysql' );

        if ( empty( $sanitized ) ) {
            return true;
        }

        $format = array();
        foreach ( $sanitized as $key => $value ) {
            if ( in_array( $key, array( 'budget' ), true ) ) {
                $format[] = '%f';
            } elseif ( in_array( $key, array( 'title', 'description', 'status', 'category', 'color', 'currency', 'start_date', 'end_date', 'updated_at' ), true ) ) {
                $format[] = '%s';
            } else {
                $format[] = is_int( $value ) ? '%d' : '%s';
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
            return new \WP_Error( 'db_error', esc_html__( 'Failed to update project.', 'agency-os-ai' ) );
        }
        
        aosai_log_activity( array(
            'project_id'  => $id,
            'action'     => 'updated',
            'object_type'=> 'project',
            'object_id'  => $id,
        ) );

        $updated_project = $this->get( $id );
        do_action( 'aosai_project_updated', $id, $project, $updated_project );

        if ( $updated_project ) {
            AOSAI_Webhook_Service::get_instance()->dispatch( 'project.updated', $updated_project );
        }
        
        return true;
    }
    
    public function delete( int $id ): bool {
        global $wpdb;
        $table             = $this->get_table();
        $activities_table  = esc_sql( $wpdb->prefix . 'aosai_activities' );
        $comments_table    = esc_sql( $wpdb->prefix . 'aosai_comments' );
        $files_table       = esc_sql( $wpdb->prefix . 'aosai_files' );
        $messages_table    = esc_sql( $wpdb->prefix . 'aosai_messages' );
        $task_users_table  = esc_sql( $wpdb->prefix . 'aosai_task_users' );
        $task_meta_table   = esc_sql( $wpdb->prefix . 'aosai_task_meta' );
        $tasks_table       = esc_sql( $wpdb->prefix . 'aosai_tasks' );
        $task_lists_table  = esc_sql( $wpdb->prefix . 'aosai_task_lists' );
        $milestones_table  = esc_sql( $wpdb->prefix . 'aosai_milestones' );
        $project_users_table = esc_sql( $wpdb->prefix . 'aosai_project_users' );
        
        $project = $this->get( $id );
        if ( ! $project ) {
            return false;
        }
        
        $wpdb->query( $wpdb->prepare( 'DELETE FROM ' . $activities_table . ' WHERE project_id = %d', $id ) );
        $wpdb->query( $wpdb->prepare( 'DELETE FROM ' . $comments_table . " WHERE commentable_type = 'project' AND commentable_id = %d", $id ) );
        $wpdb->query( $wpdb->prepare( 'DELETE FROM ' . $files_table . ' WHERE project_id = %d', $id ) );
        $wpdb->query( $wpdb->prepare( 'DELETE FROM ' . $messages_table . ' WHERE project_id = %d', $id ) );
        $wpdb->query( $wpdb->prepare( 'DELETE FROM ' . $task_users_table . ' WHERE task_id IN (SELECT id FROM ' . $tasks_table . ' WHERE project_id = %d)', $id ) );
        $wpdb->query( $wpdb->prepare( 'DELETE FROM ' . $task_meta_table . ' WHERE task_id IN (SELECT id FROM ' . $tasks_table . ' WHERE project_id = %d)', $id ) );
        $wpdb->query( $wpdb->prepare( 'DELETE FROM ' . $tasks_table . ' WHERE project_id = %d', $id ) );
        $wpdb->query( $wpdb->prepare( 'DELETE FROM ' . $task_lists_table . ' WHERE project_id = %d', $id ) );
        $wpdb->query( $wpdb->prepare( 'DELETE FROM ' . $milestones_table . ' WHERE project_id = %d', $id ) );
        $wpdb->query( $wpdb->prepare( 'DELETE FROM ' . $project_users_table . ' WHERE project_id = %d', $id ) );
        
        $result = $wpdb->delete( $table, array( 'id' => $id ), array( '%d' ) );
        
        return $result !== false;
    }
    
    public function get_members( int $project_id ): array {
        global $wpdb;
        $table       = esc_sql( $wpdb->prefix . 'aosai_project_users' );
        $users_table = esc_sql( $wpdb->users );
        
        $members = $wpdb->get_results(
            $wpdb->prepare(
                'SELECT pu.*, u.display_name, u.user_email
                FROM ' . $table . ' pu
                INNER JOIN ' . $users_table . ' u ON pu.user_id = u.ID
                WHERE pu.project_id = %d',
                $project_id
            ),
            ARRAY_A
        );
        
        foreach ( $members as &$member ) {
            $member['avatar_url'] = aosai_get_avatar_url( (int) $member['user_id'] );
        }
        
        return $members;
    }
    
    public function add_member( int $project_id, int $user_id, string $role = 'member' ): bool {
        global $wpdb;
        $table = esc_sql( $wpdb->prefix . 'aosai_project_users' );
        
        $existing = $wpdb->get_row(
            $wpdb->prepare(
                'SELECT id FROM ' . $table . ' WHERE project_id = %d AND user_id = %d',
                $project_id,
                $user_id
            )
        );
        
        if ( $existing ) {
            $wpdb->update(
                $table,
                array( 'role' => $role ),
                array( 'project_id' => $project_id, 'user_id' => $user_id ),
                array( '%s' ),
                array( '%d', '%d' )
            );
        } else {
            $wpdb->insert(
                $table,
                array(
                    'project_id' => $project_id,
                    'user_id'   => $user_id,
                    'role'      => $role,
                ),
                array( '%d', '%d', '%s' )
            );
        }
        
        return true;
    }
    
    public function remove_member( int $project_id, int $user_id ): bool {
        global $wpdb;
        $table = esc_sql( $wpdb->prefix . 'aosai_project_users' );
        
        $result = $wpdb->delete(
            $table,
            array( 'project_id' => $project_id, 'user_id' => $user_id ),
            array( '%d', '%d' )
        );
        
        return $result !== false;
    }
    
    public function get_stats( int $project_id ): array {
        global $wpdb;
        $tasks_table = esc_sql( $wpdb->prefix . 'aosai_tasks' );
        $milestones_table = esc_sql( $wpdb->prefix . 'aosai_milestones' );
        $messages_table = esc_sql( $wpdb->prefix . 'aosai_messages' );
        $files_table = esc_sql( $wpdb->prefix . 'aosai_files' );
        $sql = 'SELECT '
            . '(SELECT COUNT(*) FROM ' . $tasks_table . ' WHERE project_id = %d) as total_tasks, '
            . '(SELECT COUNT(*) FROM ' . $tasks_table . " WHERE project_id = %d AND status IN ('done','completed')) as completed_tasks, "
            . '(SELECT COUNT(*) FROM ' . $tasks_table . " WHERE project_id = %d AND due_date < CURDATE() AND status NOT IN ('done','completed')) as overdue_tasks, "
            . '(SELECT COUNT(*) FROM ' . $milestones_table . ' WHERE project_id = %d) as total_milestones, '
            . '(SELECT COUNT(*) FROM ' . $milestones_table . " WHERE project_id = %d AND status = 'completed') as completed_milestones, "
            . '(SELECT COUNT(*) FROM ' . $messages_table . ' WHERE project_id = %d) as total_messages, '
            . '(SELECT COUNT(*) FROM ' . $files_table . ' WHERE project_id = %d) as total_files';
        
        $stats = $wpdb->get_row(
            $wpdb->prepare(
                $sql,
                $project_id, $project_id, $project_id, $project_id, $project_id, $project_id, $project_id
            ),
            ARRAY_A
        );
        
        $stats['total_hours'] = 0;
        
        return $stats;
    }
    
    public function enrich( array $project ): array {
        $id = (int) $project['id'];

        // Aliases: frontend uses 'name' and 'due_date'
        $project['name']     = $project['title'];
        $project['due_date'] = $project['end_date'];

        // Owner name
        $owner = get_userdata( (int) ( $project['created_by'] ?? 0 ) );
        $project['owner_name'] = $owner ? $owner->display_name : '';

        // Members
        $members = $this->get_members( $id );
        $project['members']      = $members;
        $project['member_count'] = count( $members );

        // Stats
        $stats = $this->get_stats( $id );
        $project['stats']                 = $stats;
        $project['task_count']            = (int) ( $stats['total_tasks'] ?? 0 );
        $project['completed_task_count']  = (int) ( $stats['completed_tasks'] ?? 0 );
        $project['progress']              = $project['task_count'] > 0
            ? (int) round( ( $project['completed_task_count'] / $project['task_count'] ) * 100 )
            : 0;
        $project['tags']                  = AOSAI_Tag::get_instance()->get_object_tags( 'project', $id );

        return $project;
    }

    private function get_order_clause( string $orderby, string $order ): string {
        $allowed = array(
            'created_at' => 'p.created_at',
            'updated_at' => 'p.updated_at',
            'title'      => 'p.title',
            'status'     => 'p.status',
            'start_date' => 'p.start_date',
            'end_date'   => 'p.end_date',
        );

        $column    = $allowed[ sanitize_key( $orderby ) ] ?? 'p.created_at';
        $direction = 'ASC' === strtoupper( $order ) ? 'ASC' : 'DESC';

        return $column . ' ' . $direction;
    }

    private function sanitize_input( array $input ): array {
        $sanitized = array();

        // Accept 'name' as alias for 'title'
        $title_value = $input['title'] ?? $input['name'] ?? null;
        if ( $title_value !== null ) {
            $sanitized['title'] = sanitize_text_field( wp_unslash( $title_value ) );
        }
        if ( isset( $input['description'] ) ) {
            $sanitized['description'] = wp_kses_post( wp_unslash( $input['description'] ) );
        }
        if ( isset( $input['status'] ) ) {
            $allowed = array( 'active', 'archived', 'completed', 'on_hold' );
            $sanitized['status'] = in_array( $input['status'], $allowed, true ) ? $input['status'] : 'active';
        }
        if ( isset( $input['category'] ) ) {
            $sanitized['category'] = sanitize_text_field( wp_unslash( $input['category'] ) );
        }
        if ( isset( $input['color'] ) ) {
            $sanitized['color'] = preg_match( '/^#[a-fA-F0-9]{6}$/', $input['color'] ) ? $input['color'] : '#6366f1';
        }
        if ( isset( $input['budget'] ) ) {
            $sanitized['budget'] = max( 0, floatval( $input['budget'] ) );
        }
        if ( isset( $input['currency'] ) ) {
            $sanitized['currency'] = sanitize_text_field( wp_unslash( $input['currency'] ) );
        }
        if ( isset( $input['start_date'] ) ) {
            $date = sanitize_text_field( wp_unslash( $input['start_date'] ) );
            $sanitized['start_date'] = preg_match( '/^\d{4}-\d{2}-\d{2}$/', $date ) ? $date : null;
        }
        // Accept 'due_date' as alias for 'end_date'
        $end_value = $input['end_date'] ?? $input['due_date'] ?? null;
        if ( $end_value !== null ) {
            $date = sanitize_text_field( wp_unslash( $end_value ) );
            $sanitized['end_date'] = preg_match( '/^\d{4}-\d{2}-\d{2}$/', $date ) ? $date : null;
        }

        return $sanitized;
    }
}
