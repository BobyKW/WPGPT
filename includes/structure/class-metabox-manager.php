<?php

namespace WPGPT\MCPBridge\Structure;

use WP_Error;

if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

class Metabox_Manager {
    private const OPTION_KEY = 'wpgpt_mcp_metabox_definitions';

    public static function init(): void {
        add_action( 'add_meta_boxes', array( __CLASS__, 'register_meta_boxes' ) );
        add_action( 'save_post', array( __CLASS__, 'save_meta_boxes' ), 10, 2 );
    }

    public static function list(): array {
        $definitions = self::get_definitions();
        return array(
            'count' => count( $definitions ),
            'items' => array_values( $definitions ),
        );
    }

    public static function create( array $input ): array|WP_Error {
        $key = isset( $input['key'] ) ? sanitize_key( (string) $input['key'] ) : sanitize_key( (string) ( $input['id'] ?? '' ) );
        if ( '' === $key ) {
            return new WP_Error( 'wpgpt_metabox_key_required', __( 'Debes indicar una key para el metabox.', 'wpgpt-mcp-bridge' ), array( 'status' => 400 ) );
        }

        $definitions = self::get_definitions();
        foreach ( $definitions as $definition ) {
            if ( $definition['key'] === $key ) {
                return new WP_Error( 'wpgpt_metabox_exists', __( 'Ya existe un metabox con esa key.', 'wpgpt-mcp-bridge' ), array( 'status' => 409 ) );
            }
        }

        $definition = array(
            'key'        => $key,
            'title'      => sanitize_text_field( (string) ( $input['title'] ?? $key ) ),
            'post_types' => self::sanitize_post_types( $input['post_types'] ?? array( 'post' ) ),
            'fields'     => self::sanitize_fields( $input['fields'] ?? array() ),
        );

        if ( empty( $definition['fields'] ) ) {
            return new WP_Error( 'wpgpt_metabox_fields_required', __( 'Debes indicar al menos un field.', 'wpgpt-mcp-bridge' ), array( 'status' => 400 ) );
        }

        $definitions[] = $definition;
        update_option( self::OPTION_KEY, $definitions, false );

        return array(
            'created' => true,
            'key'     => $definition['key'],
            'title'   => $definition['title'],
        );
    }

    public static function delete( array $input ): array|WP_Error {
        $key = isset( $input['key'] ) ? sanitize_key( (string) $input['key'] ) : sanitize_key( (string) ( $input['id'] ?? '' ) );
        if ( '' === $key ) {
            return new WP_Error( 'wpgpt_metabox_key_required', __( 'Debes indicar la key del metabox.', 'wpgpt-mcp-bridge' ), array( 'status' => 400 ) );
        }

        $definitions = self::get_definitions();
        $found       = false;
        $kept        = array();

        foreach ( $definitions as $definition ) {
            $definition_key = isset( $definition['key'] ) ? sanitize_key( (string) $definition['key'] ) : '';
            if ( $definition_key === $key ) {
                $found = true;
                continue;
            }
            $kept[] = $definition;
        }

        if ( ! $found ) {
            return new WP_Error( 'wpgpt_metabox_not_found', __( 'No se ha encontrado un metabox gestionado por el plugin con esa key.', 'wpgpt-mcp-bridge' ), array( 'status' => 404 ) );
        }

        update_option( self::OPTION_KEY, array_values( $kept ), false );

        return array(
            'deleted' => true,
            'key'     => $key,
        );
    }

    public static function register_meta_boxes(): void {
        foreach ( self::get_definitions() as $definition ) {
            foreach ( self::sanitize_post_types( $definition['post_types'] ?? array() ) as $post_type ) {
                add_meta_box(
                    'wpgpt_metabox_' . $definition['key'],
                    $definition['title'],
                    array( __CLASS__, 'render_meta_box' ),
                    $post_type,
                    'normal',
                    'default',
                    $definition
                );
            }
        }
    }

