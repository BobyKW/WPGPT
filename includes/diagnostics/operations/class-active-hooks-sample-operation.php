<?php

namespace WPGPT\MCPBridge\Diagnostics\Operations;

use WPGPT\MCPBridge\Diagnostics\Diagnostic_Operation;
use WP_Error;

if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

class Active_Hooks_Sample_Operation implements Diagnostic_Operation {
    public function name(): string {
        return 'active_hooks_sample';
    }

    public function label(): string {
        return __( 'Active hooks sample', 'wpgpt-mcp-bridge' );
    }

    public function description(): string {
        return __( 'Devuelve una muestra de hooks activos del runtime.', 'wpgpt-mcp-bridge' );
    }

    public function input_schema(): array {
        return array(
            'type'       => 'object',
            'properties' => array(
                'limit' => array( 'type' => 'integer' ),
            ),
        );
    }

    public function execute( array $input ): array|WP_Error {
        global $wp_filter;

        $limit      = isset( $input['limit'] ) ? max( 1, min( 200, (int) $input['limit'] ) ) : 100;
        $hook_names = array_slice( array_keys( (array) $wp_filter ), 0, $limit );
        sort( $hook_names );

        return array(
            'operation' => $this->name(),
            'count'     => count( $hook_names ),
            'items'     => array_values( $hook_names ),
        );
    }
}
