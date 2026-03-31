<?php
if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

class AOSAI_Invoice {
    use AOSAI_Singleton;
    
    private function __construct() {}
    
    public function get_table(): string {
        global $wpdb;
        return esc_sql( $wpdb->prefix . 'aosai_invoices' );
    }
    
    public function get_all( array $args = array() ): array {
        global $wpdb;
        $table = $this->get_table();
        
        $defaults = array(
            'page'       => 1,
            'per_page'   => 20,
            'status'     => '',
            'client_id'  => '',
            'project_id' => '',
            'search'     => '',
            'orderby'    => 'created_at',
            'order'      => 'DESC',
        );
        $args = wp_parse_args( $args, $defaults );
        
        $per_page = max( 1, (int) $args['per_page'] );
        $page     = max( 1, (int) $args['page'] );
        $offset   = ( $page - 1 ) * $per_page;
        $params   = array();
        $sql      = 'SELECT * FROM ' . $table . ' WHERE 1=1';
        
        if ( ! empty( $args['status'] ) ) {
            $sql .= ' AND status = %s';
            $params[] = sanitize_key( $args['status'] );
        }
        
        if ( ! empty( $args['client_id'] ) ) {
            $sql .= ' AND client_id = %d';
            $params[] = absint( $args['client_id'] );
        }
        
        if ( ! empty( $args['project_id'] ) ) {
            $sql .= ' AND project_id = %d';
            $params[] = absint( $args['project_id'] );
        }
        
        if ( ! empty( $args['search'] ) ) {
            $search = '%' . $wpdb->esc_like( sanitize_text_field( $args['search'] ) ) . '%';
            $sql .= ' AND (invoice_number LIKE %s OR title LIKE %s)';
            $params[] = $search;
            $params[] = $search;
        }

        $sql .= ' ORDER BY ' . $this->get_order_clause( (string) $args['orderby'], (string) $args['order'] );
        $sql .= ' LIMIT %d OFFSET %d';
        $params[] = $per_page;
        $params[] = $offset;
        $sql = $wpdb->prepare( $sql, $params );
        
        $invoices = $wpdb->get_results( $sql, ARRAY_A );
        
        foreach ( $invoices as &$invoice ) {
            $invoice = $this->enrich( $invoice );
        }
        
        return $invoices;
    }
    
    public function get( int $id ): ?array {
        global $wpdb;
        $table = $this->get_table();
        
        $invoice = $wpdb->get_row(
            $wpdb->prepare( 'SELECT * FROM ' . $table . ' WHERE id = %d', $id ),
            ARRAY_A
        );
        
        if ( ! $invoice ) {
            return null;
        }
        
        return $this->enrich( $invoice );
    }
    
    public function get_by_number( string $invoice_number ): ?array {
        global $wpdb;
        $table = $this->get_table();
        
        $invoice = $wpdb->get_row(
            $wpdb->prepare( 'SELECT * FROM ' . $table . ' WHERE invoice_number = %s', sanitize_text_field( $invoice_number ) ),
            ARRAY_A
        );
        
        if ( ! $invoice ) {
            return null;
        }
        
        return $this->enrich( $invoice );
    }
    
