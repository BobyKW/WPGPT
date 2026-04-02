<?php

namespace WPGPT\MCPBridge\Users;

use WP_Error;

if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

class User_Manager_Service {
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
}
