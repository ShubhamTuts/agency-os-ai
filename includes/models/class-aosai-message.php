<?php
if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

class AOSAI_Message {
    use AOSAI_Singleton;
    
    public function get_table(): string {
        global $wpdb;
        return $wpdb->prefix . 'aosai_messages';
    }
    
    private function enrich( array $message ): array {
        $message['user_name'] = $message['author_name'] ?? '';
        
        if ( empty( $message['project_name'] ) && ! empty( $message['project_id'] ) ) {
             global $wpdb;
             $message['project_name'] = (string) $wpdb->get_var($wpdb->prepare("SELECT title FROM {$wpdb->prefix}aosai_projects WHERE id = %d", $message['project_id']));
        } elseif (empty($message['project_id'])) {
            $message['project_name'] = esc_html__( 'General', 'agency-os-ai' );
        }
        
        $message['author_avatar_url'] = get_avatar_url($message['created_by']);

        return $message;
    }

    public function get_messages_for_user( int $user_id, array $args = array() ): array {
        global $wpdb;
        $table    = $this->get_table();
        $pu_table = $wpdb->prefix . 'aosai_project_users';

        $defaults = array(
            'page'     => 1,
            'per_page' => 20,
            'search'   => '',
        );
        $args   = wp_parse_args( $args, $defaults );
        $offset = ( $args['page'] - 1 ) * $args['per_page'];

        $has_manager_access = user_can( $user_id, 'manage_options' ) || user_can( $user_id, 'aosai_manage_projects' );
        $where  = $has_manager_access
            ? 'WHERE 1=1'
            : "WHERE ( m.project_id = 0 OR pu.user_id = %d ) AND ( m.is_private = 0 OR m.created_by = %d )";
        $params = $has_manager_access ? array() : array( $user_id, $user_id );

        if ( ! empty( $args['search'] ) ) {
            $search   = '%' . $wpdb->esc_like( sanitize_text_field( $args['search'] ) ) . '%';
            $where   .= " AND (m.title LIKE %s OR m.content LIKE %s)";
            $params[] = $search;
            $params[] = $search;
        }

        $params[] = $args['per_page'];
        $params[] = $offset;

        $sql = $wpdb->prepare(
            "SELECT DISTINCT m.*, u.display_name as author_name
            FROM {$table} m
            LEFT JOIN {$pu_table} pu ON m.project_id = pu.project_id
            LEFT JOIN {$wpdb->users} u ON m.created_by = u.ID
            {$where}
            ORDER BY m.created_at DESC
            LIMIT %d OFFSET %d",
            ...$params
        );

        $messages = $wpdb->get_results( $sql, ARRAY_A );
        return array_map( array( $this, 'enrich' ), $messages );
    }

    public function get_project_messages( int $project_id, array $args = array() ): array {
        global $wpdb;
        $table = $this->get_table();

        $defaults = array(
            'page'     => 1,
            'per_page' => 20,
            'search'   => '',
        );
        $args = wp_parse_args( $args, $defaults );

        $offset = ( $args['page'] - 1 ) * $args['per_page'];

        $where  = "WHERE m.project_id = %d";
        $params = array( $project_id );

        if ( ! current_user_can( 'manage_options' ) ) {
            $current_user_id  = get_current_user_id();
            $where           .= " AND (m.is_private = 0 OR m.created_by = %d)";
            $params[]         = $current_user_id;
        }

        if ( ! empty( $args['search'] ) ) {
            $search   = '%' . $wpdb->esc_like( sanitize_text_field( $args['search'] ) ) . '%';
            $where   .= " AND (m.title LIKE %s OR m.content LIKE %s)";
            $params[] = $search;
            $params[] = $search;
        }

        $params[] = $args['per_page'];
        $params[] = $offset;

        $sql = $wpdb->prepare(
            "SELECT m.*, u.display_name as author_name
            FROM {$table} m
            LEFT JOIN {$wpdb->users} u ON m.created_by = u.ID
            {$where}
            ORDER BY m.created_at DESC
            LIMIT %d OFFSET %d",
            ...$params
        );

        $messages = $wpdb->get_results( $sql, ARRAY_A );
        return array_map( array( $this, 'enrich' ), $messages );
    }

