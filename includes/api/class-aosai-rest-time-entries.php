<?php
if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

class AOSAI_REST_Time_Entries extends WP_REST_Controller {
    use AOSAI_REST_Validation;
    
    protected $namespace = 'aosai/v1';
    protected $rest_base = 'time-entries';
    
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
        
        register_rest_route( $this->namespace, '/' . $this->rest_base . '/active', array(
            'methods'             => \WP_REST_Server::READABLE,
            'callback'            => array( $this, 'get_active_timer' ),
            'permission_callback' => array( $this, 'get_items_permissions_check' ),
        ) );
        
        register_rest_route( $this->namespace, '/' . $this->rest_base . '/start', array(
            'methods'             => \WP_REST_Server::CREATABLE,
            'callback'            => array( $this, 'start_timer' ),
            'permission_callback' => array( $this, 'create_item_permissions_check' ),
            'args'               => array(
                'task_id'     => array( 'type' => 'integer' ),
                'project_id'  => array( 'type' => 'integer' ),
                'description' => array( 'type' => 'string' ),
            ),
        ) );
        
        register_rest_route( $this->namespace, '/' . $this->rest_base . '/(?P<id>\d+)/stop', array(
            'methods'             => \WP_REST_Server::CREATABLE,
            'callback'            => array( $this, 'stop_timer' ),
            'permission_callback' => array( $this, 'update_item_permissions_check' ),
        ) );
        
        register_rest_route( $this->namespace, '/' . $this->rest_base . '/(?P<id>\d+)', array(
            array(
                'methods'             => \WP_REST_Server::READABLE,
                'callback'            => array( $this, 'get_item' ),
                'permission_callback' => array( $this, 'get_item_permissions_check' ),
            ),
            array(
                'methods'             => \WP_REST_Server::EDITABLE,
                'callback'            => array( $this, 'update_item' ),
                'permission_callback' => array( $this, 'update_item_permissions_check' ),
            ),
            array(
                'methods'             => \WP_REST_Server::DELETABLE,
                'callback'            => array( $this, 'delete_item' ),
                'permission_callback' => array( $this, 'delete_item_permissions_check' ),
            ),
        ) );
        
