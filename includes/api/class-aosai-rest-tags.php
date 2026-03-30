<?php
if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

class AOSAI_REST_Tags extends WP_REST_Controller {
    protected $namespace = 'aosai/v1';

    public function register_routes() {
        register_rest_route(
            $this->namespace,
            '/tags',
            array(
                array(
                    'methods'             => WP_REST_Server::READABLE,
                    'callback'            => array( $this, 'get_items' ),
                    'permission_callback' => array( $this, 'permissions_check' ),
                ),
                array(
                    'methods'             => WP_REST_Server::CREATABLE,
                    'callback'            => array( $this, 'create_item' ),
                    'permission_callback' => array( $this, 'manage_permissions_check' ),
                ),
            )
        );
    }

    public function permissions_check( $request ) {
        return is_user_logged_in() ? true : new WP_Error( 'rest_not_logged_in', esc_html__( 'You must be logged in.', 'agency-os-ai' ), array( 'status' => 401 ) );
    }

    public function manage_permissions_check( $request ) {
        if ( ! is_user_logged_in() ) {
            return new WP_Error( 'rest_not_logged_in', esc_html__( 'You must be logged in.', 'agency-os-ai' ), array( 'status' => 401 ) );
        }

        if ( ! current_user_can( 'aosai_manage_tags' ) && ! current_user_can( 'manage_options' ) ) {
            return new WP_Error( 'rest_forbidden', esc_html__( 'You cannot manage tags.', 'agency-os-ai' ), array( 'status' => 403 ) );
        }

        return true;
    }

    public function get_items( $request ) {
        $type = sanitize_key( (string) $request->get_param( 'type' ) );
        return rest_ensure_response( AOSAI_Tag::get_instance()->get_all( $type ) );
    }

    public function create_item( $request ) {
        $data = $request->get_json_params();
        $tag_id = AOSAI_Tag::get_instance()->ensure_tag( (string) ( $data['name'] ?? '' ), sanitize_key( (string) ( $data['type'] ?? 'general' ) ) );
        if ( ! $tag_id ) {
            return new WP_Error( 'invalid_tag', esc_html__( 'Tag name is required.', 'agency-os-ai' ), array( 'status' => 400 ) );
        }

        return rest_ensure_response( AOSAI_Tag::get_instance()->get_all( sanitize_key( (string) ( $data['type'] ?? 'general' ) ) ) );
    }
}

