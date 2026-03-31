<?php
if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

class AOSAI_REST_Invoices extends WP_REST_Controller {
    use AOSAI_REST_Validation;
    
    protected $namespace = 'aosai/v1';
    protected $rest_base = 'invoices';
    
    public function register_routes() {
        register_rest_route( $this->namespace, '/' . $this->rest_base, array(
            array(
                'methods'             => \WP_REST_Server::READABLE,
                'callback'            => array( $this, 'get_items' ),
                'permission_callback' => array( $this, 'get_items_permissions_check' ),
                'args'               => $this->get_collection_params(),
            ),
            array(
                'methods'             => \WP_REST_Server::CREATABLE,
                'callback'            => array( $this, 'create_item' ),
                'permission_callback' => array( $this, 'create_item_permissions_check' ),
                'args'               => $this->get_create_params(),
            ),
        ) );
        
        register_rest_route( $this->namespace, '/' . $this->rest_base . '/stats', array(
            'methods'             => \WP_REST_Server::READABLE,
            'callback'            => array( $this, 'get_stats' ),
            'permission_callback' => array( $this, 'get_items_permissions_check' ),
        ) );
        
        register_rest_route( $this->namespace, '/' . $this->rest_base . '/(?P<id>\d+)', array(
            array(
                'methods'             => \WP_REST_Server::READABLE,
                'callback'            => array( $this, 'get_item' ),
                'permission_callback' => array( $this, 'get_item_permissions_check' ),
                'args'               => array(
                    'id' => array(
                        'description' => __( 'Unique identifier for the invoice.', 'agency-os-ai' ),
                        'type'        => 'integer',
                    ),
                ),
            ),
            array(
                'methods'             => \WP_REST_Server::EDITABLE,
                'callback'            => array( $this, 'update_item' ),
                'permission_callback' => array( $this, 'update_item_permissions_check' ),
                'args'               => $this->get_update_params(),
            ),
            array(
                'methods'             => \WP_REST_Server::DELETABLE,
                'callback'            => array( $this, 'delete_item' ),
                'permission_callback' => array( $this, 'delete_item_permissions_check' ),
                'args'               => array(
                    'force' => array(
                        'type'        => 'boolean',
                        'default'     => false,
                    ),
                ),
            ),
        ) );
        
        register_rest_route( $this->namespace, '/' . $this->rest_base . '/(?P<id>\d+)/mark-paid', array(
            'methods'             => \WP_REST_Server::CREATABLE,
            'callback'            => array( $this, 'mark_as_paid' ),
            'permission_callback' => array( $this, 'update_item_permissions_check' ),
            'args'               => array(
                'paid_date' => array(
                    'description' => __( 'Date payment was received.', 'agency-os-ai' ),
                    'type'        => 'string',
                ),
            ),
        ) );
        
        register_rest_route( $this->namespace, '/' . $this->rest_base . '/(?P<id>\d+)/line-items', array(
            'methods'             => \WP_REST_Server::READABLE,
            'callback'            => array( $this, 'get_line_items' ),
            'permission_callback' => array( $this, 'get_item_permissions_check' ),
        ) );

        register_rest_route( $this->namespace, '/' . $this->rest_base . '/(?P<id>\d+)/send', array(
            'methods'             => \WP_REST_Server::CREATABLE,
            'callback'            => array( $this, 'send_invoice' ),
            'permission_callback' => array( $this, 'update_item_permissions_check' ),
        ) );

        register_rest_route( $this->namespace, '/' . $this->rest_base . '/(?P<id>\d+)/pdf', array(
            'methods'             => \WP_REST_Server::READABLE,
            'callback'            => array( $this, 'render_invoice_pdf_view' ),
            'permission_callback' => array( $this, 'get_item_permissions_check' ),
        ) );
    }
    
    public function get_items_permissions_check( $request ) {
        return current_user_can( 'aosai_manage_projects' );
    }
    
    public function get_item_permissions_check( $request ) {
        return current_user_can( 'aosai_manage_projects' ) || current_user_can( 'aosai_access_portal' );
    }
    
    public function create_item_permissions_check( $request ) {
        return current_user_can( 'aosai_manage_projects' );
    }
    
