<?php
if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

class AOSAI_Portal_Service {
    use AOSAI_Singleton;

    private function __construct() {}

    public function create_default_pages(): array|\WP_Error {
        if ( ! current_user_can( 'aosai_manage_settings' ) ) {
            return new \WP_Error( 'forbidden', esc_html__( 'You do not have permission to create portal pages.', 'agency-os-ai' ), array( 'status' => 403 ) );
        }

        $pages = array(
            'portal_login_page_id' => array(
                'title'   => __( 'Workspace Login', 'agency-os-ai' ),
                'slug'    => 'workspace-login',
                'content' => '[agency_os_ai_login]',
            ),
            'portal_page_id' => array(
                'title'   => __( 'Client Portal', 'agency-os-ai' ),
                'slug'    => 'client-portal',
                'content' => '[agency_os_ai_portal]',
            ),
            'portal_ticket_page_id' => array(
                'title'   => __( 'Support Center', 'agency-os-ai' ),
                'slug'    => 'support-center',
                'content' => '[agency_os_ai_portal view="tickets"]',
            ),
        );

        $created = array();
        foreach ( $pages as $setting_key => $page ) {
            $option_key = $this->setting_key_to_option_key( $setting_key );
            $page_id    = absint( get_option( $option_key, 0 ) );
            $wp_page    = $page_id > 0 ? get_post( $page_id ) : null;

            if ( ! $wp_page || 'trash' === $wp_page->post_status ) {
                $existing = get_page_by_path( $page['slug'], OBJECT, 'page' );
                if ( $existing instanceof \WP_Post ) {
                    $page_id = (int) $existing->ID;
                } else {
                    $page_id = wp_insert_post(
                        array(
                            'post_type'    => 'page',
                            'post_status'  => 'publish',
                            'post_title'   => $page['title'],
                            'post_name'    => $page['slug'],
                            'post_content' => $page['content'],
                        ),
                        true
                    );

                    if ( is_wp_error( $page_id ) ) {
                        return $page_id;
                    }
                }

                update_option( $option_key, absint( $page_id ) );
            }

            $created[ $setting_key ] = array(
                'id'  => absint( $page_id ),
                'url' => get_permalink( absint( $page_id ) ) ?: '',
            );
        }

        return $created;
    }

    public function get_navigation_for_user( int $user_id ): array {
        $portal_type = aosai_get_user_portal_type( $user_id );

        $items = array(
            array( 'id' => 'dashboard', 'label' => __( 'Dashboard', 'agency-os-ai' ) ),
            array( 'id' => 'projects', 'label' => __( 'Projects', 'agency-os-ai' ) ),
        );

        if ( 'client' !== $portal_type ) {
            $items[] = array( 'id' => 'tasks', 'label' => __( 'My Work', 'agency-os-ai' ) );
        }

        $items[] = array( 'id' => 'tickets', 'label' => __( 'Tickets', 'agency-os-ai' ) );
        $items[] = array( 'id' => 'files', 'label' => __( 'Files', 'agency-os-ai' ) );
        $items[] = array( 'id' => 'messages', 'label' => __( 'Messages', 'agency-os-ai' ) );
        $items[] = array( 'id' => 'profile', 'label' => __( 'Profile', 'agency-os-ai' ) );

        return $items;
    }

