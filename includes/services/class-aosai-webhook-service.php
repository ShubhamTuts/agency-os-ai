<?php
if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

class AOSAI_Webhook_Service {
    use AOSAI_Singleton;

    private function __construct() {}

    public function dispatch( string $event, array $payload ): void {
        global $wpdb;

        $webhooks = $wpdb->get_results(
            $wpdb->prepare(
                "SELECT id, url, secret FROM {$wpdb->prefix}aosai_webhooks WHERE is_active = 1 AND (events = 'all' OR events LIKE %s)",
                '%' . $wpdb->esc_like( $event ) . '%'
            ),
            ARRAY_A
        );

        if ( empty( $webhooks ) ) {
            return;
        }

        $body = wp_json_encode(
            array(
                'event'     => $event,
                'timestamp' => current_time( 'timestamp' ),
                'data'      => $payload,
            )
        );

        foreach ( $webhooks as $webhook ) {
            $signature = hash_hmac( 'sha256', (string) $body, (string) $webhook['secret'] );

            wp_remote_post(
                esc_url_raw( (string) $webhook['url'] ),
                array(
                    'timeout'     => 5,
                    'blocking'    => false,
                    'headers'     => array(
                        'Content-Type'           => 'application/json',
                        'X-AOSAI-Event'          => $event,
                        'X-AOSAI-Signature'      => 'sha256=' . $signature,
                        'X-AOSAI-Webhook-ID'     => absint( $webhook['id'] ),
                    ),
                    'body'        => $body,
                    'data_format' => 'body',
                )
            );

            $wpdb->query(
                $wpdb->prepare(
                    "UPDATE {$wpdb->prefix}aosai_webhooks SET last_triggered_at = %s, trigger_count = trigger_count + 1 WHERE id = %d",
                    current_time( 'mysql' ),
                    absint( $webhook['id'] )
                )
            );
        }
    }
}
