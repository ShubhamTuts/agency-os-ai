<?php
if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

class AOSAI_Frontend {
    use AOSAI_Singleton;

    private bool $assets_enqueued = false;

    private function __construct() {}

    public function register_shortcodes() {
        add_shortcode( 'agency_os_ai_portal', array( $this, 'render_portal_shortcode' ) );
        add_shortcode( 'agency_os_ai_login', array( $this, 'render_login_shortcode' ) );
    }

    public function maybe_serve_virtual_assets() {
        if ( isset( $_GET['aosai_portal_manifest'] ) ) {
            $branding = AOSAI_Setting::get_instance()->get_portal_branding();
            wp_send_json(
                array(
                    'name'             => $branding['portal_name'],
                    'short_name'       => $branding['company_name'],
                    'start_url'        => aosai_get_portal_page_url(),
                    'scope'            => home_url( '/' ),
                    'display'          => 'standalone',
                    'background_color' => '#f8fafc',
                    'theme_color'      => $branding['primary_color'],
                    'icons'            => array(),
                )
            );
        }

        if ( isset( $_GET['aosai_portal_sw'] ) ) {
            nocache_headers();
            header( 'Content-Type: application/javascript; charset=utf-8' );
            $urls = array(
                home_url( '/' ),
                aosai_get_portal_page_url(),
                aosai_get_login_page_url(),
                aosai_get_ticket_page_url(),
            );
            echo "const CACHE_NAME = 'aosai-portal-v" . esc_js( AOSAI_VERSION ) . "';\n";
            echo 'const URLS = ' . wp_json_encode( array_values( array_unique( array_filter( $urls ) ) ) ) . ";\n";
            echo "self.addEventListener('install', (event) => { event.waitUntil(caches.open(CACHE_NAME).then((cache) => cache.addAll(URLS)).catch(() => Promise.resolve())); self.skipWaiting(); });\n";
            echo "self.addEventListener('activate', (event) => { event.waitUntil(self.clients.claim()); });\n";
            echo "self.addEventListener('fetch', (event) => { if (event.request.method !== 'GET') { return; } event.respondWith(fetch(event.request).catch(() => caches.match(event.request).then((response) => response || caches.match(URLS[0])))); });\n";
            exit;
        }
    }

    public function maybe_hide_admin_bar( $show ) {
        if ( ! is_user_logged_in() ) {
            return $show;
        }

        if ( 'yes' !== get_option( 'aosai_portal_hide_admin_bar', 'yes' ) ) {
            return $show;
        }

        $portal_type = aosai_get_user_portal_type( get_current_user_id() );
        if ( in_array( $portal_type, array( 'client', 'employee' ), true ) ) {
            return false;
        }

        // Also hide admin bar for all users (including admins) on portal pages.
        if ( $this->is_portal_page_request() ) {
            return false;
        }

        return $show;
    }

    public function maybe_redirect_portal_users() {
        if ( ! is_admin() || ! is_user_logged_in() || wp_doing_ajax() ) {
            return;
        }

        if ( 'yes' !== get_option( 'aosai_portal_force_frontend', 'yes' ) ) {
            return;
        }

        $screen = function_exists( 'get_current_screen' ) ? get_current_screen() : null;
        if ( $screen && in_array( $screen->base, array( 'profile', 'user-edit' ), true ) ) {
            return;
        }

        if ( ! in_array( aosai_get_user_portal_type( get_current_user_id() ), array( 'client', 'employee' ), true ) ) {
            return;
        }

        if ( defined( 'DOING_CRON' ) && DOING_CRON ) {
            return;
        }

        wp_safe_redirect( aosai_get_portal_page_url() );
        exit;
    }

    public function print_pwa_meta() {
        if ( ! $this->is_portal_page_request() ) {
            return;
        }

        if ( 'yes' !== get_option( 'aosai_portal_enable_pwa', 'yes' ) ) {
            return;
        }

        $branding = AOSAI_Setting::get_instance()->get_portal_branding();
        echo '<link rel="manifest" href="' . esc_url( home_url( '/?aosai_portal_manifest=1' ) ) . '">' . "\n";
        echo '<meta name="theme-color" content="' . esc_attr( $branding['primary_color'] ) . '">' . "\n";
        echo '<meta name="apple-mobile-web-app-capable" content="yes">' . "\n";
        echo '<meta name="apple-mobile-web-app-title" content="' . esc_attr( $branding['portal_name'] ) . '">' . "\n";
    }

