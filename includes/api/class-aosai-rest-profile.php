<?php
if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

class AOSAI_REST_Profile extends WP_REST_Controller {
    
    protected $namespace = 'aosai/v1';
    
    public function register_routes() {
        register_rest_route( $this->namespace, '/profile/me', array(
            array(
                'methods'             => WP_REST_Server::READABLE,
                'callback'            => array( $this, 'get_me' ),
                'permission_callback' => array( $this, 'permissions_check' ),
            ),
            array(
                'methods'             => 'POST, PUT, PATCH',
                'callback'            => array( $this, 'update_me' ),
                'permission_callback' => array( $this, 'permissions_check' ),
            ),
        ) );
    }
    
    public function permissions_check( $request ) {
        if ( ! is_user_logged_in() ) {
            return new WP_Error( 'rest_not_logged_in', esc_html__( 'You must be logged in.', 'agency-os-ai' ), array( 'status' => 401 ) );
        }
        return true;
    }
    
    public function get_me( $request ) {
        $user_id = get_current_user_id();
        $user_model = AOSAI_User::get_instance();
        $user_data = $user_model->get_formatted_user( $user_id );

        if ( ! $user_data ) {
            return new WP_Error( 'not_found', esc_html__( 'User not found.', 'agency-os-ai' ), array( 'status' => 404 ) );
        }

        return new WP_REST_Response( array( 'data' => $user_data ), 200 );
    }
    
    public function update_me( $request ) {
        $user_id = get_current_user_id();
        $data = $request->get_json_params();
        
        $user_model = AOSAI_User::get_instance();
        $result = $user_model->update_profile( $user_id, $data );
        
        if ( is_wp_error( $result ) ) {
            return $result;
        }
        
        $user_data = $user_model->get_formatted_user( $user_id );
        return new WP_REST_Response( array( 'data' => $user_data ), 200 );
    }
}
