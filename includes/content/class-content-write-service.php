<?php

namespace WPGPT\MCPBridge\Content;

use WP_Error;

if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

class Content_Write_Service {
    public function create_post( array $input ): array|WP_Error {
        $postarr = $this->normalize_post_input( $input, false );
        if ( is_wp_error( $postarr ) ) {
            return $postarr;
        }

        $post_id = wp_insert_post( $postarr, true, false );
        if ( is_wp_error( $post_id ) ) {
            return $post_id;
        }

        $meta_result = $this->apply_meta( (int) $post_id, $input['meta'] ?? array() );
        if ( is_wp_error( $meta_result ) ) {
            return $meta_result;
        }

        return $this->format_post_result( (int) $post_id, 'created' );
    }

    public function update_post( array $input ): array|WP_Error {
        $post_id = isset( $input['post_id'] ) ? absint( $input['post_id'] ) : 0;
        if ( $post_id <= 0 || ! get_post( $post_id ) ) {
            return new WP_Error( 'wpgpt_post_not_found', __( 'Debes indicar un post válido.', 'wpgpt-mcp-bridge' ), array( 'status' => 404 ) );
        }

        $postarr = $this->normalize_post_input( $input, true );
        if ( is_wp_error( $postarr ) ) {
            return $postarr;
        }

        $postarr['ID'] = $post_id;
        $updated_id    = wp_update_post( $postarr, true, false );
        if ( is_wp_error( $updated_id ) ) {
            return $updated_id;
        }

        if ( array_key_exists( 'meta', $input ) ) {
            $meta_result = $this->apply_meta( $post_id, $input['meta'] );
            if ( is_wp_error( $meta_result ) ) {
                return $meta_result;
            }
        }

        return $this->format_post_result( $post_id, 'updated' );
    }

    public function duplicate_post( array $input ): array|WP_Error {
        $post_id = absint( $input['post_id'] ?? 0 );
        $source = get_post( $post_id );
        if ( ! $source ) {
            return new WP_Error( 'wpgpt_post_not_found', __( 'Debes indicar un post válido.', 'wpgpt-mcp-bridge' ), array( 'status' => 404 ) );
        }
        $new_id = wp_insert_post( array(
            'post_type' => $source->post_type,
            'post_status' => sanitize_text_field( (string) ( $input['status'] ?? 'draft' ) ),
            'post_title' => sanitize_text_field( (string) ( $input['title'] ?? ( $source->post_title . ' (Copy)' ) ) ),
            'post_content' => $source->post_content,
            'post_excerpt' => $source->post_excerpt,
            'post_parent' => $source->post_parent,
            'menu_order' => $source->menu_order,
        ), true, false );
        if ( is_wp_error( $new_id ) ) { return $new_id; }
        $meta = get_post_meta( $post_id );
        foreach ( $meta as $key => $values ) {
            foreach ( $values as $value ) {
                add_post_meta( $new_id, $key, maybe_unserialize( $value ) );
            }
        }
        $terms = wp_get_object_terms( $post_id, get_object_taxonomies( $source->post_type ), array( 'fields' => 'ids' ) );
        if ( ! is_wp_error( $terms ) ) {
            foreach ( get_object_taxonomies( $source->post_type ) as $taxonomy ) {
                $tax_terms = wp_get_object_terms( $post_id, $taxonomy, array( 'fields' => 'ids' ) );
                if ( ! is_wp_error( $tax_terms ) ) {
                    wp_set_object_terms( $new_id, $tax_terms, $taxonomy, false );
                }
            }
        }
        return $this->format_post_result( (int) $new_id, 'duplicated' );
    }

    public function list_revisions( array $input ): array|WP_Error {
        $post_id = absint( $input['post_id'] ?? 0 );
        if ( $post_id <= 0 || ! get_post( $post_id ) ) {
            return new WP_Error( 'wpgpt_post_not_found', __( 'Debes indicar un post válido.', 'wpgpt-mcp-bridge' ), array( 'status' => 404 ) );
        }
        $revisions = wp_get_post_revisions( $post_id );
        $items = array();
        foreach ( $revisions as $revision ) {
            $items[] = array( 'revision_id' => (int) $revision->ID, 'post_id' => (int) $revision->post_parent, 'title' => $revision->post_title, 'modified_gmt' => $revision->post_modified_gmt, 'author' => (int) $revision->post_author );
        }
        return array( 'count' => count( $items ), 'items' => array_values( $items ) );
    }

    public function restore_revision( array $input ): array|WP_Error {
        $revision_id = absint( $input['revision_id'] ?? 0 );
        if ( $revision_id <= 0 || 'revision' !== get_post_type( $revision_id ) ) {
            return new WP_Error( 'wpgpt_revision_invalid', __( 'Debes indicar una revisión válida.', 'wpgpt-mcp-bridge' ), array( 'status' => 400 ) );
        }
        wp_restore_post_revision( $revision_id );
        $revision = get_post( $revision_id );
        return $this->format_post_result( (int) $revision->post_parent, 'revision_restored' );
    }

