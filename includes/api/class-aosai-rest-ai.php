<?php
if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

class AOSAI_REST_AI extends WP_REST_Controller {
    
    protected $namespace = 'aosai/v1';
    
    public function register_routes() {
        register_rest_route( $this->namespace, '/ai/generate-tasks', array(
            array(
                'methods'             => WP_REST_Server::CREATABLE,
                'callback'            => array( $this, 'generate_tasks' ),
                'permission_callback' => array( $this, 'ai_permissions_check' ),
            ),
        ) );
        
        register_rest_route( $this->namespace, '/ai/suggest-description', array(
            array(
                'methods'             => WP_REST_Server::CREATABLE,
                'callback'            => array( $this, 'suggest_description' ),
                'permission_callback' => array( $this, 'ai_permissions_check' ),
            ),
        ) );
        
        register_rest_route( $this->namespace, '/ai/chat', array(
            array(
                'methods'             => WP_REST_Server::CREATABLE,
                'callback'            => array( $this, 'chat' ),
                'permission_callback' => array( $this, 'ai_permissions_check' ),
            ),
        ) );

        register_rest_route( $this->namespace, '/ai/productivity-brief', array(
            array(
                'methods'             => WP_REST_Server::CREATABLE,
                'callback'            => array( $this, 'productivity_brief' ),
                'permission_callback' => array( $this, 'ai_permissions_check' ),
            ),
        ) );

        register_rest_route( $this->namespace, '/ai/team-brief', array(
            array(
                'methods'             => WP_REST_Server::CREATABLE,
                'callback'            => array( $this, 'team_brief' ),
                'permission_callback' => array( $this, 'team_brief_permissions_check' ),
            ),
        ) );

        register_rest_route( $this->namespace, '/ai/ticket-assist', array(
            array(
                'methods'             => WP_REST_Server::CREATABLE,
                'callback'            => array( $this, 'ticket_assist' ),
                'permission_callback' => array( $this, 'ticket_assist_permissions_check' ),
            ),
        ) );
        
        register_rest_route( $this->namespace, '/ai/providers', array(
            array(
                'methods'             => WP_REST_Server::READABLE,
                'callback'            => array( $this, 'get_providers' ),
                'permission_callback' => array( $this, 'ai_permissions_check' ),
            ),
        ) );
        
        register_rest_route( $this->namespace, '/ai/models', array(
            array(
                'methods'             => WP_REST_Server::READABLE,
                'callback'            => array( $this, 'get_models' ),
                'permission_callback' => array( $this, 'ai_permissions_check' ),
            ),
        ) );
    }
    
    public function ai_permissions_check( $request ) {
        if ( ! is_user_logged_in() ) {
            return new WP_Error( 'rest_not_logged_in', esc_html__( 'You must be logged in.', 'agency-os-ai' ), array( 'status' => 401 ) );
        }
        if ( ! current_user_can( 'aosai_use_ai' ) ) {
            return new WP_Error( 'rest_forbidden', esc_html__( 'You do not have permission to use AI features.', 'agency-os-ai' ), array( 'status' => 403 ) );
        }
        return true;
    }

    public function team_brief_permissions_check( $request ) {
        if ( ! is_user_logged_in() ) {
            return new WP_Error( 'rest_not_logged_in', esc_html__( 'You must be logged in.', 'agency-os-ai' ), array( 'status' => 401 ) );
        }

        if ( ! current_user_can( 'aosai_view_reports' ) && ! current_user_can( 'manage_options' ) ) {
            return new WP_Error( 'rest_forbidden', esc_html__( 'You do not have permission to view team insights.', 'agency-os-ai' ), array( 'status' => 403 ) );
        }

        return true;
    }

    public function ticket_assist_permissions_check( $request ) {
        if ( ! is_user_logged_in() ) {
            return new WP_Error( 'rest_not_logged_in', esc_html__( 'You must be logged in.', 'agency-os-ai' ), array( 'status' => 401 ) );
        }

        if ( current_user_can( 'aosai_submit_tickets' ) || current_user_can( 'aosai_manage_tickets' ) || current_user_can( 'aosai_access_portal' ) ) {
            return true;
        }

        return new WP_Error( 'rest_forbidden', esc_html__( 'You do not have permission to use ticket assistance.', 'agency-os-ai' ), array( 'status' => 403 ) );
    }
    
