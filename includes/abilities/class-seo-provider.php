<?php

namespace WPGPT\MCPBridge;

use WPGPT\MCPBridge\SEO\SEO_Service;
use WP_Error;

if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

class SEO_Provider extends Base_Ability_Provider {
    private ?SEO_Service $service = null;

    public function get_abilities(): array {
        return array(
            'wpgpt/seo-query' => array(
                'label' => __( 'SEO query', 'wpgpt-mcp-bridge' ),
                'description' => __( 'Resume el estado SEO del sitio, plugins SEO activos y posts filtrables con señales SEO básicas.', 'wpgpt-mcp-bridge' ),
                'input_schema' => $this->query_schema(),
                'execute_callback' => array( $this, 'seo_query' ),
                'output_schema' => $this->object_schema(),
            ),
            'wpgpt/seo-inspect' => array(
                'label' => __( 'SEO inspect', 'wpgpt-mcp-bridge' ),
                'description' => __( 'Inspecciona uno o varios posts SEO y/o una opción SEO concreta.', 'wpgpt-mcp-bridge' ),
                'input_schema' => $this->inspect_schema(),
                'execute_callback' => array( $this, 'seo_inspect' ),
                'output_schema' => $this->object_schema(),
            ),
            'wpgpt/seo-apply' => array(
                'label' => __( 'SEO apply', 'wpgpt-mcp-bridge' ),
                'description' => __( 'Ejecuta cambios controlados sobre meta SEO de posts u opciones SEO, con soporte dry_run.', 'wpgpt-mcp-bridge' ),
                'input_schema' => $this->apply_schema(),
                'execute_callback' => array( $this, 'seo_apply' ),
                'output_schema' => $this->object_schema(),
                'permission_callback' => array( $this, 'can_manage_site' ),
            ),
        );
    }

    public function seo_query( array $input = array() ): array|WP_Error { return $this->service()->query( $input ); }
    public function seo_inspect( array $input = array() ): array|WP_Error { return $this->service()->inspect( $input ); }
    public function seo_apply( array $input = array() ): array|WP_Error { return $this->service()->apply( $input ); }

    private function service(): SEO_Service { return $this->service ??= new SEO_Service(); }

    private function query_schema(): array {
        return array(
            'type' => 'object',
            'additionalProperties' => false,
            'properties' => array(
                'search' => array( 'type' => 'string' ),
                'filters' => array(
                    'type' => 'object',
                    'additionalProperties' => false,
                    'properties' => array(
                        'post_id' => array( 'type' => 'integer' ),
                        'post_type' => array( 'type' => 'string' ),
                        'post_status' => array( 'type' => 'string' ),
                        'plugin' => array( 'type' => 'string' ),
                    ),
                ),
                'limit' => array( 'type' => 'integer', 'minimum' => 1, 'maximum' => 100 ),
                'offset' => array( 'type' => 'integer', 'minimum' => 0 ),
            ),
        );
    }

    private function inspect_schema(): array {
        return array(
            'type' => 'object',
            'additionalProperties' => false,
            'properties' => array(
                'post_id' => array( 'type' => 'integer' ),
                'post_ids' => array( 'type' => 'array', 'items' => array( 'type' => 'integer' ) ),
                'plugin' => array( 'type' => 'string' ),
                'option_name' => array( 'type' => 'string' ),
                'include_analysis' => array( 'type' => 'boolean' ),
                'include_settings' => array( 'type' => 'boolean' ),
            ),
        );
    }

    private function apply_schema(): array {
        return array(
            'type' => 'object',
            'additionalProperties' => false,
            'properties' => array(
                'action' => array( 'type' => 'string', 'enum' => array( 'meta_update', 'settings_update' ) ),
                'dry_run' => array( 'type' => 'boolean' ),
                'targets' => array(
                    'type' => 'array',
                    'items' => array(
                        'type' => 'object',
                        'additionalProperties' => false,
                        'properties' => array(
                            'post_id' => array( 'type' => 'integer' ),
                            'plugin' => array( 'type' => 'string' ),
                            'option_name' => array( 'type' => 'string' ),
                        ),
                    ),
                ),
                'payload' => array(
                    'type' => 'object',
                    'additionalProperties' => true,
                    'properties' => array(
                        'plugin' => array( 'type' => 'string' ),
                        'meta' => array( 'type' => 'object', 'additionalProperties' => true ),
                        'option_name' => array( 'type' => 'string' ),
                        'settings_patch' => array( 'type' => 'object', 'additionalProperties' => true ),
                    ),
                ),
            ),
            'required' => array( 'action' ),
        );
    }
}
