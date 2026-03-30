<?php
if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

class AOSAI_Email_Service {
    use AOSAI_Singleton;

    private function __construct() {}

    public function send_notification( \WP_User $user, array $args ): void {
        $subject = sanitize_text_field( (string) $args['title'] );
        $message = $this->build_email_template(
            array(
                'heading'    => $subject,
                'intro'      => sanitize_text_field( (string) $args['title'] ),
                'content'    => isset( $args['content'] ) ? wp_kses_post( (string) $args['content'] ) : '',
                'button_url' => aosai_get_portal_page_url(),
                'button_text'=> __( 'Open Workspace', 'agency-os-ai' ),
            )
        );

        $this->send_email( $user->user_email, $subject, $message );
    }

    public function send_ticket_created_emails( array $ticket ): void {
        $portal_url       = aosai_get_ticket_page_url();
        $requester_email  = sanitize_email( (string) ( $ticket['requester_email'] ?? '' ) );
        $department       = AOSAI_Department::get_instance()->get( (int) ( $ticket['department_id'] ?? 0 ) );
        $department_email = sanitize_email( (string) ( $department['email'] ?? get_option( 'aosai_support_email', get_option( 'admin_email' ) ) ) );

        if ( $requester_email ) {
            $this->send_email(
                $requester_email,
                sprintf( __( 'Ticket received: %s', 'agency-os-ai' ), $ticket['subject'] ),
                $this->build_email_template(
                    array(
                        'heading'     => __( 'We received your ticket', 'agency-os-ai' ),
                        'intro'       => sprintf( __( 'Your request "%s" is now in the queue.', 'agency-os-ai' ), $ticket['subject'] ),
                        'content'     => wp_trim_words( (string) $ticket['content'], 32 ),
                        'button_url'  => $portal_url,
                        'button_text' => __( 'View Ticket Center', 'agency-os-ai' ),
                    )
                )
            );
        }

        if ( $department_email && $department_email !== $requester_email ) {
            $this->send_email(
                $department_email,
                sprintf( __( 'New support ticket: %s', 'agency-os-ai' ), $ticket['subject'] ),
                $this->build_email_template(
                    array(
                        'heading'     => __( 'New ticket submitted', 'agency-os-ai' ),
                        'intro'       => sprintf( __( '%s opened a new support request.', 'agency-os-ai' ), $ticket['requester_name'] ?? __( 'A portal user', 'agency-os-ai' ) ),
                        'content'     => wp_trim_words( (string) $ticket['content'], 40 ),
                        'button_url'  => $portal_url,
                        'button_text' => __( 'Open Ticket Center', 'agency-os-ai' ),
                    )
                )
            );
        }
    }

    public function send_task_assigned_email( \WP_User $user, array $task, \WP_User $assigner ): void {
        $this->send_notification(
            $user,
            array(
                'user_id'     => $user->ID,
                'project_id'  => $task['project_id'],
                'type'        => 'task_assigned',
                'title'       => sprintf(
                    esc_html__( '%1$s assigned you to "%2$s"', 'agency-os-ai' ),
                    $assigner->display_name,
                    $task['title']
                ),
                'content'     => $task['description'] ?? '',
                'object_type' => 'task',
                'object_id'   => $task['id'],
            )
        );
    }

    public function send_ticket_assigned_email( \WP_User $user, array $ticket ): void {
        $this->send_email(
            $user->user_email,
            sprintf( __( 'Ticket assigned: %s', 'agency-os-ai' ), $ticket['subject'] ),
            $this->build_email_template(
                array(
                    'heading'     => __( 'A ticket was assigned to you', 'agency-os-ai' ),
                    'intro'       => sprintf( __( 'You are now responsible for ticket "%s".', 'agency-os-ai' ), $ticket['subject'] ),
                    'content'     => wp_trim_words( (string) $ticket['content'], 36 ),
                    'button_url'  => aosai_get_ticket_page_url(),
                    'button_text' => __( 'Open Ticket Center', 'agency-os-ai' ),
                )
            )
        );
    }

    public function send_ticket_status_updated_email( array $ticket, string $previous_status ): void {
        $requester_email = sanitize_email( (string) ( $ticket['requester_email'] ?? '' ) );
        if ( '' === $requester_email ) {
            return;
        }

        $this->send_email(
            $requester_email,
            sprintf( __( 'Ticket update: %s', 'agency-os-ai' ), $ticket['subject'] ),
            $this->build_email_template(
                array(
                    'heading'     => __( 'Your ticket has been updated', 'agency-os-ai' ),
                    'intro'       => sprintf(
                        __( 'Status changed from %1$s to %2$s.', 'agency-os-ai' ),
                        ucwords( str_replace( '_', ' ', $previous_status ) ),
                        ucwords( str_replace( '_', ' ', (string) ( $ticket['status'] ?? 'open' ) ) )
                    ),
                    'content'     => wp_trim_words( (string) $ticket['content'], 28 ),
                    'button_url'  => aosai_get_ticket_page_url(),
                    'button_text' => __( 'Review Ticket', 'agency-os-ai' ),
                )
            )
        );
    }