    public static function render_meta_box( \WP_Post $post, array $callback_args ): void {
        $definition = $callback_args['args'] ?? array();
        $box_key    = isset( $definition['key'] ) ? (string) $definition['key'] : '';
        wp_nonce_field( 'wpgpt_metabox_' . $box_key, 'wpgpt_metabox_nonce_' . $box_key );

        foreach ( (array) ( $definition['fields'] ?? array() ) as $field ) {
            $field_key   = sanitize_key( (string) ( $field['key'] ?? '' ) );
            $field_label = sanitize_text_field( (string) ( $field['label'] ?? $field_key ) );
            $field_type  = sanitize_key( (string) ( $field['type'] ?? 'text' ) );
            $value       = get_post_meta( $post->ID, $field_key, true );
            echo '<p><label><strong>' . esc_html( $field_label ) . '</strong></label><br />';
            if ( 'textarea' === $field_type ) {
                echo '<textarea style="width:100%;min-height:100px;" name="wpgpt_meta[' . esc_attr( $field_key ) . ']">' . esc_textarea( (string) $value ) . '</textarea>';
            } else {
                $html_type = in_array( $field_type, array( 'number', 'url' ), true ) ? $field_type : 'text';
                echo '<input style="width:100%;" type="' . esc_attr( $html_type ) . '" name="wpgpt_meta[' . esc_attr( $field_key ) . ']" value="' . esc_attr( (string) $value ) . '" />';
            }
            echo '</p>';
        }
    }

    public static function save_meta_boxes( int $post_id, \WP_Post $post ): void {
        if ( defined( 'DOING_AUTOSAVE' ) && DOING_AUTOSAVE ) {
            return;
        }
        if ( ! current_user_can( 'edit_post', $post_id ) ) {
            return;
        }

        foreach ( self::get_definitions() as $definition ) {
            $box_key = isset( $definition['key'] ) ? (string) $definition['key'] : '';
            $nonce   = $_POST[ 'wpgpt_metabox_nonce_' . $box_key ] ?? '';
            if ( ! wp_verify_nonce( sanitize_text_field( wp_unslash( (string) $nonce ) ), 'wpgpt_metabox_' . $box_key ) ) {
                continue;
            }
            $meta_input = $_POST['wpgpt_meta'] ?? array();
            if ( ! is_array( $meta_input ) ) {
                continue;
            }
            foreach ( (array) ( $definition['fields'] ?? array() ) as $field ) {
                $field_key = sanitize_key( (string) ( $field['key'] ?? '' ) );
                if ( '' === $field_key ) {
                    continue;
                }
                $value = $meta_input[ $field_key ] ?? '';
                update_post_meta( $post_id, $field_key, sanitize_textarea_field( wp_unslash( (string) $value ) ) );
            }
        }
    }

    private static function get_definitions(): array {
        $definitions = get_option( self::OPTION_KEY, array() );
        return is_array( $definitions ) ? array_values( $definitions ) : array();
    }

    private static function sanitize_post_types( $post_types ): array {
        if ( ! is_array( $post_types ) ) {
            $post_types = array( 'post' );
        }
        $post_types = array_values( array_filter( array_map( 'sanitize_key', $post_types ) ) );
        return empty( $post_types ) ? array( 'post' ) : $post_types;
    }

    private static function sanitize_fields( $fields ): array {
        if ( ! is_array( $fields ) ) {
            return array();
        }

        $items = array();
        foreach ( $fields as $field ) {
            if ( ! is_array( $field ) ) {
                continue;
            }
            $key = isset( $field['key'] ) ? sanitize_key( (string) $field['key'] ) : '';
            if ( '' === $key ) {
                continue;
            }
            $items[] = array(
                'key'   => $key,
                'label' => sanitize_text_field( (string) ( $field['label'] ?? $key ) ),
                'type'  => sanitize_key( (string) ( $field['type'] ?? 'text' ) ),
            );
        }

        return $items;
    }
}
