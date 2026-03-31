<?php
if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

class AOSAI_REST_Portal extends WP_REST_Controller {
    protected $namespace = 'aosai/v1';

    public function register_routes() {
        register_rest_route(
            $this->namespace,
            '/portal/bootstrap',
            array(
                array(
                    'methods'             => WP_REST_Server::READABLE,
                    'callback'            => array( $this, 'get_bootstrap' ),
                    'permission_callback' => array( $this, 'permissions_check' ),
                ),
            )
        );
    }

    public function permissions_check( $request ) {
        if ( ! is_user_logged_in() ) {
            return new WP_Error( 'rest_not_logged_in', esc_html__( 'You must be logged in.', 'agency-os-ai' ), array( 'status' => 401 ) );
        }

        if ( ! aosai_user_has_portal_access( get_current_user_id() ) ) {
            return new WP_Error( 'rest_forbidden', esc_html__( 'You do not have access to the portal.', 'agency-os-ai' ), array( 'status' => 403 ) );
        }

        return true;
    }

    public function get_bootstrap( $request ) {
        return rest_ensure_response( AOSAI_Portal_Service::get_instance()->build_bootstrap_payload( get_current_user_id() ) );
    }
}