    public function generate_tasks( $request ) {
        $data = $request->get_json_params();
        
        $result = AOSAI_AI_Service::get_instance()->generate_tasks( array(
            'project_title'       => sanitize_text_field( $data['project_title'] ?? '' ),
            'project_description'=> sanitize_textarea_field( $data['project_description'] ?? '' ),
            'num_tasks'          => absint( $data['num_tasks'] ?? 15 ),
            'project_id'         => absint( $data['project_id'] ?? 0 ),
            'model'             => sanitize_text_field( $data['model'] ?? '' ),
            'provider'          => sanitize_key( $data['provider'] ?? '' ),
        ) );
        
        if ( is_wp_error( $result ) ) {
            return $result;
        }
        
        return rest_ensure_response( $result );
    }
    
    public function suggest_description( $request ) {
        $data = $request->get_json_params();
        
        $result = AOSAI_AI_Service::get_instance()->suggest_description(
            sanitize_text_field( $data['title'] ?? '' ),
            sanitize_textarea_field( $data['context'] ?? '' ),
            sanitize_key( $data['provider'] ?? '' )
        );
        
        if ( is_wp_error( $result ) ) {
            return $result;
        }
        
        return rest_ensure_response( array( 'description' => $result ) );
    }
    
    public function chat( $request ) {
        $data = $request->get_json_params();

        // Support both `message` (single string) and `messages` (array format)
        if ( ! empty( $data['message'] ) ) {
            $messages = array(
                array(
                    'role'    => 'user',
                    'content' => sanitize_textarea_field( wp_unslash( $data['message'] ) ),
                ),
            );
        } else {
            $messages = array_map( function( $m ) {
                return array(
                    'role'    => sanitize_key( $m['role'] ?? 'user' ),
                    'content' => sanitize_textarea_field( wp_unslash( $m['content'] ?? '' ) ),
                );
            }, (array) ( $data['messages'] ?? array() ) );
        }

        $result = AOSAI_AI_Service::get_instance()->chat( $messages, array(
            'model'    => sanitize_text_field( $data['model'] ?? '' ),
            'provider' => sanitize_key( $data['provider'] ?? '' ),
        ) );

        if ( is_wp_error( $result ) ) {
            return $result;
        }

        // Normalize: ensure response has `reply` field
        if ( is_array( $result ) && ! isset( $result['reply'] ) ) {
            $result['reply'] = $result['content'] ?? $result['message'] ?? '';
        }

        return rest_ensure_response( $result );
    }

    public function productivity_brief( $request ) {
        $payload = $request->get_json_params();

        $overview = is_array( $payload['overview'] ?? null ) ? $payload['overview'] : array();
        $project_stats = array_slice( is_array( $payload['project_stats'] ?? null ) ? $payload['project_stats'] : array(), 0, 8 );
        $member_performance = array_slice( is_array( $payload['member_performance'] ?? null ) ? $payload['member_performance'] : array(), 0, 8 );
        $task_by_status = array_slice( is_array( $payload['task_by_status'] ?? null ) ? $payload['task_by_status'] : array(), 0, 10 );

        $fallback = $this->build_fallback_productivity_brief( $overview, $project_stats, $member_performance, $task_by_status );

        $provider = AOSAI_AI_Service::get_instance()->get_provider( 'openai' );
        if ( ! $provider || ! $provider->is_configured() ) {
            return rest_ensure_response( $fallback );
        }

        $report_payload = array(
            'overview'           => $overview,
            'project_stats'      => $project_stats,
            'member_performance' => $member_performance,
            'task_by_status'     => $task_by_status,
        );

        $messages = array(
            array(
                'role'    => 'system',
                'content' => 'You are an operations and delivery coach for an agency workspace. Review the workspace report payload and return practical management guidance. Respond with valid JSON only using this shape: {"summary":"...", "action_items":["...", "...", "..."], "risks":["...", "..."], "wins":["...", "..."]}. Keep summary under 120 words. Keep each list item under 20 words.',
            ),
            array(
                'role'    => 'user',
                'content' => 'Create an actionable productivity brief for this workspace report payload: ' . wp_json_encode( $report_payload ),
            ),
        );

        $result = AOSAI_AI_Service::get_instance()->chat(
            $messages,
            array(
                'provider' => 'openai',
                'model'    => sanitize_text_field( get_option( 'aosai_openai_model', 'gpt-4o-mini' ) ),
                'action'   => 'productivity_brief',
            )
        );

        if ( is_wp_error( $result ) ) {
            $fallback['message'] = $result->get_error_message();
            return rest_ensure_response( $fallback );
        }

        $content = (string) ( $result['content'] ?? '' );
        $decoded = json_decode( $content, true );

        if ( ! is_array( $decoded ) ) {
            $fallback['message'] = __( 'AI returned an unreadable response. Showing the local productivity brief instead.', 'agency-os-ai' );
            return rest_ensure_response( $fallback );
        }

        return rest_ensure_response(
            array(
                'source'       => 'ai',
                'summary'      => sanitize_textarea_field( (string) ( $decoded['summary'] ?? $fallback['summary'] ) ),
                'action_items' => $this->sanitize_string_list( $decoded['action_items'] ?? $fallback['action_items'] ),
                'risks'        => $this->sanitize_string_list( $decoded['risks'] ?? $fallback['risks'] ),
                'wins'         => $this->sanitize_string_list( $decoded['wins'] ?? $fallback['wins'] ),
                'message'      => __( 'AI productivity brief generated from your live workspace metrics.', 'agency-os-ai' ),
            )
        );
    }

