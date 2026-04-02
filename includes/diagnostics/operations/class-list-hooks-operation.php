<?php

namespace WPGPT\MCPBridge\Diagnostics\Operations;

use WPGPT\MCPBridge\Diagnostics\Diagnostic_Operation;
use WP_Error;

if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

class List_Hooks_Operation implements Diagnostic_Operation {
    public function name(): string {
        return 'list_hooks';
    }

    public function label(): string {
        return __( 'List hooks', 'wpgpt-mcp-bridge' );
    }

    public function description(): string {
        return __( 'Lista hooks activos del runtime con filtro opcional.', 'wpgpt-mcp-bridge' );
    }

    public function input_schema(): array {
        return array(
            'type'       => 'object',
            'properties' => array(
                'hook_like' => array( 'type' => 'string' ),
                'limit'     => array( 'type' => 'integer' ),
            ),
        );
    }

    public function execute( array $input ): array|WP_Error {
        global $wp_filter;

        $limit     = isset( $input['limit'] ) ? max( 1, min( 500, (int) $input['limit'] ) ) : 200;
        $hook_like = isset( $input['hook_like'] ) ? sanitize_text_field( (string) $input['hook_like'] ) : '';
        $items     = array();

        foreach ( array_keys( (array) $wp_filter ) as $hook_name ) {
            if ( '' !== $hook_like && false === stripos( (string) $hook_name, $hook_like ) ) {
                continue;
            }

            $items[] = array( 'hook' => (string) $hook_name );

            if ( count( $items ) >= $limit ) {
                break;
            }
        }

        usort(
            $items,
            static function ( array $a, array $b ): int {
                return strcmp( (string) $a['hook'], (string) $b['hook'] );
            }
        );

        return array(
            'operation' => $this->name(),
            'count'     => count( $items ),
            'items'     => array_values( $items ),
        );
    }
}