    public function create( array $data ): int|\WP_Error {
        global $wpdb;
        $table = $this->get_table();
        
        $sanitized = $this->sanitize_input( $data );
        
        if ( empty( $sanitized['title'] ) ) {
            return new \WP_Error( 'missing_title', esc_html__( 'Invoice title is required.', 'agency-os-ai' ) );
        }
        
        $invoice_number = $this->generate_invoice_number();
        
        $result = $wpdb->insert(
            $table,
            array(
                'invoice_number' => $invoice_number,
                'client_id'      => $sanitized['client_id'] ?? null,
                'project_id'     => $sanitized['project_id'] ?? null,
                'title'         => $sanitized['title'],
                'description'   => $sanitized['description'] ?? '',
                'amount'        => $sanitized['amount'] ?? 0,
                'tax_rate'      => $sanitized['tax_rate'] ?? 0,
                'tax_amount'    => $sanitized['tax_amount'] ?? 0,
                'total_amount' => $sanitized['total_amount'] ?? $sanitized['amount'] ?? 0,
                'currency'      => $sanitized['currency'] ?? 'USD',
                'status'        => $sanitized['status'] ?? 'draft',
                'issue_date'    => $sanitized['issue_date'] ?? current_time( 'Y-m-d' ),
                'due_date'      => $sanitized['due_date'] ?? null,
                'paid_date'     => $sanitized['paid_date'] ?? null,
                'notes'         => $sanitized['notes'] ?? '',
                'created_by'    => get_current_user_id(),
            ),
            array( '%s', '%d', '%d', '%s', '%s', '%f', '%f', '%f', '%f', '%s', '%s', '%s', '%s', '%s', '%s', '%d' )
        );
        
        if ( false === $result ) {
            return new \WP_Error( 'db_error', esc_html__( 'Failed to create invoice.', 'agency-os-ai' ) );
        }
        
        $invoice_id = $wpdb->insert_id;
        
        if ( ! empty( $data['line_items'] ) ) {
            $this->save_line_items( $invoice_id, $data['line_items'] );
        }
        
        $invoice = $this->get( $invoice_id );
        do_action( 'aosai_invoice_created', $invoice_id, $invoice );
        
        return $invoice_id;
    }
    
    public function update( int $id, array $data ): bool|\WP_Error {
        global $wpdb;
        $table = $this->get_table();
        $invoice = $this->get( $id );
        
        if ( ! $invoice ) {
            return new \WP_Error( 'not_found', esc_html__( 'Invoice not found.', 'agency-os-ai' ) );
        }
        
        $sanitized = $this->sanitize_input( $data );
        
        if ( empty( $sanitized ) ) {
            return true;
        }
        
        $sanitized['updated_at'] = current_time( 'mysql' );
        
        $format = array();
        foreach ( $sanitized as $key => $value ) {
            if ( in_array( $key, array( 'amount', 'tax_rate', 'tax_amount', 'total_amount' ), true ) ) {
                $format[] = '%f';
            } elseif ( in_array( $key, array( 'client_id', 'project_id', 'created_by' ), true ) ) {
                $format[] = '%d';
            } else {
                $format[] = '%s';
            }
        }
        
        $result = $wpdb->update(
            $table,
            $sanitized,
            array( 'id' => $id ),
            $format,
            array( '%d' )
        );
        
        if ( false === $result ) {
            return new \WP_Error( 'db_error', esc_html__( 'Failed to update invoice.', 'agency-os-ai' ) );
        }
        
        if ( isset( $data['line_items'] ) ) {
            $this->save_line_items( $id, $data['line_items'] );
        }
        
        $updated_invoice = $this->get( $id );
        do_action( 'aosai_invoice_updated', $id, $invoice, $updated_invoice );
        
        return true;
    }
    
    public function delete( int $id ): bool {
        global $wpdb;
        $table = $this->get_table();
        
        $invoice = $this->get( $id );
        if ( ! $invoice ) {
            return false;
        }
        
        $wpdb->delete( esc_sql( $wpdb->prefix . 'aosai_invoice_items' ), array( 'invoice_id' => $id ), array( '%d' ) );
        
        $result = $wpdb->delete( $table, array( 'id' => $id ), array( '%d' ) );
        
        if ( $result !== false ) {
            do_action( 'aosai_invoice_deleted', $id, $invoice );
        }
        
        return $result !== false;
    }
    