    public function render_portal_shortcode( array $atts = array() ): string {
        $atts = shortcode_atts(
            array(
                'view' => 'dashboard',
            ),
            $atts,
            'agency_os_ai_portal'
        );

        if ( ! is_user_logged_in() ) {
            return $this->render_login_shell( get_permalink() ?: aosai_get_portal_page_url() );
        }

        if ( ! aosai_user_has_portal_access( get_current_user_id() ) ) {
            return '<div class="aosai-portal-denied">' . esc_html__( 'You do not have access to this workspace.', 'agency-os-ai' ) . '</div>';
        }

        $this->enqueue_portal_assets( sanitize_key( (string) $atts['view'] ) ?: 'dashboard' );

        return aosai_render_brand_preloader( 'aosai-portal-preloader', __( 'Opening your workspace...', 'agency-os-ai' ) )
            . '<div id="aosai-portal-root" class="aosai-app-container"></div>';
    }

    public function render_login_shortcode( array $atts = array() ): string {
        $redirect_to = get_permalink() ?: aosai_get_portal_page_url();
        return $this->render_login_shell( $redirect_to );
    }

    private function enqueue_portal_assets( string $initial_view ) {
        if ( $this->assets_enqueued ) {
            return;
        }

        $entry = aosai_get_asset_entry( 'src/portal/index.tsx' );
        if ( ! $entry ) {
            return;
        }

        $handle = 'aosai-portal-app';
        $script_url = AOSAI_PLUGIN_URL . 'build/' . ltrim( $entry['file'], '/' );

        wp_enqueue_script(
            $handle,
            $script_url,
            array(),
            AOSAI_VERSION,
            array( 'in_footer' => true )
        );

        add_filter( 'script_loader_tag', array( $this, 'add_module_type' ), 10, 3 );
        add_action( 'wp_print_footer_scripts', array( $this, 'remove_module_filter' ) );

        foreach ( aosai_get_asset_styles( 'src/portal/index.tsx' ) as $css_file ) {
            wp_enqueue_style(
                'aosai-portal-style-' . md5( $css_file ),
                AOSAI_PLUGIN_URL . 'build/' . ltrim( $css_file, '/' ),
                array(),
                AOSAI_VERSION
            );
        }

        wp_localize_script( $handle, 'aosaiPortalData', $this->get_portal_script_data( $initial_view ) );
        $this->assets_enqueued = true;
    }

    public function add_module_type( string $tag, string $handle, string $src ): string {
        if ( 'aosai-portal-app' !== $handle ) {
            return $tag;
        }

        return str_replace( ' src', ' type="module" src', $tag );
    }

    public function remove_module_filter() {
        remove_filter( 'script_loader_tag', array( $this, 'add_module_type' ) );
    }

    public function output_modulepreload_hints(): void {
        $manifest = aosai_get_asset_manifest();
        if ( empty( $manifest ) ) {
            return;
        }

        foreach ( $manifest as $entry ) {
            if ( empty( $entry['file'] ) ) {
                continue;
            }

            $file = ltrim( (string) $entry['file'], '/' );
            // Only preload JS files (not CSS)
            if ( substr( $file, -3 ) !== '.js' ) {
                continue;
            }

            $url = AOSAI_PLUGIN_URL . 'build/' . $file;
            echo '<link rel="modulepreload" href="' . esc_url( $url ) . '">' . "\n";
        }
    }

    private function get_portal_script_data( string $initial_view ): array {
        $branding = AOSAI_Setting::get_instance()->get_portal_branding();

        return array(
            'apiBase'          => esc_url_raw( rest_url() ),
            'nonce'            => wp_create_nonce( 'wp_rest' ),
            'userId'           => get_current_user_id(),
            'version'          => AOSAI_VERSION,
            'initialView'      => $initial_view,
            'portalUrl'        => aosai_get_portal_page_url(),
            'ticketUrl'        => aosai_get_ticket_page_url(),
            'loginUrl'         => aosai_get_login_page_url(),
            'logoutUrl'        => wp_logout_url( aosai_get_login_page_url() ),
            'lostPasswordUrl'  => wp_lostpassword_url( aosai_get_login_page_url() ),
            'serviceWorkerUrl' => home_url( '/?aosai_portal_sw=1' ),
            'manifestUrl'      => home_url( '/?aosai_portal_manifest=1' ),
            'branding'         => $branding,
            'brandingAssets'   => aosai_get_branding_assets(),
        );
    }

