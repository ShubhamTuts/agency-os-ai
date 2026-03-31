<?php
if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

class AOSAI_Activator {
    public const DB_VERSION = '1.5.0';

    public static function activate() {
        self::install_schema();
        self::seed_defaults();
        self::register_roles();
        self::add_capabilities();
        self::seed_default_departments();

        update_option( 'aosai_db_version', self::DB_VERSION );
        flush_rewrite_rules();
    }

    public static function maybe_upgrade() {
        $installed_version = (string) get_option( 'aosai_db_version', '0.0.0' );
        if ( version_compare( $installed_version, self::DB_VERSION, '>=' ) ) {
            return;
        }

        self::install_schema();
        self::seed_defaults();
        self::register_roles();
        self::add_capabilities();
        self::seed_default_departments();
        self::seed_workflow_stages();
        self::seed_email_templates();

        update_option( 'aosai_db_version', self::DB_VERSION );
    }

    private static function install_schema() {
        global $wpdb;

        $charset_collate = $wpdb->get_charset_collate();
        $prefix          = $wpdb->prefix;

        require_once ABSPATH . 'wp-admin/includes/upgrade.php';

        $sql = "
CREATE TABLE {$prefix}aosai_projects (
    id BIGINT(20) UNSIGNED NOT NULL AUTO_INCREMENT,
    title VARCHAR(255) NOT NULL DEFAULT '',
    description LONGTEXT NULL,
    status VARCHAR(20) NOT NULL DEFAULT 'active',
    category VARCHAR(100) NULL,
    color VARCHAR(7) NULL DEFAULT '#6366f1',
    budget DECIMAL(12,2) NULL DEFAULT 0.00,
    currency VARCHAR(3) NULL DEFAULT 'USD',
    start_date DATE NULL,
    end_date DATE NULL,
    created_by BIGINT(20) UNSIGNED NOT NULL,
    created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    updated_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    PRIMARY KEY (id),
    KEY idx_status (status),
    KEY idx_created_by (created_by),
    KEY idx_created_at (created_at)
) {$charset_collate};

CREATE TABLE {$prefix}aosai_project_users (
    id BIGINT(20) UNSIGNED NOT NULL AUTO_INCREMENT,
    project_id BIGINT(20) UNSIGNED NOT NULL,
    user_id BIGINT(20) UNSIGNED NOT NULL,
    role VARCHAR(20) NOT NULL DEFAULT 'member',
    added_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    PRIMARY KEY (id),
    UNIQUE KEY idx_project_user (project_id, user_id),
    KEY idx_user_id (user_id)
) {$charset_collate};

CREATE TABLE {$prefix}aosai_task_lists (
    id BIGINT(20) UNSIGNED NOT NULL AUTO_INCREMENT,
    project_id BIGINT(20) UNSIGNED NOT NULL,
    milestone_id BIGINT(20) UNSIGNED NULL DEFAULT NULL,
    title VARCHAR(255) NOT NULL DEFAULT '',
    description TEXT NULL,
    sort_order INT(11) NOT NULL DEFAULT 0,
    is_complete TINYINT(1) NOT NULL DEFAULT 0,
    created_by BIGINT(20) UNSIGNED NOT NULL,
    created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    updated_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    PRIMARY KEY (id),
    KEY idx_project_id (project_id),
    KEY idx_milestone_id (milestone_id),
    KEY idx_sort_order (sort_order)
) {$charset_collate};

CREATE TABLE {$prefix}aosai_tasks (
    id BIGINT(20) UNSIGNED NOT NULL AUTO_INCREMENT,
    project_id BIGINT(20) UNSIGNED NOT NULL,
    task_list_id BIGINT(20) UNSIGNED NOT NULL,
    parent_id BIGINT(20) UNSIGNED NULL DEFAULT NULL,
    title VARCHAR(500) NOT NULL DEFAULT '',
    description LONGTEXT NULL,
    status VARCHAR(20) NOT NULL DEFAULT 'open',
    priority VARCHAR(10) NOT NULL DEFAULT 'medium',
    start_date DATE NULL,
    due_date DATE NULL,
    completed_at DATETIME NULL,
    estimated_hours DECIMAL(8,2) NULL DEFAULT 0.00,
    sort_order INT(11) NOT NULL DEFAULT 0,
    is_private TINYINT(1) NOT NULL DEFAULT 0,
    is_recurring TINYINT(1) NOT NULL DEFAULT 0,
    recurrence_rule VARCHAR(100) NULL,
    kanban_column VARCHAR(50) NULL DEFAULT 'open',
    created_by BIGINT(20) UNSIGNED NOT NULL,
    completed_by BIGINT(20) UNSIGNED NULL,
    created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    updated_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    PRIMARY KEY (id),
    KEY idx_project_id (project_id),
    KEY idx_task_list_id (task_list_id),
    KEY idx_parent_id (parent_id),
    KEY idx_status (status),
    KEY idx_priority (priority),
    KEY idx_due_date (due_date),
    KEY idx_created_by (created_by),
    KEY idx_kanban (project_id, kanban_column)
) {$charset_collate};

CREATE TABLE {$prefix}aosai_task_users (
    id BIGINT(20) UNSIGNED NOT NULL AUTO_INCREMENT,
    task_id BIGINT(20) UNSIGNED NOT NULL,
    user_id BIGINT(20) UNSIGNED NOT NULL,
    assigned_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    PRIMARY KEY (id),
    UNIQUE KEY idx_task_user (task_id, user_id),
    KEY idx_user_tasks (user_id)
) {$charset_collate};

CREATE TABLE {$prefix}aosai_task_meta (
    meta_id BIGINT(20) UNSIGNED NOT NULL AUTO_INCREMENT,
    task_id BIGINT(20) UNSIGNED NOT NULL,
    meta_key VARCHAR(255) NOT NULL,
    meta_value LONGTEXT NULL,
    PRIMARY KEY (meta_id),
    KEY idx_task_id (task_id),
    KEY idx_meta_key (meta_key)
) {$charset_collate};

CREATE TABLE {$prefix}aosai_milestones (
    id BIGINT(20) UNSIGNED NOT NULL AUTO_INCREMENT,
    project_id BIGINT(20) UNSIGNED NOT NULL,
    title VARCHAR(255) NOT NULL DEFAULT '',
    description TEXT NULL,
    status VARCHAR(20) NOT NULL DEFAULT 'upcoming',
    due_date DATE NULL,
    completed_at DATETIME NULL,
    sort_order INT(11) NOT NULL DEFAULT 0,
    created_by BIGINT(20) UNSIGNED NOT NULL,
    created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    updated_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    PRIMARY KEY (id),
    KEY idx_project_id (project_id),
    KEY idx_status (status),
    KEY idx_due_date (due_date)
) {$charset_collate};

CREATE TABLE {$prefix}aosai_messages (
    id BIGINT(20) UNSIGNED NOT NULL AUTO_INCREMENT,
    project_id BIGINT(20) UNSIGNED NOT NULL,
    title VARCHAR(255) NOT NULL DEFAULT '',
    content LONGTEXT NOT NULL,
    is_private TINYINT(1) NOT NULL DEFAULT 0,
    created_by BIGINT(20) UNSIGNED NOT NULL,
    created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    updated_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    PRIMARY KEY (id),
    KEY idx_project_id (project_id),
    KEY idx_created_by (created_by)
) {$charset_collate};

CREATE TABLE {$prefix}aosai_comments (
    id BIGINT(20) UNSIGNED NOT NULL AUTO_INCREMENT,
    commentable_type VARCHAR(20) NOT NULL,
    commentable_id BIGINT(20) UNSIGNED NOT NULL,
    parent_id BIGINT(20) UNSIGNED NULL DEFAULT NULL,
    content LONGTEXT NOT NULL,
    created_by BIGINT(20) UNSIGNED NOT NULL,
    created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    updated_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    PRIMARY KEY (id),
    KEY idx_commentable (commentable_type, commentable_id),
    KEY idx_parent_id (parent_id),
    KEY idx_created_by (created_by)
) {$charset_collate};

CREATE TABLE {$prefix}aosai_files (
    id BIGINT(20) UNSIGNED NOT NULL AUTO_INCREMENT,
    project_id BIGINT(20) UNSIGNED NOT NULL,
    attachment_id BIGINT(20) UNSIGNED NOT NULL,
    fileable_type VARCHAR(20) NULL,
    fileable_id BIGINT(20) UNSIGNED NULL DEFAULT NULL,
    folder VARCHAR(255) NULL DEFAULT '',
    title VARCHAR(255) NOT NULL DEFAULT '',
    file_name VARCHAR(255) NOT NULL DEFAULT '',
    file_url TEXT NOT NULL,
    file_type VARCHAR(50) NOT NULL DEFAULT '',
    file_size BIGINT(20) UNSIGNED NOT NULL DEFAULT 0,
    is_private TINYINT(1) NOT NULL DEFAULT 0,
    uploaded_by BIGINT(20) UNSIGNED NOT NULL,
    created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    PRIMARY KEY (id),
    KEY idx_project_id (project_id),
    KEY idx_fileable (fileable_type, fileable_id),
    KEY idx_folder (folder),
    KEY idx_uploaded_by (uploaded_by)
) {$charset_collate};

CREATE TABLE {$prefix}aosai_activities (
    id BIGINT(20) UNSIGNED NOT NULL AUTO_INCREMENT,
    project_id BIGINT(20) UNSIGNED NOT NULL,
    user_id BIGINT(20) UNSIGNED NOT NULL,
    action VARCHAR(50) NOT NULL,
    object_type VARCHAR(30) NOT NULL,
    object_id BIGINT(20) UNSIGNED NOT NULL,
    meta LONGTEXT NULL,
    created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    PRIMARY KEY (id),
    KEY idx_project_id (project_id),
    KEY idx_user_id (user_id),
    KEY idx_object (object_type, object_id),
    KEY idx_created_at (created_at)
) {$charset_collate};

CREATE TABLE {$prefix}aosai_notifications (
    id BIGINT(20) UNSIGNED NOT NULL AUTO_INCREMENT,
    user_id BIGINT(20) UNSIGNED NOT NULL,
    project_id BIGINT(20) UNSIGNED NULL,
    type VARCHAR(50) NOT NULL,
    title VARCHAR(255) NOT NULL DEFAULT '',
    content TEXT NULL,
    object_type VARCHAR(30) NULL,
    object_id BIGINT(20) UNSIGNED NULL,
    is_read TINYINT(1) NOT NULL DEFAULT 0,
    is_emailed TINYINT(1) NOT NULL DEFAULT 0,
    created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    PRIMARY KEY (id),
    KEY idx_user_read (user_id, is_read),
    KEY idx_created_at (created_at)
) {$charset_collate};

CREATE TABLE {$prefix}aosai_ai_logs (
    id BIGINT(20) UNSIGNED NOT NULL AUTO_INCREMENT,
    user_id BIGINT(20) UNSIGNED NOT NULL,
    project_id BIGINT(20) UNSIGNED NULL,
    provider VARCHAR(30) NOT NULL,
    model VARCHAR(100) NOT NULL,
    action VARCHAR(50) NOT NULL,
    prompt_tokens INT(11) NOT NULL DEFAULT 0,
    completion_tokens INT(11) NOT NULL DEFAULT 0,
    total_tokens INT(11) NOT NULL DEFAULT 0,
    cost_estimate DECIMAL(10,6) NULL DEFAULT 0.000000,
    status VARCHAR(20) NOT NULL DEFAULT 'success',
    error_message TEXT NULL,
    created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    PRIMARY KEY (id),
    KEY idx_user_id (user_id),
    KEY idx_provider (provider),
    KEY idx_created_at (created_at)
) {$charset_collate};

CREATE TABLE {$prefix}aosai_departments (
    id BIGINT(20) UNSIGNED NOT NULL AUTO_INCREMENT,
    name VARCHAR(190) NOT NULL DEFAULT '',
    slug VARCHAR(190) NOT NULL DEFAULT '',
    email VARCHAR(190) NULL DEFAULT NULL,
    description TEXT NULL,
    color VARCHAR(7) NOT NULL DEFAULT '#0f766e',
    keywords TEXT NULL,
    is_default TINYINT(1) NOT NULL DEFAULT 0,
    default_assignee_id BIGINT(20) UNSIGNED NULL DEFAULT NULL,
    created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    updated_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    PRIMARY KEY (id),
    UNIQUE KEY idx_slug (slug)
) {$charset_collate};

CREATE TABLE {$prefix}aosai_tickets (
    id BIGINT(20) UNSIGNED NOT NULL AUTO_INCREMENT,
    project_id BIGINT(20) UNSIGNED NULL DEFAULT NULL,
    department_id BIGINT(20) UNSIGNED NULL DEFAULT NULL,
    requester_id BIGINT(20) UNSIGNED NOT NULL,
    assignee_id BIGINT(20) UNSIGNED NULL DEFAULT NULL,
    subject VARCHAR(255) NOT NULL DEFAULT '',
    content LONGTEXT NOT NULL,
    status VARCHAR(20) NOT NULL DEFAULT 'open',
    priority VARCHAR(20) NOT NULL DEFAULT 'medium',
    source VARCHAR(20) NOT NULL DEFAULT 'portal',
    ai_summary TEXT NULL,
    created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    updated_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    PRIMARY KEY (id),
    KEY idx_project_id (project_id),
    KEY idx_department_id (department_id),
    KEY idx_requester_id (requester_id),
    KEY idx_assignee_id (assignee_id),
    KEY idx_status (status),
    KEY idx_created_at (created_at)
) {$charset_collate};

CREATE TABLE {$prefix}aosai_tags (
    id BIGINT(20) UNSIGNED NOT NULL AUTO_INCREMENT,
    name VARCHAR(120) NOT NULL DEFAULT '',
    slug VARCHAR(120) NOT NULL DEFAULT '',
    color VARCHAR(7) NOT NULL DEFAULT '#0f766e',
    type VARCHAR(30) NOT NULL DEFAULT 'general',
    created_by BIGINT(20) UNSIGNED NOT NULL DEFAULT 0,
    created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    PRIMARY KEY (id),
    UNIQUE KEY idx_type_slug (type, slug)
) {$charset_collate};

CREATE TABLE {$prefix}aosai_tag_relations (
    id BIGINT(20) UNSIGNED NOT NULL AUTO_INCREMENT,
    tag_id BIGINT(20) UNSIGNED NOT NULL,
    object_type VARCHAR(30) NOT NULL,
    object_id BIGINT(20) UNSIGNED NOT NULL,
    created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    PRIMARY KEY (id),
    UNIQUE KEY idx_tag_object (tag_id, object_type, object_id),
    KEY idx_object_lookup (object_type, object_id)
) {$charset_collate};

CREATE TABLE {$prefix}aosai_webhooks (
    id BIGINT(20) UNSIGNED NOT NULL AUTO_INCREMENT,
    name VARCHAR(190) NOT NULL DEFAULT '',
    url TEXT NOT NULL,
    events VARCHAR(255) NOT NULL DEFAULT 'all',
    secret VARCHAR(64) NOT NULL DEFAULT '',
    is_active TINYINT(1) NOT NULL DEFAULT 1,
    trigger_count INT(11) NOT NULL DEFAULT 0,
    last_triggered_at DATETIME NULL,
    created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    PRIMARY KEY (id),
    KEY idx_active (is_active)
) {$charset_collate};

CREATE TABLE {$prefix}aosai_login_activity (
    id BIGINT(20) UNSIGNED NOT NULL AUTO_INCREMENT,
    user_id BIGINT(20) UNSIGNED NOT NULL DEFAULT 0,
    user_login VARCHAR(60) NOT NULL DEFAULT '',
    portal_type VARCHAR(20) NOT NULL DEFAULT 'team',
    event_type VARCHAR(40) NOT NULL DEFAULT 'login_success',
    ip_address VARCHAR(64) NOT NULL DEFAULT '',
    user_agent TEXT NULL,
    created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    PRIMARY KEY (id),
    KEY idx_user_id (user_id),
    KEY idx_event_type (event_type),
    KEY idx_created_at (created_at)
) {$charset_collate};

CREATE TABLE {$prefix}aosai_clients (
    id BIGINT(20) UNSIGNED NOT NULL AUTO_INCREMENT,
    name VARCHAR(255) NOT NULL DEFAULT '',
    company_name VARCHAR(255) NULL,
    email VARCHAR(190) NULL,
    phone VARCHAR(50) NULL,
    website VARCHAR(255) NULL,
    address TEXT NULL,
    city VARCHAR(100) NULL,
    state VARCHAR(100) NULL,
    country VARCHAR(100) NULL,
    zip_code VARCHAR(20) NULL,
    tax_id VARCHAR(50) NULL,
    notes TEXT NULL,
    status VARCHAR(20) NOT NULL DEFAULT 'active',
    source VARCHAR(30) NOT NULL DEFAULT 'manual',
    created_by BIGINT(20) UNSIGNED NOT NULL DEFAULT 0,
    created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    updated_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    PRIMARY KEY (id),
    KEY idx_status (status),
    KEY idx_email (email),
    KEY idx_created_by (created_by)
) {$charset_collate};

CREATE TABLE {$prefix}aosai_client_users (
    id BIGINT(20) UNSIGNED NOT NULL AUTO_INCREMENT,
    client_id BIGINT(20) UNSIGNED NOT NULL,
    user_id BIGINT(20) UNSIGNED NOT NULL,
    role VARCHAR(30) NOT NULL DEFAULT 'contact',
    added_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    PRIMARY KEY (id),
    UNIQUE KEY idx_client_user (client_id, user_id),
    KEY idx_user_id (user_id)
) {$charset_collate};

CREATE TABLE {$prefix}aosai_client_projects (
    id BIGINT(20) UNSIGNED NOT NULL AUTO_INCREMENT,
    client_id BIGINT(20) UNSIGNED NOT NULL,
    project_id BIGINT(20) UNSIGNED NOT NULL,
    linked_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    PRIMARY KEY (id),
    UNIQUE KEY idx_client_project (client_id, project_id)
) {$charset_collate};

CREATE TABLE {$prefix}aosai_invoices (
    id BIGINT(20) UNSIGNED NOT NULL AUTO_INCREMENT,
    invoice_number VARCHAR(50) NOT NULL,
    client_id BIGINT(20) UNSIGNED NULL,
    project_id BIGINT(20) UNSIGNED NULL,
    title VARCHAR(255) NOT NULL DEFAULT '',
    description TEXT NULL,
    amount DECIMAL(12,2) NOT NULL DEFAULT 0.00,
    tax_rate DECIMAL(5,2) NOT NULL DEFAULT 0.00,
    tax_amount DECIMAL(12,2) NOT NULL DEFAULT 0.00,
    total_amount DECIMAL(12,2) NOT NULL DEFAULT 0.00,
    currency VARCHAR(3) NOT NULL DEFAULT 'USD',
    status VARCHAR(20) NOT NULL DEFAULT 'draft',
    issue_date DATE NULL,
    due_date DATE NULL,
    paid_date DATE NULL,
    notes TEXT NULL,
    created_by BIGINT(20) UNSIGNED NOT NULL DEFAULT 0,
    created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    updated_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    PRIMARY KEY (id),
    UNIQUE KEY idx_invoice_number (invoice_number),
    KEY idx_client_id (client_id),
    KEY idx_project_id (project_id),
    KEY idx_status (status),
    KEY idx_due_date (due_date)
) {$charset_collate};

CREATE TABLE {$prefix}aosai_invoice_items (
    id BIGINT(20) UNSIGNED NOT NULL AUTO_INCREMENT,
    invoice_id BIGINT(20) UNSIGNED NOT NULL,
    description TEXT NOT NULL,
    quantity DECIMAL(10,2) NOT NULL DEFAULT 1.00,
    unit_price DECIMAL(12,2) NOT NULL DEFAULT 0.00,
    amount DECIMAL(12,2) NOT NULL DEFAULT 0.00,
    sort_order INT(11) NOT NULL DEFAULT 0,
    PRIMARY KEY (id),
    KEY idx_invoice_id (invoice_id)
) {$charset_collate};

CREATE TABLE {$prefix}aosai_workflow_stages (
    id BIGINT(20) UNSIGNED NOT NULL AUTO_INCREMENT,
    name VARCHAR(100) NOT NULL DEFAULT '',
    slug VARCHAR(100) NOT NULL DEFAULT '',
    type VARCHAR(20) NOT NULL DEFAULT 'task',
    color VARCHAR(7) NOT NULL DEFAULT '#6366f1',
    icon VARCHAR(50) NULL,
    sort_order INT(11) NOT NULL DEFAULT 0,
    is_default TINYINT(1) NOT NULL DEFAULT 0,
    is_completed TINYINT(1) NOT NULL DEFAULT 0,
    created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    PRIMARY KEY (id),
    KEY idx_type (type),
    KEY idx_sort_order (sort_order)
) {$charset_collate};

CREATE TABLE {$prefix}aosai_email_templates (
    id BIGINT(20) UNSIGNED NOT NULL AUTO_INCREMENT,
    name VARCHAR(190) NOT NULL DEFAULT '',
    slug VARCHAR(190) NOT NULL DEFAULT '',
    type VARCHAR(30) NOT NULL DEFAULT 'general',
    subject VARCHAR(500) NOT NULL DEFAULT '',
    body LONGTEXT NOT NULL,
    variables TEXT NULL,
    is_active TINYINT(1) NOT NULL DEFAULT 1,
    is_default TINYINT(1) NOT NULL DEFAULT 0,
    created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    updated_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    PRIMARY KEY (id),
    UNIQUE KEY idx_slug_type (slug, type)
) {$charset_collate};

CREATE TABLE {$prefix}aosai_time_entries (
    id BIGINT(20) UNSIGNED NOT NULL AUTO_INCREMENT,
    task_id BIGINT(20) UNSIGNED NULL,
    project_id BIGINT(20) UNSIGNED NULL,
    user_id BIGINT(20) UNSIGNED NOT NULL,
    description TEXT NULL,
    start_time DATETIME NOT NULL,
    end_time DATETIME NULL,
    duration INT(11) NOT NULL DEFAULT 0,
    billable TINYINT(1) NOT NULL DEFAULT 1,
    invoiced TINYINT(1) NOT NULL DEFAULT 0,
    invoice_id BIGINT(20) UNSIGNED NULL,
    created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    updated_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    PRIMARY KEY (id),
    KEY idx_task_id (task_id),
    KEY idx_project_id (project_id),
    KEY idx_user_id (user_id),
    KEY idx_invoiced (invoiced)
) {$charset_collate};
";

        dbDelta( $sql );
    }

