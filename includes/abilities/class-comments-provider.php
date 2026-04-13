<?php

namespace WPGPT\MCPBridge;

use WPGPT\MCPBridge\Comments\Comments_Service;

if ( ! defined( 'ABSPATH' ) ) { exit; }

class Comments_Provider extends Base_Ability_Provider {
    private ?Comments_Service $service = null;

    public function get_abilities(): array {
        return array(
            'wpgpt/discussion-query' => array(
                'label' => __( 'Discussion query', 'wpgpt-mcp-bridge' ),
                'description' => __( 'Audita el estado de comentarios y pings sobre posts y páginas.', 'wpgpt-mcp-bridge' ),
                'input_schema' => $this->discussion_audit_schema(),
                'execute_callback' => array( $this, 'discussion_query' ),
                'output_schema' => $this->object_schema(),
                'permission_callback' => array( $this, 'can_edit_content' ),
            ),
            'wpgpt/discussion-inspect' => array(
                'label' => __( 'Discussion inspect', 'wpgpt-mcp-bridge' ),
                'description' => __( 'Inspecciona el estado de discusión con filtros concretos.', 'wpgpt-mcp-bridge' ),
                'input_schema' => $this->discussion_audit_schema(),
                'execute_callback' => array( $this, 'discussion_inspect' ),
                'output_schema' => $this->object_schema(),
                'permission_callback' => array( $this, 'can_edit_content' ),
            ),
            'wpgpt/discussion-apply' => array(
                'label' => __( 'Discussion apply', 'wpgpt-mcp-bridge' ),
                'description' => __( 'Valida acciones sobre discusión, con soporte dry_run.', 'wpgpt-mcp-bridge' ),
                'input_schema' => $this->discussion_apply_schema(),
                'execute_callback' => array( $this, 'discussion_apply' ),
                'output_schema' => $this->object_schema(),
                'permission_callback' => array( $this, 'can_edit_content' ),
            ),
            'wpgpt/comments-query' => array(
                'label' => __( 'Comments query', 'wpgpt-mcp-bridge' ),
                'description' => __( 'Lista, filtra y resume comentarios del sitio.', 'wpgpt-mcp-bridge' ),
                'input_schema' => $this->comments_query_schema(),
                'execute_callback' => array( $this, 'comments_query' ),
                'output_schema' => $this->object_schema(),
                'permission_callback' => array( $this, 'can_edit_content' ),
            ),
            'wpgpt/comments-inspect' => array(
                'label' => __( 'Comments inspect', 'wpgpt-mcp-bridge' ),
                'description' => __( 'Inspecciona uno o varios comentarios por comment_id.', 'wpgpt-mcp-bridge' ),
                'input_schema' => $this->comments_inspect_schema(),
                'execute_callback' => array( $this, 'comments_inspect' ),
                'output_schema' => $this->object_schema(),
                'permission_callback' => array( $this, 'can_edit_content' ),
            ),
            'wpgpt/comments-apply' => array(
                'label' => __( 'Comments apply', 'wpgpt-mcp-bridge' ),
                'description' => __( 'Ejecuta acciones controladas sobre comentarios, con soporte dry_run.', 'wpgpt-mcp-bridge' ),
                'input_schema' => $this->comments_apply_schema(),
                'execute_callback' => array( $this, 'comments_apply' ),
                'output_schema' => $this->object_schema(),
                'permission_callback' => array( $this, 'can_write_content' ),
            ),
        );
    }

    public function discussion_query( array $input ) { return $this->service()->discussion_audit( $input ); }
    public function discussion_inspect( array $input ) { return $this->service()->discussion_audit( $input ); }
    public function discussion_apply( array $input ) { $dry_run = ! empty( $input['dry_run'] ); return array( 'summary' => array( 'action' => isset( $input['action'] ) ? (string) $input['action'] : 'audit', 'dry_run' => $dry_run, 'executed' => 0 ), 'items' => array(), 'warnings' => array(), 'blocked' => array(), 'next_actions' => array() ); }
    public function comments_query( array $input = array() ) { return $this->service()->query( $input ); }
    public function comments_inspect( array $input = array() ) { return $this->service()->inspect( $input ); }
    public function comments_apply( array $input = array() ) { return $this->service()->apply( $input ); }

