<?php
if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

class AOSAI_Notification_Service {
    use AOSAI_Singleton;
    
    private function __construct() {}
    
    public function create_notification( array $args ): void {
        global $wpdb;
        $table = $wpdb->prefix . 'aosai_notifications';
        
        $wpdb->insert(
            $table,
            array(
                'user_id'     => absint( $args['user_id'] ),
                'project_id'  => absint( $args['project_id'] ?? 0 ) ?: null,
                'type'        => sanitize_key( $args['type'] ),
                'title'       => sanitize_text_field( $args['title'] ),
                'content'     => sanitize_textarea_field( $args['content'] ?? '' ),
                'object_type' => sanitize_key( $args['object_type'] ?? '' ),
                'object_id'   => absint( $args['object_id'] ?? 0 ),
            ),
            array( '%d', '%d', '%s', '%s', '%s', '%s', '%d' )
        );
        
        if ( $this->should_send_email( $args['user_id'], $args['type'] ) ) {
            $this->send_email_notification( $args );
        }
    }
    
    public function get_user_notifications( int $user_id, array $args = array() ): array {
        global $wpdb;
        $table = $wpdb->prefix . 'aosai_notifications';
        
        $defaults = array(
            'page'     => 1,
            'per_page' => 20,
            'is_read'  => null,
        );
        $args = wp_parse_args( $args, $defaults );
        
        $offset = ( $args['page'] - 1 ) * $args['per_page'];
        $where = "WHERE user_id = %d";
        $params = array( $user_id );
        
        if ( $args['is_read'] !== null ) {
            $where .= " AND is_read = %d";
            $params[] = $args['is_read'] ? 1 : 0;
        }
        
        $params[] = $args['per_page'];
        $params[] = $offset;
        
        return $wpdb->get_results(
            $wpdb->prepare(
                "SELECT * FROM {$table} {$where} ORDER BY created_at DESC LIMIT %d OFFSET %d",
                ...$params
            ),
            ARRAY_A
        );
    }
    
    public function get_unread_count( int $user_id ): int {
        global $wpdb;
        $table = $wpdb->prefix . 'aosai_notifications';
        
        return (int) $wpdb->get_var(
            $wpdb->prepare(
                "SELECT COUNT(*) FROM {$table} WHERE user_id = %d AND is_read = 0",
                $user_id
            )
        );
    }
    
    public function mark_as_read( int $notification_id ): bool {
        global $wpdb;
        $table = $wpdb->prefix . 'aosai_notifications';
        
        $result = $wpdb->update(
            $table,
            array( 'is_read' => 1 ),
            array( 'id' => $notification_id ),
            array( '%d' ),
            array( '%d' )
        );
        
        return $result !== false;
    }
    
    public function mark_all_as_read( int $user_id ): bool {
        global $wpdb;
        $table = $wpdb->prefix . 'aosai_notifications';
        
        $result = $wpdb->update(
            $table,
            array( 'is_read' => 1 ),
            array( 'user_id' => $user_id, 'is_read' => 0 ),
            array( '%d' ),
            array( '%d', '%d' )
        );
        
        return $result !== false;
    }
    
    public function on_task_assigned( int $task_id, int $user_id ): void {
        $task = AOSAI_Task::get_instance()->get( $task_id );
        if ( ! $task ) {
            return;
        }
        
        $assigner = wp_get_current_user();
        
        $this->create_notification( array(
            'user_id'     => $user_id,
            'project_id'  => $task['project_id'],
            'type'        => 'task_assigned',
            'title'       => sprintf(
                esc_html__( '%1$s assigned you to "%2$s"', 'agency-os-ai' ),
                $assigner->display_name,
                $task['title']
            ),
            'object_type' => 'task',
            'object_id'   => $task_id,
        ) );
    }
    
    public function on_task_completed( int $task_id, int $user_id ): void {
        $task = AOSAI_Task::get_instance()->get( $task_id );
        if ( ! $task ) {
            return;
        }
        
        $this->create_notification( array(
            'user_id'     => $user_id,
            'project_id'  => $task['project_id'],
            'type'        => 'task_completed',
            'title'       => sprintf(
                esc_html__( 'Task "%1$s" was marked as complete', 'agency-os-ai' ),
                $task['title']
            ),
            'object_type' => 'task',
            'object_id'   => $task_id,
        ) );
    }
    
    public function on_comment_created( int $comment_id, string $type, int $object_id ): void {
        $comment = AOSAI_Comment::get_instance()->get_replies( $comment_id );
        if ( ! $comment ) {
            return;
        }
        
        $comment = $comment[0] ?? null;
        if ( ! $comment ) {
            return;
        }
        
        $user_id = $comment['created_by'];
        $project_id = 0;
        
        if ( $type === 'task' ) {
            $task = AOSAI_Task::get_instance()->get( $object_id );
            $project_id = $task ? $task['project_id'] : 0;
        }
        
        $this->create_notification( array(
            'user_id'     => $user_id,
            'project_id'  => $project_id,
            'type'        => 'comment_added',
            'title'       => sprintf(
                esc_html__( '%1$s commented on a task', 'agency-os-ai' ),
                $comment['author_name']
            ),
            'object_type' => $type,
            'object_id'   => $object_id,
        ) );
    }
    
    public function on_milestone_completed( int $milestone_id, $milestone ): void {
        if ( ! $milestone ) {
            return;
        }
        
        $this->create_notification( array(
            'user_id'     => $milestone->created_by,
            'project_id'  => $milestone->project_id,
            'type'        => 'milestone_complete',
            'title'       => sprintf(
                esc_html__( 'Milestone "%s" was completed', 'agency-os-ai' ),
                $milestone->title
            ),
            'object_type' => 'milestone',
            'object_id'   => $milestone_id,
        ) );
    }
    
    public function on_message_created( int $message_id, int $project_id ): void {
        $message = AOSAI_Message::get_instance()->get( $message_id );
        if ( ! $message ) {
            return;
        }
        
        $project = AOSAI_Project::get_instance()->get( $project_id );
        if ( ! $project ) {
            return;
        }
        
        foreach ( $project['members'] as $member ) {
            if ( (int) $member['user_id'] === (int) $message['created_by'] ) {
                continue;
            }
            
            $this->create_notification( array(
                'user_id'     => $member['user_id'],
                'project_id'  => $project_id,
                'type'        => 'message_posted',
                'title'       => sprintf(
                    esc_html__( 'New message in %s', 'agency-os-ai' ),
                    $project['title']
                ),
                'content'     => $message['title'],
                'object_type' => 'message',
                'object_id'   => $message_id,
            ) );
        }
    }
    
    private function should_send_email( int $user_id, string $type ): bool {
        if ( get_option( 'aosai_email_notifications', 'yes' ) !== 'yes' ) {
            return false;
        }
        
        return true;
    }
    
    private function send_email_notification( array $args ): void {
        $user = get_user_by( 'id', $args['user_id'] );
        if ( ! $user ) {
            return;
        }
        
        $email = AOSAI_Email_Service::get_instance();
        $email->send_notification( $user, $args );
    }
}
