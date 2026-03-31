<?php
if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

class AOSAI_REST_Inbound extends WP_REST_Controller {

    protected $namespace = 'aosai/v1';
    protected $legacy_namespace = 'agency-os-ai/v1';
    protected $rest_base = 'inbound';

    public function register_routes(): void {
        foreach ( array_unique( array( $this->namespace, $this->legacy_namespace ) ) as $namespace ) {
            register_rest_route(
                $namespace,
                '/' . $this->rest_base . '/email',
                array(
                    array(
                        'methods'             => WP_REST_Server::CREATABLE,
                        'callback'            => array( $this, 'receive_email' ),
                        'permission_callback' => array( $this, 'verify_inbound_token' ),
                        'args'                => $this->get_inbound_args(),
                    ),
                ),
            );

            register_rest_route(
                $namespace,
                '/' . $this->rest_base . '/email-pipe',
                array(
                    array(
                        'methods'             => WP_REST_Server::CREATABLE,
                        'callback'            => array( $this, 'receive_email' ),
                        'permission_callback' => array( $this, 'verify_inbound_token' ),
                        'args'                => $this->get_inbound_args(),
                    ),
                ),
            );
        }
    }

    public function receive_email( $request ) {
        global $wpdb;

        $from_email = sanitize_email( (string) $request->get_param( 'from_email' ) );
        $from_name  = sanitize_text_field( (string) ( $request->get_param( 'from_name' ) ?: '' ) );
        $subject    = sanitize_text_field( (string) $request->get_param( 'subject' ) );
        $body       = wp_kses_post( (string) ( $request->get_param( 'body_plain' ) ?: $request->get_param( 'body_html' ) ?: '' ) );
        $priority   = sanitize_key( (string) ( $request->get_param( 'priority' ) ?: 'medium' ) );

        if ( empty( $from_email ) || empty( $subject ) ) {
            return new WP_Error( 'aosai_missing_fields', __( 'from_email and subject are required.', 'agency-os-ai' ), array( 'status' => 400 ) );
        }

        $user = get_user_by( 'email', $from_email );
        $requester_id = 0;

        if ( $user ) {
            $requester_id = $user->ID;
        } else {
            $username = sanitize_user( strtok( $from_email, '@' ) . '_guest' );
            $existing = username_exists( $username );
            if ( ! $existing ) {
                $user_id = wp_create_user( $username, wp_generate_password(), $from_email );
                if ( ! is_wp_error( $user_id ) ) {
                    $user_obj = get_userdata( $user_id );
                    if ( $user_obj ) {
                        $user_obj->set_role( 'aosai_client' );
                        if ( $from_name ) {
                            $parts = explode( ' ', $from_name, 2 );
                            wp_update_user( array( 'ID' => $user_id, 'first_name' => $parts[0], 'last_name' => $parts[1] ?? '' ) );
                        }
                        $requester_id = $user_id;
                    }
                }
            } else {
                $existing_user = get_user_by( 'login', $existing );
                $requester_id  = $existing_user ? $existing_user->ID : 0;
            }
        }

        if ( $requester_id <= 0 ) {
            return new WP_Error( 'aosai_user_error', __( 'Could not resolve requester user.', 'agency-os-ai' ), array( 'status' => 422 ) );
        }

        $department_id = $this->route_to_department( $subject . ' ' . $body );
        $assignee_id   = $this->get_department_assignee( $department_id );
        $ai_summary    = $this->get_ai_summary( $subject, $body );

        $ticket_data = array(
            'requester_id'  => $requester_id,
            'department_id' => $department_id ?: null,
            'assignee_id'   => $assignee_id ?: null,
            'subject'       => $subject,
            'content'       => $body,
            'status'        => 'open',
            'priority'      => in_array( $priority, array( 'low', 'medium', 'high', 'urgent' ), true ) ? $priority : 'medium',
            'source'        => 'email',
            'ai_summary'    => $ai_summary,
        );

        $formats = array( '%d', '%d', '%d', '%s', '%s', '%s', '%s', '%s', '%s' );

        $inserted = $wpdb->insert(
            $wpdb->prefix . 'aosai_tickets',
            $ticket_data,
            $formats
        );

        if ( ! $inserted ) {
            return new WP_Error( 'aosai_insert_failed', __( 'Failed to create ticket.', 'agency-os-ai' ), array( 'status' => 500 ) );
        }

        $ticket_id = $wpdb->insert_id;

        $ticket_data['id']             = $ticket_id;
        $ticket_data['requester_email'] = $from_email;
        $ticket_data['requester_name']  = $from_name ?: $from_email;

        do_action( 'aosai_ticket_created', $ticket_id, $ticket_data );

        if ( 'yes' === get_option( 'aosai_email_notifications', 'yes' ) ) {
            AOSAI_Email_Service::get_instance()->send_ticket_created_emails( $ticket_data );

            if ( $assignee_id ) {
                $assignee = get_userdata( $assignee_id );
                if ( $assignee ) {
                    AOSAI_Email_Service::get_instance()->send_ticket_assigned_email( $assignee, $ticket_data );
                }
            }
        }

        AOSAI_Webhook_Service::get_instance()->dispatch( 'ticket.created', array(
            'ticket_id'     => $ticket_id,
            'subject'       => $subject,
            'source'        => 'email',
            'department_id' => $department_id,
        ) );

        return new WP_REST_Response( array( 'ticket_id' => $ticket_id, 'status' => 'created' ), 201 );
    }

