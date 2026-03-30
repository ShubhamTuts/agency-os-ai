<?php
if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

class AOSAI_REST_Webhooks extends WP_REST_Controller {

    protected $namespace = 'agency-os-ai/v1';
    protected $rest_base = 'webhooks';

    public function register_routes(): void {
        register_rest_route(
            $this->namespace,
            '/' . $this->rest_base,
            array(
                array(
                    'methods'             => WP_REST_Server::READABLE,
                    'callback'            => array( $this, 'get_items' ),
                    'permission_callback' => array( $this, 'admin_permissions_check' ),
                ),
                array(
                    'methods'             => WP_REST_Server::CREATABLE,
                    'callback'            => array( $this, 'create_item' ),
                    'permission_callback' => array( $this, 'admin_permissions_check' ),
                    'args'                => $this->get_create_args(),
                ),
            )
        );

        register_rest_route(
            $this->namespace,
            '/' . $this->rest_base . '/(?P<id>\d+)',
            array(
                array(
                    'methods'             => WP_REST_Server::READABLE,
                    'callback'            => array( $this, 'get_item' ),
                    'permission_callback' => array( $this, 'admin_permissions_check' ),
                    'args'                => array( 'id' => array( 'validate_callback' => 'is_numeric' ) ),
                ),
                array(
                    'methods'             => WP_REST_Server::EDITABLE,
                    'callback'            => array( $this, 'update_item' ),
                    'permission_callback' => array( $this, 'admin_permissions_check' ),
                    'args'                => array( 'id' => array( 'validate_callback' => 'is_numeric' ) ),
                ),
                array(
                    'methods'             => WP_REST_Server::DELETABLE,
                    'callback'            => array( $this, 'delete_item' ),
                    'permission_callback' => array( $this, 'admin_permissions_check' ),
                    'args'                => array( 'id' => array( 'validate_callback' => 'is_numeric' ) ),
                ),
            )
        );

        register_rest_route(
            $this->namespace,
            '/' . $this->rest_base . '/(?P<id>\d+)/test',
            array(
                array(
                    'methods'             => WP_REST_Server::CREATABLE,
                    'callback'            => array( $this, 'test_webhook' ),
                    'permission_callback' => array( $this, 'admin_permissions_check' ),
                    'args'                => array( 'id' => array( 'validate_callback' => 'is_numeric' ) ),
                ),
            )
        );

        register_rest_route(
            $this->namespace,
            '/smtp/test',
            array(
                array(
                    'methods'             => WP_REST_Server::CREATABLE,
                    'callback'            => array( $this, 'test_smtp' ),
                    'permission_callback' => array( $this, 'admin_permissions_check' ),
                ),
            )
        );
    }

    public function get_items( WP_REST_Request $request ): WP_REST_Response {
        global $wpdb;

        $items = $wpdb->get_results(
            "SELECT * FROM {$wpdb->prefix}aosai_webhooks ORDER BY created_at DESC",
            ARRAY_A
        );

        return rest_ensure_response( $items ?: array() );
    }

    public function get_item( WP_REST_Request $request ): WP_REST_Response|WP_Error {
        global $wpdb;

        $item = $wpdb->get_row(
            $wpdb->prepare( "SELECT * FROM {$wpdb->prefix}aosai_webhooks WHERE id = %d", absint( $request['id'] ) ),
            ARRAY_A
        );

        if ( ! $item ) {
            return new WP_Error( 'aosai_not_found', __( 'Webhook not found.', 'agency-os-ai' ), array( 'status' => 404 ) );
        }

        return rest_ensure_response( $item );
    }

    public function create_item( WP_REST_Request $request ): WP_REST_Response|WP_Error {
        global $wpdb;

        $name   = sanitize_text_field( (string) $request->get_param( 'name' ) );
        $url    = esc_url_raw( (string) $request->get_param( 'url' ) );
        $events = sanitize_text_field( (string) $request->get_param( 'events' ) );

        if ( empty( $name ) || empty( $url ) ) {
            return new WP_Error( 'aosai_missing_fields', __( 'Name and URL are required.', 'agency-os-ai' ), array( 'status' => 400 ) );
        }

        if ( ! filter_var( $url, FILTER_VALIDATE_URL ) ) {
            return new WP_Error( 'aosai_invalid_url', __( 'Invalid webhook URL.', 'agency-os-ai' ), array( 'status' => 400 ) );
        }

        $secret = wp_generate_password( 32, false );

        $result = $wpdb->insert(
            $wpdb->prefix . 'aosai_webhooks',
            array(
                'name'      => $name,
                'url'       => $url,
                'events'    => $events ?: 'all',
                'secret'    => $secret,
                'is_active' => 1,
            ),
            array( '%s', '%s', '%s', '%s', '%d' )
        );

        if ( ! $result ) {
            return new WP_Error( 'aosai_insert_failed', __( 'Failed to create webhook.', 'agency-os-ai' ), array( 'status' => 500 ) );
        }

        $item = $wpdb->get_row(
            $wpdb->prepare( "SELECT * FROM {$wpdb->prefix}aosai_webhooks WHERE id = %d", $wpdb->insert_id ),
            ARRAY_A
        );

        return new WP_REST_Response( $item, 201 );
    }

