<?php
if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

class AOSAI_Login_Activity {
    use AOSAI_Singleton;

    private bool $table_checked = false;

    private function __construct() {}

    public function get_table(): string {
        global $wpdb;
        return $wpdb->prefix . 'aosai_login_activity';
    }

    public function ensure_table(): void {
        if ( $this->table_checked ) {
            return;
        }
        $this->table_checked = true;

        if ( 'yes' === (string) get_option( 'aosai_login_activity_table_ready', 'no' ) ) {
            return;
        }

        global $wpdb;
        $table_name      = $this->get_table();
        $charset_collate = $wpdb->get_charset_collate();

        require_once ABSPATH . 'wp-admin/includes/upgrade.php';

        $sql = "CREATE TABLE {$table_name} (
            id BIGINT(20) UNSIGNED NOT NULL AUTO_INCREMENT,
            user_id BIGINT(20) UNSIGNED NOT NULL DEFAULT 0,
            user_login VARCHAR(60) NOT NULL DEFAULT '',
            portal_type VARCHAR(20) NOT NULL DEFAULT 'team',
            event_type VARCHAR(40) NOT NULL DEFAULT 'login_success',
            ip_address VARCHAR(64) NOT NULL DEFAULT '',
            user_agent TEXT NULL,
            created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
            PRIMARY KEY (id),
            KEY idx_user_id (user_id),
            KEY idx_event_type (event_type),
            KEY idx_created_at (created_at)
        ) {$charset_collate};";

        dbDelta( $sql );
        update_option( 'aosai_login_activity_table_ready', 'yes' );
    }

    public function on_user_login( string $user_login, WP_User $user ): void {
        if ( ! $this->is_tracking_enabled() ) {
            return;
        }

        $this->record_event(
            array(
                'user_id'    => (int) $user->ID,
                'user_login' => $user_login,
                'portal_type'=> aosai_get_user_portal_type( (int) $user->ID ),
                'event_type' => 'login_success',
            )
        );
    }

    public function on_login_failed( string $username ): void {
        if ( ! $this->is_tracking_enabled() ) {
            return;
        }

        $user = get_user_by( 'login', sanitize_user( $username ) );

        $this->record_event(
            array(
                'user_id'    => $user ? (int) $user->ID : 0,
                'user_login' => sanitize_user( $username ),
                'portal_type'=> $user ? aosai_get_user_portal_type( (int) $user->ID ) : 'guest',
                'event_type' => 'login_failed',
            )
        );
    }

    public function on_user_logout(): void {
        if ( ! $this->is_tracking_enabled() ) {
            return;
        }

        $user = wp_get_current_user();
        if ( ! $user || ! $user->ID ) {
            return;
        }

        $this->record_event(
            array(
                'user_id'    => (int) $user->ID,
                'user_login' => (string) $user->user_login,
                'portal_type'=> aosai_get_user_portal_type( (int) $user->ID ),
                'event_type' => 'logout',
            )
        );
    }

    public function get_events_for_user( int $user_id, array $args = array() ): array {
        return $this->get_events(
            array_merge(
                $args,
                array(
                    'user_id' => $user_id,
                )
            )
        );
    }

    public function get_events_for_workspace( array $args = array() ): array {
        return $this->get_events( $args );
    }

    public function get_events( array $args = array() ): array {
        $this->ensure_table();

        global $wpdb;
        $table = $this->get_table();

        $defaults = array(
            'page'       => 1,
            'per_page'   => 20,
            'user_id'    => 0,
            'event_type' => '',
            'portal_type'=> '',
        );

        $args    = wp_parse_args( $args, $defaults );
        $page    = max( 1, absint( $args['page'] ) );
        $per_page = min( 100, max( 1, absint( $args['per_page'] ) ) );
        $offset  = ( $page - 1 ) * $per_page;

        $where   = 'WHERE 1=1';
        $params  = array();

        if ( absint( $args['user_id'] ) > 0 ) {
            $where   .= ' AND user_id = %d';
            $params[] = absint( $args['user_id'] );
        }

        if ( '' !== (string) $args['event_type'] ) {
            $where   .= ' AND event_type = %s';
            $params[] = sanitize_key( (string) $args['event_type'] );
        }

        if ( '' !== (string) $args['portal_type'] ) {
            $where   .= ' AND portal_type = %s';
            $params[] = sanitize_key( (string) $args['portal_type'] );
        }

        $params[] = $per_page;
        $params[] = $offset;

        $rows = $wpdb->get_results(
            $wpdb->prepare(
                "SELECT id, user_id, user_login, portal_type, event_type, ip_address, user_agent, created_at
                FROM {$table}
                {$where}
                ORDER BY id DESC
                LIMIT %d OFFSET %d",
                ...$params
            ),
            ARRAY_A
        );

        return array_map( array( $this, 'enrich_row' ), $rows ?: array() );
    }

    private function enrich_row( array $row ): array {
        $user_id = absint( $row['user_id'] ?? 0 );
        if ( $user_id > 0 ) {
            $user = get_userdata( $user_id );
            if ( $user ) {
                $row['user_name'] = $user->display_name;
                $row['email']     = $user->user_email;
            }
        }

        if ( empty( $row['user_name'] ) ) {
            $row['user_name'] = $row['user_login'] ?: __( 'Unknown user', 'agency-os-ai' );
        }

        return $row;
    }

    private function record_event( array $data ): bool {
        $this->ensure_table();

        global $wpdb;

        $result = $wpdb->insert(
            $this->get_table(),
            array(
                'user_id'    => absint( $data['user_id'] ?? 0 ),
                'user_login' => sanitize_user( (string) ( $data['user_login'] ?? '' ), true ),
                'portal_type'=> sanitize_key( (string) ( $data['portal_type'] ?? 'team' ) ),
                'event_type' => sanitize_key( (string) ( $data['event_type'] ?? 'login_success' ) ),
                'ip_address' => $this->get_client_ip(),
                'user_agent' => $this->get_user_agent(),
                'created_at' => current_time( 'mysql' ),
            ),
            array( '%d', '%s', '%s', '%s', '%s', '%s', '%s' )
        );

        return false !== $result;
    }

    private function get_client_ip(): string {
        $headers = array( 'HTTP_CF_CONNECTING_IP', 'HTTP_X_REAL_IP', 'HTTP_X_FORWARDED_FOR', 'REMOTE_ADDR' );
        foreach ( $headers as $header ) {
            if ( empty( $_SERVER[ $header ] ) ) {
                continue;
            }

            $raw = sanitize_text_field( wp_unslash( (string) $_SERVER[ $header ] ) );
            if ( '' === $raw ) {
                continue;
            }

            $candidate = trim( explode( ',', $raw )[0] );
            if ( filter_var( $candidate, FILTER_VALIDATE_IP ) ) {
                return $candidate;
            }
        }

        return '';
    }

    private function get_user_agent(): string {
        if ( empty( $_SERVER['HTTP_USER_AGENT'] ) ) {
            return '';
        }

        $ua = sanitize_text_field( wp_unslash( (string) $_SERVER['HTTP_USER_AGENT'] ) );
        return substr( $ua, 0, 500 );
    }

    private function is_tracking_enabled(): bool {
        return 'yes' === (string) get_option( 'aosai_login_tracking_enabled', 'yes' );
    }
}
