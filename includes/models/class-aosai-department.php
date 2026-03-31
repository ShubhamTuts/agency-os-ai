<?php
if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

class AOSAI_Department {
    use AOSAI_Singleton;

    private function __construct() {}

    public function get_table(): string {
        global $wpdb;
        return $wpdb->prefix . 'aosai_departments';
    }

    public function get_all(): array {
        global $wpdb;
        $table = $this->get_table();
        return $wpdb->get_results( "SELECT * FROM {$table} ORDER BY is_default DESC, name ASC", ARRAY_A ) ?: array();
    }

    public function get( int $id ): ?array {
        global $wpdb;
        $table = $this->get_table();
        $row   = $wpdb->get_row( $wpdb->prepare( "SELECT * FROM {$table} WHERE id = %d", $id ), ARRAY_A );
        return $row ?: null;
    }

    public function suggest_department( string $subject, string $content ): ?array {
        $departments = $this->get_all();
        if ( empty( $departments ) ) {
            return null;
        }

        $ai_suggestion = $this->suggest_with_ai( $departments, $subject, $content );
        if ( $ai_suggestion ) {
            return $ai_suggestion;
        }

        $haystack   = strtolower( $subject . ' ' . $content );
        $best_match = null;
        $best_score = 0;

        foreach ( $departments as $department ) {
            $score = 0;
            $keywords = array_filter( array_map( 'trim', explode( ',', (string) ( $department['keywords'] ?? '' ) ) ) );
            $keywords[] = (string) $department['name'];
            $keywords[] = (string) $department['slug'];

            foreach ( $keywords as $keyword ) {
                $keyword = strtolower( $keyword );
                if ( '' !== $keyword && false !== strpos( $haystack, $keyword ) ) {
                    $score += 2;
                }
            }

            if ( ! empty( $department['is_default'] ) ) {
                $score += 1;
            }

            if ( $score > $best_score ) {
                $best_score = $score;
                $best_match = $department;
            }
        }

        if ( $best_match ) {
            return $best_match;
        }

        foreach ( $departments as $department ) {
            if ( ! empty( $department['is_default'] ) ) {
                return $department;
            }
        }

        return $departments[0] ?? null;
    }

    private function suggest_with_ai( array $departments, string $subject, string $content ): ?array {
        if ( 'yes' !== get_option( 'aosai_ticket_ai_routing', 'yes' ) ) {
            return null;
        }

        $provider = AOSAI_AI_Service::get_instance()->get_provider();
        if ( ! $provider || ! $provider->is_configured() ) {
            return null;
        }

        $choices = array();
        foreach ( $departments as $department ) {
            $choices[] = sprintf(
                '%s (%s): %s',
                $department['name'],
                $department['slug'],
                $department['description'] ?? ''
            );
        }

        $result = AOSAI_AI_Service::get_instance()->chat(
            array(
                array(
                    'role'    => 'user',
                    'content' => "Choose the best department slug for this ticket. Reply with only one slug.\n\nDepartments:\n" . implode( "\n", $choices ) . "\n\nSubject: {$subject}\nContent: {$content}",
                ),
            ),
            array(
                'action' => 'route_ticket_department',
                'model'  => get_option( 'aosai_openai_model', 'gpt-4o-mini' ),
            )
        );

        if ( is_wp_error( $result ) ) {
            return null;
        }

        $slug = sanitize_title( trim( (string) ( $result['content'] ?? '' ) ) );
        if ( '' === $slug ) {
            return null;
        }

        foreach ( $departments as $department ) {
            if ( $slug === $department['slug'] ) {
                return $department;
            }
        }

        return null;
    }
}

