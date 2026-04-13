<?php

namespace WPGPT\MCPBridge;

use WPGPT\MCPBridge\Publishing\Publishing_Service;

if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

class Publishing_Provider extends Base_Ability_Provider {
    private ?Publishing_Service $service = null;

    public function get_abilities(): array {
        return array(
            'wpgpt/drafts-query' => array(
                'label' => __( 'Drafts query', 'wpgpt-mcp-bridge' ),
                'description' => __( 'Resume la capacidad de creación asistida de borradores y sus parámetros admitidos.', 'wpgpt-mcp-bridge' ),
                'input_schema' => array( 'type' => 'object', 'additionalProperties' => false, 'properties' => array() ),
                'execute_callback' => array( $this, 'drafts_query' ),
                'output_schema' => $this->object_schema(),
                'permission_callback' => array( $this, 'can_write_content' ),
            ),
            'wpgpt/drafts-inspect' => array(
                'label' => __( 'Drafts inspect', 'wpgpt-mcp-bridge' ),
                'description' => __( 'Inspecciona la operación de creación asistida de borradores.', 'wpgpt-mcp-bridge' ),
                'input_schema' => array( 'type' => 'object', 'additionalProperties' => false, 'properties' => array() ),
                'execute_callback' => array( $this, 'drafts_inspect' ),
                'output_schema' => $this->object_schema(),
                'permission_callback' => array( $this, 'can_write_content' ),
            ),
            'wpgpt/drafts-apply' => array(
                'label' => __( 'Drafts apply', 'wpgpt-mcp-bridge' ),
                'description' => __( 'Crea borradores asistidos, con soporte dry_run.', 'wpgpt-mcp-bridge' ),
                'input_schema' => $this->drafts_apply_schema(),
                'execute_callback' => array( $this, 'drafts_apply' ),
                'output_schema' => $this->object_schema(),
                'permission_callback' => array( $this, 'can_write_content' ),
            ),
            'wpgpt/terms-query' => array(
                'label' => __( 'Terms query', 'wpgpt-mcp-bridge' ),
                'description' => __( 'Lista, filtra y resume términos y taxonomías.', 'wpgpt-mcp-bridge' ),
                'input_schema' => $this->terms_query_schema(),
                'execute_callback' => array( $this, 'terms_query' ),
                'output_schema' => $this->object_schema(),
            ),
            'wpgpt/terms-inspect' => array(
                'label' => __( 'Terms inspect', 'wpgpt-mcp-bridge' ),
                'description' => __( 'Inspecciona uno o varios términos por taxonomy y term_id.', 'wpgpt-mcp-bridge' ),
                'input_schema' => $this->terms_inspect_schema(),
                'execute_callback' => array( $this, 'terms_inspect' ),
                'output_schema' => $this->object_schema(),
            ),
            'wpgpt/terms-apply' => array(
                'label' => __( 'Terms apply', 'wpgpt-mcp-bridge' ),
                'description' => __( 'Ejecuta acciones controladas sobre términos y asignaciones, con soporte dry_run.', 'wpgpt-mcp-bridge' ),
                'input_schema' => $this->terms_apply_schema(),
                'execute_callback' => array( $this, 'terms_apply' ),
                'output_schema' => $this->object_schema(),
                'permission_callback' => array( $this, 'can_write_content' ),
            ),
        );
    }

    public function drafts_query( array $input = array() ) { return array( 'summary' => array( 'operation' => 'create_blog_draft' ), 'items' => array( array( 'fields' => array( 'title', 'content', 'excerpt', 'slug', 'post_status', 'categories', 'tags', 'seo' ) ) ), 'warnings' => array(), 'next_actions' => array() ); }
    public function drafts_inspect( array $input = array() ) { return array( 'operation' => 'create_blog_draft', 'required' => array( 'title' ), 'optional' => array( 'content', 'excerpt', 'slug', 'post_status', 'categories', 'tags', 'seo' ) ); }
    public function drafts_apply( array $input ) { $dry_run = ! empty( $input['dry_run'] ); if ( $dry_run ) { return array( 'summary' => array( 'action' => 'create', 'dry_run' => true, 'executed' => 0 ), 'items' => array( array( 'status' => 'dry_run', 'message' => __( 'Acción validada, no ejecutada por dry_run.', 'wpgpt-mcp-bridge' ) ) ), 'warnings' => array(), 'blocked' => array(), 'next_actions' => array() ); } return $this->service()->create_blog_draft( $input['payload'] ?? $input ); }
    public function terms_query( array $input = array() ) { return $this->service()->query( $input ); }
    public function terms_inspect( array $input = array() ) { return $this->service()->inspect( $input ); }
    public function terms_apply( array $input = array() ) { return $this->service()->apply( $input ); }

