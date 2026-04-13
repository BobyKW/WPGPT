<?php

namespace WPGPT\MCPBridge\Navigation;

use WP_Error;

if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

class Navigation_Service {
    public function query( array $input = array() ): array {
        $scope   = isset( $input['scope'] ) ? sanitize_key( (string) $input['scope'] ) : 'all';
        $search  = isset( $input['search'] ) ? sanitize_text_field( (string) $input['search'] ) : '';
        $filters = isset( $input['filters'] ) && is_array( $input['filters'] ) ? $input['filters'] : array();
        $limit   = isset( $input['limit'] ) ? max( 1, min( 200, (int) $input['limit'] ) ) : 100;
        $offset  = isset( $input['offset'] ) ? max( 0, (int) $input['offset'] ) : 0;

        $menus     = $this->build_menu_inventory();
        $locations = $this->build_location_inventory();

        $items = array();
        if ( 'all' === $scope || 'menus' === $scope ) {
            foreach ( $menus as $menu ) {
                if ( $this->menu_matches_filters( $menu, $filters, $search ) ) {
                    $items[] = $menu + array( 'entity_type' => 'menu' );
                }
            }
        }
        if ( 'all' === $scope || 'locations' === $scope ) {
            foreach ( $locations as $location ) {
                if ( $this->location_matches_filters( $location, $filters, $search ) ) {
                    $items[] = $location + array( 'entity_type' => 'location' );
                }
            }
        }

        $paged = array_slice( $items, $offset, $limit );

        return array(
            'summary' => array(
                'scope' => $scope,
                'total_menus' => count( $menus ),
                'total_locations' => count( $locations ),
                'matched' => count( $items ),
                'returned' => count( $paged ),
                'offset' => $offset,
                'limit' => $limit,
            ),
            'items' => $paged,
            'warnings' => empty( $items ) ? array( __( 'No se han encontrado elementos de navegación con esos filtros.', 'wpgpt-mcp-bridge' ) ) : array(),
            'next_actions' => count( $items ) > $offset + count( $paged ) ? array( 'Usa offset=' . ( $offset + count( $paged ) ) . ' para continuar la consulta.' ) : array(),
        );
    }

    public function inspect( array $input = array() ): array|WP_Error {
        $targets = $this->collect_navigation_targets( $input );
        if ( empty( $targets ) ) {
            return new WP_Error( 'wpgpt_navigation_target_required', __( 'Debes indicar al menos un menú o una ubicación.', 'wpgpt-mcp-bridge' ), array( 'status' => 400 ) );
        }

        $include_items = ! empty( $input['include_items'] );
        $items = array();
        $warnings = array();
        foreach ( $targets as $target ) {
            if ( 'location' === ( $target['type'] ?? '' ) ) {
                $location = $this->resolve_location_target( $target );
                if ( ! $location ) {
                    $warnings[] = __( 'No se ha encontrado una de las ubicaciones solicitadas.', 'wpgpt-mcp-bridge' );
                    continue;
                }
                $items[] = $location + array(
                    'available_actions' => array( 'assign_location' ),
                    'risk_level' => 'low',
                );
                continue;
            }

            $menu = $this->resolve_menu_target( $target );
            if ( ! $menu ) {
                $warnings[] = __( 'No se ha encontrado uno de los menús solicitados.', 'wpgpt-mcp-bridge' );
                continue;
            }
            $item = $this->format_menu( $menu );
            if ( $include_items ) {
                $item['menu_items'] = $this->get_menu_items_data( (int) $menu->term_id );
            }
            $item['available_actions'] = array( 'update_menu', 'delete_menu', 'create_item', 'update_item', 'delete_item', 'assign_location' );
            $item['risk_level'] = ! empty( $item['locations'] ) ? 'medium' : 'low';
            $items[] = $item;
        }

        return array(
            'summary' => array( 'requested' => count( $targets ), 'inspected' => count( $items ) ),
            'items' => $items,
            'warnings' => array_values( array_unique( array_filter( $warnings ) ) ),
            'next_actions' => empty( $items ) ? array() : array( __( 'Usa wpgpt/navigation-apply con dry_run=true antes de ejecutar cambios.', 'wpgpt-mcp-bridge' ) ),
        );
    }

