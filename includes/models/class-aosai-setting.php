<?php

if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

class AOSAI_Setting {
    use AOSAI_Singleton;

    public const MASKED_API_KEY = '********';
    public const MASKED_SECRET  = '********';

    private array $key_map = array();
    private array $defaults = array();

    private function __construct() {
        $this->key_map = array(
            'openai_api_key'          => 'aosai_openai_api_key',
            'default_model'           => 'aosai_openai_model',
            'email_notifications'     => 'aosai_email_notifications',
            'timezone'                => 'aosai_timezone',
            'date_format'             => 'aosai_date_format',
            'primary_color'           => 'aosai_primary_color',
            'company_name'            => 'aosai_company_name',
            'company_email'           => 'aosai_company_email',
            'company_phone'           => 'aosai_company_phone',
            'company_website'         => 'aosai_company_website',
            'privacy_policy_url'      => 'aosai_policy_privacy_url',
            'terms_url'               => 'aosai_policy_terms_url',
            'company_address'         => 'aosai_company_address',
            'company_logo_url'        => 'aosai_company_logo_url',
            'support_email'           => 'aosai_support_email',
            'email_from_name'         => 'aosai_email_from_name',
            'email_from_email'        => 'aosai_email_from_email',
            'email_footer_text'       => 'aosai_email_footer_text',
            'smtp_enabled'            => 'aosai_smtp_enabled',
            'smtp_host'               => 'aosai_smtp_host',
            'smtp_port'               => 'aosai_smtp_port',
            'smtp_username'           => 'aosai_smtp_username',
            'smtp_password'           => 'aosai_smtp_password',
            'smtp_encryption'         => 'aosai_smtp_encryption',
            'smtp_auth'               => 'aosai_smtp_auth',
            'inbound_ai_routing'      => 'aosai_inbound_ai_routing',
            'inbound_email_token'     => 'aosai_inbound_email_token',
            'portal_name'             => 'aosai_portal_name',
            'portal_welcome_title'    => 'aosai_portal_welcome_title',
            'portal_welcome_text'     => 'aosai_portal_welcome_text',
            'portal_secondary_color'  => 'aosai_portal_secondary_color',
            'portal_page_id'          => 'aosai_portal_page_id',
            'portal_login_page_id'    => 'aosai_portal_login_page_id',
            'portal_ticket_page_id'   => 'aosai_portal_ticket_page_id',
            'hide_admin_bar'          => 'aosai_portal_hide_admin_bar',
            'force_frontend_dashboard'=> 'aosai_portal_force_frontend',
            'enable_pwa'              => 'aosai_portal_enable_pwa',
            'show_footer_credit'      => 'aosai_show_footer_credit',
            'footer_credit_text'      => 'aosai_footer_credit_text',
            'ticket_ai_routing'       => 'aosai_ticket_ai_routing',
            'ticket_default_priority' => 'aosai_ticket_default_priority',
            'ticket_sla_low_hours'    => 'aosai_ticket_sla_low_hours',
            'ticket_sla_medium_hours' => 'aosai_ticket_sla_medium_hours',
            'ticket_sla_high_hours'   => 'aosai_ticket_sla_high_hours',
            'ticket_sla_urgent_hours' => 'aosai_ticket_sla_urgent_hours',
            'ticket_macro_library'    => 'aosai_ticket_macro_library',
            'workload_capacity_per_member' => 'aosai_workload_capacity_per_member',
            'portal_dashboard_layout' => 'aosai_portal_dashboard_layout',
            'login_tracking_enabled'  => 'aosai_login_tracking_enabled',
        );

        $this->defaults = array(
            'aosai_openai_api_key'          => '',
            'aosai_openai_model'            => 'gpt-4o-mini',
            'aosai_email_notifications'     => 'yes',
            'aosai_timezone'                => get_option( 'timezone_string', 'UTC' ),
            'aosai_date_format'             => get_option( 'date_format', 'F j, Y' ),
            'aosai_primary_color'           => '#0f766e',
            'aosai_company_name'            => get_bloginfo( 'name' ),
            'aosai_company_email'           => get_option( 'admin_email' ),
            'aosai_company_phone'           => '',
            'aosai_company_website'         => home_url( '/' ),
            'aosai_policy_privacy_url'      => function_exists( 'get_privacy_policy_url' ) ? get_privacy_policy_url() : '',
            'aosai_policy_terms_url'        => '',
            'aosai_company_address'         => '',
            'aosai_company_logo_url'        => '',
            'aosai_support_email'           => get_option( 'admin_email' ),
            'aosai_email_from_name'         => get_bloginfo( 'name' ),
            'aosai_email_from_email'        => get_option( 'admin_email' ),
            'aosai_email_footer_text'       => 'You are receiving this update because you are a member of the workspace.',
            'aosai_smtp_enabled'            => 'no',
            'aosai_smtp_host'               => '',
            'aosai_smtp_port'               => 587,
            'aosai_smtp_username'           => '',
            'aosai_smtp_password'           => '',
            'aosai_smtp_encryption'         => 'tls',
            'aosai_smtp_auth'               => 'yes',
            'aosai_inbound_ai_routing'      => 'yes',
            'aosai_inbound_email_token'     => (string) get_option( 'aosai_inbound_email_token', '' ),
            'aosai_portal_name'             => get_bloginfo( 'name' ) . ' Workspace',
            'aosai_portal_welcome_title'    => 'Client and team workspace',
            'aosai_portal_welcome_text'     => 'Track projects, collaborate with your team, and manage support requests in one branded portal.',
            'aosai_portal_secondary_color'  => '#f59e0b',
            'aosai_portal_page_id'          => 0,
            'aosai_portal_login_page_id'    => 0,
            'aosai_portal_ticket_page_id'   => 0,
            'aosai_portal_hide_admin_bar'   => 'yes',
            'aosai_portal_force_frontend'   => 'yes',
            'aosai_portal_enable_pwa'       => 'yes',
            'aosai_show_footer_credit'      => 'no',
            'aosai_footer_credit_text'      => '',
            'aosai_ticket_ai_routing'       => 'yes',
            'aosai_ticket_default_priority' => 'medium',
            'aosai_ticket_sla_low_hours'    => 72,
            'aosai_ticket_sla_medium_hours' => 24,
            'aosai_ticket_sla_high_hours'   => 8,
            'aosai_ticket_sla_urgent_hours' => 2,
            'aosai_ticket_macro_library'    => $this->get_default_macro_library(),
            'aosai_workload_capacity_per_member' => 8,
            'aosai_portal_dashboard_layout' => 'split',
            'aosai_login_tracking_enabled'  => 'yes',
        );
    }

