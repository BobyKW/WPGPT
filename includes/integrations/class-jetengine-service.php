<?php

namespace WPGPT\MCPBridge\Integrations;

if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

class JetEngine_Service {
    public function status(): array {
        return array(
            'active' => defined( 'JET_ENGINE_VERSION' ) || class_exists( '\\Jet_Engine' ),
            'version' => defined( 'JET_ENGINE_VERSION' ) ? JET_ENGINE_VERSION : '',
            'rest_namespace_present' => rest_get_server() ? rest_get_server()->get_route_options( '/jet-engine/v1' ) !== null || rest_get_server()->get_route_options( '/jet-engine/v2' ) !== null : false,
        );
    }

    public function options_scan( string $search = 'jet_engine', int $limit = 50 ): array {
        global $wpdb;
        $limit = max( 1, min( 100, $limit ) );
        $search = trim( $search );
        $like = '%' . $wpdb->esc_like( $search ) . '%';
        $sql = $wpdb->prepare( "SELECT option_name, LEFT(option_value, 500) AS option_value_sample FROM {$wpdb->options} WHERE option_name LIKE %s ORDER BY option_name ASC LIMIT %d", $like, $limit );
        $rows = $wpdb->get_results( $sql, ARRAY_A );
        return array( 'search' => $search, 'count' => count( $rows ), 'items' => array_values( $rows ) );
    }
}
