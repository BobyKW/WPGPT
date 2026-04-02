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
            'wpgpt/blog-draft-create' => array(
                'label' => __( 'Blog draft create', 'wpgpt-mcp-bridge' ),
                'description' => __( 'Crea un borrador de blog con contenido, categorías, etiquetas y metadatos SEO básicos.', 'wpgpt-mcp-bridge' ),
                'input_schema' => array( 'type' => 'object', 'properties' => array( 'title' => array( 'type' => 'string' ), 'content' => array( 'type' => 'string' ), 'excerpt' => array( 'type' => 'string' ), 'slug' => array( 'type' => 'string' ), 'post_status' => array( 'type' => 'string' ), 'categories' => array( 'type' => 'array', 'items' => array( 'type' => 'string' ) ), 'tags' => array( 'type' => 'array', 'items' => array( 'type' => 'string' ) ), 'seo' => true ), 'required' => array( 'title' ) ),
                'execute_callback' => array( $this, 'blog_draft_create' ),
                'output_schema' => $this->object_schema(),
                'permission_callback' => array( $this, 'can_write_content' ),
            ),
            'wpgpt/terms-list' => array(
                'label' => __( 'Terms list', 'wpgpt-mcp-bridge' ),
                'description' => __( 'Lista términos de una taxonomía.', 'wpgpt-mcp-bridge' ),
                'input_schema' => array( 'type' => 'object', 'properties' => array( 'taxonomy' => array( 'type' => 'string' ), 'hide_empty' => array( 'type' => 'boolean' ), 'limit' => array( 'type' => 'integer' ) ), 'required' => array( 'taxonomy' ) ),
                'execute_callback' => array( $this, 'terms_list' ),
                'output_schema' => $this->object_schema(),
            ),
            'wpgpt/term-get' => array(
                'label' => __( 'Term get', 'wpgpt-mcp-bridge' ),
                'description' => __( 'Obtiene un término concreto.', 'wpgpt-mcp-bridge' ),
                'input_schema' => array( 'type' => 'object', 'properties' => array( 'taxonomy' => array( 'type' => 'string' ), 'term_id' => array( 'type' => 'integer' ) ), 'required' => array( 'taxonomy', 'term_id' ) ),
                'execute_callback' => array( $this, 'term_get' ),
                'output_schema' => $this->object_schema(),
            ),
            'wpgpt/term-create' => array(
                'label' => __( 'Term create', 'wpgpt-mcp-bridge' ),
                'description' => __( 'Crea un término en una taxonomía existente.', 'wpgpt-mcp-bridge' ),
                'input_schema' => array( 'type' => 'object', 'properties' => array( 'taxonomy' => array( 'type' => 'string' ), 'name' => array( 'type' => 'string' ), 'slug' => array( 'type' => 'string' ) ), 'required' => array( 'taxonomy', 'name' ) ),
                'execute_callback' => array( $this, 'term_create' ),
                'output_schema' => $this->object_schema(),
                'permission_callback' => array( $this, 'can_write_structure' ),
            ),
            'wpgpt/term-update' => array(
                'label' => __( 'Term update', 'wpgpt-mcp-bridge' ),
                'description' => __( 'Actualiza un término existente.', 'wpgpt-mcp-bridge' ),
                'input_schema' => array( 'type' => 'object', 'properties' => array( 'taxonomy' => array( 'type' => 'string' ), 'term_id' => array( 'type' => 'integer' ), 'name' => array( 'type' => 'string' ), 'slug' => array( 'type' => 'string' ), 'description' => array( 'type' => 'string' ), 'parent' => array( 'type' => 'integer' ) ), 'required' => array( 'taxonomy', 'term_id' ) ),
                'execute_callback' => array( $this, 'term_update' ),
                'output_schema' => $this->object_schema(),
                'permission_callback' => array( $this, 'can_write_structure' ),
            ),
            'wpgpt/term-assign-to-post' => array(
                'label' => __( 'Term assign to post', 'wpgpt-mcp-bridge' ),
                'description' => __( 'Asigna un término a un post.', 'wpgpt-mcp-bridge' ),
                'input_schema' => array( 'type' => 'object', 'properties' => array( 'post_id' => array( 'type' => 'integer' ), 'taxonomy' => array( 'type' => 'string' ), 'term_id' => array( 'type' => 'integer' ), 'append' => array( 'type' => 'boolean' ) ), 'required' => array( 'post_id', 'taxonomy', 'term_id' ) ),
                'execute_callback' => array( $this, 'term_assign_to_post' ),
                'output_schema' => $this->object_schema(),
                'permission_callback' => array( $this, 'can_write_content' ),
            ),
            'wpgpt/term-remove-from-post' => array(
                'label' => __( 'Term remove from post', 'wpgpt-mcp-bridge' ),
                'description' => __( 'Elimina la asignación de un término en un post.', 'wpgpt-mcp-bridge' ),
                'input_schema' => array( 'type' => 'object', 'properties' => array( 'post_id' => array( 'type' => 'integer' ), 'taxonomy' => array( 'type' => 'string' ), 'term_id' => array( 'type' => 'integer' ) ), 'required' => array( 'post_id', 'taxonomy', 'term_id' ) ),
                'execute_callback' => array( $this, 'term_remove_from_post' ),
                'output_schema' => $this->object_schema(),
                'permission_callback' => array( $this, 'can_write_content' ),
            ),
            'wpgpt/term-delete' => array(
                'label' => __( 'Term delete', 'wpgpt-mcp-bridge' ),
                'description' => __( 'Elimina un término de una taxonomía.', 'wpgpt-mcp-bridge' ),
                'input_schema' => array( 'type' => 'object', 'properties' => array( 'taxonomy' => array( 'type' => 'string' ), 'term_id' => array( 'type' => 'integer' ) ), 'required' => array( 'taxonomy', 'term_id' ) ),
                'execute_callback' => array( $this, 'term_delete' ),
                'output_schema' => $this->object_schema(),
                'permission_callback' => array( $this, 'can_delete_structure' ),
            ),
        );
    }

    public function blog_draft_create( array $input ) { return $this->service()->create_blog_draft( $input ); }
    public function terms_list( array $input ) { return $this->service()->list_terms( $input ); }
    public function term_get( array $input ) { return $this->service()->get_term_data( $input ); }
    public function term_create( array $input ) { return $this->service()->create_term( $input ); }
    public function term_update( array $input ) { return $this->service()->update_term_data( $input ); }
    public function term_assign_to_post( array $input ) { return $this->service()->assign_term_to_post( $input ); }
    public function term_remove_from_post( array $input ) { return $this->service()->remove_term_from_post( $input ); }
    public function term_delete( array $input ) { return $this->service()->delete_term( $input ); }

    private function service(): Publishing_Service { if ( null === $this->service ) { $this->service = new Publishing_Service(); } return $this->service; }
}
