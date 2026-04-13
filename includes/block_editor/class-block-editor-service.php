<?php

namespace WPGPT\MCPBridge\BlockEditor;

use WP_Error;

if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

class Block_Editor_Service {
    private array $allowed = array( 'wp_template', 'wp_template_part', 'wp_block', 'wp_navigation' );

    public function query( array $input = array() ) {
        $filters = isset( $input['filters'] ) && is_array( $input['filters'] ) ? $input['filters'] : array();
        $post_type = sanitize_key( (string) ( $filters['entity_type'] ?? 'wp_block' ) );
        if ( ! in_array( $post_type, $this->allowed, true ) ) {
            return new WP_Error( 'wpgpt_block_entity_invalid', __( 'Entidad de bloques no soportada.', 'wpgpt-mcp-bridge' ), array( 'status' => 400 ) );
        }
        $offset = max( 0, (int) ( $input['offset'] ?? 0 ) );
        $limit = min( 100, max( 1, (int) ( $input['limit'] ?? 20 ) ) );
        $q = new \WP_Query( array( 'post_type' => $post_type, 'post_status' => sanitize_key( (string) ( $filters['status'] ?? 'any' ) ), 's' => sanitize_text_field( (string) ( $filters['search'] ?? '' ) ), 'posts_per_page' => $limit, 'offset' => $offset ) );
        $items = array_map( array( $this, 'normalize_post' ), $q->posts );
        return array( 'summary' => array( 'entity_type' => $post_type, 'matched' => (int) $q->found_posts, 'returned' => count( $items ), 'offset' => $offset, 'limit' => $limit ), 'items' => $items, 'warnings' => empty( $items ) ? array( __( 'No se han encontrado entidades de bloques con esos filtros.', 'wpgpt-mcp-bridge' ) ) : array(), 'next_actions' => ( $q->found_posts > ( $offset + count( $items ) ) ) ? array( 'Usa offset=' . ( $offset + count( $items ) ) . ' para continuar la consulta.' ) : array() );
    }

    public function inspect( array $input = array() ) {
        $entity_type = sanitize_key( (string) ( $input['entity_type'] ?? '' ) );
        $ids = array();
        if ( ! empty( $input['id'] ) ) { $ids[] = absint( $input['id'] ); }
        foreach ( (array) ( $input['ids'] ?? array() ) as $id ) { $ids[] = absint( $id ); }
        $ids = array_values( array_unique( array_filter( $ids ) ) );
        $items = array(); $warnings = array();
        foreach ( $ids as $id ) {
            $post = get_post( $id );
            if ( ! $post ) { $warnings[] = sprintf( __( 'No se ha encontrado la entidad %d.', 'wpgpt-mcp-bridge' ), $id ); continue; }
            if ( $entity_type && $post->post_type !== $entity_type ) { $warnings[] = sprintf( __( 'La entidad %d no coincide con el tipo solicitado.', 'wpgpt-mcp-bridge' ), $id ); continue; }
            $item = $this->normalize_post( $post );
            $item['available_actions'] = array( 'upsert', 'delete' );
            $item['risk_level'] = 'medium';
            $items[] = $item;
        }
        return array( 'summary' => array( 'requested' => count( $ids ), 'inspected' => count( $items ) ), 'items' => $items, 'warnings' => array_values( array_unique( array_filter( $warnings ) ) ), 'next_actions' => $items ? array( __( 'Usa wpgpt/blocks-apply con dry_run=true antes de ejecutar cambios.', 'wpgpt-mcp-bridge' ) ) : array() );
    }

    public function apply( array $input = array() ) {
        $action = sanitize_key( (string) ( $input['action'] ?? '' ) );
        $dry_run = (bool) ( $input['dry_run'] ?? false );
        $targets = is_array( $input['targets'] ?? null ) ? $input['targets'] : array();
        $payload = is_array( $input['payload'] ?? null ) ? $input['payload'] : array();
        $items = array(); $blocked = array();
        if ( ! in_array( $action, array( 'upsert', 'delete' ), true ) ) {
            return new WP_Error( 'wpgpt_block_action_invalid', __( 'La acción de bloques indicada no es válida.', 'wpgpt-mcp-bridge' ), array( 'status' => 400 ) );
        }
        if ( 'upsert' === $action ) {
            $entity_type = sanitize_key( (string) ( $payload['entity_type'] ?? '' ) );
            if ( ! in_array( $entity_type, $this->allowed, true ) || '' === (string) ( $payload['content'] ?? '' ) ) {
                $blocked[] = array( 'action' => $action, 'reason' => __( 'Debes indicar payload.entity_type válido y payload.content.', 'wpgpt-mcp-bridge' ) );
            } elseif ( $dry_run ) {
                $items[] = array( 'action' => $action, 'status' => 'dry_run', 'message' => __( 'Acción validada, no ejecutada por dry_run.', 'wpgpt-mcp-bridge' ) );
            } else {
                $postarr = array( 'ID' => absint( $payload['id'] ?? 0 ), 'post_type' => $entity_type, 'post_title' => sanitize_text_field( (string) ( $payload['title'] ?? '' ) ), 'post_name' => sanitize_title( (string) ( $payload['slug'] ?? '' ) ), 'post_status' => sanitize_key( (string) ( $payload['status'] ?? 'publish' ) ), 'post_content' => (string) ( $payload['content'] ?? '' ) );
                $post_id = wp_insert_post( wp_slash( $postarr ), true );
                if ( is_wp_error( $post_id ) ) { $blocked[] = array( 'action' => $action, 'reason' => $post_id->get_error_message() ); }
                else { $items[] = array( 'action' => $action, 'status' => 'updated', 'item' => $this->normalize_post( get_post( $post_id ) ) ); }
            }
        }
        if ( 'delete' === $action ) {
            foreach ( $targets as $target ) {
                $id = absint( $target['id'] ?? 0 );
                if ( $id <= 0 || ! get_post( $id ) ) { $blocked[] = array( 'id' => $id, 'reason' => __( 'Entidad de bloques no válida.', 'wpgpt-mcp-bridge' ) ); continue; }
                if ( $dry_run ) { $items[] = array( 'id' => $id, 'action' => $action, 'status' => 'dry_run', 'message' => __( 'Acción validada, no ejecutada por dry_run.', 'wpgpt-mcp-bridge' ) ); continue; }
                $deleted = wp_delete_post( $id, ! empty( $payload['force'] ) );
                if ( ! $deleted ) { $blocked[] = array( 'id' => $id, 'reason' => __( 'No se pudo borrar la entidad.', 'wpgpt-mcp-bridge' ) ); }
                else { $items[] = array( 'id' => $id, 'action' => $action, 'status' => 'deleted' ); }
            }
        }
        return array( 'summary' => array( 'action' => $action, 'dry_run' => $dry_run, 'resolved_targets' => count( $targets ), 'executed' => count( $items ), 'blocked' => count( $blocked ) ), 'items' => $items, 'warnings' => array(), 'blocked' => $blocked, 'next_actions' => $dry_run ? array( __( 'Repite la misma llamada con dry_run=false para aplicar los cambios validados.', 'wpgpt-mcp-bridge' ) ) : array() );
    }

    private function normalize_post( \WP_Post $post ): array {
        return array( 'id' => $post->ID, 'entity_type' => $post->post_type, 'title' => $post->post_title, 'slug' => $post->post_name, 'status' => $post->post_status, 'content' => $post->post_content, 'modified_gmt' => $post->post_modified_gmt );
    }
}
