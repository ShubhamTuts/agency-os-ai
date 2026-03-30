<?php
if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

class AOSAI_REST_Tasks extends WP_REST_Controller {
    use AOSAI_REST_Validation;
    
    protected $namespace = 'aosai/v1';
    protected $rest_base = 'tasks';
    
    public function register_routes() {
        register_rest_route( $this->namespace, '/projects/(?P<project_id>[\d]+)/tasks', array(
            array(
                'methods'             => WP_REST_Server::READABLE,
                'callback'            => array( $this, 'get_project_tasks' ),
                'permission_callback' => array( $this, 'project_access_check' ),
            ),
            array(
                'methods'             => WP_REST_Server::CREATABLE,
                'callback'            => array( $this, 'create_task' ),
                'permission_callback' => array( $this, 'project_edit_check' ),
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
        
        register_rest_route( $this->namespace, '/' . $this->rest_base . '/(?P<id>[\d]+)/status', array(
            array(
                'methods'             => 'POST, PUT, PATCH',
                'callback'            => array( $this, 'update_status' ),
                'permission_callback' => array( $this, 'update_item_permissions_check' ),
            ),
        ) );
        
        register_rest_route( $this->namespace, '/' . $this->rest_base . '/(?P<id>[\d]+)/assign', array(
            array(
                'methods'             => WP_REST_Server::CREATABLE,
                'callback'            => array( $this, 'assign_users' ),
                'permission_callback' => array( $this, 'update_item_permissions_check' ),
            ),
        ) );
        
        register_rest_route( $this->namespace, '/' . $this->rest_base . '/my-tasks', array(
            array(
                'methods'             => WP_REST_Server::READABLE,
                'callback'            => array( $this, 'get_my_tasks' ),
                'permission_callback' => array( $this, 'get_items_permissions_check' ),
            ),
        ) );
    }
    
    public function get_items_permissions_check( $request ) {
        return is_user_logged_in() ? true : new WP_Error( 'rest_not_logged_in', esc_html__( 'You must be logged in.', 'agency-os-ai' ), array( 'status' => 401 ) );
    }
    
    public function project_access_check( $request ) {
        if ( ! is_user_logged_in() ) {
            return new WP_Error( 'rest_not_logged_in', esc_html__( 'You must be logged in.', 'agency-os-ai' ), array( 'status' => 401 ) );
        }
        $project_id = absint( $request->get_param( 'project_id' ) );
        if ( ! aosai_user_can_access_project( get_current_user_id(), $project_id ) ) {
            return new WP_Error( 'rest_forbidden', esc_html__( 'You do not have access to this project.', 'agency-os-ai' ), array( 'status' => 403 ) );
        }
        return true;
    }
    
    public function project_edit_check( $request ) {
        if ( ! is_user_logged_in() ) {
            return new WP_Error( 'rest_not_logged_in', esc_html__( 'You must be logged in.', 'agency-os-ai' ), array( 'status' => 401 ) );
        }
        $project_id = absint( $request->get_param( 'project_id' ) );
        $permissions = AOSAI_Permission_Service::get_instance();
        if ( ! $permissions->can_create_tasks( get_current_user_id(), $project_id ) ) {
            return new WP_Error( 'rest_forbidden', esc_html__( 'You cannot create tasks in this project.', 'agency-os-ai' ), array( 'status' => 403 ) );
        }
        return true;
    }
    
    public function get_item_permissions_check( $request ) {
        if ( ! is_user_logged_in() ) {
            return new WP_Error( 'rest_not_logged_in', esc_html__( 'You must be logged in.', 'agency-os-ai' ), array( 'status' => 401 ) );
        }
        return true;
    }
    
    public function update_item_permissions_check( $request ) {
        if ( ! is_user_logged_in() ) {
            return new WP_Error( 'rest_not_logged_in', esc_html__( 'You must be logged in.', 'agency-os-ai' ), array( 'status' => 401 ) );
        }
        return true;
    }
    
    public function delete_item_permissions_check( $request ) {
        if ( ! is_user_logged_in() ) {
            return new WP_Error( 'rest_not_logged_in', esc_html__( 'You must be logged in.', 'agency-os-ai' ), array( 'status' => 401 ) );
        }
        return true;
    }
    
    public function get_project_tasks( $request ) {
        $project_id = absint( $request->get_param( 'project_id' ) );
        $args = array(
            'page'        => absint( $request->get_param( 'page' ) ) ?: 1,
            'per_page'    => min( absint( $request->get_param( 'per_page' ) ) ?: 50, 100 ),
            'task_list_id'=> sanitize_key( $request->get_param( 'task_list_id' ) ) ?: '',
            'status'      => sanitize_key( $request->get_param( 'status' ) ) ?: '',
            'priority'    => sanitize_key( $request->get_param( 'priority' ) ) ?: '',
            'assigned_to' => sanitize_key( $request->get_param( 'assigned_to' ) ) ?: '',
            'search'      => sanitize_text_field( $request->get_param( 'search' ) ) ?: '',
            'due_before'  => sanitize_text_field( $request->get_param( 'due_before' ) ) ?: '',
            'due_after'   => sanitize_text_field( $request->get_param( 'due_after' ) ) ?: '',
        );
        
        $model = AOSAI_Task::get_instance();
        $tasks = $model->get_project_tasks( $project_id, $args );
        
        return rest_ensure_response( $tasks );
    }
    
    public function create_task( $request ) {
        $data = $request->get_json_params();
        $data['project_id'] = absint( $request->get_param( 'project_id' ) );
        
        $model = AOSAI_Task::get_instance();
        $result = $model->create( $data );
        
        if ( is_wp_error( $result ) ) {
            return $result;
        }

        if ( array_key_exists( 'tags', $data ) ) {
            AOSAI_Tag::get_instance()->sync_object_tags( 'task', (int) $result, $data['tags'], 'task' );
        }
        
        $task = $model->get( $result );
        return rest_ensure_response( $task );
    }
    
    public function get_item( $request ) {
        $id = absint( $request->get_param( 'id' ) );
        
        $model = AOSAI_Task::get_instance();
        $task = $model->get( $id );
        
        if ( ! $task ) {
            return new WP_Error( 'not_found', esc_html__( 'Task not found.', 'agency-os-ai' ), array( 'status' => 404 ) );
        }
        
        return rest_ensure_response( $task );
    }
    
    public function update_item( $request ) {
        $id = absint( $request->get_param( 'id' ) );
        $data = $request->get_json_params();
        
        $model = AOSAI_Task::get_instance();
        $result = $model->update( $id, $data );
        
        if ( is_wp_error( $result ) ) {
            return $result;
        }

        if ( array_key_exists( 'tags', $data ) ) {
            AOSAI_Tag::get_instance()->sync_object_tags( 'task', $id, $data['tags'], 'task' );
        }
        
        $task = $model->get( $id );
        return rest_ensure_response( $task );
    }
    
    public function delete_item( $request ) {
        $id = absint( $request->get_param( 'id' ) );
        
        $model = AOSAI_Task::get_instance();
        $result = $model->delete( $id );
        
        if ( ! $result ) {
            return new WP_Error( 'not_found', esc_html__( 'Task not found.', 'agency-os-ai' ), array( 'status' => 404 ) );
        }
        
        return new WP_REST_Response( array( 'deleted' => true, 'id' => $id ), 200 );
    }
    
    public function update_status( $request ) {
        $id = absint( $request->get_param( 'id' ) );
        $data = $request->get_json_params();
        
        $model = AOSAI_Task::get_instance();
        $result = $model->update( $id, array( 'status' => sanitize_key( $data['status'] ?? 'open' ) ) );
        
        if ( is_wp_error( $result ) ) {
            return $result;
        }
        
        $task = $model->get( $id );
        return rest_ensure_response( $task );
    }
    
    public function assign_users( $request ) {
        $id = absint( $request->get_param( 'id' ) );
        $data = $request->get_json_params();
        
        $user_ids = array_filter( array_map( 'absint', (array) ( $data['user_ids'] ?? array() ) ) );
        
        $model = AOSAI_Task::get_instance();
        $model->set_assignees( $id, $user_ids );
        
        $task = $model->get( $id );
        return rest_ensure_response( $task );
    }
    
    public function get_my_tasks( $request ) {
        $user_id = get_current_user_id();
        $args = array(
            'page'   => absint( $request->get_param( 'page' ) ) ?: 1,
            'per_page'=> min( absint( $request->get_param( 'per_page' ) ) ?: 20, 100 ),
            'status' => sanitize_key( $request->get_param( 'status' ) ) ?: '',
        );
        
        $model = AOSAI_Task::get_instance();
        $tasks = $model->get_my_tasks( $user_id, $args );
        
        return rest_ensure_response( $tasks );
    }
}
