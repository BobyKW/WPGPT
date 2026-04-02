<?php

namespace WPGPT\MCPBridge\Structure;

use WP_Error;

if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

class Taxonomy_Manager {
    private const OPTION_KEY = 'wpgpt_mcp_custom_taxonomies';

    public static function init(): void {
        add_action( 'init', array( __CLASS__, 'register_saved_taxonomies' ), 21 );
    }

    public static function register_saved_taxonomies(): void {
        foreach ( self::get_definitions() as $definition ) {
            $slug = isset( $definition['slug'] ) ? sanitize_key( (string) $definition['slug'] ) : '';
            if ( '' === $slug || taxonomy_exists( $slug ) ) {
                continue;
            }

            register_taxonomy( $slug, self::sanitize_object_types( $definition['object_type'] ?? array( 'post' ) ), self::build_args( $definition ) );
        }
    }

    public static function list(): array {
        $managed = array();
        foreach ( self::get_definitions() as $definition ) {
            $managed[ $definition['slug'] ] = true;
        }

        $items = array();
        foreach ( get_taxonomies( array(), 'objects' ) as $taxonomy ) {
            $items[] = array(
                'slug'              => $taxonomy->name,
                'label'             => $taxonomy->label,
                'public'            => (bool) $taxonomy->public,
                'show_in_rest'      => (bool) $taxonomy->show_in_rest,
                'hierarchical'      => (bool) $taxonomy->hierarchical,
                'object_type'       => array_values( (array) $taxonomy->object_type ),
                'managed_by_plugin' => isset( $managed[ $taxonomy->name ] ),
            );
        }

        return array( 'count' => count( $items ), 'items' => $items );
    }

    public static function create( array $input ): array|WP_Error {
        $slug = isset( $input['slug'] ) ? sanitize_key( (string) $input['slug'] ) : '';
        if ( '' === $slug ) {
            return new WP_Error( 'wpgpt_tax_slug_required', __( 'Debes indicar un slug para la taxonomía.', 'wpgpt-mcp-bridge' ), array( 'status' => 400 ) );
        }
        if ( taxonomy_exists( $slug ) ) {
            return new WP_Error( 'wpgpt_tax_exists', __( 'Ya existe una taxonomía con ese slug.', 'wpgpt-mcp-bridge' ), array( 'status' => 409 ) );
        }

        $definition = array(
            'slug'         => $slug,
            'label'        => sanitize_text_field( (string) ( $input['label'] ?? $slug ) ),
            'singular'     => sanitize_text_field( (string) ( $input['singular_label'] ?? $input['label'] ?? $slug ) ),
            'public'       => ! empty( $input['public'] ),
            'show_in_rest' => ! empty( $input['show_in_rest'] ),
            'hierarchical' => ! empty( $input['hierarchical'] ),
            'object_type'  => self::sanitize_object_types( $input['object_type'] ?? $input['post_types'] ?? array( 'post' ) ),
        );

        $definitions   = self::get_definitions();
        $definitions[] = $definition;
        update_option( self::OPTION_KEY, $definitions, false );

        register_taxonomy( $slug, $definition['object_type'], self::build_args( $definition ) );

        return array(
            'created'     => true,
            'slug'        => $slug,
            'label'       => $definition['label'],
            'object_type' => $definition['object_type'],
        );
    }

    public static function delete( array $input ): array|WP_Error {
        $slug = isset( $input['slug'] ) ? sanitize_key( (string) $input['slug'] ) : '';
        if ( '' === $slug ) {
            return new WP_Error( 'wpgpt_tax_slug_required', __( 'Debes indicar el slug de la taxonomía.', 'wpgpt-mcp-bridge' ), array( 'status' => 400 ) );
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
            return new WP_Error( 'wpgpt_tax_not_managed', __( 'Solo se pueden eliminar taxonomías gestionadas por este plugin.', 'wpgpt-mcp-bridge' ), array( 'status' => 404 ) );
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
        );
    }

    private static function sanitize_object_types( $types ): array {
        if ( ! is_array( $types ) ) {
            $types = array( 'post' );
        }
        $types = array_values( array_filter( array_map( 'sanitize_key', $types ) ) );
        return empty( $types ) ? array( 'post' ) : $types;
    }
}
