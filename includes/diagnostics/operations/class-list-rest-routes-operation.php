<?php

namespace WPGPT\MCPBridge\Diagnostics\Operations;

use WPGPT\MCPBridge\Diagnostics\Diagnostic_Operation;
use WP_Error;

if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

class List_Rest_Routes_Operation implements Diagnostic_Operation {
    public function name(): string {
        return 'list_rest_routes';
    }

    public function label(): string {
        return __( 'List REST routes', 'wpgpt-mcp-bridge' );
    }

    public function description(): string {
        return __( 'Lista rutas REST registradas en el sitio.', 'wpgpt-mcp-bridge' );
    }

    public function input_schema(): array {
        return array(
            'type'       => 'object',
            'properties' => array(
                'namespace_like' => array( 'type' => 'string' ),
                'limit'          => array( 'type' => 'integer' ),
            ),
        );
    }

    public function execute( array $input ): array|WP_Error {
        global $wp_rest_server;

        if ( ! $wp_rest_server && function_exists( 'rest_get_server' ) ) {
            $wp_rest_server = rest_get_server();
        }

        $limit          = isset( $input['limit'] ) ? max( 1, min( 300, (int) $input['limit'] ) ) : 150;
        $namespace_like = isset( $input['namespace_like'] ) ? sanitize_text_field( (string) $input['namespace_like'] ) : '';

        $routes = is_object( $wp_rest_server ) && method_exists( $wp_rest_server, 'get_routes' )
            ? $wp_rest_server->get_routes()
            : array();

        $items = array();
        foreach ( array_keys( $routes ) as $route ) {
            if ( '' !== $namespace_like && false === stripos( $route, $namespace_like ) ) {
                continue;
            }

            $items[] = array( 'route' => $route );

            if ( count( $items ) >= $limit ) {
                break;
            }
        }

        return array(
            'operation' => $this->name(),
            'count'     => count( $items ),
            'items'     => $items,
        );
    }
}
