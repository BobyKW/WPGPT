<?php

namespace WPGPT\MCPBridge\Users;

use WP_Error;

if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

class User_Manager_Service {
    public function query( array $input = array() ): array|WP_Error {
        $filters = isset( $input['filters'] ) && is_array( $input['filters'] ) ? $input['filters'] : array();
        $search = isset( $input['search'] ) ? sanitize_text_field( (string) $input['search'] ) : '';
        $limit = isset( $input['limit'] ) ? max( 1, min( 200, (int) $input['limit'] ) ) : 100;
        $offset = isset( $input['offset'] ) ? max( 0, (int) $input['offset'] ) : 0;
        $items = $this->build_user_inventory();
        $matched = array_values( array_filter( $items, fn( $item ) => $this->user_matches_filters( $item, $filters, $search ) ) );
        $paged = array_slice( $matched, $offset, $limit );
        return array(
            'summary' => array(
                'total_users' => count( $items ),
                'matched' => count( $matched ),
                'returned' => count( $paged ),
                'roles' => $this->count_by_role( $matched ),
                'offset' => $offset,
                'limit' => $limit,
            ),
            'items' => $paged,
            'warnings' => empty( $matched ) ? array( __( 'No se han encontrado usuarios con esos filtros.', 'wpgpt-mcp-bridge' ) ) : array(),
            'next_actions' => count( $matched ) > $offset + count( $paged ) ? array( 'Usa offset=' . ( $offset + count( $paged ) ) . ' para continuar la consulta.' ) : array(),
        );
    }

    public function inspect( array $input = array() ): array|WP_Error {
        $targets = $this->collect_user_targets( $input );
        if ( empty( $targets ) ) {
            return new WP_Error( 'wpgpt_user_target_required', __( 'Debes indicar al menos un usuario por user_id, login o email.', 'wpgpt-mcp-bridge' ), array( 'status' => 400 ) );
        }
        $items = array();
        $warnings = array();
        foreach ( $targets as $target ) {
            $user = $this->resolve_user_target( $target );
            if ( ! $user ) {
                $warnings[] = __( 'No se ha encontrado uno de los usuarios solicitados.', 'wpgpt-mcp-bridge' );
                continue;
            }
            $items[] = $this->format_user( $user->ID, true ) + array(
                'capabilities' => array_keys( array_filter( (array) $user->allcaps ) ),
                'available_actions' => array( 'update', 'delete' ),
                'risk_level' => in_array( 'administrator', (array) $user->roles, true ) ? 'medium' : 'low',
            );
        }
        return array(
            'summary' => array( 'requested' => count( $targets ), 'inspected' => count( $items ) ),
            'items' => $items,
            'warnings' => array_values( array_unique( array_filter( $warnings ) ) ),
            'next_actions' => empty( $items ) ? array() : array( __( 'Usa wpgpt/users-apply con dry_run=true antes de ejecutar cambios.', 'wpgpt-mcp-bridge' ) ),
        );
    }

