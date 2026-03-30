<?php
if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

class AOSAI_REST_Reports extends WP_REST_Controller {
    
    protected $namespace = 'aosai/v1';
    
    public function register_routes() {
        register_rest_route( $this->namespace, '/reports/overview', array(
            array(
                'methods'             => WP_REST_Server::READABLE,
                'callback'            => array( $this, 'get_overview' ),
                'permission_callback' => array( $this, 'get_reports_permissions_check' ),
            ),
        ) );
        
        register_rest_route( $this->namespace, '/reports/project/(?P<id>[\d]+)', array(
            array(
                'methods'             => WP_REST_Server::READABLE,
                'callback'            => array( $this, 'get_project_report' ),
                'permission_callback' => array( $this, 'get_reports_permissions_check' ),
            ),
        ) );
    }
    
    public function get_reports_permissions_check( $request ) {
        if ( ! is_user_logged_in() ) {
            return new WP_Error( 'rest_not_logged_in', esc_html__( 'You must be logged in.', 'agency-os-ai' ), array( 'status' => 401 ) );
        }
        if ( ! current_user_can( 'aosai_view_reports' ) ) {
            return new WP_Error( 'rest_forbidden', esc_html__( 'You do not have permission to view reports.', 'agency-os-ai' ), array( 'status' => 403 ) );
        }
        return true;
    }
    
    public function get_overview( $request ) {
        $user_id = get_current_user_id();
        global $wpdb;
        $prefix = $wpdb->prefix;

        $is_admin = current_user_can( 'manage_options' );

        if ( $is_admin ) {
            $project_ids = $wpdb->get_col( "SELECT id FROM {$prefix}aosai_projects" );
        } else {
            $project_ids = $wpdb->get_col(
                $wpdb->prepare(
                    "SELECT project_id FROM {$prefix}aosai_project_users WHERE user_id = %d",
                    $user_id
                )
            );
        }

        $empty = array(
            'total_projects'    => 0,
            'active_projects'   => 0,
            'total_tasks'       => 0,
            'completed_tasks'   => 0,
            'open_tasks'        => 0,
            'in_progress_tasks' => 0,
            'overdue_tasks'     => 0,
            'total_members'     => 0,
            'hours_logged'      => 0,
            'completion_rate'   => 0,
            'task_by_status'    => array(),
            'task_by_priority'  => array(),
            'project_stats'     => array(),
            'member_performance'=> array(),
        );

        if ( empty( $project_ids ) ) {
            return rest_ensure_response( $empty );
        }

        $placeholders = implode( ',', array_fill( 0, count( $project_ids ), '%d' ) );

        // Project stats
        $project_stats = $wpdb->get_row(
            $wpdb->prepare(
                "SELECT
                    COUNT(*) as total,
                    SUM(CASE WHEN status = 'active' THEN 1 ELSE 0 END) as active
                FROM {$prefix}aosai_projects
                WHERE id IN ({$placeholders})",
                ...$project_ids
            )
        );

        // Task stats
        $task_stats = $wpdb->get_row(
            $wpdb->prepare(
                "SELECT
                    COUNT(*) as total,
                    SUM(CASE WHEN status IN ('done','completed') THEN 1 ELSE 0 END) as completed,
                    SUM(CASE WHEN status = 'open' OR status = 'backlog' OR status = 'todo' THEN 1 ELSE 0 END) as open_tasks,
                    SUM(CASE WHEN status = 'in_progress' OR status = 'in_review' THEN 1 ELSE 0 END) as in_progress,
                    SUM(CASE WHEN due_date < CURDATE() AND status NOT IN ('done','completed') THEN 1 ELSE 0 END) as overdue
                FROM {$prefix}aosai_tasks
                WHERE project_id IN ({$placeholders})",
                ...$project_ids
            )
        );

        // Total unique members across these projects
        $total_members = (int) $wpdb->get_var(
            $wpdb->prepare(
                "SELECT COUNT(DISTINCT user_id) FROM {$prefix}aosai_project_users WHERE project_id IN ({$placeholders})",
                ...$project_ids
            )
        );

        // Task by status (for charts)
        $task_by_status = $wpdb->get_results(
            $wpdb->prepare(
                "SELECT status, COUNT(*) as count FROM {$prefix}aosai_tasks WHERE project_id IN ({$placeholders}) GROUP BY status",
                ...$project_ids
            ),
            ARRAY_A
        );

        // Task by priority (for charts)
        $task_by_priority = $wpdb->get_results(
            $wpdb->prepare(
                "SELECT priority, COUNT(*) as count FROM {$prefix}aosai_tasks WHERE project_id IN ({$placeholders}) GROUP BY priority",
                ...$project_ids
            ),
            ARRAY_A
        );

        $project_progress = $wpdb->get_results(
            $wpdb->prepare(
                "SELECT
                    p.id,
                    p.title as name,
                    COUNT(t.id) as tasks,
                    SUM(CASE WHEN t.status IN ('done','completed') THEN 1 ELSE 0 END) as completed,
                    CASE
                        WHEN COUNT(t.id) = 0 THEN 0
                        ELSE ROUND((SUM(CASE WHEN t.status IN ('done','completed') THEN 1 ELSE 0 END) / COUNT(t.id)) * 100, 1)
                    END as progress
                FROM {$prefix}aosai_projects p
                LEFT JOIN {$prefix}aosai_tasks t ON p.id = t.project_id
                WHERE p.id IN ({$placeholders})
                GROUP BY p.id, p.title
                ORDER BY p.created_at DESC
                LIMIT 10",
                ...$project_ids
            ),
            ARRAY_A
        );

        $member_performance = $wpdb->get_results(
            $wpdb->prepare(
                "SELECT
                    u.display_name as name,
                    COUNT(DISTINCT tu.task_id) as tasks,
                    SUM(CASE WHEN t.status IN ('done','completed') THEN 1 ELSE 0 END) as completed
                FROM (
                    SELECT DISTINCT user_id
                    FROM {$prefix}aosai_project_users
                    WHERE project_id IN ({$placeholders})
                ) pu
                INNER JOIN {$wpdb->users} u ON u.ID = pu.user_id
                LEFT JOIN {$prefix}aosai_task_users tu ON u.ID = tu.user_id
                LEFT JOIN {$prefix}aosai_tasks t ON tu.task_id = t.id AND t.project_id IN ({$placeholders})
                GROUP BY u.ID, u.display_name
                ORDER BY completed DESC, tasks DESC, u.display_name ASC
                LIMIT 10",
                ...array_merge( $project_ids, $project_ids )
            ),
            ARRAY_A
        );

        $total_tasks    = absint( $task_stats->total );
        $completed      = absint( $task_stats->completed );

        $data = array(
            'total_projects'    => absint( $project_stats->total ),
            'active_projects'   => absint( $project_stats->active ),
            'total_tasks'       => $total_tasks,
            'completed_tasks'   => $completed,
            'open_tasks'        => absint( $task_stats->open_tasks ),
            'in_progress_tasks' => absint( $task_stats->in_progress ),
            'overdue_tasks'     => absint( $task_stats->overdue ),
            'total_members'     => $total_members,
            'hours_logged'      => 0,
            'completion_rate'   => $total_tasks > 0 ? round( ( $completed / $total_tasks ) * 100, 1 ) : 0,
            'task_by_status'    => $task_by_status,
            'task_by_priority'  => $task_by_priority,
            'project_stats'     => $project_progress,
            'member_performance'=> $member_performance,
        );

        return rest_ensure_response( $data );
    }
    
