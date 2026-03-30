<?php
if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

trait AOSAI_Singleton {
    private static ?self $instance = null;
    
    public static function get_instance(): self {
        if ( null === self::$instance ) {
            self::$instance = new self();
        }
        return self::$instance;
    }
    
    private function __clone() {}
    
    public function __wakeup() {
        throw new \Exception( esc_html__( 'Cannot unserialize singleton.', 'agency-os-ai' ) );
    }
}
