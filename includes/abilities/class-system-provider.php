<?php

namespace WPGPT\MCPBridge;

use WPGPT\MCPBridge\Support\Ability_Catalog;
use WP_Error;

if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

class System_Provider extends Base_Ability_Provider {
    public function get_abilities(): array {
        return array(
            'wpgpt/self-test'          => array(
                'label'            => __( 'Self test', 'wpgpt-mcp-bridge' ),
                'description'      => __( 'Confirma que el bridge está vivo y devuelve abilities registradas.', 'wpgpt-mcp-bridge' ),
                'execute_callback' => array( $this, 'self_test' ),
                'output_schema'    => $this->object_schema(),
            ),
            'wpgpt/site-info'          => array(
                'label'            => __( 'Site info', 'wpgpt-mcp-bridge' ),
                'description'      => __( 'Devuelve información básica del sitio, tema activo y versiones.', 'wpgpt-mcp-bridge' ),
                'execute_callback' => array( $this, 'get_site_info' ),
                'output_schema'    => $this->object_schema(),
            ),
            'wpgpt/discover-abilities' => array(
                'label'            => __( 'Discover abilities', 'wpgpt-mcp-bridge' ),
                'description'      => __( 'Lista abilities registradas accesibles para el usuario actual.', 'wpgpt-mcp-bridge' ),
                'execute_callback' => array( $this, 'discover_abilities' ),
                'output_schema'    => $this->object_schema(),
            ),
            'wpgpt/ability-info'       => array(
                'label'            => __( 'Ability info', 'wpgpt-mcp-bridge' ),
                'description'      => __( 'Devuelve metadatos básicos de una ability por nombre.', 'wpgpt-mcp-bridge' ),
                'input_schema'     => $this->ability_info_input_schema(),
                'execute_callback' => array( $this, 'ability_info' ),
                'output_schema'    => $this->object_schema(),
            ),
        );
    }

    public function self_test(): array {
        $declared = Ability_Catalog::declared_names();
        $runtime  = array_keys( Ability_Catalog::runtime_registry() );

        return array(
            'ok'                    => true,
            'plugin_version'        => WPGPT_MCP_BRIDGE_VERSION,
            'read_only'             => Security::get_read_only(),
            'current_user'          => wp_get_current_user()->user_login,
            'site_name'             => get_bloginfo( 'name' ),
            'timestamp_gmt'         => gmdate( 'c' ),
            'registered_abilities'  => $declared,
            'declared_count'        => count( $declared ),
            'runtime_registry_count'=> count( $runtime ),
            'missing_from_runtime'  => Ability_Catalog::missing_from_runtime(),
        );
    }

    public function get_site_info(): array {
        $theme = wp_get_theme();

        return array(
            'site_name'       => get_bloginfo( 'name' ),
            'site_url'        => home_url( '/' ),
            'admin_email'     => get_bloginfo( 'admin_email' ),
            'language'        => get_bloginfo( 'language' ),
            'timezone_string' => wp_timezone_string(),
            'wordpress'       => get_bloginfo( 'version' ),
            'php'             => PHP_VERSION,
            'theme'           => array(
                'name'    => $theme->get( 'Name' ),
                'version' => $theme->get( 'Version' ),
            ),
        );
    }

    public function discover_abilities(): array {
        $items = Ability_Catalog::visible_for_current_user();

        return array(
            'count' => count( $items ),
            'items' => $items,
        );
    }

    public function ability_info( array $input ): array|WP_Error {
        $name = isset( $input['name'] ) ? sanitize_text_field( (string) $input['name'] ) : '';
        if ( '' === $name ) {
            return new WP_Error( 'wpgpt_invalid_ability', __( 'Debes indicar el nombre de la ability.', 'wpgpt-mcp-bridge' ), array( 'status' => 400 ) );
        }

        return Ability_Catalog::info( $name );
    }

    private function ability_info_input_schema(): array {
        return array(
            'type'       => 'object',
            'properties' => array(
                'name' => array( 'type' => 'string' ),
            ),
            'required'   => array( 'name' ),
        );
    }
}
