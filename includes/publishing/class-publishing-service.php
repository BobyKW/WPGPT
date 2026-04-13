<?php

namespace WPGPT\MCPBridge\Publishing;

use WP_Error;

if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

class Publishing_Service {
    public function query( array $input = array() ): array|WP_Error {
        $filters = isset( $input['filters'] ) && is_array( $input['filters'] ) ? $input['filters'] : array();
        $search  = isset( $input['search'] ) ? sanitize_text_field( (string) $input['search'] ) : '';
        $limit   = isset( $input['limit'] ) ? max( 1, min( 200, (int) $input['limit'] ) ) : 100;
        $offset  = isset( $input['offset'] ) ? max( 0, (int) $input['offset'] ) : 0;

        $items   = $this->build_term_inventory( $filters );
        $matched = array_values( array_filter( $items, fn( $item ) => $this->term_matches_filters( $item, $filters, $search ) ) );
        $paged   = array_slice( $matched, $offset, $limit );

        return array(
            'summary' => array(
                'total_terms' => count( $items ),
                'matched' => count( $matched ),
                'returned' => count( $paged ),
                'taxonomies' => $this->count_terms_by_taxonomy( $matched ),
                'offset' => $offset,
                'limit' => $limit,
            ),
            'items' => $paged,
            'warnings' => empty( $matched ) ? array( __( 'No se han encontrado términos con esos filtros.', 'wpgpt-mcp-bridge' ) ) : array(),
            'next_actions' => count( $matched ) > $offset + count( $paged ) ? array( 'Usa offset=' . ( $offset + count( $paged ) ) . ' para continuar la consulta.' ) : array(),
        );
    }

    public function inspect( array $input = array() ): array|WP_Error {
        $targets = $this->collect_term_targets( $input );
        if ( empty( $targets ) ) {
            return new WP_Error( 'wpgpt_term_target_required', __( 'Debes indicar al menos un término por taxonomy y term_id.', 'wpgpt-mcp-bridge' ), array( 'status' => 400 ) );
        }

        $items = array();
        $warnings = array();
        foreach ( $targets as $target ) {
            $term = $this->resolve_term_target( $target );
            if ( ! $term ) {
                $warnings[] = __( 'No se ha encontrado uno de los términos solicitados.', 'wpgpt-mcp-bridge' );
                continue;
            }

            $item = $this->format_term( $term );
            $item['available_actions'] = array( 'update', 'assign_to_post', 'remove_from_post', 'delete' );
            $item['risk_level'] = in_array( $term->taxonomy, array( 'category', 'post_tag' ), true ) ? 'low' : 'medium';
            $items[] = $item;
        }

        return array(
            'summary' => array( 'requested' => count( $targets ), 'inspected' => count( $items ) ),
            'items' => $items,
            'warnings' => array_values( array_unique( array_filter( $warnings ) ) ),
            'next_actions' => empty( $items ) ? array() : array( __( 'Usa wpgpt/terms-apply con dry_run=true antes de ejecutar cambios.', 'wpgpt-mcp-bridge' ) ),
        );
    }

