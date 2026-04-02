<?php

namespace WPGPT\MCPBridge\Database;

if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

class Database_Audit_Service {
    public function orphan_postmeta( array $input ): array {
        global $wpdb;

        $limit = max( 1, min( 200, absint( $input['limit'] ?? 100 ) ) );
        $sql   = $wpdb->prepare(
            "SELECT pm.meta_id, pm.post_id, pm.meta_key
             FROM {$wpdb->postmeta} pm
             LEFT JOIN {$wpdb->posts} p ON p.ID = pm.post_id
             WHERE p.ID IS NULL
             ORDER BY pm.meta_id ASC
             LIMIT %d",
            $limit
        );

        $items = $wpdb->get_results( $sql, ARRAY_A ); // phpcs:ignore WordPress.DB.PreparedSQL.NotPrepared

        return array(
            'count' => count( $items ),
            'items' => array_values( array_map( static function ( array $row ): array {
                return array(
                    'meta_id'  => (int) $row['meta_id'],
                    'post_id'  => (int) $row['post_id'],
                    'meta_key' => (string) $row['meta_key'],
                );
            }, $items ) ),
        );
    }

    public function orphan_usermeta( array $input ): array {
        global $wpdb;

        $limit = max( 1, min( 200, absint( $input['limit'] ?? 100 ) ) );
        $sql   = $wpdb->prepare(
            "SELECT um.umeta_id, um.user_id, um.meta_key
             FROM {$wpdb->usermeta} um
             LEFT JOIN {$wpdb->users} u ON u.ID = um.user_id
             WHERE u.ID IS NULL
             ORDER BY um.umeta_id ASC
             LIMIT %d",
            $limit
        );

        $items = $wpdb->get_results( $sql, ARRAY_A ); // phpcs:ignore WordPress.DB.PreparedSQL.NotPrepared

        return array(
            'count' => count( $items ),
            'items' => array_values( array_map( static function ( array $row ): array {
                return array(
                    'umeta_id' => (int) $row['umeta_id'],
                    'user_id'  => (int) $row['user_id'],
                    'meta_key' => (string) $row['meta_key'],
                );
            }, $items ) ),
        );
    }

    public function orphan_term_relationships( array $input ): array {
        global $wpdb;

        $limit = max( 1, min( 200, absint( $input['limit'] ?? 100 ) ) );
        $sql   = $wpdb->prepare(
            "SELECT tr.object_id, tr.term_taxonomy_id, tt.taxonomy
             FROM {$wpdb->term_relationships} tr
             LEFT JOIN {$wpdb->posts} p ON p.ID = tr.object_id
             LEFT JOIN {$wpdb->term_taxonomy} tt ON tt.term_taxonomy_id = tr.term_taxonomy_id
             WHERE p.ID IS NULL OR tt.term_taxonomy_id IS NULL
             ORDER BY tr.object_id ASC, tr.term_taxonomy_id ASC
             LIMIT %d",
            $limit
        );

        $items = $wpdb->get_results( $sql, ARRAY_A ); // phpcs:ignore WordPress.DB.PreparedSQL.NotPrepared

        return array(
            'count' => count( $items ),
            'items' => array_values( array_map( static function ( array $row ): array {
                return array(
                    'object_id'        => (int) $row['object_id'],
                    'term_taxonomy_id' => (int) $row['term_taxonomy_id'],
                    'taxonomy'         => isset( $row['taxonomy'] ) ? (string) $row['taxonomy'] : '',
                );
            }, $items ) ),
        );
    }

    public function unused_terms( array $input ): array {
        global $wpdb;

        $limit    = max( 1, min( 200, absint( $input['limit'] ?? 100 ) ) );
        $taxonomy = isset( $input['taxonomy'] ) ? sanitize_key( (string) $input['taxonomy'] ) : '';
        $where    = array( 'tt.count = 0' );
        $args     = array();

        if ( '' !== $taxonomy ) {
            $where[] = 'tt.taxonomy = %s';
            $args[]  = $taxonomy;
        }

        $sql = "SELECT t.term_id, t.name, t.slug, tt.term_taxonomy_id, tt.taxonomy, tt.count
                FROM {$wpdb->terms} t
                INNER JOIN {$wpdb->term_taxonomy} tt ON tt.term_id = t.term_id
                WHERE " . implode( ' AND ', $where ) . ' ORDER BY tt.taxonomy ASC, t.name ASC LIMIT %d';

        $args[] = $limit;
        $sql    = $wpdb->prepare( $sql, $args );

        $items = $wpdb->get_results( $sql, ARRAY_A ); // phpcs:ignore WordPress.DB.PreparedSQL.NotPrepared

        return array(
            'count'    => count( $items ),
            'taxonomy' => $taxonomy,
            'items'    => array_values( array_map( static function ( array $row ): array {
                return array(
                    'term_id'          => (int) $row['term_id'],
                    'term_taxonomy_id' => (int) $row['term_taxonomy_id'],
                    'name'             => (string) $row['name'],
                    'slug'             => (string) $row['slug'],
                    'taxonomy'         => (string) $row['taxonomy'],
                    'count'            => (int) $row['count'],
                );
            }, $items ) ),
        );
    }
}
