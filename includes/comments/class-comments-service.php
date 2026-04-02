<?php

namespace WPGPT\MCPBridge\Comments;

use WP_Comment_Query;
use WP_Error;

if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

class Comments_Service {
    public function query_comments( array $input ): array {
        $page     = max( 1, absint( $input['page'] ?? 1 ) );
        $per_page = min( 100, max( 1, absint( $input['per_page'] ?? 20 ) ) );
        $args     = array(
            'number'  => $per_page,
            'offset'  => ( $page - 1 ) * $per_page,
            'orderby' => sanitize_key( (string) ( $input['orderby'] ?? 'comment_date_gmt' ) ),
            'order'   => 'ASC' === strtoupper( (string) ( $input['order'] ?? 'DESC' ) ) ? 'ASC' : 'DESC',
            'status'  => sanitize_key( (string) ( $input['status'] ?? 'all' ) ),
            'search'  => isset( $input['search'] ) ? sanitize_text_field( (string) $input['search'] ) : '',
            'count'   => false,
        );

        if ( ! empty( $input['post_id'] ) ) {
            $args['post_id'] = absint( $input['post_id'] );
        }
        if ( ! empty( $input['author_email'] ) ) {
            $args['author_email'] = sanitize_email( (string) $input['author_email'] );
        }
        if ( ! empty( $input['author_name'] ) ) {
            $args['author__in'] = $this->find_comment_author_user_ids( sanitize_text_field( (string) $input['author_name'] ) );
        }
        if ( isset( $input['parent'] ) ) {
            $args['parent'] = absint( $input['parent'] );
        }
        if ( ! empty( $input['date_from'] ) || ! empty( $input['date_to'] ) ) {
            $args['date_query'] = array_filter(
                array(
                    'after'     => ! empty( $input['date_from'] ) ? sanitize_text_field( (string) $input['date_from'] ) : null,
                    'before'    => ! empty( $input['date_to'] ) ? sanitize_text_field( (string) $input['date_to'] ) : null,
                    'inclusive' => true,
                ),
                static fn( $v ) => null !== $v
            );
        }

        $query      = new WP_Comment_Query();
        $comments    = $query->query( $args );
        $count_args  = $args;
        $count_args['count'] = true;
        unset( $count_args['number'], $count_args['offset'] );
        $total = (int) ( new WP_Comment_Query() )->query( $count_args );

        return array(
            'success'  => true,
            'resource' => 'comments',
            'items'    => array_map( array( $this, 'normalize_comment' ), is_array( $comments ) ? $comments : array() ),
            'total'    => $total,
            'page'     => $page,
            'per_page' => $per_page,
        );
    }

    public function get_comment( array $input ) {
        $comment = get_comment( absint( $input['comment_id'] ?? 0 ) );
        if ( ! $comment ) {
            return new WP_Error( 'wpgpt_comment_not_found', __( 'No se ha encontrado el comentario.', 'wpgpt-mcp-bridge' ), array( 'status' => 404 ) );
        }

        return array(
            'success' => true,
            'item'    => $this->normalize_comment( $comment ),
        );
    }

    public function upsert_comment( array $input ) {
        $data = array(
            'comment_post_ID'      => absint( $input['post_id'] ?? 0 ),
            'comment_content'      => wp_kses_post( (string) ( $input['content'] ?? '' ) ),
            'comment_author'       => sanitize_text_field( (string) ( $input['author_name'] ?? '' ) ),
            'comment_author_email' => sanitize_email( (string) ( $input['author_email'] ?? '' ) ),
            'comment_author_url'   => esc_url_raw( (string) ( $input['author_url'] ?? '' ) ),
            'comment_parent'       => absint( $input['parent'] ?? 0 ),
            'comment_approved'     => $this->map_status_to_approved( (string) ( $input['status'] ?? 'approve' ) ),
        );

        if ( empty( $input['comment_id'] ) ) {
            if ( empty( $data['comment_post_ID'] ) || '' === trim( $data['comment_content'] ) ) {
                return new WP_Error( 'wpgpt_invalid_comment_data', __( 'Debes indicar post_id y content para crear el comentario.', 'wpgpt-mcp-bridge' ), array( 'status' => 400 ) );
            }
            $comment_id = wp_insert_comment( wp_slash( $data ) );
        } else {
            $data['comment_ID'] = absint( $input['comment_id'] );
            $comment_id         = wp_update_comment( wp_slash( $data ), true );
        }

        if ( is_wp_error( $comment_id ) ) {
            return $comment_id;
        }

        return $this->get_comment( array( 'comment_id' => (int) $comment_id ) );
    }