    public function apply( array $input = array() ): array|WP_Error {
        $action  = isset( $input['action'] ) ? sanitize_key( (string) $input['action'] ) : '';
        $dry_run = ! empty( $input['dry_run'] );
        $payload = isset( $input['payload'] ) && is_array( $input['payload'] ) ? $input['payload'] : array();

        if ( ! in_array( $action, array( 'create', 'update', 'assign_to_post', 'remove_from_post', 'delete' ), true ) ) {
            return new WP_Error( 'wpgpt_term_action_invalid', __( 'La acción indicada no es válida.', 'wpgpt-mcp-bridge' ), array( 'status' => 400 ) );
        }

        $targets = $this->resolve_term_apply_targets( $action, $input, $payload );
        if ( empty( $targets ) ) {
            return new WP_Error( 'wpgpt_term_apply_target_required', __( 'No se han resuelto términos objetivo para la acción indicada.', 'wpgpt-mcp-bridge' ), array( 'status' => 400 ) );
        }

        $items = array();
        $blocked = array();
        $executed = 0;
        foreach ( $targets as $target ) {
            $validation = $this->validate_term_action( $action, $target, $payload );
            if ( ! empty( $validation ) ) {
                $blocked[] = array( 'target' => $target, 'reasons' => $validation );
                continue;
            }

            if ( $dry_run ) {
                $items[] = array( 'target' => $target, 'status' => 'dry_run', 'action' => $action, 'message' => __( 'Acción validada, no ejecutada por dry_run.', 'wpgpt-mcp-bridge' ) );
                continue;
            }

            switch ( $action ) {
                case 'create':
                    $result = $this->create_term( $payload );
                    break;
                case 'update':
                    $result = $this->update_term_data( $payload + array( 'taxonomy' => (string) $target['taxonomy'], 'term_id' => (int) $target['term_id'] ) );
                    break;
                case 'assign_to_post':
                    $result = $this->assign_term_to_post( $payload + array( 'taxonomy' => (string) $target['taxonomy'], 'term_id' => (int) $target['term_id'] ) );
                    break;
                case 'remove_from_post':
                    $result = $this->remove_term_from_post( $payload + array( 'taxonomy' => (string) $target['taxonomy'], 'term_id' => (int) $target['term_id'] ) );
                    break;
                case 'delete':
                    $result = $this->delete_term( array( 'taxonomy' => (string) $target['taxonomy'], 'term_id' => (int) $target['term_id'] ) );
                    break;
                default:
                    $result = new WP_Error( 'wpgpt_term_action_invalid', __( 'La acción indicada no es válida.', 'wpgpt-mcp-bridge' ), array( 'status' => 400 ) );
            }

            if ( is_wp_error( $result ) ) {
                $blocked[] = array( 'target' => $target, 'reasons' => array( $result->get_error_message() ) );
                continue;
            }

            $executed++;
            $items[] = array( 'target' => $target, 'status' => 'applied', 'action' => $action, 'result' => $result );
        }

        return array(
            'summary' => array( 'action' => $action, 'dry_run' => $dry_run, 'resolved_targets' => count( $targets ), 'executed' => $executed, 'blocked' => count( $blocked ) ),
            'items' => $items,
            'warnings' => array(),
            'blocked' => $blocked,
            'next_actions' => $dry_run ? array( __( 'Repite la misma llamada con dry_run=false para aplicar los cambios validados.', 'wpgpt-mcp-bridge' ) ) : array(),
        );
    }

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
        return $this->format_term( $term );
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

    private function build_term_inventory( array $filters = array() ): array {
        $taxonomies = array();
        if ( ! empty( $filters['taxonomy'] ) ) {
            $taxonomy = sanitize_key( (string) $filters['taxonomy'] );
            if ( taxonomy_exists( $taxonomy ) ) {
                $taxonomies[] = $taxonomy;
            }
        }
        if ( empty( $taxonomies ) ) {
            $taxonomies = array_values( get_taxonomies( array( 'public' => true ), 'names' ) );
        }

        $hide_empty = ! empty( $filters['hide_empty'] );
        $items = array();
        foreach ( $taxonomies as $taxonomy ) {
            $terms = get_terms( array( 'taxonomy' => $taxonomy, 'hide_empty' => $hide_empty, 'number' => 0 ) );
            if ( is_wp_error( $terms ) ) {
                continue;
            }
            foreach ( $terms as $term ) {
                $items[] = $this->format_term( $term );
            }
        }
        return $items;
    }

    private function term_matches_filters( array $item, array $filters, string $search ): bool {
        if ( isset( $filters['taxonomy'] ) && sanitize_key( (string) $filters['taxonomy'] ) !== (string) $item['taxonomy'] ) {
            return false;
        }
        if ( isset( $filters['term_id'] ) && (int) $filters['term_id'] !== (int) $item['term_id'] ) {
            return false;
        }
        if ( isset( $filters['slug'] ) && sanitize_title( (string) $filters['slug'] ) !== (string) $item['slug'] ) {
            return false;
        }
        if ( isset( $filters['parent'] ) && (int) $filters['parent'] !== (int) $item['parent'] ) {
            return false;
        }
        if ( '' !== $search ) {
            $haystack = strtolower( implode( ' ', array( (string) $item['name'], (string) $item['slug'], (string) $item['taxonomy'], (string) $item['description'] ) ) );
            if ( ! str_contains( $haystack, strtolower( $search ) ) ) {
                return false;
            }
        }
        return true;
    }

    private function count_terms_by_taxonomy( array $items ): array {
        $counts = array();
        foreach ( $items as $item ) {
            $key = (string) $item['taxonomy'];
            $counts[ $key ] = ( $counts[ $key ] ?? 0 ) + 1;
        }
        ksort( $counts );
        return $counts;
    }

