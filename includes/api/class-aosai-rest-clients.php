<?php
if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

class AOSAI_REST_Clients extends WP_REST_Controller {
    use AOSAI_REST_Validation;
    
    protected $namespace = 'aosai/v1';
    protected $rest_base = 'clients';
    
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
        
        register_rest_route( $this->namespace, '/' . $this->rest_base . '/(?P<id>\d+)', array(
            array(
                'methods'             => \WP_REST_Server::READABLE,
                'callback'            => array( $this, 'get_item' ),
                'permission_callback' => array( $this, 'get_item_permissions_check' ),
                'args'               => array(
                    'id' => array(
                        'description' => __( 'Unique identifier for the client.', 'agency-os-ai' ),
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
                        'description' => __( 'Whether to bypass Trash before deletion.', 'agency-os-ai' ),
                    ),
                ),
            ),
        ) );
        
        register_rest_route( $this->namespace, '/' . $this->rest_base . '/(?P<client_id>\d+)/users', array(
            array(
                'methods'             => \WP_REST_Server::READABLE,
                'callback'            => array( $this, 'get_client_users' ),
                'permission_callback' => array( $this, 'get_item_permissions_check' ),
            ),
            array(
                'methods'             => \WP_REST_Server::CREATABLE,
                'callback'            => array( $this, 'add_client_user' ),
                'permission_callback' => array( $this, 'update_item_permissions_check' ),
                'args'               => array(
                    'user_id' => array(
                        'required'    => true,
                        'type'        => 'integer',
                    ),
                    'role'    => array(
                        'type'        => 'string',
                        'default'     => 'contact',
                    ),
                ),
            ),
        ) );
        
        register_rest_route( $this->namespace, '/' . $this->rest_base . '/(?P<client_id>\d+)/users/(?P<user_id>\d+)', array(
            'methods'             => \WP_REST_Server::DELETABLE,
            'callback'            => array( $this, 'remove_client_user' ),
            'permission_callback' => array( $this, 'update_item_permissions_check' ),
        ) );
        
        register_rest_route( $this->namespace, '/' . $this->rest_base . '/(?P<client_id>\d+)/projects', array(
            array(
                'methods'             => \WP_REST_Server::READABLE,
                'callback'            => array( $this, 'get_client_projects' ),
                'permission_callback' => array( $this, 'get_item_permissions_check' ),
            ),
            array(
                'methods'             => \WP_REST_Server::CREATABLE,
                'callback'            => array( $this, 'link_client_project' ),
                'permission_callback' => array( $this, 'update_item_permissions_check' ),
                'args'               => array(
                    'project_id' => array(
                        'required' => true,
                        'type'     => 'integer',
                    ),
                ),
            ),
        ) );
        
