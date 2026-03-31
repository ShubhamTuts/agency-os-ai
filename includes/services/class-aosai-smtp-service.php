<?php
if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

class AOSAI_SMTP_Service {
    use AOSAI_Singleton;

    private function __construct() {}

    public function configure_phpmailer( PHPMailer\PHPMailer\PHPMailer $phpmailer ): void {
        if ( 'yes' !== get_option( 'aosai_smtp_enabled', 'no' ) ) {
            return;
        }

        $host       = sanitize_text_field( (string) get_option( 'aosai_smtp_host', '' ) );
        $port       = absint( get_option( 'aosai_smtp_port', 587 ) );
        $username   = sanitize_text_field( (string) get_option( 'aosai_smtp_username', '' ) );
        $password   = (string) get_option( 'aosai_smtp_password', '' );
        $encryption = sanitize_text_field( (string) get_option( 'aosai_smtp_encryption', 'tls' ) );
        $from_name  = sanitize_text_field( (string) get_option( 'aosai_email_from_name', get_bloginfo( 'name' ) ) );
        $from_email = sanitize_email( (string) get_option( 'aosai_email_from_email', get_option( 'admin_email' ) ) );

        if ( empty( $host ) ) {
            return;
        }

        $phpmailer->isSMTP();
        $phpmailer->Host       = $host;
        $phpmailer->SMTPAuth   = ( 'yes' === get_option( 'aosai_smtp_auth', 'yes' ) );
        $phpmailer->Username   = $username;
        $phpmailer->Password   = $password;
        $phpmailer->Port       = $port;
        $phpmailer->SMTPAutoTLS = ( 'tls' === $encryption );
        if ( 'ssl' === $encryption ) {
            $phpmailer->SMTPSecure = PHPMailer\PHPMailer\PHPMailer::ENCRYPTION_SMTPS;
        } elseif ( 'tls' === $encryption ) {
            $phpmailer->SMTPSecure = PHPMailer\PHPMailer\PHPMailer::ENCRYPTION_STARTTLS;
        } else {
            $phpmailer->SMTPSecure = '';
        }
        $phpmailer->CharSet    = 'UTF-8';

        if ( ! empty( $from_name ) ) {
            $phpmailer->FromName = $from_name;
        }

        if ( ! empty( $from_email ) ) {
            $phpmailer->From = $from_email;
            $phpmailer->addReplyTo( $from_email, $from_name );
        }
    }

    public function test_connection(): array {
        if ( 'yes' !== get_option( 'aosai_smtp_enabled', 'no' ) ) {
            return array( 'success' => false, 'message' => __( 'SMTP is not enabled.', 'agency-os-ai' ) );
        }

        $test_to = sanitize_email( (string) get_option( 'admin_email' ) );
        $result  = wp_mail(
            $test_to,
            __( 'Agency OS AI SMTP Test', 'agency-os-ai' ),
            __( 'This is a test email to verify your SMTP configuration is working correctly.', 'agency-os-ai' )
        );

        return array(
            'success' => (bool) $result,
            'message' => $result
                ? __( 'Test email sent successfully.', 'agency-os-ai' )
                : __( 'Failed to send test email. Check SMTP credentials.', 'agency-os-ai' ),
        );
    }
}
