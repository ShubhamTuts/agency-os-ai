<?php
if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

class AOSAI_REST_Users extends WP_REST_Controller {
    
    protected $namespace = 'aosai/v1';
    
    public function register_routes() {
        register_rest_route( $this->namespace, '/users', array(
            array(
                'methods'             => WP_REST_Server::READABLE,
                'callback'            => array( $this, 'get_users' ),
                'permission_callback' => array( $this, 'get_users_permissions_check' ),
            ),
        ) );

        register_rest_route( $this->namespace, '/users/list', array(
            array(
                'methods'             => WP_REST_Server::READABLE,
                'callback'            => array( $this, 'get_users_list' ),
                'permission_callback' => array( $this, 'get_users_permissions_check' ),
            ),
        ) );

        register_rest_route( $this->namespace, '/users/invite', array(
            array(
                'methods'             => WP_REST_Server::CREATABLE,
                'callback'            => array( $this, 'invite_user' ),
                'permission_callback' => array( $this, 'invite_user_permissions_check' ),
            ),
        ) );

        register_rest_route( $this->namespace, '/users/(?P<id>[\d]+)/dashboard', array(
            array(
                'methods'             => WP_REST_Server::READABLE,
                'callback'            => array( $this, 'get_user_dashboard' ),
                'permission_callback' => array( $this, 'get_user_dashboard_permissions_check' ),
            ),
        ) );
    }
    
    public function get_users_permissions_check( $request ) {
        return is_user_logged_in() ? true : new WP_Error( 'rest_not_logged_in', esc_html__( 'You must be logged in.', 'agency-os-ai' ), array( 'status' => 401 ) );
    }

    public function invite_user_permissions_check( $request ) {
        if ( ! is_user_logged_in() ) {
            return new WP_Error( 'rest_not_logged_in', esc_html__( 'You must be logged in.', 'agency-os-ai' ), array( 'status' => 401 ) );
        }
        if ( ! current_user_can( 'create_users' ) ) {
            return new WP_Error( 'rest_forbidden', esc_html__( 'You cannot invite users.', 'agency-os-ai' ), array( 'status' => 403 ) );
        }
        return true;
    }
    
    public function get_user_dashboard_permissions_check( $request ) {
        $id = absint( $request->get_param( 'id' ) );
        $current_user = get_current_user_id();
        
        if ( $id !== $current_user && ! current_user_can( 'manage_options' ) ) {
            return new WP_Error( 'rest_forbidden', esc_html__( 'You can only view your own dashboard.', 'agency-os-ai' ), array( 'status' => 403 ) );
        }
        
        return true;
    }
    
    private function format_user( \WP_User $user ): array {
        global $wpdb;

        $project_count = (int) $wpdb->get_var(
            $wpdb->prepare(
                "SELECT COUNT(*) FROM {$wpdb->prefix}aosai_project_users WHERE user_id = %d",
                $user->ID
            )
        );
        $task_count = (int) $wpdb->get_var(
            $wpdb->prepare(
                "SELECT COUNT(DISTINCT task_id) FROM {$wpdb->prefix}aosai_task_users WHERE user_id = %d",
                $user->ID
            )
        );

        return array(
            'id'            => $user->ID,
            'name'          => $user->display_name,
            'display_name'  => $user->display_name,
            'email'         => $user->user_email,
            'avatar_url'    => get_avatar_url( $user->ID ),
            'roles'         => $user->roles,
            'portal_type'   => aosai_get_user_portal_type( $user->ID ),
            'project_count' => $project_count,
            'task_count'    => $task_count,
        );
    }

    public function get_users( $request ) {
        $search  = sanitize_text_field( $request->get_param( 'search' ) ) ?: '';
        $per_page = min( absint( $request->get_param( 'per_page' ) ) ?: 20, 100 );
        $page    = max( 1, absint( $request->get_param( 'page' ) ) ?: 1 );

        $args = array(
            'number' => $per_page,
            'offset' => ( $page - 1 ) * $per_page,
            'orderby' => 'display_name',
            'order' => 'ASC',
        );
        if ( $search !== '' ) {
            $args['search'] = '*' . $search . '*';
        }

        $user_query = new \WP_User_Query( $args );
        $users  = $user_query->get_results();
        $total_users = $user_query->get_total();

        $result = array();
        foreach ( $users as $user ) {
            $result[] = $this->format_user( $user );
        }

        $response = new WP_REST_Response( $result, 200 );
        $response->header( 'X-WP-Total', $total_users );
        $response->header( 'X-WP-TotalPages', ceil( $total_users / $per_page ) );

        return $response;
    }