    private static function seed_defaults() {
        $defaults = array(
            'aosai_ai_provider'                 => 'openai',
            'aosai_openai_api_key'             => '',
            'aosai_openai_model'               => 'gpt-4o-mini',
            'aosai_default_task_status'        => 'todo',
            'aosai_default_priority'           => 'medium',
            'aosai_email_notifications'        => 'yes',
            'aosai_daily_digest'               => 'no',
            'aosai_file_upload_max_mb'         => 10,
            'aosai_clean_uninstall'            => 'no',
            'aosai_timezone'                   => get_option( 'timezone_string', 'UTC' ),
            'aosai_date_format'                => get_option( 'date_format', 'F j, Y' ),
            'aosai_primary_color'              => '#0f766e',
            'aosai_company_name'               => get_bloginfo( 'name' ),
            'aosai_company_email'              => get_option( 'admin_email' ),
            'aosai_company_phone'              => '',
            'aosai_company_website'            => home_url( '/' ),
            'aosai_policy_privacy_url'         => function_exists( 'get_privacy_policy_url' ) ? get_privacy_policy_url() : '',
            'aosai_policy_terms_url'           => '',
            'aosai_company_address'            => '',
            'aosai_company_logo_url'           => '',
            'aosai_support_email'              => get_option( 'admin_email' ),
            'aosai_email_from_name'            => get_bloginfo( 'name' ),
            'aosai_email_from_email'           => get_option( 'admin_email' ),
            'aosai_email_footer_text'          => 'You are receiving this update because you are a member of the workspace.',
            'aosai_portal_name'                => get_bloginfo( 'name' ) . ' Workspace',
            'aosai_portal_welcome_title'       => 'Client and team workspace',
            'aosai_portal_welcome_text'        => 'Track projects, collaborate with your team, and manage support requests in one branded portal.',
            'aosai_portal_secondary_color'     => '#f59e0b',
            'aosai_portal_page_id'             => 0,
            'aosai_portal_login_page_id'       => 0,
            'aosai_portal_ticket_page_id'      => 0,
            'aosai_portal_hide_admin_bar'      => 'yes',
            'aosai_portal_force_frontend'      => 'yes',
            'aosai_portal_enable_pwa'          => 'yes',
            'aosai_show_footer_credit'         => 'no',
            'aosai_footer_credit_text'         => '',
            'aosai_ticket_ai_routing'          => 'yes',
            'aosai_ticket_default_priority'    => 'medium',
            'aosai_portal_dashboard_layout'    => 'split',
            'aosai_login_tracking_enabled'     => 'yes',
            'aosai_role_access_matrix'         => self::get_default_role_access_matrix(),
            'aosai_smtp_enabled'           => 'no',
            'aosai_smtp_host'              => '',
            'aosai_smtp_port'              => 587,
            'aosai_smtp_username'          => '',
            'aosai_smtp_password'          => '',
            'aosai_smtp_encryption'        => 'tls',
            'aosai_smtp_auth'              => 'yes',
            'aosai_inbound_email_token'    => wp_generate_password( 32, false ),
            'aosai_inbound_ai_routing'     => 'yes',
        );

        foreach ( $defaults as $key => $value ) {
            if ( false === get_option( $key ) ) {
                add_option( $key, $value );
            }
        }
    }