    public function bulk_status_update( array $input ): array|WP_Error {
        $post_ids = isset( $input['post_ids'] ) && is_array( $input['post_ids'] ) ? array_values( array_filter( array_map( 'absint', $input['post_ids'] ) ) ) : array();
        $status = sanitize_text_field( (string) ( $input['status'] ?? '' ) );
        if ( empty( $post_ids ) || '' === $status ) {
            return new WP_Error( 'wpgpt_bulk_invalid', __( 'Debes indicar post_ids y status.', 'wpgpt-mcp-bridge' ), array( 'status' => 400 ) );
        }
        $updated = array();
        foreach ( $post_ids as $post_id ) {
            $result = wp_update_post( array( 'ID' => $post_id, 'post_status' => $status ), true, false );
            if ( ! is_wp_error( $result ) ) { $updated[] = (int) $post_id; }
        }
        return array( 'updated' => true, 'status' => $status, 'count' => count( $updated ), 'post_ids' => $updated );
    }

    public function update_slug( array $input ): array|WP_Error {
        $post_id = absint( $input['post_id'] ?? 0 );
        $slug = sanitize_title( (string) ( $input['slug'] ?? '' ) );
        if ( $post_id <= 0 || '' === $slug ) {
            return new WP_Error( 'wpgpt_slug_invalid', __( 'Debes indicar post_id y slug válidos.', 'wpgpt-mcp-bridge' ), array( 'status' => 400 ) );
        }
        $result = wp_update_post( array( 'ID' => $post_id, 'post_name' => $slug ), true, false );
        if ( is_wp_error( $result ) ) { return $result; }
        return $this->format_post_result( $post_id, 'slug_updated' );
    }

    public function delete_post( array $input ): array|WP_Error {
        $post_id = isset( $input['post_id'] ) ? absint( $input['post_id'] ) : 0;
        if ( $post_id <= 0 ) {
            return new WP_Error( 'wpgpt_post_not_found', __( 'Debes indicar un post válido.', 'wpgpt-mcp-bridge' ), array( 'status' => 404 ) );
        }

        $post = get_post( $post_id );
        if ( ! $post ) {
            return new WP_Error( 'wpgpt_post_not_found', __( 'Debes indicar un post válido.', 'wpgpt-mcp-bridge' ), array( 'status' => 404 ) );
        }

        $force   = ! empty( $input['force'] );
        $deleted = wp_delete_post( $post_id, $force );
        if ( ! $deleted ) {
            return new WP_Error( 'wpgpt_post_delete_failed', __( 'No se pudo eliminar el contenido indicado.', 'wpgpt-mcp-bridge' ), array( 'status' => 500 ) );
        }

        return array(
            'deleted'     => true,
            'force'       => $force,
            'post_id'     => $post_id,
            'post_type'   => $post->post_type,
            'post_status' => $post->post_status,
            'title'       => get_the_title( $post ),
        );
    }

    public function get_post_meta( array $input ): array|WP_Error {
        $post_id = isset( $input['post_id'] ) ? absint( $input['post_id'] ) : 0;
        if ( $post_id <= 0 || ! get_post( $post_id ) ) {
            return new WP_Error( 'wpgpt_post_not_found', __( 'Debes indicar un post válido.', 'wpgpt-mcp-bridge' ), array( 'status' => 404 ) );
        }

        $meta_key = isset( $input['meta_key'] ) ? sanitize_key( (string) $input['meta_key'] ) : '';
        if ( '' !== $meta_key ) {
            return array(
                'post_id'  => $post_id,
                'meta_key' => $meta_key,
                'value'    => get_post_meta( $post_id, $meta_key, true ),
            );
        }

        $all_meta = get_post_meta( $post_id );
        $items    = array();
        foreach ( $all_meta as $key => $values ) {
            $items[] = array(
                'meta_key' => (string) $key,
                'value'    => maybe_unserialize( is_array( $values ) ? reset( $values ) : $values ),
            );
        }

        return array(
            'post_id' => $post_id,
            'count'   => count( $items ),
            'items'   => $items,
        );
    }

