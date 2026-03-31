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
}