    private function collect_term_targets( array $input ): array {
        $targets = array();
        if ( ! empty( $input['taxonomy'] ) && ! empty( $input['term_id'] ) ) {
            $targets[] = array( 'taxonomy' => sanitize_key( (string) $input['taxonomy'] ), 'term_id' => absint( $input['term_id'] ) );
        }
        if ( isset( $input['targets'] ) && is_array( $input['targets'] ) ) {
            foreach ( $input['targets'] as $target ) {
                if ( is_array( $target ) && ! empty( $target['taxonomy'] ) && ! empty( $target['term_id'] ) ) {
                    $targets[] = array( 'taxonomy' => sanitize_key( (string) $target['taxonomy'] ), 'term_id' => absint( $target['term_id'] ) );
                }
            }
        }
        $unique = array();
        foreach ( $targets as $target ) {
            if ( $target['term_id'] <= 0 || '' === $target['taxonomy'] ) {
                continue;
            }
            $unique[ $target['taxonomy'] . ':' . $target['term_id'] ] = $target;
        }
        return array_values( $unique );
    }

    private function resolve_term_target( array $target ): ?\WP_Term {
        $taxonomy = sanitize_key( (string) ( $target['taxonomy'] ?? '' ) );
        $term_id  = absint( $target['term_id'] ?? 0 );
        if ( '' === $taxonomy || ! taxonomy_exists( $taxonomy ) || $term_id <= 0 ) {
            return null;
        }
        $term = get_term( $term_id, $taxonomy );
        return ( $term && ! is_wp_error( $term ) ) ? $term : null;
    }

    private function resolve_term_apply_targets( string $action, array $input, array $payload ): array {
        if ( 'create' === $action ) {
            return array( array( 'taxonomy' => sanitize_key( (string) ( $payload['taxonomy'] ?? '' ) ) ) );
        }

        $targets = array();
        if ( isset( $input['targets'] ) && is_array( $input['targets'] ) ) {
            foreach ( $this->collect_term_targets( array( 'targets' => $input['targets'] ) ) as $target ) {
                $targets[] = $target;
            }
        }
        if ( empty( $targets ) && ! empty( $input['filters'] ) && is_array( $input['filters'] ) ) {
            $matched = $this->query( array( 'filters' => $input['filters'], 'search' => (string) ( $input['filters']['search'] ?? '' ), 'limit' => 200, 'offset' => 0 ) );
            if ( is_array( $matched ) && isset( $matched['items'] ) && is_array( $matched['items'] ) ) {
                foreach ( $matched['items'] as $item ) {
                    $targets[] = array( 'taxonomy' => (string) $item['taxonomy'], 'term_id' => (int) $item['term_id'] );
                }
            }
        }
        if ( empty( $targets ) && ! empty( $payload['taxonomy'] ) && ! empty( $payload['term_id'] ) ) {
            $targets[] = array( 'taxonomy' => sanitize_key( (string) $payload['taxonomy'] ), 'term_id' => absint( $payload['term_id'] ) );
        }
        return $targets;
    }

    private function validate_term_action( string $action, array $target, array $payload ): array {
        $reasons = array();
        if ( 'create' === $action ) {
            if ( empty( $payload['taxonomy'] ) || ! taxonomy_exists( sanitize_key( (string) $payload['taxonomy'] ) ) ) {
                $reasons[] = __( 'Para crear debes indicar una taxonomía válida.', 'wpgpt-mcp-bridge' );
            }
            if ( empty( $payload['name'] ) ) {
                $reasons[] = __( 'Para crear debes indicar name.', 'wpgpt-mcp-bridge' );
            }
            return $reasons;
        }

        $term = $this->resolve_term_target( $target );
        if ( ! $term ) {
            $reasons[] = __( 'El término objetivo no existe.', 'wpgpt-mcp-bridge' );
            return $reasons;
        }
        if ( in_array( $action, array( 'assign_to_post', 'remove_from_post' ), true ) && empty( $payload['post_id'] ) ) {
            $reasons[] = __( 'Debes indicar post_id para gestionar la asignación.', 'wpgpt-mcp-bridge' );
        }
        return $reasons;
    }

    private function format_term( \WP_Term $term ): array {
        return array(
            'taxonomy' => (string) $term->taxonomy,
            'term_id' => (int) $term->term_id,
            'name' => (string) $term->name,
            'slug' => (string) $term->slug,
            'description' => (string) $term->description,
            'count' => (int) $term->count,
            'parent' => (int) $term->parent,
        );
    }
}