    public function update_post_meta( array $input ): array|WP_Error {
        $post_id = isset( $input['post_id'] ) ? absint( $input['post_id'] ) : 0;
        if ( $post_id <= 0 || ! get_post( $post_id ) ) {
            return new WP_Error( 'wpgpt_post_not_found', __( 'Debes indicar un post válido.', 'wpgpt-mcp-bridge' ), array( 'status' => 404 ) );
        }

        $meta_key = isset( $input['meta_key'] ) ? sanitize_key( (string) $input['meta_key'] ) : '';
        if ( '' === $meta_key ) {
            return new WP_Error( 'wpgpt_meta_key_required', __( 'Debes indicar meta_key.', 'wpgpt-mcp-bridge' ), array( 'status' => 400 ) );
        }

        $value = $input['value'] ?? '';
        update_post_meta( $post_id, $meta_key, $this->normalize_meta_value( $value ) );

        return array(
            'post_id'  => $post_id,
            'meta_key' => $meta_key,
            'value'    => get_post_meta( $post_id, $meta_key, true ),
            'updated'  => true,
        );
    }

    public function delete_post_meta( array $input ): array|WP_Error {
        $post_id = isset( $input['post_id'] ) ? absint( $input['post_id'] ) : 0;
        if ( $post_id <= 0 || ! get_post( $post_id ) ) {
            return new WP_Error( 'wpgpt_post_not_found', __( 'Debes indicar un post válido.', 'wpgpt-mcp-bridge' ), array( 'status' => 404 ) );
        }

        $meta_key = isset( $input['meta_key'] ) ? sanitize_key( (string) $input['meta_key'] ) : '';
        if ( '' === $meta_key ) {
            return new WP_Error( 'wpgpt_meta_key_required', __( 'Debes indicar meta_key.', 'wpgpt-mcp-bridge' ), array( 'status' => 400 ) );
        }

        $deleted = delete_post_meta( $post_id, $meta_key );

        return array(
            'post_id'  => $post_id,
            'meta_key' => $meta_key,
            'deleted'  => (bool) $deleted,
        );
    }

    private function normalize_post_input( array $input, bool $is_update ): array|WP_Error {
        $postarr = array();
        $map     = array(
            'title'     => 'post_title',
            'content'   => 'post_content',
            'excerpt'   => 'post_excerpt',
            'slug'      => 'post_name',
            'status'    => 'post_status',
            'post_type' => 'post_type',
        );

        foreach ( $map as $input_key => $post_key ) {
            if ( ! array_key_exists( $input_key, $input ) ) {
                continue;
            }
            $value = $input[ $input_key ];
            if ( in_array( $input_key, array( 'content', 'excerpt' ), true ) ) {
                $postarr[ $post_key ] = wp_kses_post( (string) $value );
            } elseif ( in_array( $input_key, array( 'status', 'post_type', 'slug' ), true ) ) {
                $postarr[ $post_key ] = sanitize_key( (string) $value );
            } else {
                $postarr[ $post_key ] = sanitize_text_field( (string) $value );
            }
        }

        if ( ! $is_update ) {
            $postarr['post_type']   = $postarr['post_type'] ?? 'post';
            $postarr['post_status'] = $postarr['post_status'] ?? 'draft';
            if ( empty( $postarr['post_title'] ) ) {
                return new WP_Error( 'wpgpt_post_title_required', __( 'Debes indicar un título.', 'wpgpt-mcp-bridge' ), array( 'status' => 400 ) );
            }
        }

        if ( isset( $postarr['post_type'] ) && ! post_type_exists( $postarr['post_type'] ) ) {
            return new WP_Error( 'wpgpt_invalid_post_type', __( 'El post_type indicado no existe.', 'wpgpt-mcp-bridge' ), array( 'status' => 400 ) );
        }

        if ( empty( $postarr ) ) {
            return new WP_Error( 'wpgpt_empty_post_update', __( 'No se han recibido cambios válidos.', 'wpgpt-mcp-bridge' ), array( 'status' => 400 ) );
        }

        return $postarr;
    }

    private function apply_meta( int $post_id, $meta ): bool|WP_Error {
        if ( ! is_array( $meta ) ) {
            return true;
        }

        foreach ( $meta as $meta_key => $value ) {
            $meta_key = sanitize_key( (string) $meta_key );
            if ( '' === $meta_key ) {
                continue;
            }
            update_post_meta( $post_id, $meta_key, $this->normalize_meta_value( $value ) );
        }

        return true;
    }

    private function normalize_meta_value( $value ) {
        if ( is_scalar( $value ) || null === $value ) {
            return $value;
        }

        return wp_json_encode( $value );
    }

    private function format_post_result( int $post_id, string $action ): array {
        $post = get_post( $post_id );
        return array(
            'action'       => $action,
            'post_id'      => $post_id,
            'post_type'    => $post ? $post->post_type : '',
            'post_status'  => $post ? $post->post_status : '',
            'title'        => get_the_title( $post_id ),
            'slug'         => $post ? $post->post_name : '',
            'permalink'    => get_permalink( $post_id ),
            'modified_gmt' => $post ? $post->post_modified_gmt : '',
        );
    }
}