    public function build_bootstrap_payload( int $user_id ): array {
        $user_model    = AOSAI_User::get_instance();
        $project_model = AOSAI_Project::get_instance();
        $task_model    = AOSAI_Task::get_instance();
        $ticket_model  = AOSAI_Ticket::get_instance();
        $message_model = AOSAI_Message::get_instance();
        $file_model    = AOSAI_File::get_instance();
        $settings      = AOSAI_Setting::get_instance();

        $portal_type = aosai_get_user_portal_type( $user_id );
        $projects = $project_model->get_user_projects( $user_id, array( 'per_page' => 24, 'page' => 1 ) );
        $projects = $this->decorate_portal_projects( $projects, $portal_type );
        $tasks    = $task_model->get_my_tasks( $user_id, array( 'per_page' => 8, 'page' => 1, 'include_all_if_manager' => true ) );
        $tickets  = $ticket_model->get_tickets_for_user( $user_id, array( 'per_page' => 8, 'page' => 1 ) );
        $messages = $message_model->get_messages_for_user( $user_id, array( 'per_page' => 6, 'page' => 1 ) );
        $files    = $file_model->get_user_files( $user_id, array( 'per_page' => 6, 'page' => 1 ) );

        $user = $user_model->get_formatted_user( $user_id ) ?: array();
        $wp_user = get_userdata( $user_id );
        $user['roles']       = $wp_user ? array_values( (array) $wp_user->roles ) : array();
        $user['portal_type'] = $portal_type;

        return array(
            'user'        => $user,
            'branding'    => $settings->get_portal_branding(),
            'navigation'  => $this->get_navigation_for_user( $user_id ),
            'stats'       => array(
                'projects'      => $this->count_projects_for_user( $user_id ),
                'tasks'         => $this->count_tasks_for_user( $user_id ),
                'tickets'       => $this->count_tickets_for_user( $user_id ),
                'messages'      => $this->count_messages_for_user( $user_id ),
                'overdue_tasks' => $this->count_overdue_tasks_for_user( $user_id ),
            ),
            'projects'    => $projects,
            'tasks'       => $tasks,
            'tickets'     => $tickets,
            'messages'    => $messages,
            'files'       => $files,
            'departments' => AOSAI_Department::get_instance()->get_all(),
            'tags'        => AOSAI_Tag::get_instance()->get_all(),
            'urls'        => array(
                'portal' => aosai_get_portal_page_url(),
                'login'  => aosai_get_login_page_url(),
                'tickets'=> aosai_get_ticket_page_url(),
                'logout' => wp_logout_url( aosai_get_login_page_url() ),
            ),
        );
    }

    private function decorate_portal_projects( array $projects, string $portal_type ): array {
        $task_model      = AOSAI_Task::get_instance();
        $milestone_model = AOSAI_Milestone::get_instance();

        foreach ( $projects as &$project ) {
            $project_id  = (int) ( $project['id'] ?? 0 );
            $task_preview = $task_model->get_project_tasks(
                $project_id,
                array(
                    'per_page' => 6,
                    'page'     => 1,
                    'orderby'  => 'due_date',
                    'order'    => 'ASC',
                )
            );

            if ( 'client' === $portal_type ) {
                $task_preview = array_values(
                    array_filter(
                        $task_preview,
                        static fn( array $task ): bool => empty( $task['is_private'] )
                    )
                );
            }

            $milestones = $milestone_model->get_project_milestones( $project_id );
            $visible_task_stats = $this->get_visible_task_stats_for_project( $project_id, $portal_type );

            $project['task_preview']                = array_slice( $task_preview, 0, 4 );
            $project['milestone_preview']           = array_slice( $milestones, 0, 4 );
            $project['next_milestone']              = $this->get_next_milestone( $milestones );
            $project['visible_task_count']          = $visible_task_stats['total'];
            $project['visible_completed_task_count'] = $visible_task_stats['completed'];
            $project['open_task_count']             = $visible_task_stats['open'];
            $project['milestone_count']             = count( $milestones );

            if ( 'client' === $portal_type && $visible_task_stats['total'] > 0 ) {
                $project['progress'] = $visible_task_stats['percentage'];
            }
        }

        return $projects;
    }

    private function get_visible_task_stats_for_project( int $project_id, string $portal_type ): array {
        global $wpdb;

        $tasks_table = esc_sql( $wpdb->prefix . 'aosai_tasks' );
        $args        = array( $project_id );
        $sql         = "SELECT
                    COUNT(*) as total,
                    SUM(CASE WHEN status IN ('done', 'completed') THEN 1 ELSE 0 END) as completed
                FROM " . $tasks_table . ' WHERE project_id = %d';

        if ( 'client' === $portal_type ) {
            $sql .= ' AND is_private = 0';
        }

        $stats = $wpdb->get_row(
            $wpdb->prepare( $sql, $args ),
            ARRAY_A
        );

        $total      = absint( $stats['total'] ?? 0 );
        $completed  = absint( $stats['completed'] ?? 0 );
        $percentage = $total > 0 ? round( ( $completed / $total ) * 100, 1 ) : 0;

        return array(
            'total'      => $total,
            'completed'  => $completed,
            'open'       => max( 0, $total - $completed ),
            'percentage' => $percentage,
        );
    }

