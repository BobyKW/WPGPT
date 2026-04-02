<?php

namespace WPGPT\MCPBridge\BlockEditor;

use WP_Error;

if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

class Block_Editor_Service {
    private array $allowed = array( 'wp_template', 'wp_template_part', 'wp_block', 'wp_navigation' );

    public function query_entities( array $input ) {
        $post_type = sanitize_key( (string) ( $input['entity_type'] ?? '' ) );
        if ( ! in_array( $post_type, $this->allowed, true ) ) {
            return new WP_Error( 'wpgpt_block_entity_invalid', __( 'Entidad de bloques no soportada.', 'wpgpt-mcp-bridge' ), array( 'status' => 400 ) );
        }
        $page = max( 1, absint( $input['page'] ?? 1 ) );
        $per_page = min( 100, max( 1, absint( $input['per_page'] ?? 20 ) ) );
        $q = new \WP_Query( array( 'post_type' => $post_type, 'post_status' => 'any', 's' => sanitize_text_field( (string) ( $input['search'] ?? '' ) ), 'posts_per_page' => $per_page, 'paged' => $page ) );
        return array( 'success' => true, 'resource' => $post_type, 'items' => array_map( array( $this, 'normalize_post' ), $q->posts ), 'total' => (int) $q->found_posts, 'page' => $page, 'per_page' => $per_page );
    }
    public function get_entity( array $input ) {
        $post = get_post( absint( $input['id'] ?? 0 ) );
        if ( ! $post ) { return new WP_Error( 'wpgpt_block_entity_not_found', __( 'Entidad no encontrada.', 'wpgpt-mcp-bridge' ), array( 'status' => 404 ) ); }
        return array( 'success' => true, 'item' => $this->normalize_post( $post ) );
    }
    public function upsert_entity( array $input ) {
        $post_type = sanitize_key( (string) ( $input['entity_type'] ?? '' ) );
        if ( ! in_array( $post_type, $this->allowed, true ) ) {
            return new WP_Error( 'wpgpt_block_entity_invalid', __( 'Entidad de bloques no soportada.', 'wpgpt-mcp-bridge' ), array( 'status' => 400 ) );
        }
        $postarr = array(
            'ID' => absint( $input['id'] ?? 0 ),
            'post_type' => $post_type,
            'post_title' => sanitize_text_field( (string) ( $input['title'] ?? '' ) ),
            'post_name' => sanitize_title( (string) ( $input['slug'] ?? '' ) ),
            'post_status' => sanitize_key( (string) ( $input['status'] ?? 'publish' ) ),
            'post_content' => (string) ( $input['content'] ?? '' ),
        );
        $post_id = wp_insert_post( wp_slash( $postarr ), true );
        if ( is_wp_error( $post_id ) ) { return $post_id; }
        return $this->get_entity( array( 'id' => $post_id ) );
    }
    public function delete_entity( array $input ) {
        $deleted = wp_delete_post( absint( $input['id'] ?? 0 ), ! empty( $input['force'] ) );
        if ( ! $deleted ) { return new WP_Error( 'wpgpt_block_entity_delete_failed', __( 'No se pudo borrar la entidad.', 'wpgpt-mcp-bridge' ), array( 'status' => 500 ) ); }
        return array( 'success' => true, 'deleted' => true, 'id' => $deleted->ID );
    }
    private function normalize_post( \WP_Post $post ): array {
        return array( 'id' => $post->ID, 'type' => $post->post_type, 'title' => $post->post_title, 'slug' => $post->post_name, 'status' => $post->post_status, 'content' => $post->post_content, 'modified_gmt' => $post->post_modified_gmt );
    }
}
