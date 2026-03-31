<?php
if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

class AOSAI_Tag {
    use AOSAI_Singleton;

    private function __construct() {}

    public function get_table(): string {
        global $wpdb;
        return esc_sql( $wpdb->prefix . 'aosai_tags' );
    }

    public function get_relation_table(): string {
        global $wpdb;
        return esc_sql( $wpdb->prefix . 'aosai_tag_relations' );
    }

    public function get_all( string $type = '' ): array {
        global $wpdb;

        $table = $this->get_table();
        if ( '' === $type ) {
            return $wpdb->get_results( 'SELECT * FROM ' . $table . ' ORDER BY name ASC', ARRAY_A ) ?: array();
        }

        return $wpdb->get_results(
            $wpdb->prepare( 'SELECT * FROM ' . $table . ' WHERE type = %s ORDER BY name ASC', sanitize_key( $type ) ),
            ARRAY_A
        ) ?: array();
    }

    public function get_object_tags( string $object_type, int $object_id ): array {
        global $wpdb;

        $tags_table      = $this->get_table();
        $relations_table = $this->get_relation_table();

        return $wpdb->get_results(
            $wpdb->prepare(
                'SELECT t.*
                FROM ' . $tags_table . ' t
                INNER JOIN ' . $relations_table . ' r ON t.id = r.tag_id
                WHERE r.object_type = %s AND r.object_id = %d
                ORDER BY t.name ASC',
                sanitize_key( $object_type ),
                $object_id
            ),
            ARRAY_A
        ) ?: array();
    }

    public function sync_object_tags( string $object_type, int $object_id, array|string $tags, string $type = 'general' ): array {
        global $wpdb;

        $relations_table = $this->get_relation_table();
        $clean_names     = $this->normalize_names( $tags );
        $tag_ids         = array();

        foreach ( $clean_names as $name ) {
            $tag_ids[] = $this->ensure_tag( $name, $type );
        }

        $wpdb->delete(
            $relations_table,
            array(
                'object_type' => sanitize_key( $object_type ),
                'object_id'   => $object_id,
            ),
            array( '%s', '%d' )
        );

        foreach ( array_filter( array_map( 'absint', $tag_ids ) ) as $tag_id ) {
            $wpdb->insert(
                $relations_table,
                array(
                    'tag_id'      => $tag_id,
                    'object_type' => sanitize_key( $object_type ),
                    'object_id'   => $object_id,
                ),
                array( '%d', '%s', '%d' )
            );
        }

        return $this->get_object_tags( $object_type, $object_id );
    }

    public function ensure_tag( string $name, string $type = 'general' ): int {
        global $wpdb;

        $table = $this->get_table();
        $name  = sanitize_text_field( $name );
        if ( '' === $name ) {
            return 0;
        }

        $type = sanitize_key( $type );
        $slug = sanitize_title( $name );

        $existing = $wpdb->get_var(
            $wpdb->prepare( 'SELECT id FROM ' . $table . ' WHERE type = %s AND slug = %s', $type, $slug )
        );
        if ( $existing ) {
            return (int) $existing;
        }

        $wpdb->insert(
            $table,
            array(
                'name'       => $name,
                'slug'       => $slug,
                'color'      => $this->generate_color( $slug ),
                'type'       => $type,
                'created_by' => get_current_user_id(),
            ),
            array( '%s', '%s', '%s', '%s', '%d' )
        );

        return (int) $wpdb->insert_id;
    }

    private function normalize_names( array|string $tags ): array {
        if ( is_string( $tags ) ) {
            $tags = explode( ',', $tags );
        }

        $normalized = array();
        foreach ( (array) $tags as $tag ) {
            $tag = sanitize_text_field( (string) $tag );
            if ( '' === $tag ) {
                continue;
            }
            $normalized[ strtolower( $tag ) ] = $tag;
        }

        return array_values( $normalized );
    }

    private function generate_color( string $seed ): string {
        $palette = array( '#0f766e', '#2563eb', '#f59e0b', '#db2777', '#7c3aed', '#ea580c' );
        $index   = abs( crc32( $seed ) ) % count( $palette );
        return $palette[ $index ];
    }
}

