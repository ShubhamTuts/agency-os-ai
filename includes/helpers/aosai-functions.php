<?php
if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

function aosai_is_pro_active(): bool {
    return defined( 'AOSAI_PRO_VERSION' ) && AOSAI_PRO_VERSION;
}

function aosai_get_avatar_url( int $user_id, int $size = 48 ): string {
    return esc_url( get_avatar_url( $user_id, array( 'size' => $size ) ) );
}

function aosai_format_date( string $date ): string {
    if ( empty( $date ) ) {
        return '';
    }
    return wp_date( get_option( 'date_format' ), strtotime( $date ) );
}

function aosai_format_datetime( string $datetime ): string {
    if ( empty( $datetime ) ) {
        return '';
    }
    return wp_date(
        get_option( 'date_format' ) . ' ' . get_option( 'time_format' ),
        strtotime( $datetime )
    );
}

function aosai_get_js_translations(): array {
    return array(
        'dashboard'   => esc_html__( 'Dashboard', 'agency-os-ai' ),
        'projects'    => esc_html__( 'Projects', 'agency-os-ai' ),
        'tasks'       => esc_html__( 'Tasks', 'agency-os-ai' ),
        'milestones' => esc_html__( 'Milestones', 'agency-os-ai' ),
        'messages'    => esc_html__( 'Messages', 'agency-os-ai' ),
        'files'       => esc_html__( 'Files', 'agency-os-ai' ),
        'reports'    => esc_html__( 'Reports', 'agency-os-ai' ),
        'settings'    => esc_html__( 'Settings', 'agency-os-ai' ),
        'create'     => esc_html__( 'Create', 'agency-os-ai' ),
        'edit'       => esc_html__( 'Edit', 'agency-os-ai' ),
        'delete'     => esc_html__( 'Delete', 'agency-os-ai' ),
        'save'       => esc_html__( 'Save', 'agency-os-ai' ),
        'cancel'     => esc_html__( 'Cancel', 'agency-os-ai' ),
        'confirm'    => esc_html__( 'Are you sure?', 'agency-os-ai' ),
        'loading'    => esc_html__( 'Loading...', 'agency-os-ai' ),
        'no_results' => esc_html__( 'No results found.', 'agency-os-ai' ),
        'error'      => esc_html__( 'An error occurred.', 'agency-os-ai' ),
        'success'    => esc_html__( 'Operation successful.', 'agency-os-ai' ),
        'open'       => esc_html__( 'Open', 'agency-os-ai' ),
        'in_progress'=> esc_html__( 'In Progress', 'agency-os-ai' ),
        'done'       => esc_html__( 'Done', 'agency-os-ai' ),
        'overdue'    => esc_html__( 'Overdue', 'agency-os-ai' ),
        'low'        => esc_html__( 'Low', 'agency-os-ai' ),
        'medium'     => esc_html__( 'Medium', 'agency-os-ai' ),
        'high'       => esc_html__( 'High', 'agency-os-ai' ),
        'urgent'     => esc_html__( 'Urgent', 'agency-os-ai' ),
        'upgrade_pro'=> esc_html__( 'Upgrade to Pro', 'agency-os-ai' ),
        'ai_generate'=> esc_html__( 'Generate with AI', 'agency-os-ai' ),
    );
}

function aosai_log_activity( array $args ): void {
    global $wpdb;
    $wpdb->insert(
        $wpdb->prefix . 'aosai_activities',
        array(
            'project_id'  => absint( $args['project_id'] ),
            'user_id'    => absint( $args['user_id'] ?? get_current_user_id() ),
            'action'     => sanitize_key( $args['action'] ),
            'object_type'=> sanitize_key( $args['object_type'] ),
            'object_id'  => absint( $args['object_id'] ),
            'meta'       => isset( $args['meta'] ) ? wp_json_encode( $args['meta'] ) : null,
        ),
        array( '%d', '%d', '%s', '%s', '%d', '%s' )
    );
}

