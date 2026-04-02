<?php

namespace WPGPT\MCPBridge;

use WPGPT\MCPBridge\SEO\SEO_Service;

if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

class SEO_Provider extends Base_Ability_Provider {
    private ?SEO_Service $service = null;

    public function get_abilities(): array {
        return array(

            'wpgpt/seo-analysis-get' => array(
                'label' => __( 'SEO analysis get', 'wpgpt-mcp-bridge' ),
                'description' => __( 'Lee el análisis SEO almacenado para un post, incluido score si está disponible.', 'wpgpt-mcp-bridge' ),
                'input_schema' => array( 'type' => 'object', 'properties' => array( 'post_id' => array( 'type' => 'integer' ) ), 'required' => array( 'post_id' ) ),
                'execute_callback' => array( $this, 'seo_analysis_get' ),
                'output_schema' => $this->object_schema(),
            ),
            'wpgpt/seo-plugin-status' => array(
                'label' => __( 'SEO plugin status', 'wpgpt-mcp-bridge' ),
                'description' => __( 'Informa del estado de Rank Math y Yoast SEO.', 'wpgpt-mcp-bridge' ),
                'execute_callback' => array( $this, 'seo_plugin_status' ),
                'output_schema' => $this->object_schema(),
            ),
            'wpgpt/seo-meta-get' => array(
                'label' => __( 'SEO meta get', 'wpgpt-mcp-bridge' ),
                'description' => __( 'Lee metadatos SEO soportados de un post para Rank Math y Yoast.', 'wpgpt-mcp-bridge' ),
                'input_schema' => array( 'type' => 'object', 'properties' => array( 'post_id' => array( 'type' => 'integer' ) ), 'required' => array( 'post_id' ) ),
                'execute_callback' => array( $this, 'seo_meta_get' ),
                'output_schema' => $this->object_schema(),
            ),
            'wpgpt/seo-meta-update' => array(
                'label' => __( 'SEO meta update', 'wpgpt-mcp-bridge' ),
                'description' => __( 'Actualiza metadatos SEO soportados de un post para Rank Math y/o Yoast.', 'wpgpt-mcp-bridge' ),
                'input_schema' => array( 'type' => 'object', 'properties' => array( 'post_id' => array( 'type' => 'integer' ), 'plugin' => array( 'type' => 'string' ), 'meta' => true ), 'required' => array( 'post_id', 'meta' ) ),
                'execute_callback' => array( $this, 'seo_meta_update' ),
                'output_schema' => $this->object_schema(),
                'permission_callback' => array( $this, 'can_write_content' ),
            ),
            'wpgpt/seo-settings-get' => array(
                'label' => __( 'SEO settings get', 'wpgpt-mcp-bridge' ),
                'description' => __( 'Lee una opción SEO permitida de Rank Math o Yoast.', 'wpgpt-mcp-bridge' ),
                'input_schema' => array( 'type' => 'object', 'properties' => array( 'plugin' => array( 'type' => 'string' ), 'option_name' => array( 'type' => 'string' ) ), 'required' => array( 'plugin', 'option_name' ) ),
                'execute_callback' => array( $this, 'seo_settings_get' ),
                'output_schema' => $this->object_schema(),
            ),
            'wpgpt/seo-settings-update' => array(
                'label' => __( 'SEO settings update', 'wpgpt-mcp-bridge' ),
                'description' => __( 'Actualiza de forma controlada una opción SEO permitida de Rank Math o Yoast.', 'wpgpt-mcp-bridge' ),
                'input_schema' => array( 'type' => 'object', 'properties' => array( 'plugin' => array( 'type' => 'string' ), 'option_name' => array( 'type' => 'string' ), 'settings_patch' => true ), 'required' => array( 'plugin', 'option_name', 'settings_patch' ) ),
                'execute_callback' => array( $this, 'seo_settings_update' ),
                'output_schema' => $this->object_schema(),
                'permission_callback' => array( $this, 'can_manage_site' ),
            ),
        );
    }

    public function seo_plugin_status(): array { return $this->service()->plugin_status(); }
    public function seo_analysis_get( array $input ) { return $this->service()->get_analysis( absint( $input['post_id'] ?? 0 ) ); }
    public function seo_meta_get( array $input ) { return $this->service()->get_post_meta( absint( $input['post_id'] ?? 0 ) ); }
    public function seo_meta_update( array $input ) { return $this->service()->update_post_meta( $input ); }
    public function seo_settings_get( array $input ) { return $this->service()->get_settings( sanitize_key( (string) ( $input['plugin'] ?? '' ) ), sanitize_key( (string) ( $input['option_name'] ?? '' ) ) ); }
    public function seo_settings_update( array $input ) { return $this->service()->update_settings( sanitize_key( (string) ( $input['plugin'] ?? '' ) ), sanitize_key( (string) ( $input['option_name'] ?? '' ) ), $input['settings_patch'] ?? array() ); }

    private function service(): SEO_Service { if ( null === $this->service ) { $this->service = new SEO_Service(); } return $this->service; }
}
