<?php
if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

class AOSAI_REST_Comments extends WP_REST_Controller {
    
    protected $namespace = 'aosai/v1';
    
    public function register_routes() {
        // Global comments route: GET /comments?task_id=X, POST /comments with {task_id, content}
        register_rest_route( $this->namespace, '/comments', array(
            array(
                'methods'             => WP_REST_Server::READABLE,
                'callback'            => array( $this, 'get_comments_global' ),
                'permission_callback' => array( $this, 'get_comments_permissions_check' ),
                'args'                => array(
                    'task_id' => array(
                        'validate_callback' => 'is_numeric',
                    ),
                ),
            ),
            array(
                'methods'             => WP_REST_Server::CREATABLE,
                'callback'            => array( $this, 'create_comment_global' ),
                'permission_callback' => array( $this, 'create_comment_permissions_check' ),
            ),
        ) );

        register_rest_route( $this->namespace, '/(?P<type>task|message|milestone|file)/(?P<id>[\d]+)/comments', array(
            array(
                'methods'             => WP_REST_Server::READABLE,
                'callback'            => array( $this, 'get_comments' ),
                'permission_callback' => array( $this, 'get_comments_permissions_check' ),
            ),
            array(
                'methods'             => WP_REST_Server::CREATABLE,
                'callback'            => array( $this, 'create_comment' ),
                'permission_callback' => array( $this, 'create_comment_permissions_check' ),
            ),
        ) );
        
        register_rest_route( $this->namespace, '/comments/(?P<id>[\d]+)', array(
            array(
                'methods'             => WP_REST_Server::EDITABLE,
                'callback'            => array( $this, 'update_comment' ),
                'permission_callback' => array( $this, 'update_comment_permissions_check' ),
            ),
            array(
                'methods'             => WP_REST_Server::DELETABLE,
                'callback'            => array( $this, 'delete_comment' ),
                'permission_callback' => array( $this, 'delete_comment_permissions_check' ),
            ),
        ) );
    }
    
    public function get_comments_permissions_check( $request ) {
        return is_user_logged_in() ? true : new WP_Error( 'rest_not_logged_in', esc_html__( 'You must be logged in.', 'agency-os-ai' ), array( 'status' => 401 ) );
    }
    
    public function create_comment_permissions_check( $request ) {
        return is_user_logged_in() ? true : new WP_Error( 'rest_not_logged_in', esc_html__( 'You must be logged in.', 'agency-os-ai' ), array( 'status' => 401 ) );
    }
    
    public function update_comment_permissions_check( $request ) {
        $comment = AOSAI_Comment::get_instance()->get_comment( absint( $request->get_param( 'id' ) ) );
        if ( ! $comment ) {
            return new WP_Error( 'not_found', esc_html__( 'Comment not found.', 'agency-os-ai' ), array( 'status' => 404 ) );
        }
        if ( ! current_user_can( 'manage_options' ) && (int) ( $comment['created_by'] ?? 0 ) !== get_current_user_id() ) {
            return new WP_Error( 'rest_forbidden', esc_html__( 'You cannot edit this comment.', 'agency-os-ai' ), array( 'status' => 403 ) );
        }
        return true;
    }
    
    public function delete_comment_permissions_check( $request ) {
        $comment = AOSAI_Comment::get_instance()->get_comment( absint( $request->get_param( 'id' ) ) );
        if ( ! $comment ) {
            return new WP_Error( 'not_found', esc_html__( 'Comment not found.', 'agency-os-ai' ), array( 'status' => 404 ) );
        }
        if ( ! current_user_can( 'manage_options' ) && (int) ( $comment['created_by'] ?? 0 ) !== get_current_user_id() ) {
            return new WP_Error( 'rest_forbidden', esc_html__( 'You cannot delete this comment.', 'agency-os-ai' ), array( 'status' => 403 ) );
        }
        return true;
    }
    
    public function get_comments_global( $request ) {
        $task_id    = absint( $request->get_param( 'task_id' ) );
        $message_id = absint( $request->get_param( 'message_id' ) );
        $model      = AOSAI_Comment::get_instance();

        if ( $task_id ) {
            $comments = $model->get_comments( 'task', $task_id );
        } elseif ( $message_id ) {
            $comments = $model->get_comments( 'message', $message_id );
        } else {
            return rest_ensure_response( array() );
        }

        foreach ( $comments as &$comment ) {
            $comment['replies'] = $model->get_replies( (int) $comment['id'] );
        }

        return rest_ensure_response( $comments );
    }

    public function create_comment_global( $request ) {
        $data = $request->get_json_params();
        $model = AOSAI_Comment::get_instance();

        // Determine type from payload
        if ( ! empty( $data['task_id'] ) ) {
            $data['commentable_type'] = 'task';
            $data['commentable_id']   = absint( $data['task_id'] );
        } elseif ( ! empty( $data['message_id'] ) ) {
            $data['commentable_type'] = 'message';
            $data['commentable_id']   = absint( $data['message_id'] );
        } else {
            return new WP_Error( 'missing_target', esc_html__( 'A task_id or message_id is required.', 'agency-os-ai' ), array( 'status' => 400 ) );
        }

        $result = $model->create( $data );
        if ( is_wp_error( $result ) ) {
            return $result;
        }

        $comment = $model->get_comment( $result );
        
        return rest_ensure_response( $comment );
    }

    public function get_comments( $request ) {
        $type = sanitize_key( $request->get_param( 'type' ) );
        $id = absint( $request->get_param( 'id' ) );
        
        $model = AOSAI_Comment::get_instance();
        $comments = $model->get_comments( $type, $id );
        
        foreach ( $comments as &$comment ) {
            $comment['replies'] = $model->get_replies( (int) $comment['id'] );
        }
        
        return rest_ensure_response( $comments );
    }
    
    public function create_comment( $request ) {
        $data = $request->get_json_params();
        $data['commentable_type'] = sanitize_key( $request->get_param( 'type' ) );
        $data['commentable_id'] = absint( $request->get_param( 'id' ) );
        
        $model = AOSAI_Comment::get_instance();
        $result = $model->create( $data );
        
        if ( is_wp_error( $result ) ) {
            return $result;
        }
        
        $comment = $model->get_comment( $result );
        return rest_ensure_response( $comment );
    }
    
    public function update_comment( $request ) {
        $id = absint( $request->get_param( 'id' ) );
        $data = $request->get_json_params();
        
        $model = AOSAI_Comment::get_instance();
        $result = $model->update( $id, $data );
        
        if ( is_wp_error( $result ) ) {
            return $result;
        }

        $comment = $model->get_comment( $id );
        return rest_ensure_response( $comment );
    }
    
    public function delete_comment( $request ) {
        $id = absint( $request->get_param( 'id' ) );
        
        $model = AOSAI_Comment::get_instance();
        $result = $model->delete( $id );
        
        if ( ! $result ) {
            return new WP_Error( 'not_found', esc_html__( 'Comment not found.', 'agency-os-ai' ), array( 'status' => 404 ) );
        }
        
        return new WP_REST_Response( array( 'deleted' => true, 'id' => $id ), 200 );
    }
}