    private function get_next_milestone( array $milestones ): ?array {
        $upcoming = array_values(
            array_filter(
                $milestones,
                static function( array $milestone ): bool {
                    return 'completed' !== (string) ( $milestone['status'] ?? '' );
                }
            )
        );

        if ( empty( $upcoming ) ) {
            return null;
        }

        usort(
            $upcoming,
            static function( array $left, array $right ): int {
                $left_due  = ! empty( $left['due_date'] ) ? strtotime( (string) $left['due_date'] ) : PHP_INT_MAX;
                $right_due = ! empty( $right['due_date'] ) ? strtotime( (string) $right['due_date'] ) : PHP_INT_MAX;

                return $left_due <=> $right_due;
            }
        );

        return $upcoming[0] ?? null;
    }

    private function count_projects_for_user( int $user_id ): int {
        global $wpdb;

        if ( user_can( $user_id, 'manage_options' ) || user_can( $user_id, 'aosai_manage_projects' ) ) {
            return (int) $wpdb->get_var( "SELECT COUNT(*) FROM {$wpdb->prefix}aosai_projects" );
        }

        return (int) $wpdb->get_var(
            $wpdb->prepare(
                "SELECT COUNT(DISTINCT p.id)
                FROM {$wpdb->prefix}aosai_projects p
                INNER JOIN {$wpdb->prefix}aosai_project_users pu ON p.id = pu.project_id
                WHERE pu.user_id = %d",
                $user_id
            )
        );
    }

    private function count_tasks_for_user( int $user_id ): int {
        global $wpdb;

        if ( 'client' === aosai_get_user_portal_type( $user_id ) ) {
            return (int) $wpdb->get_var(
                $wpdb->prepare(
                    "SELECT COUNT(DISTINCT t.id)
                    FROM {$wpdb->prefix}aosai_tasks t
                    INNER JOIN {$wpdb->prefix}aosai_project_users pu ON t.project_id = pu.project_id
                    LEFT JOIN {$wpdb->prefix}aosai_projects p ON t.project_id = p.id
                    WHERE pu.user_id = %d
                    AND p.id IS NOT NULL
                    AND t.is_private = 0",
                    $user_id
                )
            );
        }

        $has_workspace_scope = user_can( $user_id, 'manage_options' ) || user_can( $user_id, 'aosai_manage_projects' ) || user_can( $user_id, 'aosai_manage_tickets' );

        if ( $has_workspace_scope ) {
            return (int) $wpdb->get_var(
                "SELECT COUNT(*)
                FROM {$wpdb->prefix}aosai_tasks t
                LEFT JOIN {$wpdb->prefix}aosai_projects p ON t.project_id = p.id
                WHERE p.id IS NOT NULL"
            );
        }

        return (int) $wpdb->get_var(
            $wpdb->prepare(
                "SELECT COUNT(DISTINCT t.id)
                FROM {$wpdb->prefix}aosai_tasks t
                INNER JOIN {$wpdb->prefix}aosai_task_users tu ON t.id = tu.task_id
                INNER JOIN {$wpdb->prefix}aosai_project_users pu ON t.project_id = pu.project_id AND pu.user_id = %d
                LEFT JOIN {$wpdb->prefix}aosai_projects p ON t.project_id = p.id
                WHERE tu.user_id = %d AND p.id IS NOT NULL",
                $user_id,
                $user_id
            )
        );
    }