    public function get_all(): array {
        $settings = array();

        foreach ( $this->key_map as $frontend_key => $internal_key ) {
            $value = get_option( $internal_key, $this->defaults[ $internal_key ] ?? null );

            if ( 'inbound_email_token' === $frontend_key ) {
                $settings[ $frontend_key ] = $this->get_inbound_email_token();
                continue;
            }

            if ( 'openai_api_key' === $frontend_key && ! empty( $value ) ) {
                $settings[ $frontend_key ] = self::MASKED_API_KEY;
                continue;
            }

            if ( 'default_model' === $frontend_key ) {
                $model = sanitize_text_field( (string) $value );
                $settings[ $frontend_key ] = '' !== trim( $model ) ? $model : (string) $this->defaults['aosai_openai_model'];
                continue;
            }

            if ( 'smtp_password' === $frontend_key && ! empty( $value ) ) {
                $settings[ $frontend_key ] = self::MASKED_SECRET;
                continue;
            }

            if ( $this->is_boolean_key( $frontend_key ) ) {
                $settings[ $frontend_key ] = 'yes' === $value;
                continue;
            }

            if ( $this->is_integer_key( $frontend_key ) ) {
                $settings[ $frontend_key ] = absint( $value );
                continue;
            }

            if ( 'ticket_macro_library' === $frontend_key ) {
                $settings[ $frontend_key ] = $this->normalize_macro_library( $value );
                continue;
            }

            $settings[ $frontend_key ] = $value;
        }

        $settings['portal_page_url']        = aosai_get_portal_page_url();
        $settings['portal_login_page_url']  = aosai_get_login_page_url();
        $settings['portal_ticket_page_url'] = aosai_get_ticket_page_url();
        $settings['inbound_email_endpoint'] = esc_url_raw( rest_url( 'aosai/v1/inbound/email' ) );
        $settings['inbound_email_pipe_endpoint'] = esc_url_raw( rest_url( 'aosai/v1/inbound/email-pipe' ) );
        $settings['shortcodes']             = $this->get_shortcodes();

        return $settings;
    }

