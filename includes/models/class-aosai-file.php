<?php
if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

class AOSAI_File {
    use AOSAI_Singleton;
    
    public function get_table(): string {
        global $wpdb;
        return esc_sql( $wpdb->prefix . 'aosai_files' );
    }
    
    public function get_project_files( int $project_id, array $args = array() ): array {
        global $wpdb;
        $table       = $this->get_table();
        $users_table = esc_sql( $wpdb->users );
        
        $defaults = array(
            'page'     => 1,
            'per_page' => 20,
            'folder'   => '',
            'search'   => '',
        );
        $args = wp_parse_args( $args, $defaults );
        
        $per_page = max( 1, (int) $args['per_page'] );
        $page     = max( 1, (int) $args['page'] );
        $offset   = ( $page - 1 ) * $per_page;
        $params   = array( $project_id );
        $sql      = 'SELECT f.*, u.display_name as uploader_name, p.title as project_title
            FROM ' . $table . ' f 
            INNER JOIN ' . esc_sql( $wpdb->prefix . 'aosai_projects' ) . ' p ON f.project_id = p.id
            LEFT JOIN ' . $users_table . ' u ON f.uploaded_by = u.ID 
            WHERE project_id = %d';
        
        if ( ! current_user_can( 'manage_options' ) ) {
            $sql .= ' AND is_private = 0';
        }
        
        if ( ! empty( $args['folder'] ) ) {
            $sql .= ' AND folder = %s';
            $params[] = sanitize_text_field( $args['folder'] );
        }
        
        if ( ! empty( $args['search'] ) ) {
            $search = '%' . $wpdb->esc_like( sanitize_text_field( $args['search'] ) ) . '%';
            $sql .= ' AND (file_name LIKE %s OR title LIKE %s)';
            $params[] = $search;
            $params[] = $search;
        }

        $sql .= ' ORDER BY f.created_at DESC LIMIT %d OFFSET %d';
        $params[] = $per_page;
        $params[] = $offset;
        $sql = $wpdb->prepare( $sql, $params );

        $files = $wpdb->get_results( $sql, ARRAY_A );
        return array_map( array( $this, 'normalize_file' ), $files );
    }

    public function get_user_files( int $user_id, array $args = array() ): array {
        global $wpdb;
        $table       = $this->get_table();
        $users_table = esc_sql( $wpdb->users );

        $defaults = array(
            'page'     => 1,
            'per_page' => 20,
            'search'   => '',
        );
        $args   = wp_parse_args( $args, $defaults );
        $per_page = max( 1, (int) $args['per_page'] );
        $page     = max( 1, (int) $args['page'] );
        $offset   = ( $page - 1 ) * $per_page;
        $pu_table = esc_sql( $wpdb->prefix . 'aosai_project_users' );

        $sql = 'SELECT f.*, u.display_name as uploader_name, p.title as project_title
            FROM ' . $table . ' f
            INNER JOIN ' . esc_sql( $wpdb->prefix . 'aosai_projects' ) . ' p ON f.project_id = p.id
            LEFT JOIN ' . $users_table . ' u ON f.uploaded_by = u.ID
            LEFT JOIN ' . $pu_table . ' pu ON f.project_id = pu.project_id
            WHERE 1=1';
        $params = array();

        if ( ! user_can( $user_id, 'manage_options' ) ) {
            $sql .= ' AND pu.user_id = %d AND (f.is_private = 0 OR f.uploaded_by = %d)';
            $params[] = $user_id;
            $params[] = $user_id;
        }

        if ( ! empty( $args['search'] ) ) {
            $search   = '%' . $wpdb->esc_like( sanitize_text_field( $args['search'] ) ) . '%';
            $sql .= ' AND (f.file_name LIKE %s OR f.title LIKE %s)';
            $params[] = $search;
            $params[] = $search;
        }

        $sql .= ' GROUP BY f.id ORDER BY f.created_at DESC LIMIT %d OFFSET %d';
        $params[] = $per_page;
        $params[] = $offset;
        $sql = $wpdb->prepare( $sql, $params );

        $files = $wpdb->get_results( $sql, ARRAY_A );
        return array_map( array( $this, 'normalize_file' ), $files );
    }
    