    public function mark_as_paid( int $id, ?string $paid_date = null ): bool|\WP_Error {
        global $wpdb;
        $table = $this->get_table();
        
        $invoice = $this->get( $id );
        if ( ! $invoice ) {
            return new \WP_Error( 'not_found', esc_html__( 'Invoice not found.', 'agency-os-ai' ) );
        }
        
        $result = $wpdb->update(
            $table,
            array(
                'status'     => 'paid',
                'paid_date'  => $paid_date ?? current_time( 'Y-m-d' ),
                'updated_at' => current_time( 'mysql' ),
            ),
            array( 'id' => $id ),
            array( '%s', '%s', '%s' ),
            array( '%d' )
        );
        
        if ( false === $result ) {
            return new \WP_Error( 'db_error', esc_html__( 'Failed to update invoice.', 'agency-os-ai' ) );
        }
        
        do_action( 'aosai_invoice_paid', $id, $this->get( $id ) );
        
        return true;
    }
    
    public function get_line_items( int $invoice_id ): array {
        global $wpdb;
        $table = esc_sql( $wpdb->prefix . 'aosai_invoice_items' );
        
        return $wpdb->get_results(
            $wpdb->prepare( 'SELECT * FROM ' . $table . ' WHERE invoice_id = %d ORDER BY sort_order ASC, id ASC', $invoice_id ),
            ARRAY_A
        );
    }
    
    public function save_line_items( int $invoice_id, array $items ): void {
        global $wpdb;
        $table = esc_sql( $wpdb->prefix . 'aosai_invoice_items' );
        
        $wpdb->delete( $table, array( 'invoice_id' => $invoice_id ), array( '%d' ) );
        
        foreach ( $items as $index => $item ) {
            if ( empty( $item['description'] ) ) {
                continue;
            }
            
            $wpdb->insert(
                $table,
                array(
                    'invoice_id'   => $invoice_id,
                    'description'   => sanitize_text_field( $item['description'] ),
                    'quantity'      => floatval( $item['quantity'] ?? 1 ),
                    'unit_price'    => floatval( $item['unit_price'] ?? 0 ),
                    'amount'        => floatval( $item['amount'] ?? ( $item['quantity'] * $item['unit_price'] ) ),
                    'sort_order'    => $index,
                ),
                array( '%d', '%s', '%f', '%f', '%f', '%d' )
            );
        }
    }
    
