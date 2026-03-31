<?php
if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

class AOSAI_REST_Files extends WP_REST_Controller {
    
    protected $namespace = 'aosai/v1';
    
    public function register_routes() {
        register_rest_route( $this->namespace, '/files', array(
            array(
                'methods'             => WP_REST_Server::READABLE,
                'callback'            => array( $this, 'get_my_files' ),
                'permission_callback' => array( $this, 'get_files_permissions_check' ),
            ),
            array(
                'methods'             => WP_REST_Server::CREATABLE,
                'callback'            => array( $this, 'upload_global_file' ),
                'permission_callback' => array( $this, 'get_files_permissions_check' ),
            ),
        ) );

        register_rest_route( $this->namespace, '/projects/(?P<project_id>[\d]+)/files', array(
            array(
                'methods'             => WP_REST_Server::READABLE,
                'callback'            => array( $this, 'get_project_files' ),
                'permission_callback' => array( $this, 'project_access_check' ),
            ),
            array(
                'methods'             => WP_REST_Server::CREATABLE,
                'callback'            => array( $this, 'upload_file' ),
                'permission_callback' => array( $this, 'project_edit_check' ),
            ),
        ) );
        
        register_rest_route( $this->namespace, '/files/(?P<id>[\d]+)', array(
            array(
                'methods'             => WP_REST_Server::DELETABLE,
                'callback'            => array( $this, 'delete_file' ),
                'permission_callback' => array( $this, 'delete_file_permissions_check' ),
            ),
        ) );
    }
    
    public function project_access_check( $request ) {
        if ( ! is_user_logged_in() ) {
            return new WP_Error( 'rest_not_logged_in', esc_html__( 'You must be logged in.', 'agency-os-ai' ), array( 'status' => 401 ) );
        }
        return aosai_user_can_access_project( get_current_user_id(), absint( $request->get_param( 'project_id' ) ) ) 
            ? true 
            : new WP_Error( 'rest_forbidden', esc_html__( 'Access denied.', 'agency-os-ai' ), array( 'status' => 403 ) );
    }
    
    public function project_edit_check( $request ) {
        if ( ! is_user_logged_in() ) {
            return new WP_Error( 'rest_not_logged_in', esc_html__( 'You must be logged in.', 'agency-os-ai' ), array( 'status' => 401 ) );
        }
        $permissions = AOSAI_Permission_Service::get_instance();
        return $permissions->can_upload_files( get_current_user_id(), absint( $request->get_param( 'project_id' ) ) ) 
            ? true 
            : new WP_Error( 'rest_forbidden', esc_html__( 'Access denied.', 'agency-os-ai' ), array( 'status' => 403 ) );
    }
    
    public function delete_file_permissions_check( $request ) {
        $file_model = AOSAI_File::get_instance();
        $file       = $file_model->get( absint( $request->get_param( 'id' ) ) );
        if ( ! $file ) {
            return new WP_Error( 'not_found', esc_html__( 'File not found.', 'agency-os-ai' ), array( 'status' => 404 ) );
        }

        $permissions = AOSAI_Permission_Service::get_instance();
        return $permissions->can_delete_file( get_current_user_id(), $file['id'] )
            ? true
            : new WP_Error( 'rest_forbidden', esc_html__( 'You do not have permission to delete this file.', 'agency-os-ai' ), array( 'status' => 403 ) );
    }

    public function get_files_permissions_check( $request ) {
        return is_user_logged_in() ? true : new WP_Error( 'rest_not_logged_in', esc_html__( 'You must be logged in.', 'agency-os-ai' ), array( 'status' => 401 ) );
    }

    public function get_my_files( $request ) {
        $args = array(
            'page'     => absint( $request->get_param( 'page' ) ) ?: 1,
            'per_page' => min( absint( $request->get_param( 'per_page' ) ) ?: 20, 100 ),
            'search'   => sanitize_text_field( $request->get_param( 'search' ) ) ?: '',
        );

        $model = AOSAI_File::get_instance();
        $files = $model->get_user_files( get_current_user_id(), $args );

        return rest_ensure_response( $files );
    }
    