    public function apply( array $input = array() ): array|WP_Error {
        $action = isset( $input['action'] ) ? sanitize_key( (string) $input['action'] ) : '';
        $dry_run = ! empty( $input['dry_run'] );
        $payload = isset( $input['payload'] ) && is_array( $input['payload'] ) ? $input['payload'] : array();
        if ( ! in_array( $action, array( 'create', 'update', 'delete' ), true ) ) {
            return new WP_Error( 'wpgpt_user_action_invalid', __( 'La acción indicada no es válida.', 'wpgpt-mcp-bridge' ), array( 'status' => 400 ) );
        }
        $targets = $this->resolve_user_apply_targets( $action, $input, $payload );
        if ( empty( $targets ) ) {
            return new WP_Error( 'wpgpt_user_apply_target_required', __( 'No se han resuelto usuarios objetivo para la acción indicada.', 'wpgpt-mcp-bridge' ), array( 'status' => 400 ) );
        }
        $items = array();
        $blocked = array();
        $executed = 0;
        foreach ( $targets as $target ) {
            $validation = $this->validate_user_action( $action, $target, $payload );
            if ( ! empty( $validation ) ) {
                $blocked[] = array( 'target' => $target, 'reasons' => $validation );
                continue;
            }
            if ( $dry_run ) {
                $items[] = array( 'target' => $target, 'status' => 'dry_run', 'action' => $action, 'message' => __( 'Acción validada, no ejecutada por dry_run.', 'wpgpt-mcp-bridge' ) );
                continue;
            }
            if ( 'create' === $action ) {
                $result = $this->create_user_data( $payload );
            } elseif ( 'update' === $action ) {
                $data = $payload + array( 'user_id' => (int) $target['user_id'] );
                $result = $this->update_user_data( $data );
            } else {
                $data = array( 'user_id' => (int) $target['user_id'] );
                if ( isset( $target['reassign'] ) ) { $data['reassign'] = (int) $target['reassign']; }
                if ( isset( $payload['reassign'] ) ) { $data['reassign'] = (int) $payload['reassign']; }
                $result = $this->delete_user_data( $data );
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

    public function list_users( array $input ): array {
        $limit  = isset( $input['limit'] ) ? max( 1, min( 100, (int) $input['limit'] ) ) : 20;
        $search = isset( $input['search'] ) ? sanitize_text_field( (string) $input['search'] ) : '';

        $users = get_users(
            array(
                'number'  => $limit,
                'search'  => '' !== $search ? '*' . esc_attr( $search ) . '*' : '',
                'fields'  => array( 'ID', 'user_login', 'user_email', 'display_name', 'user_registered' ),
                'orderby' => 'ID',
                'order'   => 'DESC',
            )
        );

        $items = array();
        foreach ( $users as $user ) {
            $items[] = $this->format_user( $user->ID );
        }

        return array( 'count' => count( $items ), 'items' => $items );
    }

    public function get_user_data( array $input ): array|WP_Error {
        $user_id = isset( $input['user_id'] ) ? absint( $input['user_id'] ) : 0;
        if ( $user_id <= 0 ) {
            return new WP_Error( 'wpgpt_invalid_user', __( 'Debes indicar un usuario válido.', 'wpgpt-mcp-bridge' ), array( 'status' => 404 ) );
        }
        $user = get_user_by( 'id', $user_id );
        if ( ! $user ) {
            return new WP_Error( 'wpgpt_user_not_found', __( 'No se ha encontrado el usuario indicado.', 'wpgpt-mcp-bridge' ), array( 'status' => 404 ) );
        }
        return $this->format_user( $user_id, true );
    }

    public function create_user_data( array $input ): array|WP_Error {
        $userdata = array(
            'user_login'   => sanitize_user( (string) ( $input['user_login'] ?? '' ), true ),
            'user_email'   => sanitize_email( (string) ( $input['user_email'] ?? '' ) ),
            'display_name' => sanitize_text_field( (string) ( $input['display_name'] ?? '' ) ),
            'role'         => sanitize_key( (string) ( $input['role'] ?? 'subscriber' ) ),
        );
        if ( '' === $userdata['user_login'] || '' === $userdata['user_email'] ) {
            return new WP_Error( 'wpgpt_invalid_user_data', __( 'Debes indicar al menos user_login y user_email.', 'wpgpt-mcp-bridge' ), array( 'status' => 400 ) );
        }
        $password = isset( $input['user_pass'] ) && '' !== (string) $input['user_pass'] ? (string) $input['user_pass'] : wp_generate_password( 20, true, true );
        $userdata['user_pass'] = $password;
        $user_id = wp_insert_user( $userdata );
        if ( is_wp_error( $user_id ) ) {
            return $user_id;
        }
        return array( 'created' => true, 'user_id' => (int) $user_id, 'password' => $password, 'item' => $this->format_user( (int) $user_id, true ) );
    }

    public function update_user_data( array $input ): array|WP_Error {
        $user_id = isset( $input['user_id'] ) ? absint( $input['user_id'] ) : 0;
        if ( $user_id <= 0 || ! get_user_by( 'id', $user_id ) ) {
            return new WP_Error( 'wpgpt_user_not_found', __( 'No se ha encontrado el usuario indicado.', 'wpgpt-mcp-bridge' ), array( 'status' => 404 ) );
        }
        $userdata = array( 'ID' => $user_id );
        foreach ( array( 'user_email', 'display_name', 'role', 'user_pass' ) as $field ) {
            if ( ! array_key_exists( $field, $input ) ) {
                continue;
            }
            $value = $input[ $field ];
            if ( 'user_email' === $field ) {
                $userdata[ $field ] = sanitize_email( (string) $value );
            } elseif ( 'role' === $field ) {
                $userdata[ $field ] = sanitize_key( (string) $value );
            } else {
                $userdata[ $field ] = sanitize_text_field( (string) $value );
            }
        }
        $updated = wp_update_user( $userdata );
        if ( is_wp_error( $updated ) ) {
            return $updated;
        }
        return array( 'updated' => true, 'user_id' => $user_id, 'item' => $this->format_user( $user_id, true ) );
    }

    public function delete_user_data( array $input ): array|WP_Error {
        if ( ! function_exists( 'wp_delete_user' ) ) {
            require_once ABSPATH . 'wp-admin/includes/user.php';
        }
        $user_id = isset( $input['user_id'] ) ? absint( $input['user_id'] ) : 0;
        if ( $user_id <= 0 || ! get_user_by( 'id', $user_id ) ) {
            return new WP_Error( 'wpgpt_user_not_found', __( 'No se ha encontrado el usuario indicado.', 'wpgpt-mcp-bridge' ), array( 'status' => 404 ) );
        }
        $reassign = isset( $input['reassign'] ) ? absint( $input['reassign'] ) : null;
        $deleted  = wp_delete_user( $user_id, $reassign );
        if ( ! $deleted ) {
            return new WP_Error( 'wpgpt_user_delete_failed', __( 'No se pudo eliminar el usuario indicado.', 'wpgpt-mcp-bridge' ), array( 'status' => 500 ) );
        }
        return array( 'deleted' => true, 'user_id' => $user_id, 'reassign' => $reassign );
    }


    public function create_role_data( array $input ): array|WP_Error {
        $role = sanitize_key( (string) ( $input['role'] ?? '' ) );
        $label = sanitize_text_field( (string) ( $input['label'] ?? '' ) );
        if ( '' === $role || '' === $label ) {
            return new WP_Error( 'wpgpt_role_invalid', __( 'Debes indicar role y label válidos.', 'wpgpt-mcp-bridge' ), array( 'status' => 400 ) );
        }
        if ( get_role( $role ) ) {
            return new WP_Error( 'wpgpt_role_exists', __( 'Ese rol ya existe.', 'wpgpt-mcp-bridge' ), array( 'status' => 409 ) );
        }
        $caps = array();
        foreach ( (array) ( $input['capabilities'] ?? array() ) as $cap ) {
            $cap = sanitize_key( (string) $cap );
            if ( '' !== $cap ) { $caps[ $cap ] = true; }
        }
        add_role( $role, $label, $caps );
        $created = get_role( $role );
        if ( ! $created ) {
            return new WP_Error( 'wpgpt_role_create_failed', __( 'No se pudo crear el rol.', 'wpgpt-mcp-bridge' ), array( 'status' => 500 ) );
        }
        return array( 'created' => true, 'role' => $role, 'label' => $label, 'capabilities' => array_keys( $caps ) );
    }

    public function delete_role_data( array $input ): array|WP_Error {
        $role = sanitize_key( (string) ( $input['role'] ?? '' ) );
        if ( '' === $role ) {
            return new WP_Error( 'wpgpt_role_invalid', __( 'Debes indicar un role válido.', 'wpgpt-mcp-bridge' ), array( 'status' => 400 ) );
        }
        if ( in_array( $role, array( 'administrator', 'editor', 'author', 'contributor', 'subscriber' ), true ) ) {
            return new WP_Error( 'wpgpt_role_protected', __( 'No se pueden eliminar los roles nativos protegidos.', 'wpgpt-mcp-bridge' ), array( 'status' => 403 ) );
        }
        if ( ! get_role( $role ) ) {
            return new WP_Error( 'wpgpt_role_not_found', __( 'No se ha encontrado el rol indicado.', 'wpgpt-mcp-bridge' ), array( 'status' => 404 ) );
        }
        remove_role( $role );
        return array( 'deleted' => true, 'role' => $role );
    }

    public function grant_capability( array $input ): array|WP_Error {
        $role = sanitize_key( (string) ( $input['role'] ?? '' ) );
        $cap  = sanitize_key( (string) ( $input['capability'] ?? '' ) );
        $role_obj = $role ? get_role( $role ) : null;
        if ( ! $role_obj || '' === $cap ) {
            return new WP_Error( 'wpgpt_role_cap_invalid', __( 'Debes indicar role y capability válidos.', 'wpgpt-mcp-bridge' ), array( 'status' => 400 ) );
        }
        $role_obj->add_cap( $cap, true );
        return array( 'updated' => true, 'role' => $role, 'capability' => $cap, 'granted' => true );
    }

    public function revoke_capability( array $input ): array|WP_Error {
        $role = sanitize_key( (string) ( $input['role'] ?? '' ) );
        $cap  = sanitize_key( (string) ( $input['capability'] ?? '' ) );
        $role_obj = $role ? get_role( $role ) : null;
        if ( ! $role_obj || '' === $cap ) {
            return new WP_Error( 'wpgpt_role_cap_invalid', __( 'Debes indicar role y capability válidos.', 'wpgpt-mcp-bridge' ), array( 'status' => 400 ) );
        }
        $role_obj->remove_cap( $cap );
        return array( 'updated' => true, 'role' => $role, 'capability' => $cap, 'granted' => false );
    }

    public function list_roles(): array {
        global $wp_roles;
        if ( ! isset( $wp_roles ) ) {
            $wp_roles = wp_roles();
        }
        $items = array();
        foreach ( (array) $wp_roles->roles as $key => $role ) {
            $items[] = array(
                'role'         => (string) $key,
                'label'        => translate_user_role( $role['name'] ?? $key ),
                'capabilities' => array_keys( array_filter( (array) ( $role['capabilities'] ?? array() ) ) ),
            );
        }
        return array( 'count' => count( $items ), 'items' => $items );
    }


    private function build_user_inventory(): array {
        $users = get_users( array( 'number' => 500, 'orderby' => 'ID', 'order' => 'DESC' ) );
        $items = array();
        foreach ( $users as $user ) {
            $items[] = $this->format_user( $user->ID, true );
        }
        return $items;
    }

    private function user_matches_filters( array $item, array $filters, string $search ): bool {
        if ( isset( $filters['user_id'] ) && (int) $item['user_id'] !== (int) $filters['user_id'] ) {
            return false;
        }
        if ( isset( $filters['role'] ) && ! in_array( sanitize_key( (string) $filters['role'] ), (array) ( $item['roles'] ?? array() ), true ) ) {
            return false;
        }
        if ( isset( $filters['email'] ) && strtolower( (string) $item['user_email'] ) !== strtolower( (string) $filters['email'] ) ) {
            return false;
        }
        if ( isset( $filters['login'] ) && strtolower( (string) $item['user_login'] ) !== strtolower( (string) $filters['login'] ) ) {
            return false;
        }
        if ( '' !== $search ) {
            $haystack = strtolower( implode( ' ', array( $item['user_login'], $item['user_email'], $item['display_name'] ) ) );
            if ( false === strpos( $haystack, strtolower( $search ) ) ) {
                return false;
            }
        }
        return true;
    }

    private function count_by_role( array $items ): array {
        $counts = array();
        foreach ( $items as $item ) {
            foreach ( (array) ( $item['roles'] ?? array() ) as $role ) {
                if ( ! isset( $counts[ $role ] ) ) {
                    $counts[ $role ] = 0;
                }
                $counts[ $role ]++;
            }
        }
        ksort( $counts );
        return $counts;
    }

    private function collect_user_targets( array $input ): array {
        $targets = array();
        if ( ! empty( $input['user_id'] ) ) { $targets[] = array( 'user_id' => absint( $input['user_id'] ) ); }
        if ( ! empty( $input['user_ids'] ) && is_array( $input['user_ids'] ) ) {
            foreach ( $input['user_ids'] as $user_id ) { $targets[] = array( 'user_id' => absint( $user_id ) ); }
        }
        if ( ! empty( $input['login'] ) ) { $targets[] = array( 'login' => sanitize_user( (string) $input['login'], true ) ); }
        if ( ! empty( $input['email'] ) ) { $targets[] = array( 'email' => sanitize_email( (string) $input['email'] ) ); }
        return array_values( array_filter( $targets ) );
    }

    private function resolve_user_target( array $target ) {
        if ( ! empty( $target['user_id'] ) ) {
            return get_user_by( 'id', absint( $target['user_id'] ) );
        }
        if ( ! empty( $target['login'] ) ) {
            return get_user_by( 'login', sanitize_user( (string) $target['login'], true ) );
        }
        if ( ! empty( $target['email'] ) ) {
            return get_user_by( 'email', sanitize_email( (string) $target['email'] ) );
        }
        return false;
    }

    private function resolve_user_apply_targets( string $action, array $input, array $payload ): array {
        if ( 'create' === $action ) {
            return array( array( 'user_login' => sanitize_user( (string) ( $payload['user_login'] ?? '' ), true ), 'user_email' => sanitize_email( (string) ( $payload['user_email'] ?? '' ) ) ) );
        }
        $targets = array();
        if ( ! empty( $input['targets'] ) && is_array( $input['targets'] ) ) {
            foreach ( $input['targets'] as $target ) {
                if ( ! is_array( $target ) ) { continue; }
                $user = $this->resolve_user_target( $target );
                if ( $user ) {
                    $targets[] = array( 'user_id' => (int) $user->ID ) + $target;
                }
            }
        }
        if ( empty( $targets ) ) {
            $filters = isset( $input['filters'] ) && is_array( $input['filters'] ) ? $input['filters'] : array();
            $search = isset( $filters['search'] ) ? sanitize_text_field( (string) $filters['search'] ) : '';
            foreach ( $this->build_user_inventory() as $item ) {
                if ( $this->user_matches_filters( $item, $filters, $search ) ) {
                    $targets[] = array( 'user_id' => (int) $item['user_id'] );
                }
            }
        }
        return $targets;
    }

    private function validate_user_action( string $action, array $target, array $payload ): array {
        $reasons = array();
        if ( 'create' === $action ) {
            if ( empty( $payload['user_login'] ) || empty( $payload['user_email'] ) ) {
                $reasons[] = __( 'Para crear un usuario debes indicar user_login y user_email.', 'wpgpt-mcp-bridge' );
            }
            return $reasons;
        }
        $user_id = isset( $target['user_id'] ) ? absint( $target['user_id'] ) : 0;
        if ( $user_id <= 0 || ! get_user_by( 'id', $user_id ) ) {
            $reasons[] = __( 'No se ha encontrado el usuario objetivo.', 'wpgpt-mcp-bridge' );
            return $reasons;
        }
        if ( 'delete' === $action && get_current_user_id() === $user_id ) {
            $reasons[] = __( 'No se debe eliminar el usuario actual autenticado.', 'wpgpt-mcp-bridge' );
        }
        return $reasons;
    }

    private function format_user( int $user_id, bool $include_roles = false ): array {
        $user = get_user_by( 'id', $user_id );
        $item = array(
            'user_id'         => $user_id,
            'user_login'      => $user ? $user->user_login : '',
            'user_email'      => $user ? $user->user_email : '',
            'display_name'    => $user ? $user->display_name : '',
            'user_registered' => $user ? $user->user_registered : '',
        );
        if ( $include_roles ) {
            $item['roles'] = $user ? array_values( (array) $user->roles ) : array();
        }
        return $item;
    }


    public function query_roles( array $input = array() ): array|WP_Error {
        $search = sanitize_text_field( (string) ( $input['search'] ?? '' ) );
        $result = $this->list_roles();
        $items = array_values( array_filter( $result['items'], function( $item ) use ( $search ) { return '' === $search || false !== stripos( $item['role'], $search ) || false !== stripos( $item['label'], $search ); } ) );
        return array( 'summary' => array( 'matched' => count( $items ) ), 'items' => $items, 'warnings' => array(), 'next_actions' => array() );
    }
    public function inspect_roles( array $input = array() ): array|WP_Error {
        $role = sanitize_key( (string) ( $input['role'] ?? '' ) );
        $result = $this->list_roles();
        $items = array_values( array_filter( $result['items'], fn( $item ) => '' === $role || $item['role'] === $role ) );
        return array( 'summary' => array( 'inspected' => count( $items ) ), 'items' => $items, 'warnings' => empty( $items ) ? array( __( 'No se han encontrado roles con esos filtros.', 'wpgpt-mcp-bridge' ) ) : array(), 'next_actions' => array() );
    }
    public function apply_roles( array $input = array() ): array|WP_Error {
        $action = sanitize_key( (string) ( $input['action'] ?? '' ) );
        $dry_run = ! empty( $input['dry_run'] );
        $payload = isset( $input['payload'] ) && is_array( $input['payload'] ) ? $input['payload'] : array();
        if ( $dry_run ) { return array( 'summary' => array( 'action' => $action, 'dry_run' => true ), 'items' => array(), 'warnings' => array(), 'blocked' => array(), 'next_actions' => array( __( 'Repite la misma llamada con dry_run=false para aplicar los cambios validados.', 'wpgpt-mcp-bridge' ) ) ); }
        $result = match ( $action ) {
            'create' => $this->create_role_data( $payload ),
            'delete' => $this->delete_role_data( $payload ),
            'grant_capability' => $this->grant_capability( $payload ),
            'revoke_capability' => $this->revoke_capability( $payload ),
            default => new WP_Error( 'wpgpt_roles_action_invalid', __( 'Acción de roles no válida.', 'wpgpt-mcp-bridge' ), array( 'status' => 400 ) ),
        };
        if ( is_wp_error( $result ) ) { return $result; }
        return array( 'summary' => array( 'action' => $action, 'dry_run' => false ), 'items' => array( $result ), 'warnings' => array(), 'blocked' => array(), 'next_actions' => array() );
    }

}
