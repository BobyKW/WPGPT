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
            'wpgpt/post-create' => array(
                'label' => __( 'Post create', 'wpgpt-mcp-bridge' ),
                'description' => __( 'Crea una entrada, página o CPT.', 'wpgpt-mcp-bridge' ),
                'input_schema' => $this->post_create_schema(),
                'execute_callback' => array( $this, 'post_create' ),
                'output_schema' => $this->object_schema(),
                'permission_callback' => array( $this, 'can_write_content' ),
            ),
            'wpgpt/post-update' => array(
                'label' => __( 'Post update', 'wpgpt-mcp-bridge' ),
                'description' => __( 'Actualiza una entrada, página o CPT.', 'wpgpt-mcp-bridge' ),
                'input_schema' => $this->post_update_schema(),
                'execute_callback' => array( $this, 'post_update' ),
                'output_schema' => $this->object_schema(),
                'permission_callback' => array( $this, 'can_write_content' ),
            ),
            'wpgpt/post-duplicate' => array(
                'label' => __( 'Post duplicate', 'wpgpt-mcp-bridge' ),
                'description' => __( 'Duplica una entrada, página o CPT.', 'wpgpt-mcp-bridge' ),
                'input_schema' => $this->post_duplicate_schema(),
                'execute_callback' => array( $this, 'post_duplicate' ),
                'output_schema' => $this->object_schema(),
                'permission_callback' => array( $this, 'can_write_content' ),
            ),
            'wpgpt/post-revision-list' => array(
                'label' => __( 'Post revision list', 'wpgpt-mcp-bridge' ),
                'description' => __( 'Lista revisiones de un post.', 'wpgpt-mcp-bridge' ),
                'input_schema' => $this->meta_get_schema(),
                'execute_callback' => array( $this, 'post_revision_list' ),
                'output_schema' => $this->object_schema(),
                'permission_callback' => array( $this, 'can_edit_content' ),
            ),
            'wpgpt/post-revision-restore' => array(
                'label' => __( 'Post revision restore', 'wpgpt-mcp-bridge' ),
                'description' => __( 'Restaura una revisión concreta de un post.', 'wpgpt-mcp-bridge' ),
                'input_schema' => array( 'type' => 'object', 'properties' => array( 'revision_id' => array( 'type' => 'integer' ) ), 'required' => array( 'revision_id' ) ),
                'execute_callback' => array( $this, 'post_revision_restore' ),
                'output_schema' => $this->object_schema(),
                'permission_callback' => array( $this, 'can_write_content' ),
            ),
            'wpgpt/post-status-bulk-update' => array(
                'label' => __( 'Post status bulk update', 'wpgpt-mcp-bridge' ),
                'description' => __( 'Actualiza en lote el estado de varios posts.', 'wpgpt-mcp-bridge' ),
                'input_schema' => array( 'type' => 'object', 'properties' => array( 'post_ids' => array( 'type' => 'array', 'items' => array( 'type' => 'integer' ) ), 'status' => array( 'type' => 'string' ) ), 'required' => array( 'post_ids', 'status' ) ),
                'execute_callback' => array( $this, 'post_status_bulk_update' ),
                'output_schema' => $this->object_schema(),
                'permission_callback' => array( $this, 'can_write_content' ),
            ),
            'wpgpt/post-slug-update' => array(
                'label' => __( 'Post slug update', 'wpgpt-mcp-bridge' ),
                'description' => __( 'Actualiza el slug de un post.', 'wpgpt-mcp-bridge' ),
                'input_schema' => array( 'type' => 'object', 'properties' => array( 'post_id' => array( 'type' => 'integer' ), 'slug' => array( 'type' => 'string' ) ), 'required' => array( 'post_id', 'slug' ) ),
                'execute_callback' => array( $this, 'post_slug_update' ),
                'output_schema' => $this->object_schema(),
                'permission_callback' => array( $this, 'can_write_content' ),
            ),
            'wpgpt/post-delete' => array(
                'label' => __( 'Post delete', 'wpgpt-mcp-bridge' ),
                'description' => __( 'Elimina o envía a la papelera una entrada, página o CPT.', 'wpgpt-mcp-bridge' ),
                'input_schema' => $this->post_delete_schema(),
                'execute_callback' => array( $this, 'post_delete' ),
                'output_schema' => $this->object_schema(),
                'permission_callback' => array( $this, 'can_delete_content' ),
            ),
            'wpgpt/post-meta-get' => array(
                'label' => __( 'Post meta get', 'wpgpt-mcp-bridge' ),
                'description' => __( 'Devuelve metadatos de un post concreto.', 'wpgpt-mcp-bridge' ),
                'input_schema' => $this->meta_get_schema(),
                'execute_callback' => array( $this, 'post_meta_get' ),
                'output_schema' => $this->object_schema(),
                'permission_callback' => array( $this, 'can_edit_content' ),
            ),
            'wpgpt/post-meta-update' => array(
                'label' => __( 'Post meta update', 'wpgpt-mcp-bridge' ),
                'description' => __( 'Actualiza un meta concreto de un post.', 'wpgpt-mcp-bridge' ),
                'input_schema' => $this->meta_update_schema(),
                'execute_callback' => array( $this, 'post_meta_update' ),
                'output_schema' => $this->object_schema(),
                'permission_callback' => array( $this, 'can_write_content' ),
            ),
            'wpgpt/post-meta-delete' => array(
                'label' => __( 'Post meta delete', 'wpgpt-mcp-bridge' ),
                'description' => __( 'Elimina un meta concreto de un post.', 'wpgpt-mcp-bridge' ),
                'input_schema' => $this->meta_delete_schema(),
                'execute_callback' => array( $this, 'post_meta_delete' ),
                'output_schema' => $this->object_schema(),
                'permission_callback' => array( $this, 'can_delete_content' ),
            ),
        );
    }

    public function post_create( array $input ): array|WP_Error { return $this->service()->create_post( $input ); }
    public function post_update( array $input ): array|WP_Error { return $this->service()->update_post( $input ); }
    public function post_duplicate( array $input ): array|WP_Error { return $this->service()->duplicate_post( $input ); }
    public function post_revision_list( array $input ): array|WP_Error { return $this->service()->list_revisions( $input ); }
    public function post_revision_restore( array $input ): array|WP_Error { return $this->service()->restore_revision( $input ); }
    public function post_status_bulk_update( array $input ): array|WP_Error { return $this->service()->bulk_status_update( $input ); }
    public function post_slug_update( array $input ): array|WP_Error { return $this->service()->update_slug( $input ); }
    public function post_delete( array $input ): array|WP_Error { return $this->service()->delete_post( $input ); }
    public function post_meta_get( array $input ): array|WP_Error { return $this->service()->get_post_meta( $input ); }
    public function post_meta_update( array $input ): array|WP_Error { return $this->service()->update_post_meta( $input ); }
    public function post_meta_delete( array $input ): array|WP_Error { return $this->service()->delete_post_meta( $input ); }

    private function service(): Content_Write_Service {
        if ( null === $this->service ) {
            $this->service = new Content_Write_Service();
        }
        return $this->service;
    }

    private function post_create_schema(): array {
        return array('type'=>'object','properties'=>array('title'=>array('type'=>'string'),'content'=>array('type'=>'string'),'excerpt'=>array('type'=>'string'),'slug'=>array('type'=>'string'),'status'=>array('type'=>'string'),'post_type'=>array('type'=>'string'),'meta'=>array('type'=>'object','additionalProperties'=>true)),'required'=>array('title'));
    }
    private function post_update_schema(): array {
        return array('type'=>'object','properties'=>array('post_id'=>array('type'=>'integer'),'title'=>array('type'=>'string'),'content'=>array('type'=>'string'),'excerpt'=>array('type'=>'string'),'slug'=>array('type'=>'string'),'status'=>array('type'=>'string'),'post_type'=>array('type'=>'string'),'meta'=>array('type'=>'object','additionalProperties'=>true)),'required'=>array('post_id'));
    }
    private function post_duplicate_schema(): array { return array('type'=>'object','properties'=>array('post_id'=>array('type'=>'integer'),'title'=>array('type'=>'string'),'status'=>array('type'=>'string')),'required'=>array('post_id')); }
    private function post_delete_schema(): array {
        return array('type'=>'object','properties'=>array('post_id'=>array('type'=>'integer'),'force'=>array('type'=>'boolean')),'required'=>array('post_id'));
    }
    private function meta_get_schema(): array {
        return array('type'=>'object','properties'=>array('post_id'=>array('type'=>'integer'),'meta_key'=>array('type'=>'string')),'required'=>array('post_id'));
    }
    private function meta_update_schema(): array {
        return array('type'=>'object','properties'=>array('post_id'=>array('type'=>'integer'),'meta_key'=>array('type'=>'string'),'value'=>true),'required'=>array('post_id','meta_key'));
    }
    private function meta_delete_schema(): array {
        return array('type'=>'object','properties'=>array('post_id'=>array('type'=>'integer'),'meta_key'=>array('type'=>'string')),'required'=>array('post_id','meta_key'));
    }
}