    private function service(): Comments_Service { return $this->service ??= new Comments_Service(); }

    private function discussion_audit_schema(): array {
        return array('type'=>'object','properties'=>array('post_type'=>array('type'=>'string'),'post_status'=>array('type'=>'string'),'limit'=>array('type'=>'integer'),'override_only'=>array('type'=>'boolean'),'comments_open'=>array('type'=>'boolean'),'pings_open'=>array('type'=>'boolean')));
    }

    private function discussion_apply_schema(): array {
        return array('type'=>'object','additionalProperties'=>false,'properties'=>array('action'=>array('type'=>'string','enum'=>array('audit')),'dry_run'=>array('type'=>'boolean')),'required'=>array('action'));
    }

    private function comments_query_schema(): array {
        return array(
            'type' => 'object',
            'additionalProperties' => false,
            'properties' => array(
                'search' => array( 'type' => 'string' ),
                'filters' => array(
                    'type' => 'object',
                    'additionalProperties' => false,
                    'properties' => array(
                        'comment_id' => array( 'type' => 'integer' ),
                        'post_id' => array( 'type' => 'integer' ),
                        'status' => array( 'type' => 'string' ),
                        'author_email' => array( 'type' => 'string' ),
                        'author_name' => array( 'type' => 'string' ),
                        'author_user_id' => array( 'type' => 'integer' ),
                        'parent' => array( 'type' => 'integer' ),
                    ),
                ),
                'limit' => array( 'type' => 'integer', 'minimum' => 1, 'maximum' => 200 ),
                'offset' => array( 'type' => 'integer', 'minimum' => 0 ),
            ),
        );
    }

    private function comments_inspect_schema(): array {
        return array(
            'type' => 'object',
            'additionalProperties' => false,
            'properties' => array(
                'comment_id' => array( 'type' => 'integer' ),
                'comment_ids' => array( 'type' => 'array', 'items' => array( 'type' => 'integer' ) ),
            ),
        );
    }

    private function comments_apply_schema(): array {
        return array(
            'type' => 'object',
            'additionalProperties' => false,
            'properties' => array(
                'action' => array( 'type' => 'string', 'enum' => array( 'create', 'update', 'status', 'delete' ) ),
                'dry_run' => array( 'type' => 'boolean' ),
                'targets' => array(
                    'type' => 'array',
                    'items' => array(
                        'type' => 'object',
                        'additionalProperties' => false,
                        'properties' => array(
                            'comment_id' => array( 'type' => 'integer' ),
                        ),
                    ),
                ),
                'filters' => array(
                    'type' => 'object',
                    'additionalProperties' => false,
                    'properties' => array(
                        'post_id' => array( 'type' => 'integer' ),
                        'status' => array( 'type' => 'string' ),
                        'author_email' => array( 'type' => 'string' ),
                        'author_name' => array( 'type' => 'string' ),
                        'author_user_id' => array( 'type' => 'integer' ),
                        'parent' => array( 'type' => 'integer' ),
                        'search' => array( 'type' => 'string' ),
                    ),
                ),
                'payload' => array(
                    'type' => 'object',
                    'additionalProperties' => true,
                    'properties' => array(
                        'comment_id' => array( 'type' => 'integer' ),
                        'post_id' => array( 'type' => 'integer' ),
                        'content' => array( 'type' => 'string' ),
                        'author_name' => array( 'type' => 'string' ),
                        'author_email' => array( 'type' => 'string' ),
                        'author_url' => array( 'type' => 'string' ),
                        'parent' => array( 'type' => 'integer' ),
                        'status' => array( 'type' => 'string' ),
                        'force' => array( 'type' => 'boolean' ),
                    ),
                ),
            ),
            'required' => array( 'action' ),
        );
    }
}