    public function update_item_permissions_check( $request ) {
        return current_user_can( 'aosai_manage_projects' );
    }
    
    public function delete_item_permissions_check( $request ) {
        return current_user_can( 'aosai_manage_projects' );
    }
    
    public function get_items( $request ) {
        $invoice = AOSAI_Invoice::get_instance();
        
        $args = array(
            'page'       => (int) $request->get_param( 'page' ),
            'per_page'   => (int) $request->get_param( 'per_page' ),
            'status'     => sanitize_key( $request->get_param( 'status' ) ),
            'client_id'  => (int) $request->get_param( 'client_id' ),
            'project_id' => (int) $request->get_param( 'project_id' ),
            'search'     => sanitize_text_field( $request->get_param( 'search' ) ),
            'orderby'    => sanitize_sql_orderby( $request->get_param( 'orderby' ) ?: 'created_at' ),
            'order'      => in_array( strtoupper( $request->get_param( 'order' ) ?: 'DESC' ), array( 'ASC', 'DESC' ), true ) ? strtoupper( $request->get_param( 'order' ) ) : 'DESC',
        );
        
        $invoices = $invoice->get_all( $args );
        
        return new \WP_REST_Response( $invoices, 200 );
    }
    
    public function get_stats( $request ) {
        $invoice = AOSAI_Invoice::get_instance();
        $stats = $invoice->get_stats();
        
        return new \WP_REST_Response( $stats, 200 );
    }
    
    public function get_item( $request ) {
        $invoice = AOSAI_Invoice::get_instance();
        $item = $invoice->get( (int) $request->get_param( 'id' ) );
        
        if ( ! $item ) {
            return new \WP_Error( 'rest_invoice_invalid_id', __( 'Invalid invoice ID.', 'agency-os-ai' ), array( 'status' => 404 ) );
        }
        
        return new \WP_REST_Response( $item, 200 );
    }
    
    public function create_item( $request ) {
        $invoice = AOSAI_Invoice::get_instance();
        
        $data = $this->normalize_invoice_payload( (array) $request->get_json_params() );
        
        if ( ! empty( $data['line_items'] ) && isset( $data['tax_rate'] ) ) {
            $totals = $invoice->calculate_totals( $data['line_items'], floatval( $data['tax_rate'] ) );
            $data['amount'] = $totals['subtotal'];
            $data['tax_amount'] = $totals['tax_amount'];
            $data['total_amount'] = $totals['total'];
        }
        
        $result = $invoice->create( $data );
        
        if ( is_wp_error( $result ) ) {
            return $result;
        }
        
        $item = $invoice->get( $result );
        
        return new \WP_REST_Response( $item, 201 );
    }
    
    public function update_item( $request ) {
        $invoice = AOSAI_Invoice::get_instance();
        
        $data = $this->normalize_invoice_payload( (array) $request->get_json_params() );
        
        if ( ! empty( $data['line_items'] ) && isset( $data['tax_rate'] ) ) {
            $totals = $invoice->calculate_totals( $data['line_items'], floatval( $data['tax_rate'] ) );
            $data['amount'] = $totals['subtotal'];
            $data['tax_amount'] = $totals['tax_amount'];
            $data['total_amount'] = $totals['total'];
        }
        
        $result = $invoice->update( (int) $request->get_param( 'id' ), $data );
        
        if ( is_wp_error( $result ) ) {
            return $result;
        }
        
        $item = $invoice->get( (int) $request->get_param( 'id' ) );
        
        return new \WP_REST_Response( $item, 200 );
    }
    
    public function delete_item( $request ) {
        $invoice = AOSAI_Invoice::get_instance();
        
        $result = $invoice->delete( (int) $request->get_param( 'id' ) );
        
        if ( ! $result ) {
            return new \WP_Error( 'rest_cannot_delete', __( 'Cannot delete this invoice.', 'agency-os-ai' ), array( 'status' => 500 ) );
        }
        
        return new \WP_REST_Response( array( 'deleted' => true ), 200 );
    }
    