    public function get( string $frontend_key ) {
        if ( ! isset( $this->key_map[ $frontend_key ] ) ) {
            return null;
        }

        $value = get_option( $this->key_map[ $frontend_key ], $this->defaults[ $this->key_map[ $frontend_key ] ] ?? null );
        if ( 'inbound_email_token' === $frontend_key ) {
            return $this->get_inbound_email_token();
        }

        if ( 'openai_api_key' === $frontend_key && ! empty( $value ) ) {
            return self::MASKED_API_KEY;
        }

        if ( 'default_model' === $frontend_key ) {
            $model = sanitize_text_field( (string) $value );
            return '' !== trim( $model ) ? $model : (string) $this->defaults['aosai_openai_model'];
        }

        if ( 'smtp_password' === $frontend_key && ! empty( $value ) ) {
            return self::MASKED_SECRET;
        }

        if ( $this->is_boolean_key( $frontend_key ) ) {
            return 'yes' === $value;
        }

        if ( $this->is_integer_key( $frontend_key ) ) {
            return absint( $value );
        }

        if ( 'ticket_macro_library' === $frontend_key ) {
            return $this->normalize_macro_library( $value );
        }

        return $value;
    }

    public function update( array $data ): bool|\WP_Error {
        foreach ( $data as $frontend_key => $value ) {
            if ( ! isset( $this->key_map[ $frontend_key ] ) ) {
                continue;
            }

            $internal_key = $this->key_map[ $frontend_key ];
            $sanitized    = $this->sanitize_value( $frontend_key, $value );

            if ( null === $sanitized ) {
                continue;
            }

            update_option( $internal_key, $sanitized );
        }

        return true;
    }

    public function get_portal_branding(): array {
        return array(
            'company_name'         => (string) get_option( 'aosai_company_name', $this->defaults['aosai_company_name'] ),
            'company_website'      => (string) get_option( 'aosai_company_website', home_url( '/' ) ),
            'company_logo_url'     => (string) get_option( 'aosai_company_logo_url', '' ),
            'privacy_policy_url'   => (string) get_option( 'aosai_policy_privacy_url', $this->defaults['aosai_policy_privacy_url'] ),
            'terms_url'            => (string) get_option( 'aosai_policy_terms_url', '' ),
            'support_email'        => (string) get_option( 'aosai_support_email', get_option( 'admin_email' ) ),
            'primary_color'        => (string) get_option( 'aosai_primary_color', '#0f766e' ),
            'secondary_color'      => (string) get_option( 'aosai_portal_secondary_color', '#f59e0b' ),
            'portal_name'          => (string) get_option( 'aosai_portal_name', $this->defaults['aosai_portal_name'] ),
            'welcome_title'        => (string) get_option( 'aosai_portal_welcome_title', $this->defaults['aosai_portal_welcome_title'] ),
            'welcome_text'         => (string) get_option( 'aosai_portal_welcome_text', $this->defaults['aosai_portal_welcome_text'] ),
            'footer_credit_text'   => (string) get_option( 'aosai_footer_credit_text', '' ),
            'show_footer_credit'   => 'yes' === get_option( 'aosai_show_footer_credit', 'no' ),
            'enable_pwa'           => 'yes' === get_option( 'aosai_portal_enable_pwa', 'yes' ),
            'portal_page_url'      => aosai_get_portal_page_url(),
            'portal_login_page_url'=> aosai_get_login_page_url(),
            'portal_ticket_page_url'=> aosai_get_ticket_page_url(),
        );
    }

