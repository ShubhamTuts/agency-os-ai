<?php
if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

class AOSAI_File_Service {
    use AOSAI_Singleton;
    
    private function __construct() {}
    
    public function handle_upload( array $file, int $project_id ): array|\WP_Error {
        $allowed_types = array(
            'jpg', 'jpeg', 'png', 'gif', 'webp', 'svg',
            'pdf', 'doc', 'docx', 'xls', 'xlsx', 'ppt', 'pptx',
            'txt', 'csv', 'zip', 'rar',
        );
        
        $file_ext = strtolower( pathinfo( $file['name'], PATHINFO_EXTENSION ) );
        if ( ! in_array( $file_ext, $allowed_types, true ) ) {
            return new \WP_Error(
                'invalid_file_type',
                esc_html__( 'This file type is not allowed.', 'agency-os-ai' )
            );
        }
        
        $max_size = absint( get_option( 'aosai_file_upload_max_mb', 10 ) ) * MB_IN_BYTES;
        if ( $file['size'] > $max_size ) {
            return new \WP_Error(
                'file_too_large',
                esc_html__( 'File exceeds maximum upload size.', 'agency-os-ai' )
            );
        }
        
        require_once ABSPATH . 'wp-admin/includes/file.php';
        require_once ABSPATH . 'wp-admin/includes/image.php';
        require_once ABSPATH . 'wp-admin/includes/media.php';
        
        $upload = wp_handle_upload( $file, array( 'test_form' => false ) );
        
        if ( isset( $upload['error'] ) ) {
            return new \WP_Error( 'upload_error', $upload['error'] );
        }
        
        $attachment = array(
            'post_mime_type' => $upload['type'],
            'post_title'     => sanitize_file_name( $file['name'] ),
            'post_content'   => '',
            'post_status'    => 'inherit',
        );
        
        $attachment_id = wp_insert_attachment( $attachment, $upload['file'] );
        
        if ( is_wp_error( $attachment_id ) ) {
            return $attachment_id;
        }
        
        $metadata = wp_generate_attachment_metadata( $attachment_id, $upload['file'] );
        wp_update_attachment_metadata( $attachment_id, $metadata );
        
        return array(
            'attachment_id' => $attachment_id,
            'url'           => $upload['url'],
            'file'          => $upload['file'],
            'type'          => $upload['type'],
        );
    }
    
    public function format_file_size( int $bytes ): string {
        if ( $bytes < 1024 ) {
            return $bytes . ' B';
        }
        if ( $bytes < 1048576 ) {
            return round( $bytes / 1024, 1 ) . ' KB';
        }
        if ( $bytes < 1073741824 ) {
            return round( $bytes / 1048576, 1 ) . ' MB';
        }
        return round( $bytes / 1073741824, 1 ) . ' GB';
    }
    
    public function get_file_icon( string $mime_type ): string {
        if ( strpos( $mime_type, 'image/' ) === 0 ) {
            return 'image';
        }
        if ( strpos( $mime_type, 'video/' ) === 0 ) {
            return 'video';
        }
        if ( strpos( $mime_type, 'audio/' ) === 0 ) {
            return 'audio';
        }
        if ( strpos( $mime_type, 'application/pdf' ) === 0 ) {
            return 'pdf';
        }
        if ( strpos( $mime_type, 'word' ) !== false || strpos( $mime_type, 'document' ) !== false ) {
            return 'doc';
        }
        if ( strpos( $mime_type, 'sheet' ) !== false || strpos( $mime_type, 'excel' ) !== false ) {
            return 'spreadsheet';
        }
        if ( strpos( $mime_type, 'presentation' ) !== false || strpos( $mime_type, 'powerpoint' ) !== false ) {
            return 'presentation';
        }
        if ( strpos( $mime_type, 'zip' ) !== false || strpos( $mime_type, 'archive' ) !== false ) {
            return 'archive';
        }
        return 'file';
    }
}
