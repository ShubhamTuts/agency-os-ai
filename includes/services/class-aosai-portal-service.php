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

        $projects = $project_model->get_user_projects( $user_id, array( 'per_page' => 8, 'page' => 1 ) );
        $tasks    = $task_model->get_my_tasks( $user_id, array( 'per_page' => 8, 'page' => 1 ) );
        $tickets  = $ticket_model->get_tickets_for_user( $user_id, array( 'per_page' => 8, 'page' => 1 ) );
        $messages = $message_model->get_messages_for_user( $user_id, array( 'per_page' => 6, 'page' => 1 ) );
        $files    = $file_model->get_user_files( $user_id, array( 'per_page' => 6, 'page' => 1 ) );

        $overdue_tasks = 0;
        foreach ( $tasks as $task ) {
            if ( ! empty( $task['due_date'] ) && ! in_array( $task['status'] ?? '', array( 'done', 'completed' ), true ) && strtotime( $task['due_date'] ) < strtotime( current_time( 'Y-m-d' ) ) ) {
                $overdue_tasks++;
            }
        }

        $user = $user_model->get_formatted_user( $user_id ) ?: array();
        $wp_user = get_userdata( $user_id );
        $user['roles']       = $wp_user ? array_values( (array) $wp_user->roles ) : array();
        $user['portal_type'] = aosai_get_user_portal_type( $user_id );

        return array(
            'user'        => $user,
            'branding'    => $settings->get_portal_branding(),
            'navigation'  => $this->get_navigation_for_user( $user_id ),
            'stats'       => array(
                'projects'      => count( $projects ),
                'tasks'         => count( $tasks ),
                'tickets'       => count( $tickets ),
                'messages'      => count( $messages ),
                'overdue_tasks' => $overdue_tasks,
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

    private function setting_key_to_option_key( string $setting_key ): string {
        return match ( $setting_key ) {
            'portal_page_id' => 'aosai_portal_page_id',
            'portal_login_page_id' => 'aosai_portal_login_page_id',
            'portal_ticket_page_id' => 'aosai_portal_ticket_page_id',
            default => $setting_key,
        };
    }
}

