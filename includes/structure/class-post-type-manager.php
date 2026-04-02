<?php

namespace WPGPT\MCPBridge\Structure;

use WP_Error;

if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

class Post_Type_Manager {
    private const OPTION_KEY = 'wpgpt_mcp_custom_post_types';

    public static function init(): void {
        add_action( 'init', array( __CLASS__, 'register_saved_post_types' ), 20 );
    }

    public static function register_saved_post_types(): void {
        foreach ( self::get_definitions() as $definition ) {
            $slug = isset( $definition['slug'] ) ? sanitize_key( (string) $definition['slug'] ) : '';
            if ( '' === $slug || post_type_exists( $slug ) ) {
                continue;
            }

            register_post_type( $slug, self::build_args( $definition ) );
        }
    }

    public static function list(): array {
        $managed = array();
        foreach ( self::get_definitions() as $definition ) {
            $managed[ $definition['slug'] ] = true;
        }

        $items = array();
        foreach ( get_post_types( array(), 'objects' ) as $type ) {
            $items[] = array(
                'slug'              => $type->name,
                'label'             => $type->label,
                'public'            => (bool) $type->public,
                'show_in_rest'      => (bool) $type->show_in_rest,
                'hierarchical'      => (bool) $type->hierarchical,
                'managed_by_plugin' => isset( $managed[ $type->name ] ),
            );
        }

        return array( 'count' => count( $items ), 'items' => $items );
    }

    public static function create( array $input ): array|WP_Error {
        $slug = isset( $input['slug'] ) ? sanitize_key( (string) $input['slug'] ) : '';
        if ( '' === $slug ) {
            return new WP_Error( 'wpgpt_cpt_slug_required', __( 'Debes indicar un slug para el CPT.', 'wpgpt-mcp-bridge' ), array( 'status' => 400 ) );
        }
        if ( post_type_exists( $slug ) ) {
            return new WP_Error( 'wpgpt_cpt_exists', __( 'Ya existe un post type con ese slug.', 'wpgpt-mcp-bridge' ), array( 'status' => 409 ) );
        }

        $definition = array(
            'slug'         => $slug,
            'label'        => sanitize_text_field( (string) ( $input['label'] ?? $slug ) ),
            'singular'     => sanitize_text_field( (string) ( $input['singular_label'] ?? $input['label'] ?? $slug ) ),
            'public'       => ! empty( $input['public'] ),
            'show_in_rest' => ! empty( $input['show_in_rest'] ),
            'hierarchical' => ! empty( $input['hierarchical'] ),
            'supports'     => self::sanitize_string_array( $input['supports'] ?? array( 'title', 'editor' ) ),
        );

        $definitions   = self::get_definitions();
        $definitions[] = $definition;
        update_option( self::OPTION_KEY, $definitions, false );

        register_post_type( $slug, self::build_args( $definition ) );

        return array(
            'created' => true,
            'slug'    => $slug,
            'label'   => $definition['label'],
        );
    }

    public static function delete( array $input ): array|WP_Error {
        $slug = isset( $input['slug'] ) ? sanitize_key( (string) $input['slug'] ) : '';
        if ( '' === $slug ) {
            return new WP_Error( 'wpgpt_cpt_slug_required', __( 'Debes indicar el slug del CPT.', 'wpgpt-mcp-bridge' ), array( 'status' => 400 ) );
        }

        $definitions = self::get_definitions();
        $found       = false;
        $kept        = array();

        foreach ( $definitions as $definition ) {
            $definition_slug = isset( $definition['slug'] ) ? sanitize_key( (string) $definition['slug'] ) : '';
            if ( $definition_slug === $slug ) {
                $found = true;
                continue;
            }
            $kept[] = $definition;
        }

        if ( ! $found ) {
            return new WP_Error( 'wpgpt_cpt_not_managed', __( 'Solo se pueden eliminar CPT gestionados por este plugin.', 'wpgpt-mcp-bridge' ), array( 'status' => 404 ) );
        }

        update_option( self::OPTION_KEY, array_values( $kept ), false );

        return array(
            'deleted' => true,
            'slug'    => $slug,
        );
    }

    private static function get_definitions(): array {
        $definitions = get_option( self::OPTION_KEY, array() );
        return is_array( $definitions ) ? array_values( $definitions ) : array();
    }

    private static function build_args( array $definition ): array {
        return array(
            'label'        => $definition['label'] ?? $definition['slug'],
            'labels'       => array(
                'name'          => $definition['label'] ?? $definition['slug'],
                'singular_name' => $definition['singular'] ?? $definition['label'] ?? $definition['slug'],
            ),
            'public'       => ! empty( $definition['public'] ),
            'show_in_rest' => ! empty( $definition['show_in_rest'] ),
            'hierarchical' => ! empty( $definition['hierarchical'] ),
            'supports'     => self::sanitize_string_array( $definition['supports'] ?? array( 'title', 'editor' ) ),
        );
    }

    private static function sanitize_string_array( $values ): array {
        if ( ! is_array( $values ) ) {
            return array();
        }
        return array_values( array_filter( array_map( 'sanitize_key', $values ) ) );
    }
}
