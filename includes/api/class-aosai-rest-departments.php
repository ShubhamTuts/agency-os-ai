<?php
if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

class AOSAI_REST_Departments extends WP_REST_Controller {
    protected $namespace = 'aosai/v1';

    public function register_routes() {
        register_rest_route(
            $this->namespace,
            '/departments',
            array(
                array(
                    'methods'             => WP_REST_Server::READABLE,
                    'callback'            => array( $this, 'get_items' ),
                    'permission_callback' => array( $this, 'permissions_check' ),
                ),
            )
        );
    }

    public function permissions_check( $request ) {
        return is_user_logged_in() ? true : new WP_Error( 'rest_not_logged_in', esc_html__( 'You must be logged in.', 'agency-os-ai' ), array( 'status' => 401 ) );
    }

    public function get_items( $request ) {
        return rest_ensure_response( AOSAI_Department::get_instance()->get_all() );
    }
}

