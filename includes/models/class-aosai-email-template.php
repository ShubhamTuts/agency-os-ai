<?php
if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

class AOSAI_Email_Template {
    use AOSAI_Singleton;
    
    private function __construct() {}
    
    public function get_table(): string {
        global $wpdb;
        return $wpdb->prefix . 'aosai_email_templates';
    }
    
    public function get_all( string $type = '' ): array {
        global $wpdb;
        $table = $this->get_table();
        
        if ( ! empty( $type ) ) {
            $templates = $wpdb->get_results(
                $wpdb->prepare( "SELECT * FROM {$table} WHERE type = %s OR is_default = 1 ORDER BY is_default DESC, name ASC", $type ),
                ARRAY_A
            );
        } else {
            $templates = $wpdb->get_results(
                "SELECT * FROM {$table} ORDER BY type, name ASC",
                ARRAY_A
            );
        }
        
        foreach ( $templates as &$template ) {
            $template['variables'] = maybe_unserialize( $template['variables'] );
        }
        
        return $templates;
    }
    
    public function get( int $id ): ?array {
        global $wpdb;
        $table = $this->get_table();
        
        $template = $wpdb->get_row(
            $wpdb->prepare( "SELECT * FROM {$table} WHERE id = %d", $id ),
            ARRAY_A
        );
        
        if ( ! $template ) {
            return null;
        }
        
        $template['variables'] = maybe_unserialize( $template['variables'] );
        
        return $template;
    }
    
    public function get_by_slug( string $slug, string $type = '' ): ?array {
        global $wpdb;
        $table = $this->get_table();
        
        if ( ! empty( $type ) ) {
            $template = $wpdb->get_row(
                $wpdb->prepare( "SELECT * FROM {$table} WHERE slug = %s AND type = %s", $slug, $type ),
                ARRAY_A
            );
        } else {
            $template = $wpdb->get_row(
                $wpdb->prepare( "SELECT * FROM {$table} WHERE slug = %s LIMIT 1", $slug ),
                ARRAY_A
            );
        }
        
        if ( ! $template ) {
            return null;
        }
        
        $template['variables'] = maybe_unserialize( $template['variables'] );
        
        return $template;
    }
    
    public function create( array $data ): int|\WP_Error {
        global $wpdb;
        $table = $this->get_table();
        
        $sanitized = $this->sanitize_input( $data );
        
        if ( empty( $sanitized['name'] ) ) {
            return new \WP_Error( 'missing_name', esc_html__( 'Template name is required.', 'agency-os-ai' ) );
        }
        
        if ( empty( $sanitized['slug'] ) ) {
            $sanitized['slug'] = sanitize_title( $sanitized['name'] );
        }
        
        $existing = $this->get_by_slug( $sanitized['slug'], $sanitized['type'] );
        if ( $existing ) {
            return new \WP_Error( 'duplicate_slug', esc_html__( 'A template with this slug already exists for this type.', 'agency-os-ai' ) );
        }
        
        $result = $wpdb->insert(
            $table,
            array(
                'name'       => $sanitized['name'],
                'slug'       => $sanitized['slug'],
                'type'       => $sanitized['type'] ?? 'general',
                'subject'    => $sanitized['subject'] ?? '',
                'body'       => $sanitized['body'] ?? '',
                'variables'  => maybe_serialize( $sanitized['variables'] ?? array() ),
                'is_active'  => $sanitized['is_active'] ?? 1,
                'is_default' => $sanitized['is_default'] ?? 0,
            ),
            array( '%s', '%s', '%s', '%s', '%s', '%s', '%d', '%d' )
        );
        
        if ( false === $result ) {
            return new \WP_Error( 'db_error', esc_html__( 'Failed to create email template.', 'agency-os-ai' ) );
        }
        
        return $wpdb->insert_id;
    }
    
    public function update( int $id, array $data ): bool|\WP_Error {
        global $wpdb;
        $table = $this->get_table();
        
        $template = $this->get( $id );
        if ( ! $template ) {
            return new \WP_Error( 'not_found', esc_html__( 'Template not found.', 'agency-os-ai' ) );
        }
        
        $sanitized = $this->sanitize_input( $data );
        
        if ( empty( $sanitized ) ) {
            return true;
        }
        
        $sanitized['updated_at'] = current_time( 'mysql' );
        
        if ( isset( $sanitized['variables'] ) ) {
            $sanitized['variables'] = maybe_serialize( $sanitized['variables'] );
        }
        
        $format = array();
        foreach ( $sanitized as $key => $value ) {
            if ( in_array( $key, array( 'is_active', 'is_default' ), true ) ) {
                $format[] = '%d';
            } else {
                $format[] = '%s';
            }
        }
        
        $result = $wpdb->update(
            $table,
            $sanitized,
            array( 'id' => $id ),
            $format,
            array( '%d' )
        );
        
        if ( false === $result ) {
            return new \WP_Error( 'db_error', esc_html__( 'Failed to update email template.', 'agency-os-ai' ) );
        }
        
        return true;
    }
    
