<?php

namespace WPGPT\MCPBridge\Database;

use WP_Error;

if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

class Database_Inspector_Service {
    private Database_Catalog $catalog;

    private Safe_Query_Builder $builder;

    public function __construct( ?Database_Catalog $catalog = null, ?Safe_Query_Builder $builder = null ) {
        $this->catalog = $catalog ?: new Database_Catalog();
        $this->builder = $builder ?: new Safe_Query_Builder();
    }

    public function list_tables(): array {
        global $wpdb;

        $items = array();
        foreach ( $this->catalog->allowed_tables() as $short => $full ) {
            $count   = (int) $wpdb->get_var( "SELECT COUNT(*) FROM {$full}" ); // phpcs:ignore WordPress.DB.PreparedSQL.NotPrepared
            $items[] = array(
                'key'        => $short,
                'table_name' => $full,
                'rows'       => $count,
            );
        }

        return array(
            'count' => count( $items ),
            'items' => $items,
        );
    }

    public function describe_table( string $table_key ): array|WP_Error {
        global $wpdb;

        $table_name = $this->catalog->table_name( $table_key );
        if ( '' === $table_name ) {
            return new WP_Error( 'wpgpt_table_not_allowed', __( 'Tabla no permitida.', 'wpgpt-mcp-bridge' ), array( 'status' => 403 ) );
        }

        $rows = $wpdb->get_results( "DESCRIBE {$table_name}", ARRAY_A ); // phpcs:ignore WordPress.DB.PreparedSQL.NotPrepared

        return array(
            'table'   => $table_name,
            'columns' => array_values( $rows ),
        );
    }

    public function select( string $table_key, array $columns = array(), array $where = array(), int $limit = 10 ): array|WP_Error {
        global $wpdb;

        $table_name = $this->catalog->table_name( $table_key );
        if ( '' === $table_name ) {
            return new WP_Error( 'wpgpt_table_not_allowed', __( 'Tabla no permitida.', 'wpgpt-mcp-bridge' ), array( 'status' => 403 ) );
        }

        $valid_columns = $this->catalog->allowed_columns_for_table( $table_key );
        $columns       = $this->builder->sanitize_requested_columns( $columns, $valid_columns, $this->catalog->default_columns_for_table( $table_key ) );
        $limit         = max( 1, min( 50, $limit ) );

        $where_data = $this->builder->build_where_clause( $where, $valid_columns );
        $sql        = 'SELECT ' . $this->builder->quote_columns( $columns ) . " FROM {$table_name}";

        if ( ! empty( $where_data['sql_parts'] ) ) {
            $sql .= ' WHERE ' . implode( ' AND ', $where_data['sql_parts'] );
        }

        $sql .= $wpdb->prepare( ' LIMIT %d', $limit );

        if ( ! empty( $where_data['args'] ) ) {
            $prepared_args = $where_data['args'];
            array_unshift( $prepared_args, $sql );
            $sql = call_user_func_array( array( $wpdb, 'prepare' ), $prepared_args );
        }

        $rows = $wpdb->get_results( $sql, ARRAY_A );

        return array(
            'table'   => $table_name,
            'columns' => $columns,
            'count'   => count( $rows ),
            'items'   => array_values( $rows ),
        );
    }

    public function count( string $table_key, array $where = array() ): array|WP_Error {
        global $wpdb;

        $table_name = $this->catalog->table_name( $table_key );
        if ( '' === $table_name ) {
            return new WP_Error( 'wpgpt_table_not_allowed', __( 'Tabla no permitida.', 'wpgpt-mcp-bridge' ), array( 'status' => 403 ) );
        }

        $valid_columns = $this->catalog->allowed_columns_for_table( $table_key );
        $where_data    = $this->builder->build_where_clause( $where, $valid_columns );

        $sql = "SELECT COUNT(*) FROM {$table_name}";
        if ( ! empty( $where_data['sql_parts'] ) ) {
            $sql .= ' WHERE ' . implode( ' AND ', $where_data['sql_parts'] );
        }

        if ( ! empty( $where_data['args'] ) ) {
            $prepared_args = $where_data['args'];
            array_unshift( $prepared_args, $sql );
            $sql = call_user_func_array( array( $wpdb, 'prepare' ), $prepared_args );
        }

        $total = (int) $wpdb->get_var( $sql ); // phpcs:ignore WordPress.DB.PreparedSQL.NotPrepared

        return array(
            'table' => $table_name,
            'count' => $total,
            'where' => $where,
        );
    }