    public function get_users_list( $request ) {
        $search = sanitize_text_field( (string) $request->get_param( 'search' ) );
        $args = array( 'orderby' => 'display_name', 'order' => 'ASC' );
        if ( '' !== $search ) {
            $args['search'] = '*' . $search . '*';
        }
        $users = get_users( $args );
        $result = array_map( array( $this, 'format_user' ), $users );
        return rest_ensure_response( $result );
    }

    public function invite_user( $request ) {
        $data  = $request->get_json_params();
        $email = sanitize_email( $data['email'] ?? '' );

        if ( ! is_email( $email ) ) {
            return new WP_Error( 'invalid_email', esc_html__( 'A valid email address is required.', 'agency-os-ai' ), array( 'status' => 400 ) );
        }

        $role = sanitize_key( $data['role'] ?? 'aosai_employee' );
        $allowed_roles = array_keys( wp_roles()->roles );
        if ( ! in_array( $role, $allowed_roles, true ) ) {
            $role = 'aosai_employee';
        }

        $existing = get_user_by( 'email', $email );
        if ( $existing ) {
        return rest_ensure_response(
            array(
                'status'  => 'existing',
                'user'    => $this->format_user( $existing ),
                'message' => esc_html__( 'User already exists.', 'agency-os-ai' ),
            )
        );
        }

        $username = sanitize_user( strtok( $email, '@' ) . wp_rand( 100, 999 ) );
        $password = wp_generate_password( 12, false );

        $user_id = wp_create_user( $username, $password, $email );
        if ( is_wp_error( $user_id ) ) {
            return $user_id;
        }

        $user = new \WP_User( $user_id );
        $user->set_role( $role );

        wp_send_new_user_notifications( $user_id, 'user' );

        return rest_ensure_response(
            array(
                'status'  => 'invited',
                'user'    => $this->format_user( $user ),
                'message' => esc_html__( 'Invitation sent successfully.', 'agency-os-ai' ),
            )
        );
    }
    
    public function get_user_dashboard( $request ) {
        $user_id = absint( $request->get_param( 'id' ) );
        
        $task_model = AOSAI_Task::get_instance();
        
        $my_tasks = $task_model->get_my_tasks( $user_id, array( 'status' => 'open,in_progress' ) );
        
        global $wpdb;
        $prefix = $wpdb->prefix;
        
        $task_stats = $wpdb->get_row(
            $wpdb->prepare(
                "SELECT 
                    COUNT(DISTINCT t.project_id) as total_projects,
                    COUNT(CASE WHEN t.status IN ('open', 'todo', 'backlog') THEN 1 END) as open_tasks,
                    COUNT(CASE WHEN t.status IN ('in_progress', 'in_review') THEN 1 END) as in_progress_tasks,
                    COUNT(CASE WHEN t.status IN ('done', 'completed') AND DATE(t.completed_at) >= DATE_SUB(CURDATE(), INTERVAL 7 DAY) THEN 1 END) as completed_this_week,
                    COUNT(CASE WHEN t.due_date < CURDATE() AND t.status NOT IN ('done', 'completed') THEN 1 END) as overdue_tasks
                FROM {$prefix}aosai_tasks t
                INNER JOIN {$prefix}aosai_task_users tu ON t.id = tu.task_id
                WHERE tu.user_id = %d",
                $user_id
            )
        );
        
        $data = array(
            'my_tasks'           => $my_tasks,
            'stats'             => array(
                'total_projects'     => (int) ($task_stats->total_projects ?? 0),
                'open_tasks'         => (int) ($task_stats->open_tasks ?? 0),
                'in_progress_tasks'  => (int) ($task_stats->in_progress_tasks ?? 0),
                'completed_this_week'=> (int) ($task_stats->completed_this_week ?? 0),
                'overdue_tasks'     => (int) ($task_stats->overdue_tasks ?? 0),
            ),
        );
        
        return rest_ensure_response( $data );
    }
}