function aosai_user_can_access_project( int $user_id, int $project_id ): bool {
    if ( user_can( $user_id, 'manage_options' ) ) {
        return true;
    }
    
    global $wpdb;
    $member = $wpdb->get_row(
        $wpdb->prepare(
            "SELECT id FROM {$wpdb->prefix}aosai_project_users WHERE project_id = %d AND user_id = %d",
            $project_id,
            $user_id
        )
    );
    
    return ! empty( $member );
}

function aosai_user_project_role( int $user_id, int $project_id ): string {
    if ( user_can( $user_id, 'manage_options' ) ) {
        return 'manager';
    }
    
    global $wpdb;
    $role = $wpdb->get_var(
        $wpdb->prepare(
            "SELECT role FROM {$wpdb->prefix}aosai_project_users WHERE project_id = %d AND user_id = %d",
            $project_id,
            $user_id
        )
    );
    
    return $role ?: '';
}

function aosai_rest_error( string $code, string $message, int $status = 400 ): \WP_Error {
    return new \WP_Error(
        'aosai_' . $code,
        $message,
        array( 'status' => $status )
    );
}

function aosai_parse_mentions( string $content, int $project_id ): array {
    $mentioned_users = array();
    
    if ( preg_match_all( '/@([a-zA-Z0-9_\-\.]+)/', $content, $matches ) ) {
        foreach ( $matches[1] as $username ) {
            $user = get_user_by( 'login', sanitize_user( $username ) );
            if ( $user && aosai_user_can_access_project( $user->ID, $project_id ) ) {
                $mentioned_users[] = $user->ID;
            }
        }
    }
    
    return array_unique( $mentioned_users );
}

function aosai_get_asset_manifest(): array {
    static $manifest = null;

    if ( null !== $manifest ) {
        return $manifest;
    }

    $manifest_path = AOSAI_PLUGIN_DIR . 'build/manifest.json';
    if ( ! file_exists( $manifest_path ) ) {
        $manifest = array();
        return $manifest;
    }

    $decoded = json_decode( (string) file_get_contents( $manifest_path ), true );
    $manifest = is_array( $decoded ) ? $decoded : array();

    return $manifest;
}

function aosai_get_asset_entry( string $entry ): ?array {
    $manifest = aosai_get_asset_manifest();
    return $manifest[ $entry ] ?? null;
}

function aosai_get_asset_styles( string $entry ): array {
    $manifest = aosai_get_asset_manifest();
    if ( empty( $manifest[ $entry ] ) ) {
        return array();
    }

    $styles  = array();
    $visited = array();

    $collect = static function( string $key ) use ( &$collect, &$manifest, &$styles, &$visited ) {
        if ( isset( $visited[ $key ] ) || empty( $manifest[ $key ] ) ) {
            return;
        }

        $visited[ $key ] = true;
        $item = $manifest[ $key ];

        foreach ( (array) ( $item['css'] ?? array() ) as $css_file ) {
            $styles[ $css_file ] = $css_file;
        }

        foreach ( (array) ( $item['imports'] ?? array() ) as $import_key ) {
            $collect( $import_key );
        }
    };

    $collect( $entry );

    return array_values( $styles );
}

function aosai_get_portal_page_url(): string {
    $page_id = absint( get_option( 'aosai_portal_page_id', 0 ) );
    if ( $page_id > 0 ) {
        $url = get_permalink( $page_id );
        if ( $url ) {
            return $url;
        }
    }

    return home_url( '/' );
}

function aosai_get_login_page_url(): string {
    $page_id = absint( get_option( 'aosai_portal_login_page_id', 0 ) );
    if ( $page_id > 0 ) {
        $url = get_permalink( $page_id );
        if ( $url ) {
            return $url;
        }
    }

    return wp_login_url( aosai_get_portal_page_url() );
}

function aosai_get_ticket_page_url(): string {
    $page_id = absint( get_option( 'aosai_portal_ticket_page_id', 0 ) );
    if ( $page_id > 0 ) {
        $url = get_permalink( $page_id );
        if ( $url ) {
            return $url;
        }
    }

    return aosai_get_portal_page_url() . '#tickets';
}

