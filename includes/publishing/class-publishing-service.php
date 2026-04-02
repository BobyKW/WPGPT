<?php

namespace WPGPT\MCPBridge\Publishing;

use WP_Error;

if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

class Publishing_Service {
    public function create_blog_draft( array $input ): array|WP_Error {
        $title = sanitize_text_field( (string) ( $input['title'] ?? '' ) );
        if ( '' === $title ) {
            return new WP_Error( 'wpgpt_title_required', __( 'Debes indicar un título.', 'wpgpt-mcp-bridge' ), array( 'status' => 400 ) );
        }

        $postarr = array(
            'post_title' => $title,
            'post_content' => wp_kses_post( (string) ( $input['content'] ?? '' ) ),
            'post_excerpt' => wp_kses_post( (string) ( $input['excerpt'] ?? '' ) ),
            'post_name' => sanitize_title( (string) ( $input['slug'] ?? $title ) ),
            'post_status' => sanitize_key( (string) ( $input['post_status'] ?? 'draft' ) ),
            'post_type' => 'post',
        );

        $post_id = wp_insert_post( $postarr, true, false );
        if ( is_wp_error( $post_id ) ) {
            return $post_id;
        }

        $category_ids = $this->ensure_terms( (array) ( $input['categories'] ?? array() ), 'category' );
        if ( ! empty( $category_ids ) ) {
            wp_set_post_terms( (int) $post_id, $category_ids, 'category', false );
        }
        $tag_ids = $this->ensure_terms( (array) ( $input['tags'] ?? array() ), 'post_tag' );
        if ( ! empty( $tag_ids ) ) {
            wp_set_post_terms( (int) $post_id, $tag_ids, 'post_tag', false );
        }

        if ( ! empty( $input['seo'] ) && is_array( $input['seo'] ) ) {
            if ( array_key_exists( 'title', $input['seo'] ) ) {
                update_post_meta( (int) $post_id, 'rank_math_title', sanitize_text_field( (string) $input['seo']['title'] ) );
                update_post_meta( (int) $post_id, '_yoast_wpseo_title', sanitize_text_field( (string) $input['seo']['title'] ) );
            }
            if ( array_key_exists( 'description', $input['seo'] ) ) {
                update_post_meta( (int) $post_id, 'rank_math_description', sanitize_text_field( (string) $input['seo']['description'] ) );
                update_post_meta( (int) $post_id, '_yoast_wpseo_metadesc', sanitize_text_field( (string) $input['seo']['description'] ) );
            }
            if ( array_key_exists( 'focus_keyword', $input['seo'] ) ) {
                update_post_meta( (int) $post_id, 'rank_math_focus_keyword', sanitize_text_field( (string) $input['seo']['focus_keyword'] ) );
                update_post_meta( (int) $post_id, '_yoast_wpseo_focuskw', sanitize_text_field( (string) $input['seo']['focus_keyword'] ) );
            }
        }

        return array(
            'created' => true,
            'post_id' => (int) $post_id,
            'post_status' => get_post_status( (int) $post_id ),
            'title' => get_the_title( (int) $post_id ),
            'permalink' => get_permalink( (int) $post_id ),
            'categories' => $category_ids,
            'tags' => $tag_ids,
        );
    }

    public function list_terms( array $input ): array|WP_Error {
        $taxonomy = sanitize_key( (string) ( $input['taxonomy'] ?? '' ) );
        if ( '' === $taxonomy || ! taxonomy_exists( $taxonomy ) ) {
            return new WP_Error( 'wpgpt_taxonomy_not_found', __( 'Debes indicar una taxonomía válida.', 'wpgpt-mcp-bridge' ), array( 'status' => 400 ) );
        }
        $hide_empty = ! empty( $input['hide_empty'] );
        $limit = max( 1, min( 100, (int) ( $input['limit'] ?? 50 ) ) );
        $terms = get_terms( array( 'taxonomy' => $taxonomy, 'hide_empty' => $hide_empty, 'number' => $limit ) );
        if ( is_wp_error( $terms ) ) {
            return $terms;
        }
        $items = array();
        foreach ( $terms as $term ) {
            $items[] = array( 'term_id' => (int) $term->term_id, 'name' => $term->name, 'slug' => $term->slug, 'count' => (int) $term->count );
        }
        return array( 'taxonomy' => $taxonomy, 'count' => count( $items ), 'items' => $items );
    }

    public function create_term( array $input ): array|WP_Error {
        $taxonomy = sanitize_key( (string) ( $input['taxonomy'] ?? '' ) );
        $name = sanitize_text_field( (string) ( $input['name'] ?? '' ) );
        $slug = sanitize_title( (string) ( $input['slug'] ?? $name ) );
        if ( '' === $taxonomy || ! taxonomy_exists( $taxonomy ) ) {
            return new WP_Error( 'wpgpt_taxonomy_not_found', __( 'Debes indicar una taxonomía válida.', 'wpgpt-mcp-bridge' ), array( 'status' => 400 ) );
        }
        if ( '' === $name ) {
            return new WP_Error( 'wpgpt_term_name_required', __( 'Debes indicar un nombre de término.', 'wpgpt-mcp-bridge' ), array( 'status' => 400 ) );
        }
        $result = wp_insert_term( $name, $taxonomy, array( 'slug' => $slug ) );
        if ( is_wp_error( $result ) ) {
            return $result;
        }
        return array( 'created' => true, 'taxonomy' => $taxonomy, 'term_id' => (int) $result['term_id'], 'term_taxonomy_id' => (int) $result['term_taxonomy_id'], 'slug' => $slug, 'name' => $name );
    }