    private static function register_roles() {
        add_role(
            'aosai_client',
            __( 'Agency Client', 'agency-os-ai' ),
            array(
                'read'                => true,
                'aosai_access_portal' => true,
                'aosai_submit_tickets'=> true,
                'upload_files'        => false,
            )
        );

        add_role(
            'aosai_employee',
            __( 'Agency Employee', 'agency-os-ai' ),
            array(
                'read'                   => true,
                'upload_files'           => true,
                'aosai_access_portal'    => true,
                'aosai_submit_tickets'   => true,
                'aosai_manage_own_tickets'=> true,
            )
        );
    }

    public static function add_capabilities() {
        $admin  = get_role( 'administrator' );
        if ( $admin ) {
            foreach ( self::get_plugin_capabilities() as $cap ) {
                $admin->add_cap( $cap );
            }
        }

        self::apply_role_access_matrix();
    }

    public static function get_plugin_capabilities(): array {
        return array(
            'aosai_manage_projects',
            'aosai_manage_tasks',
            'aosai_manage_milestones',
            'aosai_manage_messages',
            'aosai_manage_files',
            'aosai_view_reports',
            'aosai_manage_settings',
            'aosai_use_ai',
            'aosai_access_portal',
            'aosai_submit_tickets',
            'aosai_manage_tickets',
            'aosai_manage_departments',
            'aosai_manage_tags',
            'aosai_manage_own_tickets',
        );
    }