    private function render_login_shell( string $redirect_to ): string {
        if ( is_user_logged_in() ) {
            $portal_url = aosai_get_portal_page_url();
            return '<div class="aosai-portal-login-state"><a class="aosai-portal-launch" href="' . esc_url( $portal_url ) . '">' . esc_html__( 'Open Workspace', 'agency-os-ai' ) . '</a></div>';
        }

        $branding   = AOSAI_Setting::get_instance()->get_portal_branding();
        $assets     = aosai_get_branding_assets();
        $brand_logo = ! empty( $branding['company_logo_url'] ) ? $branding['company_logo_url'] : ( $assets['logo'] ?? '' );
        $login_url  = wp_login_url( $redirect_to );
        $reset_url  = wp_lostpassword_url( $redirect_to );
        $stats      = array(
            __( 'Projects, support, files, and delivery updates in one place.', 'agency-os-ai' ),
            __( 'Role-based workspace for clients and employees with no WordPress admin clutter.', 'agency-os-ai' ),
            __( 'Installable portal with branded PWA support and automatic page creation.', 'agency-os-ai' ),
        );

        ob_start();
        ?>
        <section class="aosai-login-shell" style="--aosai-primary: <?php echo esc_attr( $branding['primary_color'] ); ?>; --aosai-secondary: <?php echo esc_attr( $branding['secondary_color'] ); ?>;">
            <div class="aosai-login-panel aosai-login-brand">
                <div class="aosai-login-badge"><?php echo esc_html( $branding['portal_name'] ); ?></div>
                <h2><?php echo esc_html( $branding['welcome_title'] ); ?></h2>
                <p><?php echo esc_html( $branding['welcome_text'] ); ?></p>
                <ul>
                    <?php foreach ( $stats as $item ) : ?>
                        <li><?php echo esc_html( $item ); ?></li>
                    <?php endforeach; ?>
                </ul>
            </div>
            <div class="aosai-login-panel aosai-login-form-panel">
                <?php if ( ! empty( $brand_logo ) ) : ?>
                    <img class="aosai-login-logo" src="<?php echo esc_url( $brand_logo ); ?>" alt="<?php echo esc_attr( $branding['company_name'] ?: 'Agency OS AI' ); ?>">
                <?php endif; ?>
                <h3><?php echo esc_html__( 'Sign in to your workspace', 'agency-os-ai' ); ?></h3>
                <p><?php echo esc_html__( 'Use the access details shared by your agency team.', 'agency-os-ai' ); ?></p>
                <?php
                wp_login_form(
                    array(
                        'echo'           => true,
                        'redirect'       => $redirect_to,
                        'form_id'        => 'aosai-portal-login-form',
                        'label_username' => __( 'Email or Username', 'agency-os-ai' ),
                        'label_password' => __( 'Password', 'agency-os-ai' ),
                        'label_log_in'   => __( 'Enter Workspace', 'agency-os-ai' ),
                        'remember'       => true,
                    )
                );
                ?>
                <div class="aosai-login-links">
                    <a href="<?php echo esc_url( $reset_url ); ?>"><?php echo esc_html__( 'Forgot password?', 'agency-os-ai' ); ?></a>
                    <a href="mailto:<?php echo esc_attr( get_option( 'aosai_support_email', get_option( 'admin_email' ) ) ); ?>"><?php echo esc_html__( 'Need access?', 'agency-os-ai' ); ?></a>
                </div>
                <div class="aosai-login-meta">
                    <?php if ( ! empty( $branding['privacy_policy_url'] ) ) : ?>
                        <a href="<?php echo esc_url( $branding['privacy_policy_url'] ); ?>"><?php echo esc_html__( 'Privacy Policy', 'agency-os-ai' ); ?></a>
                    <?php endif; ?>
                    <?php if ( ! empty( $branding['terms_url'] ) ) : ?>
                        <a href="<?php echo esc_url( $branding['terms_url'] ); ?>"><?php echo esc_html__( 'Terms', 'agency-os-ai' ); ?></a>
                    <?php endif; ?>
                    <?php if ( ! empty( $branding['company_website'] ) ) : ?>
                        <a href="<?php echo esc_url( $branding['company_website'] ); ?>"><?php echo esc_html__( 'Website', 'agency-os-ai' ); ?></a>
                    <?php endif; ?>
                </div>
                <p class="aosai-login-branding">
                    <?php esc_html_e( 'A product of', 'agency-os-ai' ); ?>
                    <a href="https://themefreex.com" target="_blank" rel="noreferrer"><?php esc_html_e( 'Themefreex', 'agency-os-ai' ); ?></a>
                    <?php esc_html_e( 'by', 'agency-os-ai' ); ?>
                    <a href="https://codefreex.com" target="_blank" rel="noreferrer"><?php esc_html_e( 'Codefreex', 'agency-os-ai' ); ?></a>
                </p>
            </div>
        </section>
        <style>
            .aosai-login-shell { display:grid; grid-template-columns: 1.1fr .9fr; gap:24px; max-width:1120px; margin:40px auto; padding:24px; border-radius:32px; background:linear-gradient(145deg,#fffdf7 0%,#f8fafc 45%,#ecfeff 100%); box-shadow:0 24px 80px rgba(15,23,42,.12); border:1px solid rgba(15,23,42,.06); }
            .aosai-login-panel { border-radius:24px; padding:32px; }
            .aosai-login-brand { background:linear-gradient(160deg, var(--aosai-primary) 0%, #0f172a 80%); color:#fff; }
            .aosai-login-badge { display:inline-flex; padding:8px 14px; border-radius:999px; background:rgba(255,255,255,.12); font-size:12px; letter-spacing:.08em; text-transform:uppercase; margin-bottom:20px; }
            .aosai-login-brand h2 { font-size:clamp(2rem, 5vw, 3.5rem); line-height:1.05; margin:0 0 16px; }
            .aosai-login-brand p { margin:0 0 24px; font-size:16px; line-height:1.7; color:rgba(255,255,255,.86); }
            .aosai-login-brand ul { list-style:none; padding:0; margin:0; display:grid; gap:14px; }
            .aosai-login-brand li { padding:14px 16px; border-radius:16px; background:rgba(255,255,255,.08); backdrop-filter:blur(6px); }
            .aosai-login-form-panel { background:#ffffff; border:1px solid rgba(15,23,42,.08); }
            .aosai-login-logo { max-height:42px; width:auto; display:block; margin-bottom:20px; }
            .aosai-login-form-panel h3 { margin:0 0 8px; font-size:28px; color:#0f172a; }
            .aosai-login-form-panel p { margin:0 0 24px; color:#475569; }
            #aosai-portal-login-form label { display:block; margin-bottom:8px; color:#0f172a; font-weight:600; }
            #aosai-portal-login-form input[type="text"], #aosai-portal-login-form input[type="password"] { width:100%; padding:14px 16px; border-radius:16px; border:1px solid #cbd5e1; margin-bottom:16px; }
            #aosai-portal-login-form .login-submit input { width:100%; background:linear-gradient(135deg, var(--aosai-primary) 0%, var(--aosai-secondary) 100%); color:#fff; border:none; border-radius:999px; padding:14px 18px; font-weight:700; cursor:pointer; }
            .aosai-login-links { display:flex; justify-content:space-between; gap:16px; margin-top:18px; flex-wrap:wrap; }
            .aosai-login-meta { display:flex; flex-wrap:wrap; gap:12px; margin-top:18px; }
            .aosai-login-meta a { color:#64748b; font-size:13px; text-decoration:none; }
            .aosai-login-branding { margin:18px 0 0; color:#64748b; font-size:12px; line-height:1.7; }
            .aosai-login-branding a { color:var(--aosai-primary); font-weight:600; text-decoration:none; }
            .aosai-login-links a, .aosai-portal-launch { color:var(--aosai-primary); text-decoration:none; font-weight:600; }
            .aosai-portal-login-state { max-width:420px; margin:24px auto; padding:24px; text-align:center; }
            .aosai-portal-launch { display:inline-flex; padding:12px 18px; border-radius:999px; background:#f0fdfa; }
            @media (max-width: 900px) { .aosai-login-shell { grid-template-columns:1fr; padding:16px; } }
        </style>
        <?php
        return (string) ob_get_clean();
    }

    private function is_portal_page_request(): bool {
        if ( ! is_singular() ) {
            return false;
        }

        global $post;
        if ( ! $post instanceof WP_Post ) {
            return false;
        }

        return has_shortcode( $post->post_content, 'agency_os_ai_portal' ) || has_shortcode( $post->post_content, 'agency_os_ai_login' );
    }
}

