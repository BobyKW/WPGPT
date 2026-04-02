<?php

namespace WPGPT\MCPBridge\Settings;

use WP_Error;

if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

class Options_Audit_Service {
    public function query_options( array $input ): array {
        global $wpdb;

        $limit      = max( 1, min( 200, absint( $input['limit'] ?? 50 ) ) );
        $prefix     = isset( $input['option_name_prefix'] ) ? sanitize_text_field( (string) $input['option_name_prefix'] ) : '';
        $contains   = isset( $input['option_name_contains'] ) ? sanitize_text_field( (string) $input['option_name_contains'] ) : '';
        $autoload   = isset( $input['autoload'] ) ? strtolower( sanitize_text_field( (string) $input['autoload'] ) ) : '';
        $value_like = isset( $input['option_value_contains'] ) ? sanitize_text_field( (string) $input['option_value_contains'] ) : '';

        $where = array();
        $args  = array();

        if ( '' !== $prefix ) {
            $where[] = 'option_name LIKE %s';
            $args[]  = $wpdb->esc_like( $prefix ) . '%';
        }
        if ( '' !== $contains ) {
            $where[] = 'option_name LIKE %s';
            $args[]  = '%' . $wpdb->esc_like( $contains ) . '%';
        }
        if ( '' !== $value_like ) {
            $where[] = 'option_value LIKE %s';
            $args[]  = '%' . $wpdb->esc_like( $value_like ) . '%';
        }
        if ( in_array( $autoload, array( 'yes', 'no', 'on', 'off', 'auto-on', 'auto-off' ), true ) ) {
            $where[] = 'autoload = %s';
            $args[]  = $autoload;
        }

        $sql = "SELECT option_id, option_name, autoload, LENGTH(option_value) AS value_size FROM {$wpdb->options}";
        if ( ! empty( $where ) ) {
            $sql .= ' WHERE ' . implode( ' AND ', $where );
        }
        $sql .= ' ORDER BY option_name ASC';
        $sql .= $wpdb->prepare( ' LIMIT %d', $limit );

        if ( ! empty( $args ) ) {
            array_unshift( $args, $sql );
            $sql = call_user_func_array( array( $wpdb, 'prepare' ), $args );
        }

        $rows = $wpdb->get_results( $sql, ARRAY_A ); // phpcs:ignore WordPress.DB.PreparedSQL.NotPrepared

        return array(
            'count'   => count( $rows ),
            'filters' => array(
                'option_name_prefix'    => $prefix,
                'option_name_contains'  => $contains,
                'option_value_contains' => $value_like,
                'autoload'              => $autoload,
                'limit'                 => $limit,
            ),
            'items'   => array_values( array_map( array( $this, 'normalize_option_row' ), $rows ) ),
        );
    }

    public function autoload_audit( array $input ): array {
        global $wpdb;

        $limit            = max( 1, min( 200, absint( $input['limit'] ?? 50 ) ) );
        $group_by_prefix  = ! empty( $input['group_by_prefix'] );
        $prefix_length    = max( 3, min( 40, absint( $input['prefix_length'] ?? 12 ) ) );
        $autoload_values  = array( 'yes', 'on', 'auto-on' );
        $placeholders     = implode( ',', array_fill( 0, count( $autoload_values ), '%s' ) );

        $sql = $wpdb->prepare(
            "SELECT option_id, option_name, autoload, LENGTH(option_value) AS value_size FROM {$wpdb->options} WHERE autoload IN ({$placeholders}) ORDER BY value_size DESC LIMIT %d",
            array_merge( $autoload_values, array( $limit ) )
        );

        $rows       = $wpdb->get_results( $sql, ARRAY_A ); // phpcs:ignore WordPress.DB.PreparedSQL.NotPrepared
        $total_sql = $wpdb->prepare(
            "SELECT COALESCE(SUM(LENGTH(option_value)),0) FROM {$wpdb->options} WHERE autoload IN ({$placeholders})",
            $autoload_values
        );
        $total_size = (int) $wpdb->get_var( $total_sql ); // phpcs:ignore WordPress.DB.PreparedSQL.NotPrepared

        $items = array_values( array_map( array( $this, 'normalize_option_row' ), $rows ) );
        $result = array(
            'count'             => count( $items ),
            'autoload_total_bytes' => $total_size,
            'items'             => $items,
        );

        if ( $group_by_prefix ) {
            $groups = array();
            foreach ( $items as $item ) {
                $bucket = $this->option_name_prefix_bucket( (string) $item['option_name'], $prefix_length );
                if ( ! isset( $groups[ $bucket ] ) ) {
                    $groups[ $bucket ] = array( 'prefix' => $bucket, 'count' => 0, 'total_bytes' => 0 );
                }
                $groups[ $bucket ]['count']++;
                $groups[ $bucket ]['total_bytes'] += (int) $item['value_size'];
            }
            usort(
                $groups,
                static fn( array $a, array $b ): int => $b['total_bytes'] <=> $a['total_bytes']
            );
            $result['groups'] = array_values( $groups );
        }

        return $result;
    }

