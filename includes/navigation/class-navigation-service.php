<?php

namespace WPGPT\MCPBridge\Navigation;

use WP_Error;

if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

class Navigation_Service {
    public function list_menus(): array {
        $menus = wp_get_nav_menus();
        $locations = (array) get_nav_menu_locations();
        $items = array();
        foreach ( $menus as $menu ) {
            $assigned = array();
            foreach ( $locations as $location => $term_id ) {
                if ( (int) $term_id === (int) $menu->term_id ) {
                    $assigned[] = $location;
                }
            }
            $items[] = array(
                'term_id' => (int) $menu->term_id,
                'name' => $menu->name,
                'slug' => $menu->slug,
                'count' => (int) $menu->count,
                'locations' => $assigned,
            );
        }
        return array( 'count' => count( $items ), 'items' => $items );
    }

    public function create_menu( array $input ): array|WP_Error {
        $name = sanitize_text_field( (string) ( $input['name'] ?? '' ) );
        if ( '' === $name ) {
            return new WP_Error( 'wpgpt_menu_name_required', __( 'Debes indicar un nombre de menú.', 'wpgpt-mcp-bridge' ), array( 'status' => 400 ) );
        }
        $term_id = wp_create_nav_menu( $name );
        if ( is_wp_error( $term_id ) ) {
            return $term_id;
        }
        return array( 'created' => true, 'term_id' => (int) $term_id, 'name' => $name );
    }

    public function delete_menu( array $input ): array|WP_Error {
        $menu_id = absint( $input['menu_id'] ?? 0 );
        if ( $menu_id <= 0 ) {
            return new WP_Error( 'wpgpt_menu_invalid', __( 'Debes indicar un menu_id válido.', 'wpgpt-mcp-bridge' ), array( 'status' => 400 ) );
        }
        $deleted = wp_delete_nav_menu( $menu_id );
        if ( ! $deleted ) {
            return new WP_Error( 'wpgpt_menu_delete_failed', __( 'No se pudo eliminar el menú indicado.', 'wpgpt-mcp-bridge' ), array( 'status' => 500 ) );
        }
        return array( 'deleted' => true, 'menu_id' => $menu_id );
    }

    public function create_menu_item( array $input ): array|WP_Error {
        $menu_id = absint( $input['menu_id'] ?? 0 );
        if ( $menu_id <= 0 ) {
            return new WP_Error( 'wpgpt_menu_invalid', __( 'Debes indicar un menu_id válido.', 'wpgpt-mcp-bridge' ), array( 'status' => 400 ) );
        }
        $args = $this->menu_item_args( $input );
        $item_id = wp_update_nav_menu_item( $menu_id, 0, $args );
        if ( is_wp_error( $item_id ) ) {
            return $item_id;
        }
        return array( 'created' => true, 'menu_id' => $menu_id, 'menu_item_id' => (int) $item_id );
    }

    public function update_menu_item( array $input ): array|WP_Error {
        $menu_id = absint( $input['menu_id'] ?? 0 );
        $item_id = absint( $input['menu_item_id'] ?? 0 );
        if ( $menu_id <= 0 || $item_id <= 0 ) {
            return new WP_Error( 'wpgpt_menu_item_invalid', __( 'Debes indicar menu_id y menu_item_id válidos.', 'wpgpt-mcp-bridge' ), array( 'status' => 400 ) );
        }
        $args = $this->menu_item_args( $input );
        $updated = wp_update_nav_menu_item( $menu_id, $item_id, $args );
        if ( is_wp_error( $updated ) ) {
            return $updated;
        }
        return array( 'updated' => true, 'menu_id' => $menu_id, 'menu_item_id' => $item_id );
    }

    public function delete_menu_item( array $input ): array|WP_Error {
        $item_id = absint( $input['menu_item_id'] ?? 0 );
        if ( $item_id <= 0 || 'nav_menu_item' !== get_post_type( $item_id ) ) {
            return new WP_Error( 'wpgpt_menu_item_invalid', __( 'Debes indicar un menu_item_id válido.', 'wpgpt-mcp-bridge' ), array( 'status' => 400 ) );
        }
        $deleted = wp_delete_post( $item_id, ! empty( $input['force'] ) );
        if ( ! $deleted ) {
            return new WP_Error( 'wpgpt_menu_item_delete_failed', __( 'No se pudo eliminar el item de menú indicado.', 'wpgpt-mcp-bridge' ), array( 'status' => 500 ) );
        }
        return array( 'deleted' => true, 'menu_item_id' => $item_id );
    }

    public function list_locations(): array {
        $registered = get_registered_nav_menus();
        $assigned = (array) get_nav_menu_locations();
        $items = array();
        foreach ( $registered as $location => $label ) {
            $menu_id = (int) ( $assigned[ $location ] ?? 0 );
            $menu = $menu_id ? wp_get_nav_menu_object( $menu_id ) : null;
            $items[] = array(
                'location' => $location,
                'label' => $label,
                'menu_id' => $menu_id,
                'menu_name' => $menu ? $menu->name : '',
            );
        }
        return array( 'count' => count( $items ), 'items' => $items );
    }

    public function assign_location( array $input ): array|WP_Error {
        $location = sanitize_key( (string) ( $input['location'] ?? '' ) );
        $menu_id = absint( $input['menu_id'] ?? 0 );
        $registered = get_registered_nav_menus();
        if ( '' === $location || ! isset( $registered[ $location ] ) ) {
            return new WP_Error( 'wpgpt_menu_location_invalid', __( 'Debes indicar una location válida.', 'wpgpt-mcp-bridge' ), array( 'status' => 400 ) );
        }
        if ( $menu_id > 0 && ! wp_get_nav_menu_object( $menu_id ) ) {
            return new WP_Error( 'wpgpt_menu_invalid', __( 'Debes indicar un menu_id válido.', 'wpgpt-mcp-bridge' ), array( 'status' => 400 ) );
        }
        $locations = (array) get_theme_mod( 'nav_menu_locations', array() );
        $locations[ $location ] = $menu_id;
        set_theme_mod( 'nav_menu_locations', $locations );
        return array( 'updated' => true, 'location' => $location, 'menu_id' => $menu_id );
    }

    private function menu_item_args( array $input ): array {
        $args = array();
        foreach ( array( 'menu-item-title', 'menu-item-url', 'menu-item-description', 'menu-item-attr-title', 'menu-item-target', 'menu-item-classes', 'menu-item-xfn', 'menu-item-status', 'menu-item-position', 'menu-item-parent-id' ) as $key ) {
            if ( array_key_exists( $key, $input ) ) {
                $args[ $key ] = is_numeric( $input[ $key ] ) ? absint( $input[ $key ] ) : sanitize_text_field( (string) $input[ $key ] );
            }
        }
        if ( isset( $input['object_id'] ) ) {
            $args['menu-item-object-id'] = absint( $input['object_id'] );
        }
        if ( isset( $input['object'] ) ) {
            $args['menu-item-object'] = sanitize_key( (string) $input['object'] );
        }
        if ( isset( $input['type'] ) ) {
            $args['menu-item-type'] = sanitize_key( (string) $input['type'] );
        }
        $args['menu-item-status'] = $args['menu-item-status'] ?? 'publish';
        $args['menu-item-type']   = $args['menu-item-type'] ?? 'custom';
        return $args;
    }
}