    public function team_brief( $request ) {
        $payload = $request->get_json_params();

        $overview           = is_array( $payload['overview'] ?? null ) ? $payload['overview'] : array();
        $member_performance = array_slice( is_array( $payload['member_performance'] ?? null ) ? $payload['member_performance'] : array(), 0, 12 );
        $users              = array_slice( is_array( $payload['users'] ?? null ) ? $payload['users'] : array(), 0, 20 );

        $fallback = $this->build_fallback_team_brief( $overview, $member_performance, $users );

        $provider = AOSAI_AI_Service::get_instance()->get_provider( 'openai' );
        if ( ! $provider || ! $provider->is_configured() ) {
            return rest_ensure_response( $fallback );
        }

        $messages = array(
            array(
                'role'    => 'system',
                'content' => 'You are a team operations coach for a WordPress agency workspace. Review the payload and return valid JSON only using this shape: {"summary":"...","coaching_points":["..."],"spotlight":"...","risks":["..."]}. Keep summary under 120 words. Keep coaching points and risks short and specific.',
            ),
            array(
                'role'    => 'user',
                'content' => 'Create a delivery coaching brief from this payload: ' . wp_json_encode(
                    array(
                        'overview'           => $overview,
                        'member_performance' => $member_performance,
                        'users'              => $users,
                    )
                ),
            ),
        );

        $result = AOSAI_AI_Service::get_instance()->chat(
            $messages,
            array(
                'provider' => 'openai',
                'model'    => sanitize_text_field( get_option( 'aosai_openai_model', 'gpt-4o-mini' ) ),
                'action'   => 'team_brief',
            )
        );

        if ( is_wp_error( $result ) ) {
            $fallback['message'] = $result->get_error_message();
            return rest_ensure_response( $fallback );
        }

        $decoded = json_decode( (string) ( $result['content'] ?? '' ), true );
        if ( ! is_array( $decoded ) ) {
            $fallback['message'] = __( 'AI returned an unreadable coaching brief. Showing the local team summary instead.', 'agency-os-ai' );
            return rest_ensure_response( $fallback );
        }

        return rest_ensure_response(
            array(
                'source'          => 'ai',
                'summary'         => sanitize_textarea_field( (string) ( $decoded['summary'] ?? $fallback['summary'] ) ),
                'coaching_points' => $this->sanitize_string_list( $decoded['coaching_points'] ?? $fallback['coaching_points'] ),
                'spotlight'       => sanitize_text_field( (string) ( $decoded['spotlight'] ?? $fallback['spotlight'] ) ),
                'risks'           => $this->sanitize_string_list( $decoded['risks'] ?? $fallback['risks'] ),
                'message'         => __( 'AI team coaching brief generated from your live team and report data.', 'agency-os-ai' ),
            )
        );
    }

