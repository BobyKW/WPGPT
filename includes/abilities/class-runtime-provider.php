<?php

namespace WPGPT\MCPBridge;

use WPGPT\MCPBridge\Diagnostics\Diagnostic_Registry;
use WP_Error;

if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

class Runtime_Provider extends Base_Ability_Provider {
    public function get_abilities(): array {
        return array(
            'wpgpt/rest-routes-list' => array(
                'label'            => __( 'REST routes list', 'wpgpt-mcp-bridge' ),
                'description'      => __( 'Lista rutas REST registradas en el sitio.', 'wpgpt-mcp-bridge' ),
                'input_schema'     => $this->routes_input_schema(),
                'execute_callback' => array( $this, 'rest_routes_list' ),
                'output_schema'    => $this->object_schema(),
            ),
            'wpgpt/shortcodes-list' => array(
                'label'            => __( 'Shortcodes list', 'wpgpt-mcp-bridge' ),
                'description'      => __( 'Lista shortcodes registrados en el runtime.', 'wpgpt-mcp-bridge' ),
                'execute_callback' => array( $this, 'shortcodes_list' ),
                'output_schema'    => $this->object_schema(),
            ),
            'wpgpt/hooks-list'      => array(
                'label'            => __( 'Hooks list', 'wpgpt-mcp-bridge' ),
                'description'      => __( 'Lista hooks activos del runtime con filtro opcional.', 'wpgpt-mcp-bridge' ),
                'input_schema'     => $this->hooks_input_schema(),
                'execute_callback' => array( $this, 'hooks_list' ),
                'output_schema'    => $this->object_schema(),
            ),
            'wpgpt/cron-events-list' => array(
                'label'            => __( 'Cron events list', 'wpgpt-mcp-bridge' ),
                'description'      => __( 'Lista eventos cron programados en el sitio.', 'wpgpt-mcp-bridge' ),
                'input_schema'     => $this->cron_input_schema(),
                'execute_callback' => array( $this, 'cron_events_list' ),
                'output_schema'    => $this->object_schema(),
            ),
        );
    }

    public function rest_routes_list( array $input ): array|WP_Error {
        return Diagnostic_Registry::execute( 'list_rest_routes', $input );
    }

    public function shortcodes_list(): array|WP_Error {
        return Diagnostic_Registry::execute( 'list_shortcodes' );
    }

    public function hooks_list( array $input ): array|WP_Error {
        return Diagnostic_Registry::execute( 'list_hooks', $input );
    }

    public function cron_events_list( array $input ): array|WP_Error {
        return Diagnostic_Registry::execute( 'list_cron_events', $input );
    }

    private function routes_input_schema(): array {
        return array(
            'type'       => 'object',
            'properties' => array(
                'namespace_like' => array( 'type' => 'string' ),
                'limit'          => array( 'type' => 'integer' ),
            ),
        );
    }

    private function hooks_input_schema(): array {
        return array(
            'type'       => 'object',
            'properties' => array(
                'hook_like' => array( 'type' => 'string' ),
                'limit'     => array( 'type' => 'integer' ),
            ),
        );
    }

    private function cron_input_schema(): array {
        return array(
            'type'       => 'object',
            'properties' => array(
                'hook_like' => array( 'type' => 'string' ),
                'limit'     => array( 'type' => 'integer' ),
            ),
        );
    }
}