    private function service(): Publishing_Service { if ( null === $this->service ) { $this->service = new Publishing_Service(); } return $this->service; }

    private function drafts_apply_schema(): array {
        return array( 'type' => 'object', 'additionalProperties' => false, 'properties' => array( 'dry_run' => array( 'type' => 'boolean' ), 'payload' => array( 'type' => 'object', 'additionalProperties' => true, 'properties' => array( 'title' => array( 'type' => 'string' ), 'content' => array( 'type' => 'string' ), 'excerpt' => array( 'type' => 'string' ), 'slug' => array( 'type' => 'string' ), 'post_status' => array( 'type' => 'string' ), 'categories' => array( 'type' => 'array', 'items' => array( 'type' => 'string' ) ), 'tags' => array( 'type' => 'array', 'items' => array( 'type' => 'string' ) ), 'seo' => array( 'type' => 'object', 'additionalProperties' => true ) ) ) ) );
    }

    private function terms_query_schema(): array {
        return array(
            'type' => 'object',
            'additionalProperties' => false,
            'properties' => array(
                'search' => array( 'type' => 'string' ),
                'filters' => array(
                    'type' => 'object',
                    'additionalProperties' => false,
                    'properties' => array(
                        'taxonomy' => array( 'type' => 'string' ),
                        'term_id' => array( 'type' => 'integer' ),
                        'slug' => array( 'type' => 'string' ),
                        'parent' => array( 'type' => 'integer' ),
                        'hide_empty' => array( 'type' => 'boolean' ),
                    ),
                ),
                'limit' => array( 'type' => 'integer', 'minimum' => 1, 'maximum' => 200 ),
                'offset' => array( 'type' => 'integer', 'minimum' => 0 ),
            ),
        );
    }

    private function terms_inspect_schema(): array {
        return array(
            'type' => 'object',
            'additionalProperties' => false,
            'properties' => array(
                'taxonomy' => array( 'type' => 'string' ),
                'term_id' => array( 'type' => 'integer' ),
                'targets' => array(
                    'type' => 'array',
                    'items' => array(
                        'type' => 'object',
                        'additionalProperties' => false,
                        'properties' => array(
                            'taxonomy' => array( 'type' => 'string' ),
                            'term_id' => array( 'type' => 'integer' ),
                        ),
                    ),
                ),
            ),
        );
    }

    private function terms_apply_schema(): array {
        return array(
            'type' => 'object',
            'additionalProperties' => false,
            'properties' => array(
                'action' => array( 'type' => 'string', 'enum' => array( 'create', 'update', 'assign_to_post', 'remove_from_post', 'delete' ) ),
                'dry_run' => array( 'type' => 'boolean' ),
                'targets' => array(
                    'type' => 'array',
                    'items' => array(
                        'type' => 'object',
                        'additionalProperties' => false,
                        'properties' => array(
                            'taxonomy' => array( 'type' => 'string' ),
                            'term_id' => array( 'type' => 'integer' ),
                        ),
                    ),
                ),
                'filters' => array(
                    'type' => 'object',
                    'additionalProperties' => false,
                    'properties' => array(
                        'taxonomy' => array( 'type' => 'string' ),
                        'term_id' => array( 'type' => 'integer' ),
                        'slug' => array( 'type' => 'string' ),
                        'parent' => array( 'type' => 'integer' ),
                        'hide_empty' => array( 'type' => 'boolean' ),
                        'search' => array( 'type' => 'string' ),
                    ),
                ),
                'payload' => array(
                    'type' => 'object',
                    'additionalProperties' => true,
                    'properties' => array(
                        'taxonomy' => array( 'type' => 'string' ),
                        'term_id' => array( 'type' => 'integer' ),
                        'name' => array( 'type' => 'string' ),
                        'slug' => array( 'type' => 'string' ),
                        'description' => array( 'type' => 'string' ),
                        'parent' => array( 'type' => 'integer' ),
                        'post_id' => array( 'type' => 'integer' ),
                        'append' => array( 'type' => 'boolean' ),
                    ),
                ),
            ),
            'required' => array( 'action' ),
        );
    }
}
