<?php
if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

class AOSAI_REST_Milestones extends WP_REST_Controller {
    
    protected $namespace = 'aosai/v1';
    protected $rest_base = 'milestones';
    
    public function register_routes() {
        // Global milestones list and creation
        register_rest_route( $this->namespace, '/' . $this->rest_base, array(
            array(
                'methods'             => WP_REST_Server::READABLE,
                'callback'            => array( $this, 'get_my_milestones' ),
                'permission_callback' => array( $this, 'get_item_permissions_check' ),
            ),
            array(
                'methods'             => WP_REST_Server::CREATABLE,
                'callback'            => array( $this, 'create_global_milestone' ),
                'permission_callback' => array( $this, 'create_item_permissions_check' ),
            ),
        ) );

        register_rest_route( $this->namespace, '/projects/(?P<project_id>[\d]+)/milestones', array(
            array(
                'methods'             => WP_REST_Server::READABLE,
                'callback'            => array( $this, 'get_project_milestones' ),
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
        $model     = AOSAI_Milestone::get_instance();
        $milestone = $model->get( absint( $request->get_param( 'id' ) ) );
        if ( ! $milestone ) {
            return new WP_Error( 'not_found', esc_html__( 'Milestone not found.', 'agency-os-ai' ), array( 'status' => 404 ) );
        }

        $permissions = AOSAI_Permission_Service::get_instance();
        if ( ! $permissions->can_edit_project( get_current_user_id(), (int) $milestone['project_id'] ) ) {
            return new WP_Error( 'rest_forbidden', esc_html__( 'You cannot edit this milestone.', 'agency-os-ai' ), array( 'status' => 403 ) );
        }
        return true;
    }
    
    public function delete_item_permissions_check( $request ) {
        $model     = AOSAI_Milestone::get_instance();
        $milestone = $model->get( absint( $request->get_param( 'id' ) ) );
        if ( ! $milestone ) {
            return new WP_Error( 'not_found', esc_html__( 'Milestone not found.', 'agency-os-ai' ), array( 'status' => 404 ) );
        }

        $permissions = AOSAI_Permission_Service::get_instance();
        if ( ! $permissions->can_manage_project( get_current_user_id(), (int) $milestone['project_id'] ) ) {
            return new WP_Error( 'rest_forbidden', esc_html__( 'You cannot delete this milestone.', 'agency-os-ai' ), array( 'status' => 403 ) );
        }
        return true;
    }
    
    public function get_my_milestones( $request ) {
        $user_id = get_current_user_id();
        $args = array(
            'page'     => absint( $request->get_param( 'page' ) ) ?: 1,
            'per_page' => min( absint( $request->get_param( 'per_page' ) ) ?: 50, 100 ),
            'status'   => sanitize_key( $request->get_param( 'status' ) ) ?: '',
        );
        $model = AOSAI_Milestone::get_instance();
        $milestones = $model->get_all_for_user( $user_id, $args );
        return rest_ensure_response( $milestones );
    }

    public function create_global_milestone( $request ) {
        $data = $request->get_json_params();
        $model = AOSAI_Milestone::get_instance();
        $result = $model->create( $data );
        if ( is_wp_error( $result ) ) {
            return $result;
        }
        $milestone = $model->get( $result );
        return rest_ensure_response( $milestone );
    }

    public function get_project_milestones( $request ) {
        $project_id = absint( $request->get_param( 'project_id' ) );
        $model = AOSAI_Milestone::get_instance();
        $milestones = $model->get_project_milestones( $project_id );
        return rest_ensure_response( $milestones );
    }
    
    public function create_item( $request ) {
        $data = $request->get_json_params();
        $data['project_id'] = absint( $request->get_param( 'project_id' ) );
        
        $model = AOSAI_Milestone::get_instance();
        $result = $model->create( $data );
        
        if ( is_wp_error( $result ) ) {
            return $result;
        }
        
        $milestone = $model->get( $result );
        return rest_ensure_response( $milestone );
    }
    
    public function get_item( $request ) {
        $id = absint( $request->get_param( 'id' ) );
        $model = AOSAI_Milestone::get_instance();
        $milestone = $model->get( $id );
        
        if ( ! $milestone ) {
            return new WP_Error( 'not_found', esc_html__( 'Milestone not found.', 'agency-os-ai' ), array( 'status' => 404 ) );
        }
        
        return rest_ensure_response( $milestone );
    }
    
    public function update_item( $request ) {
        $id = absint( $request->get_param( 'id' ) );
        $data = $request->get_json_params();
        
        $model = AOSAI_Milestone::get_instance();
        $result = $model->update( $id, $data );
        
        if ( is_wp_error( $result ) ) {
            return $result;
        }
        
        $milestone = $model->get( $id );
        return rest_ensure_response( $milestone );
    }
    
    public function delete_item( $request ) {
        $id = absint( $request->get_param( 'id' ) );
        $model = AOSAI_Milestone::get_instance();
        $result = $model->delete( $id );
        
        if ( ! $result ) {
            return new WP_Error( 'not_found', esc_html__( 'Milestone not found.', 'agency-os-ai' ), array( 'status' => 404 ) );
        }
        
        return new WP_REST_Response( array( 'deleted' => true, 'id' => $id ), 200 );
    }
}