    public function get_term_data( array $input ): array|WP_Error {
        $taxonomy = sanitize_key( (string) ( $input['taxonomy'] ?? '' ) );
        $term_id  = absint( $input['term_id'] ?? 0 );
        if ( '' === $taxonomy || ! taxonomy_exists( $taxonomy ) || $term_id <= 0 ) {
            return new WP_Error( 'wpgpt_invalid_term', __( 'Debes indicar taxonomy y term_id válidos.', 'wpgpt-mcp-bridge' ), array( 'status' => 400 ) );
        }
        $term = get_term( $term_id, $taxonomy );
        if ( ! $term || is_wp_error( $term ) ) {
            return new WP_Error( 'wpgpt_term_not_found', __( 'No se ha encontrado el término indicado.', 'wpgpt-mcp-bridge' ), array( 'status' => 404 ) );
        }
        return array( 'taxonomy' => $taxonomy, 'term_id' => (int) $term->term_id, 'name' => $term->name, 'slug' => $term->slug, 'description' => $term->description, 'count' => (int) $term->count, 'parent' => (int) $term->parent );
    }

    public function update_term_data( array $input ): array|WP_Error {
        $taxonomy = sanitize_key( (string) ( $input['taxonomy'] ?? '' ) );
        $term_id  = absint( $input['term_id'] ?? 0 );
        if ( '' === $taxonomy || ! taxonomy_exists( $taxonomy ) || $term_id <= 0 ) {
            return new WP_Error( 'wpgpt_invalid_term', __( 'Debes indicar taxonomy y term_id válidos.', 'wpgpt-mcp-bridge' ), array( 'status' => 400 ) );
        }
        $args = array();
        foreach ( array( 'name', 'slug', 'description' ) as $field ) {
            if ( array_key_exists( $field, $input ) ) { $args[ $field ] = sanitize_text_field( (string) $input[ $field ] ); }
        }
        if ( array_key_exists( 'parent', $input ) ) { $args['parent'] = absint( $input['parent'] ); }
        $result = wp_update_term( $term_id, $taxonomy, $args );
        if ( is_wp_error( $result ) ) { return $result; }
        return array( 'updated' => true ) + $this->get_term_data( array( 'taxonomy' => $taxonomy, 'term_id' => $term_id ) );
    }

    public function assign_term_to_post( array $input ): array|WP_Error {
        $post_id = absint( $input['post_id'] ?? 0 );
        $term_id = absint( $input['term_id'] ?? 0 );
        $taxonomy = sanitize_key( (string) ( $input['taxonomy'] ?? '' ) );
        if ( $post_id <= 0 || ! get_post( $post_id ) || $term_id <= 0 || '' === $taxonomy || ! taxonomy_exists( $taxonomy ) ) {
            return new WP_Error( 'wpgpt_invalid_term_assignment', __( 'Debes indicar post_id, taxonomy y term_id válidos.', 'wpgpt-mcp-bridge' ), array( 'status' => 400 ) );
        }
        $append = array_key_exists( 'append', $input ) ? ! empty( $input['append'] ) : true;
        $result = wp_set_post_terms( $post_id, array( $term_id ), $taxonomy, $append );
        if ( is_wp_error( $result ) ) { return $result; }
        return array( 'updated' => true, 'post_id' => $post_id, 'taxonomy' => $taxonomy, 'terms' => array_map( 'intval', $result ) );
    }

    public function remove_term_from_post( array $input ): array|WP_Error {
        $post_id = absint( $input['post_id'] ?? 0 );
        $term_id = absint( $input['term_id'] ?? 0 );
        $taxonomy = sanitize_key( (string) ( $input['taxonomy'] ?? '' ) );
        if ( $post_id <= 0 || ! get_post( $post_id ) || $term_id <= 0 || '' === $taxonomy || ! taxonomy_exists( $taxonomy ) ) {
            return new WP_Error( 'wpgpt_invalid_term_assignment', __( 'Debes indicar post_id, taxonomy y term_id válidos.', 'wpgpt-mcp-bridge' ), array( 'status' => 400 ) );
        }
        wp_remove_object_terms( $post_id, array( $term_id ), $taxonomy );
        $remaining = wp_get_post_terms( $post_id, $taxonomy, array( 'fields' => 'ids' ) );
        return array( 'updated' => true, 'post_id' => $post_id, 'taxonomy' => $taxonomy, 'terms' => array_map( 'intval', (array) $remaining ) );
    }

    public function delete_term( array $input ): array|WP_Error {
        $taxonomy = sanitize_key( (string) ( $input['taxonomy'] ?? '' ) );
        $term_id = absint( $input['term_id'] ?? 0 );
        if ( '' === $taxonomy || ! taxonomy_exists( $taxonomy ) || $term_id <= 0 ) {
            return new WP_Error( 'wpgpt_invalid_term_delete', __( 'Debes indicar taxonomy y term_id válidos.', 'wpgpt-mcp-bridge' ), array( 'status' => 400 ) );
        }
        $result = wp_delete_term( $term_id, $taxonomy );
        if ( is_wp_error( $result ) ) {
            return $result;
        }
        return array( 'deleted' => true, 'taxonomy' => $taxonomy, 'term_id' => $term_id );
    }

    private function ensure_terms( array $names, string $taxonomy ): array {
        $ids = array();
        foreach ( $names as $name ) {
            $name = sanitize_text_field( (string) $name );
            if ( '' === $name ) {
                continue;
            }
            $term = term_exists( $name, $taxonomy );
            if ( ! $term ) {
                $term = wp_insert_term( $name, $taxonomy );
            }
            if ( is_array( $term ) && isset( $term['term_id'] ) ) {
                $ids[] = (int) $term['term_id'];
            } elseif ( is_int( $term ) ) {
                $ids[] = $term;
            }
        }
        return array_values( array_unique( $ids ) );
    }
}
