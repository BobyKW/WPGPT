<?php

namespace WPGPT\MCPBridge;

use WPGPT\MCPBridge\Plugins\Plugin_Manager_Service;
use WP_Error;

if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

class Plugin_Management_Provider extends Base_Ability_Provider {
    private ?Plugin_Manager_Service $service = null;

    public function get_abilities(): array {
        return array(
            'wpgpt/plugins-query' => array(
                'label' => __( 'Plugins query', 'wpgpt-mcp-bridge' ),
                'description' => __( 'Lista, filtra y resume plugins instalados con prioridad a información local del sitio.', 'wpgpt-mcp-bridge' ),
                'input_schema' => $this->plugins_query_schema(),
                'execute_callback' => array( $this, 'plugins_query' ),
                'output_schema' => $this->object_schema(),
            ),
            'wpgpt/plugins-inspect' => array(
                'label' => __( 'Plugins inspect', 'wpgpt-mcp-bridge' ),
                'description' => __( 'Inspecciona uno o varios plugins instalados por plugin_file o slug.', 'wpgpt-mcp-bridge' ),
                'input_schema' => $this->plugins_inspect_schema(),
                'execute_callback' => array( $this, 'plugins_inspect' ),
                'output_schema' => $this->object_schema(),
            ),
            'wpgpt/plugins-apply' => array(
                'label' => __( 'Plugins apply', 'wpgpt-mcp-bridge' ),
                'description' => __( 'Ejecuta acciones controladas sobre plugins instalados o a instalar, con soporte dry_run.', 'wpgpt-mcp-bridge' ),
                'input_schema' => $this->plugins_apply_schema(),
                'execute_callback' => array( $this, 'plugins_apply' ),
                'output_schema' => $this->object_schema(),
                'permission_callback' => array( $this, 'can_write_plugins' ),
            ),
        );
    }

    public function plugins_query( array $input = array() ): array|WP_Error {
        return $this->service()->query( is_array( $input ) ? $input : array() );
    }

    public function plugins_inspect( array $input = array() ): array|WP_Error {
        return $this->service()->inspect( is_array( $input ) ? $input : array() );
    }

    public function plugins_apply( array $input = array() ): array|WP_Error {
        return $this->service()->apply( is_array( $input ) ? $input : array() );
    }

    private function service(): Plugin_Manager_Service {
        if ( null === $this->service ) {
            $this->service = new Plugin_Manager_Service();
        }
        return $this->service;
    }

    private function plugins_query_schema(): array {
        return array(
            'type'                 => 'object',
            'additionalProperties' => false,
            'properties'           => array(
                'search'  => array( 'type' => 'string' ),
                'filters' => array(
                    'type'                 => 'object',
                    'additionalProperties' => false,
                    'properties'           => array(
                        'plugin_file'         => array( 'type' => 'string' ),
                        'slug'                => array( 'type' => 'string' ),
                        'active'              => array( 'type' => 'boolean' ),
                        'update_available'    => array( 'type' => 'boolean' ),
                        'auto_update_enabled' => array( 'type' => 'boolean' ),
                        'source'              => array( 'type' => 'string' ),
                    ),
                ),
                'limit'   => array( 'type' => 'integer', 'minimum' => 1, 'maximum' => 200 ),
                'offset'  => array( 'type' => 'integer', 'minimum' => 0 ),
            ),
        );
    }

    private function plugins_inspect_schema(): array {
        return array(
            'type'                 => 'object',
            'additionalProperties' => false,
            'properties'           => array(
                'plugin_file'   => array( 'type' => 'string' ),
                'slug'          => array( 'type' => 'string' ),
                'plugin_files'  => array( 'type' => 'array', 'items' => array( 'type' => 'string' ) ),
                'slugs'         => array( 'type' => 'array', 'items' => array( 'type' => 'string' ) ),
                'include_repo'  => array( 'type' => 'boolean' ),
            ),
        );
    }

    private function plugins_apply_schema(): array {
        return array(
            'type'                 => 'object',
            'additionalProperties' => false,
            'properties'           => array(
                'action'   => array(
                    'type' => 'string',
                    'enum' => array( 'install', 'update', 'activate', 'deactivate', 'delete' ),
                ),
                'dry_run'  => array( 'type' => 'boolean' ),
                'targets'  => array(
                    'type'  => 'array',
                    'items' => array(
                        'type'                 => 'object',
                        'additionalProperties' => false,
                        'properties'           => array(
                            'plugin_file' => array( 'type' => 'string' ),
                            'slug'        => array( 'type' => 'string' ),
                        ),
                    ),
                ),
                'filters'  => array(
                    'type'                 => 'object',
                    'additionalProperties' => false,
                    'properties'           => array(
                        'plugin_file'         => array( 'type' => 'string' ),
                        'slug'                => array( 'type' => 'string' ),
                        'active'              => array( 'type' => 'boolean' ),
                        'update_available'    => array( 'type' => 'boolean' ),
                        'auto_update_enabled' => array( 'type' => 'boolean' ),
                        'source'              => array( 'type' => 'string' ),
                        'search'              => array( 'type' => 'string' ),
                    ),
                ),
            ),
            'required'             => array( 'action' ),
        );
    }
}
