<?php

namespace WPGPT\MCPBridge;

use WPGPT\MCPBridge\Navigation\Navigation_Service;

if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

class Navigation_Provider extends Base_Ability_Provider {
    private ?Navigation_Service $service = null;

    public function get_abilities(): array {
        $menu_item_schema = array(
            'type' => 'object',
            'properties' => array(
                'menu_id' => array( 'type' => 'integer' ),
                'menu_item_id' => array( 'type' => 'integer' ),
                'menu-item-title' => array( 'type' => 'string' ),
                'menu-item-url' => array( 'type' => 'string' ),
                'menu-item-description' => array( 'type' => 'string' ),
                'menu-item-status' => array( 'type' => 'string' ),
                'menu-item-position' => array( 'type' => 'integer' ),
                'menu-item-parent-id' => array( 'type' => 'integer' ),
                'object_id' => array( 'type' => 'integer' ),
                'object' => array( 'type' => 'string' ),
                'type' => array( 'type' => 'string' ),
            ),
        );
        return array(
            'wpgpt/menu-list' => array( 'label' => __( 'Menu list', 'wpgpt-mcp-bridge' ), 'description' => __( 'Lista los menús de navegación.', 'wpgpt-mcp-bridge' ), 'execute_callback' => array( $this, 'menu_list' ), 'output_schema' => $this->object_schema() ),
            'wpgpt/menu-create' => array( 'label' => __( 'Menu create', 'wpgpt-mcp-bridge' ), 'description' => __( 'Crea un menú de navegación.', 'wpgpt-mcp-bridge' ), 'input_schema' => array( 'type' => 'object', 'properties' => array( 'name' => array( 'type' => 'string' ) ), 'required' => array( 'name' ) ), 'execute_callback' => array( $this, 'menu_create' ), 'output_schema' => $this->object_schema(), 'permission_callback' => array( $this, 'can_manage_site' ) ),
            'wpgpt/menu-delete' => array( 'label' => __( 'Menu delete', 'wpgpt-mcp-bridge' ), 'description' => __( 'Elimina un menú de navegación.', 'wpgpt-mcp-bridge' ), 'input_schema' => array( 'type' => 'object', 'properties' => array( 'menu_id' => array( 'type' => 'integer' ) ), 'required' => array( 'menu_id' ) ), 'execute_callback' => array( $this, 'menu_delete' ), 'output_schema' => $this->object_schema(), 'permission_callback' => array( $this, 'can_delete_structure' ) ),
            'wpgpt/menu-item-create' => array( 'label' => __( 'Menu item create', 'wpgpt-mcp-bridge' ), 'description' => __( 'Crea un item de menú.', 'wpgpt-mcp-bridge' ), 'input_schema' => $menu_item_schema + array( 'required' => array( 'menu_id' ) ), 'execute_callback' => array( $this, 'menu_item_create' ), 'output_schema' => $this->object_schema(), 'permission_callback' => array( $this, 'can_manage_site' ) ),
            'wpgpt/menu-item-update' => array( 'label' => __( 'Menu item update', 'wpgpt-mcp-bridge' ), 'description' => __( 'Actualiza un item de menú.', 'wpgpt-mcp-bridge' ), 'input_schema' => $menu_item_schema + array( 'required' => array( 'menu_id', 'menu_item_id' ) ), 'execute_callback' => array( $this, 'menu_item_update' ), 'output_schema' => $this->object_schema(), 'permission_callback' => array( $this, 'can_manage_site' ) ),
            'wpgpt/menu-item-delete' => array( 'label' => __( 'Menu item delete', 'wpgpt-mcp-bridge' ), 'description' => __( 'Elimina un item de menú.', 'wpgpt-mcp-bridge' ), 'input_schema' => array( 'type' => 'object', 'properties' => array( 'menu_item_id' => array( 'type' => 'integer' ), 'force' => array( 'type' => 'boolean' ) ), 'required' => array( 'menu_item_id' ) ), 'execute_callback' => array( $this, 'menu_item_delete' ), 'output_schema' => $this->object_schema(), 'permission_callback' => array( $this, 'can_delete_structure' ) ),
            'wpgpt/nav-location-list' => array( 'label' => __( 'Nav location list', 'wpgpt-mcp-bridge' ), 'description' => __( 'Lista ubicaciones de menús del tema.', 'wpgpt-mcp-bridge' ), 'execute_callback' => array( $this, 'nav_location_list' ), 'output_schema' => $this->object_schema() ),
            'wpgpt/nav-location-assign' => array( 'label' => __( 'Nav location assign', 'wpgpt-mcp-bridge' ), 'description' => __( 'Asigna un menú a una ubicación del tema.', 'wpgpt-mcp-bridge' ), 'input_schema' => array( 'type' => 'object', 'properties' => array( 'location' => array( 'type' => 'string' ), 'menu_id' => array( 'type' => 'integer' ) ), 'required' => array( 'location', 'menu_id' ) ), 'execute_callback' => array( $this, 'nav_location_assign' ), 'output_schema' => $this->object_schema(), 'permission_callback' => array( $this, 'can_manage_site' ) ),
        );
    }
    private function service(): Navigation_Service { if ( null === $this->service ) { $this->service = new Navigation_Service(); } return $this->service; }
    public function menu_list() { return $this->service()->list_menus(); }
    public function menu_create( array $input ) { return $this->service()->create_menu( $input ); }
    public function menu_delete( array $input ) { return $this->service()->delete_menu( $input ); }
    public function menu_item_create( array $input ) { return $this->service()->create_menu_item( $input ); }
    public function menu_item_update( array $input ) { return $this->service()->update_menu_item( $input ); }
    public function menu_item_delete( array $input ) { return $this->service()->delete_menu_item( $input ); }
    public function nav_location_list() { return $this->service()->list_locations(); }
    public function nav_location_assign( array $input ) { return $this->service()->assign_location( $input ); }
}