    public function update_item( WP_REST_Request $request ): WP_REST_Response|WP_Error {
        global $wpdb;

        $id = absint( $request['id'] );
        $existing = $wpdb->get_row( $wpdb->prepare( "SELECT id FROM {$wpdb->prefix}aosai_webhooks WHERE id = %d", $id ) );

        if ( ! $existing ) {
            return new WP_Error( 'aosai_not_found', __( 'Webhook not found.', 'agency-os-ai' ), array( 'status' => 404 ) );
        }

        $data   = array();
        $format = array();

        if ( null !== $request->get_param( 'name' ) ) {
            $data['name']   = sanitize_text_field( (string) $request->get_param( 'name' ) );
            $format[]       = '%s';
        }

        if ( null !== $request->get_param( 'url' ) ) {
            $url = esc_url_raw( (string) $request->get_param( 'url' ) );
            if ( ! filter_var( $url, FILTER_VALIDATE_URL ) ) {
                return new WP_Error( 'aosai_invalid_url', __( 'Invalid webhook URL.', 'agency-os-ai' ), array( 'status' => 400 ) );
            }
            $data['url']  = $url;
            $format[]     = '%s';
        }

        if ( null !== $request->get_param( 'events' ) ) {
            $data['events'] = sanitize_text_field( (string) $request->get_param( 'events' ) );
            $format[]       = '%s';
        }

        if ( null !== $request->get_param( 'is_active' ) ) {
            $data['is_active'] = absint( $request->get_param( 'is_active' ) ) ? 1 : 0;
            $format[]          = '%d';
        }

        if ( ! empty( $data ) ) {
            $wpdb->update( $wpdb->prefix . 'aosai_webhooks', $data, array( 'id' => $id ), $format, array( '%d' ) );
        }

        $item = $wpdb->get_row( $wpdb->prepare( "SELECT * FROM {$wpdb->prefix}aosai_webhooks WHERE id = %d", $id ), ARRAY_A );
        return rest_ensure_response( $item );
    }

    public function delete_item( WP_REST_Request $request ): WP_REST_Response|WP_Error {
        global $wpdb;

        $id = absint( $request['id'] );
        $existing = $wpdb->get_row( $wpdb->prepare( "SELECT id FROM {$wpdb->prefix}aosai_webhooks WHERE id = %d", $id ) );

        if ( ! $existing ) {
            return new WP_Error( 'aosai_not_found', __( 'Webhook not found.', 'agency-os-ai' ), array( 'status' => 404 ) );
        }

        $wpdb->delete( $wpdb->prefix . 'aosai_webhooks', array( 'id' => $id ), array( '%d' ) );
        return rest_ensure_response( array( 'deleted' => true ) );
    }

    public function test_webhook( WP_REST_Request $request ): WP_REST_Response|WP_Error {
        global $wpdb;

        $id = absint( $request['id'] );
        $webhook = $wpdb->get_row( $wpdb->prepare( "SELECT * FROM {$wpdb->prefix}aosai_webhooks WHERE id = %d", $id ), ARRAY_A );

        if ( ! $webhook ) {
            return new WP_Error( 'aosai_not_found', __( 'Webhook not found.', 'agency-os-ai' ), array( 'status' => 404 ) );
        }

        $test_payload = array(
            'event'     => 'webhook.test',
            'timestamp' => current_time( 'timestamp' ),
            'data'      => array(
                'message' => __( 'This is a test webhook from Agency OS AI.', 'agency-os-ai' ),
                'site_url' => home_url( '/' ),
            ),
        );

        $body      = wp_json_encode( $test_payload );
        $signature = hash_hmac( 'sha256', (string) $body, (string) $webhook['secret'] );

        $response = wp_remote_post(
            esc_url_raw( (string) $webhook['url'] ),
            array(
                'timeout' => 10,
                'blocking' => true,
                'headers' => array(
                    'Content-Type'      => 'application/json',
                    'X-AOSAI-Event'     => 'webhook.test',
                    'X-AOSAI-Signature' => 'sha256=' . $signature,
                ),
                'body'        => $body,
                'data_format' => 'body',
            )
        );

        if ( is_wp_error( $response ) ) {
            return new WP_REST_Response( array( 'success' => false, 'message' => $response->get_error_message() ), 200 );
        }

        $code = wp_remote_retrieve_response_code( $response );
        return rest_ensure_response( array(
            'success'     => $code >= 200 && $code < 300,
            'status_code' => $code,
            'message'     => "HTTP {$code}",
        ) );
    }

    public function test_smtp( WP_REST_Request $request ): WP_REST_Response {
        $result = AOSAI_SMTP_Service::get_instance()->test_connection();
        return rest_ensure_response( $result );
    }

    public function admin_permissions_check(): bool {
        return current_user_can( 'manage_options' );
    }

    private function get_create_args(): array {
        return array(
            'name'   => array( 'required' => true, 'sanitize_callback' => 'sanitize_text_field' ),
            'url'    => array( 'required' => true, 'sanitize_callback' => 'esc_url_raw' ),
            'events' => array( 'required' => false, 'sanitize_callback' => 'sanitize_text_field' ),
        );
    }
}
