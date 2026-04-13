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

    public function query( array $input = array() ) {
        $check = $this->ensure_acf();
        if ( is_wp_error( $check ) ) { return $check; }
        $filters = isset( $input['filters'] ) && is_array( $input['filters'] ) ? $input['filters'] : array();
        $groups = acf_get_field_groups();
        $items  = array();
        foreach ( (array) $groups as $group ) {
            if ( ! empty( $filters['active_only'] ) && empty( $group['active'] ) ) { continue; }
            if ( ! empty( $filters['group_key'] ) && ( $group['key'] ?? '' ) !== $filters['group_key'] ) { continue; }
            if ( ! empty( $filters['group_id'] ) && (int) ( $group['ID'] ?? 0 ) !== (int) $filters['group_id'] ) { continue; }
            $items[] = array( 'group_key' => $group['key'] ?? '', 'group_id' => $group['ID'] ?? 0, 'title' => $group['title'] ?? '', 'active' => ! empty( $group['active'] ), 'location' => $group['location'] ?? array(), 'risk_level' => 'low' );
        }
        return array( 'summary' => array( 'total_groups' => count( $items ), 'matched' => count( $items ) ), 'items' => $items, 'warnings' => empty( $items ) ? array( __( 'No se han encontrado grupos ACF con esos filtros.', 'wpgpt-mcp-bridge' ) ) : array(), 'next_actions' => array() );
    }

    public function inspect( array $input = array() ) {
        $check = $this->ensure_acf();
        if ( is_wp_error( $check ) ) { return $check; }
        $items = array();
        $warnings = array();
        $include_fields = ! isset( $input['include_fields'] ) || (bool) $input['include_fields'];
        $include_values = (bool) ( $input['include_values'] ?? false );

        if ( ! empty( $input['group_key'] ) || ! empty( $input['group_id'] ) ) {
            $group = null;
            if ( ! empty( $input['group_key'] ) ) { $group = acf_get_field_group( sanitize_text_field( (string) $input['group_key'] ) ); }
            elseif ( ! empty( $input['group_id'] ) ) { $group = acf_get_field_group( absint( $input['group_id'] ) ); }
            if ( ! empty( $group ) ) {
                $item = array( 'entity_type' => 'field_group', 'group_key' => $group['key'] ?? '', 'group_id' => $group['ID'] ?? 0, 'title' => $group['title'] ?? '', 'active' => ! empty( $group['active'] ), 'location' => $group['location'] ?? array(), 'available_actions' => array(), 'risk_level' => 'low' );
                if ( $include_fields ) { $item['fields'] = array_map( array( $this, 'normalize_field' ), (array) acf_get_fields( $group ) ); }
                $items[] = $item;
            } else {
                $warnings[] = __( 'No se ha encontrado el grupo ACF indicado.', 'wpgpt-mcp-bridge' );
            }
        }

        if ( $include_values || isset( $input['target_type'] ) ) {
            $target = $this->resolve_target( $input );
            $values = array();
            $fields = ! empty( $input['fields'] ) && is_array( $input['fields'] ) ? $input['fields'] : array();
            if ( empty( $fields ) ) {
                $objects = get_field_objects( $target, false, true, true );
                $values = is_array( $objects ) ? wp_list_pluck( $objects, 'value' ) : array();
            } else {
                foreach ( $fields as $field ) { $field = sanitize_text_field( (string) $field ); $values[ $field ] = get_field( $field, $target, true ); }
            }
            $items[] = array( 'entity_type' => 'values', 'target' => $target, 'values' => $values, 'available_actions' => array( 'values_update' ), 'risk_level' => 'low' );
        }

        return array( 'summary' => array( 'inspected' => count( $items ) ), 'items' => $items, 'warnings' => array_values( array_unique( array_filter( $warnings ) ) ), 'next_actions' => $items ? array( __( 'Usa wpgpt/acf-apply con dry_run=true antes de ejecutar cambios.', 'wpgpt-mcp-bridge' ) ) : array() );
    }

    public function apply( array $input = array() ) {
        $check = $this->ensure_acf();
        if ( is_wp_error( $check ) ) { return $check; }
        $action = sanitize_key( (string) ( $input['action'] ?? '' ) );
        $dry_run = (bool) ( $input['dry_run'] ?? false );
        $target_input = is_array( $input['target'] ?? null ) ? $input['target'] : array();
        $payload = is_array( $input['payload'] ?? null ) ? $input['payload'] : array();
        $values = is_array( $payload['values'] ?? null ) ? $payload['values'] : array();
        $target = $this->resolve_target( $target_input );

        if ( 'values_update' !== $action ) {
            return new WP_Error( 'wpgpt_acf_action_invalid', __( 'La acción ACF indicada no es válida.', 'wpgpt-mcp-bridge' ), array( 'status' => 400 ) );
        }
        if ( empty( $values ) ) {
            return new WP_Error( 'wpgpt_acf_values_required', __( 'Debes indicar payload.values.', 'wpgpt-mcp-bridge' ), array( 'status' => 400 ) );
        }
        if ( $dry_run ) {
            return array( 'summary' => array( 'action' => $action, 'dry_run' => true, 'resolved_targets' => 1, 'executed' => 0, 'blocked' => 0 ), 'items' => array( array( 'target' => $target, 'action' => $action, 'status' => 'dry_run', 'message' => __( 'Acción validada, no ejecutada por dry_run.', 'wpgpt-mcp-bridge' ) ) ), 'warnings' => array(), 'blocked' => array(), 'next_actions' => array( __( 'Repite la misma llamada con dry_run=false para aplicar los cambios validados.', 'wpgpt-mcp-bridge' ) ) );
        }
        $updated = array(); $errors = array();
        foreach ( $values as $field => $value ) {
            $result = update_field( (string) $field, $value, $target );
            if ( false === $result ) { $errors[] = $field; continue; }
            $updated[] = $field;
        }
        return array( 'summary' => array( 'action' => $action, 'dry_run' => false, 'resolved_targets' => 1, 'executed' => count( $updated ), 'blocked' => count( $errors ) ), 'items' => array( array( 'target' => $target, 'updated' => $updated ) ), 'warnings' => array(), 'blocked' => $errors, 'next_actions' => array() );
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