    public function ticket_assist( $request ) {
        $payload = $request->get_json_params();
        $subject = sanitize_text_field( (string) ( $payload['subject'] ?? '' ) );
        $content = sanitize_textarea_field( (string) ( $payload['content'] ?? '' ) );

        if ( '' === $subject && '' === $content ) {
            return new WP_Error( 'missing_context', esc_html__( 'Please add a ticket subject or message before requesting AI help.', 'agency-os-ai' ), array( 'status' => 400 ) );
        }

        $department_model = AOSAI_Department::get_instance();
        $departments      = $department_model->get_all();
        $department       = $department_model->suggest_department( $subject, $content );
        $fallback         = $this->build_fallback_ticket_assist( $subject, $content, $departments, $department );

        $provider = AOSAI_AI_Service::get_instance()->get_provider( 'openai' );
        if ( ! $provider || ! $provider->is_configured() ) {
            return rest_ensure_response( $fallback );
        }

        $department_choices = array();
        foreach ( $departments as $item ) {
            $department_choices[] = array(
                'name'        => sanitize_text_field( (string) ( $item['name'] ?? '' ) ),
                'slug'        => sanitize_key( (string) ( $item['slug'] ?? '' ) ),
                'description' => sanitize_text_field( (string) ( $item['description'] ?? '' ) ),
            );
        }

        $messages = array(
            array(
                'role'    => 'system',
                'content' => 'You are a support triage assistant for an agency workspace. Return valid JSON only using this shape: {"summary":"...","priority":"low|medium|high|urgent","department_slug":"...","first_response":"...","tags":["..."]}. Keep summary under 60 words. Keep first_response helpful and concise.',
            ),
            array(
                'role'    => 'user',
                'content' => 'Triage this support request and recommend the best next step. Available departments: ' . wp_json_encode( $department_choices ) . "\n\nSubject: {$subject}\nContent: {$content}",
            ),
        );

        $result = AOSAI_AI_Service::get_instance()->chat(
            $messages,
            array(
                'provider' => 'openai',
                'model'    => sanitize_text_field( get_option( 'aosai_openai_model', 'gpt-4o-mini' ) ),
                'action'   => 'ticket_assist',
            )
        );

        if ( is_wp_error( $result ) ) {
            $fallback['message'] = $result->get_error_message();
            return rest_ensure_response( $fallback );
        }

        $decoded = json_decode( (string) ( $result['content'] ?? '' ), true );
        if ( ! is_array( $decoded ) ) {
            $fallback['message'] = __( 'AI returned an unreadable ticket assist response. Showing the local triage suggestions instead.', 'agency-os-ai' );
            return rest_ensure_response( $fallback );
        }

        $matched_department = $this->match_department_from_value(
            $departments,
            (string) ( $decoded['department_slug'] ?? $decoded['department_name'] ?? '' )
        );
        if ( ! $matched_department ) {
            $matched_department = $department;
        }

        $priority = sanitize_key( (string) ( $decoded['priority'] ?? $fallback['priority'] ) );
        if ( ! in_array( $priority, array( 'low', 'medium', 'high', 'urgent' ), true ) ) {
            $priority = $fallback['priority'];
        }

        return rest_ensure_response(
            array(
                'source'          => 'ai',
                'department_id'   => (int) ( $matched_department['id'] ?? 0 ),
                'department_name' => sanitize_text_field( (string) ( $matched_department['name'] ?? __( 'General Support', 'agency-os-ai' ) ) ),
                'priority'        => $priority,
                'summary'         => sanitize_textarea_field( (string) ( $decoded['summary'] ?? $fallback['summary'] ) ),
                'first_response'  => sanitize_textarea_field( (string) ( $decoded['first_response'] ?? $fallback['first_response'] ) ),
                'tags'            => $this->sanitize_string_list( $decoded['tags'] ?? $fallback['tags'] ),
                'message'         => __( 'AI triage suggestions are ready for this ticket request.', 'agency-os-ai' ),
            )
        );
    }
    
    public function get_providers( $request ) {
        $providers = AOSAI_AI_Service::get_instance()->get_available_providers();
        $response = array( 'data' => $providers );
        return new WP_REST_Response( $response, 200 );
    }
    
    public function get_models( $request ) {
        $provider_id = sanitize_key( $request->get_param( 'provider' ) ) ?: 'openai';
        $provider = AOSAI_AI_Service::get_instance()->get_provider( $provider_id );
        
        if ( ! $provider ) {
            return new WP_Error( 'invalid_provider', esc_html__( 'Invalid AI provider.', 'agency-os-ai' ), array( 'status' => 400 ) );
        }
        
        $models = $provider->get_models();
        $response = array( 'data' => $models );
        return new WP_REST_Response( $response, 200 );
    }

