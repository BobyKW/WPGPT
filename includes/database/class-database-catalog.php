<?php

namespace WPGPT\MCPBridge\Database;

use WPGPT\MCPBridge\Security;

if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

class Database_Catalog {
    public function supported_tables(): array {
        global $wpdb;

        return array(
            'posts'              => $wpdb->posts,
            'postmeta'           => $wpdb->postmeta,
            'options'            => $wpdb->options,
            'users'              => $wpdb->users,
            'usermeta'           => $wpdb->usermeta,
            'terms'              => $wpdb->terms,
            'term_taxonomy'      => $wpdb->term_taxonomy,
            'term_relationships' => $wpdb->term_relationships,
        );
    }

    public function allowed_tables(): array {
        $supported = $this->supported_tables();
        $allowed   = array_flip( Security::get_allowed_tables( array_keys( $supported ) ) );

        return array_filter(
            $supported,
            static function ( string $full_name, string $key ) use ( $allowed ): bool {
                return isset( $allowed[ $key ] );
            },
            ARRAY_FILTER_USE_BOTH
        );
    }

    public function table_name( string $table_key ): string {
        $tables = $this->allowed_tables();
        return $tables[ $table_key ] ?? '';
    }

    public function allowed_columns_for_table( string $table ): array {
        $map = array(
            'posts'              => array( 'ID', 'post_author', 'post_date', 'post_date_gmt', 'post_title', 'post_status', 'post_name', 'post_type' ),
            'postmeta'           => array( 'meta_id', 'post_id', 'meta_key', 'meta_value' ),
            'options'            => array( 'option_id', 'option_name', 'option_value', 'autoload' ),
            'users'              => array( 'ID', 'user_login', 'user_email', 'display_name', 'user_registered' ),
            'usermeta'           => array( 'umeta_id', 'user_id', 'meta_key', 'meta_value' ),
            'terms'              => array( 'term_id', 'name', 'slug', 'term_group' ),
            'term_taxonomy'      => array( 'term_taxonomy_id', 'term_id', 'taxonomy', 'description', 'parent', 'count' ),
            'term_relationships' => array( 'object_id', 'term_taxonomy_id', 'term_order' ),
        );

        return $map[ $table ] ?? array();
    }

    public function default_columns_for_table( string $table ): array {
        $columns = $this->allowed_columns_for_table( $table );
        return array_slice( $columns, 0, min( 5, count( $columns ) ) );
    }
}
