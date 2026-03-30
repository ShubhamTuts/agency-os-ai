<?php
if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

class AOSAI_AI_Service {
    use AOSAI_Singleton;
    
    private array $providers = array();
    
    protected function __construct() {
        $this->register_provider( 'openai', new AOSAI_AI_OpenAI() );
        do_action( 'aosai_register_ai_providers', $this );
    }
    
    public function register_provider( string $id, AOSAI_AI_Provider_Interface $provider ): void {
        $this->providers[ $id ] = $provider;
    }
    
    public function get_provider( string $id = '' ): ?AOSAI_AI_Provider_Interface {
        if ( empty( $id ) ) {
            $id = get_option( 'aosai_ai_provider', 'openai' );
        }
        return $this->providers[ $id ] ?? null;
    }
    
    public function get_available_providers(): array {
        $available = array();
        foreach ( $this->providers as $id => $provider ) {
            $available[] = array(
                'id'            => $id,
                'name'          => $provider->get_name(),
                'is_configured' => $provider->is_configured(),
                'is_pro'        => $provider->is_pro(),
                'models'        => $provider->get_models(),
            );
        }
        return $available;
    }
    
    public function generate_tasks( array $params ): array|\WP_Error {
        $provider = $this->get_provider( $params['provider'] ?? '' );
        if ( ! $provider ) {
            return new \WP_Error( 'invalid_provider', esc_html__( 'AI provider not found.', 'agency-os-ai' ) );
        }
        if ( ! $provider->is_configured() ) {
            return new \WP_Error( 'provider_not_configured', esc_html__( 'AI provider is not configured. Please add your API key in Settings.', 'agency-os-ai' ) );
        }
        
        $user_id = get_current_user_id();
        if ( $this->is_rate_limited( $user_id ) ) {
            return new \WP_Error( 'rate_limited', esc_html__( 'You have exceeded the AI request limit. Please try again later.', 'agency-os-ai' ) );
        }
        
        $result = $provider->generate_tasks( $params );
        $this->log_usage( $user_id, $provider, $params, $result );
        
        return $result;
    }
    
    public function suggest_description( string $title, string $context = '', string $provider = '' ): string|\WP_Error {
        $p = $this->get_provider( $provider );
        if ( ! $p || ! $p->is_configured() ) {
            return new \WP_Error( 'provider_unavailable', esc_html__( 'AI provider unavailable.', 'agency-os-ai' ) );
        }
        return $p->suggest_description( $title, $context );
    }
    
    public function chat( array $messages, array $params = array() ): array|\WP_Error {
        $provider = $this->get_provider( $params['provider'] ?? '' );
        if ( ! $provider || ! $provider->is_configured() ) {
            return new \WP_Error( 'provider_unavailable', esc_html__( 'AI provider unavailable.', 'agency-os-ai' ) );
        }
        return $provider->chat( $messages, $params );
    }
    
    private function is_rate_limited( int $user_id ): bool {
        global $wpdb;
        $table = $wpdb->prefix . 'aosai_ai_logs';
        $count = (int) $wpdb->get_var(
            $wpdb->prepare(
                "SELECT COUNT(*) FROM {$table} WHERE user_id = %d AND created_at > %s",
                $user_id,
                gmdate( 'Y-m-d H:i:s', strtotime( '-1 hour' ) )
            )
        );
        $limit = absint( apply_filters( 'aosai_ai_rate_limit_per_hour', 30 ) );
        return $count >= $limit;
    }
    
    private function log_usage( int $user_id, AOSAI_AI_Provider_Interface $provider, array $params, $result ): void {
        global $wpdb;
        $table = $wpdb->prefix . 'aosai_ai_logs';
        
        $usage = is_wp_error( $result ) ? array() : ( $result['usage'] ?? array() );
        
        $wpdb->insert(
            $table,
            array(
                'user_id'           => $user_id,
                'project_id'        => absint( $params['project_id'] ?? 0 ) ?: null,
                'provider'          => $provider->get_id(),
                'model'            => sanitize_text_field( $params['model'] ?? $provider->get_default_model() ),
                'action'           => sanitize_key( $params['action'] ?? 'generate_tasks' ),
                'prompt_tokens'    => absint( $usage['prompt_tokens'] ?? 0 ),
                'completion_tokens'=> absint( $usage['completion_tokens'] ?? 0 ),
                'total_tokens'     => absint( $usage['total_tokens'] ?? 0 ),
                'status'           => is_wp_error( $result ) ? 'error' : 'success',
                'error_message'    => is_wp_error( $result ) ? $result->get_error_message() : null,
            ),
            array( '%d', '%d', '%s', '%s', '%s', '%d', '%d', '%d', '%s', '%s' )
        );
    }
}