function aosai_get_user_portal_type( int $user_id ): string {
    $user = get_userdata( $user_id );
    if ( ! $user ) {
        return 'guest';
    }

    $roles = (array) $user->roles;
    if ( in_array( 'aosai_client', $roles, true ) ) {
        return 'client';
    }

    if ( in_array( 'aosai_employee', $roles, true ) ) {
        return 'employee';
    }

    return 'staff';
}

function aosai_user_has_portal_access( int $user_id ): bool {
    if ( user_can( $user_id, 'manage_options' ) ) {
        return true;
    }

    if ( user_can( $user_id, 'aosai_access_portal' ) ) {
        return true;
    }

    return aosai_get_user_portal_type( $user_id ) !== 'guest';
}

function aosai_get_branding_assets(): array {
    return array(
        'logo'         => esc_url( AOSAI_PLUGIN_URL . 'assets/branding/logo_agency_os_ai.svg' ),
        'animatedLogo' => esc_url( AOSAI_PLUGIN_URL . 'assets/branding/animated_logo_agency_os_ai.svg' ),
    );
}

function aosai_render_brand_preloader( string $id, string $message ): string {
    $assets   = aosai_get_branding_assets();
    $logo_url = ! empty( $assets['animatedLogo'] ) ? $assets['animatedLogo'] : ( $assets['logo'] ?? '' );

    ob_start();
    ?>
    <div id="<?php echo esc_attr( $id ); ?>" class="aosai-boot-preloader" aria-live="polite" aria-label="<?php echo esc_attr( $message ); ?>">
        <div class="aosai-boot-preloader__card">
            <?php if ( ! empty( $logo_url ) ) : ?>
                <img class="aosai-boot-preloader__logo" src="<?php echo esc_url( $logo_url ); ?>" alt="<?php esc_attr_e( 'Agency OS AI', 'agency-os-ai' ); ?>">
            <?php endif; ?>
            <p class="aosai-boot-preloader__message"><?php echo esc_html( $message ); ?></p>
        </div>
    </div>
    <style>
        .aosai-boot-preloader {
            position: fixed;
            inset: 32px 24px 24px;
            z-index: 9999;
            display: flex;
            align-items: center;
            justify-content: center;
            padding: 24px;
            background:
                radial-gradient(circle at top left, rgba(15,118,110,0.16), transparent 35%),
                radial-gradient(circle at top right, rgba(245,158,11,0.14), transparent 30%),
                linear-gradient(180deg, rgba(248,250,252,0.98), rgba(236,254,255,0.96));
            backdrop-filter: blur(10px);
            transition: opacity 240ms ease, visibility 240ms ease, transform 240ms ease;
        }
        .aosai-boot-preloader.is-ready {
            opacity: 0;
            visibility: hidden;
            pointer-events: none;
            transform: scale(1.01);
        }
        .aosai-boot-preloader__card {
            display: flex;
            min-width: min(420px, 100%);
            max-width: 520px;
            flex-direction: column;
            align-items: center;
            gap: 18px;
            border: 1px solid rgba(15, 23, 42, 0.08);
            border-radius: 32px;
            background: rgba(255, 255, 255, 0.88);
            padding: 28px 32px;
            box-shadow: 0 30px 80px rgba(15, 23, 42, 0.14);
        }
        .aosai-boot-preloader__logo {
            width: min(280px, 72vw);
            height: auto;
            display: block;
        }
        .aosai-boot-preloader__message {
            margin: 0;
            color: #334155;
            font: 600 14px/1.6 "Segoe UI", system-ui, sans-serif;
            letter-spacing: 0.02em;
            text-align: center;
        }
        @media (max-width: 782px) {
            .aosai-boot-preloader {
                inset: 18px 12px 12px;
                padding: 12px;
            }
            .aosai-boot-preloader__card {
                border-radius: 24px;
                padding: 22px 18px;
            }
        }
    </style>
    <?php

    return (string) ob_get_clean();
}
