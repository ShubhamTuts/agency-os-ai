<?php
if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

class AOSAI_Admin_Notices {
    
    public function add_notice( string $message, string $type = 'info', bool $dismissible = true ) {
        add_action( 'admin_notices', function() use ( $message, $type, $dismissible ) {
            $class = "notice notice-{$type}";
            if ( $dismissible ) {
                $class .= ' is-dismissible';
            }
            printf( '<div class="%1$s"><p>%2$s</p></div>', esc_attr( $class ), esc_html( $message ) );
        });
    }
    
    public function success( string $message ) {
        $this->add_notice( $message, 'success' );
    }
    
    public function error( string $message ) {
        $this->add_notice( $message, 'error' );
    }
    
    public function warning( string $message ) {
        $this->add_notice( $message, 'warning' );
    }
    
    public function info( string $message ) {
        $this->add_notice( $message, 'info' );
    }
}
