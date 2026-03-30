<?php
if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

class AOSAI_Settings_Page {

    public function render() {
        $settings   = AOSAI_Setting::get_instance()->get_all();
        $shortcodes = is_array( $settings['shortcodes'] ?? null ) ? $settings['shortcodes'] : array();
        ?>
        <div class="wrap">
            <h1><?php esc_html_e( 'Agency OS AI Settings', 'agency-os-ai' ); ?></h1>
            <p><?php esc_html_e( 'Agency OS AI uses a React-powered settings workspace. This fallback view summarizes the active portal configuration for debugging and maintenance.', 'agency-os-ai' ); ?></p>

            <table class="widefat striped" style="max-width: 960px; margin-top: 20px;">
                <tbody>
                    <tr>
                        <th style="width: 220px;"><?php esc_html_e( 'Company Name', 'agency-os-ai' ); ?></th>
                        <td><?php echo esc_html( (string) ( $settings['company_name'] ?? get_bloginfo( 'name' ) ) ); ?></td>
                    </tr>
                    <tr>
                        <th><?php esc_html_e( 'Portal Page', 'agency-os-ai' ); ?></th>
                        <td><?php echo esc_html( (string) ( $settings['portal_page_url'] ?? '' ) ?: esc_html__( 'Not created yet', 'agency-os-ai' ) ); ?></td>
                    </tr>
                    <tr>
                        <th><?php esc_html_e( 'Login Page', 'agency-os-ai' ); ?></th>
                        <td><?php echo esc_html( (string) ( $settings['portal_login_page_url'] ?? '' ) ?: esc_html__( 'Not created yet', 'agency-os-ai' ) ); ?></td>
                    </tr>
                    <tr>
                        <th><?php esc_html_e( 'Support Page', 'agency-os-ai' ); ?></th>
                        <td><?php echo esc_html( (string) ( $settings['portal_ticket_page_url'] ?? '' ) ?: esc_html__( 'Not created yet', 'agency-os-ai' ) ); ?></td>
                    </tr>
                </tbody>
            </table>

            <?php if ( ! empty( $shortcodes ) ) : ?>
                <h2 style="margin-top: 28px;"><?php esc_html_e( 'Available Shortcodes', 'agency-os-ai' ); ?></h2>
                <ul style="max-width: 960px;">
                    <?php foreach ( $shortcodes as $shortcode ) : ?>
                        <li style="margin-bottom: 10px;">
                            <strong><?php echo esc_html( (string) ( $shortcode['label'] ?? __( 'Shortcode', 'agency-os-ai' ) ) ); ?>:</strong>
                            <code><?php echo esc_html( (string) ( $shortcode['shortcode'] ?? '' ) ); ?></code>
                            <?php if ( ! empty( $shortcode['description'] ) ) : ?>
                                <span> - <?php echo esc_html( (string) $shortcode['description'] ); ?></span>
                            <?php endif; ?>
                        </li>
                    <?php endforeach; ?>
                </ul>
            <?php endif; ?>
        </div>
        <?php
    }
}
