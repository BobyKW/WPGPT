<?php

namespace WPGPT\MCPBridge\Maintenance;

use WP_Error;

if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

class Maintenance_Service {
    private const OPTION_ENABLED = 'wpgpt_mcp_bridge_maintenance_enabled';
    private const OPTION_MESSAGE = 'wpgpt_mcp_bridge_maintenance_message';

    public static function init(): void {
        add_action( 'template_redirect', array( __CLASS__, 'maybe_block_frontend' ), 0 );
    }

    public static function maybe_block_frontend(): void {
        if ( is_admin() || wp_doing_ajax() || wp_is_json_request() || ! self::is_enabled() ) {
            return;
        }
        if ( current_user_can( 'manage_options' ) ) {
            return;
        }
        wp_die( esc_html( self::get_message() ), esc_html__( 'Maintenance', 'wpgpt-mcp-bridge' ), array( 'response' => 503 ) );
    }

    public function cache_manage( array $input ): array {
        $action = sanitize_key( (string) ( $input['action'] ?? 'inspect' ) );
        $result = array( 'object_cache' => wp_using_ext_object_cache(), 'wp_cache_supports_flush' => function_exists( 'wp_cache_flush' ) );
        if ( 'flush' === $action && function_exists( 'wp_cache_flush' ) ) {
            wp_cache_flush();
            $result['flushed'] = true;
        }
        return array( 'success' => true, 'action' => $action, 'result' => $result );
    }

    public function transients_manage( array $input ): array {
        global $wpdb;
        $action = sanitize_key( (string) ( $input['action'] ?? 'list' ) );
        $search = sanitize_text_field( (string) ( $input['search'] ?? '' ) );
        $limit  = min( 200, max( 1, absint( $input['limit'] ?? 50 ) ) );
        $like   = '_transient_%';
        if ( 'search' === $action && '' !== $search ) {
            $like = '_transient_%' . $wpdb->esc_like( $search ) . '%';
        }
        if ( in_array( $action, array( 'list', 'search' ), true ) ) {
            $rows = $wpdb->get_results( $wpdb->prepare( "SELECT option_name FROM {$wpdb->options} WHERE option_name LIKE %s ORDER BY option_name ASC LIMIT %d", $like, $limit ), ARRAY_A );
            return array( 'success' => true, 'action' => $action, 'items' => array_map( fn($r)=>$r['option_name'], $rows ) );
        }
        if ( 'delete_expired' === $action && function_exists( 'delete_expired_transients' ) ) {
            delete_expired_transients( true );
            return array( 'success' => true, 'action' => $action, 'deleted' => 'expired' );
        }
        if ( 'delete' === $action ) {
            $keys = array_map( 'sanitize_text_field', (array) ( $input['keys'] ?? array() ) );
            foreach ( $keys as $key ) {
                $normalized = preg_replace( '/^_transient_/', '', $key );
                delete_transient( $normalized );
            }
            return array( 'success' => true, 'action' => $action, 'deleted_keys' => $keys );
        }
        return new WP_Error( 'wpgpt_transients_action_invalid', __( 'Acción de transients no válida.', 'wpgpt-mcp-bridge' ), array( 'status' => 400 ) );
    }

    public function media_regenerate( array $input ): array {
        if ( ! function_exists( 'wp_generate_attachment_metadata' ) || ! function_exists( 'wp_update_attachment_metadata' ) ) {
            return array( 'success' => false, 'message' => 'WordPress media functions unavailable.' );
        }
        $ids = array_values( array_filter( array_map( 'absint', (array) ( $input['attachment_ids'] ?? array() ) ) ) );
        if ( empty( $ids ) ) {
            $ids = get_posts( array( 'post_type' => 'attachment', 'post_mime_type' => 'image', 'fields' => 'ids', 'numberposts' => min( 50, max( 1, absint( $input['limit'] ?? 20 ) ) ) ) );
        }
        $results = array();
        foreach ( $ids as $id ) {
            $file = get_attached_file( $id );
            if ( ! $file || ! file_exists( $file ) ) {
                $results[] = array( 'attachment_id' => $id, 'success' => false );
                continue;
            }
            $meta = wp_generate_attachment_metadata( $id, $file );
            wp_update_attachment_metadata( $id, $meta );
            $results[] = array( 'attachment_id' => $id, 'success' => true );
        }
        return array( 'success' => true, 'results' => $results );
    }

    public function search_replace( array $input ) {
        global $wpdb;
        $search = (string) ( $input['search'] ?? '' );
        if ( '' === $search ) {
            return new WP_Error( 'wpgpt_search_replace_invalid', __( 'search es obligatorio.', 'wpgpt-mcp-bridge' ), array( 'status' => 400 ) );
        }
        $replace = (string) ( $input['replace'] ?? '' );
        $tables  = array_map( 'sanitize_text_field', (array) ( $input['tables'] ?? array( $wpdb->posts, $wpdb->postmeta, $wpdb->options ) ) );
        $dry_run = ! empty( $input['dry_run'] );
        $summary = array();
        foreach ( $tables as $table ) {
            if ( ! preg_match( '/^[A-Za-z0-9_]+$/', $table ) ) { continue; }
            $columns = $wpdb->get_col( "SHOW COLUMNS FROM {$table}", 0 );
            $text_cols = array();
            foreach ( (array) $columns as $col ) { $text_cols[] = $col; }
            $matches = 0;
            foreach ( $text_cols as $col ) {
                $count = (int) $wpdb->get_var( $wpdb->prepare( "SELECT COUNT(*) FROM {$table} WHERE {$col} LIKE %s", '%' . $wpdb->esc_like( $search ) . '%' ) );
                $matches += $count;
                if ( ! $dry_run && $count > 0 ) {
                    $wpdb->query( $wpdb->prepare( "UPDATE {$table} SET {$col} = REPLACE({$col}, %s, %s) WHERE {$col} LIKE %s", $search, $replace, '%' . $wpdb->esc_like( $search ) . '%' ) );
                }
            }
            $summary[] = array( 'table' => $table, 'matches' => $matches );
        }
        return array( 'success' => true, 'dry_run' => $dry_run, 'summary' => $summary );
    }

    public function maintenance_mode_set( array $input ): array {
        $enabled = ! empty( $input['enabled'] );
        update_option( self::OPTION_ENABLED, $enabled ? '1' : '0', false );
        if ( isset( $input['message'] ) ) {
            update_option( self::OPTION_MESSAGE, sanitize_text_field( (string) $input['message'] ), false );
        }
        return array( 'success' => true, 'enabled' => self::is_enabled(), 'message' => self::get_message() );
    }

    public static function is_enabled(): bool { return '1' === (string) get_option( self::OPTION_ENABLED, '0' ); }
    public static function get_message(): string { return (string) get_option( self::OPTION_MESSAGE, __( 'Site under maintenance. Please come back soon.', 'wpgpt-mcp-bridge' ) ); }
}