    public function get_project_files( $request ) {
        $project_id = absint( $request->get_param( 'project_id' ) );
        $args = array(
            'page'    => absint( $request->get_param( 'page' ) ) ?: 1,
            'per_page'=> min( absint( $request->get_param( 'per_page' ) ) ?: 20, 100 ),
            'folder'  => sanitize_text_field( $request->get_param( 'folder' ) ) ?: '',
            'search'  => sanitize_text_field( $request->get_param( 'search' ) ) ?: '',
        );
        
        $model = AOSAI_File::get_instance();
        $files = $model->get_project_files( $project_id, $args );
        
        return rest_ensure_response( $files );
    }
    
    public function upload_file( $request ) {
        $project_id = absint( $request->get_param( 'project_id' ) );

        $nonce = $request->get_header( 'X-WP-Nonce' );
        if ( ! $nonce || ! wp_verify_nonce( $nonce, 'wp_rest' ) ) {
            return new WP_Error( 'rest_invalid_nonce', esc_html__( 'Security check failed.', 'agency-os-ai' ), array( 'status' => 403 ) );
        }
        
        if ( empty( $_FILES['file'] ) || ! is_array( $_FILES['file'] ) ) {
            return new WP_Error( 'no_file', esc_html__( 'No file uploaded.', 'agency-os-ai' ), array( 'status' => 400 ) );
        }

        $file_input = wp_unslash( $_FILES['file'] );
        $uploaded_file = array(
            'name'     => sanitize_file_name( (string) ( $file_input['name'] ?? '' ) ),
            'type'     => sanitize_mime_type( (string) ( $file_input['type'] ?? '' ) ),
            'tmp_name' => isset( $file_input['tmp_name'] ) ? (string) $file_input['tmp_name'] : '',
            'error'    => isset( $file_input['error'] ) ? (int) $file_input['error'] : UPLOAD_ERR_NO_FILE,
            'size'     => isset( $file_input['size'] ) ? (int) $file_input['size'] : 0,
        );
        
        $file_service = AOSAI_File_Service::get_instance();
        $upload_result = $file_service->handle_upload( $uploaded_file, $project_id );
        
        if ( is_wp_error( $upload_result ) ) {
            return $upload_result;
        }
        
        $file_model = AOSAI_File::get_instance();
        $data = array(
            'project_id'    => $project_id,
            'attachment_id' => $upload_result['attachment_id'],
            'fileable_type' => sanitize_key( $request->get_param( 'fileable_type' ) ) ?: null,
            'fileable_id'   => absint( $request->get_param( 'fileable_id' ) ) ?: null,
            'folder'        => sanitize_text_field( $request->get_param( 'folder' ) ) ?: '',
        );
        
        $file_id = $file_model->create( $data );
        
        if ( is_wp_error( $file_id ) ) {
            wp_delete_attachment( $upload_result['attachment_id'], true ); // Clean up orphaned attachment
            return $file_id;
        }
        
        $file = $file_model->get( $file_id );
        return new WP_REST_Response( $file, 201 );
    }

    public function upload_global_file( $request ) {
        $project_id = absint( $request->get_param( 'project_id' ) );
        if ( ! $project_id ) {
            return new WP_Error( 'missing_project', esc_html__( 'Project selection is required.', 'agency-os-ai' ), array( 'status' => 400 ) );
        }

        $permissions = AOSAI_Permission_Service::get_instance();
        if ( ! $permissions->can_upload_files( get_current_user_id(), $project_id ) ) {
            return new WP_Error( 'rest_forbidden', esc_html__( 'You cannot upload files to this project.', 'agency-os-ai' ), array( 'status' => 403 ) );
        }

        return $this->upload_file( $request );
    }
    
    public function delete_file( $request ) {
        $id = absint( $request->get_param( 'id' ) );
        
        $model = AOSAI_File::get_instance();
        $result = $model->delete( $id );
        
        if ( ! $result ) {
            return new WP_Error( 'not_found', esc_html__( 'File not found or could not be deleted.', 'agency-os-ai' ), array( 'status' => 404 ) );
        }
        
        return new WP_REST_Response( array( 'deleted' => true, 'id' => $id ), 200 );
    }
}
