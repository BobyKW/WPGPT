<?php

namespace WPGPT\MCPBridge\ACF;

use WP_Error;

if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

class ACF_Service {
    private function ensure_acf() {
        if ( ! function_exists( 'acf_get_field_groups' ) || ! function_exists( 'get_field_objects' ) || ! function_exists( 'update_field' ) ) {
            return new WP_Error( 'wpgpt_acf_unavailable', __( 'ACF no está activo o no expone su API.', 'wpgpt-mcp-bridge' ), array( 'status' => 400 ) );
        }

        return true;
    }

    public function list_field_groups( array $input ) {
        $check = $this->ensure_acf();
        if ( is_wp_error( $check ) ) {
            return $check;
        }

        $groups = acf_get_field_groups();
        $items  = array();
        foreach ( (array) $groups as $group ) {
            if ( ! empty( $input['active_only'] ) && empty( $group['active'] ) ) {
                continue;
            }
            $items[] = array(
                'key'      => $group['key'] ?? '',
                'id'       => $group['ID'] ?? 0,
                'title'    => $group['title'] ?? '',
                'active'   => ! empty( $group['active'] ),
                'location' => $group['location'] ?? array(),
            );
        }

        return array( 'success' => true, 'items' => $items, 'total' => count( $items ) );
    }

    public function get_fields( array $input ) {
        $check = $this->ensure_acf();
        if ( is_wp_error( $check ) ) {
            return $check;
        }

        $group = null;
        if ( ! empty( $input['group_key'] ) ) {
            $group = acf_get_field_group( sanitize_text_field( (string) $input['group_key'] ) );
        } elseif ( ! empty( $input['group_id'] ) ) {
            $group = acf_get_field_group( absint( $input['group_id'] ) );
        }

        if ( empty( $group ) ) {
            return new WP_Error( 'wpgpt_acf_group_not_found', __( 'No se ha encontrado el grupo ACF.', 'wpgpt-mcp-bridge' ), array( 'status' => 404 ) );
        }

        $fields = acf_get_fields( $group );
        return array(
            'success' => true,
            'group'   => array( 'key' => $group['key'] ?? '', 'title' => $group['title'] ?? '' ),
            'items'   => array_map( array( $this, 'normalize_field' ), (array) $fields ),
        );
    }

    public function get_values( array $input ) {
        $check = $this->ensure_acf();
        if ( is_wp_error( $check ) ) {
            return $check;
        }

        $target = $this->resolve_target( $input );
        $fields = ! empty( $input['fields'] ) && is_array( $input['fields'] ) ? $input['fields'] : array();
        if ( empty( $fields ) ) {
            $objects = get_field_objects( $target, false, true, true );
            return array( 'success' => true, 'target' => $target, 'values' => is_array( $objects ) ? wp_list_pluck( $objects, 'value' ) : array() );
        }
        $values = array();
        foreach ( $fields as $field ) {
            $field = sanitize_text_field( (string) $field );
            $values[ $field ] = get_field( $field, $target, true );
        }
        return array( 'success' => true, 'target' => $target, 'values' => $values );
    }

    public function update_values( array $input ) {
        $check = $this->ensure_acf();
        if ( is_wp_error( $check ) ) {
            return $check;
        }
        $target = $this->resolve_target( $input );
        $values = is_array( $input['values'] ?? null ) ? $input['values'] : array();
        $updated = array();
        $errors  = array();
        foreach ( $values as $field => $value ) {
            $result = update_field( (string) $field, $value, $target );
            if ( false === $result ) {
                $errors[] = $field;
                continue;
            }
            $updated[] = $field;
        }
        return array( 'success' => empty( $errors ), 'target' => $target, 'updated' => $updated, 'errors' => $errors );
    }

    private function resolve_target( array $input ): string {
        $type = sanitize_key( (string) ( $input['target_type'] ?? 'post' ) );
        $id   = absint( $input['target_id'] ?? 0 );
        return match ( $type ) {
            'term'   => ( sanitize_key( (string) ( $input['taxonomy'] ?? 'category' ) ) . '_' . $id ),
            'user'   => 'user_' . $id,
            'option' => 'option',
            default  => (string) $id,
        };
    }

    private function normalize_field( array $field ): array {
        return array(
            'key'        => $field['key'] ?? '',
            'name'       => $field['name'] ?? '',
            'label'      => $field['label'] ?? '',
            'type'       => $field['type'] ?? '',
            'required'   => ! empty( $field['required'] ),
            'choices'    => $field['choices'] ?? array(),
            'sub_fields' => array_map( array( $this, 'normalize_field' ), (array) ( $field['sub_fields'] ?? array() ) ),
        );
    }
}
