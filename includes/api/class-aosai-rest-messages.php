<?php
if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

class AOSAI_REST_Messages extends WP_REST_Controller {
    
    protected $namespace = 'aosai/v1';
    protected $rest_base = 'messages';
    
    public function register_routes() {
        // Global messages (across all user projects)
        register_rest_route( $this->namespace, '/' . $this->rest_base, array(
            array(
                'methods'             => WP_REST_Server::READABLE,
                'callback'            => array( $this, 'get_my_messages' ),
                'permission_callback' => array( $this, 'get_item_permissions_check' ),
            ),
            array(
                'methods'             => WP_REST_Server::CREATABLE,
                'callback'            => array( $this, 'create_global_message' ),
                'permission_callback' => array( $this, 'create_item_permissions_check' ),
            ),
        ) );

        register_rest_route( $this->namespace, '/projects/(?P<project_id>[\d]+)/messages', array(
            array(
                'methods'             => WP_REST_Server::READABLE,
                'callback'            => array( $this, 'get_project_messages' ),
                'permission_callback' => array( $this, 'project_access_check' ),
            ),
            array(
                'methods'             => WP_REST_Server::CREATABLE,
                'callback'            => array( $this, 'create_item' ),
                'permission_callback' => array( $this, 'project_edit_check' ),
            ),
        ) );
        
        register_rest_route( $this->namespace, '/' . $this->rest_base . '/(?P<id>[\d]+)', array(
            array(
                'methods'             => WP_REST_Server::READABLE,
                'callback'            => array( $this, 'get_item' ),
                'permission_callback' => array( $this, 'get_item_permissions_check' ),
            ),
            array(
                'methods'             => WP_REST_Server::EDITABLE,
                'callback'            => array( $this, 'update_item' ),
                'permission_callback' => array( $this, 'update_item_permissions_check' ),
            ),
            array(
                'methods'             => WP_REST_Server::DELETABLE,
                'callback'            => array( $this, 'delete_item' ),
                'permission_callback' => array( $this, 'delete_item_permissions_check' ),
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
        return $permissions->can_edit_project( get_current_user_id(), absint( $request->get_param( 'project_id' ) ) ) 
            ? true 
            : new WP_Error( 'rest_forbidden', esc_html__( 'Access denied.', 'agency-os-ai' ), array( 'status' => 403 ) );
    }
    
    public function get_item_permissions_check( $request ) {
        return is_user_logged_in() ? true : new WP_Error( 'rest_not_logged_in', esc_html__( 'You must be logged in.', 'agency-os-ai' ), array( 'status' => 401 ) );
    }

    public function create_item_permissions_check( $request ) {
        return is_user_logged_in() ? true : new WP_Error( 'rest_not_logged_in', esc_html__( 'You must be logged in.', 'agency-os-ai' ), array( 'status' => 401 ) );
    }
    
    public function update_item_permissions_check( $request ) {
        $model = AOSAI_Message::get_instance();
        $message = $model->get( absint( $request->get_param( 'id' ) ) );
        if ( ! $message || (int) ( $message['created_by'] ?? 0 ) !== get_current_user_id() ) {
            return new WP_Error( 'rest_forbidden', esc_html__( 'You cannot edit this message.', 'agency-os-ai' ), array( 'status' => 403 ) );
        }
        return true;
    }
    
    public function delete_item_permissions_check( $request ) {
        $model = AOSAI_Message::get_instance();
        $message = $model->get( absint( $request->get_param( 'id' ) ) );
        if ( ! $message || (int) ( $message['created_by'] ?? 0 ) !== get_current_user_id() ) {
            return new WP_Error( 'rest_forbidden', esc_html__( 'You cannot delete this message.', 'agency-os-ai' ), array( 'status' => 403 ) );
        }
        return true;
    }
    
    public function get_my_messages( $request ) {
        $user_id = get_current_user_id();
        $args = array(
            'page'     => absint( $request->get_param( 'page' ) ) ?: 1,
            'per_page' => min( absint( $request->get_param( 'per_page' ) ) ?: 20, 100 ),
            'search'   => sanitize_text_field( $request->get_param( 'search' ) ) ?: '',
        );
        $model    = AOSAI_Message::get_instance();
        $messages = $model->get_messages_for_user( $user_id, $args );
        return rest_ensure_response( $messages );
    }

    public function create_global_message( $request ) {
        $data = $request->get_json_params();
        if ( ! empty( $data['project_id'] ) ) {
            $data['project_id'] = absint( $data['project_id'] );
        } else {
            $data['project_id'] = 0;
        }
        $model  = AOSAI_Message::get_instance();
        $result = $model->create( $data );
        if ( is_wp_error( $result ) ) {
            return $result;
        }
        $message = $model->get( $result );
        return rest_ensure_response( $message );
    }

    public function get_project_messages( $request ) {
        $project_id = absint( $request->get_param( 'project_id' ) );
        $args = array(
            'page'   => absint( $request->get_param( 'page' ) ) ?: 1,
            'per_page'=> min( absint( $request->get_param( 'per_page' ) ) ?: 20, 100 ),
            'search' => sanitize_text_field( $request->get_param( 'search' ) ) ?: '',
        );
        
        $model = AOSAI_Message::get_instance();
        $messages = $model->get_project_messages( $project_id, $args );
        return rest_ensure_response( $messages );
    }
    
    public function create_item( $request ) {
        $data = $request->get_json_params();
        $data['project_id'] = absint( $request->get_param( 'project_id' ) );
        
        $model = AOSAI_Message::get_instance();
        $result = $model->create( $data );
        
        if ( is_wp_error( $result ) ) {
            return $result;
        }
        
        $message = $model->get( $result );
        return rest_ensure_response( $message );
    }
    
    public function get_item( $request ) {
        $id = absint( $request->get_param( 'id' ) );
        $model = AOSAI_Message::get_instance();
        $message = $model->get( $id );
        
        if ( ! $message ) {
            return new WP_Error( 'not_found', esc_html__( 'Message not found.', 'agency-os-ai' ), array( 'status' => 404 ) );
        }
        
        return rest_ensure_response( $message );
    }
    
    public function update_item( $request ) {
        $id = absint( $request->get_param( 'id' ) );
        $data = $request->get_json_params();
        
        $model = AOSAI_Message::get_instance();
        $result = $model->update( $id, $data );
        
        if ( is_wp_error( $result ) ) {
            return $result;
        }
        
        $message = $model->get( $id );
        return rest_ensure_response( $message );
    }
    
    public function delete_item( $request ) {
        $id = absint( $request->get_param( 'id' ) );
        $model = AOSAI_Message::get_instance();
        $result = $model->delete( $id );
        
        if ( ! $result ) {
            return new WP_Error( 'not_found', esc_html__( 'Message not found.', 'agency-os-ai' ), array( 'status' => 404 ) );
        }
        
        return new WP_REST_Response( array( 'deleted' => true, 'id' => $id ), 200 );
    }
}
