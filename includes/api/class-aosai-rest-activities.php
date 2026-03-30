<?php
if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

class AOSAI_REST_Activities extends WP_REST_Controller {
    
    protected $namespace = 'aosai/v1';
    
    public function register_routes() {
        register_rest_route( $this->namespace, '/activities', array(
            array(
                'methods'             => WP_REST_Server::READABLE,
                'callback'            => array( $this, 'get_activities' ),
                'permission_callback' => array( $this, 'get_activities_permissions_check' ),
            ),
        ) );

        register_rest_route( $this->namespace, '/projects/(?P<project_id>[\d]+)/activities', array(
            array(
                'methods'             => WP_REST_Server::READABLE,
                'callback'            => array( $this, 'get_project_activities' ),
                'permission_callback' => array( $this, 'project_access_check' ),
            ),
        ) );
        
        register_rest_route( $this->namespace, '/activities/recent', array(
            array(
                'methods'             => WP_REST_Server::READABLE,
                'callback'            => array( $this, 'get_recent_activities' ),
                'permission_callback' => array( $this, 'get_activities_permissions_check' ),
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
    
    public function get_activities_permissions_check( $request ) {
        return is_user_logged_in() ? true : new WP_Error( 'rest_not_logged_in', esc_html__( 'You must be logged in.', 'agency-os-ai' ), array( 'status' => 401 ) );
    }

    public function get_activities( $request ) {
        $args = array(
            'page'     => absint( $request->get_param( 'page' ) ) ?: 1,
            'per_page' => min( absint( $request->get_param( 'per_page' ) ) ?: 20, 100 ),
        );
        
        $model = AOSAI_Activity::get_instance();
        $activities = $model->get_recent( get_current_user_id(), $args );

        return rest_ensure_response( $activities );
    }
    
    public function get_project_activities( $request ) {
        $project_id = absint( $request->get_param( 'project_id' ) );
        $args = array(
            'page'     => absint( $request->get_param( 'page' ) ) ?: 1,
            'per_page' => min( absint( $request->get_param( 'per_page' ) ) ?: 20, 100 ),
        );
        
        $model = AOSAI_Activity::get_instance();
        $activities = $model->get_project_activities( $project_id, $args );

        return rest_ensure_response( $activities );
    }
    
    public function get_recent_activities( $request ) {
        $user_id = get_current_user_id();
        $args = array(
            'page'     => absint( $request->get_param( 'page' ) ) ?: 1,
            'per_page' => min( absint( $request->get_param( 'per_page' ) ) ?: 20, 100 ),
        );
        
        $model = AOSAI_Activity::get_instance();
        $activities = $model->get_recent( $user_id, $args );

        return rest_ensure_response( $activities );
    }
}