    public function get_stats(): array {
        global $wpdb;
        $table = $this->get_table();
        
        $stats = $wpdb->get_row(
            'SELECT 
                COUNT(*) as total_invoices,
                SUM(CASE WHEN status = \'paid\' THEN total_amount ELSE 0 END) as total_paid,
                SUM(CASE WHEN status = \'pending\' THEN total_amount ELSE 0 END) as total_pending,
                SUM(CASE WHEN status = \'overdue\' THEN total_amount ELSE 0 END) as total_overdue,
                SUM(CASE WHEN status = \'draft\' THEN total_amount ELSE 0 END) as total_draft
            FROM ' . $table,
            ARRAY_A
        );
        
        return $stats;
    }
    
    public function calculate_totals( array $items, float $tax_rate = 0 ): array {
        $subtotal = 0;
        
        foreach ( $items as $item ) {
            $quantity = floatval( $item['quantity'] ?? 1 );
            $unit_price = floatval( $item['unit_price'] ?? 0 );
            $subtotal += $quantity * $unit_price;
        }
        
        $tax_amount = $subtotal * ( $tax_rate / 100 );
        $total = $subtotal + $tax_amount;
        
        return array(
            'subtotal'   => round( $subtotal, 2 ),
            'tax_rate'   => $tax_rate,
            'tax_amount' => round( $tax_amount, 2 ),
            'total'      => round( $total, 2 ),
        );
    }
    
    public function enrich( array $invoice ): array {
        $id = (int) $invoice['id'];
        
        $invoice['id'] = $id;
        $invoice['line_items'] = $this->get_line_items( $id );
        $invoice['items'] = $invoice['line_items'];
        $invoice['subtotal'] = (float) ( $invoice['amount'] ?? 0 );
        $invoice['total'] = (float) ( $invoice['total_amount'] ?? 0 );

        if ( ! empty( $invoice['client_id'] ) ) {
            $client = AOSAI_Client::get_instance()->get( (int) $invoice['client_id'] );
            $invoice['client_name'] = $client ? ( $client['company_name'] ?: $client['name'] ) : '';
            $invoice['client'] = $client;
        }
        
        if ( ! empty( $invoice['project_id'] ) ) {
            $project = AOSAI_Project::get_instance()->get( (int) $invoice['project_id'] );
            $invoice['project_name'] = $project ? $project['name'] : '';
            $invoice['project'] = $project;
        }
        
        if ( ! empty( $invoice['created_by'] ) ) {
            $creator = get_userdata( (int) $invoice['created_by'] );
            $invoice['created_by_name'] = $creator ? $creator->display_name : '';
        }
        
        $invoice['is_overdue'] = false;
        if ( ! empty( $invoice['due_date'] ) && $invoice['status'] === 'pending' ) {
            $invoice['is_overdue'] = strtotime( $invoice['due_date'] ) < current_time( 'timestamp' );
        }

        if ( 'pending' === $invoice['status'] ) {
            $invoice['status_label'] = 'sent';
        } else {
            $invoice['status_label'] = (string) $invoice['status'];
        }
        
        return $invoice;
    }
    
    private function generate_invoice_number(): string {
        global $wpdb;
        $table = $this->get_table();
        
        $year = gmdate( 'Y' );
        $prefix = 'INV-' . $year . '-';
        
        $last_invoice_number = (string) $wpdb->get_var(
            $wpdb->prepare(
                'SELECT invoice_number FROM ' . $table . ' WHERE invoice_number LIKE %s ORDER BY id DESC LIMIT 1',
                $prefix . '%'
            )
        );
        
        if ( '' !== $last_invoice_number ) {
            $num = (int) str_replace( $prefix, '', $last_invoice_number );
            $num++;
        } else {
            $num = 1;
        }
        
        return $prefix . str_pad( (string) $num, 4, '0', STR_PAD_LEFT );
    }

    private function get_order_clause( string $orderby, string $order ): string {
        $allowed = array(
            'created_at'     => 'created_at',
            'updated_at'     => 'updated_at',
            'invoice_number' => 'invoice_number',
            'title'          => 'title',
            'status'         => 'status',
            'issue_date'     => 'issue_date',
            'due_date'       => 'due_date',
            'total_amount'   => 'total_amount',
        );

        $column    = $allowed[ sanitize_key( $orderby ) ] ?? 'created_at';
        $direction = 'ASC' === strtoupper( $order ) ? 'ASC' : 'DESC';

        return $column . ' ' . $direction;
    }
    
    private function sanitize_input( array $input ): array {
        $sanitized = array();
        
        $allowed_fields = array(
            'client_id', 'project_id', 'title', 'description',
            'amount', 'tax_rate', 'tax_amount', 'total_amount',
            'currency', 'status', 'issue_date', 'due_date', 'paid_date', 'notes'
        );
        
        foreach ( $input as $key => $value ) {
            if ( ! in_array( $key, $allowed_fields, true ) ) {
                continue;
            }
            
            switch ( $key ) {
                case 'client_id':
                case 'project_id':
                    $sanitized[ $key ] = $value ? absint( $value ) : null;
                    break;
                case 'amount':
                case 'tax_rate':
                case 'tax_amount':
                case 'total_amount':
                    $sanitized[ $key ] = floatval( $value );
                    break;
                case 'status':
                    $allowed = array( 'draft', 'pending', 'paid', 'overdue', 'cancelled', 'refunded' );
                    $sanitized[ $key ] = in_array( $value, $allowed, true ) ? $value : 'draft';
                    break;
                case 'issue_date':
                case 'due_date':
                case 'paid_date':
                    if ( ! empty( $value ) && preg_match( '/^\d{4}-\d{2}-\d{2}$/', $value ) ) {
                        $sanitized[ $key ] = $value;
                    }
                    break;
                case 'notes':
                case 'description':
                    $sanitized[ $key ] = sanitize_textarea_field( (string) $value );
                    break;
                default:
                    $sanitized[ $key ] = sanitize_text_field( (string) $value );
            }
        }
        
        return $sanitized;
    }
}
