<?php
if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

class AOSAI_REST_Notifications extends WP_REST_Controller {

    protected $namespace = 'aosai/v1';

    public function register_routes() {
        register_rest_route(
            $this->namespace,
            '/notifications',
            array(
                array(
                    'methods'             => WP_REST_Server::READABLE,
                    'callback'            => array( $this, 'get_notifications' ),
                    'permission_callback' => array( $this, 'permissions_check' ),
                ),
            )
        );

        register_rest_route(
            $this->namespace,
            '/notifications/unread-count',
            array(
                array(
                    'methods'             => WP_REST_Server::READABLE,
                    'callback'            => array( $this, 'get_unread_count' ),
                    'permission_callback' => array( $this, 'permissions_check' ),
                ),
            )
        );

        register_rest_route(
            $this->namespace,
            '/notifications/(?P<id>[\d]+)/read',
            array(
                array(
                    'methods'             => WP_REST_Server::CREATABLE,
                    'callback'            => array( $this, 'mark_as_read' ),
                    'permission_callback' => array( $this, 'notification_owner_check' ),
                ),
            )
        );

        register_rest_route(
            $this->namespace,
            '/notifications/read-all',
            array(
                array(
                    'methods'             => WP_REST_Server::CREATABLE,
                    'callback'            => array( $this, 'mark_all_as_read' ),
                    'permission_callback' => array( $this, 'permissions_check' ),
                ),
            )
        );
    }

    public function permissions_check( $request ) {
        if ( ! is_user_logged_in() ) {
            return new WP_Error( 'rest_not_logged_in', esc_html__( 'You must be logged in.', 'agency-os-ai' ), array( 'status' => 401 ) );
        }

        return true;
    }

    public function notification_owner_check( $request ) {
        $permission = $this->permissions_check( $request );
        if ( is_wp_error( $permission ) ) {
            return $permission;
        }

        $notification = AOSAI_Notification_Service::get_instance()->get_notification( absint( $request->get_param( 'id' ) ) );
        if ( ! $notification ) {
            return new WP_Error( 'rest_notification_not_found', esc_html__( 'Notification not found.', 'agency-os-ai' ), array( 'status' => 404 ) );
        }

        if ( (int) $notification['user_id'] !== get_current_user_id() ) {
            return new WP_Error( 'rest_forbidden', esc_html__( 'You do not have access to this notification.', 'agency-os-ai' ), array( 'status' => 403 ) );
        }

        return true;
    }

    public function get_notifications( $request ) {
        $notifications = AOSAI_Notification_Service::get_instance()->get_user_notifications(
            get_current_user_id(),
            array(
                'page'     => absint( $request->get_param( 'page' ) ) ?: 1,
                'per_page' => min( absint( $request->get_param( 'per_page' ) ) ?: 10, 50 ),
                'is_read'  => $request->has_param( 'is_read' ) ? rest_sanitize_boolean( $request->get_param( 'is_read' ) ) : null,
            )
        );

        return rest_ensure_response( $notifications );
    }

    public function get_unread_count( $request ) {
        return rest_ensure_response(
            array(
                'count' => AOSAI_Notification_Service::get_instance()->get_unread_count( get_current_user_id() ),
            )
        );
    }

    public function mark_as_read( $request ) {
        $notification_id = absint( $request->get_param( 'id' ) );
        $success = AOSAI_Notification_Service::get_instance()->mark_as_read( $notification_id );

        return rest_ensure_response(
            array(
                'success' => (bool) $success,
                'id'      => $notification_id,
            )
        );
    }

    public function mark_all_as_read( $request ) {
        $success = AOSAI_Notification_Service::get_instance()->mark_all_as_read( get_current_user_id() );

        return rest_ensure_response(
            array(
                'success' => (bool) $success,
            )
        );
    }
}
