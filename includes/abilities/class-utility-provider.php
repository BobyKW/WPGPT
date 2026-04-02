<?php

namespace WPGPT\MCPBridge;

use WP_Error;

if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

class Utility_Provider extends Base_Ability_Provider {
    public function get_abilities(): array {
        return array(
            'wpgpt/options-get'         => array(
                'label'            => __( 'Get whitelisted options', 'wpgpt-mcp-bridge' ),
                'description'      => __( 'Devuelve opciones de WordPress permitidas por whitelist.', 'wpgpt-mcp-bridge' ),
                'input_schema'     => $this->options_input_schema(),
                'execute_callback' => array( $this, 'get_whitelisted_option' ),
                'output_schema'    => $this->object_schema(),
            ),
            'wpgpt/plugins-list-active' => array(
                'label'            => __( 'List active plugins', 'wpgpt-mcp-bridge' ),
                'description'      => __( 'Lista plugins activos con su versión.', 'wpgpt-mcp-bridge' ),
                'execute_callback' => array( $this, 'list_active_plugins' ),
                'output_schema'    => $this->object_schema(),
            ),
        );
    }

    public function get_whitelisted_option( array $input ): array|WP_Error {
        $name      = isset( $input['option_name'] ) ? sanitize_key( (string) $input['option_name'] ) : '';
        $whitelist = array( 'blogname', 'blogdescription', 'admin_email', 'timezone_string', 'permalink_structure', 'stylesheet', 'template' );

        if ( ! in_array( $name, $whitelist, true ) ) {
            return new WP_Error( 'wpgpt_option_not_allowed', __( 'La opción solicitada no está permitida.', 'wpgpt-mcp-bridge' ), array( 'status' => 403 ) );
        }

        return array(
            'option_name'  => $name,
            'option_value' => get_option( $name ),
        );
    }

    public function list_active_plugins(): array {
        if ( ! function_exists( 'get_plugins' ) ) {
            require_once ABSPATH . 'wp-admin/includes/plugin.php';
        }

        $plugins        = get_plugins();
        $active_plugins = (array) get_option( 'active_plugins', array() );
        $items          = array();

        foreach ( $active_plugins as $plugin_file ) {
            if ( ! isset( $plugins[ $plugin_file ] ) ) {
                continue;
            }

            $plugin  = $plugins[ $plugin_file ];
            $items[] = array(
                'name'    => $plugin['Name'] ?? $plugin_file,
                'file'    => $plugin_file,
                'version' => $plugin['Version'] ?? '',
                'author'  => wp_strip_all_tags( $plugin['Author'] ?? '' ),
            );
        }

        return array(
            'count' => count( $items ),
            'items' => $items,
        );
    }

    private function options_input_schema(): array {
        return array(
            'type'       => 'object',
            'properties' => array(
                'option_name' => array( 'type' => 'string' ),
            ),
            'required'   => array( 'option_name' ),
        );
    }
}
