<?php

namespace WPGPT\MCPBridge;

use WP_Error;
use WP_Post;
use WP_Query;

if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

/**
 * Handles generalized post abilities.
 */
class Content_Provider extends Base_Ability_Provider {
    public function get_abilities(): array {
        return array(
            'wpgpt/posts-query'   => array(
                'label'               => __( 'Posts query', 'wpgpt-mcp-bridge' ),
                'description'         => __( 'Lista, filtra y resume entradas, páginas y CPTs.', 'wpgpt-mcp-bridge' ),
                'input_schema'        => $this->posts_query_input_schema(),
                'execute_callback'    => array( $this, 'posts_query' ),
                'output_schema'       => $this->object_schema(),
                'permission_callback' => array( $this, 'can_edit_content' ),
            ),
            'wpgpt/posts-inspect' => array(
                'label'               => __( 'Posts inspect', 'wpgpt-mcp-bridge' ),
                'description'         => __( 'Inspecciona uno o varios posts por ID o slug.', 'wpgpt-mcp-bridge' ),
                'input_schema'        => $this->posts_inspect_input_schema(),
                'execute_callback'    => array( $this, 'posts_inspect' ),
                'output_schema'       => $this->object_schema(),
                'permission_callback' => array( $this, 'can_edit_content' ),
            ),
        );
    }

    public function posts_query( array $input ): array {
        $filters = isset( $input['filters'] ) && is_array( $input['filters'] ) ? $input['filters'] : array();
        $search  = isset( $input['search'] ) ? sanitize_text_field( (string) $input['search'] ) : '';
        $limit   = isset( $input['limit'] ) ? max( 1, min( 100, (int) $input['limit'] ) ) : 20;
        $offset  = isset( $input['offset'] ) ? max( 0, (int) $input['offset'] ) : 0;

        $query_args = array(
            'post_type'           => $this->normalize_post_type_filter( $filters['post_type'] ?? 'any' ),
            'post_status'         => $this->normalize_post_status_filter( $filters['post_status'] ?? 'any' ),
            'posts_per_page'      => $limit,
            'offset'              => $offset,
            'fields'              => 'ids',
            'orderby'             => 'date',
            'order'               => 'DESC',
            'ignore_sticky_posts' => true,
            's'                   => $search,
        );

        if ( ! empty( $filters['post_id'] ) ) {
            $query_args['post__in'] = array( absint( $filters['post_id'] ) );
        }
        if ( ! empty( $filters['post_ids'] ) && is_array( $filters['post_ids'] ) ) {
            $query_args['post__in'] = array_values( array_filter( array_map( 'absint', $filters['post_ids'] ) ) );
        }
        if ( isset( $filters['author_id'] ) ) {
            $query_args['author'] = absint( $filters['author_id'] );
        }
        if ( isset( $filters['parent_id'] ) ) {
            $query_args['post_parent'] = absint( $filters['parent_id'] );
        }
        if ( ! empty( $filters['slug'] ) ) {
            $query_args['name'] = sanitize_title( (string) $filters['slug'] );
        }
        if ( array_key_exists( 'has_password', $filters ) ) {
            $query_args['has_password'] = (bool) $filters['has_password'];
        }

        $query = new WP_Query( $query_args );
        $items = array();
        $type_counts = array();
        $status_counts = array();

        foreach ( $query->posts as $post_id ) {
            $post = get_post( (int) $post_id );
            if ( ! $post ) {
                continue;
            }
            $items[] = $this->format_post_summary( $post );
            $type_counts[ $post->post_type ] = ( $type_counts[ $post->post_type ] ?? 0 ) + 1;
            $status_counts[ $post->post_status ] = ( $status_counts[ $post->post_status ] ?? 0 ) + 1;
        }

        return array(
            'summary'      => array(
                'matched'       => (int) $query->found_posts,
                'returned'      => count( $items ),
                'post_types'    => $type_counts,
                'post_statuses' => $status_counts,
                'offset'        => $offset,
                'limit'         => $limit,
            ),
            'items'        => $items,
            'warnings'     => empty( $items ) ? array( __( 'No se han encontrado posts con esos filtros.', 'wpgpt-mcp-bridge' ) ) : array(),
            'next_actions' => $query->found_posts > ( $offset + count( $items ) )
                ? array( sprintf( 'Usa offset=%d para continuar la consulta.', $offset + count( $items ) ) )
                : array(),
        );
    }