    public function get_project_report( $request ) {
        $project_id = absint( $request->get_param( 'id' ) );
        
        global $wpdb;
        $prefix = $wpdb->prefix;
        
        $task_stats = $wpdb->get_results(
            $wpdb->prepare(
                "SELECT 
                    priority,
                    COUNT(*) as count
                FROM {$prefix}aosai_tasks 
                WHERE project_id = %d 
                GROUP BY priority",
                $project_id
            ),
            ARRAY_A
        );
        
        $status_stats = $wpdb->get_results(
            $wpdb->prepare(
                "SELECT 
                    status,
                    COUNT(*) as count
                FROM {$prefix}aosai_tasks 
                WHERE project_id = %d 
                GROUP BY status",
                $project_id
            ),
            ARRAY_A
        );
        
        $recent_completed = $wpdb->get_results(
            $wpdb->prepare(
                "SELECT id, title, completed_at 
                FROM {$prefix}aosai_tasks 
                WHERE project_id = %d AND status IN ('done', 'completed')
                ORDER BY completed_at DESC 
                LIMIT 10",
                $project_id
            ),
            ARRAY_A
        );
        
        $overdue_tasks = $wpdb->get_results(
            $wpdb->prepare(
                "SELECT id, title, due_date 
                FROM {$prefix}aosai_tasks 
                WHERE project_id = %d AND due_date < CURDATE() AND status NOT IN ('done', 'completed')
                ORDER BY due_date ASC 
                LIMIT 10",
                $project_id
            ),
            ARRAY_A
        );
        
        $data = array(
            'priority_breakdown' => $task_stats,
            'status_breakdown'  => $status_stats,
            'recent_completed'  => $recent_completed,
            'overdue_tasks'     => $overdue_tasks,
        );
        return rest_ensure_response( $data );
    }
}
