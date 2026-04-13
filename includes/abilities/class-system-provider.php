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
            'wpgpt/system-query'   => array(
                'label'            => __( 'System query', 'wpgpt-mcp-bridge' ),
                'description'      => __( 'Resume el estado del bridge, del sitio y del catálogo de abilities.', 'wpgpt-mcp-bridge' ),
                'input_schema'     => $this->system_query_input_schema(),
                'execute_callback' => array( $this, 'system_query' ),
                'output_schema'    => $this->object_schema(),
            ),
            'wpgpt/system-inspect' => array(
                'label'            => __( 'System inspect', 'wpgpt-mcp-bridge' ),
                'description'      => __( 'Inspecciona el bridge, el sitio, el catálogo o una ability concreta.', 'wpgpt-mcp-bridge' ),
                'input_schema'     => $this->system_inspect_input_schema(),
                'execute_callback' => array( $this, 'system_inspect' ),
                'output_schema'    => $this->object_schema(),
            ),
            'wpgpt/system-apply'   => array(
                'label'            => __( 'System apply', 'wpgpt-mcp-bridge' ),
                'description'      => __( 'Ejecuta acciones seguras del sistema, con soporte dry_run.', 'wpgpt-mcp-bridge' ),
                'input_schema'     => $this->system_apply_input_schema(),
                'execute_callback' => array( $this, 'system_apply' ),
                'output_schema'    => $this->object_schema(),
            ),
        );
    }


    public function system_query( array $input = array() ): array {
        $site = $this->get_site_info();
        $self = $this->self_test();
        $abilities = $this->discover_abilities();

        return array(
            'summary' => array(
                'site_name' => $site['site_name'] ?? '',
                'site_url' => $site['site_url'] ?? '',
                'wordpress' => $site['wordpress'] ?? '',
                'php' => $site['php'] ?? '',
                'theme' => $site['theme']['name'] ?? '',
                'bridge_version' => $self['plugin_version'] ?? '',
                'read_only' => $self['read_only'] ?? false,
                'abilities_visible' => $abilities['count'] ?? 0,
            ),
            'items' => array(
                array( 'scope' => 'site', 'data' => $site ),
                array( 'scope' => 'bridge', 'data' => $self ),
            ),
            'warnings' => array(),
            'next_actions' => array( __( 'Usa wpgpt/system-inspect para profundizar en abilities o en el bridge.', 'wpgpt-mcp-bridge' ) ),
        );
    }

    public function system_inspect( array $input = array() ): array|WP_Error {
        $scope = isset( $input['scope'] ) ? sanitize_key( (string) $input['scope'] ) : 'bridge';
        if ( 'site' === $scope ) {
            return array( 'scope' => 'site', 'item' => $this->get_site_info() );
        }
        if ( 'abilities' === $scope ) {
            return array( 'scope' => 'abilities', 'item' => $this->discover_abilities() );
        }
        if ( 'ability' === $scope ) {
            return array( 'scope' => 'ability', 'item' => $this->ability_info( $input ) );
        }
        return array( 'scope' => 'bridge', 'item' => $this->self_test() );
    }

    public function system_apply( array $input = array() ): array|WP_Error {
        $action  = isset( $input['action'] ) ? sanitize_key( (string) $input['action'] ) : 'self_test';
        $dry_run = ! empty( $input['dry_run'] );

        if ( 'self_test' !== $action ) {
            return new WP_Error( 'wpgpt_invalid_action', __( 'Acción de sistema no soportada.', 'wpgpt-mcp-bridge' ), array( 'status' => 400 ) );
        }

        return array(
            'summary' => array(
                'action' => $action,
                'dry_run' => $dry_run,
                'executed' => $dry_run ? 0 : 1,
            ),
            'items' => array(
                array(
                    'action' => $action,
                    'status' => $dry_run ? 'dry_run' : 'ok',
                    'message' => $dry_run ? __( 'Acción validada, no ejecutada por dry_run.', 'wpgpt-mcp-bridge' ) : __( 'Self test ejecutado correctamente.', 'wpgpt-mcp-bridge' ),
                    'result' => $this->self_test(),
                ),
            ),
            'warnings' => array(),
            'blocked' => array(),
            'next_actions' => array(),
        );
    }

    private function system_query_input_schema(): array {
        return array( 'type' => 'object', 'additionalProperties' => false, 'properties' => array() );
    }

    private function system_inspect_input_schema(): array {
        return array(
            'type' => 'object',
            'additionalProperties' => false,
            'properties' => array(
                'scope' => array( 'type' => 'string', 'enum' => array( 'bridge', 'site', 'abilities', 'ability' ) ),
                'name'  => array( 'type' => 'string' ),
            ),
        );
    }

    private function system_apply_input_schema(): array {
        return array(
            'type' => 'object',
            'additionalProperties' => false,
            'properties' => array(
                'action' => array( 'type' => 'string', 'enum' => array( 'self_test' ) ),
                'dry_run' => array( 'type' => 'boolean' ),
            ),
            'required' => array( 'action' ),
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