        register_rest_route( $this->namespace, '/' . $this->rest_base . '/(?P<client_id>\d+)/projects/(?P<project_id>\d+)', array(
            'methods'             => \WP_REST_Server::DELETABLE,
            'callback'            => array( $this, 'unlink_client_project' ),
            'permission_callback' => array( $this, 'update_item_permissions_check' ),
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
        $client = AOSAI_Client::get_instance();
        
        $args = array(
            'page'       => (int) $request->get_param( 'page' ),
            'per_page'   => (int) $request->get_param( 'per_page' ),
            'status'     => sanitize_key( $request->get_param( 'status' ) ),
            'search'     => sanitize_text_field( $request->get_param( 'search' ) ),
            'orderby'    => sanitize_sql_orderby( $request->get_param( 'orderby' ) ?: 'created_at' ),
            'order'      => in_array( strtoupper( $request->get_param( 'order' ) ?: 'DESC' ), array( 'ASC', 'DESC' ), true ) ? strtoupper( $request->get_param( 'order' ) ) : 'DESC',
        );
        
        $clients = $client->get_all( $args );
        
        return new \WP_REST_Response( $clients, 200 );
    }
    
    public function get_item( $request ) {
        $client = AOSAI_Client::get_instance();
        $item = $client->get( (int) $request->get_param( 'id' ) );
        
        if ( ! $item ) {
            return new \WP_Error( 'rest_client_invalid_id', __( 'Invalid client ID.', 'agency-os-ai' ), array( 'status' => 404 ) );
        }
        
        return new \WP_REST_Response( $item, 200 );
    }
    
    public function create_item( $request ) {
        $client = AOSAI_Client::get_instance();
        
        $data = $request->get_json_params();
        $result = $client->create( $data );
        
        if ( is_wp_error( $result ) ) {
            return $result;
        }
        
        $item = $client->get( $result );
        
        return new \WP_REST_Response( $item, 201 );
    }
    
    public function update_item( $request ) {
        $client = AOSAI_Client::get_instance();
        
        $data = $request->get_json_params();
        $result = $client->update( (int) $request->get_param( 'id' ), $data );
        
        if ( is_wp_error( $result ) ) {
            return $result;
        }
        
        $item = $client->get( (int) $request->get_param( 'id' ) );
        
        return new \WP_REST_Response( $item, 200 );
    }
    
    public function delete_item( $request ) {
        $client = AOSAI_Client::get_instance();
        
        $result = $client->delete( (int) $request->get_param( 'id' ) );
        
        if ( ! $result ) {
            return new \WP_Error( 'rest_cannot_delete', __( 'Cannot delete this client.', 'agency-os-ai' ), array( 'status' => 500 ) );
        }
        
        return new \WP_REST_Response( array( 'deleted' => true ), 200 );
    }
    
    public function get_client_users( $request ) {
        $client = AOSAI_Client::get_instance();
        $users = $client->get_client_users( (int) $request->get_param( 'client_id' ) );
        
        return new \WP_REST_Response( $users, 200 );
    }
    
    public function add_client_user( $request ) {
        $client = AOSAI_Client::get_instance();
        
        $result = $client->add_user(
            (int) $request->get_param( 'client_id' ),
            (int) $request->get_param( 'user_id' ),
            sanitize_key( $request->get_param( 'role' ) ?: 'contact' )
        );
        
        if ( ! $result ) {
            return new \WP_Error( 'rest_error', __( 'Failed to add user to client.', 'agency-os-ai' ), array( 'status' => 500 ) );
        }
        
        $users = $client->get_client_users( (int) $request->get_param( 'client_id' ) );
        
        return new \WP_REST_Response( $users, 200 );
    }
    
    public function remove_client_user( $request ) {
        $client = AOSAI_Client::get_instance();
        
        $result = $client->remove_user(
            (int) $request->get_param( 'client_id' ),
            (int) $request->get_param( 'user_id' )
        );
        
        if ( ! $result ) {
            return new \WP_Error( 'rest_error', __( 'Failed to remove user from client.', 'agency-os-ai' ), array( 'status' => 500 ) );
        }
        
        return new \WP_REST_Response( array( 'deleted' => true ), 200 );
    }
    
    public function get_client_projects( $request ) {
        $client = AOSAI_Client::get_instance();
        $projects = $client->get_client_projects( (int) $request->get_param( 'client_id' ) );
        
        return new \WP_REST_Response( $projects, 200 );
    }
    
    public function link_client_project( $request ) {
        $client = AOSAI_Client::get_instance();
        
        $result = $client->link_project(
            (int) $request->get_param( 'client_id' ),
            (int) $request->get_param( 'project_id' )
        );
        
        if ( ! $result ) {
            return new \WP_Error( 'rest_error', __( 'Failed to link project to client.', 'agency-os-ai' ), array( 'status' => 500 ) );
        }
        
        $projects = $client->get_client_projects( (int) $request->get_param( 'client_id' ) );
        
        return new \WP_REST_Response( $projects, 200 );
    }
    
    public function unlink_client_project( $request ) {
        $client = AOSAI_Client::get_instance();
        
        $result = $client->unlink_project(
            (int) $request->get_param( 'client_id' ),
            (int) $request->get_param( 'project_id' )
        );
        
        if ( ! $result ) {
            return new \WP_Error( 'rest_error', __( 'Failed to unlink project from client.', 'agency-os-ai' ), array( 'status' => 500 ) );
        }
        
        return new \WP_REST_Response( array( 'deleted' => true ), 200 );
    }
    
    public function get_collection_params() {
        return array(
            'page'     => array(
                'description' => __( 'Current page of the result set.', 'agency-os-ai' ),
                'type'        => 'integer',
                'default'     => 1,
            ),
            'per_page' => array(
                'description' => __( 'Maximum number of items to return.', 'agency-os-ai' ),
                'type'        => 'integer',
                'default'     => 20,
            ),
            'status'   => array(
                'description' => __( 'Filter by status.', 'agency-os-ai' ),
                'type'        => 'string',
            ),
            'search'   => array(
                'description' => __( 'Search term.', 'agency-os-ai' ),
                'type'        => 'string',
            ),
            'orderby'  => array(
                'description' => __( 'Sort collection by field.', 'agency-os-ai' ),
                'type'        => 'string',
                'default'     => 'created_at',
            ),
            'order'    => array(
                'description' => __( 'Order sort direction.', 'agency-os-ai' ),
                'type'        => 'string',
                'default'     => 'DESC',
            ),
        );
    }

    private function get_create_params(): array {
        return array(
            'name'        => array( 'type' => 'string', 'required' => true ),
            'company'     => array( 'type' => 'string' ),
            'company_name'=> array( 'type' => 'string' ),
            'email'       => array( 'type' => 'string' ),
            'phone'       => array( 'type' => 'string' ),
            'website'     => array( 'type' => 'string' ),
            'address'     => array( 'type' => 'string' ),
            'city'        => array( 'type' => 'string' ),
            'state'       => array( 'type' => 'string' ),
            'country'     => array( 'type' => 'string' ),
            'postal_code' => array( 'type' => 'string' ),
            'zip_code'    => array( 'type' => 'string' ),
            'vat_number'  => array( 'type' => 'string' ),
            'tax_id'      => array( 'type' => 'string' ),
            'notes'       => array( 'type' => 'string' ),
            'status'      => array( 'type' => 'string' ),
        );
    }

    private function get_update_params(): array {
        return $this->get_create_params();
    }
}