    public function get( int $id ): ?array {
        global $wpdb;
        $table          = $this->get_table();
        $projects_table = esc_sql( $wpdb->prefix . 'aosai_projects' );
        $users_table    = esc_sql( $wpdb->users );
        
        $file = $wpdb->get_row(
            $wpdb->prepare(
                'SELECT f.*, u.display_name as uploader_name, p.title as project_title
                FROM ' . $table . ' f
                INNER JOIN ' . $projects_table . ' p ON f.project_id = p.id
                LEFT JOIN ' . $users_table . ' u ON f.uploaded_by = u.ID
                WHERE f.id = %d',
                $id
            ),
            ARRAY_A
        );

        return $file ? $this->normalize_file( $file ) : null;
    }
    
    public function create( array $data ): int|\WP_Error {
        global $wpdb;
        $table = $this->get_table();
        
        $project_id = absint( $data['project_id'] ?? 0 );
        if ( ! $project_id ) {
            return new \WP_Error( 'missing_project', esc_html__( 'Project ID is required.', 'agency-os-ai' ) );
        }
        
        $attachment_id = absint( $data['attachment_id'] ?? 0 );
        if ( ! $attachment_id ) {
            return new \WP_Error( 'missing_attachment', esc_html__( 'Attachment ID is required.', 'agency-os-ai' ) );
        }
        
        $attachment = get_post( $attachment_id );
        if ( ! $attachment ) {
            return new \WP_Error( 'invalid_attachment', esc_html__( 'Invalid attachment.', 'agency-os-ai' ) );
        }
        
        $result = $wpdb->insert(
            $table,
            array(
                'project_id'    => $project_id,
                'attachment_id' => $attachment_id,
                'fileable_type' => sanitize_key( $data['fileable_type'] ?? '' ) ?: null,
                'fileable_id'   => isset( $data['fileable_id'] ) ? absint( $data['fileable_id'] ) : null,
                'folder'        => sanitize_text_field( $data['folder'] ?? '' ),
                'title'         => sanitize_text_field( wp_unslash( $data['title'] ?? '' ) ) ?: $attachment->post_title,
                'file_name'     => sanitize_file_name( $attachment->post_name ?: basename( get_attached_file( $attachment_id ) ) ),
                'file_url'      => esc_url_raw( wp_get_attachment_url( $attachment_id ) ),
                'file_type'     => sanitize_mime_type( $attachment->post_mime_type ),
                'file_size'     => absint( filesize( get_attached_file( $attachment_id ) ) ),
                'is_private'    => ! empty( $data['is_private'] ) ? 1 : 0,
                'uploaded_by'   => get_current_user_id(),
            ),
            array( '%d', '%d', '%s', '%d', '%s', '%s', '%s', '%s', '%s', '%d', '%d', '%d' )
        );
        
        if ( false === $result ) {
            return new \WP_Error( 'db_error', esc_html__( 'Failed to save file record.', 'agency-os-ai' ) );
        }
        
        $file_id = $wpdb->insert_id;
        
        aosai_log_activity( array(
            'project_id'  => $project_id,
            'action'     => 'uploaded',
            'object_type'=> 'file',
            'object_id'  => $file_id,
        ) );
        
        return $file_id;
    }
    
    public function delete( int $id ): bool {
        global $wpdb;
        $table = $this->get_table();
        
        $file = $this->get( $id );
        if ( ! $file ) {
            return false;
        }
        
        if ( $file['attachment_id'] ) {
            wp_delete_attachment( $file['attachment_id'], true );
        }
        
        $result = $wpdb->delete( $table, array( 'id' => $id ), array( '%d' ) );
        
        if ( $result !== false ) {
            aosai_log_activity( array(
                'project_id'  => $file['project_id'],
                'action'     => 'deleted',
                'object_type'=> 'file',
                'object_id'  => $id,
            ) );
        }
        
        return $result !== false;
    }

    private function normalize_file( array $file ): array {
        $file['filename']         = $file['file_name'] ?? '';
        $file['url']              = $file['file_url'] ?? '';
        $file['mime_type']        = $file['file_type'] ?? '';
        $file['uploaded_by_name'] = $file['uploader_name'] ?? '';
        $file['project_name']     = $file['project_title'] ?? '';

        return $file;
    }
}