    private function route_to_department( string $text ): int {
        $inbound_routing = (string) get_option( 'aosai_inbound_ai_routing', get_option( 'aosai_ticket_ai_routing', 'yes' ) );
        if ( 'yes' !== $inbound_routing ) {
            return $this->get_default_department_id();
        }

        global $wpdb;
        $departments = $wpdb->get_results(
            "SELECT id, keywords FROM {$wpdb->prefix}aosai_departments WHERE keywords IS NOT NULL AND keywords != '' ORDER BY is_default DESC",
            ARRAY_A
        );

        if ( empty( $departments ) ) {
            return $this->get_default_department_id();
        }

        $text_lower = strtolower( $text );
        foreach ( $departments as $dept ) {
            $keywords = explode( ',', strtolower( (string) $dept['keywords'] ) );
            foreach ( $keywords as $kw ) {
                $kw = trim( $kw );
                if ( $kw && strpos( $text_lower, $kw ) !== false ) {
                    return (int) $dept['id'];
                }
            }
        }

        return $this->get_default_department_id();
    }

    private function get_default_department_id(): int {
        global $wpdb;
        $id = $wpdb->get_var( "SELECT id FROM {$wpdb->prefix}aosai_departments WHERE is_default = 1 LIMIT 1" );
        if ( ! $id ) {
            $id = $wpdb->get_var( "SELECT id FROM {$wpdb->prefix}aosai_departments ORDER BY id ASC LIMIT 1" );
        }
        return (int) $id;
    }

    private function get_department_assignee( int $department_id ): int {
        if ( $department_id <= 0 ) {
            return 0;
        }

        global $wpdb;
        $assignee_id = $wpdb->get_var(
            $wpdb->prepare(
                "SELECT default_assignee_id FROM {$wpdb->prefix}aosai_departments WHERE id = %d",
                $department_id
            )
        );

        return (int) $assignee_id;
    }

    private function get_ai_summary( string $subject, string $body ): string {
        $openai_key = get_option( 'aosai_openai_api_key', '' );
        if ( empty( $openai_key ) ) {
            return '';
        }

        try {
            $ai_service = AOSAI_AI_Service::get_instance();
            $prompt     = sprintf(
                'Summarize this support ticket in 1-2 sentences:\nSubject: %s\n\n%s',
                $subject,
                wp_strip_all_tags( substr( $body, 0, 800 ) )
            );
            return (string) $ai_service->complete( $prompt, array( 'max_tokens' => 100 ) );
        } catch ( \Exception $e ) {
            return '';
        }
    }

    public function verify_inbound_token( $request ) {
        $stored_token = get_option( 'aosai_inbound_email_token', '' );
        if ( empty( $stored_token ) ) {
            return false;
        }

        $provided_token = sanitize_text_field( (string) ( $request->get_param( 'token' ) ?: $request->get_header( 'X-AOSAI-Inbound-Token' ) ?: '' ) );
        return hash_equals( $stored_token, $provided_token );
    }

    private function get_inbound_args(): array {
        return array(
            'from_email' => array(
                'required'          => true,
                'sanitize_callback' => 'sanitize_email',
            ),
            'from_name'  => array(
                'required'          => false,
                'sanitize_callback' => 'sanitize_text_field',
            ),
            'subject'    => array(
                'required'          => true,
                'sanitize_callback' => 'sanitize_text_field',
            ),
            'body_plain' => array(
                'required'          => false,
            ),
            'body_html'  => array(
                'required'          => false,
            ),
            'priority'   => array(
                'required'          => false,
                'sanitize_callback' => 'sanitize_key',
            ),
            'token'      => array(
                'required'          => false,
                'sanitize_callback' => 'sanitize_text_field',
            ),
        );
    }
}