    public function apply( array $input = array() ): array|WP_Error {
        $action  = isset( $input['action'] ) ? sanitize_key( (string) $input['action'] ) : '';
        $dry_run = ! empty( $input['dry_run'] );
        $payload = isset( $input['payload'] ) && is_array( $input['payload'] ) ? $input['payload'] : array();

        $allowed = array( 'create_menu', 'update_menu', 'delete_menu', 'create_item', 'update_item', 'delete_item', 'assign_location' );
        if ( ! in_array( $action, $allowed, true ) ) {
            return new WP_Error( 'wpgpt_navigation_action_invalid', __( 'La acción indicada no es válida.', 'wpgpt-mcp-bridge' ), array( 'status' => 400 ) );
        }

        $targets = $this->resolve_navigation_apply_targets( $action, $input, $payload );
        if ( empty( $targets ) ) {
            return new WP_Error( 'wpgpt_navigation_apply_target_required', __( 'No se han resuelto objetivos de navegación para la acción indicada.', 'wpgpt-mcp-bridge' ), array( 'status' => 400 ) );
        }

        $items = array();
        $blocked = array();
        $executed = 0;
        foreach ( $targets as $target ) {
            $validation = $this->validate_navigation_action( $action, $target, $payload );
            if ( ! empty( $validation ) ) {
                $blocked[] = array( 'target' => $target, 'reasons' => $validation );
                continue;
            }
            if ( $dry_run ) {
                $items[] = array( 'target' => $target, 'status' => 'dry_run', 'action' => $action, 'message' => __( 'Acción validada, no ejecutada por dry_run.', 'wpgpt-mcp-bridge' ) );
                continue;
            }

            switch ( $action ) {
                case 'create_menu':
                    $result = $this->create_menu( $payload );
                    break;
                case 'update_menu':
                    $result = $this->rename_menu( array( 'menu_id' => (int) $target['menu_id'], 'name' => (string) ( $payload['name'] ?? '' ) ) );
                    break;
                case 'delete_menu':
                    $result = $this->delete_menu( array( 'menu_id' => (int) $target['menu_id'] ) );
                    break;
                case 'create_item':
                    $result = $this->create_menu_item( $payload + array( 'menu_id' => (int) $target['menu_id'] ) );
                    break;
                case 'update_item':
                    $result = $this->update_menu_item( $payload + array( 'menu_id' => (int) $target['menu_id'], 'menu_item_id' => (int) $target['menu_item_id'] ) );
                    break;
                case 'delete_item':
                    $result = $this->delete_menu_item( array( 'menu_item_id' => (int) $target['menu_item_id'], 'force' => ! empty( $payload['force'] ) ) );
                    break;
                case 'assign_location':
                    $result = $this->assign_location( array( 'location' => (string) $target['location'], 'menu_id' => (int) $target['menu_id'] ) );
                    break;
                default:
                    $result = new WP_Error( 'wpgpt_navigation_action_invalid', __( 'La acción indicada no es válida.', 'wpgpt-mcp-bridge' ), array( 'status' => 400 ) );
            }

            if ( is_wp_error( $result ) ) {
                $blocked[] = array( 'target' => $target, 'reasons' => array( $result->get_error_message() ) );
                continue;
            }

            $executed++;
            $items[] = array( 'target' => $target, 'status' => 'applied', 'action' => $action, 'result' => $result );
        }

        return array(
            'summary' => array( 'action' => $action, 'dry_run' => $dry_run, 'resolved_targets' => count( $targets ), 'executed' => $executed, 'blocked' => count( $blocked ) ),
            'items' => $items,
            'warnings' => array(),
            'blocked' => $blocked,
            'next_actions' => $dry_run ? array( __( 'Repite la misma llamada con dry_run=false para aplicar los cambios validados.', 'wpgpt-mcp-bridge' ) ) : array(),
        );
    }

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

