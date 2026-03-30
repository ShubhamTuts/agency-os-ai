<?php
if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

class AOSAI_i18n {
    use AOSAI_Singleton;
    
    public function load_plugin_textdomain() {
        load_plugin_textdomain(
            'agency-os-ai',
            false,
            dirname( AOSAI_PLUGIN_BASENAME ) . '/languages/'
        );
    }
}