    public static function get_default_role_access_matrix(): array {
        $all_caps = array_fill_keys( self::get_plugin_capabilities(), true );

        return array(
            'editor' => $all_caps,
            'author' => array(
                'aosai_manage_tasks'       => true,
                'aosai_manage_messages'    => true,
                'aosai_manage_files'       => true,
                'aosai_use_ai'             => true,
                'aosai_access_portal'      => true,
                'aosai_submit_tickets'     => true,
                'aosai_manage_own_tickets' => true,
            ),
            'aosai_employee' => array(
                'aosai_access_portal'      => true,
                'aosai_submit_tickets'     => true,
                'aosai_manage_own_tickets' => true,
            ),
            'aosai_client' => array(
                'aosai_access_portal'  => true,
                'aosai_submit_tickets' => true,
            ),
        );
    }

    public static function apply_role_access_matrix( ?array $matrix = null ): void {
        if ( null === $matrix ) {
            $stored = get_option( 'aosai_role_access_matrix', self::get_default_role_access_matrix() );
            $matrix = is_array( $stored ) ? $stored : self::get_default_role_access_matrix();
        }

        $managed_roles = array_keys( self::get_default_role_access_matrix() );
        $plugin_caps   = self::get_plugin_capabilities();

        foreach ( $managed_roles as $role_name ) {
            $role = get_role( $role_name );
            if ( ! $role ) {
                continue;
            }

            foreach ( $plugin_caps as $cap ) {
                $role->remove_cap( $cap );
            }

            $role_caps = is_array( $matrix[ $role_name ] ?? null ) ? $matrix[ $role_name ] : array();
            foreach ( $plugin_caps as $cap ) {
                if ( ! empty( $role_caps[ $cap ] ) ) {
                    $role->add_cap( $cap );
                }
            }
        }
    }