    public function posts_inspect( array $input ): array|WP_Error {
        $include_meta      = ! empty( $input['include_meta'] );
        $include_revisions = ! empty( $input['include_revisions'] );
        $ids               = $this->resolve_post_ids( $input );

        if ( empty( $ids ) ) {
            return new WP_Error( 'wpgpt_posts_not_found', __( 'Debes indicar al menos un post válido para inspeccionar.', 'wpgpt-mcp-bridge' ), array( 'status' => 404 ) );
        }

        $items = array();
        foreach ( $ids as $post_id ) {
            $post = get_post( $post_id );
            if ( ! $post ) {
                continue;
            }

            $item = array(
                'post_id'          => (int) $post->ID,
                'post_type'        => $post->post_type,
                'post_status'      => $post->post_status,
                'title'            => get_the_title( $post ),
                'slug'             => $post->post_name,
                'permalink'        => get_permalink( $post ),
                'author_id'        => (int) $post->post_author,
                'parent_id'        => (int) $post->post_parent,
                'menu_order'       => (int) $post->menu_order,
                'has_password'     => ! empty( $post->post_password ),
                'comment_status'   => $post->comment_status,
                'ping_status'      => $post->ping_status,
                'date_gmt'         => $post->post_date_gmt,
                'modified_gmt'     => $post->post_modified_gmt,
                'excerpt'          => wp_strip_all_tags( get_the_excerpt( $post ) ),
                'content_html'     => apply_filters( 'the_content', $post->post_content ),
                'available_actions'=> $this->available_actions_for_post( $post ),
                'risk_level'       => $this->risk_level_for_post( $post ),
            );

            if ( $include_meta ) {
                $item['meta'] = $this->safe_meta_dump( (int) $post->ID );
            }
            if ( $include_revisions ) {
                $item['revisions'] = $this->revisions_summary( (int) $post->ID );
            }

            $items[] = $item;
        }

        return array(
            'summary'      => array(
                'requested'  => count( $ids ),
                'inspected'  => count( $items ),
            ),
            'items'        => $items,
            'warnings'     => empty( $items ) ? array( __( 'No se han podido inspeccionar los posts solicitados.', 'wpgpt-mcp-bridge' ) ) : array(),
            'next_actions' => array( __( 'Usa wpgpt/posts-apply con dry_run=true antes de ejecutar cambios.', 'wpgpt-mcp-bridge' ) ),
        );
    }

    private function format_post_summary( WP_Post $post ): array {
        return array(
            'post_id'       => (int) $post->ID,
            'post_type'     => $post->post_type,
            'post_status'   => $post->post_status,
            'title'         => get_the_title( $post ),
            'slug'          => $post->post_name,
            'permalink'     => get_permalink( $post ),
            'author_id'     => (int) $post->post_author,
            'parent_id'     => (int) $post->post_parent,
            'modified_gmt'  => $post->post_modified_gmt,
            'date_gmt'      => $post->post_date_gmt,
        );
    }

    private function resolve_post_ids( array $input ): array {
        $ids = array();

        if ( ! empty( $input['post_id'] ) ) {
            $ids[] = absint( $input['post_id'] );
        }
        if ( ! empty( $input['post_ids'] ) && is_array( $input['post_ids'] ) ) {
            $ids = array_merge( $ids, array_values( array_filter( array_map( 'absint', $input['post_ids'] ) ) ) );
        }
        if ( ! empty( $input['slug'] ) ) {
            $post = get_page_by_path( sanitize_title( (string) $input['slug'] ), OBJECT, 'any' );
            if ( $post instanceof WP_Post ) {
                $ids[] = (int) $post->ID;
            }
        }
        if ( ! empty( $input['slugs'] ) && is_array( $input['slugs'] ) ) {
            foreach ( $input['slugs'] as $slug ) {
                $post = get_page_by_path( sanitize_title( (string) $slug ), OBJECT, 'any' );
                if ( $post instanceof WP_Post ) {
                    $ids[] = (int) $post->ID;
                }
            }
        }

        $ids = array_values( array_unique( array_filter( $ids ) ) );
        return $ids;
    }

