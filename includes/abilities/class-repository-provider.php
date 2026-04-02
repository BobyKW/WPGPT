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
            'wpgpt/plugin-repo-search' => array(
                'label'            => __( 'Plugin repo search', 'wpgpt-mcp-bridge' ),
                'description'      => __( 'Busca plugins en el repositorio oficial de WordPress.', 'wpgpt-mcp-bridge' ),
                'input_schema'     => $this->search_input_schema(),
                'execute_callback' => array( $this, 'plugin_repo_search' ),
                'output_schema'    => $this->object_schema(),
            ),
            'wpgpt/plugin-repo-info'   => array(
                'label'            => __( 'Plugin repo info', 'wpgpt-mcp-bridge' ),
                'description'      => __( 'Devuelve información básica de un plugin del repositorio oficial por slug.', 'wpgpt-mcp-bridge' ),
                'input_schema'     => $this->info_input_schema(),
                'execute_callback' => array( $this, 'plugin_repo_info' ),
                'output_schema'    => $this->object_schema(),
            ),
        );
    }

    public function plugin_repo_search( array $input ): array|WP_Error {
        $search = isset( $input['search'] ) ? sanitize_text_field( (string) $input['search'] ) : '';
        $page   = isset( $input['page'] ) ? (int) $input['page'] : 1;
        $limit  = isset( $input['limit'] ) ? (int) $input['limit'] : 10;

        return $this->service()->search( $search, $page, $limit );
    }

    public function plugin_repo_info( array $input ): array|WP_Error {
        $slug = isset( $input['slug'] ) ? sanitize_key( (string) $input['slug'] ) : '';
        return $this->service()->info( $slug );
    }

    private function service(): Plugin_Repository_Service {
        if ( null === $this->service ) {
            $this->service = new Plugin_Repository_Service();
        }

        return $this->service;
    }

    private function search_input_schema(): array {
        return array(
            'type'       => 'object',
            'properties' => array(
                'search' => array( 'type' => 'string' ),
                'page'   => array( 'type' => 'integer' ),
                'limit'  => array( 'type' => 'integer' ),
            ),
            'required'   => array( 'search' ),
        );
    }

    private function info_input_schema(): array {
        return array(
            'type'       => 'object',
            'properties' => array(
                'slug' => array( 'type' => 'string' ),
            ),
            'required'   => array( 'slug' ),
        );
    }
}
