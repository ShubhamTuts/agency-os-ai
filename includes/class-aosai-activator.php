<?php
if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

class AOSAI_Activator {
    public const DB_VERSION = '1.2.0';

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
        $editor = get_role( 'editor' );
        $author = get_role( 'author' );

        $admin_caps = array(
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

        foreach ( $admin_caps as $cap ) {
            if ( $admin ) {
                $admin->add_cap( $cap );
            }

            if ( $editor ) {
                $editor->add_cap( $cap );
            }
        }

        if ( $author ) {
            $author->add_cap( 'aosai_manage_tasks' );
            $author->add_cap( 'aosai_manage_messages' );
            $author->add_cap( 'aosai_manage_files' );
            $author->add_cap( 'aosai_use_ai' );
            $author->add_cap( 'aosai_access_portal' );
            $author->add_cap( 'aosai_submit_tickets' );
            $author->add_cap( 'aosai_manage_own_tickets' );
        }

        foreach ( array( 'aosai_client', 'aosai_employee' ) as $role_name ) {
            $role = get_role( $role_name );
            if ( ! $role ) {
                continue;
            }

            $role->add_cap( 'aosai_access_portal' );
            $role->add_cap( 'aosai_submit_tickets' );

            if ( 'aosai_employee' === $role_name ) {
                $role->add_cap( 'aosai_manage_own_tickets' );
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
}