    private function build_fallback_productivity_brief( array $overview, array $project_stats, array $member_performance, array $task_by_status ): array {
        $total_projects = absint( $overview['total_projects'] ?? 0 );
        $total_tasks    = absint( $overview['total_tasks'] ?? 0 );
        $completed      = absint( $overview['completed_tasks'] ?? 0 );
        $overdue        = absint( $overview['overdue_tasks'] ?? 0 );
        $completion     = isset( $overview['completion_rate'] ) ? floatval( $overview['completion_rate'] ) : 0;

        $top_project = null;
        foreach ( $project_stats as $project ) {
            if ( ! is_array( $project ) ) {
                continue;
            }

            if ( null === $top_project || floatval( $project['progress'] ?? 0 ) > floatval( $top_project['progress'] ?? 0 ) ) {
                $top_project = $project;
            }
        }

        $top_member = null;
        foreach ( $member_performance as $member ) {
            if ( ! is_array( $member ) ) {
                continue;
            }

            if ( null === $top_member || absint( $member['completed'] ?? 0 ) > absint( $top_member['completed'] ?? 0 ) ) {
                $top_member = $member;
            }
        }

        $blocked_count = 0;
        foreach ( $task_by_status as $row ) {
            if ( ! is_array( $row ) ) {
                continue;
            }

            $status = sanitize_key( (string) ( $row['status'] ?? '' ) );
            if ( in_array( $status, array( 'backlog', 'todo', 'open', 'in_review' ), true ) ) {
                $blocked_count += absint( $row['count'] ?? 0 );
            }
        }

        $summary_parts = array();
        if ( $total_projects > 0 ) {
            $summary_parts[] = sprintf(
                /* translators: 1: project count, 2: task count */
                __( '%1$d projects are active with %2$d tracked tasks.', 'agency-os-ai' ),
                $total_projects,
                $total_tasks
            );
        }
        if ( $completion > 0 ) {
            $summary_parts[] = sprintf(
                /* translators: %s: completion rate */
                __( 'Overall completion is %s%%.', 'agency-os-ai' ),
                number_format_i18n( $completion, 1 )
            );
        }
        if ( $overdue > 0 ) {
            $summary_parts[] = sprintf(
                /* translators: %d: overdue task count */
                __( '%d tasks need immediate deadline recovery.', 'agency-os-ai' ),
                $overdue
            );
        } else {
            $summary_parts[] = __( 'There are no overdue tasks right now.', 'agency-os-ai' );
        }

        $action_items = array();
        if ( $overdue > 0 ) {
            $action_items[] = sprintf( __( 'Triage %d overdue tasks and reassign blocked work.', 'agency-os-ai' ), $overdue );
        }
        if ( $blocked_count > 0 ) {
            $action_items[] = sprintf( __( 'Review %d items sitting in backlog or review.', 'agency-os-ai' ), $blocked_count );
        }
        if ( $top_project && floatval( $top_project['progress'] ?? 0 ) < 50 ) {
            $action_items[] = sprintf( __( 'Push %s past the halfway mark this week.', 'agency-os-ai' ), sanitize_text_field( (string) $top_project['name'] ) );
        }
        if ( empty( $action_items ) ) {
            $action_items[] = __( 'Protect current delivery momentum and keep weekly planning tight.', 'agency-os-ai' );
        }

        $risks = array();
        if ( $overdue > 0 ) {
            $risks[] = __( 'Overdue tasks could delay client delivery windows.', 'agency-os-ai' );
        }
        if ( $blocked_count > $completed ) {
            $risks[] = __( 'Blocked or waiting work is outpacing completed output.', 'agency-os-ai' );
        }
        if ( empty( $risks ) ) {
            $risks[] = __( 'No major operational risk signals detected from this snapshot.', 'agency-os-ai' );
        }

        $wins = array();
        if ( $top_member && ! empty( $top_member['name'] ) ) {
            $wins[] = sprintf( __( '%s is leading the delivery pace.', 'agency-os-ai' ), sanitize_text_field( (string) $top_member['name'] ) );
        }
        if ( $top_project && ! empty( $top_project['name'] ) ) {
            $wins[] = sprintf(
                __( '%s is currently the strongest project by progress.', 'agency-os-ai' ),
                sanitize_text_field( (string) $top_project['name'] )
            );
        }
        if ( $completed > 0 ) {
            $wins[] = sprintf( __( '%d tasks have already been completed.', 'agency-os-ai' ), $completed );
        }

        return array(
            'source'       => 'fallback',
            'summary'      => implode( ' ', array_filter( $summary_parts ) ),
            'action_items' => array_slice( array_values( array_unique( $action_items ) ), 0, 3 ),
            'risks'        => array_slice( array_values( array_unique( $risks ) ), 0, 3 ),
            'wins'         => array_slice( array_values( array_unique( $wins ) ), 0, 3 ),
            'message'      => __( 'Generated a local productivity brief from current workspace metrics.', 'agency-os-ai' ),
        );
    }

