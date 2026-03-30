<?php
if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

class AOSAI_REST_Settings extends WP_REST_Controller {
    
    protected $namespace = 'aosai/v1';
    
    public function register_routes() {
        register_rest_route( $this->namespace, '/settings', array(
            array(
                'methods'             => WP_REST_Server::READABLE,
                'callback'            => array( $this, 'get_settings' ),
                'permission_callback' => array( $this, 'get_settings_permissions_check' ),
            ),
            array(
                'methods'             => 'POST, PUT, PATCH',
                'callback'            => array( $this, 'update_settings' ),
                'permission_callback' => array( $this, 'update_settings_permissions_check' ),
            ),
        ) );
        
        register_rest_route( $this->namespace, '/settings/test-ai', array(
            array(
                'methods'             => WP_REST_Server::CREATABLE,
                'callback'            => array( $this, 'test_ai_connection' ),
                'permission_callback' => array( $this, 'update_settings_permissions_check' ),
            ),
        ) );

        register_rest_route( $this->namespace, '/settings/create-pages', array(
            array(
                'methods'             => WP_REST_Server::CREATABLE,
                'callback'            => array( $this, 'create_portal_pages' ),
                'permission_callback' => array( $this, 'update_settings_permissions_check' ),
            ),
        ) );
    }
    
    public function get_settings_permissions_check( $request ) {
        if ( ! is_user_logged_in() ) {
            return new WP_Error( 'rest_not_logged_in', esc_html__( 'You must be logged in.', 'agency-os-ai' ), array( 'status' => 401 ) );
        }
        if ( ! current_user_can( 'aosai_manage_settings' ) ) {
            return new WP_Error( 'rest_forbidden', esc_html__( 'You do not have permission to view settings.', 'agency-os-ai' ), array( 'status' => 403 ) );
        }
        return true;
    }
    
    public function update_settings_permissions_check( $request ) {
        if ( ! is_user_logged_in() ) {
            return new WP_Error( 'rest_not_logged_in', esc_html__( 'You must be logged in.', 'agency-os-ai' ), array( 'status' => 401 ) );
        }
        if ( ! current_user_can( 'aosai_manage_settings' ) ) {
            return new WP_Error( 'rest_forbidden', esc_html__( 'You do not have permission to manage settings.', 'agency-os-ai' ), array( 'status' => 403 ) );
        }
        return true;
    }
    
    public function get_settings( $request ) {
        $settings = AOSAI_Setting::get_instance()->get_all();
        return rest_ensure_response( $settings );
    }
    
    public function update_settings( $request ) {
        $data = $request->get_json_params();
        
        $result = AOSAI_Setting::get_instance()->update( $data );
        
        if ( is_wp_error( $result ) ) {
            return $result;
        }
        
        $settings = AOSAI_Setting::get_instance()->get_all();
        return rest_ensure_response( $settings );
    }
    
    public function test_ai_connection( $request ) {
        $data     = $request->get_json_params();
        $provider = sanitize_key( $data['provider'] ?? '' );
        $api_key  = sanitize_text_field( $data['api_key'] ?? '' );
        $model    = sanitize_key( $data['model'] ?? '' );

        $result = AOSAI_Setting::get_instance()->test_ai_connection( $provider, $api_key, $model );

        if ( is_wp_error( $result ) ) {
            return $result;
        }

        return rest_ensure_response( $result );
    }

    public function create_portal_pages( $request ) {
        $result = AOSAI_Portal_Service::get_instance()->create_default_pages();
        if ( is_wp_error( $result ) ) {
            return $result;
        }

        return rest_ensure_response(
            array(
                'pages'    => $result,
                'settings' => AOSAI_Setting::get_instance()->get_all(),
            )
        );
    }
}
