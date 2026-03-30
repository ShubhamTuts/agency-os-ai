<?php
if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

class AOSAI_REST_Projects extends WP_REST_Controller {
    use AOSAI_REST_Validation;
    
    protected $namespace = 'aosai/v1';
    protected $rest_base = 'projects';
    
    public function register_routes() {
        register_rest_route( $this->namespace, '/' . $this->rest_base, array(
            array(
                'methods'             => WP_REST_Server::READABLE,
                'callback'            => array( $this, 'get_items' ),
                'permission_callback' => array( $this, 'get_items_permissions_check' ),
                'args'                => $this->get_collection_params(),
            ),
            array(
                'methods'             => WP_REST_Server::CREATABLE,
                'callback'            => array( $this, 'create_item' ),
                'permission_callback' => array( $this, 'create_item_permissions_check' ),
                'args'                => $this->get_create_params(),
            ),
        ) );
        
        register_rest_route( $this->namespace, '/' . $this->rest_base . '/(?P<id>[\d]+)', array(
            array(
                'methods'             => WP_REST_Server::READABLE,
                'callback'            => array( $this, 'get_item' ),
                'permission_callback' => array( $this, 'get_item_permissions_check' ),
            ),
            array(
                'methods'             => WP_REST_Server::EDITABLE,
                'callback'            => array( $this, 'update_item' ),
                'permission_callback' => array( $this, 'update_item_permissions_check' ),
            ),
            array(
                'methods'             => WP_REST_Server::DELETABLE,
                'callback'            => array( $this, 'delete_item' ),
                'permission_callback' => array( $this, 'delete_item_permissions_check' ),
            ),
        ) );
        
        register_rest_route( $this->namespace, '/' . $this->rest_base . '/(?P<id>[\d]+)/stats', array(
            array(
                'methods'             => WP_REST_Server::READABLE,
                'callback'            => array( $this, 'get_project_stats' ),
                'permission_callback' => array( $this, 'get_item_permissions_check' ),
            ),
        ) );
        
        register_rest_route( $this->namespace, '/' . $this->rest_base . '/(?P<id>[\d]+)/members', array(
            array(
                'methods'             => WP_REST_Server::CREATABLE,
                'callback'            => array( $this, 'add_member' ),
                'permission_callback' => array( $this, 'update_item_permissions_check' ),
            ),
        ) );
        
        register_rest_route( $this->namespace, '/' . $this->rest_base . '/(?P<id>[\d]+)/members/(?P<user_id>[\d]+)', array(
            array(
                'methods'             => WP_REST_Server::DELETABLE,
                'callback'            => array( $this, 'remove_member' ),
                'permission_callback' => array( $this, 'update_item_permissions_check' ),
            ),
            array(
                'methods'             => WP_REST_Server::EDITABLE,
                'callback'            => array( $this, 'update_member' ),
                'permission_callback' => array( $this, 'update_item_permissions_check' ),
            ),
        ) );
    }
    
    public function get_items_permissions_check( $request ) {
        if ( ! is_user_logged_in() ) {
            return new WP_Error( 'rest_not_logged_in', esc_html__( 'You must be logged in.', 'agency-os-ai' ), array( 'status' => 401 ) );
        }
        return true;
    }
    
    public function create_item_permissions_check( $request ) {
        if ( ! is_user_logged_in() || ! current_user_can( 'aosai_manage_projects' ) ) {
            return new WP_Error( 'rest_forbidden', esc_html__( 'You do not have permission to create projects.', 'agency-os-ai' ), array( 'status' => 403 ) );
        }
        return true;
    }
    
    public function get_item_permissions_check( $request ) {
        if ( ! is_user_logged_in() ) {
            return new WP_Error( 'rest_not_logged_in', esc_html__( 'You must be logged in.', 'agency-os-ai' ), array( 'status' => 401 ) );
        }
        
        $project_id = absint( $request->get_param( 'id' ) );
        if ( $project_id && ! aosai_user_can_access_project( get_current_user_id(), $project_id ) ) {
            return new WP_Error( 'rest_forbidden', esc_html__( 'You do not have access to this project.', 'agency-os-ai' ), array( 'status' => 403 ) );
        }
        return true;
    }
    
