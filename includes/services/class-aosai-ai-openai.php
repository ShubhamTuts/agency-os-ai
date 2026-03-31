<?php
if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

class AOSAI_AI_OpenAI implements AOSAI_AI_Provider_Interface {
    
    private const API_URL = 'https://api.openai.com/v1/chat/completions';
    
    public function get_id(): string {
        return 'openai';
    }
    
    public function get_name(): string {
        return 'OpenAI';
    }
    
    public function is_pro(): bool {
        return false;
    }
    
    public function is_configured(): bool {
        $key = get_option( 'aosai_openai_api_key', '' );
        return ! empty( $key );
    }
    
    public function get_models(): array {
        return array(
            array( 'id' => 'gpt-4o-mini', 'name' => 'GPT-4o Mini', 'description' => 'Fast and affordable' ),
            array( 'id' => 'gpt-4o', 'name' => 'GPT-4o', 'description' => 'Most capable' ),
            array( 'id' => 'gpt-4-turbo', 'name' => 'GPT-4 Turbo', 'description' => 'High performance' ),
            array( 'id' => 'gpt-3.5-turbo', 'name' => 'GPT-3.5 Turbo', 'description' => 'Budget friendly' ),
        );
    }
    
    public function get_default_model(): string {
        $model = sanitize_text_field( (string) get_option( 'aosai_openai_model', '' ) );
        return '' !== trim( $model ) ? $model : 'gpt-4o-mini';
    }
    
    public function generate_tasks( array $params ): array|\WP_Error {
        $prompt = $this->build_task_generation_prompt( $params );
        $model  = $this->resolve_model( (string) ( $params['model'] ?? '' ) );
        
        $response = $this->make_request( array(
            'model'    => $model,
            'messages' => array(
                array(
                    'role'    => 'system',
                    'content' => 'You are a project management expert. Generate structured task lists for projects. Always respond with valid JSON in this exact format: {"task_lists":[{"title":"Task List Name","tasks":[{"title":"Task name","description":"Brief task description","priority":"low|medium|high|urgent","estimated_hours":2}]}]} Generate comprehensive, actionable tasks organized into logical task lists.',
                ),
                array(
                    'role'    => 'user',
                    'content' => $prompt,
                ),
            ),
            'temperature' => 0.7,
            'max_tokens'  => 4000,
            'response_format' => array( 'type' => 'json_object' ),
        ) );
        
        if ( is_wp_error( $response ) ) {
            return $response;
        }
        
        $content = $response['choices'][0]['message']['content'] ?? '';
        $parsed = json_decode( $content, true );
        
        if ( json_last_error() !== JSON_ERROR_NONE || ! isset( $parsed['task_lists'] ) ) {
            return new \WP_Error( 'ai_parse_error', esc_html__( 'Failed to parse AI response.', 'agency-os-ai' ) );
        }
        
        $sanitized = $this->sanitize_task_output( $parsed );
        $sanitized['usage'] = $response['usage'] ?? array();
        $sanitized['provider'] = $this->get_id();
        $sanitized['model'] = $model;
        
        return $sanitized;
    }
    
    public function suggest_description( string $title, string $context = '' ): string|\WP_Error {
        $response = $this->make_request( array(
            'model'    => $this->get_default_model(),
            'messages' => array(
                array(
                    'role'    => 'system',
                    'content' => 'You are a project management assistant. Write a concise, actionable description for the given task.',
                ),
                array(
                    'role'    => 'user',
                    'content' => sprintf(
                        'Task: %s%s',
                        sanitize_text_field( $title ),
                        $context ? "\nProject context: " . sanitize_text_field( $context ) : ''
                    ) . "\n\nWrite a 2-3 sentence description.",
                ),
            ),
            'temperature' => 0.5,
            'max_tokens'  => 500,
        ) );
        
        if ( is_wp_error( $response ) ) {
            return $response;
        }
        
        return sanitize_textarea_field( $response['choices'][0]['message']['content'] ?? '' );
    }
    