    public function get_shortcodes(): array {
        return array(
            array(
                'label'       => 'Branded Login',
                'shortcode'   => '[agency_os_ai_login]',
                'description' => 'Use on your dedicated workspace login page.',
            ),
            array(
                'label'       => 'Portal Dashboard',
                'shortcode'   => '[agency_os_ai_portal]',
                'description' => 'Client and employee dashboard with projects, tasks, files, tickets, and profile.',
            ),
            array(
                'label'       => 'Ticket View',
                'shortcode'   => '[agency_os_ai_portal view="tickets"]',
                'description' => 'Starts the portal directly on the support tickets screen.',
            ),
        );
    }

    public function get_ticket_sla_rules(): array {
        return array(
            'low'    => max( 1, (int) $this->get( 'ticket_sla_low_hours' ) ),
            'medium' => max( 1, (int) $this->get( 'ticket_sla_medium_hours' ) ),
            'high'   => max( 1, (int) $this->get( 'ticket_sla_high_hours' ) ),
            'urgent' => max( 1, (int) $this->get( 'ticket_sla_urgent_hours' ) ),
        );
    }

    public function get_workload_capacity_per_member(): int {
        return max( 1, (int) $this->get( 'workload_capacity_per_member' ) );
    }

    public function get_ticket_macro_library(): array {
        $macros = $this->get( 'ticket_macro_library' );
        return $this->normalize_macro_library( $macros );
    }

    public function test_ai_connection( string $provider, string $api_key, string $model ): array|\WP_Error {
        $provider = $provider ?: 'openai';
        $stored_key = (string) get_option( 'aosai_openai_api_key', '' );
        $stored_model = (string) get_option( 'aosai_openai_model', $this->defaults['aosai_openai_model'] );
        $api_key = trim( $api_key );
        $model   = trim( $model );

        if ( self::MASKED_API_KEY === $api_key ) {
            $api_key = $stored_key;
        }

        if ( empty( $api_key ) ) {
            return new \WP_Error( 'missing_key', __( 'API key is required.', 'agency-os-ai' ) );
        }

        if ( '' === $model ) {
            $model = '' !== trim( $stored_model ) ? $stored_model : (string) $this->defaults['aosai_openai_model'];
        }

        $ai_service = AOSAI_AI_Service::get_instance();
        update_option( 'aosai_openai_api_key', $api_key );
        update_option( 'aosai_openai_model', $model );

        $result = $ai_service->chat(
            array(
                array(
                    'role'    => 'user',
                    'content' => 'Reply with exactly: Connection successful.',
                ),
            ),
            array(
                'provider' => $provider,
                'model'    => $model,
            )
        );

        update_option( 'aosai_openai_api_key', $stored_key );
        update_option( 'aosai_openai_model', $stored_model );

        if ( is_wp_error( $result ) ) {
            return $result;
        }

        return array(
            'success' => true,
            'message' => __( 'AI connection successful.', 'agency-os-ai' ),
            'reply'   => $result['content'] ?? '',
        );
    }