    public function update_item_permissions_check( $request ) {
        if ( ! is_user_logged_in() ) {
            return new WP_Error( 'rest_not_logged_in', esc_html__( 'You must be logged in.', 'agency-os-ai' ), array( 'status' => 401 ) );
        }
        
        $project_id = absint( $request->get_param( 'id' ) );
        if ( ! aosai_user_can_access_project( get_current_user_id(), $project_id ) ) {
            return new WP_Error( 'rest_forbidden', esc_html__( 'You do not have access to this project.', 'agency-os-ai' ), array( 'status' => 403 ) );
        }
        
        $permissions = AOSAI_Permission_Service::get_instance();
        if ( ! $permissions->can_edit_project( get_current_user_id(), $project_id ) ) {
            return new WP_Error( 'rest_forbidden', esc_html__( 'You do not have permission to edit this project.', 'agency-os-ai' ), array( 'status' => 403 ) );
        }
        return true;
    }
    
    public function delete_item_permissions_check( $request ) {
        if ( ! is_user_logged_in() ) {
            return new WP_Error( 'rest_not_logged_in', esc_html__( 'You must be logged in.', 'agency-os-ai' ), array( 'status' => 401 ) );
        }
        
        $project_id = absint( $request->get_param( 'id' ) );
        $permissions = AOSAI_Permission_Service::get_instance();
        if ( ! $permissions->can_manage_project( get_current_user_id(), $project_id ) ) {
            return new WP_Error( 'rest_forbidden', esc_html__( 'You do not have permission to delete this project.', 'agency-os-ai' ), array( 'status' => 403 ) );
        }
        return true;
    }
    
    public function get_items( $request ) {
        $user_id = get_current_user_id();
        $args = array(
            'page'     => absint( $request->get_param( 'page' ) ) ?: 1,
            'per_page' => min( absint( $request->get_param( 'per_page' ) ) ?: 20, 100 ),
            'status'   => sanitize_key( $request->get_param( 'status' ) ) ?: '',
            'search'   => sanitize_text_field( $request->get_param( 'search' ) ) ?: '',
            'orderby'  => sanitize_key( $request->get_param( 'orderby' ) ) ?: 'created_at',
            'order'    => in_array( strtoupper( $request->get_param( 'order' ) ), array( 'ASC', 'DESC' ), true ) ? strtoupper( $request->get_param( 'order' ) ) : 'DESC',
        );
        
        $model = AOSAI_Project::get_instance();
        $projects = $model->get_user_projects( $user_id, $args );
        
        return rest_ensure_response( $projects );
    }
    
    public function create_item( $request ) {
        $data = $request->get_json_params();
        
        $model = AOSAI_Project::get_instance();
        $result = $model->create( $data );
        
        if ( is_wp_error( $result ) ) {
            return $result;
        }

        if ( array_key_exists( 'tags', $data ) ) {
            AOSAI_Tag::get_instance()->sync_object_tags( 'project', (int) $result, $data['tags'], 'project' );
        }
        
        $project = $model->get( $result );
        
        return rest_ensure_response( $project );
    }
    
    public function get_item( $request ) {
        $id = absint( $request->get_param( 'id' ) );
        
        $model = AOSAI_Project::get_instance();
        $project = $model->get( $id );
        
        if ( ! $project ) {
            return new WP_Error( 'not_found', esc_html__( 'Project not found.', 'agency-os-ai' ), array( 'status' => 404 ) );
        }
        
        return rest_ensure_response( $project );
    }
    
    public function update_item( $request ) {
        $id = absint( $request->get_param( 'id' ) );
        $data = $request->get_json_params();
        
        $model = AOSAI_Project::get_instance();
        $result = $model->update( $id, $data );
        
        if ( is_wp_error( $result ) ) {
            return $result;
        }

        if ( array_key_exists( 'tags', $data ) ) {
            AOSAI_Tag::get_instance()->sync_object_tags( 'project', $id, $data['tags'], 'project' );
        }
        
        $project = $model->get( $id );
        return rest_ensure_response( $project );
    }
    
