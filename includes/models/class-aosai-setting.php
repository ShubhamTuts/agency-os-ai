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
            'portal_dashboard_layout' => 'aosai_portal_dashboard_layout',
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
            'aosai_portal_dashboard_layout' => 'split',
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

        if ( 'smtp_password' === $frontend_key && ! empty( $value ) ) {
            return self::MASKED_SECRET;
        }

        if ( $this->is_boolean_key( $frontend_key ) ) {
            return 'yes' === $value;
        }

        if ( $this->is_integer_key( $frontend_key ) ) {
            return absint( $value );
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

    public function test_ai_connection( string $provider, string $api_key, string $model ): array|\WP_Error {
        $provider = $provider ?: 'openai';
        $stored_key = (string) get_option( 'aosai_openai_api_key', '' );
        $stored_model = (string) get_option( 'aosai_openai_model', $this->defaults['aosai_openai_model'] );
        $api_key = trim( $api_key );

        if ( self::MASKED_API_KEY === $api_key ) {
            $api_key = $stored_key;
        }

        if ( empty( $api_key ) ) {
            return new \WP_Error( 'missing_key', __( 'API key is required.', 'agency-os-ai' ) );
        }

        if ( empty( $model ) ) {
            return new \WP_Error( 'missing_model', __( 'Model is required.', 'agency-os-ai' ) );
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
                return sanitize_text_field( (string) $value );
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
            array( 'email_notifications', 'smtp_enabled', 'smtp_auth', 'hide_admin_bar', 'force_frontend_dashboard', 'enable_pwa', 'show_footer_credit', 'ticket_ai_routing', 'inbound_ai_routing' ),
            true
        );
    }

    private function is_integer_key( string $key ): bool {
        return in_array( $key, array( 'smtp_port', 'portal_page_id', 'portal_login_page_id', 'portal_ticket_page_id' ), true );
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
}