    private function build_menu_inventory(): array {
        $menus = wp_get_nav_menus();
        $items = array();
        foreach ( $menus as $menu ) {
            $items[] = $this->format_menu( $menu );
        }
        return $items;
    }

    private function build_location_inventory(): array {
        return $this->list_locations()['items'];
    }

    private function format_menu( $menu ): array {
        $locations = (array) get_nav_menu_locations();
        $assigned = array();
        foreach ( $locations as $location => $term_id ) {
            if ( (int) $term_id === (int) $menu->term_id ) {
                $assigned[] = $location;
            }
        }
        return array(
            'menu_id' => (int) $menu->term_id,
            'name' => (string) $menu->name,
            'slug' => (string) $menu->slug,
            'count' => (int) $menu->count,
            'locations' => $assigned,
        );
    }

    private function get_menu_items_data( int $menu_id ): array {
        $items = wp_get_nav_menu_items( $menu_id, array( 'post_status' => 'publish,draft' ) );
        $data = array();
        foreach ( is_array( $items ) ? $items : array() as $item ) {
            $data[] = array(
                'menu_item_id' => (int) $item->ID,
                'title' => (string) $item->title,
                'url' => (string) $item->url,
                'type' => (string) $item->type,
                'object' => (string) $item->object,
                'object_id' => (int) $item->object_id,
                'parent' => (int) $item->menu_item_parent,
                'position' => (int) $item->menu_order,
                'status' => (string) $item->post_status,
            );
        }
        return $data;
    }

    private function menu_matches_filters( array $item, array $filters, string $search ): bool {
        if ( isset( $filters['menu_id'] ) && (int) $item['menu_id'] !== (int) $filters['menu_id'] ) {
            return false;
        }
        if ( isset( $filters['slug'] ) && strtolower( (string) $item['slug'] ) !== strtolower( (string) $filters['slug'] ) ) {
            return false;
        }
        if ( isset( $filters['assigned'] ) && (bool) $filters['assigned'] !== ! empty( $item['locations'] ) ) {
            return false;
        }
        if ( '' !== $search ) {
            $haystack = strtolower( implode( ' ', array( $item['name'], $item['slug'] ) ) );
            if ( false === strpos( $haystack, strtolower( $search ) ) ) {
                return false;
            }
        }
        return true;
    }

    private function location_matches_filters( array $item, array $filters, string $search ): bool {
        if ( isset( $filters['location'] ) && strtolower( (string) $item['location'] ) !== strtolower( (string) $filters['location'] ) ) {
            return false;
        }
        if ( isset( $filters['menu_id'] ) && (int) $item['menu_id'] !== (int) $filters['menu_id'] ) {
            return false;
        }
        if ( isset( $filters['assigned'] ) && (bool) $filters['assigned'] !== ( (int) $item['menu_id'] > 0 ) ) {
            return false;
        }
        if ( '' !== $search ) {
            $haystack = strtolower( implode( ' ', array( $item['location'], $item['label'], $item['menu_name'] ) ) );
            if ( false === strpos( $haystack, strtolower( $search ) ) ) {
                return false;
            }
        }
        return true;
    }

    private function collect_navigation_targets( array $input ): array {
        $targets = array();
        if ( ! empty( $input['menu_id'] ) ) {
            $targets[] = array( 'type' => 'menu', 'menu_id' => absint( $input['menu_id'] ) );
        }
        if ( ! empty( $input['menu_ids'] ) && is_array( $input['menu_ids'] ) ) {
            foreach ( $input['menu_ids'] as $menu_id ) {
                $targets[] = array( 'type' => 'menu', 'menu_id' => absint( $menu_id ) );
            }
        }
        if ( ! empty( $input['slug'] ) ) {
            $targets[] = array( 'type' => 'menu', 'slug' => sanitize_title( (string) $input['slug'] ) );
        }
        if ( ! empty( $input['location'] ) ) {
            $targets[] = array( 'type' => 'location', 'location' => sanitize_key( (string) $input['location'] ) );
        }
        return array_values( array_filter( $targets ) );
    }

