<?php

namespace WPGPT\MCPBridge;

use WPGPT\MCPBridge\Plugins\Plugin_Manager_Service;
use WP_Error;

if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

class Plugin_Management_Provider extends Base_Ability_Provider {
    private ?Plugin_Manager_Service $service = null;

    public function get_abilities(): array {
        return array(
            'wpgpt/plugin-list-installed' => array(
                'label' => __( 'Plugin list installed', 'wpgpt-mcp-bridge' ),
                'description' => __( 'Lista plugins instalados.', 'wpgpt-mcp-bridge' ),
                'execute_callback' => array( $this, 'plugin_list_installed' ),
                'output_schema' => $this->object_schema(),
            ),
            'wpgpt/plugin-get' => array(
                'label' => __( 'Plugin get', 'wpgpt-mcp-bridge' ),
                'description' => __( 'Obtiene información de un plugin instalado.', 'wpgpt-mcp-bridge' ),
                'input_schema' => $this->plugin_file_schema(),
                'execute_callback' => array( $this, 'plugin_get' ),
                'output_schema' => $this->object_schema(),
            ),
            'wpgpt/plugin-update' => array(
                'label' => __( 'Plugin update', 'wpgpt-mcp-bridge' ),
                'description' => __( 'Actualiza un plugin instalado.', 'wpgpt-mcp-bridge' ),
                'input_schema' => $this->plugin_file_schema(),
                'execute_callback' => array( $this, 'plugin_update' ),
                'output_schema' => $this->object_schema(),
                'permission_callback' => array( $this, 'can_write_plugins' ),
            ),
            'wpgpt/plugin-install' => array(
                'label' => __( 'Plugin install', 'wpgpt-mcp-bridge' ),
                'description' => __( 'Instala un plugin del repositorio oficial por slug.', 'wpgpt-mcp-bridge' ),
                'input_schema' => $this->slug_schema(),
                'execute_callback' => array( $this, 'plugin_install' ),
                'output_schema' => $this->object_schema(),
                'permission_callback' => array( $this, 'can_write_plugins' ),
            ),
            'wpgpt/plugin-activate' => array(
                'label' => __( 'Plugin activate', 'wpgpt-mcp-bridge' ),
                'description' => __( 'Activa un plugin instalado por plugin_file o slug.', 'wpgpt-mcp-bridge' ),
                'input_schema' => $this->plugin_file_schema(),
                'execute_callback' => array( $this, 'plugin_activate' ),
                'output_schema' => $this->object_schema(),
                'permission_callback' => array( $this, 'can_write_plugins' ),
            ),
            'wpgpt/plugin-deactivate' => array(
                'label' => __( 'Plugin deactivate', 'wpgpt-mcp-bridge' ),
                'description' => __( 'Desactiva un plugin instalado por plugin_file o slug.', 'wpgpt-mcp-bridge' ),
                'input_schema' => $this->plugin_file_schema(),
                'execute_callback' => array( $this, 'plugin_deactivate' ),
                'output_schema' => $this->object_schema(),
                'permission_callback' => array( $this, 'can_write_plugins' ),
            ),
            'wpgpt/plugin-delete' => array(
                'label' => __( 'Plugin delete', 'wpgpt-mcp-bridge' ),
                'description' => __( 'Elimina un plugin instalado. Debe estar desactivado.', 'wpgpt-mcp-bridge' ),
                'input_schema' => $this->plugin_file_schema(),
                'execute_callback' => array( $this, 'plugin_delete' ),
                'output_schema' => $this->object_schema(),
                'permission_callback' => array( $this, 'can_delete_plugins' ),
            ),
        );
    }

    public function plugin_list_installed(): array { return $this->service()->list_installed(); }

    public function plugin_get( array $input ): array|WP_Error {
        $plugin_file = isset( $input['plugin_file'] ) ? sanitize_text_field( (string) $input['plugin_file'] ) : '';
        return $this->service()->get_plugin( $plugin_file );
    }

    public function plugin_update( array $input ): array|WP_Error {
        $plugin_file = isset( $input['plugin_file'] ) ? sanitize_text_field( (string) $input['plugin_file'] ) : '';
        return $this->service()->update( $plugin_file );
    }

    public function plugin_install( array $input ): array|WP_Error {
        $slug = isset( $input['slug'] ) ? sanitize_key( (string) $input['slug'] ) : '';
        return $this->service()->install( $slug );
    }

    public function plugin_activate( array $input ): array|WP_Error {
        $plugin_file = isset( $input['plugin_file'] ) ? sanitize_text_field( (string) $input['plugin_file'] ) : '';
        return $this->service()->activate( $plugin_file );
    }

    public function plugin_deactivate( array $input ): array|WP_Error {
        $plugin_file = isset( $input['plugin_file'] ) ? sanitize_text_field( (string) $input['plugin_file'] ) : '';
        return $this->service()->deactivate( $plugin_file );
    }

    public function plugin_delete( array $input ): array|WP_Error {
        $plugin_file = isset( $input['plugin_file'] ) ? sanitize_text_field( (string) $input['plugin_file'] ) : '';
        return $this->service()->delete( $plugin_file );
    }

    private function service(): Plugin_Manager_Service {
        if ( null === $this->service ) {
            $this->service = new Plugin_Manager_Service();
        }
        return $this->service;
    }

    private function slug_schema(): array {
        return array('type'=>'object','properties'=>array('slug'=>array('type'=>'string')),'required'=>array('slug'));
    }

    private function plugin_file_schema(): array {
        return array('type'=>'object','properties'=>array('plugin_file'=>array('type'=>'string')),'required'=>array('plugin_file'));
    }
}
