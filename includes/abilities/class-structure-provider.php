<?php

namespace WPGPT\MCPBridge;

use WPGPT\MCPBridge\Structure\Metabox_Manager;
use WPGPT\MCPBridge\Structure\Post_Type_Manager;
use WPGPT\MCPBridge\Structure\Taxonomy_Manager;
use WP_Error;

if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

class Structure_Provider extends Base_Ability_Provider {
    public function get_abilities(): array {
        return array(
            'wpgpt/cpt-list' => array(
                'label' => __( 'CPT list', 'wpgpt-mcp-bridge' ),
                'description' => __( 'Lista tipos de contenido registrados y los gestionados por el plugin.', 'wpgpt-mcp-bridge' ),
                'execute_callback' => array( $this, 'cpt_list' ),
                'output_schema' => $this->object_schema(),
            ),
            'wpgpt/cpt-create' => array(
                'label' => __( 'CPT create', 'wpgpt-mcp-bridge' ),
                'description' => __( 'Crea y persiste un Custom Post Type.', 'wpgpt-mcp-bridge' ),
                'input_schema' => $this->cpt_schema(),
                'execute_callback' => array( $this, 'cpt_create' ),
                'output_schema' => $this->object_schema(),
                'permission_callback' => array( $this, 'can_write_structure' ),
            ),
            'wpgpt/cpt-delete' => array(
                'label' => __( 'CPT delete', 'wpgpt-mcp-bridge' ),
                'description' => __( 'Elimina un Custom Post Type gestionado por el plugin.', 'wpgpt-mcp-bridge' ),
                'input_schema' => $this->slug_schema(),
                'execute_callback' => array( $this, 'cpt_delete' ),
                'output_schema' => $this->object_schema(),
                'permission_callback' => array( $this, 'can_delete_structure' ),
            ),
            'wpgpt/taxonomy-list' => array(
                'label' => __( 'Taxonomy list', 'wpgpt-mcp-bridge' ),
                'description' => __( 'Lista taxonomías registradas y las gestionadas por el plugin.', 'wpgpt-mcp-bridge' ),
                'execute_callback' => array( $this, 'taxonomy_list' ),
                'output_schema' => $this->object_schema(),
            ),
            'wpgpt/taxonomy-create' => array(
                'label' => __( 'Taxonomy create', 'wpgpt-mcp-bridge' ),
                'description' => __( 'Crea y persiste una taxonomía custom.', 'wpgpt-mcp-bridge' ),
                'input_schema' => $this->taxonomy_schema(),
                'execute_callback' => array( $this, 'taxonomy_create' ),
                'output_schema' => $this->object_schema(),
                'permission_callback' => array( $this, 'can_write_structure' ),
            ),
            'wpgpt/taxonomy-delete' => array(
                'label' => __( 'Taxonomy delete', 'wpgpt-mcp-bridge' ),
                'description' => __( 'Elimina una taxonomía gestionada por el plugin.', 'wpgpt-mcp-bridge' ),
                'input_schema' => $this->slug_schema(),
                'execute_callback' => array( $this, 'taxonomy_delete' ),
                'output_schema' => $this->object_schema(),
                'permission_callback' => array( $this, 'can_delete_structure' ),
            ),
            'wpgpt/metabox-list' => array(
                'label' => __( 'Metabox list', 'wpgpt-mcp-bridge' ),
                'description' => __( 'Lista definiciones de metaboxes gestionadas por el plugin.', 'wpgpt-mcp-bridge' ),
                'execute_callback' => array( $this, 'metabox_list' ),
                'output_schema' => $this->object_schema(),
            ),
            'wpgpt/metabox-create' => array(
                'label' => __( 'Metabox create', 'wpgpt-mcp-bridge' ),
                'description' => __( 'Crea y persiste un metabox simple con campos básicos.', 'wpgpt-mcp-bridge' ),
                'input_schema' => $this->metabox_schema(),
                'execute_callback' => array( $this, 'metabox_create' ),
                'output_schema' => $this->object_schema(),
                'permission_callback' => array( $this, 'can_write_structure' ),
            ),
            'wpgpt/metabox-delete' => array(
                'label' => __( 'Metabox delete', 'wpgpt-mcp-bridge' ),
                'description' => __( 'Elimina un metabox gestionado por el plugin.', 'wpgpt-mcp-bridge' ),
                'input_schema' => $this->metabox_delete_schema(),
                'execute_callback' => array( $this, 'metabox_delete' ),
                'output_schema' => $this->object_schema(),
                'permission_callback' => array( $this, 'can_delete_structure' ),
            ),
        );
    }

    public function cpt_list(): array { return Post_Type_Manager::list(); }
    public function cpt_create( array $input ): array|WP_Error { return Post_Type_Manager::create( $input ); }
    public function cpt_delete( array $input ): array|WP_Error { return Post_Type_Manager::delete( $input ); }
    public function taxonomy_list(): array { return Taxonomy_Manager::list(); }
    public function taxonomy_create( array $input ): array|WP_Error { return Taxonomy_Manager::create( $input ); }
    public function taxonomy_delete( array $input ): array|WP_Error { return Taxonomy_Manager::delete( $input ); }
    public function metabox_list(): array { return Metabox_Manager::list(); }
    public function metabox_create( array $input ): array|WP_Error { return Metabox_Manager::create( $input ); }
    public function metabox_delete( array $input ): array|WP_Error { return Metabox_Manager::delete( $input ); }

    private function cpt_schema(): array {
        return array('type'=>'object','properties'=>array('slug'=>array('type'=>'string'),'label'=>array('type'=>'string'),'singular_label'=>array('type'=>'string'),'public'=>array('type'=>'boolean'),'show_in_rest'=>array('type'=>'boolean'),'hierarchical'=>array('type'=>'boolean'),'supports'=>array('type'=>'array','items'=>array('type'=>'string'))),'required'=>array('slug','label'));
    }
    private function taxonomy_schema(): array {
        return array('type'=>'object','properties'=>array('slug'=>array('type'=>'string'),'label'=>array('type'=>'string'),'singular_label'=>array('type'=>'string'),'public'=>array('type'=>'boolean'),'show_in_rest'=>array('type'=>'boolean'),'hierarchical'=>array('type'=>'boolean'),'object_type'=>array('type'=>'array','items'=>array('type'=>'string')),'post_types'=>array('type'=>'array','items'=>array('type'=>'string'))),'required'=>array('slug','label'));
    }
    private function metabox_schema(): array {
        return array('type'=>'object','properties'=>array('key'=>array('type'=>'string'),'id'=>array('type'=>'string'),'title'=>array('type'=>'string'),'post_types'=>array('type'=>'array','items'=>array('type'=>'string')),'fields'=>array('type'=>'array','items'=>array('type'=>'object','additionalProperties'=>true))),'required'=>array('title','fields'));
    }
    private function slug_schema(): array {
        return array('type'=>'object','properties'=>array('slug'=>array('type'=>'string')),'required'=>array('slug'));
    }
    private function metabox_delete_schema(): array {
        return array('type'=>'object','properties'=>array('key'=>array('type'=>'string')),'required'=>array('key'));
    }
}