    private function resolve_menu_target( array $target ) {
        if ( ! empty( $target['menu_id'] ) ) {
            return wp_get_nav_menu_object( absint( $target['menu_id'] ) );
        }
        if ( ! empty( $target['slug'] ) ) {
            return wp_get_nav_menu_object( sanitize_title( (string) $target['slug'] ) );
        }
        return false;
    }

    private function resolve_location_target( array $target ): array|false {
        $location = sanitize_key( (string) ( $target['location'] ?? '' ) );
        foreach ( $this->build_location_inventory() as $item ) {
            if ( $item['location'] === $location ) {
                return $item;
            }
        }
        return false;
    }

    private function resolve_navigation_apply_targets( string $action, array $input, array $payload ): array {
        if ( 'create_menu' === $action ) {
            return array( array( 'name' => sanitize_text_field( (string) ( $payload['name'] ?? '' ) ) ) );
        }

        $targets = array();
        if ( ! empty( $input['targets'] ) && is_array( $input['targets'] ) ) {
            foreach ( $input['targets'] as $target ) {
                if ( ! is_array( $target ) ) {
                    continue;
                }
                if ( ! empty( $target['location'] ) && 'assign_location' === $action ) {
                    $menu_id = isset( $target['menu_id'] ) ? absint( $target['menu_id'] ) : absint( $payload['menu_id'] ?? 0 );
                    $targets[] = array( 'location' => sanitize_key( (string) $target['location'] ), 'menu_id' => $menu_id );
                    continue;
                }
                if ( ! empty( $target['menu_item_id'] ) && in_array( $action, array( 'update_item', 'delete_item' ), true ) ) {
                    $item = wp_setup_nav_menu_item( get_post( absint( $target['menu_item_id'] ) ) );
                    if ( $item ) {
                        $targets[] = array( 'menu_item_id' => (int) $item->ID, 'menu_id' => (int) $item->menu_item_parent ? absint( $target['menu_id'] ?? 0 ) : absint( $target['menu_id'] ?? 0 ) );
                    }
                    continue;
                }
                if ( ! empty( $target['menu_id'] ) ) {
                    $targets[] = array( 'menu_id' => absint( $target['menu_id'] ) );
                    continue;
                }
                if ( ! empty( $target['slug'] ) ) {
                    $menu = wp_get_nav_menu_object( sanitize_title( (string) $target['slug'] ) );
                    if ( $menu ) {
                        $targets[] = array( 'menu_id' => (int) $menu->term_id );
                    }
                }
            }
        }

        if ( empty( $targets ) && in_array( $action, array( 'update_menu', 'delete_menu', 'create_item' ), true ) ) {
            if ( ! empty( $payload['menu_id'] ) ) {
                $targets[] = array( 'menu_id' => absint( $payload['menu_id'] ) );
            }
        }
        if ( empty( $targets ) && in_array( $action, array( 'update_item', 'delete_item' ), true ) ) {
            if ( ! empty( $payload['menu_item_id'] ) ) {
                $targets[] = array( 'menu_item_id' => absint( $payload['menu_item_id'] ), 'menu_id' => absint( $payload['menu_id'] ?? 0 ) );
            }
        }
        if ( empty( $targets ) && 'assign_location' === $action ) {
            $location = sanitize_key( (string) ( $payload['location'] ?? '' ) );
            $menu_id  = absint( $payload['menu_id'] ?? 0 );
            if ( '' !== $location ) {
                $targets[] = array( 'location' => $location, 'menu_id' => $menu_id );
            }
        }
        if ( empty( $targets ) ) {
            $filters = isset( $input['filters'] ) && is_array( $input['filters'] ) ? $input['filters'] : array();
            $search = isset( $filters['search'] ) ? sanitize_text_field( (string) $filters['search'] ) : '';
            if ( 'assign_location' === $action ) {
                foreach ( $this->build_location_inventory() as $item ) {
                    if ( $this->location_matches_filters( $item, $filters, $search ) ) {
                        $targets[] = array( 'location' => $item['location'], 'menu_id' => absint( $payload['menu_id'] ?? $item['menu_id'] ) );
                    }
                }
            } else {
                foreach ( $this->build_menu_inventory() as $item ) {
                    if ( $this->menu_matches_filters( $item, $filters, $search ) ) {
                        $targets[] = array( 'menu_id' => (int) $item['menu_id'] );
                    }
                }
            }
        }

        return $targets;
    }