        register_rest_route( $this->namespace, '/' . $this->rest_base . '/totals', array(
            'methods'             => \WP_REST_Server::READABLE,
            'callback'            => array( $this, 'get_totals' ),
            'permission_callback' => array( $this, 'get_items_permissions_check' ),
            'args'               => array(
                'user_id'    => array( 'type' => 'integer' ),
                'project_id' => array( 'type' => 'integer' ),
                'date_from'  => array( 'type' => 'string' ),
                'date_to'    => array( 'type' => 'string' ),
            ),
        ) );
    }
    
    public function get_items_permissions_check( $request ) {
        return current_user_can( 'aosai_manage_tasks' ) || current_user_can( 'aosai_access_portal' );
    }
    
    public function get_item_permissions_check( $request ) {
        return current_user_can( 'aosai_manage_tasks' ) || current_user_can( 'aosai_access_portal' );
    }
    
    public function create_item_permissions_check( $request ) {
        return current_user_can( 'aosai_manage_tasks' );
    }
    
    public function update_item_permissions_check( $request ) {
        return current_user_can( 'aosai_manage_tasks' );
    }
    
    public function delete_item_permissions_check( $request ) {
        return current_user_can( 'aosai_manage_tasks' );
    }
    
    public function get_items( $request ) {
        $time_entry = AOSAI_Time_Entry::get_instance();
        
        $user_id = (int) $request->get_param( 'user_id' );
        if ( ! current_user_can( 'aosai_manage_tasks' ) ) {
            $user_id = get_current_user_id();
        }
        
        $date = sanitize_text_field( (string) ( $request->get_param( 'date' ) ?: '' ) );

        $args = array(
            'page'       => (int) $request->get_param( 'page' ),
            'per_page'   => (int) $request->get_param( 'per_page' ),
            'user_id'    => $user_id ?: get_current_user_id(),
            'task_id'    => (int) $request->get_param( 'task_id' ),
            'project_id' => (int) $request->get_param( 'project_id' ),
            'billable'   => $request->get_param( 'billable' ),
            'invoiced'   => $request->get_param( 'invoiced' ),
            'date_from'  => sanitize_text_field( $request->get_param( 'date_from' ) ?: $date ),
            'date_to'    => sanitize_text_field( $request->get_param( 'date_to' ) ?: $date ),
        );
        
        $entries = $time_entry->get_all( $args );
        
        return new \WP_REST_Response( $entries, 200 );
    }
    
    public function get_active_timer( $request ) {
        $time_entry = AOSAI_Time_Entry::get_instance();
        $entry = $time_entry->get_active_timer();
        
        return new \WP_REST_Response( $entry ?: null, 200 );
    }
    
    public function start_timer( $request ) {
        $time_entry = AOSAI_Time_Entry::get_instance();
        
        $result = $time_entry->start_timer(
            (int) $request->get_param( 'task_id' ),
            (int) $request->get_param( 'project_id' ),
            sanitize_text_field( $request->get_param( 'description' ) ?: '' )
        );
        
        if ( is_wp_error( $result ) ) {
            return $result;
        }
        
        $entry = $time_entry->get( $result );
        
        return new \WP_REST_Response( $entry, 201 );
    }
    
    public function stop_timer( $request ) {
        $time_entry = AOSAI_Time_Entry::get_instance();
        
        $result = $time_entry->stop_timer( (int) $request->get_param( 'id' ) );
        
        if ( is_wp_error( $result ) ) {
            return $result;
        }
        
        $entry = $time_entry->get( (int) $request->get_param( 'id' ) );
        
        return new \WP_REST_Response( $entry, 200 );
    }
    
    public function get_item( $request ) {
        $time_entry = AOSAI_Time_Entry::get_instance();
        $entry = $time_entry->get( (int) $request->get_param( 'id' ) );
        
        if ( ! $entry ) {
            return new \WP_Error( 'rest_invalid_id', __( 'Invalid time entry ID.', 'agency-os-ai' ), array( 'status' => 404 ) );
        }
        
        return new \WP_REST_Response( $entry, 200 );
    }
    
    public function create_item( $request ) {
        $time_entry = AOSAI_Time_Entry::get_instance();
        
        $data = $this->normalize_time_entry_payload( (array) $request->get_json_params() );
        $result = $time_entry->create( $data );
        
        if ( is_wp_error( $result ) ) {
            return $result;
        }
        
        $entry = $time_entry->get( $result );
        
        return new \WP_REST_Response( $entry, 201 );
    }
    
    public function update_item( $request ) {
        $time_entry = AOSAI_Time_Entry::get_instance();
        
        $data = $this->normalize_time_entry_payload( (array) $request->get_json_params() );
        $result = $time_entry->update( (int) $request->get_param( 'id' ), $data );
        
        if ( is_wp_error( $result ) ) {
            return $result;
        }
        
        $entry = $time_entry->get( (int) $request->get_param( 'id' ) );
        
        return new \WP_REST_Response( $entry, 200 );
    }
    
    public function delete_item( $request ) {
        $time_entry = AOSAI_Time_Entry::get_instance();
        
        $result = $time_entry->delete( (int) $request->get_param( 'id' ) );
        
        if ( ! $result ) {
            return new \WP_Error( 'rest_cannot_delete', __( 'Cannot delete this time entry.', 'agency-os-ai' ), array( 'status' => 500 ) );
        }
        
        return new \WP_REST_Response( array( 'deleted' => true ), 200 );
    }
    
    public function get_totals( $request ) {
        $time_entry = AOSAI_Time_Entry::get_instance();
        
        $user_id = (int) $request->get_param( 'user_id' );
        if ( ! current_user_can( 'aosai_manage_tasks' ) ) {
            $user_id = get_current_user_id();
        }
        
        $args = array(
            'date_from'  => sanitize_text_field( $request->get_param( 'date_from' ) ?: '' ),
            'date_to'    => sanitize_text_field( $request->get_param( 'date_to' ) ?: '' ),
            'project_id' => (int) $request->get_param( 'project_id' ),
        );
        
        if ( ! empty( $args['project_id'] ) ) {
            $totals = $time_entry->get_project_totals( $args['project_id'], $args );
        } else {
            $totals = $time_entry->get_user_totals( $user_id, $args );
        }
        
        return new \WP_REST_Response( $totals, 200 );
    }
    
    public function get_collection_params() {
        return array(
            'page'       => array( 'type' => 'integer', 'default' => 1 ),
            'per_page'   => array( 'type' => 'integer', 'default' => 50 ),
            'user_id'    => array( 'type' => 'integer' ),
            'task_id'    => array( 'type' => 'integer' ),
            'project_id' => array( 'type' => 'integer' ),
            'billable'   => array( 'type' => 'string' ),
            'invoiced'   => array( 'type' => 'string' ),
            'date'       => array( 'type' => 'string' ),
            'date_from'  => array( 'type' => 'string' ),
            'date_to'    => array( 'type' => 'string' ),
            'orderby'    => array( 'type' => 'string', 'default' => 'start_time' ),
            'order'      => array( 'type' => 'string', 'default' => 'DESC' ),
        );
    }

    private function get_create_params(): array {
        return array(
            'task_id'     => array( 'type' => 'integer' ),
            'project_id'  => array( 'type' => 'integer', 'required' => true ),
            'user_id'     => array( 'type' => 'integer' ),
            'description' => array( 'type' => 'string' ),
            'start_time'  => array( 'type' => 'string' ),
            'end_time'    => array( 'type' => 'string' ),
            'duration'    => array( 'type' => 'number' ),
            'date'        => array( 'type' => 'string' ),
            'billable'    => array( 'type' => 'boolean' ),
        );
    }

    private function normalize_time_entry_payload( array $data ): array {
        if ( empty( $data['start_time'] ) && ! empty( $data['date'] ) ) {
            $date = sanitize_text_field( (string) $data['date'] );
            $hours = isset( $data['duration'] ) ? (float) $data['duration'] : 0.0;
            $seconds = (int) round( max( 0, $hours ) * HOUR_IN_SECONDS );

            if ( preg_match( '/^\d{4}-\d{2}-\d{2}$/', $date ) ) {
                $data['start_time'] = $date . ' 09:00:00';
                $data['end_time']   = gmdate( 'Y-m-d H:i:s', strtotime( $data['start_time'] . ' UTC' ) + $seconds );
            }
        }

        return $data;
    }
}