    private function count_overdue_tasks_for_user( int $user_id ): int {
        global $wpdb;

        if ( 'client' === aosai_get_user_portal_type( $user_id ) ) {
            return (int) $wpdb->get_var(
                $wpdb->prepare(
                    "SELECT COUNT(DISTINCT t.id)
                    FROM {$wpdb->prefix}aosai_tasks t
                    INNER JOIN {$wpdb->prefix}aosai_project_users pu ON t.project_id = pu.project_id
                    LEFT JOIN {$wpdb->prefix}aosai_projects p ON t.project_id = p.id
                    WHERE pu.user_id = %d
                    AND p.id IS NOT NULL
                    AND t.is_private = 0
                    AND t.status NOT IN ('done', 'completed')
                    AND t.due_date IS NOT NULL
                    AND t.due_date < %s",
                    $user_id,
                    current_time( 'Y-m-d' )
                )
            );
        }

        $has_workspace_scope = user_can( $user_id, 'manage_options' ) || user_can( $user_id, 'aosai_manage_projects' ) || user_can( $user_id, 'aosai_manage_tickets' );

        if ( $has_workspace_scope ) {
            return (int) $wpdb->get_var(
                $wpdb->prepare(
                    "SELECT COUNT(*)
                    FROM {$wpdb->prefix}aosai_tasks t
                    LEFT JOIN {$wpdb->prefix}aosai_projects p ON t.project_id = p.id
                    WHERE p.id IS NOT NULL
                    AND t.status NOT IN ('done', 'completed')
                    AND t.due_date IS NOT NULL
                    AND t.due_date < %s",
                    current_time( 'Y-m-d' )
                )
            );
        }

        return (int) $wpdb->get_var(
            $wpdb->prepare(
                "SELECT COUNT(DISTINCT t.id)
                FROM {$wpdb->prefix}aosai_tasks t
                INNER JOIN {$wpdb->prefix}aosai_task_users tu ON t.id = tu.task_id
                INNER JOIN {$wpdb->prefix}aosai_project_users pu ON t.project_id = pu.project_id AND pu.user_id = %d
                LEFT JOIN {$wpdb->prefix}aosai_projects p ON t.project_id = p.id
                WHERE tu.user_id = %d
                AND p.id IS NOT NULL
                AND t.status NOT IN ('done', 'completed')
                AND t.due_date IS NOT NULL
                AND t.due_date < %s",
                $user_id,
                $user_id,
                current_time( 'Y-m-d' )
            )
        );
    }

    private function count_tickets_for_user( int $user_id ): int {
        global $wpdb;

        if ( user_can( $user_id, 'manage_options' ) || user_can( $user_id, 'aosai_manage_tickets' ) ) {
            return (int) $wpdb->get_var( "SELECT COUNT(*) FROM {$wpdb->prefix}aosai_tickets" );
        }

        return (int) $wpdb->get_var(
            $wpdb->prepare(
                "SELECT COUNT(*)
                FROM {$wpdb->prefix}aosai_tickets
                WHERE requester_id = %d OR assignee_id = %d",
                $user_id,
                $user_id
            )
        );
    }

    private function count_messages_for_user( int $user_id ): int {
        global $wpdb;

        if ( user_can( $user_id, 'manage_options' ) || user_can( $user_id, 'aosai_manage_projects' ) ) {
            return (int) $wpdb->get_var( "SELECT COUNT(*) FROM {$wpdb->prefix}aosai_messages" );
        }

        return (int) $wpdb->get_var(
            $wpdb->prepare(
                "SELECT COUNT(DISTINCT m.id)
                FROM {$wpdb->prefix}aosai_messages m
                LEFT JOIN {$wpdb->prefix}aosai_project_users pu ON m.project_id = pu.project_id
                WHERE ( m.project_id = 0 OR pu.user_id = %d )
                AND ( m.is_private = 0 OR m.created_by = %d )",
                $user_id,
                $user_id
            )
        );
    }

    private function setting_key_to_option_key( string $setting_key ): string {
        return match ( $setting_key ) {
            'portal_page_id' => 'aosai_portal_page_id',
            'portal_login_page_id' => 'aosai_portal_login_page_id',
            'portal_ticket_page_id' => 'aosai_portal_ticket_page_id',
            default => $setting_key,
        };
    }
}

