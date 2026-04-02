<?php

namespace WPGPT\MCPBridge\Database;

if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

class Safe_Query_Builder {
    public function sanitize_requested_columns( array $columns, array $valid_columns, array $defaults ): array {
        $columns = array_values( array_map( 'sanitize_key', $columns ) );
        $columns = array_values( array_intersect( $columns, $valid_columns ) );

        return empty( $columns ) ? $defaults : $columns;
    }

    public function sanitize_column( string $column, array $valid_columns ): string {
        $column = sanitize_key( $column );
        return in_array( $column, $valid_columns, true ) ? $column : '';
    }

    public function build_where_clause( array $where, array $valid_columns ): array {
        $where_sql  = array();
        $where_args = array();

        foreach ( $where as $key => $value ) {
            $key = sanitize_key( (string) $key );
            if ( ! in_array( $key, $valid_columns, true ) ) {
                continue;
            }

            $where_sql[]  = "`{$key}` = %s";
            $where_args[] = is_scalar( $value ) ? (string) $value : wp_json_encode( $value );
        }

        return array(
            'sql_parts' => $where_sql,
            'args'      => $where_args,
        );
    }

    public function build_like_clause( string $column, string $term ): array {
        global $wpdb;

        return array(
            'sql' => "`{$column}` LIKE %s",
            'arg' => '%' . $wpdb->esc_like( $term ) . '%',
        );
    }

    public function quote_columns( array $columns ): string {
        return implode(
            ', ',
            array_map(
                static function ( string $column ): string {
                    return "`{$column}`";
                },
                $columns
            )
        );
    }
}