    private function validate_navigation_action( string $action, array $target, array $payload ): array {
        $reasons = array();
        if ( 'create_menu' === $action ) {
            if ( empty( $payload['name'] ) ) {
                $reasons[] = __( 'Para crear un menú debes indicar payload.name.', 'wpgpt-mcp-bridge' );
            }
            return $reasons;
        }
        if ( in_array( $action, array( 'update_menu', 'delete_menu', 'create_item' ), true ) ) {
            $menu_id = isset( $target['menu_id'] ) ? absint( $target['menu_id'] ) : 0;
            if ( $menu_id <= 0 || ! wp_get_nav_menu_object( $menu_id ) ) {
                $reasons[] = __( 'No se ha encontrado el menú objetivo.', 'wpgpt-mcp-bridge' );
                return $reasons;
            }
            if ( 'delete_menu' === $action ) {
                foreach ( (array) get_nav_menu_locations() as $location => $assigned_menu_id ) {
                    if ( (int) $assigned_menu_id === $menu_id ) {
                        $reasons[] = sprintf( __( 'No se debe eliminar un menú asignado a la ubicación %s.', 'wpgpt-mcp-bridge' ), $location );
                        break;
                    }
                }
            }
            if ( 'update_menu' === $action && empty( $payload['name'] ) ) {
                $reasons[] = __( 'Para renombrar un menú debes indicar payload.name.', 'wpgpt-mcp-bridge' );
            }
            if ( 'create_item' === $action && empty( $payload['menu-item-title'] ) && empty( $payload['object_id'] ) ) {
                $reasons[] = __( 'Para crear un item debes indicar al menos payload.menu-item-title o payload.object_id.', 'wpgpt-mcp-bridge' );
            }
            return $reasons;
        }
        if ( in_array( $action, array( 'update_item', 'delete_item' ), true ) ) {
            $menu_item_id = isset( $target['menu_item_id'] ) ? absint( $target['menu_item_id'] ) : 0;
            if ( $menu_item_id <= 0 || 'nav_menu_item' !== get_post_type( $menu_item_id ) ) {
                $reasons[] = __( 'No se ha encontrado el item de menú objetivo.', 'wpgpt-mcp-bridge' );
                return $reasons;
            }
            if ( 'update_item' === $action && empty( $payload ) ) {
                $reasons[] = __( 'Para actualizar un item debes indicar payload con cambios.', 'wpgpt-mcp-bridge' );
            }
            return $reasons;
        }
        if ( 'assign_location' === $action ) {
            $location = sanitize_key( (string) ( $target['location'] ?? '' ) );
            if ( '' === $location || ! isset( get_registered_nav_menus()[ $location ] ) ) {
                $reasons[] = __( 'No se ha encontrado la ubicación objetivo.', 'wpgpt-mcp-bridge' );
            }
            if ( ! empty( $target['menu_id'] ) && ! wp_get_nav_menu_object( (int) $target['menu_id'] ) ) {
                $reasons[] = __( 'No se ha encontrado el menú a asignar.', 'wpgpt-mcp-bridge' );
            }
        }
        return $reasons;
    }

    private function rename_menu( array $input ): array|WP_Error {
        $menu_id = absint( $input['menu_id'] ?? 0 );
        $name    = sanitize_text_field( (string) ( $input['name'] ?? '' ) );
        if ( $menu_id <= 0 || '' === $name ) {
            return new WP_Error( 'wpgpt_menu_update_invalid', __( 'Debes indicar menu_id y name válidos.', 'wpgpt-mcp-bridge' ), array( 'status' => 400 ) );
        }
        $updated = wp_update_nav_menu_object( $menu_id, array( 'menu-name' => $name ) );
        if ( is_wp_error( $updated ) ) {
            return $updated;
        }
        return array( 'updated' => true, 'menu_id' => $menu_id, 'name' => $name );
    }
}