    private static function seed_default_departments() {
        global $wpdb;

        $table = $wpdb->prefix . 'aosai_departments';
        $count = (int) $wpdb->get_var( "SELECT COUNT(*) FROM {$table}" );
        if ( $count > 0 ) {
            return;
        }

        $departments = array(
            array(
                'name'        => 'Support',
                'slug'        => 'support',
                'email'       => get_option( 'aosai_support_email', get_option( 'admin_email' ) ),
                'description' => 'General support and troubleshooting requests.',
                'color'       => '#0f766e',
                'keywords'    => 'bug,error,issue,broken,login,access,support,help,problem',
                'is_default'  => 1,
            ),
            array(
                'name'        => 'Development',
                'slug'        => 'development',
                'email'       => get_option( 'admin_email' ),
                'description' => 'Feature requests, integrations, and development work.',
                'color'       => '#2563eb',
                'keywords'    => 'feature,api,integration,development,build,code,plugin,website',
                'is_default'  => 0,
            ),
            array(
                'name'        => 'Design',
                'slug'        => 'design',
                'email'       => get_option( 'admin_email' ),
                'description' => 'Branding, UX, creative direction, and layout feedback.',
                'color'       => '#db2777',
                'keywords'    => 'design,logo,brand,layout,ui,ux,color,creative,graphics',
                'is_default'  => 0,
            ),
            array(
                'name'        => 'Accounts',
                'slug'        => 'accounts',
                'email'       => get_option( 'admin_email' ),
                'description' => 'Billing, invoices, quotes, and account management.',
                'color'       => '#d97706',
                'keywords'    => 'invoice,billing,payment,quote,renewal,subscription,account',
                'is_default'  => 0,
            ),
        );

        foreach ( $departments as $department ) {
            $wpdb->insert(
                $table,
                $department,
                array( '%s', '%s', '%s', '%s', '%s', '%s', '%d' )
            );
        }
    }

