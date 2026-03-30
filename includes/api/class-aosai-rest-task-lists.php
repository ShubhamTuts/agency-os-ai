<?php
if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

class AOSAI_REST_Task_Lists extends WP_REST_Controller {
    use AOSAI_REST_Validation;
    
    protected $namespace = 'aosai/v1';
    protected $rest_base = 'task-lists';
    
    public function register_routes() {
        register_rest_route( $this->namespace, '/projects/(?P<project_id>[\d]+)/task-lists', array(
            array(
                'methods'             => WP_REST_Server::READABLE,
                'callback'            => array( $this, 'get_project_lists' ),
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
    
    public function update_item_permissions_check( $request ) {
        return is_user_logged_in() ? true : new WP_Error( 'rest_not_logged_in', esc_html__( 'You must be logged in.', 'agency-os-ai' ), array( 'status' => 401 ) );
    }
    
    public function delete_item_permissions_check( $request ) {
        return is_user_logged_in() ? true : new WP_Error( 'rest_not_logged_in', esc_html__( 'You must be logged in.', 'agency-os-ai' ), array( 'status' => 401 ) );
    }
    
    public function get_project_lists( $request ) {
        $project_id = absint( $request->get_param( 'project_id' ) );
        $model = AOSAI_Task_List::get_instance();
        $lists = $model->get_project_lists( $project_id );
        return rest_ensure_response( $lists );
    }
    
    public function create_item( $request ) {
        $data = $request->get_json_params();
        $data['project_id'] = absint( $request->get_param( 'project_id' ) );
        
        $model = AOSAI_Task_List::get_instance();
        $result = $model->create( $data );
        
        if ( is_wp_error( $result ) ) {
            return $result;
        }
        
        $list = $model->get( $result );
        return rest_ensure_response( $list );
    }
    
    public function get_item( $request ) {
        $id = absint( $request->get_param( 'id' ) );
        $model = AOSAI_Task_List::get_instance();
        $list = $model->get( $id );
        
        if ( ! $list ) {
            return new WP_Error( 'not_found', esc_html__( 'Task list not found.', 'agency-os-ai' ), array( 'status' => 404 ) );
        }
        
        return rest_ensure_response( $list );
    }
    
    public function update_item( $request ) {
        $id = absint( $request->get_param( 'id' ) );
        $data = $request->get_json_params();
        
        $model = AOSAI_Task_List::get_instance();
        $result = $model->update( $id, $data );
        
        if ( is_wp_error( $result ) ) {
            return $result;
        }
        
        $list = $model->get( $id );
        return rest_ensure_response( $list );
    }
    
    public function delete_item( $request ) {
        $id = absint( $request->get_param( 'id' ) );
        $model = AOSAI_Task_List::get_instance();
        $result = $model->delete( $id );
        
        if ( ! $result ) {
            return new WP_Error( 'not_found', esc_html__( 'Task list not found.', 'agency-os-ai' ), array( 'status' => 404 ) );
        }
        
        return new WP_REST_Response( array( 'deleted' => true, 'id' => $id ), 200 );
    }
}