    public function bulk_status( array $input ): array {
        $ids    = array_values( array_filter( array_map( 'absint', (array) ( $input['comment_ids'] ?? array() ) ) ) );
        $action = sanitize_key( (string) ( $input['action'] ?? '' ) );
        $results = array();

        foreach ( $ids as $comment_id ) {
            $results[] = $this->apply_status_action( $comment_id, $action );
        }

        return array(
            'success' => true,
            'action'  => $action,
            'results' => $results,
        );
    }

    public function delete_comment_data( array $input ) {
        $comment_id = absint( $input['comment_id'] ?? 0 );
        if ( $comment_id <= 0 ) {
            return new WP_Error( 'wpgpt_invalid_comment_id', __( 'comment_id es obligatorio.', 'wpgpt-mcp-bridge' ), array( 'status' => 400 ) );
        }

        $deleted = wp_delete_comment( $comment_id, ! empty( $input['force'] ) );
        if ( ! $deleted ) {
            return new WP_Error( 'wpgpt_comment_delete_failed', __( 'No se pudo eliminar el comentario.', 'wpgpt-mcp-bridge' ), array( 'status' => 500 ) );
        }

        return array(
            'success'    => true,
            'comment_id' => $comment_id,
            'deleted'    => true,
        );
    }

    private function apply_status_action( int $comment_id, string $action ): array {
        $ok = false;
        switch ( $action ) {
            case 'approve': $ok = wp_set_comment_status( $comment_id, 'approve' ); break;
            case 'hold': $ok = wp_set_comment_status( $comment_id, 'hold' ); break;
            case 'spam': $ok = wp_spam_comment( $comment_id ); break;
            case 'unspam': $ok = wp_unspam_comment( $comment_id ); break;
            case 'trash': $ok = wp_trash_comment( $comment_id ); break;
            case 'untrash': $ok = wp_untrash_comment( $comment_id ); break;
            case 'delete': $ok = wp_delete_comment( $comment_id, true ); break;
        }

        return array(
            'comment_id' => $comment_id,
            'success'    => (bool) $ok,
            'action'     => $action,
        );
    }

    private function normalize_comment( \WP_Comment $comment ): array {
        $post = get_post( $comment->comment_post_ID );
        return array(
            'comment_id'    => (int) $comment->comment_ID,
            'post_id'       => (int) $comment->comment_post_ID,
            'post_title'    => $post ? get_the_title( $post ) : '',
            'author_name'   => (string) $comment->comment_author,
            'author_email'  => (string) $comment->comment_author_email,
            'author_url'    => (string) $comment->comment_author_url,
            'author_user_id'=> (int) $comment->user_id,
            'content'       => (string) $comment->comment_content,
            'status'        => wp_get_comment_status( $comment ),
            'parent'        => (int) $comment->comment_parent,
            'date_gmt'      => (string) $comment->comment_date_gmt,
        );
    }

    private function map_status_to_approved( string $status ) {
        return match ( $status ) {
            'approve', 'approved' => 1,
            'spam' => 'spam',
            'trash' => 'trash',
            default => 0,
        };
    }

    private function find_comment_author_user_ids( string $author_name ): array {
        $users = get_users( array( 'search' => '*' . esc_attr( $author_name ) . '*', 'search_columns' => array( 'display_name', 'user_login' ), 'fields' => 'ID', 'number' => 20 ) );
        return array_map( 'absint', is_array( $users ) ? $users : array() );
    }
}