    private function send_email( string $to, string $subject, string $message ): void {
        if ( '' === $to ) {
            return;
        }

        $support_email = sanitize_email( (string) get_option( 'aosai_support_email', get_option( 'admin_email' ) ) );
        $payload = array(
            'to'          => $to,
            'subject'     => $subject,
            'message'     => $message,
            'headers'     => array(
                'Content-Type: text/html; charset=UTF-8',
                'From: ' . $this->get_from_name() . ' <' . $this->get_from_email() . '>',
                'Reply-To: ' . ( $support_email ?: $this->get_from_email() ),
            ),
            'attachments' => array(),
        );

        $payload = apply_filters( 'aosai_email_payload', $payload );
        do_action( 'aosai_before_send_email', $payload );

        $preflight = apply_filters( 'aosai_pre_send_email', null, $payload );
        if ( null !== $preflight ) {
            do_action( 'aosai_after_send_email', (bool) $preflight, $payload );
            return;
        }

        $result = wp_mail(
            (string) $payload['to'],
            (string) $payload['subject'],
            (string) $payload['message'],
            (array) ( $payload['headers'] ?? array() ),
            (array) ( $payload['attachments'] ?? array() )
        );

        do_action( 'aosai_after_send_email', (bool) $result, $payload );
    }

    private function build_email_template( array $args ): string {
        $company_name   = get_option( 'aosai_company_name', get_bloginfo( 'name' ) );
        $logo_url       = get_option( 'aosai_company_logo_url', '' );
        if ( empty( $logo_url ) ) {
            $assets   = aosai_get_branding_assets();
            $logo_url = $assets['logo'] ?? '';
        }
        $primary_color  = get_option( 'aosai_primary_color', '#0f766e' );
        $footer_text    = get_option( 'aosai_email_footer_text', 'You are receiving this update because you are a member of the workspace.' );
        $button_url     = esc_url( (string) ( $args['button_url'] ?? aosai_get_portal_page_url() ) );
        $button_text    = sanitize_text_field( (string) ( $args['button_text'] ?? __( 'Open Workspace', 'agency-os-ai' ) ) );
        $heading        = sanitize_text_field( (string) ( $args['heading'] ?? $company_name ) );
        $intro          = wp_kses_post( (string) ( $args['intro'] ?? '' ) );
        $content        = wp_kses_post( (string) ( $args['content'] ?? '' ) );

        ob_start();
        ?>
<!DOCTYPE html>
<html>
<head>
    <meta charset="UTF-8">
    <style>
        body { font-family: 'Segoe UI', Tahoma, sans-serif; margin: 0; padding: 32px 16px; background: #f4f7f7; color: #0f172a; }
        .shell { max-width: 640px; margin: 0 auto; background: #ffffff; border-radius: 20px; overflow: hidden; border: 1px solid #dbe4e4; }
        .hero { padding: 28px 32px; background: linear-gradient(135deg, <?php echo esc_html( $primary_color ); ?> 0%, #0f172a 100%); color: #ffffff; }
        .logo { max-height: 36px; margin-bottom: 18px; }
        .body { padding: 28px 32px; }
        .body p { font-size: 15px; line-height: 1.7; margin: 0 0 14px; color: #334155; }
        .card { margin: 18px 0; padding: 18px; border-radius: 16px; background: #f8fafc; border: 1px solid #e2e8f0; }
        .btn { display: inline-block; margin-top: 10px; background: <?php echo esc_html( $primary_color ); ?>; color: #ffffff !important; text-decoration: none; padding: 12px 18px; border-radius: 999px; font-weight: 600; }
        .footer { padding: 0 32px 28px; color: #64748b; font-size: 12px; }
    </style>
</head>
<body>
    <div class="shell">
        <div class="hero">
            <?php if ( ! empty( $logo_url ) ) : ?>
                <img class="logo" src="<?php echo esc_url( $logo_url ); ?>" alt="<?php echo esc_attr( $company_name ); ?>">
            <?php endif; ?>
            <h1 style="margin:0;font-size:28px;line-height:1.2;"><?php echo esc_html( $heading ); ?></h1>
        </div>
        <div class="body">
            <?php if ( '' !== $intro ) : ?>
                <p><?php echo wp_kses_post( $intro ); ?></p>
            <?php endif; ?>
            <?php if ( '' !== $content ) : ?>
                <div class="card"><?php echo wpautop( $content ); ?></div>
            <?php endif; ?>
            <a href="<?php echo $button_url; ?>" class="btn"><?php echo esc_html( $button_text ); ?></a>
        </div>
        <div class="footer">
            <?php echo esc_html( $footer_text ); ?>
        </div>
    </div>
</body>
</html>
        <?php
        return (string) ob_get_clean();
    }

    private function get_from_name(): string {
        return sanitize_text_field( (string) get_option( 'aosai_email_from_name', get_bloginfo( 'name' ) ) );
    }

    private function get_from_email(): string {
        $email = sanitize_email( (string) get_option( 'aosai_email_from_email', get_option( 'admin_email' ) ) );
        return $email ?: get_option( 'admin_email' );
    }
}