    private function build_fallback_team_brief( array $overview, array $member_performance, array $users ): array {
        $top_member = null;
        foreach ( $member_performance as $member ) {
            if ( ! is_array( $member ) ) {
                continue;
            }

            if ( null === $top_member || absint( $member['completed'] ?? 0 ) > absint( $top_member['completed'] ?? 0 ) ) {
                $top_member = $member;
            }
        }

        $most_loaded = null;
        foreach ( $users as $user ) {
            if ( ! is_array( $user ) ) {
                continue;
            }

            if ( null === $most_loaded || absint( $user['task_count'] ?? 0 ) > absint( $most_loaded['task_count'] ?? 0 ) ) {
                $most_loaded = $user;
            }
        }

        $overdue          = absint( $overview['overdue_tasks'] ?? 0 );
        $completion_rate  = floatval( $overview['completion_rate'] ?? 0 );
        $active_projects  = absint( $overview['active_projects'] ?? 0 );
        $team_members     = count( $users );
        $completed_tasks  = absint( $overview['completed_tasks'] ?? 0 );

        $summary_parts = array(
            sprintf(
                /* translators: 1: active project count, 2: member count */
                __( '%1$d active projects are being covered by %2$d visible team members.', 'agency-os-ai' ),
                $active_projects,
                $team_members
            ),
            sprintf(
                /* translators: %s: completion rate */
                __( 'Current completion rate is %s%%.', 'agency-os-ai' ),
                number_format_i18n( $completion_rate, 1 )
            ),
        );

        if ( $overdue > 0 ) {
            $summary_parts[] = sprintf( __( '%d overdue tasks still need recovery planning.', 'agency-os-ai' ), $overdue );
        } else {
            $summary_parts[] = __( 'No overdue work is currently blocking delivery.', 'agency-os-ai' );
        }

        $coaching_points = array();
        if ( $overdue > 0 ) {
            $coaching_points[] = sprintf( __( 'Run a recovery pass on %d overdue tasks.', 'agency-os-ai' ), $overdue );
        }
        if ( $most_loaded && absint( $most_loaded['task_count'] ?? 0 ) > 6 ) {
            $coaching_points[] = sprintf(
                __( 'Rebalance workload around %s.', 'agency-os-ai' ),
                sanitize_text_field( (string) ( $most_loaded['display_name'] ?? $most_loaded['name'] ?? __( 'the busiest teammate', 'agency-os-ai' ) ) )
            );
        }
        if ( $completion_rate < 60 ) {
            $coaching_points[] = __( 'Tighten weekly priorities to improve finish rate.', 'agency-os-ai' );
        }
        if ( empty( $coaching_points ) ) {
            $coaching_points[] = __( 'Protect momentum and keep daily ownership clear.', 'agency-os-ai' );
        }

        $risks = array();
        if ( $overdue > 0 ) {
            $risks[] = __( 'Overdue tasks can compress client delivery timelines.', 'agency-os-ai' );
        }
        if ( $completion_rate < 45 ) {
            $risks[] = __( 'Low completion rate suggests work is being started faster than finished.', 'agency-os-ai' );
        }
        if ( empty( $risks ) ) {
            $risks[] = __( 'No major team delivery risk stands out from this snapshot.', 'agency-os-ai' );
        }

        $spotlight = $top_member && ! empty( $top_member['name'] )
            ? sprintf(
                __( '%1$s is leading output with %2$d completed tasks.', 'agency-os-ai' ),
                sanitize_text_field( (string) $top_member['name'] ),
                absint( $top_member['completed'] ?? 0 )
            )
            : sprintf(
                __( '%d tasks have already been completed across the workspace.', 'agency-os-ai' ),
                $completed_tasks
            );

        return array(
            'source'          => 'fallback',
            'summary'         => implode( ' ', array_filter( $summary_parts ) ),
            'coaching_points' => array_slice( array_values( array_unique( $coaching_points ) ), 0, 4 ),
            'spotlight'       => $spotlight,
            'risks'           => array_slice( array_values( array_unique( $risks ) ), 0, 3 ),
            'message'         => __( 'Generated a local team coaching brief from current workspace data.', 'agency-os-ai' ),
        );
    }