    public function transients_audit( array $input ): array {
        global $wpdb;

        $limit    = max( 1, min( 200, absint( $input['limit'] ?? 100 ) ) );
        $search   = isset( $input['search'] ) ? sanitize_text_field( (string) $input['search'] ) : '';
        $expired  = ! empty( $input['expired_only'] );
        $now      = time();
        $where    = array( "option_name LIKE '_transient_%'", "option_name NOT LIKE '_transient_timeout_%'" );
        $args     = array();

        if ( '' !== $search ) {
            $where[] = 'option_name LIKE %s';
            $args[]  = '%_transient_' . $wpdb->esc_like( $search ) . '%';
        }

        $sql = "SELECT option_name, autoload, LENGTH(option_value) AS value_size FROM {$wpdb->options} WHERE " . implode( ' AND ', $where ) . ' ORDER BY option_name ASC';
        $sql .= $wpdb->prepare( ' LIMIT %d', $limit );
        if ( ! empty( $args ) ) {
            array_unshift( $args, $sql );
            $sql = call_user_func_array( array( $wpdb, 'prepare' ), $args );
        }

        $rows  = $wpdb->get_results( $sql, ARRAY_A ); // phpcs:ignore WordPress.DB.PreparedSQL.NotPrepared
        $items = array();
        foreach ( $rows as $row ) {
            $transient_name = preg_replace( '/^_transient_/', '', (string) $row['option_name'] );
            $timeout_option = '_transient_timeout_' . $transient_name;
            $timeout_raw    = get_option( $timeout_option, null );
            $timeout_ts     = is_numeric( $timeout_raw ) ? (int) $timeout_raw : null;
            $is_expired     = null !== $timeout_ts ? $timeout_ts < $now : false;
            if ( $expired && ! $is_expired ) {
                continue;
            }
            $items[] = array(
                'transient_name'   => $transient_name,
                'option_name'      => (string) $row['option_name'],
                'timeout_option'   => $timeout_option,
                'timeout_ts'       => $timeout_ts,
                'expired'          => $is_expired,
                'autoload'         => (string) $row['autoload'],
                'value_size'       => (int) $row['value_size'],
            );
        }

        return array(
            'count'        => count( $items ),
            'expired_only' => $expired,
            'search'       => $search,
            'items'        => array_values( $items ),
        );
    }

    private function normalize_option_row( array $row ): array {
        return array(
            'option_id'   => isset( $row['option_id'] ) ? (int) $row['option_id'] : 0,
            'option_name' => (string) ( $row['option_name'] ?? '' ),
            'autoload'    => (string) ( $row['autoload'] ?? '' ),
            'value_size'  => isset( $row['value_size'] ) ? (int) $row['value_size'] : 0,
        );
    }

    private function option_name_prefix_bucket( string $option_name, int $prefix_length ): string {
        if ( preg_match( '/^([^_]+_[^_]+)_/', $option_name, $matches ) ) {
            return $matches[1];
        }
        return substr( $option_name, 0, $prefix_length );
    }
}
