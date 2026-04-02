<?php

namespace WPGPT\MCPBridge;

use WP_Error;
use WP_Query;

if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

/**
 * Class Content_Provider
 * 
 * Handles content-related abilities (posts, postmeta).
 */
class Content_Provider extends Base_Ability_Provider {
    /**
     * Returns an array of ability definitions.
     */
    public function get_abilities(): array {
        return array(
            'wpgpt/posts-search'       => array(
                'label'            => __( 'Search posts', 'wpgpt-mcp-bridge' ),
                'description'      => __( 'Busca contenido por texto, tipo de post y estado.', 'wpgpt-mcp-bridge' ),
                'input_schema'     => $this->posts_search_input_schema(),
                'execute_callback' => array( $this, 'search_posts' ),
                'output_schema'    => $this->object_schema(),
                'permission_callback' => array( $this, 'can_edit_content' ),
            ),
            'wpgpt/post-get'           => array(
                'label'            => __( 'Get post', 'wpgpt-mcp-bridge' ),
                'description'      => __( 'Obtiene una entrada, página o CPT por ID con datos útiles para análisis.', 'wpgpt-mcp-bridge' ),
                'input_schema'     => $this->post_get_input_schema(),
                'execute_callback' => array( $this, 'get_post' ),
                'output_schema'    => $this->object_schema(),
                'permission_callback' => array( $this, 'can_edit_content' ),
            ),
            'wpgpt/postmeta-search'    => array(
                'label'            => __( 'Search postmeta', 'wpgpt-mcp-bridge' ),
                'description'      => __( 'Busca claves de metadatos por prefijo y devuelve una muestra segura.', 'wpgpt-mcp-bridge' ),
                'input_schema'     => $this->postmeta_input_schema(),
                'execute_callback' => array( $this, 'search_postmeta' ),
                'output_schema'    => $this->object_schema(),
            ),
        );
    }

    /**
     * Search posts logic.
     */
    public function search_posts( array $input ): array {
        $search      = isset( $input['search'] ) ? sanitize_text_field( (string) $input['search'] ) : '';
        $post_type   = isset( $input['post_type'] ) ? sanitize_key( (string) $input['post_type'] ) : 'any';
        $post_status = isset( $input['post_status'] ) ? sanitize_key( (string) $input['post_status'] ) : 'any';
        $limit       = isset( $input['limit'] ) ? max( 1, min( 25, (int) $input['limit'] ) ) : 10;

        $query = new WP_Query(
            array(
                's'              => $search,
                'post_type'      => 'any' === $post_type ? 'any' : $post_type,
                'post_status'    => 'any' === $post_status ? array_keys( get_post_stati() ) : $post_status,
                'posts_per_page' => $limit,
                'no_found_rows'  => true,
                'fields'         => 'ids',
            )
        );

        $items = array();
        foreach ( $query->posts as $post_id ) {
            $items[] = $this->format_post_summary( (int) $post_id );
        }

        return array( 'count' => count( $items ), 'items' => $items );
    }

    /**
     * Get post logic.
     */
    public function get_post( array $input ): array|WP_Error {
        $post_id = isset( $input['post_id'] ) ? absint( $input['post_id'] ) : 0;
        $post    = $post_id ? get_post( $post_id ) : null;
        if ( ! $post ) {
            return new WP_Error( 'wpgpt_post_not_found', __( 'No se ha encontrado el contenido solicitado.', 'wpgpt-mcp-bridge' ), array( 'status' => 404 ) );
        }

        return array(
            'id'            => (int) $post->ID,
            'post_type'     => $post->post_type,
            'post_status'   => $post->post_status,
            'title'         => get_the_title( $post ),
            'slug'          => $post->post_name,
            'permalink'     => get_permalink( $post ),
            'modified_gmt'  => $post->post_modified_gmt,
            'date_gmt'      => $post->post_date_gmt,
            'excerpt'       => wp_strip_all_tags( get_the_excerpt( $post ) ),
            'content_html'  => apply_filters( 'the_content', $post->post_content ),
        );
    }

    /**
     * Search postmeta logic.
     */
    public function search_postmeta( array $input ): array {
        global $wpdb;

        $like  = isset( $input['meta_key_like'] ) ? sanitize_text_field( (string) $input['meta_key_like'] ) : '';
        $limit = isset( $input['limit'] ) ? max( 1, min( 50, (int) $input['limit'] ) ) : 20;

        $sql = "SELECT post_id, meta_key, LEFT(meta_value, 300) AS meta_value_sample FROM {$wpdb->postmeta}";
        if ( '' !== $like ) {
            $sql .= $wpdb->prepare( ' WHERE meta_key LIKE %s', '%' . $wpdb->esc_like( $like ) . '%' );
        }
        $sql .= $wpdb->prepare( ' ORDER BY meta_id DESC LIMIT %d', $limit );

        $rows = $wpdb->get_results( $sql, ARRAY_A );
        return array( 'count' => count( $rows ), 'items' => array_values( $rows ) );
    }

    /**
     * Formats a post summary for search results.
     */
    private function format_post_summary( int $post_id ): array {
        $post = get_post( $post_id );
        return array(
            'id'           => $post_id,
            'post_type'    => $post ? $post->post_type : '',
            'post_status'  => $post ? $post->post_status : '',
            'title'        => get_the_title( $post_id ),
            'slug'         => $post ? $post->post_name : '',
            'permalink'    => get_permalink( $post_id ),
            'modified_gmt' => $post ? $post->post_modified_gmt : '',
        );
    }

    private function posts_search_input_schema(): array {
        return array(
            'type'       => 'object',
            'properties' => array(
                'search'      => array( 'type' => 'string' ),
                'post_type'   => array( 'type' => 'string' ),
                'post_status' => array( 'type' => 'string' ),
                'limit'       => array( 'type' => 'integer' ),
            ),
        );
    }

    private function post_get_input_schema(): array {
        return array(
            'type'       => 'object',
            'properties' => array(
                'post_id' => array( 'type' => 'integer' ),
            ),
            'required'   => array( 'post_id' ),
        );
    }

    private function postmeta_input_schema(): array {
        return array(
            'type'       => 'object',
            'properties' => array(
                'meta_key_like' => array( 'type' => 'string' ),
                'limit'         => array( 'type' => 'integer' ),
            ),
        );
    }
}