    public function get( int $id ): ?array {
        global $wpdb;
        $table = $this->get_table();

        $message = $wpdb->get_row(
            $wpdb->prepare(
                "SELECT m.*, u.display_name as author_name
                FROM {$table} m
                LEFT JOIN {$wpdb->users} u ON m.created_by = u.ID
                WHERE m.id = %d",
                $id
            ),
            ARRAY_A
        );

        if ( ! $message ) {
            return null;
        }

        return $this->enrich( $message );
    }

    public function create( array $data ): int|\WP_Error {
        global $wpdb;
        $table = $this->get_table();
        
        $sanitized = $this->sanitize_input($data);

        if ( empty( $sanitized['content'] ) ) {
            return new \WP_Error( 'missing_content', esc_html__( 'Message content is required.', 'agency-os-ai' ) );
        }

        $sanitized['created_by'] = get_current_user_id();
        
        $result = $wpdb->insert( $table, $sanitized );
        
        if ( false === $result ) {
            return new \WP_Error( 'db_error', esc_html__( 'Failed to create message.', 'agency-os-ai' ) );
        }
        
        $message_id = $wpdb->insert_id;
        $project_id = $sanitized['project_id'] ?? 0;

        $mentioned = aosai_parse_mentions( $sanitized['content'], $project_id );
        foreach ( $mentioned as $user_id ) {
            do_action( 'aosai_message_mention', $message_id, $user_id );
        }
        
        aosai_log_activity( array(
            'project_id'  => $project_id,
            'action'     => 'created',
            'object_type'=> 'message',
            'object_id'  => $message_id,
        ) );
        
        do_action( 'aosai_message_created', $message_id, $project_id );
        
        return $message_id;
    }
    
    public function update( int $id, array $data ): bool|\WP_Error {
        global $wpdb;
        $table = $this->get_table();
        
        $message = $this->get( $id );
        if ( ! $message ) {
            return new \WP_Error( 'not_found', esc_html__( 'Message not found.', 'agency-os-ai' ) );
        }

        $sanitized = $this->sanitize_input($data);
        if (empty($sanitized)) {
            return true;
        }
        $sanitized['updated_at'] = current_time( 'mysql' );
        
        $result = $wpdb->update( $table, $sanitized, array( 'id' => $id ) );
        
        if ( false === $result ) {
            return new \WP_Error( 'db_error', esc_html__( 'Failed to update message.', 'agency-os-ai' ) );
        }
        
        aosai_log_activity( array(
            'project_id'  => $message['project_id'],
            'action'     => 'updated',
            'object_type'=> 'message',
            'object_id'  => $id,
        ) );
        
        return true;
    }
    
    public function delete( int $id ): bool {
        global $wpdb;
        $table = $this->get_table();
        
        $message = $this->get( $id );
        if ( ! $message ) {
            return false;
        }
        
        $comments_table = $wpdb->prefix . 'aosai_comments';
        $wpdb->delete( $comments_table, array( 'commentable_type' => 'message', 'commentable_id' => $id ), array( '%s', '%d' ) );
        
        $result = $wpdb->delete( $table, array( 'id' => $id ), array( '%d' ) );
        
        if ( $result !== false ) {
            aosai_log_activity( array(
                'project_id'  => $message['project_id'],
                'action'     => 'deleted',
                'object_type'=> 'message',
                'object_id'  => $id,
            ) );
        }
        
        return $result !== false;
    }

    private function sanitize_input(array $data): array
    {
        $sanitized = [];
        $allowed = [
            'project_id' => 'absint',
            'title' => 'sanitize_text_field',
            'content' => 'wp_kses_post',
            'is_private' => 'intval',
        ];

        foreach($data as $key => $value) {
            if (isset($allowed[$key])) {
                $sanitized[$key] = call_user_func($allowed[$key], wp_unslash($value));
            }
        }

        if (isset($sanitized['is_private'])) {
            $sanitized['is_private'] = $sanitized['is_private'] ? 1 : 0;
        }

        return $sanitized;
    }
}
