<?php
if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

if ( ! defined( 'WP_UNINSTALL_PLUGIN' ) ) {
    exit;
}

$clean_data = get_option( 'aosai_clean_uninstall', 'no' );

if ( $clean_data === 'yes' ) {
    global $wpdb;
    $prefix = $wpdb->prefix;
    
    $tables = array(
        'aosai_projects',
        'aosai_project_users',
        'aosai_task_lists',
        'aosai_tasks',
        'aosai_task_users',
        'aosai_task_meta',
        'aosai_milestones',
        'aosai_messages',
        'aosai_comments',
        'aosai_files',
        'aosai_activities',
        'aosai_notifications',
        'aosai_ai_logs',
    );
    
    foreach ( $tables as $table ) {
        $wpdb->query( "DROP TABLE IF EXISTS {$prefix}{$table}" );
    }
    
    $wpdb->query(
        "DELETE FROM {$wpdb->options} WHERE option_name LIKE 'aosai\_%'"
    );
    
    $roles = array( 'administrator', 'editor', 'author' );
    $capabilities = array(
        'aosai_manage_projects',
        'aosai_manage_tasks',
        'aosai_manage_milestones',
        'aosai_manage_messages',
        'aosai_manage_files',
        'aosai_view_reports',
        'aosai_manage_settings',
        'aosai_use_ai',
    );
    
    foreach ( $roles as $role_name ) {
        $role = get_role( $role_name );
        if ( $role ) {
            foreach ( $capabilities as $cap ) {
                $role->remove_cap( $cap );
            }
        }
    }
    
    $wpdb->query(
        "DELETE FROM {$wpdb->options} WHERE option_name LIKE '_transient_aosai%' OR option_name LIKE '_transient_timeout_aosai%'"
    );
}
