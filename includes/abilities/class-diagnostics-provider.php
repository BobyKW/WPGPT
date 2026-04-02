<?php

namespace WPGPT\MCPBridge;

use WPGPT\MCPBridge\Diagnostics\Diagnostic_Registry;
use WP_Error;

if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

class Diagnostics_Provider extends Base_Ability_Provider {
    public function get_abilities(): array {
        return array(
            'wpgpt/php-diagnostic' => array(
                'label'            => __( 'PHP diagnostic', 'wpgpt-mcp-bridge' ),
                'description'      => __( 'Ejecuta diagnósticos PHP/WordPress predefinidos desde un registro modular.', 'wpgpt-mcp-bridge' ),
                'input_schema'     => $this->php_diagnostic_input_schema(),
                'execute_callback' => array( $this, 'php_diagnostic' ),
                'output_schema'    => $this->object_schema(),
            ),
            'wpgpt/diagnostics-list' => array(
                'label'            => __( 'Diagnostics list', 'wpgpt-mcp-bridge' ),
                'description'      => __( 'Lista operaciones de diagnóstico disponibles.', 'wpgpt-mcp-bridge' ),
                'execute_callback' => array( $this, 'diagnostics_list' ),
                'output_schema'    => $this->object_schema(),
            ),
        );
    }

    public function php_diagnostic( array $input ): array|WP_Error {
        $operation = isset( $input['operation'] ) ? sanitize_key( (string) $input['operation'] ) : '';
        $payload   = $input;
        unset( $payload['operation'] );

        return Diagnostic_Registry::execute( $operation, $payload );
    }

    public function diagnostics_list(): array {
        $items = Diagnostic_Registry::info();

        return array(
            'count' => count( $items ),
            'items' => $items,
        );
    }

    private function php_diagnostic_input_schema(): array {
        return array(
            'type'       => 'object',
            'properties' => array(
                'operation'      => array( 'type' => 'string' ),
                'namespace_like' => array( 'type' => 'string' ),
                'hook_like'      => array( 'type' => 'string' ),
                'limit'          => array( 'type' => 'integer' ),
            ),
            'required'   => array( 'operation' ),
        );
    }
}
