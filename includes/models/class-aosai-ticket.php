<?php
if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

class AOSAI_Ticket {
    use AOSAI_Singleton;

    private function __construct() {}

    public function get_table(): string {
        global $wpdb;
        return esc_sql( $wpdb->prefix . 'aosai_tickets' );
    }

    public function get( int $id ): ?array {
        global $wpdb;

        $table             = $this->get_table();
        $departments_table = esc_sql( $wpdb->prefix . 'aosai_departments' );
        $projects_table    = esc_sql( $wpdb->prefix . 'aosai_projects' );
        $users_table       = esc_sql( $wpdb->users );
        $ticket = $wpdb->get_row(
            $wpdb->prepare(
                'SELECT t.*, d.name AS department_name, d.color AS department_color,
                        p.title AS project_name,
                        requester.display_name AS requester_name,
                        requester.user_email AS requester_email,
                        assignee.display_name AS assignee_name
                FROM ' . $table . ' t
                LEFT JOIN ' . $departments_table . ' d ON t.department_id = d.id
                LEFT JOIN ' . $projects_table . ' p ON t.project_id = p.id
                LEFT JOIN ' . $users_table . ' requester ON t.requester_id = requester.ID
                LEFT JOIN ' . $users_table . ' assignee ON t.assignee_id = assignee.ID
                WHERE t.id = %d',
                $id
            ),
            ARRAY_A
        );

        return $ticket ? $this->enrich( $ticket ) : null;
    }

    public function get_tickets_for_user( int $user_id, array $args = array() ): array {
        global $wpdb;

        $table = $this->get_table();
        $args  = wp_parse_args(
            $args,
            array(
                'page'          => 1,
                'per_page'      => 20,
                'status'        => '',
                'search'        => '',
                'project_id'    => 0,
                'department_id' => 0,
            )
        );

        $per_page = max( 1, (int) $args['per_page'] );
        $page     = max( 1, (int) $args['page'] );
        $offset   = ( $page - 1 ) * $per_page;
        $params   = array();
        $sql      = 'SELECT t.*, d.name AS department_name, d.color AS department_color,
                    p.title AS project_name,
                    requester.display_name AS requester_name,
                    requester.user_email AS requester_email,
                    assignee.display_name AS assignee_name
            FROM ' . $table . ' t
            LEFT JOIN ' . esc_sql( $wpdb->prefix . 'aosai_departments' ) . ' d ON t.department_id = d.id
            LEFT JOIN ' . esc_sql( $wpdb->prefix . 'aosai_projects' ) . ' p ON t.project_id = p.id
            LEFT JOIN ' . $wpdb->users . ' requester ON t.requester_id = requester.ID
            LEFT JOIN ' . $wpdb->users . ' assignee ON t.assignee_id = assignee.ID
            WHERE 1=1';

        if ( ! user_can( $user_id, 'manage_options' ) && ! user_can( $user_id, 'aosai_manage_tickets' ) ) {
            $sql .= ' AND (t.requester_id = %d OR t.assignee_id = %d)';
            $params[] = $user_id;
            $params[] = $user_id;
        }

        if ( ! empty( $args['status'] ) ) {
            $sql .= ' AND t.status = %s';
            $params[] = sanitize_key( (string) $args['status'] );
        }

        if ( ! empty( $args['project_id'] ) ) {
            $sql .= ' AND t.project_id = %d';
            $params[] = absint( $args['project_id'] );
        }

        if ( ! empty( $args['department_id'] ) ) {
            $sql .= ' AND t.department_id = %d';
            $params[] = absint( $args['department_id'] );
        }

        if ( ! empty( $args['search'] ) ) {
            $search = '%' . $wpdb->esc_like( sanitize_text_field( (string) $args['search'] ) ) . '%';
            $sql .= ' AND (t.subject LIKE %s OR t.content LIKE %s OR p.title LIKE %s)';
            $params[] = $search;
            $params[] = $search;
            $params[] = $search;
        }

        $sql .= ' ORDER BY t.updated_at DESC LIMIT %d OFFSET %d';
        $params[] = $per_page;
        $params[] = $offset;
        $sql = $wpdb->prepare( $sql, $params );

        $tickets = $wpdb->get_results( $sql, ARRAY_A ) ?: array();
        return array_map( array( $this, 'enrich' ), $tickets );
    }

