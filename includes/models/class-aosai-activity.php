<?php
if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

class AOSAI_Activity {
    use AOSAI_Singleton;
    
    public function get_table(): string {
        global $wpdb;
        return $wpdb->prefix . 'aosai_activities';
    }
    
    public function get_project_activities( int $project_id, array $args = array() ): array {
        global $wpdb;
        $table = $this->get_table();
        
        $defaults = array(
            'page'     => 1,
            'per_page' => 20,
        );
        $args = wp_parse_args( $args, $defaults );
        
        $offset = ( $args['page'] - 1 ) * $args['per_page'];
        
        $activities = $wpdb->get_results(
            $wpdb->prepare(
                "SELECT a.*, u.display_name as user_name 
                FROM {$table} a 
                LEFT JOIN {$wpdb->users} u ON a.user_id = u.ID 
                WHERE a.project_id = %d 
                ORDER BY a.created_at DESC 
                LIMIT %d OFFSET %d",
                $project_id,
                $args['per_page'],
                $offset
            ),
            ARRAY_A
        );
        
        foreach ( $activities as &$activity ) {
            if ( ! empty( $activity['meta'] ) ) {
                $activity['meta'] = json_decode( $activity['meta'], true );
            }
        }
        
        return $activities;
    }
    
    public function get_recent( int $user_id, array $args = array() ): array {
        global $wpdb;
        $table = $this->get_table();
        $pu_table = $wpdb->prefix . 'aosai_project_users';
        
        $defaults = array(
            'page'     => 1,
            'per_page' => 20,
        );
        $args = wp_parse_args( $args, $defaults );
        
        $offset = ( $args['page'] - 1 ) * $args['per_page'];
        
        $activities = $wpdb->get_results(
            $wpdb->prepare(
                "SELECT a.*, u.display_name as user_name, p.title as project_title 
                FROM {$table} a 
                LEFT JOIN {$wpdb->users} u ON a.user_id = u.ID 
                LEFT JOIN {$wpdb->prefix}aosai_projects p ON a.project_id = p.id 
                WHERE a.project_id IN (SELECT project_id FROM {$pu_table} WHERE user_id = %d) 
                ORDER BY a.created_at DESC 
                LIMIT %d OFFSET %d",
                $user_id,
                $args['per_page'],
                $offset
            ),
            ARRAY_A
        );
        
        foreach ( $activities as &$activity ) {
            if ( ! empty( $activity['meta'] ) ) {
                $activity['meta'] = json_decode( $activity['meta'], true );
            }
        }
        
        return $activities;
    }
}
