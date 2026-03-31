<?php
if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

trait AOSAI_REST_Validation {
    
    protected function validate_required_fields( array $data, array $required ): \WP_Error|true {
        $missing = array();
        foreach ( $required as $field ) {
            if ( ! isset( $data[ $field ] ) || ( is_string( $data[ $field ] ) && trim( $data[ $field ] ) === '' ) ) {
                $missing[] = $field;
            }
        }
        
        if ( ! empty( $missing ) ) {
            return new \WP_Error(
                'missing_required_fields',
                sprintf(
                    esc_html__( 'Missing required fields: %s', 'agency-os-ai' ),
                    implode( ', ', $missing )
                ),
                array( 'status' => 400 )
            );
        }
        
        return true;
    }
    
    protected function sanitize_task_input( array $input ): array {
        $sanitized = array();
        
        if ( isset( $input['title'] ) ) {
            $sanitized['title'] = sanitize_text_field( wp_unslash( $input['title'] ) );
        }
        if ( isset( $input['description'] ) ) {
            $sanitized['description'] = wp_kses_post( wp_unslash( $input['description'] ) );
        }
        if ( isset( $input['status'] ) ) {
            $allowed = array( 'open', 'in_progress', 'done', 'overdue', 'cancelled' );
            $sanitized['status'] = in_array( $input['status'], $allowed, true ) ? $input['status'] : 'open';
        }
        if ( isset( $input['priority'] ) ) {
            $allowed = array( 'low', 'medium', 'high', 'urgent' );
            $sanitized['priority'] = in_array( $input['priority'], $allowed, true ) ? $input['priority'] : 'medium';
        }
        if ( isset( $input['due_date'] ) ) {
            $sanitized['due_date'] = $this->sanitize_date( $input['due_date'] );
        }
        if ( isset( $input['start_date'] ) ) {
            $sanitized['start_date'] = $this->sanitize_date( $input['start_date'] );
        }
        if ( isset( $input['estimated_hours'] ) ) {
            $sanitized['estimated_hours'] = max( 0, floatval( $input['estimated_hours'] ) );
        }
        if ( isset( $input['sort_order'] ) ) {
            $sanitized['sort_order'] = intval( $input['sort_order'] );
        }
        if ( isset( $input['is_private'] ) ) {
            $sanitized['is_private'] = ! empty( $input['is_private'] ) ? 1 : 0;
        }
        if ( isset( $input['is_recurring'] ) ) {
            $sanitized['is_recurring'] = ! empty( $input['is_recurring'] ) ? 1 : 0;
        }
        if ( isset( $input['recurrence_rule'] ) ) {
            $sanitized['recurrence_rule'] = sanitize_text_field( wp_unslash( $input['recurrence_rule'] ) );
        }
        if ( isset( $input['kanban_column'] ) ) {
            $sanitized['kanban_column'] = sanitize_key( wp_unslash( $input['kanban_column'] ) );
        }
        if ( isset( $input['task_list_id'] ) ) {
            $sanitized['task_list_id'] = absint( $input['task_list_id'] );
        }
        if ( isset( $input['project_id'] ) ) {
            $sanitized['project_id'] = absint( $input['project_id'] );
        }
        if ( isset( $input['milestone_id'] ) ) {
            $sanitized['milestone_id'] = absint( $input['milestone_id'] ) ?: null;
        }
        if ( isset( $input['parent_id'] ) ) {
            $sanitized['parent_id'] = absint( $input['parent_id'] ) ?: null;
        }
        
        return $sanitized;
    }
    
    protected function sanitize_project_input( array $input ): array {
        $sanitized = array();
        
        if ( isset( $input['title'] ) ) {
            $sanitized['title'] = sanitize_text_field( wp_unslash( $input['title'] ) );
        }
        if ( isset( $input['description'] ) ) {
            $sanitized['description'] = wp_kses_post( wp_unslash( $input['description'] ) );
        }
        if ( isset( $input['status'] ) ) {
            $allowed = array( 'active', 'archived', 'completed', 'on_hold' );
            $sanitized['status'] = in_array( $input['status'], $allowed, true ) ? $input['status'] : 'active';
        }
        if ( isset( $input['category'] ) ) {
            $sanitized['category'] = sanitize_text_field( wp_unslash( $input['category'] ) );
        }
        if ( isset( $input['color'] ) ) {
            $sanitized['color'] = preg_match( '/^#[a-fA-F0-9]{6}$/', $input['color'] ) ? $input['color'] : '#6366f1';
        }
        if ( isset( $input['budget'] ) ) {
            $sanitized['budget'] = max( 0, floatval( $input['budget'] ) );
        }
        if ( isset( $input['currency'] ) ) {
            $sanitized['currency'] = sanitize_text_field( wp_unslash( $input['currency'] ) );
        }
        if ( isset( $input['start_date'] ) ) {
            $sanitized['start_date'] = $this->sanitize_date( $input['start_date'] );
        }
        if ( isset( $input['end_date'] ) ) {
            $sanitized['end_date'] = $this->sanitize_date( $input['end_date'] );
        }
        
        return $sanitized;
    }
    
    public function sanitize_date( $date ): ?string {
        if ( empty( $date ) ) {
            return null;
        }
        $date = sanitize_text_field( wp_unslash( $date ) );
        if ( preg_match( '/^\d{4}-\d{2}-\d{2}$/', $date ) ) {
            return $date;
        }
        return null;
    }
    
    protected function check_project_access( int $user_id, int $project_id ): bool {
        if ( user_can( $user_id, 'manage_options' ) ) {
            return true;
        }
        
        global $wpdb;
        $member = $wpdb->get_row(
            $wpdb->prepare(
                "SELECT id FROM {$wpdb->prefix}aosai_project_users WHERE project_id = %d AND user_id = %d",
                $project_id,
                $user_id
            )
        );
        
        return ! empty( $member );
    }
    
    protected function get_user_project_role( int $user_id, int $project_id ): string {
        if ( user_can( $user_id, 'manage_options' ) ) {
            return 'manager';
        }
        
        global $wpdb;
        $role = $wpdb->get_var(
            $wpdb->prepare(
                "SELECT role FROM {$wpdb->prefix}aosai_project_users WHERE project_id = %d AND user_id = %d",
                $project_id,
                $user_id
            )
        );
        
        return $role ?: '';
    }
}