    private static function seed_workflow_stages() {
        global $wpdb;

        $table = $wpdb->prefix . 'aosai_workflow_stages';
        $count = (int) $wpdb->get_var( "SELECT COUNT(*) FROM {$table}" );
        if ( $count > 0 ) {
            return;
        }

        $stages = array(
            array(
                'name'         => 'Backlog',
                'slug'         => 'backlog',
                'type'         => 'task',
                'color'        => '#64748b',
                'icon'         => 'inbox',
                'sort_order'   => 1,
                'is_default'   => 1,
                'is_completed' => 0,
            ),
            array(
                'name'         => 'To Do',
                'slug'         => 'todo',
                'type'         => 'task',
                'color'        => '#3b82f6',
                'icon'         => 'list-todo',
                'sort_order'   => 2,
                'is_default'   => 1,
                'is_completed' => 0,
            ),
            array(
                'name'         => 'In Progress',
                'slug'         => 'in_progress',
                'type'         => 'task',
                'color'        => '#f59e0b',
                'icon'         => 'loader',
                'sort_order'   => 3,
                'is_default'   => 1,
                'is_completed' => 0,
            ),
            array(
                'name'         => 'In Review',
                'slug'         => 'in_review',
                'type'         => 'task',
                'color'        => '#8b5cf6',
                'icon'         => 'eye',
                'sort_order'   => 4,
                'is_default'   => 1,
                'is_completed' => 0,
            ),
            array(
                'name'         => 'Completed',
                'slug'         => 'completed',
                'type'         => 'task',
                'color'        => '#10b981',
                'icon'         => 'check-circle',
                'sort_order'   => 5,
                'is_default'   => 1,
                'is_completed' => 1,
            ),
            array(
                'name'         => 'Open',
                'slug'         => 'open',
                'type'         => 'ticket',
                'color'        => '#3b82f6',
                'icon'         => 'inbox',
                'sort_order'   => 1,
                'is_default'   => 1,
                'is_completed' => 0,
            ),
            array(
                'name'         => 'In Progress',
                'slug'         => 'in_progress',
                'type'         => 'ticket',
                'color'        => '#f59e0b',
                'icon'         => 'loader',
                'sort_order'   => 2,
                'is_default'   => 1,
                'is_completed' => 0,
            ),
            array(
                'name'         => 'Waiting',
                'slug'         => 'waiting',
                'type'         => 'ticket',
                'color'        => '#6366f1',
                'icon'         => 'clock',
                'sort_order'   => 3,
                'is_default'   => 1,
                'is_completed' => 0,
            ),
            array(
                'name'         => 'Resolved',
                'slug'         => 'resolved',
                'type'         => 'ticket',
                'color'        => '#10b981',
                'icon'         => 'check-circle',
                'sort_order'   => 4,
                'is_default'   => 1,
                'is_completed' => 1,
            ),
            array(
                'name'         => 'Closed',
                'slug'         => 'closed',
                'type'         => 'ticket',
                'color'        => '#64748b',
                'icon'         => 'archive',
                'sort_order'   => 5,
                'is_default'   => 1,
                'is_completed' => 1,
            ),
        );

        foreach ( $stages as $stage ) {
            $wpdb->insert(
                $table,
                $stage,
                array( '%s', '%s', '%s', '%s', '%s', '%d', '%d', '%d', '%s' )
            );
        }
    }