    public function mark_as_paid( $request ) {
        $invoice = AOSAI_Invoice::get_instance();
        
        $paid_date = $request->get_param( 'paid_date' );
        $result = $invoice->mark_as_paid( (int) $request->get_param( 'id' ), $paid_date );
        
        if ( is_wp_error( $result ) ) {
            return $result;
        }
        
        $item = $invoice->get( (int) $request->get_param( 'id' ) );
        
        return new \WP_REST_Response( $item, 200 );
    }
    
    public function get_line_items( $request ) {
        $invoice = AOSAI_Invoice::get_instance();
        $items = $invoice->get_line_items( (int) $request->get_param( 'id' ) );
        
        return new \WP_REST_Response( $items, 200 );
    }

    public function send_invoice( $request ) {
        $invoice = AOSAI_Invoice::get_instance()->get( (int) $request->get_param( 'id' ) );

        if ( ! $invoice ) {
            return new \WP_Error( 'rest_invoice_invalid_id', __( 'Invalid invoice ID.', 'agency-os-ai' ), array( 'status' => 404 ) );
        }

        $client_email = sanitize_email( (string) ( $invoice['client']['email'] ?? '' ) );
        if ( '' === $client_email ) {
            return new \WP_Error( 'rest_invoice_missing_email', __( 'This invoice does not have a client email address.', 'agency-os-ai' ), array( 'status' => 400 ) );
        }

        $subject = sprintf( __( 'Invoice %s from %s', 'agency-os-ai' ), $invoice['invoice_number'], get_option( 'aosai_company_name', get_bloginfo( 'name' ) ) );
        $body    = sprintf(
            __( "Hello,\n\nYour invoice %1\$s is ready.\nTotal: %2\$s\nDue date: %3\$s\n\nYou can review it here:\n%4\$s", 'agency-os-ai' ),
            $invoice['invoice_number'],
            wp_strip_all_tags( $this->format_invoice_total( $invoice ) ),
            ! empty( $invoice['due_date'] ) ? $invoice['due_date'] : __( 'No due date set', 'agency-os-ai' ),
            rest_url( $this->namespace . '/' . $this->rest_base . '/' . (int) $invoice['id'] . '/pdf' )
        );

        $headers = array(
            'Content-Type: text/plain; charset=UTF-8',
            'From: ' . get_option( 'aosai_company_name', get_bloginfo( 'name' ) ) . ' <' . sanitize_email( (string) get_option( 'aosai_sender_email', get_option( 'admin_email' ) ) ) . '>',
        );

        $sent = wp_mail( $client_email, $subject, $body, $headers );
        if ( ! $sent ) {
            return new \WP_Error( 'rest_invoice_send_failed', __( 'The invoice email could not be sent.', 'agency-os-ai' ), array( 'status' => 500 ) );
        }

        AOSAI_Invoice::get_instance()->update( (int) $invoice['id'], array( 'status' => 'pending' ) );

        return new \WP_REST_Response(
            array(
                'success' => true,
                'message' => __( 'Invoice sent successfully.', 'agency-os-ai' ),
            ),
            200
        );
    }

