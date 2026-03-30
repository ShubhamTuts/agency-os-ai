<?php
if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

class AOSAI_Deactivator {
    
    public static function deactivate() {
        flush_rewrite_rules();
    }
}