    private function sanitize_value( string $key, $value ) {
        if ( 'openai_api_key' === $key ) {
            $value = sanitize_text_field( (string) $value );
            if ( '' === $value || self::MASKED_API_KEY === $value ) {
                return null;
            }
            return $value;
        }

        if ( 'smtp_password' === $key ) {
            $value = sanitize_text_field( (string) $value );
            if ( '' === $value || self::MASKED_SECRET === $value ) {
                return null;
            }
            return $value;
        }

        if ( $this->is_boolean_key( $key ) ) {
            return rest_sanitize_boolean( $value ) ? 'yes' : 'no';
        }

        if ( $this->is_integer_key( $key ) ) {
            return absint( $value );
        }

        switch ( $key ) {
            case 'default_model':
                $model = sanitize_text_field( (string) $value );
                return '' !== trim( $model ) ? $model : (string) $this->defaults['aosai_openai_model'];
            case 'timezone':
                return in_array( $value, timezone_identifiers_list(), true ) ? $value : $this->defaults['aosai_timezone'];
            case 'date_format':
                return sanitize_text_field( (string) $value );
            case 'primary_color':
            case 'portal_secondary_color':
                return sanitize_hex_color( (string) $value ) ?: $this->defaults[ $this->key_map[ $key ] ];
            case 'company_email':
            case 'support_email':
            case 'email_from_email':
                return sanitize_email( (string) $value );
            case 'smtp_host':
            case 'smtp_username':
                return sanitize_text_field( (string) $value );
            case 'company_website':
            case 'privacy_policy_url':
            case 'terms_url':
            case 'company_logo_url':
                return esc_url_raw( (string) $value );
            case 'smtp_encryption':
                $allowed = array( 'none', 'tls', 'ssl' );
                $value   = sanitize_key( (string) $value );
                return in_array( $value, $allowed, true ) ? $value : 'tls';
            case 'inbound_email_token':
                return sanitize_text_field( (string) $value );
            case 'ticket_default_priority':
                $allowed = array( 'low', 'medium', 'high', 'urgent' );
                $value   = sanitize_key( (string) $value );
                return in_array( $value, $allowed, true ) ? $value : 'medium';
            case 'ticket_macro_library':
                return $this->sanitize_macro_library( $value );
            case 'portal_dashboard_layout':
                $allowed = array( 'split', 'compact', 'stacked' );
                $value   = sanitize_key( (string) $value );
                return in_array( $value, $allowed, true ) ? $value : 'split';
            case 'company_address':
            case 'portal_welcome_text':
            case 'email_footer_text':
                return sanitize_textarea_field( (string) $value );
            default:
                return sanitize_text_field( (string) $value );
        }
    }

    private function is_boolean_key( string $key ): bool {
        return in_array(
            $key,
            array( 'email_notifications', 'smtp_enabled', 'smtp_auth', 'hide_admin_bar', 'force_frontend_dashboard', 'enable_pwa', 'show_footer_credit', 'ticket_ai_routing', 'inbound_ai_routing', 'login_tracking_enabled' ),
            true
        );
    }

    private function is_integer_key( string $key ): bool {
        return in_array(
            $key,
            array(
                'smtp_port',
                'portal_page_id',
                'portal_login_page_id',
                'portal_ticket_page_id',
                'ticket_sla_low_hours',
                'ticket_sla_medium_hours',
                'ticket_sla_high_hours',
                'ticket_sla_urgent_hours',
                'workload_capacity_per_member',
            ),
            true
        );
    }

    private function get_inbound_email_token(): string {
        $token = (string) get_option( 'aosai_inbound_email_token', '' );
        if ( '' !== $token ) {
            return $token;
        }

        $token = wp_generate_password( 32, false );
        update_option( 'aosai_inbound_email_token', $token );

        return $token;
    }

    private function sanitize_macro_library( $value ): array {
        $library = is_array( $value ) ? $value : array();
        $sanitized = array();

        foreach ( $library as $index => $macro ) {
            if ( ! is_array( $macro ) ) {
                continue;
            }

            $normalized = $this->normalize_macro_definition( $macro, (int) $index );
            if ( $normalized ) {
                $sanitized[] = $normalized;
            }
        }

        return ! empty( $sanitized ) ? $sanitized : $this->get_default_macro_library();
    }

    private function normalize_macro_library( $value ): array {
        $library = is_array( $value ) ? $value : maybe_unserialize( $value );
        if ( ! is_array( $library ) ) {
            return $this->get_default_macro_library();
        }

        $normalized = array();
        foreach ( $library as $index => $macro ) {
            if ( ! is_array( $macro ) ) {
                continue;
            }

            $item = $this->normalize_macro_definition( $macro, (int) $index );
            if ( $item ) {
                $normalized[] = $item;
            }
        }

        return ! empty( $normalized ) ? $normalized : $this->get_default_macro_library();
    }

