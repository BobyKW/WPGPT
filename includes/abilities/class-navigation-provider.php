<?php

namespace WPGPT\MCPBridge;

use WPGPT\MCPBridge\Navigation\Navigation_Service;

if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

class Navigation_Provider extends Base_Ability_Provider {
    private ?Navigation_Service $service = null;

    public function get_abilities(): array {
        return array(
            'wpgpt/navigation-query' => array(
                'label' => __( 'Navigation query', 'wpgpt-mcp-bridge' ),
                'description' => __( 'Lista, filtra y resume menús y ubicaciones de navegación.', 'wpgpt-mcp-bridge' ),
                'input_schema' => $this->navigation_query_schema(),
                'execute_callback' => array( $this, 'navigation_query' ),
                'output_schema' => $this->object_schema(),
            ),
            'wpgpt/navigation-inspect' => array(
                'label' => __( 'Navigation inspect', 'wpgpt-mcp-bridge' ),
                'description' => __( 'Inspecciona uno o varios menús o ubicaciones de navegación.', 'wpgpt-mcp-bridge' ),
                'input_schema' => $this->navigation_inspect_schema(),
                'execute_callback' => array( $this, 'navigation_inspect' ),
                'output_schema' => $this->object_schema(),
            ),
            'wpgpt/navigation-apply' => array(
                'label' => __( 'Navigation apply', 'wpgpt-mcp-bridge' ),
                'description' => __( 'Ejecuta acciones controladas sobre menús, items y ubicaciones, con soporte dry_run.', 'wpgpt-mcp-bridge' ),
                'input_schema' => $this->navigation_apply_schema(),
                'execute_callback' => array( $this, 'navigation_apply' ),
                'output_schema' => $this->object_schema(),
                'permission_callback' => array( $this, 'can_manage_site' ),
            ),
        );
    }

    private function service(): Navigation_Service {
        if ( null === $this->service ) {
            $this->service = new Navigation_Service();
        }
        return $this->service;
    }

    public function navigation_query( array $input = array() ) { return $this->service()->query( $input ); }
    public function navigation_inspect( array $input = array() ) { return $this->service()->inspect( $input ); }
    public function navigation_apply( array $input = array() ) { return $this->service()->apply( $input ); }

    private function navigation_query_schema(): array {
        return array(
            'type' => 'object',
            'additionalProperties' => false,
            'properties' => array(
                'scope' => array( 'type' => 'string', 'enum' => array( 'all', 'menus', 'locations' ) ),
                'search' => array( 'type' => 'string' ),
                'filters' => array(
                    'type' => 'object',
                    'additionalProperties' => false,
                    'properties' => array(
                        'menu_id' => array( 'type' => 'integer' ),
                        'slug' => array( 'type' => 'string' ),
                        'location' => array( 'type' => 'string' ),
                        'assigned' => array( 'type' => 'boolean' ),
                    ),
                ),
                'limit' => array( 'type' => 'integer', 'minimum' => 1, 'maximum' => 200 ),
                'offset' => array( 'type' => 'integer', 'minimum' => 0 ),
            ),
        );
    }

    private function navigation_inspect_schema(): array {
        return array(
            'type' => 'object',
            'additionalProperties' => false,
            'properties' => array(
                'menu_id' => array( 'type' => 'integer' ),
                'menu_ids' => array( 'type' => 'array', 'items' => array( 'type' => 'integer' ) ),
                'slug' => array( 'type' => 'string' ),
                'location' => array( 'type' => 'string' ),
                'include_items' => array( 'type' => 'boolean' ),
            ),
        );
    }

    private function navigation_apply_schema(): array {
        return array(
            'type' => 'object',
            'additionalProperties' => false,
            'properties' => array(
                'action' => array( 'type' => 'string', 'enum' => array( 'create_menu', 'update_menu', 'delete_menu', 'create_item', 'update_item', 'delete_item', 'assign_location' ) ),
                'dry_run' => array( 'type' => 'boolean' ),
                'targets' => array(
                    'type' => 'array',
                    'items' => array(
                        'type' => 'object',
                        'additionalProperties' => false,
                        'properties' => array(
                            'menu_id' => array( 'type' => 'integer' ),
                            'menu_item_id' => array( 'type' => 'integer' ),
                            'location' => array( 'type' => 'string' ),
                            'slug' => array( 'type' => 'string' ),
                        ),
                    ),
                ),
                'filters' => array(
                    'type' => 'object',
                    'additionalProperties' => false,
                    'properties' => array(
                        'menu_id' => array( 'type' => 'integer' ),
                        'slug' => array( 'type' => 'string' ),
                        'location' => array( 'type' => 'string' ),
                        'assigned' => array( 'type' => 'boolean' ),
                        'search' => array( 'type' => 'string' ),
                    ),
                ),
                'payload' => array(
                    'type' => 'object',
                    'additionalProperties' => true,
                    'properties' => array(
                        'name' => array( 'type' => 'string' ),
                        'menu_id' => array( 'type' => 'integer' ),
                        'menu_item_id' => array( 'type' => 'integer' ),
                        'location' => array( 'type' => 'string' ),
                        'menu-item-title' => array( 'type' => 'string' ),
                        'menu-item-url' => array( 'type' => 'string' ),
                        'menu-item-description' => array( 'type' => 'string' ),
                        'menu-item-attr-title' => array( 'type' => 'string' ),
                        'menu-item-target' => array( 'type' => 'string' ),
                        'menu-item-classes' => array( 'type' => 'string' ),
                        'menu-item-xfn' => array( 'type' => 'string' ),
                        'menu-item-status' => array( 'type' => 'string' ),
                        'menu-item-position' => array( 'type' => 'integer' ),
                        'menu-item-parent-id' => array( 'type' => 'integer' ),
                        'object_id' => array( 'type' => 'integer' ),
                        'object' => array( 'type' => 'string' ),
                        'type' => array( 'type' => 'string' ),
                        'force' => array( 'type' => 'boolean' ),
                    ),
                ),
            ),
            'required' => array( 'action' ),
        );
    }
}