    private function build_fallback_ticket_assist( string $subject, string $content, array $departments, ?array $department ): array {
        $priority       = $this->determine_fallback_ticket_priority( $subject . ' ' . $content );
        $summary_source = trim( $subject . ' ' . wp_strip_all_tags( $content ) );
        $summary        = wp_trim_words( $summary_source, 28, '...' );
        $department_name = sanitize_text_field( (string) ( $department['name'] ?? ( $departments[0]['name'] ?? __( 'General Support', 'agency-os-ai' ) ) ) );
        $department_id   = absint( $department['id'] ?? 0 );

        $first_response = sprintf(
            /* translators: %s: department name */
            __( 'Thanks for the clear request. We have routed this to %s and will review the next best action shortly.', 'agency-os-ai' ),
            $department_name
        );

        return array(
            'source'          => 'fallback',
            'department_id'   => $department_id,
            'department_name' => $department_name,
            'priority'        => $priority,
            'summary'         => '' !== $summary ? $summary : __( 'Support request captured and ready for triage.', 'agency-os-ai' ),
            'first_response'  => $first_response,
            'tags'            => $this->extract_ticket_tags( $subject . ' ' . $content, $department_name ),
            'message'         => __( 'Generated a local ticket triage suggestion from the request details.', 'agency-os-ai' ),
        );
    }

    private function determine_fallback_ticket_priority( string $text ): string {
        $haystack = strtolower( $text );

        if ( preg_match( '/\b(down|outage|urgent|asap|immediately|payment failed|security|critical|blocked)\b/', $haystack ) ) {
            return 'urgent';
        }

        if ( preg_match( '/\b(error|broken|issue|not working|failed|stuck|cannot|can\'t)\b/', $haystack ) ) {
            return 'high';
        }

        if ( preg_match( '/\b(request|question|clarify|update|change)\b/', $haystack ) ) {
            return 'medium';
        }

        return 'low';
    }

    private function extract_ticket_tags( string $text, string $department_name = '' ): array {
        $haystack = strtolower( $text );
        $tags     = array();

        $map = array(
            'bug'          => array( 'bug', 'error', 'broken', 'issue' ),
            'login'        => array( 'login', 'password', 'access' ),
            'integration'  => array( 'api', 'integration', 'webhook' ),
            'billing'      => array( 'invoice', 'payment', 'billing', 'refund' ),
            'design'       => array( 'design', 'layout', 'ui', 'ux' ),
            'performance'  => array( 'slow', 'performance', 'speed' ),
            'feature'      => array( 'feature', 'request', 'enhancement' ),
        );

        foreach ( $map as $tag => $keywords ) {
            foreach ( $keywords as $keyword ) {
                if ( false !== strpos( $haystack, $keyword ) ) {
                    $tags[] = $tag;
                    break;
                }
            }
        }

        if ( '' !== $department_name ) {
            $tags[] = sanitize_title( $department_name );
        }

        return array_slice( array_values( array_unique( array_filter( $tags ) ) ), 0, 6 );
    }

    private function match_department_from_value( array $departments, string $value ): ?array {
        $value = sanitize_title( $value );
        if ( '' === $value ) {
            return null;
        }

        foreach ( $departments as $department ) {
            $slug = sanitize_title( (string) ( $department['slug'] ?? '' ) );
            $name = sanitize_title( (string) ( $department['name'] ?? '' ) );

            if ( $value === $slug || $value === $name ) {
                return $department;
            }
        }

        return null;
    }

    private function sanitize_string_list( $items ): array {
        if ( ! is_array( $items ) ) {
            return array();
        }

        $clean = array();
        foreach ( $items as $item ) {
            $value = sanitize_text_field( (string) $item );
            if ( '' !== $value ) {
                $clean[] = $value;
            }
        }

        return array_values( array_slice( array_unique( $clean ), 0, 5 ) );
    }
}