    public function chat( array $messages, array $params = array() ): array|\WP_Error {
        $model = $this->resolve_model( (string) ( $params['model'] ?? '' ) );

        $sanitized_messages = array_map( function( $msg ) {
            return array(
                'role'    => in_array( $msg['role'], array( 'user', 'assistant', 'system' ), true ) ? $msg['role'] : 'user',
                'content' => sanitize_textarea_field( $msg['content'] ?? '' ),
            );
        }, $messages );
        
        array_unshift( $sanitized_messages, array(
            'role'    => 'system',
            'content' => 'You are an AI assistant inside a WordPress project management tool called Agency OS AI. Help users with project planning, task organization, milestone tracking, and team collaboration. Be concise, practical, and actionable in your responses.',
        ) );
        
        $response = $this->make_request( array(
            'model'    => $model,
            'messages' => $sanitized_messages,
            'temperature' => 0.7,
            'max_tokens'  => 2000,
        ) );
        
        if ( is_wp_error( $response ) ) {
            return $response;
        }
        
        return array(
            'content' => sanitize_textarea_field( $response['choices'][0]['message']['content'] ?? '' ),
            'usage'   => $response['usage'] ?? array(),
        );
    }
    
    private function make_request( array $body ): array|\WP_Error {
        $api_key = get_option( 'aosai_openai_api_key', '' );
        if ( empty( $api_key ) ) {
            return new \WP_Error( 'no_api_key', esc_html__( 'OpenAI API key is not configured.', 'agency-os-ai' ) );
        }

        // Keep payload valid even when upstream callers pass an empty model.
        if ( ! isset( $body['model'] ) || '' === trim( (string) $body['model'] ) ) {
            $body['model'] = $this->get_default_model();
        }
        
        $response = wp_remote_post( self::API_URL, array(
            'timeout' => 60,
            'headers' => array(
                'Authorization' => 'Bearer ' . sanitize_text_field( $api_key ),
                'Content-Type'  => 'application/json',
            ),
            'body'    => wp_json_encode( $body ),
        ) );
        
        if ( is_wp_error( $response ) ) {
            return new \WP_Error(
                'api_request_failed',
                sprintf( esc_html__( 'API request failed: %s', 'agency-os-ai' ), $response->get_error_message() )
            );
        }
        
        $code = wp_remote_retrieve_response_code( $response );
        $body = json_decode( wp_remote_retrieve_body( $response ), true );
        
        if ( $code !== 200 ) {
            $error_msg = $body['error']['message'] ?? esc_html__( 'Unknown API error', 'agency-os-ai' );
            return new \WP_Error(
                'api_error_' . $code,
                sanitize_text_field( $error_msg ),
                array( 'status' => $code )
            );
        }
        
        return $body;
    }

    private function resolve_model( string $model ): string {
        $model = sanitize_text_field( $model );
        return '' !== trim( $model ) ? $model : $this->get_default_model();
    }
    
    private function build_task_generation_prompt( array $params ): string {
        $title = sanitize_text_field( $params['project_title'] ?? '' );
        $description = sanitize_textarea_field( $params['project_description'] ?? '' );
        $num_tasks = absint( $params['num_tasks'] ?? 15 );
        $num_tasks = min( max( $num_tasks, 5 ), 50 );
        
        return sprintf(
            "Generate a comprehensive task list for the following project:\n\nProject Title: %s\nProject Description: %s\n\nRequirements:\n- Generate approximately %d tasks\n- Organize them into 3-6 logical task lists (phases/categories)\n- Each task should have a clear, actionable title\n- Include brief descriptions\n- Assign priorities (low/medium/high/urgent)\n- Estimate hours for each task\n- Order tasks logically within each list",
            $title,
            $description,
            $num_tasks
        );
    }
    
    private function sanitize_task_output( array $data ): array {
        $sanitized = array( 'task_lists' => array() );
        
        foreach ( ( $data['task_lists'] ?? array() ) as $list ) {
            $sanitized_list = array(
                'title' => sanitize_text_field( $list['title'] ?? '' ),
                'tasks' => array(),
            );
            
            foreach ( ( $list['tasks'] ?? array() ) as $task ) {
                $priority = sanitize_key( $task['priority'] ?? 'medium' );
                if ( ! in_array( $priority, array( 'low', 'medium', 'high', 'urgent' ), true ) ) {
                    $priority = 'medium';
                }
                
                $sanitized_list['tasks'][] = array(
                    'title'           => sanitize_text_field( $task['title'] ?? '' ),
                    'description'     => sanitize_textarea_field( $task['description'] ?? '' ),
                    'priority'        => $priority,
                    'estimated_hours' => max( 0, floatval( $task['estimated_hours'] ?? 1 ) ),
                );
            }
            
            $sanitized['task_lists'][] = $sanitized_list;
        }
        
        return $sanitized;
    }
}
