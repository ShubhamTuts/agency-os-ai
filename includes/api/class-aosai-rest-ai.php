<?php
if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

class AOSAI_REST_AI extends WP_REST_Controller {
    
    protected $namespace = 'aosai/v1';
    
    public function register_routes() {
        register_rest_route( $this->namespace, '/ai/generate-tasks', array(
            array(
                'methods'             => WP_REST_Server::CREATABLE,
                'callback'            => array( $this, 'generate_tasks' ),
                'permission_callback' => array( $this, 'ai_permissions_check' ),
            ),
        ) );
        
        register_rest_route( $this->namespace, '/ai/suggest-description', array(
            array(
                'methods'             => WP_REST_Server::CREATABLE,
                'callback'            => array( $this, 'suggest_description' ),
                'permission_callback' => array( $this, 'ai_permissions_check' ),
            ),
        ) );
        
        register_rest_route( $this->namespace, '/ai/chat', array(
            array(
                'methods'             => WP_REST_Server::CREATABLE,
                'callback'            => array( $this, 'chat' ),
                'permission_callback' => array( $this, 'ai_permissions_check' ),
            ),
        ) );
        
        register_rest_route( $this->namespace, '/ai/providers', array(
            array(
                'methods'             => WP_REST_Server::READABLE,
                'callback'            => array( $this, 'get_providers' ),
                'permission_callback' => array( $this, 'ai_permissions_check' ),
            ),
        ) );
        
        register_rest_route( $this->namespace, '/ai/models', array(
            array(
                'methods'             => WP_REST_Server::READABLE,
                'callback'            => array( $this, 'get_models' ),
                'permission_callback' => array( $this, 'ai_permissions_check' ),
            ),
        ) );
    }
    
    public function ai_permissions_check( $request ) {
        if ( ! is_user_logged_in() ) {
            return new WP_Error( 'rest_not_logged_in', esc_html__( 'You must be logged in.', 'agency-os-ai' ), array( 'status' => 401 ) );
        }
        if ( ! current_user_can( 'aosai_use_ai' ) ) {
            return new WP_Error( 'rest_forbidden', esc_html__( 'You do not have permission to use AI features.', 'agency-os-ai' ), array( 'status' => 403 ) );
        }
        return true;
    }
    
    public function generate_tasks( $request ) {
        $data = $request->get_json_params();
        
        $result = AOSAI_AI_Service::get_instance()->generate_tasks( array(
            'project_title'       => sanitize_text_field( $data['project_title'] ?? '' ),
            'project_description'=> sanitize_textarea_field( $data['project_description'] ?? '' ),
            'num_tasks'          => absint( $data['num_tasks'] ?? 15 ),
            'project_id'         => absint( $data['project_id'] ?? 0 ),
            'model'             => sanitize_text_field( $data['model'] ?? '' ),
            'provider'          => sanitize_key( $data['provider'] ?? '' ),
        ) );
        
        if ( is_wp_error( $result ) ) {
            return $result;
        }
        
        return rest_ensure_response( $result );
    }
    
    public function suggest_description( $request ) {
        $data = $request->get_json_params();
        
        $result = AOSAI_AI_Service::get_instance()->suggest_description(
            sanitize_text_field( $data['title'] ?? '' ),
            sanitize_textarea_field( $data['context'] ?? '' ),
            sanitize_key( $data['provider'] ?? '' )
        );
        
        if ( is_wp_error( $result ) ) {
            return $result;
        }
        
        return rest_ensure_response( array( 'description' => $result ) );
    }
    
    public function chat( $request ) {
        $data = $request->get_json_params();

        // Support both `message` (single string) and `messages` (array format)
        if ( ! empty( $data['message'] ) ) {
            $messages = array(
                array(
                    'role'    => 'user',
                    'content' => sanitize_textarea_field( wp_unslash( $data['message'] ) ),
                ),
            );
        } else {
            $messages = array_map( function( $m ) {
                return array(
                    'role'    => sanitize_key( $m['role'] ?? 'user' ),
                    'content' => sanitize_textarea_field( wp_unslash( $m['content'] ?? '' ) ),
                );
            }, (array) ( $data['messages'] ?? array() ) );
        }

        $result = AOSAI_AI_Service::get_instance()->chat( $messages, array(
            'model'    => sanitize_text_field( $data['model'] ?? '' ),
            'provider' => sanitize_key( $data['provider'] ?? '' ),
        ) );

        if ( is_wp_error( $result ) ) {
            return $result;
        }

        // Normalize: ensure response has `reply` field
        if ( is_array( $result ) && ! isset( $result['reply'] ) ) {
            $result['reply'] = $result['content'] ?? $result['message'] ?? '';
        }

        return rest_ensure_response( $result );
    }
    
    public function get_providers( $request ) {
        $providers = AOSAI_AI_Service::get_instance()->get_available_providers();
        $response = array( 'data' => $providers );
        return new WP_REST_Response( $response, 200 );
    }
    
    public function get_models( $request ) {
        $provider_id = sanitize_key( $request->get_param( 'provider' ) ) ?: 'openai';
        $provider = AOSAI_AI_Service::get_instance()->get_provider( $provider_id );
        
        if ( ! $provider ) {
            return new WP_Error( 'invalid_provider', esc_html__( 'Invalid AI provider.', 'agency-os-ai' ), array( 'status' => 400 ) );
        }
        
        $models = $provider->get_models();
        $response = array( 'data' => $models );
        return new WP_REST_Response( $response, 200 );
    }
}
