<?php
if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

class AOSAI_Comment {
    use AOSAI_Singleton;
    
    public function get_table(): string {
        global $wpdb;
        return esc_sql( $wpdb->prefix . 'aosai_comments' );
    }
    
    private function enrich( array $comment ): array {
        $comment['user_name'] = $comment['author_name'] ?? '';
        $comment['user_avatar_url'] = get_avatar_url($comment['created_by']);
        unset($comment['author_name']); // Use user_name for consistency
        return $comment;
    }

    public function get_comment( int $id ): ?array {
        global $wpdb;
        $table       = $this->get_table();
        $users_table = esc_sql( $wpdb->users );

        $comment = $wpdb->get_row(
            $wpdb->prepare(
                'SELECT c.*, u.display_name as author_name
                FROM ' . $table . ' c
                LEFT JOIN ' . $users_table . ' u ON c.created_by = u.ID
                WHERE c.id = %d',
                $id
            ),
            ARRAY_A
        );

        return $comment ? $this->enrich($comment) : null;
    }

    public function get_comments( string $type, int $object_id ): array {
        global $wpdb;
        $table       = $this->get_table();
        $users_table = esc_sql( $wpdb->users );

        $comments = $wpdb->get_results(
            $wpdb->prepare(
                'SELECT c.*, u.display_name as author_name
                FROM ' . $table . ' c
                LEFT JOIN ' . $users_table . ' u ON c.created_by = u.ID
                WHERE c.commentable_type = %s AND c.commentable_id = %d AND c.parent_id IS NULL
                ORDER BY c.created_at ASC',
                $type,
                $object_id
            ),
            ARRAY_A
        );
        return array_map(array($this, 'enrich'), $comments);
    }

    public function get_replies( int $parent_id ): array {
        global $wpdb;
        $table       = $this->get_table();
        $users_table = esc_sql( $wpdb->users );

        $replies = $wpdb->get_results(
            $wpdb->prepare(
                'SELECT c.*, u.display_name as author_name
                FROM ' . $table . ' c
                LEFT JOIN ' . $users_table . ' u ON c.created_by = u.ID
                WHERE c.parent_id = %d
                ORDER BY c.created_at ASC',
                $parent_id
            ),
            ARRAY_A
        );
        return array_map(array($this, 'enrich'), $replies);
    }
    
    public function create( array $data ): int|\WP_Error {
        global $wpdb;
        $table = $this->get_table();
        
        $sanitized = $this->sanitize_input($data);

        if ( empty( $sanitized['content'] ) ) {
            return new \WP_Error( 'missing_content', esc_html__( 'Comment content is required.', 'agency-os-ai' ) );
        }
        
        if ( empty($sanitized['commentable_type']) || empty($sanitized['commentable_id']) ) {
             return new \WP_Error( 'missing_target', esc_html__( 'A comment must be associated with an object.', 'agency-os-ai' ) );
        }
        
        $sanitized['created_by'] = get_current_user_id();
        
        $result = $wpdb->insert( $table, $sanitized );
        
        if ( false === $result ) {
            return new \WP_Error( 'db_error', esc_html__( 'Failed to create comment.', 'agency-os-ai' ) );
        }
        
        $comment_id = $wpdb->insert_id;
        
        do_action( 'aosai_comment_created', $comment_id, $sanitized['commentable_type'], $sanitized['commentable_id'] );
        
        return $comment_id;
    }
    
    public function update( int $id, array $data ): bool|\WP_Error {
        global $wpdb;
        $table = $this->get_table();
        
        if ( ! $this->get_comment( $id ) ) {
            return new \WP_Error( 'not_found', esc_html__( 'Comment not found.', 'agency-os-ai' ) );
        }

        $sanitized = $this->sanitize_input($data);
        if ( empty( $sanitized['content'] ) ) {
            return new \WP_Error( 'missing_content', esc_html__( 'Comment content is required.', 'agency-os-ai' ) );
        }
        $sanitized['updated_at'] = current_time('mysql');
        
        $result = $wpdb->update( $table, $sanitized, array( 'id' => $id ) );
        
        if ( false === $result ) {
            return new \WP_Error( 'db_error', esc_html__( 'Failed to update comment.', 'agency-os-ai' ) );
        }
        
        return true;
    }
    
    public function delete( int $id ): bool {
        global $wpdb;
        $table = $this->get_table();
        
        if ( ! $this->get_comment( $id ) ) {
            return false;
        }
        
        // Delete replies first
        $wpdb->delete( $table, array( 'parent_id' => $id ), array( '%d' ) );
        $result = $wpdb->delete( $table, array( 'id' => $id ), array( '%d' ) );
        
        return $result !== false;
    }

    private function sanitize_input(array $data): array
    {
        $sanitized = [];
        $allowed = [
            'commentable_type' => 'sanitize_key',
            'commentable_id' => 'absint',
            'parent_id' => 'absint',
            'content' => 'wp_kses_post',
        ];

        foreach($data as $key => $value) {
            if (isset($allowed[$key])) {
                $sanitized[$key] = call_user_func($allowed[$key], wp_unslash($value));
            }
        }

        if (isset($sanitized['parent_id']) && $sanitized['parent_id'] === 0) {
            $sanitized['parent_id'] = null;
        }

        return $sanitized;
    }
}
