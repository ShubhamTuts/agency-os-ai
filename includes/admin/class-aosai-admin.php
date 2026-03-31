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
        // No WP React deps — our Vite bundle is self-contained with React 18 bundled
        // inline. Loading wp-element / react / react-dom alongside the bundle creates
        // two React instances on the same page, causing reconciler DOM conflicts.
        $deps = array();

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

        add_action( 'admin_head', array( $this, 'output_admin_modulepreload' ) );
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

    public function add_module_type( $tag, $handle, $src ) {
        if ( 'aosai-admin-app' !== $handle ) {
            return $tag;
        }
        return str_replace( ' src', ' type="module" src', $tag );
    }

    public function remove_module_filter() {
        remove_filter( 'script_loader_tag', array( $this, 'add_module_type' ) );
    }

    public function output_admin_modulepreload(): void {
        $manifest = aosai_get_asset_manifest();
        if ( empty( $manifest ) ) {
            return;
        }

        foreach ( $manifest as $entry ) {
            if ( empty( $entry['file'] ) ) {
                continue;
            }

            $file = ltrim( (string) $entry['file'], '/' );
            if ( substr( $file, -3 ) !== '.js' ) {
                continue;
            }

            $url = AOSAI_PLUGIN_URL . 'build/' . $file;
            echo '<link rel="modulepreload" href="' . esc_url( $url ) . '">' . "\n";
        }
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