    private function safe_meta_dump( int $post_id ): array {
        $raw_meta = get_post_meta( $post_id );
        $items    = array();

        foreach ( $raw_meta as $meta_key => $values ) {
            $value = is_array( $values ) ? reset( $values ) : $values;
            if ( is_string( $value ) && strlen( $value ) > 500 ) {
                $value = substr( $value, 0, 500 );
            }
            $items[] = array(
                'meta_key' => (string) $meta_key,
                'value'    => maybe_unserialize( $value ),
            );
        }

        return $items;
    }

    private function revisions_summary( int $post_id ): array {
        $revisions = wp_get_post_revisions( $post_id );
        $items = array();
        foreach ( $revisions as $revision ) {
            $items[] = array(
                'revision_id'  => (int) $revision->ID,
                'modified_gmt' => $revision->post_modified_gmt,
                'author_id'    => (int) $revision->post_author,
            );
        }
        return $items;
    }

    private function available_actions_for_post( WP_Post $post ): array {
        $actions = array( 'update', 'delete', 'set_status', 'set_slug', 'meta_update', 'meta_delete' );
        if ( post_type_supports( $post->post_type, 'revisions' ) ) {
            $actions[] = 'revision_restore';
        }
        $actions[] = 'duplicate';
        return $actions;
    }

    private function risk_level_for_post( WP_Post $post ): string {
        if ( in_array( $post->post_status, array( 'publish', 'future' ), true ) ) {
            return 'medium';
        }
        return 'low';
    }

    private function normalize_post_type_filter( $value ) {
        if ( is_array( $value ) ) {
            $types = array_values( array_filter( array_map( 'sanitize_key', $value ) ) );
            return empty( $types ) ? 'any' : $types;
        }
        $value = sanitize_key( (string) $value );
        return '' === $value || 'any' === $value ? 'any' : $value;
    }

    private function normalize_post_status_filter( $value ) {
        if ( is_array( $value ) ) {
            $statuses = array_values( array_filter( array_map( 'sanitize_key', $value ) ) );
            return empty( $statuses ) ? array_keys( get_post_stati() ) : $statuses;
        }
        $value = sanitize_key( (string) $value );
        return '' === $value || 'any' === $value ? array_keys( get_post_stati() ) : $value;
    }

    private function posts_query_input_schema(): array {
        return array(
            'type'                 => 'object',
            'additionalProperties' => false,
            'properties'           => array(
                'search'  => array( 'type' => 'string' ),
                'filters' => array(
                    'type'                 => 'object',
                    'additionalProperties' => false,
                    'properties'           => array(
                        'post_id'      => array( 'type' => 'integer' ),
                        'post_ids'     => array( 'type' => 'array', 'items' => array( 'type' => 'integer' ) ),
                        'post_type'    => array( 'oneOf' => array( array( 'type' => 'string' ), array( 'type' => 'array', 'items' => array( 'type' => 'string' ) ) ) ),
                        'post_status'  => array( 'oneOf' => array( array( 'type' => 'string' ), array( 'type' => 'array', 'items' => array( 'type' => 'string' ) ) ) ),
                        'author_id'    => array( 'type' => 'integer' ),
                        'parent_id'    => array( 'type' => 'integer' ),
                        'slug'         => array( 'type' => 'string' ),
                        'has_password' => array( 'type' => 'boolean' ),
                    ),
                ),
                'limit'   => array( 'type' => 'integer', 'minimum' => 1, 'maximum' => 100 ),
                'offset'  => array( 'type' => 'integer', 'minimum' => 0 ),
            ),
        );
    }

    private function posts_inspect_input_schema(): array {
        return array(
            'type'                 => 'object',
            'additionalProperties' => false,
            'properties'           => array(
                'post_id'           => array( 'type' => 'integer' ),
                'post_ids'          => array( 'type' => 'array', 'items' => array( 'type' => 'integer' ) ),
                'slug'              => array( 'type' => 'string' ),
                'slugs'             => array( 'type' => 'array', 'items' => array( 'type' => 'string' ) ),
                'include_meta'      => array( 'type' => 'boolean' ),
                'include_revisions' => array( 'type' => 'boolean' ),
            ),
        );
    }
}