    public function create( array $data ): int|\WP_Error {
        global $wpdb;

        $table     = $this->get_table();
        $sanitized = $this->sanitize_input( $data );

        if ( empty( $sanitized['subject'] ) ) {
            return new \WP_Error( 'missing_subject', esc_html__( 'Ticket subject is required.', 'agency-os-ai' ) );
        }

        if ( empty( $sanitized['content'] ) ) {
            return new \WP_Error( 'missing_content', esc_html__( 'Ticket description is required.', 'agency-os-ai' ) );
        }

        if ( ! empty( $sanitized['project_id'] ) && ! aosai_user_can_access_project( get_current_user_id(), (int) $sanitized['project_id'] ) && ! current_user_can( 'manage_options' ) ) {
            return new \WP_Error( 'forbidden_project', esc_html__( 'You cannot create a ticket for that project.', 'agency-os-ai' ), array( 'status' => 403 ) );
        }

        if ( empty( $sanitized['department_id'] ) ) {
            $department = AOSAI_Department::get_instance()->suggest_department( $sanitized['subject'], $sanitized['content'] );
            $sanitized['department_id'] = $department ? (int) $department['id'] : 0;
        }

        if ( empty( $sanitized['status'] ) ) {
            $sanitized['status'] = 'open';
        }

        if ( empty( $sanitized['priority'] ) ) {
            $sanitized['priority'] = get_option( 'aosai_ticket_default_priority', 'medium' );
        }

        if ( empty( $sanitized['source'] ) ) {
            $sanitized['source'] = 'portal';
        }

        if ( ! isset( $sanitized['ai_summary'] ) ) {
            $sanitized['ai_summary'] = '';
        }

        $sanitized['requester_id'] = get_current_user_id();

        $result = $wpdb->insert(
            $table,
            array(
                'project_id'     => $sanitized['project_id'] ?: null,
                'department_id'  => $sanitized['department_id'] ?: null,
                'requester_id'   => $sanitized['requester_id'],
                'assignee_id'    => $sanitized['assignee_id'] ?: null,
                'subject'        => $sanitized['subject'],
                'content'        => $sanitized['content'],
                'status'         => $sanitized['status'],
                'priority'       => $sanitized['priority'],
                'source'         => $sanitized['source'],
                'ai_summary'     => $sanitized['ai_summary'],
            ),
            array( '%d', '%d', '%d', '%d', '%s', '%s', '%s', '%s', '%s', '%s' )
        );

        if ( false === $result ) {
            return new \WP_Error( 'db_error', esc_html__( 'Failed to create ticket.', 'agency-os-ai' ) );
        }

        $ticket_id = (int) $wpdb->insert_id;

        if ( isset( $data['tags'] ) ) {
            AOSAI_Tag::get_instance()->sync_object_tags( 'ticket', $ticket_id, $data['tags'], 'ticket' );
        }

        aosai_log_activity(
            array(
                'project_id'   => (int) ( $sanitized['project_id'] ?: 0 ),
                'action'       => 'created',
                'object_type'  => 'ticket',
                'object_id'    => $ticket_id,
                'meta'         => array( 'department_id' => $sanitized['department_id'] ?: 0 ),
            )
        );

        $ticket = $this->get( $ticket_id );
        if ( $ticket ) {
            AOSAI_Email_Service::get_instance()->send_ticket_created_emails( $ticket );

            if ( ! empty( $ticket['assignee_id'] ) ) {
                AOSAI_Notification_Service::get_instance()->create_notification(
                    array(
                        'user_id'     => (int) $ticket['assignee_id'],
                        'project_id'  => (int) ( $ticket['project_id'] ?? 0 ),
                        'type'        => 'ticket_assigned',
                        /* translators: %s: ticket subject */
                        'title'       => sprintf( esc_html__( 'New ticket assigned: %s', 'agency-os-ai' ), $ticket['subject'] ),
                        'content'     => wp_trim_words( (string) $ticket['content'], 18 ),
                        'object_type' => 'ticket',
                        'object_id'   => $ticket_id,
                    )
                );
            }

            AOSAI_Webhook_Service::get_instance()->dispatch( 'ticket.created', $ticket );
        }

        return $ticket_id;
    }

    public function update( int $id, array $data ): bool|\WP_Error {
        global $wpdb;

        $table  = $this->get_table();
        $existing_ticket = $this->get( $id );
        if ( ! $existing_ticket ) {
            return new \WP_Error( 'not_found', esc_html__( 'Ticket not found.', 'agency-os-ai' ) );
        }

        $sanitized = $this->sanitize_input( $data );
        if ( empty( $sanitized ) ) {
            return true;
        }

        $result = $wpdb->update( $table, $sanitized, array( 'id' => $id ) );
        if ( false === $result ) {
            return new \WP_Error( 'db_error', esc_html__( 'Failed to update ticket.', 'agency-os-ai' ) );
        }

        if ( array_key_exists( 'tags', $data ) ) {
            AOSAI_Tag::get_instance()->sync_object_tags( 'ticket', $id, $data['tags'], 'ticket' );
        }

        $updated_ticket = $this->get( $id );
        if ( $updated_ticket ) {
            $this->handle_automations_after_update( $existing_ticket, $updated_ticket );
        }

        aosai_log_activity(
            array(
                'project_id'  => (int) ( $existing_ticket['project_id'] ?? 0 ),
                'action'      => 'updated',
                'object_type' => 'ticket',
                'object_id'   => $id,
            )
        );

        if ( $updated_ticket ) {
            AOSAI_Webhook_Service::get_instance()->dispatch( 'ticket.updated', $updated_ticket );
        }

        return true;
    }

