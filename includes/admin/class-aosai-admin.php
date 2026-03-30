<?php
if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

class AOSAI_Admin {

    public function register_menu() {
        add_menu_page(
            esc_html__( 'Agency OS AI', 'agency-os-ai' ),
            esc_html__( 'Agency OS AI', 'agency-os-ai' ),
            'edit_posts',
            'agency-os-ai',
            array( $this, 'render_admin_page' ),
            'dashicons-chart-bar',
            30
        );
    }

    public function enqueue_assets( $hook ) {
        if ( 'toplevel_page_agency-os-ai' !== $hook ) {
            return;
        }

        $entry = aosai_get_asset_entry( 'src/admin/index.tsx' );
        $version = AOSAI_VERSION;
        $deps = array_merge( array( 'wp-element', 'wp-api-fetch', 'wp-i18n' ), $this->get_react_deps() );

        if ( $entry ) {
            wp_enqueue_script(
                'aosai-admin-app',
                AOSAI_PLUGIN_URL . 'build/' . ltrim( $entry['file'], '/' ),
                $deps,
                $version,
                array( 'in_footer' => true )
            );

            foreach ( aosai_get_asset_styles( 'src/admin/index.tsx' ) as $css_file ) {
                wp_enqueue_style(
                    'aosai-admin-style-' . md5( $css_file ),
                    AOSAI_PLUGIN_URL . 'build/' . ltrim( $css_file, '/' ),
                    array(),
                    $version
                );
            }
        } else {
            wp_enqueue_script(
                'aosai-admin-app',
                AOSAI_PLUGIN_URL . 'build/admin/index.js',
                $deps,
                $version,
                array( 'in_footer' => true )
            );

            wp_enqueue_style(
                'aosai-admin-app',
                AOSAI_PLUGIN_URL . 'build/admin/index.css',
                array(),
                $version
            );
        }

        add_filter( 'script_loader_tag', array( $this, 'add_module_type' ), 10, 3 );
        add_action( 'print_footer_scripts', array( $this, 'remove_module_filter' ) );

        $current_user = wp_get_current_user();
        $data = array(
            'apiBase'   => esc_url_raw( rest_url() ),
            'nonce'     => wp_create_nonce( 'wp_rest' ),
            'userId'    => get_current_user_id(),
            'pluginUrl' => esc_url( AOSAI_PLUGIN_URL ),
            'version'   => AOSAI_VERSION,
            'isPro'     => aosai_is_pro_active(),
            'brandingAssets' => aosai_get_branding_assets(),
            'logoutUrl' => wp_logout_url( admin_url() ),
            'profileUrl'=> admin_url( 'profile.php' ),
            'currentUser' => array(
                'name'       => $current_user->display_name,
                'email'      => $current_user->user_email,
                'avatar_url' => get_avatar_url( $current_user->ID ),
            ),
            'i18n'      => aosai_get_js_translations(),
        );

        $data = apply_filters( 'aosai_admin_js_data', $data );
        wp_localize_script( 'aosai-admin-app', 'aosaiData', $data );

        do_action( 'aosai_enqueue_pro_assets', $hook );
    }

    private function get_react_deps() {
        global $wp_scripts;
        if ( ! isset( $wp_scripts ) ) {
            return array();
        }
        $deps = array();
        foreach ( array( 'react', 'react-dom' ) as $handle ) {
            if ( $wp_scripts->query( $handle, 'registered' ) ) {
                $deps[] = $handle;
            }
        }
        return $deps;
    }

    public function add_module_type( $tag, $handle, $src ) {
        if ( 'aosai-admin-app' !== $handle ) {
            return $tag;
        }
        return str_replace( ' src', ' type="module" src', $tag );
    }

    public function remove_module_filter() {
        remove_filter( 'script_loader_tag', array( $this, 'add_module_type' ) );
    }

    public function render_admin_page() {
        require_once AOSAI_PLUGIN_DIR . 'templates/admin-page.php';
    }

    public function add_action_links( $links ) {
        $settings_link = '<a href="' . esc_url( admin_url( 'admin.php?page=agency-os-ai&tab=settings' ) ) . '">' . esc_html__( 'Settings', 'agency-os-ai' ) . '</a>';
        array_unshift( $links, $settings_link );
        return $links;
    }
}