    public function delete( int $id ): bool {
        global $wpdb;
        $table = $this->get_table();
        
        $template = $this->get( $id );
        if ( ! $template ) {
            return false;
        }
        
        if ( (int) $template['is_default'] === 1 ) {
            return false;
        }
        
        $result = $wpdb->delete( $table, array( 'id' => $id ), array( '%d' ) );
        
        return $result !== false;
    }
    
    public function render( string $slug, string $type, array $data ): array {
        $template = $this->get_by_slug( $slug, $type );
        
        if ( ! $template || ! (int) $template['is_active'] ) {
            return array(
                'subject' => '',
                'body'    => '',
                'success' => false,
            );
        }
        
        $subject = $template['subject'];
        $body = $template['body'];
        
        foreach ( $data as $key => $value ) {
            $placeholder = '{{' . $key . '}}';
            $subject = str_replace( $placeholder, (string) $value, $subject );
            $body = str_replace( $placeholder, (string) $value, $body );
        }
        
        $available_vars = array(
            'company_name'      => get_option( 'aosai_company_name', get_bloginfo( 'name' ) ),
            'company_email'    => get_option( 'aosai_company_email', get_option( 'admin_email' ) ),
            'current_date'     => wp_date( get_option( 'date_format' ) ),
            'current_time'     => wp_date( get_option( 'time_format' ) ),
        );
        
        foreach ( $available_vars as $key => $value ) {
            $placeholder = '{{' . $key . '}}';
            $subject = str_replace( $placeholder, (string) $value, $subject );
            $body = str_replace( $placeholder, (string) $value, $body );
        }
        
        return array(
            'subject' => $subject,
            'body'    => $body,
            'success' => true,
        );
    }
    
    public function get_available_variables(): array {
        return array(
            'general' => array(
                'company_name',
                'company_email',
                'current_date',
                'current_time',
                'admin_email',
            ),
            'task' => array(
                'task_id',
                'task_title',
                'task_description',
                'task_url',
                'task_priority',
                'task_due_date',
                'task_status',
                'project_name',
                'project_url',
                'assignee_name',
                'assignee_email',
                'task_assigner_name',
                'completed_by_name',
                'completed_at',
            ),
            'ticket' => array(
                'ticket_id',
                'ticket_subject',
                'ticket_content',
                'ticket_url',
                'ticket_priority',
                'ticket_status',
                'department_name',
                'ticket_requester',
                'ticket_assignee',
                'ticket_note',
            ),
            'project' => array(
                'project_id',
                'project_name',
                'project_description',
                'project_url',
                'project_start_date',
                'project_end_date',
                'project_status',
                'member_name',
                'member_email',
            ),
            'milestone' => array(
                'milestone_id',
                'milestone_name',
                'milestone_description',
                'milestone_url',
                'milestone_due_date',
                'milestone_status',
                'project_name',
                'project_url',
                'member_name',
            ),
            'invoice' => array(
                'invoice_id',
                'invoice_number',
                'invoice_url',
                'invoice_amount',
                'invoice_tax',
                'invoice_total',
                'invoice_due_date',
                'invoice_issue_date',
                'invoice_status',
                'client_name',
                'client_email',
            ),
        );
    }
    
    private function sanitize_input( array $input ): array {
        $sanitized = array();
        
        $allowed_fields = array( 'name', 'slug', 'type', 'subject', 'body', 'variables', 'is_active', 'is_default' );
        
        foreach ( $input as $key => $value ) {
            if ( ! in_array( $key, $allowed_fields, true ) ) {
                continue;
            }
            
            switch ( $key ) {
                case 'name':
                case 'slug':
                    $sanitized[ $key ] = sanitize_text_field( (string) $value );
                    break;
                case 'type':
                    $allowed = array( 'general', 'task', 'ticket', 'project', 'milestone', 'invoice' );
                    $sanitized[ $key ] = in_array( $value, $allowed, true ) ? $value : 'general';
                    break;
                case 'subject':
                case 'body':
                    $sanitized[ $key ] = sanitize_textarea_field( (string) $value );
                    break;
                case 'variables':
                    $sanitized[ $key ] = is_array( $value ) ? $value : array();
                    break;
                case 'is_active':
                case 'is_default':
                    $sanitized[ $key ] = (int) $value ? 1 : 0;
                    break;
            }
        }
        
        return $sanitized;
    }
}
