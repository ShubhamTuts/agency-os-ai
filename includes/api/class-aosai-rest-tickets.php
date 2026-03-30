<?php
if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

class AOSAI_REST_Tickets extends WP_REST_Controller {
    protected $namespace = 'aosai/v1';

    public function register_routes() {
        register_rest_route(
            $this->namespace,
            '/tickets',
            array(
                array(
                    'methods'             => WP_REST_Server::READABLE,
                    'callback'            => array( $this, 'get_items' ),
                    'permission_callback' => array( $this, 'permissions_check' ),
                ),
                array(
                    'methods'             => WP_REST_Server::CREATABLE,
                    'callback'            => array( $this, 'create_item' ),
                    'permission_callback' => array( $this, 'create_permissions_check' ),
                ),
            )
        );

        register_rest_route(
            $this->namespace,
            '/tickets/(?P<id>[\d]+)',
            array(
                array(
                    'methods'             => WP_REST_Server::READABLE,
                    'callback'            => array( $this, 'get_item' ),
                    'permission_callback' => array( $this, 'ticket_access_check' ),
                ),
                array(
                    'methods'             => 'POST, PUT, PATCH',
                    'callback'            => array( $this, 'update_item' ),
                    'permission_callback' => array( $this, 'ticket_access_check' ),
                ),
            )
        );

        register_rest_route(
            $this->namespace,
            '/tickets/(?P<id>[\d]+)/notes',
            array(
                array(
                    'methods'             => WP_REST_Server::READABLE,
                    'callback'            => array( $this, 'get_notes' ),
                    'permission_callback' => array( $this, 'ticket_access_check' ),
                ),
                array(
                    'methods'             => WP_REST_Server::CREATABLE,
                    'callback'            => array( $this, 'create_note' ),
                    'permission_callback' => array( $this, 'ticket_access_check' ),
                ),
            )
        );
    }

    public function permissions_check( $request ) {
        return is_user_logged_in() ? true : new WP_Error( 'rest_not_logged_in', esc_html__( 'You must be logged in.', 'agency-os-ai' ), array( 'status' => 401 ) );
    }

    public function create_permissions_check( $request ) {
        if ( ! is_user_logged_in() ) {
            return new WP_Error( 'rest_not_logged_in', esc_html__( 'You must be logged in.', 'agency-os-ai' ), array( 'status' => 401 ) );
        }

        if ( ! current_user_can( 'aosai_submit_tickets' ) && ! current_user_can( 'read' ) ) {
            return new WP_Error( 'rest_forbidden', esc_html__( 'You cannot create tickets.', 'agency-os-ai' ), array( 'status' => 403 ) );
        }

        return true;
    }

    public function ticket_access_check( $request ) {
        if ( ! is_user_logged_in() ) {
            return new WP_Error( 'rest_not_logged_in', esc_html__( 'You must be logged in.', 'agency-os-ai' ), array( 'status' => 401 ) );
        }

        $ticket = AOSAI_Ticket::get_instance()->get( absint( $request->get_param( 'id' ) ) );
        if ( ! $ticket ) {
            return new WP_Error( 'not_found', esc_html__( 'Ticket not found.', 'agency-os-ai' ), array( 'status' => 404 ) );
        }

        $user_id = get_current_user_id();
        if ( current_user_can( 'manage_options' ) || current_user_can( 'aosai_manage_tickets' ) ) {
            return true;
        }

        if ( (int) $ticket['requester_id'] === $user_id || (int) ( $ticket['assignee_id'] ?? 0 ) === $user_id ) {
            return true;
        }

        return new WP_Error( 'rest_forbidden', esc_html__( 'You do not have access to this ticket.', 'agency-os-ai' ), array( 'status' => 403 ) );
    }

    public function get_items( $request ) {
        $args = array(
            'page'          => absint( $request->get_param( 'page' ) ) ?: 1,
            'per_page'      => min( absint( $request->get_param( 'per_page' ) ) ?: 20, 100 ),
            'status'        => sanitize_key( (string) $request->get_param( 'status' ) ),
            'search'        => sanitize_text_field( (string) $request->get_param( 'search' ) ),
            'project_id'    => absint( $request->get_param( 'project_id' ) ),
            'department_id' => absint( $request->get_param( 'department_id' ) ),
        );

        return rest_ensure_response( AOSAI_Ticket::get_instance()->get_tickets_for_user( get_current_user_id(), $args ) );
    }

    public function create_item( $request ) {
        $data = $request->get_json_params();
        $ticket_id = AOSAI_Ticket::get_instance()->create( $data );
        if ( is_wp_error( $ticket_id ) ) {
            return $ticket_id;
        }

        return rest_ensure_response( AOSAI_Ticket::get_instance()->get( $ticket_id ) );
    }

    public function get_item( $request ) {
        return rest_ensure_response( AOSAI_Ticket::get_instance()->get( absint( $request->get_param( 'id' ) ) ) );
    }

    public function update_item( $request ) {
        $ticket_id = absint( $request->get_param( 'id' ) );
        $result    = AOSAI_Ticket::get_instance()->update( $ticket_id, $request->get_json_params() );
        if ( is_wp_error( $result ) ) {
            return $result;
        }

        return rest_ensure_response( AOSAI_Ticket::get_instance()->get( $ticket_id ) );
    }

    public function get_notes( $request ) {
        return rest_ensure_response( AOSAI_Ticket::get_instance()->get_notes( absint( $request->get_param( 'id' ) ) ) );
    }

    public function create_note( $request ) {
        $ticket_id = absint( $request->get_param( 'id' ) );
        $data      = $request->get_json_params();
        $note_id   = AOSAI_Ticket::get_instance()->add_note( $ticket_id, (string) ( $data['content'] ?? '' ) );
        if ( is_wp_error( $note_id ) ) {
            return $note_id;
        }

        return rest_ensure_response( AOSAI_Ticket::get_instance()->get_notes( $ticket_id ) );
    }
}

