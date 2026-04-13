<?php

namespace WPGPT\MCPBridge;

use WPGPT\MCPBridge\Content\Content_Write_Service;
use WP_Error;

if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

class Content_Write_Provider extends Base_Ability_Provider {
    private ?Content_Write_Service $service = null;

    public function get_abilities(): array {
        return array(
            'wpgpt/posts-apply' => array(
                'label'               => __( 'Posts apply', 'wpgpt-mcp-bridge' ),
                'description'         => __( 'Ejecuta acciones controladas sobre posts, con soporte dry_run.', 'wpgpt-mcp-bridge' ),
                'input_schema'        => $this->posts_apply_schema(),
                'execute_callback'    => array( $this, 'posts_apply' ),
                'output_schema'       => $this->object_schema(),
                'permission_callback' => array( $this, 'can_write_content' ),
            ),
        );
    }

    public function posts_apply( array $input ): array|WP_Error {
        return $this->service()->apply( $input );
    }

    private function service(): Content_Write_Service {
        if ( null === $this->service ) {
            $this->service = new Content_Write_Service();
        }
        return $this->service;
    }

    private function posts_apply_schema(): array {
        return array(
            'type'                 => 'object',
            'additionalProperties' => false,
            'properties'           => array(
                'action'   => array(
                    'type' => 'string',
                    'enum' => array( 'create', 'update', 'duplicate', 'delete', 'set_status', 'set_slug', 'meta_update', 'meta_delete', 'revision_restore' ),
                ),
                'dry_run'  => array( 'type' => 'boolean' ),
                'targets'  => array(
                    'type'  => 'array',
                    'items' => array(
                        'type'                 => 'object',
                        'additionalProperties' => false,
                        'properties'           => array(
                            'post_id' => array( 'type' => 'integer' ),
                            'slug'    => array( 'type' => 'string' ),
                        ),
                    ),
                ),
                'filters'  => array(
                    'type'                 => 'object',
                    'additionalProperties' => false,
                    'properties'           => array(
                        'post_type'   => array( 'oneOf' => array( array( 'type' => 'string' ), array( 'type' => 'array', 'items' => array( 'type' => 'string' ) ) ) ),
                        'post_status' => array( 'oneOf' => array( array( 'type' => 'string' ), array( 'type' => 'array', 'items' => array( 'type' => 'string' ) ) ) ),
                        'author_id'   => array( 'type' => 'integer' ),
                        'parent_id'   => array( 'type' => 'integer' ),
                        'slug'        => array( 'type' => 'string' ),
                        'search'      => array( 'type' => 'string' ),
                    ),
                ),
                'payload'  => array(
                    'type'                 => 'object',
                    'additionalProperties' => true,
                    'properties'           => array(
                        'post_type'   => array( 'type' => 'string' ),
                        'title'       => array( 'type' => 'string' ),
                        'content'     => array( 'type' => 'string' ),
                        'excerpt'     => array( 'type' => 'string' ),
                        'slug'        => array( 'type' => 'string' ),
                        'status'      => array( 'type' => 'string' ),
                        'meta'        => array( 'type' => 'object', 'additionalProperties' => true ),
                        'meta_key'    => array( 'type' => 'string' ),
                        'value'       => true,
                        'force'       => array( 'type' => 'boolean' ),
                        'revision_id' => array( 'type' => 'integer' ),
                    ),
                ),
            ),
            'required'             => array( 'action' ),
        );
    }
}
