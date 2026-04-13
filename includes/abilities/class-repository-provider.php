<?php

namespace WPGPT\MCPBridge;

use WPGPT\MCPBridge\Repository\Plugin_Repository_Service;
use WP_Error;

if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

class Repository_Provider extends Base_Ability_Provider {
    private ?Plugin_Repository_Service $service = null;

    public function get_abilities(): array {
        return array(
            'wpgpt/plugin-repository-query' => array(
                'label'            => __( 'Plugin repository query', 'wpgpt-mcp-bridge' ),
                'description'      => __( 'Busca y resume plugins del repositorio oficial de WordPress.', 'wpgpt-mcp-bridge' ),
                'input_schema'     => array(
                    'type'                 => 'object',
                    'additionalProperties' => false,
                    'properties'           => array(
                        'search' => array( 'type' => 'string' ),
                        'page'   => array( 'type' => 'integer' ),
                        'limit'  => array( 'type' => 'integer' ),
                    ),
                    'required'             => array( 'search' ),
                ),
                'execute_callback' => array( $this, 'plugin_repository_query' ),
                'output_schema'    => $this->object_schema(),
            ),
            'wpgpt/plugin-repository-inspect' => array(
                'label'            => __( 'Plugin repository inspect', 'wpgpt-mcp-bridge' ),
                'description'      => __( 'Inspecciona uno o varios plugins del repositorio oficial por slug.', 'wpgpt-mcp-bridge' ),
                'input_schema'     => array(
                    'type'                 => 'object',
                    'additionalProperties' => false,
                    'properties'           => array(
                        'slug'  => array( 'type' => 'string' ),
                        'slugs' => array( 'type' => 'array', 'items' => array( 'type' => 'string' ) ),
                    ),
                ),
                'execute_callback' => array( $this, 'plugin_repository_inspect' ),
                'output_schema'    => $this->object_schema(),
            ),
            'wpgpt/plugin-repository-apply' => array(
                'label'               => __( 'Plugin repository apply', 'wpgpt-mcp-bridge' ),
                'description'         => __( 'Ejecuta acciones controladas sobre plugins del repositorio oficial, con soporte dry_run.', 'wpgpt-mcp-bridge' ),
                'input_schema'        => array(
                    'type'                 => 'object',
                    'additionalProperties' => false,
                    'properties'           => array(
                        'action'  => array( 'type' => 'string', 'enum' => array( 'install' ) ),
                        'dry_run' => array( 'type' => 'boolean' ),
                        'targets' => array(
                            'type'  => 'array',
                            'items' => array(
                                'type'                 => 'object',
                                'additionalProperties' => false,
                                'properties'           => array(
                                    'slug' => array( 'type' => 'string' ),
                                ),
                            ),
                        ),
                        'search'  => array( 'type' => 'string' ),
                    ),
                    'required'             => array( 'action' ),
                ),
                'execute_callback'    => array( $this, 'plugin_repository_apply' ),
                'output_schema'       => $this->object_schema(),
                'permission_callback' => array( $this, 'can_manage_site' ),
            ),
        );
    }

    public function plugin_repository_query( array $input ): array|WP_Error {
        return $this->service()->query( $input );
    }

    public function plugin_repository_inspect( array $input ): array|WP_Error {
        return $this->service()->inspect( $input );
    }

    public function plugin_repository_apply( array $input ): array|WP_Error {
        return $this->service()->apply( $input );
    }

    private function service(): Plugin_Repository_Service {
        return $this->service ??= new Plugin_Repository_Service();
    }
}