    public function get_notes( int $ticket_id ): array {
        global $wpdb;
        $comments_table = esc_sql( $wpdb->prefix . 'aosai_comments' );
        $users_table    = esc_sql( $wpdb->users );

        return $wpdb->get_results(
            $wpdb->prepare(
                'SELECT c.*, u.display_name AS author_name
                FROM ' . $comments_table . ' c
                LEFT JOIN ' . $users_table . ' u ON c.created_by = u.ID
                WHERE c.commentable_type = %s AND c.commentable_id = %d
                ORDER BY c.created_at ASC',
                'ticket',
                $ticket_id
            ),
            ARRAY_A
        ) ?: array();
    }

    public function add_note( int $ticket_id, string $content ): int|\WP_Error {
        global $wpdb;
        $comments_table = esc_sql( $wpdb->prefix . 'aosai_comments' );
        $users_table    = esc_sql( $wpdb->users );

        $content = wp_kses_post( $content );
        if ( '' === trim( wp_strip_all_tags( $content ) ) ) {
            return new \WP_Error( 'missing_content', esc_html__( 'Note content is required.', 'agency-os-ai' ) );
        }

        $result = $wpdb->insert(
            $comments_table,
            array(
                'commentable_type' => 'ticket',
                'commentable_id'   => $ticket_id,
                'content'          => $content,
                'created_by'       => get_current_user_id(),
            ),
            array( '%s', '%d', '%s', '%d' )
        );

        if ( false === $result ) {
            return new \WP_Error( 'db_error', esc_html__( 'Failed to add ticket note.', 'agency-os-ai' ) );
        }

        $note_id = (int) $wpdb->insert_id;
        $note    = $wpdb->get_row(
            $wpdb->prepare(
                'SELECT c.*, u.display_name AS author_name
                FROM ' . $comments_table . ' c
                LEFT JOIN ' . $users_table . ' u ON c.created_by = u.ID
                WHERE c.id = %d',
                $note_id
            ),
            ARRAY_A
        );

        AOSAI_Webhook_Service::get_instance()->dispatch(
            'ticket.note_added',
            array(
                'ticket_id' => $ticket_id,
                'note'      => $note ?: array(
                    'id'      => $note_id,
                    'content' => $content,
                ),
            )
        );

        return $note_id;
    }

    private function handle_automations_after_update( array $existing_ticket, array $updated_ticket ): void {
        $this->maybe_record_status_note( $existing_ticket, $updated_ticket );
        $this->maybe_record_assignment_note( $existing_ticket, $updated_ticket );
        $this->maybe_record_department_note( $existing_ticket, $updated_ticket );
        $this->maybe_notify_requester_of_status_change( $existing_ticket, $updated_ticket );
        $this->maybe_notify_assignee_of_assignment( $existing_ticket, $updated_ticket );
    }

    private function enrich( array $ticket ): array {
        $ticket['tags']        = AOSAI_Tag::get_instance()->get_object_tags( 'ticket', (int) $ticket['id'] );
        $ticket['notes']       = $this->get_notes( (int) $ticket['id'] );
        $ticket['notes_count'] = count( $ticket['notes'] );
        return $ticket;
    }

    private function maybe_record_status_note( array $existing_ticket, array $updated_ticket ): void {
        if ( (string) ( $existing_ticket['status'] ?? '' ) === (string) ( $updated_ticket['status'] ?? '' ) ) {
            return;
        }

        $this->insert_system_note(
            (int) $updated_ticket['id'],
            sprintf(
                /* translators: 1: old status, 2: new status */
                __( 'Status changed from %1$s to %2$s.', 'agency-os-ai' ),
                ucwords( str_replace( '_', ' ', (string) $existing_ticket['status'] ) ),
                ucwords( str_replace( '_', ' ', (string) $updated_ticket['status'] ) )
            )
        );
    }

    private function maybe_record_assignment_note( array $existing_ticket, array $updated_ticket ): void {
        if ( (int) ( $existing_ticket['assignee_id'] ?? 0 ) === (int) ( $updated_ticket['assignee_id'] ?? 0 ) ) {
            return;
        }

        $assignee_name = $updated_ticket['assignee_name'] ?? __( 'Unassigned', 'agency-os-ai' );
        $message = (int) ( $updated_ticket['assignee_id'] ?? 0 ) > 0
            /* translators: %s: assignee display name */
            ? sprintf( __( 'Assigned to %s.', 'agency-os-ai' ), $assignee_name )
            : __( 'Ticket was unassigned.', 'agency-os-ai' );

        $this->insert_system_note( (int) $updated_ticket['id'], $message );
    }

