<?php
if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

class AOSAI_Permission_Service {
    use AOSAI_Singleton;
    
    private function __construct() {}
    
    public function can_manage_project( int $user_id, int $project_id ): bool {
        if ( user_can( $user_id, 'manage_options' ) ) {
            return true;
        }
        
        $role = aosai_user_project_role( $user_id, $project_id );
        return in_array( $role, array( 'manager' ), true );
    }
    
    public function can_edit_project( int $user_id, int $project_id ): bool {
        if ( user_can( $user_id, 'manage_options' ) ) {
            return true;
        }
        
        $role = aosai_user_project_role( $user_id, $project_id );
        return in_array( $role, array( 'manager', 'member' ), true );
    }
    
    public function can_view_project( int $user_id, int $project_id ): bool {
        if ( user_can( $user_id, 'manage_options' ) ) {
            return true;
        }
        
        return aosai_user_can_access_project( $user_id, $project_id );
    }
    
    public function can_manage_tasks( int $user_id, int $project_id ): bool {
        if ( user_can( $user_id, 'aosai_manage_tasks' ) ) {
            return $this->can_view_project( $user_id, $project_id );
        }
        
        return false;
    }
    
    public function can_create_tasks( int $user_id, int $project_id ): bool {
        return $this->can_edit_project( $user_id, $project_id );
    }
    
    public function can_delete_tasks( int $user_id, int $project_id ): bool {
        return $this->can_edit_project( $user_id, $project_id );
    }
    
    public function can_comment( int $user_id, int $project_id ): bool {
        return $this->can_view_project( $user_id, $project_id );
    }
    
    public function can_upload_files( int $user_id, int $project_id ): bool {
        if ( user_can( $user_id, 'aosai_manage_files' ) ) {
            return $this->can_view_project( $user_id, $project_id );
        }
        return false;
    }

    public function can_delete_file( int $user_id, int $file_id ): bool {
        global $wpdb;

        $file = $wpdb->get_row(
            $wpdb->prepare(
                "SELECT id, project_id, uploaded_by FROM {$wpdb->prefix}aosai_files WHERE id = %d",
                $file_id
            ),
            ARRAY_A
        );

        if ( ! $file ) {
            return false;
        }

        if ( user_can( $user_id, 'manage_options' ) || (int) $file['uploaded_by'] === $user_id ) {
            return true;
        }

        return $this->can_manage_project( $user_id, (int) $file['project_id'] );
    }
    
    public function can_view_reports( int $user_id ): bool {
        return user_can( $user_id, 'aosai_view_reports' );
    }
    
    public function can_manage_settings(): bool {
        return current_user_can( 'aosai_manage_settings' );
    }
    
    public function can_use_ai(): bool {
        return current_user_can( 'aosai_use_ai' );
    }
}