    public function search( string $table_key, string $column, string $term, array $columns = array(), int $limit = 20 ): array|WP_Error {
        global $wpdb;

        $table_name = $this->catalog->table_name( $table_key );
        if ( '' === $table_name ) {
            return new WP_Error( 'wpgpt_table_not_allowed', __( 'Tabla no permitida.', 'wpgpt-mcp-bridge' ), array( 'status' => 403 ) );
        }

        $valid_columns = $this->catalog->allowed_columns_for_table( $table_key );
        $search_column = $this->builder->sanitize_column( $column, $valid_columns );
        if ( '' === $search_column ) {
            return new WP_Error( 'wpgpt_column_not_allowed', __( 'Columna no permitida.', 'wpgpt-mcp-bridge' ), array( 'status' => 403 ) );
        }

        if ( '' === $term ) {
            return new WP_Error( 'wpgpt_empty_search_term', __( 'Debes indicar un término de búsqueda.', 'wpgpt-mcp-bridge' ), array( 'status' => 400 ) );
        }

        $columns   = $this->builder->sanitize_requested_columns( $columns, $valid_columns, $this->catalog->default_columns_for_table( $table_key ) );
        $limit     = max( 1, min( 50, $limit ) );
        $like_data = $this->builder->build_like_clause( $search_column, $term );

        $sql  = 'SELECT ' . $this->builder->quote_columns( $columns ) . " FROM {$table_name} WHERE {$like_data['sql']}";
        $sql .= $wpdb->prepare( ' LIMIT %d', $limit );
        $sql  = $wpdb->prepare( $sql, $like_data['arg'] );

        $rows = $wpdb->get_results( $sql, ARRAY_A );

        return array(
            'table'         => $table_name,
            'search_column' => $search_column,
            'term'          => $term,
            'columns'       => $columns,
            'count'         => count( $rows ),
            'items'         => array_values( $rows ),
        );
    }

    public function distinct( string $table_key, string $column, array $where = array(), int $limit = 50 ): array|WP_Error {
        global $wpdb;

        $table_name = $this->catalog->table_name( $table_key );
        if ( '' === $table_name ) {
            return new WP_Error( 'wpgpt_table_not_allowed', __( 'Tabla no permitida.', 'wpgpt-mcp-bridge' ), array( 'status' => 403 ) );
        }

        $valid_columns = $this->catalog->allowed_columns_for_table( $table_key );
        $column        = $this->builder->sanitize_column( $column, $valid_columns );
        if ( '' === $column ) {
            return new WP_Error( 'wpgpt_column_not_allowed', __( 'Columna no permitida.', 'wpgpt-mcp-bridge' ), array( 'status' => 403 ) );
        }

        $limit      = max( 1, min( 100, $limit ) );
        $where_data = $this->builder->build_where_clause( $where, $valid_columns );
        $sql        = "SELECT DISTINCT `{$column}` AS value FROM {$table_name}";

        if ( ! empty( $where_data['sql_parts'] ) ) {
            $sql .= ' WHERE ' . implode( ' AND ', $where_data['sql_parts'] );
        }

        $sql .= $wpdb->prepare( ' LIMIT %d', $limit );

        if ( ! empty( $where_data['args'] ) ) {
            $prepared_args = $where_data['args'];
            array_unshift( $prepared_args, $sql );
            $sql = call_user_func_array( array( $wpdb, 'prepare' ), $prepared_args );
        }

        $rows  = $wpdb->get_results( $sql, ARRAY_A );
        $items = array();
        foreach ( $rows as $row ) {
            $items[] = array( 'value' => $row['value'] ?? null );
        }

        return array(
            'table'  => $table_name,
            'column' => $column,
            'count'  => count( $items ),
            'items'  => $items,
        );
    }
}
