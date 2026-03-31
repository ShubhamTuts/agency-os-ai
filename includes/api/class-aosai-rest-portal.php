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

        register_rest_route(
            $this->namespace,
            '/portal/login-activity',
            array(
                array(
                    'methods'             => WP_REST_Server::READABLE,
                    'callback'            => array( $this, 'get_login_activity' ),
                    'permission_callback' => array( $this, 'login_activity_permissions_check' ),
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

    public function login_activity_permissions_check( $request ) {
        if ( ! is_user_logged_in() ) {
            return new WP_Error( 'rest_not_logged_in', esc_html__( 'You must be logged in.', 'agency-os-ai' ), array( 'status' => 401 ) );
        }

        $user_id = get_current_user_id();
        if ( current_user_can( 'manage_options' ) || current_user_can( 'aosai_manage_settings' ) || aosai_user_has_portal_access( $user_id ) ) {
            return true;
        }

        return new WP_Error( 'rest_forbidden', esc_html__( 'You do not have access to login activity.', 'agency-os-ai' ), array( 'status' => 403 ) );
    }

    public function get_bootstrap( $request ) {
        $ip = $this->get_client_ip( $request );
        $now = current_time( 'mysql' );
        $user_id = get_current_user_id();

        // Track last portal access for auditing/analytics.
        update_user_meta( $user_id, 'aosai_last_portal_ip', $ip );
        update_user_meta( $user_id, 'aosai_last_portal_seen', $now );

        $payload = AOSAI_Portal_Service::get_instance()->build_bootstrap_payload( $user_id );
        $payload['session'] = array(
            'ip'        => $ip,
            'last_seen' => $now,
        );

        return rest_ensure_response( $payload );
    }

    public function get_login_activity( $request ) {
        $current_user_id    = get_current_user_id();
        $can_view_workspace = current_user_can( 'manage_options' ) || current_user_can( 'aosai_manage_settings' ) || current_user_can( 'aosai_manage_tickets' );

        $args = array(
            'page'        => max( 1, absint( $request->get_param( 'page' ) ?: 1 ) ),
            'per_page'    => min( 100, max( 1, absint( $request->get_param( 'per_page' ) ?: 20 ) ) ),
            'event_type'  => sanitize_key( (string) $request->get_param( 'event_type' ) ),
            'portal_type' => sanitize_key( (string) $request->get_param( 'portal_type' ) ),
        );

        $requested_user_id = absint( $request->get_param( 'user_id' ) );
        $model             = AOSAI_Login_Activity::get_instance();

        if ( $can_view_workspace ) {
            $events = $requested_user_id > 0
                ? $model->get_events_for_user( $requested_user_id, $args )
                : $model->get_events_for_workspace( $args );
        } else {
            $events = $model->get_events_for_user( $current_user_id, $args );
        }

        return rest_ensure_response(
            array(
                'data'               => $events,
                'can_view_workspace' => $can_view_workspace,
            )
        );
    }

    private function get_client_ip( WP_REST_Request $request ): string {
        $headers = array(
            'cf-connecting-ip',
            'x-real-ip',
            'x-forwarded-for',
            'remote_addr',
        );

        foreach ( $headers as $header ) {
            $value = (string) $request->get_header( $header );
            if ( '' === trim( $value ) ) {
                continue;
            }

            $candidate = trim( explode( ',', $value )[0] );
            if ( filter_var( $candidate, FILTER_VALIDATE_IP ) ) {
                return sanitize_text_field( $candidate );
            }
        }

        $server_headers = array( 'HTTP_CF_CONNECTING_IP', 'HTTP_X_REAL_IP', 'HTTP_X_FORWARDED_FOR', 'REMOTE_ADDR' );
        foreach ( $server_headers as $header ) {
            if ( empty( $_SERVER[ $header ] ) ) {
                continue;
            }

            $candidate = trim( explode( ',', sanitize_text_field( wp_unslash( (string) $_SERVER[ $header ] ) ) )[0] );
            if ( filter_var( $candidate, FILTER_VALIDATE_IP ) ) {
                return $candidate;
            }
        }

        return '';
    }
}

