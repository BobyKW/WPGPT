<?php

namespace WPGPT\MCPBridge\Diagnostics\Operations;

use WPGPT\MCPBridge\Diagnostics\Diagnostic_Operation;
use WPGPT\MCPBridge\Security;
use WP_Error;

if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

class Plugin_Status_Operation implements Diagnostic_Operation {
    public function name(): string {
        return 'plugin_status';
    }

    public function label(): string {
        return __( 'Plugin status', 'wpgpt-mcp-bridge' );
    }

    public function description(): string {
        return __( 'Comprueba el estado del bridge y sus dependencias principales.', 'wpgpt-mcp-bridge' );
    }

    public function input_schema(): array {
        return array(
            'type'       => 'object',
            'properties' => array(),
        );
    }

    public function execute( array $input ): array|WP_Error {
        return array(
            'operation'     => $this->name(),
            'mcp_adapter'   => class_exists( '\\WP\\MCP\\Core\\McpAdapter' ),
            'abilities_api' => function_exists( 'wp_register_ability' ),
            'read_only'     => Security::get_read_only(),
        );
    }
}