    private function maybe_record_department_note( array $existing_ticket, array $updated_ticket ): void {
        if ( (int) ( $existing_ticket['department_id'] ?? 0 ) === (int) ( $updated_ticket['department_id'] ?? 0 ) ) {
            return;
        }

        $department_name = $updated_ticket['department_name'] ?? __( 'General', 'agency-os-ai' );
        $this->insert_system_note(
            (int) $updated_ticket['id'],
            /* translators: %s: department name */
            sprintf( __( 'Department updated to %s.', 'agency-os-ai' ), $department_name )
        );
    }

    private function maybe_notify_requester_of_status_change( array $existing_ticket, array $updated_ticket ): void {
        if ( (string) ( $existing_ticket['status'] ?? '' ) === (string) ( $updated_ticket['status'] ?? '' ) ) {
            return;
        }

        AOSAI_Email_Service::get_instance()->send_ticket_status_updated_email(
            $updated_ticket,
            (string) ( $existing_ticket['status'] ?? 'open' )
        );
    }

    private function maybe_notify_assignee_of_assignment( array $existing_ticket, array $updated_ticket ): void {
        $existing_assignee = (int) ( $existing_ticket['assignee_id'] ?? 0 );
        $new_assignee      = (int) ( $updated_ticket['assignee_id'] ?? 0 );

        if ( $existing_assignee === $new_assignee || $new_assignee <= 0 ) {
            return;
        }

        $user = get_userdata( $new_assignee );
        if ( ! $user instanceof \WP_User ) {
            return;
        }

        AOSAI_Notification_Service::get_instance()->create_notification(
            array(
                'user_id'     => $new_assignee,
                'project_id'  => (int) ( $updated_ticket['project_id'] ?? 0 ),
                'type'        => 'ticket_assigned',
                /* translators: %s: ticket subject */
                'title'       => sprintf( esc_html__( 'Ticket assigned: %s', 'agency-os-ai' ), $updated_ticket['subject'] ),
                'content'     => wp_trim_words( (string) $updated_ticket['content'], 18 ),
                'object_type' => 'ticket',
                'object_id'   => (int) $updated_ticket['id'],
            )
        );

        AOSAI_Email_Service::get_instance()->send_ticket_assigned_email( $user, $updated_ticket );
    }

    private function insert_system_note( int $ticket_id, string $content ): void {
        global $wpdb;
        $comments_table = esc_sql( $wpdb->prefix . 'aosai_comments' );

        $content = trim( wp_strip_all_tags( $content ) );
        if ( '' === $content ) {
            return;
        }

        $wpdb->insert(
            $comments_table,
            array(
                'commentable_type' => 'ticket',
                'commentable_id'   => $ticket_id,
                'content'          => wp_kses_post( $content ),
                'created_by'       => max( 1, get_current_user_id() ),
            ),
            array( '%s', '%d', '%s', '%d' )
        );
    }

    private function sanitize_input( array $input ): array {
        $sanitized = array();

        if ( isset( $input['project_id'] ) ) {
            $sanitized['project_id'] = absint( $input['project_id'] );
        }
        if ( isset( $input['department_id'] ) ) {
            $sanitized['department_id'] = absint( $input['department_id'] );
        }
        if ( isset( $input['assignee_id'] ) ) {
            $sanitized['assignee_id'] = absint( $input['assignee_id'] );
        }
        if ( isset( $input['subject'] ) ) {
            $sanitized['subject'] = sanitize_text_field( (string) $input['subject'] );
        }
        if ( isset( $input['content'] ) ) {
            $sanitized['content'] = wp_kses_post( (string) $input['content'] );
        }
        if ( isset( $input['status'] ) ) {
            $status = sanitize_key( (string) $input['status'] );
            $sanitized['status'] = in_array( $status, array( 'open', 'in_progress', 'waiting', 'resolved', 'closed' ), true ) ? $status : 'open';
        }
        if ( isset( $input['priority'] ) ) {
            $priority = sanitize_key( (string) $input['priority'] );
            $sanitized['priority'] = in_array( $priority, array( 'low', 'medium', 'high', 'urgent' ), true ) ? $priority : 'medium';
        }
        if ( isset( $input['source'] ) ) {
            $sanitized['source'] = sanitize_key( (string) $input['source'] );
        }
        if ( isset( $input['ai_summary'] ) ) {
            $sanitized['ai_summary'] = sanitize_textarea_field( (string) $input['ai_summary'] );
        }

        return $sanitized;
    }
}

