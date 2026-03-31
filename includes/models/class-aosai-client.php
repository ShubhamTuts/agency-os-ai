<?php
if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

class AOSAI_Client {
    use AOSAI_Singleton;
    
    private function __construct() {}
    
    public function get_table(): string {
        global $wpdb;
        return esc_sql( $wpdb->prefix . 'aosai_clients' );
    }
    
    public function get_all( array $args = array() ): array {
        global $wpdb;
        $table = $this->get_table();
        
        $defaults = array(
            'page'     => 1,
            'per_page' => 20,
            'status'   => '',
            'search'   => '',
            'orderby'  => 'created_at',
            'order'    => 'DESC',
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
        
        if ( ! empty( $args['search'] ) ) {
            $search = '%' . $wpdb->esc_like( sanitize_text_field( $args['search'] ) ) . '%';
            $sql .= ' AND (name LIKE %s OR email LIKE %s OR company_name LIKE %s)';
            $params[] = $search;
            $params[] = $search;
            $params[] = $search;
        }

        $sql .= ' ORDER BY ' . $this->get_order_clause( (string) $args['orderby'], (string) $args['order'] );
        $sql .= ' LIMIT %d OFFSET %d';
        $params[] = $per_page;
        $params[] = $offset;
        $sql = $wpdb->prepare( $sql, $params );
        
        $clients = $wpdb->get_results( $sql, ARRAY_A );
        
        foreach ( $clients as &$client ) {
            $client = $this->enrich( $client );
        }
        
        return $clients;
    }
    
    public function get( int $id ): ?array {
        global $wpdb;
        $table = $this->get_table();
        
        $client = $wpdb->get_row(
            $wpdb->prepare( 'SELECT * FROM ' . $table . ' WHERE id = %d', $id ),
            ARRAY_A
        );
        
        if ( ! $client ) {
            return null;
        }
        
        return $this->enrich( $client );
    }
    
    public function get_by_email( string $email ): ?array {
        global $wpdb;
        $table = $this->get_table();
        
        $client = $wpdb->get_row(
            $wpdb->prepare( 'SELECT * FROM ' . $table . ' WHERE email = %s', sanitize_email( $email ) ),
            ARRAY_A
        );
        
        if ( ! $client ) {
            return null;
        }
        
        return $this->enrich( $client );
    }
    
    public function create( array $data ): int|\WP_Error {
        global $wpdb;
        $table = $this->get_table();
        
        $sanitized = $this->sanitize_input( $data );
        
        if ( empty( $sanitized['name'] ) && empty( $sanitized['company_name'] ) ) {
            return new \WP_Error( 'missing_name', esc_html__( 'Client name or company name is required.', 'agency-os-ai' ) );
        }
        
        if ( ! empty( $sanitized['email'] ) && $this->get_by_email( $sanitized['email'] ) ) {
            return new \WP_Error( 'duplicate_email', esc_html__( 'A client with this email already exists.', 'agency-os-ai' ) );
        }
        
        $result = $wpdb->insert(
            $table,
            array(
                'name'         => $sanitized['name'] ?? '',
                'company_name' => $sanitized['company_name'] ?? '',
                'email'        => $sanitized['email'] ?? '',
                'phone'        => $sanitized['phone'] ?? '',
                'website'      => $sanitized['website'] ?? '',
                'address'      => $sanitized['address'] ?? '',
                'city'         => $sanitized['city'] ?? '',
                'state'        => $sanitized['state'] ?? '',
                'country'      => $sanitized['country'] ?? '',
                'zip_code'     => $sanitized['zip_code'] ?? '',
                'tax_id'       => $sanitized['tax_id'] ?? '',
                'notes'        => $sanitized['notes'] ?? '',
                'status'       => $sanitized['status'] ?? 'active',
                'source'       => $sanitized['source'] ?? 'manual',
                'created_by'   => get_current_user_id(),
            ),
            array( '%s', '%s', '%s', '%s', '%s', '%s', '%s', '%s', '%s', '%s', '%s', '%s', '%s', '%s', '%d' )
        );
        
        if ( false === $result ) {
            return new \WP_Error( 'db_error', esc_html__( 'Failed to create client.', 'agency-os-ai' ) );
        }
        
        $client_id = $wpdb->insert_id;
        
        $client = $this->get( $client_id );
        do_action( 'aosai_client_created', $client_id, $client );
        
        return $client_id;
    }
    
    public function update( int $id, array $data ): bool|\WP_Error {
        global $wpdb;
        $table = $this->get_table();
        $client = $this->get( $id );
        
        if ( ! $client ) {
            return new \WP_Error( 'not_found', esc_html__( 'Client not found.', 'agency-os-ai' ) );
        }
        
        $sanitized = $this->sanitize_input( $data );
        
        if ( empty( $sanitized ) ) {
            return true;
        }
        
        $sanitized['updated_at'] = current_time( 'mysql' );
        
        $format = array();
        foreach ( $sanitized as $key => $value ) {
            $format[] = is_int( $value ) ? '%d' : '%s';
        }
        
        $result = $wpdb->update(
            $table,
            $sanitized,
            array( 'id' => $id ),
            $format,
            array( '%d' )
        );
        
        if ( false === $result ) {
            return new \WP_Error( 'db_error', esc_html__( 'Failed to update client.', 'agency-os-ai' ) );
        }
        
        $updated_client = $this->get( $id );
        do_action( 'aosai_client_updated', $id, $client, $updated_client );
        
        return true;
    }
    
    public function delete( int $id ): bool {
        global $wpdb;
        $table = $this->get_table();
        
        $client = $this->get( $id );
        if ( ! $client ) {
            return false;
        }
        
        $result = $wpdb->delete( $table, array( 'id' => $id ), array( '%d' ) );
        
        if ( $result !== false ) {
            $wpdb->delete( esc_sql( $wpdb->prefix . 'aosai_client_users' ), array( 'client_id' => $id ), array( '%d' ) );
            $wpdb->delete( esc_sql( $wpdb->prefix . 'aosai_client_projects' ), array( 'client_id' => $id ), array( '%d' ) );
            do_action( 'aosai_client_deleted', $id, $client );
        }
        
        return $result !== false;
    }
    
    public function get_client_users( int $client_id ): array {
        global $wpdb;
        $table       = esc_sql( $wpdb->prefix . 'aosai_client_users' );
        $users_table = esc_sql( $wpdb->users );
        
        $users = $wpdb->get_results(
            $wpdb->prepare(
                'SELECT cu.*, u.display_name, u.user_email, u.user_login
                FROM ' . $table . ' cu
                INNER JOIN ' . $users_table . ' u ON cu.user_id = u.ID
                WHERE cu.client_id = %d',
                $client_id
            ),
            ARRAY_A
        );
        
        foreach ( $users as &$user ) {
            $user['avatar_url'] = aosai_get_avatar_url( (int) $user['user_id'] );
        }
        
        return $users;
    }
    
    public function get_client_projects( int $client_id ): array {
        global $wpdb;
        $table          = esc_sql( $wpdb->prefix . 'aosai_client_projects' );
        $projects_table = esc_sql( $wpdb->prefix . 'aosai_projects' );
        
        $projects = $wpdb->get_results(
            $wpdb->prepare(
                'SELECT cp.*, p.title as project_name, p.status as project_status
                FROM ' . $table . ' cp
                INNER JOIN ' . $projects_table . ' p ON cp.project_id = p.id
                WHERE cp.client_id = %d',
                $client_id
            ),
            ARRAY_A
        );
        
        return $projects;
    }
    
    public function add_user( int $client_id, int $user_id, string $role = 'contact' ): bool {
        global $wpdb;
        $table = esc_sql( $wpdb->prefix . 'aosai_client_users' );
        
        $existing = $wpdb->get_row(
            $wpdb->prepare(
                'SELECT id FROM ' . $table . ' WHERE client_id = %d AND user_id = %d',
                $client_id,
                $user_id
            )
        );
        
        if ( $existing ) {
            $wpdb->update(
                $table,
                array( 'role' => $role ),
                array( 'client_id' => $client_id, 'user_id' => $user_id ),
                array( '%s' ),
                array( '%d', '%d' )
            );
        } else {
            $wpdb->insert(
                $table,
                array(
                    'client_id' => $client_id,
                    'user_id'   => $user_id,
                    'role'      => $role,
                ),
                array( '%d', '%d', '%s' )
            );
        }
        
        return true;
    }
    
    public function remove_user( int $client_id, int $user_id ): bool {
        global $wpdb;
        $table = esc_sql( $wpdb->prefix . 'aosai_client_users' );
        
        $result = $wpdb->delete(
            $table,
            array( 'client_id' => $client_id, 'user_id' => $user_id ),
            array( '%d', '%d' )
        );
        
        return $result !== false;
    }
    
    public function link_project( int $client_id, int $project_id ): bool {
        global $wpdb;
        $table = esc_sql( $wpdb->prefix . 'aosai_client_projects' );
        
        $existing = $wpdb->get_row(
            $wpdb->prepare(
                'SELECT id FROM ' . $table . ' WHERE client_id = %d AND project_id = %d',
                $client_id,
                $project_id
            )
        );
        
        if ( $existing ) {
            return true;
        }
        
        $wpdb->insert(
            $table,
            array(
                'client_id'  => $client_id,
                'project_id' => $project_id,
            ),
            array( '%d', '%d' )
        );
        
        return true;
    }
    
    public function unlink_project( int $client_id, int $project_id ): bool {
        global $wpdb;
        $table = esc_sql( $wpdb->prefix . 'aosai_client_projects' );
        
        $result = $wpdb->delete(
            $table,
            array( 'client_id' => $client_id, 'project_id' => $project_id ),
            array( '%d', '%d' )
        );
        
        return $result !== false;
    }
    
    public function get_stats( int $client_id ): array {
        global $wpdb;
        $client_projects_table = esc_sql( $wpdb->prefix . 'aosai_client_projects' );
        $client_users_table    = esc_sql( $wpdb->prefix . 'aosai_client_users' );
        
        $project_count = (int) $wpdb->get_var(
            $wpdb->prepare(
                'SELECT COUNT(*) FROM ' . $client_projects_table . ' WHERE client_id = %d',
                $client_id
            )
        );
        
        $user_count = (int) $wpdb->get_var(
            $wpdb->prepare(
                'SELECT COUNT(*) FROM ' . $client_users_table . ' WHERE client_id = %d',
                $client_id
            )
        );
        
        return array(
            'project_count' => $project_count,
            'user_count'    => $user_count,
        );
    }
    
    public function enrich( array $client ): array {
        $id = (int) $client['id'];
        $stats = $this->get_stats( $id );
        
        $client['id'] = $id;
        $client['company'] = (string) ( $client['company_name'] ?? '' );
        $client['postal_code'] = (string) ( $client['zip_code'] ?? '' );
        $client['vat_number'] = (string) ( $client['tax_id'] ?? '' );
        $client['stats'] = $stats;
        $client['project_count'] = (int) ( $stats['project_count'] ?? 0 );
        $client['user_count'] = (int) ( $stats['user_count'] ?? 0 );
        $client['users'] = $this->get_client_users( $id );
        $client['projects'] = $this->get_client_projects( $id );
        
        if ( ! empty( $client['created_by'] ) ) {
            $creator = get_userdata( (int) $client['created_by'] );
            $client['created_by_name'] = $creator ? $creator->display_name : '';
        }
        
        return $client;
    }

    private function get_order_clause( string $orderby, string $order ): string {
        $allowed = array(
            'name'         => 'name',
            'company_name' => 'company_name',
            'email'        => 'email',
            'status'       => 'status',
            'created_at'   => 'created_at',
            'updated_at'   => 'updated_at',
        );

        $column    = $allowed[ sanitize_key( $orderby ) ] ?? 'created_at';
        $direction = 'ASC' === strtoupper( $order ) ? 'ASC' : 'DESC';

        return $column . ' ' . $direction;
    }
    
    private function sanitize_input( array $input ): array {
        $sanitized = array();
        
        $allowed_fields = array(
            'name', 'company_name', 'email', 'phone', 'website',
            'address', 'city', 'state', 'country', 'zip_code',
            'tax_id', 'notes', 'status', 'source',
            'company', 'postal_code', 'vat_number'
        );
        
        foreach ( $input as $key => $value ) {
            if ( ! in_array( $key, $allowed_fields, true ) ) {
                continue;
            }
            
            if ( 'company' === $key ) {
                $key = 'company_name';
            } elseif ( 'postal_code' === $key ) {
                $key = 'zip_code';
            } elseif ( 'vat_number' === $key ) {
                $key = 'tax_id';
            }

            switch ( $key ) {
                case 'email':
                    $sanitized[ $key ] = sanitize_email( (string) $value );
                    break;
                case 'website':
                    $sanitized[ $key ] = esc_url_raw( (string) $value );
                    break;
                case 'status':
                    if ( 'lead' === $value ) {
                        $value = 'prospect';
                    }
                    $allowed = array( 'active', 'inactive', 'prospect', 'archived' );
                    $sanitized[ $key ] = in_array( $value, $allowed, true ) ? $value : 'active';
                    break;
                case 'notes':
                    $sanitized[ $key ] = sanitize_textarea_field( (string) $value );
                    break;
                default:
                    $sanitized[ $key ] = sanitize_text_field( (string) $value );
            }
        }
        
        return $sanitized;
    }
}