    public function delete_item( $request ) {
        $id = absint( $request->get_param( 'id' ) );
        
        $model = AOSAI_Project::get_instance();
        $result = $model->delete( $id );
        
        if ( ! $result ) {
            return new WP_Error( 'not_found', esc_html__( 'Project not found.', 'agency-os-ai' ), array( 'status' => 404 ) );
        }
        
        return new WP_REST_Response( array( 'deleted' => true, 'id' => $id ), 200 );
    }
    
    public function get_project_stats( $request ) {
        $id = absint( $request->get_param( 'id' ) );
        
        $model = AOSAI_Project::get_instance();
        $stats = $model->get_stats( $id );
        
        return rest_ensure_response( $stats );
    }
    
    public function add_member( $request ) {
        $project_id = absint( $request->get_param( 'id' ) );
        $data = $request->get_json_params();
        
        $user_id = absint( $data['user_id'] ?? 0 );
        $role = sanitize_key( $data['role'] ?? 'member' );
        
        if ( ! $user_id ) {
            return new WP_Error( 'missing_user_id', esc_html__( 'User ID is required.', 'agency-os-ai' ), array( 'status' => 400 ) );
        }
        
        $model = AOSAI_Project::get_instance();
        $model->add_member( $project_id, $user_id, $role );
        
        $members = $model->get_members( $project_id );
        return rest_ensure_response( $members );
    }
    
    public function remove_member( $request ) {
        $project_id = absint( $request->get_param( 'id' ) );
        $user_id = absint( $request->get_param( 'user_id' ) );
        
        $model = AOSAI_Project::get_instance();
        $model->remove_member( $project_id, $user_id );
        
        return new WP_REST_Response( array( 'deleted' => true ), 200 );
    }
    
    public function update_member( $request ) {
        $project_id = absint( $request->get_param( 'id' ) );
        $user_id = absint( $request->get_param( 'user_id' ) );
        $data = $request->get_json_params();
        
        $role = sanitize_key( $data['role'] ?? 'member' );
        
        $model = AOSAI_Project::get_instance();
        $model->add_member( $project_id, $user_id, $role );
        
        return new WP_REST_Response( array( 'updated' => true ), 200 );
    }
    
    public function get_collection_params() {
        return array(
            'page'     => array( 'type' => 'integer', 'default' => 1, 'sanitize_callback' => 'absint' ),
            'per_page' => array( 'type' => 'integer', 'default' => 20, 'sanitize_callback' => 'absint' ),
            'status'   => array( 'type' => 'string', 'sanitize_callback' => 'sanitize_key' ),
            'search'   => array( 'type' => 'string', 'sanitize_callback' => 'sanitize_text_field' ),
            'orderby'  => array( 'type' => 'string', 'default' => 'created_at', 'sanitize_callback' => 'sanitize_key' ),
            'order'    => array( 'type' => 'string', 'default' => 'DESC', 'sanitize_callback' => 'sanitize_key' ),
        );
    }
    
    public function get_create_params() {
        return array(
            'name'        => array( 'type' => 'string', 'sanitize_callback' => 'sanitize_text_field' ),
            'title'       => array( 'type' => 'string', 'sanitize_callback' => 'sanitize_text_field' ),
            'description' => array( 'type' => 'string', 'sanitize_callback' => 'sanitize_textarea_field' ),
            'status'      => array( 'type' => 'string', 'sanitize_callback' => 'sanitize_key' ),
            'category'    => array( 'type' => 'string', 'sanitize_callback' => 'sanitize_text_field' ),
            'color'       => array( 'type' => 'string', 'sanitize_callback' => 'sanitize_hex_color' ),
            'budget'      => array( 'type' => 'number' ),
            'currency'    => array( 'type' => 'string', 'sanitize_callback' => 'sanitize_key' ),
            'start_date'  => array( 'type' => 'string', 'sanitize_callback' => array( $this, 'sanitize_date' ) ),
            'end_date'    => array( 'type' => 'string', 'sanitize_callback' => array( $this, 'sanitize_date' ) ),
            'due_date'    => array( 'type' => 'string', 'sanitize_callback' => array( $this, 'sanitize_date' ) ),
        );
    }
}
