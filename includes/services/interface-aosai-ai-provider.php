<?php
if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

interface AOSAI_AI_Provider_Interface {
    public function get_id(): string;
    public function get_name(): string;
    public function is_configured(): bool;
    public function is_pro(): bool;
    public function get_models(): array;
    public function get_default_model(): string;
    public function generate_tasks( array $params ): array|\WP_Error;
    public function suggest_description( string $title, string $context = '' ): string|\WP_Error;
    public function chat( array $messages, array $params = array() ): array|\WP_Error;
}