    private static function seed_email_templates() {
        global $wpdb;

        $table = $wpdb->prefix . 'aosai_email_templates';
        $count = (int) $wpdb->get_var( "SELECT COUNT(*) FROM {$table}" );
        if ( $count > 0 ) {
            return;
        }

        $templates = array(
            array(
                'name'       => 'Task Assigned',
                'slug'       => 'task_assigned',
                'type'       => 'task',
                'subject'    => '[{{project_name}}] New task assigned: {{task_title}}',
                'body'       => "Hello {{assignee_name}},\n\nA new task has been assigned to you:\n\n**Task:** {{task_title}}\n**Project:** {{project_name}}\n**Due Date:** {{task_due_date}}\n**Priority:** {{task_priority}}\n\n{{task_description}}\n\nView task: {{task_url}}\n\nBest regards,\n{{company_name}}",
                'variables'  => json_encode( array( 'assignee_name', 'task_title', 'project_name', 'task_due_date', 'task_priority', 'task_description', 'task_url', 'company_name' ) ),
                'is_active'  => 1,
                'is_default' => 1,
            ),
            array(
                'name'       => 'Task Completed',
                'slug'       => 'task_completed',
                'type'       => 'task',
                'subject'    => '[{{project_name}}] Task completed: {{task_title}}',
                'body'       => "Hello {{task_assigner_name}},\n\nGreat news! The following task has been marked as completed:\n\n**Task:** {{task_title}}\n**Project:** {{project_name}}\n**Completed By:** {{completed_by_name}}\n**Completed At:** {{completed_at}}\n\nView task: {{task_url}}\n\nBest regards,\n{{company_name}}",
                'variables'  => json_encode( array( 'task_assigner_name', 'task_title', 'project_name', 'completed_by_name', 'completed_at', 'task_url', 'company_name' ) ),
                'is_active'  => 1,
                'is_default' => 1,
            ),
            array(
                'name'       => 'Ticket Created',
                'slug'       => 'ticket_created',
                'type'       => 'ticket',
                'subject'    => '[#{{ticket_id}}] New support request: {{ticket_subject}}',
                'body'       => "Hello {{assignee_name}},\n\nA new support ticket has been submitted:\n\n**Subject:** {{ticket_subject}}\n**Priority:** {{ticket_priority}}\n**Department:** {{department_name}}\n**Submitted By:** {{ticket_requester}}\n\n**Description:**\n{{ticket_content}}\n\nView ticket: {{ticket_url}}\n\nBest regards,\n{{company_name}}",
                'variables'  => json_encode( array( 'assignee_name', 'ticket_id', 'ticket_subject', 'ticket_priority', 'department_name', 'ticket_requester', 'ticket_content', 'ticket_url', 'company_name' ) ),
                'is_active'  => 1,
                'is_default' => 1,
            ),
            array(
                'name'       => 'Ticket Updated',
                'slug'       => 'ticket_updated',
                'type'       => 'ticket',
                'subject'    => '[#{{ticket_id}}] Ticket updated: {{ticket_subject}}',
                'body'       => "Hello {{ticket_requester}},\n\nYour support ticket has been updated:\n\n**Subject:** {{ticket_subject}}\n**New Status:** {{ticket_status}}\n\n**Update:**\n{{ticket_note}}\n\nView ticket: {{ticket_url}}\n\nBest regards,\n{{company_name}}",
                'variables'  => json_encode( array( 'ticket_requester', 'ticket_id', 'ticket_subject', 'ticket_status', 'ticket_note', 'ticket_url', 'company_name' ) ),
                'is_active'  => 1,
                'is_default' => 1,
            ),
            array(
                'name'       => 'Project Created',
                'slug'       => 'project_created',
                'type'       => 'project',
                'subject'    => 'New project created: {{project_name}}',
                'body'       => "Hello {{member_name}},\n\nA new project has been created and you have been added as a team member:\n\n**Project:** {{project_name}}\n**Start Date:** {{project_start_date}}\n**Due Date:** {{project_end_date}}\n\n{{project_description}}\n\nView project: {{project_url}}\n\nBest regards,\n{{company_name}}",
                'variables'  => json_encode( array( 'member_name', 'project_name', 'project_start_date', 'project_end_date', 'project_description', 'project_url', 'company_name' ) ),
                'is_active'  => 1,
                'is_default' => 1,
            ),
            array(
                'name'       => 'Milestone Due Soon',
                'slug'       => 'milestone_due_soon',
                'type'       => 'milestone',
                'subject'    => '[{{project_name}}] Milestone due soon: {{milestone_name}}',
                'body'       => "Hello {{member_name}},\n\nA milestone is approaching its due date:\n\n**Milestone:** {{milestone_name}}\n**Project:** {{project_name}}\n**Due Date:** {{milestone_due_date}}\n\nView milestone: {{milestone_url}}\n\nBest regards,\n{{company_name}}",
                'variables'  => json_encode( array( 'member_name', 'milestone_name', 'project_name', 'milestone_due_date', 'milestone_url', 'company_name' ) ),
                'is_active'  => 1,
                'is_default' => 1,
            ),
            array(
                'name'       => 'Invoice Created',
                'slug'       => 'invoice_created',
                'type'       => 'invoice',
                'subject'    => 'Invoice #{{invoice_number}} from {{company_name}}',
                'body'       => "Dear {{client_name}},\n\nPlease find attached invoice #{{invoice_number}}.\n\n**Issue Date:** {{invoice_issue_date}}\n**Due Date:** {{invoice_due_date}}\n**Amount:** {{invoice_amount}}\n\n{{invoice_description}}\n\nView invoice: {{invoice_url}}\n\nBest regards,\n{{company_name}}",
                'variables'  => json_encode( array( 'client_name', 'invoice_number', 'company_name', 'invoice_issue_date', 'invoice_due_date', 'invoice_amount', 'invoice_description', 'invoice_url' ) ),
                'is_active'  => 1,
                'is_default' => 1,
            ),
            array(
                'name'       => 'Invoice Payment Reminder',
                'slug'       => 'invoice_reminder',
                'type'       => 'invoice',
                'subject'    => 'Payment Reminder: Invoice #{{invoice_number}} due {{invoice_due_date}}',
                'body'       => "Dear {{client_name}},\n\nThis is a friendly reminder that invoice #{{invoice_number}} is due on {{invoice_due_date}}.\n\n**Amount Due:** {{invoice_amount}}\n\nPlease arrange payment at your earliest convenience.\n\nView invoice: {{invoice_url}}\n\nBest regards,\n{{company_name}}",
                'variables'  => json_encode( array( 'client_name', 'invoice_number', 'invoice_due_date', 'invoice_amount', 'invoice_url', 'company_name' ) ),
                'is_active'  => 1,
                'is_default' => 1,
            ),
        );

        foreach ( $templates as $template ) {
            $wpdb->insert(
                $table,
                $template,
                array( '%s', '%s', '%s', '%s', '%s', '%s', '%d', '%d', '%s' )
            );
        }
    }
}