    private function normalize_macro_definition( array $macro, int $index ): ?array {
        $name = sanitize_text_field( (string) ( $macro['name'] ?? '' ) );
        if ( '' === $name ) {
            return null;
        }

        $status = sanitize_key( (string) ( $macro['status'] ?? 'open' ) );
        if ( ! in_array( $status, array( 'open', 'in_progress', 'waiting', 'resolved', 'closed' ), true ) ) {
            $status = 'open';
        }

        $priority = sanitize_key( (string) ( $macro['priority'] ?? 'medium' ) );
        if ( ! in_array( $priority, array( 'low', 'medium', 'high', 'urgent' ), true ) ) {
            $priority = 'medium';
        }

        $tags = $macro['tags'] ?? array();
        if ( is_string( $tags ) ) {
            $tags = array_filter( array_map( 'trim', explode( ',', $tags ) ) );
        }

        if ( ! is_array( $tags ) ) {
            $tags = array();
        }

        $tag_values = array();
        foreach ( $tags as $tag ) {
            $tag = sanitize_text_field( (string) $tag );
            if ( '' !== $tag ) {
                $tag_values[] = $tag;
            }
        }

        $id = sanitize_title( (string) ( $macro['id'] ?? '' ) );
        if ( '' === $id ) {
            $id = sanitize_title( $name );
        }
        if ( '' === $id ) {
            $id = 'macro-' . max( 1, $index + 1 );
        }

        return array(
            'id'            => $id,
            'name'          => $name,
            'description'   => sanitize_text_field( (string) ( $macro['description'] ?? '' ) ),
            'status'        => $status,
            'priority'      => $priority,
            'department_id' => absint( $macro['department_id'] ?? 0 ),
            'tags'          => array_values( array_unique( $tag_values ) ),
            'note_template' => sanitize_textarea_field( (string) ( $macro['note_template'] ?? '' ) ),
        );
    }

    private function get_default_macro_library(): array {
        return array(
            array(
                'id'            => 'urgent-client-escalation',
                'name'          => __( 'Urgent Client Escalation', 'agency-os-ai' ),
                'description'   => __( 'Escalate urgent requests, raise priority, and alert the delivery owner.', 'agency-os-ai' ),
                'status'        => 'in_progress',
                'priority'      => 'urgent',
                'department_id' => 0,
                'tags'          => array( 'escalated', 'client', 'urgent' ),
                'note_template' => __( 'Urgent escalation applied. The team is reviewing the issue now and an updated ETA will be shared shortly.', 'agency-os-ai' ),
            ),
            array(
                'id'            => 'waiting-on-client',
                'name'          => __( 'Waiting On Client', 'agency-os-ai' ),
                'description'   => __( 'Pause the workflow cleanly while awaiting assets, approval, or additional details.', 'agency-os-ai' ),
                'status'        => 'waiting',
                'priority'      => 'medium',
                'department_id' => 0,
                'tags'          => array( 'waiting', 'client-response' ),
                'note_template' => __( 'We are currently waiting on client confirmation or assets before the next delivery step can continue.', 'agency-os-ai' ),
            ),
            array(
                'id'            => 'resolved-and-monitoring',
                'name'          => __( 'Resolved And Monitoring', 'agency-os-ai' ),
                'description'   => __( 'Mark the ticket resolved while keeping a short observation window documented.', 'agency-os-ai' ),
                'status'        => 'resolved',
                'priority'      => 'low',
                'department_id' => 0,
                'tags'          => array( 'resolved', 'monitoring' ),
                'note_template' => __( 'The issue has been resolved and moved into monitoring. Reply here if anything still needs follow-up.', 'agency-os-ai' ),
            ),
        );
    }
}