    public function render_invoice_pdf_view( $request ) {
        $invoice = AOSAI_Invoice::get_instance()->get( (int) $request->get_param( 'id' ) );

        if ( ! $invoice ) {
            return new \WP_Error( 'rest_invoice_invalid_id', __( 'Invalid invoice ID.', 'agency-os-ai' ), array( 'status' => 404 ) );
        }

        $line_items = is_array( $invoice['line_items'] ?? null ) ? $invoice['line_items'] : array();
        $company    = get_option( 'aosai_company_name', get_bloginfo( 'name' ) );
        $client     = (string) ( $invoice['client_name'] ?? '' );
        $currency   = (string) ( $invoice['currency'] ?? 'USD' );
        $subtotal   = $this->format_money( (float) ( $invoice['subtotal'] ?? 0 ), $currency );
        $tax        = $this->format_money( (float) ( $invoice['tax_amount'] ?? 0 ), $currency );
        $total      = $this->format_money( (float) ( $invoice['total'] ?? 0 ), $currency );

        ob_start();
        ?>
<!DOCTYPE html>
<html>
<head>
    <meta charset="utf-8">
    <title><?php echo esc_html( $invoice['invoice_number'] ); ?></title>
    <style>
        body { font-family: Arial, sans-serif; margin: 40px; color: #0f172a; }
        h1, h2, h3, p { margin: 0 0 12px; }
        .meta, .totals { margin-top: 24px; }
        table { width: 100%; border-collapse: collapse; margin-top: 24px; }
        th, td { border-bottom: 1px solid #e2e8f0; padding: 10px 8px; text-align: left; }
        .summary { margin-top: 30px; margin-left: auto; width: 320px; }
        .summary td { border: 0; padding: 6px 8px; }
        .summary tr:last-child td { font-weight: 700; border-top: 2px solid #0f172a; padding-top: 10px; }
    </style>
</head>
<body>
    <h1><?php echo esc_html( $company ); ?></h1>
    <h2><?php echo esc_html( sprintf( __( 'Invoice %s', 'agency-os-ai' ), $invoice['invoice_number'] ) ); ?></h2>
    <div class="meta">
        <p><strong><?php esc_html_e( 'Client:', 'agency-os-ai' ); ?></strong> <?php echo esc_html( $client ?: __( 'Unassigned client', 'agency-os-ai' ) ); ?></p>
        <p><strong><?php esc_html_e( 'Issue date:', 'agency-os-ai' ); ?></strong> <?php echo esc_html( (string) ( $invoice['issue_date'] ?? '' ) ); ?></p>
        <p><strong><?php esc_html_e( 'Due date:', 'agency-os-ai' ); ?></strong> <?php echo esc_html( (string) ( $invoice['due_date'] ?? '' ) ); ?></p>
        <p><strong><?php esc_html_e( 'Status:', 'agency-os-ai' ); ?></strong> <?php echo esc_html( ucfirst( (string) ( $invoice['status_label'] ?? $invoice['status'] ?? 'draft' ) ) ); ?></p>
    </div>
    <table>
        <thead>
            <tr>
                <th><?php esc_html_e( 'Description', 'agency-os-ai' ); ?></th>
                <th><?php esc_html_e( 'Quantity', 'agency-os-ai' ); ?></th>
                <th><?php esc_html_e( 'Rate', 'agency-os-ai' ); ?></th>
                <th><?php esc_html_e( 'Amount', 'agency-os-ai' ); ?></th>
            </tr>
        </thead>
        <tbody>
            <?php foreach ( $line_items as $item ) : ?>
                <tr>
                    <td><?php echo esc_html( (string) ( $item['description'] ?? '' ) ); ?></td>
                    <td><?php echo esc_html( (string) ( $item['quantity'] ?? '' ) ); ?></td>
                    <td><?php echo esc_html( $this->format_money( (float) ( $item['unit_price'] ?? 0 ), $currency ) ); ?></td>
                    <td><?php echo esc_html( $this->format_money( (float) ( $item['amount'] ?? 0 ), $currency ) ); ?></td>
                </tr>
            <?php endforeach; ?>
        </tbody>
    </table>
    <table class="summary">
        <tr><td><?php esc_html_e( 'Subtotal', 'agency-os-ai' ); ?></td><td><?php echo esc_html( $subtotal ); ?></td></tr>
        <tr><td><?php echo esc_html( sprintf( __( 'Tax (%s%%)', 'agency-os-ai' ), (string) ( $invoice['tax_rate'] ?? 0 ) ) ); ?></td><td><?php echo esc_html( $tax ); ?></td></tr>
        <tr><td><?php esc_html_e( 'Total', 'agency-os-ai' ); ?></td><td><?php echo esc_html( $total ); ?></td></tr>
    </table>
    <?php if ( ! empty( $invoice['notes'] ) ) : ?>
        <div class="totals">
            <h3><?php esc_html_e( 'Notes', 'agency-os-ai' ); ?></h3>
            <p><?php echo nl2br( esc_html( (string) $invoice['notes'] ) ); ?></p>
        </div>
    <?php endif; ?>
</body>
</html>
        <?php

        $html = (string) ob_get_clean();

        return new \WP_REST_Response( $html, 200, array( 'Content-Type' => 'text/html; charset=' . get_bloginfo( 'charset' ) ) );
    }
    
    public function get_collection_params() {
        return array(
            'page'       => array(
                'description' => __( 'Current page of the result set.', 'agency-os-ai' ),
                'type'        => 'integer',
                'default'     => 1,
            ),
            'per_page'   => array(
                'description' => __( 'Maximum number of items to return.', 'agency-os-ai' ),
                'type'        => 'integer',
                'default'     => 20,
            ),
            'status'     => array(
                'description' => __( 'Filter by status.', 'agency-os-ai' ),
                'type'        => 'string',
            ),
            'client_id'  => array(
                'description' => __( 'Filter by client.', 'agency-os-ai' ),
                'type'        => 'integer',
            ),
            'project_id' => array(
                'description' => __( 'Filter by project.', 'agency-os-ai' ),
                'type'        => 'integer',
            ),
            'search'     => array(
                'description' => __( 'Search term.', 'agency-os-ai' ),
                'type'        => 'string',
            ),
            'orderby'    => array(
                'description' => __( 'Sort collection by field.', 'agency-os-ai' ),
                'type'        => 'string',
                'default'     => 'created_at',
            ),
            'order'      => array(
                'description' => __( 'Order sort direction.', 'agency-os-ai' ),
                'type'        => 'string',
                'default'     => 'DESC',
            ),
        );
    }

    private function get_create_params(): array {
        return array(
            'client_id'   => array( 'type' => 'integer', 'required' => true ),
            'project_id'  => array( 'type' => 'integer' ),
            'title'       => array( 'type' => 'string' ),
            'description' => array( 'type' => 'string' ),
            'items'       => array( 'type' => 'array' ),
            'line_items'  => array( 'type' => 'array' ),
            'tax_rate'    => array( 'type' => 'number' ),
            'currency'    => array( 'type' => 'string' ),
            'status'      => array( 'type' => 'string' ),
            'issue_date'  => array( 'type' => 'string' ),
            'due_date'    => array( 'type' => 'string' ),
            'paid_date'   => array( 'type' => 'string' ),
            'notes'       => array( 'type' => 'string' ),
        );
    }

    private function get_update_params(): array {
        return $this->get_create_params();
    }

    private function normalize_invoice_payload( array $data ): array {
        if ( ! empty( $data['items'] ) && empty( $data['line_items'] ) ) {
            $data['line_items'] = $data['items'];
        }

        if ( ! empty( $data['line_items'] ) && is_array( $data['line_items'] ) ) {
            foreach ( $data['line_items'] as &$item ) {
                if ( ! is_array( $item ) ) {
                    continue;
                }

                if ( isset( $item['rate'] ) && ! isset( $item['unit_price'] ) ) {
                    $item['unit_price'] = $item['rate'];
                }
            }
            unset( $item );
        }

        if ( isset( $data['status'] ) && 'sent' === $data['status'] ) {
            $data['status'] = 'pending';
        }

        if ( empty( $data['title'] ) ) {
            $client_name = '';

            if ( ! empty( $data['client_id'] ) ) {
                $client = AOSAI_Client::get_instance()->get( (int) $data['client_id'] );
                if ( $client ) {
                    $client_name = (string) ( $client['company_name'] ?: $client['name'] );
                }
            }

            $data['title'] = $client_name
                ? sprintf( __( 'Invoice for %s', 'agency-os-ai' ), $client_name )
                : __( 'Invoice', 'agency-os-ai' );
        }

        return $data;
    }

    private function format_invoice_total( array $invoice ): string {
        return $this->format_money( (float) ( $invoice['total'] ?? $invoice['total_amount'] ?? 0 ), (string) ( $invoice['currency'] ?? 'USD' ) );
    }

    private function format_money( float $amount, string $currency ): string {
        if ( class_exists( 'NumberFormatter' ) ) {
            $formatter = new \NumberFormatter( get_locale(), \NumberFormatter::CURRENCY );
            $result = $formatter->formatCurrency( $amount, $currency );
            if ( false !== $result ) {
                return $result;
            }
        }

        return $currency . ' ' . number_format_i18n( $amount, 2 );
    }
}
