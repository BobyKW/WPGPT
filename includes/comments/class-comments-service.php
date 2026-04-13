<?php

namespace WPGPT\MCPBridge\Comments;

use WP_Comment_Query;
use WP_Error;

if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

class Comments_Service {

    public function discussion_audit( array $input ): array {
        global $wpdb;

        $limit         = min( 200, max( 1, absint( $input['limit'] ?? 100 ) ) );
        $post_type     = sanitize_key( (string) ( $input['post_type'] ?? '' ) );
        $post_status   = sanitize_key( (string) ( $input['post_status'] ?? 'publish' ) );
        $override_only = ! empty( $input['override_only'] );
        $comments_open = array_key_exists( 'comments_open', $input ) ? (bool) $input['comments_open'] : null;
        $pings_open    = array_key_exists( 'pings_open', $input ) ? (bool) $input['pings_open'] : null;

        $where = array( "post_type NOT IN ('attachment','revision','nav_menu_item')" );
        $args  = array();
        if ( '' !== $post_type ) {
            $where[] = 'post_type = %s';
            $args[]  = $post_type;
        }
        if ( '' !== $post_status && 'any' !== $post_status ) {
            $where[] = 'post_status = %s';
            $args[]  = $post_status;
        }
        if ( true === $comments_open ) {
            $where[] = "comment_status = 'open'";
        } elseif ( false === $comments_open ) {
            $where[] = "comment_status = 'closed'";
        }
        if ( true === $pings_open ) {
            $where[] = "ping_status = 'open'";
        } elseif ( false === $pings_open ) {
            $where[] = "ping_status = 'closed'";
        }

        $sql = "SELECT ID, post_type, post_status, post_title, comment_status, ping_status FROM {$wpdb->posts} WHERE " . implode( ' AND ', $where ) . ' ORDER BY ID DESC';
        $sql .= $wpdb->prepare( ' LIMIT %d', $limit );
        if ( ! empty( $args ) ) {
            $args[] = $limit;
            $sql    = $wpdb->prepare( $sql, $args );
        }
        $rows = $wpdb->get_results( $sql, ARRAY_A ); // phpcs:ignore WordPress.DB.PreparedSQL.NotPrepared

        $default_comment = (string) get_option( 'default_comment_status', 'open' );
        $default_ping    = (string) get_option( 'default_ping_status', 'open' );
        $items           = array();
        foreach ( $rows as $row ) {
            $comment_override = (string) $row['comment_status'] !== $default_comment;
            $ping_override    = (string) $row['ping_status'] !== $default_ping;
            if ( $override_only && ! $comment_override && ! $ping_override ) {
                continue;
            }
            $items[] = array(
                'post_id'                  => (int) $row['ID'],
                'post_type'                => (string) $row['post_type'],
                'post_status'              => (string) $row['post_status'],
                'post_title'               => (string) $row['post_title'],
                'comment_status'           => (string) $row['comment_status'],
                'ping_status'              => (string) $row['ping_status'],
                'comment_status_override'  => $comment_override,
                'ping_status_override'     => $ping_override,
            );
        }

        return array(
            'count'                  => count( $items ),
            'default_comment_status' => $default_comment,
            'default_ping_status'    => $default_ping,
            'items'                  => array_values( $items ),
        );
    }

    public function query( array $input = array() ): array {
        $filters = isset( $input['filters'] ) && is_array( $input['filters'] ) ? $input['filters'] : array();
        $search  = isset( $input['search'] ) ? sanitize_text_field( (string) $input['search'] ) : '';
        $limit   = isset( $input['limit'] ) ? max( 1, min( 200, (int) $input['limit'] ) ) : 100;
        $offset  = isset( $input['offset'] ) ? max( 0, (int) $input['offset'] ) : 0;
        $items   = $this->build_comment_inventory();
        $matched = array_values( array_filter( $items, fn( $item ) => $this->comment_matches_filters( $item, $filters, $search ) ) );
        $paged   = array_slice( $matched, $offset, $limit );

        return array(
            'summary' => array(
                'total_comments' => count( $items ),
                'matched'        => count( $matched ),
                'returned'       => count( $paged ),
                'status_counts'  => $this->count_by_status( $matched ),
                'offset'         => $offset,
                'limit'          => $limit,
            ),
            'items' => $paged,
            'warnings' => empty( $matched ) ? array( __( 'No se han encontrado comentarios con esos filtros.', 'wpgpt-mcp-bridge' ) ) : array(),
            'next_actions' => count( $matched ) > $offset + count( $paged ) ? array( 'Usa offset=' . ( $offset + count( $paged ) ) . ' para continuar la consulta.' ) : array(),
        );
    }

    public function inspect( array $input = array() ): array|WP_Error {
        $targets = $this->collect_comment_targets( $input );
        if ( empty( $targets ) ) {
            return new WP_Error( 'wpgpt_comment_target_required', __( 'Debes indicar al menos un comment_id.', 'wpgpt-mcp-bridge' ), array( 'status' => 400 ) );
        }

        $items = array();
        $warnings = array();
        foreach ( $targets as $target ) {
            $comment = $this->resolve_comment_target( $target );
            if ( ! $comment ) {
                $warnings[] = __( 'No se ha encontrado uno de los comentarios solicitados.', 'wpgpt-mcp-bridge' );
                continue;
            }
            $post = get_post( $comment->comment_post_ID );
            $items[] = $this->normalize_comment( $comment ) + array(
                'post_type'          => $post ? $post->post_type : '',
                'post_status'        => $post ? $post->post_status : '',
                'content_length'     => strlen( (string) $comment->comment_content ),
                'available_actions'  => array( 'update', 'status', 'delete' ),
                'risk_level'         => $this->comment_risk_level( $comment ),
                'runtime_signals'    => array(
                    'is_reply' => (int) $comment->comment_parent > 0,
                    'has_user' => (int) $comment->user_id > 0,
                    'post_exists' => (bool) $post,
                ),
            );
        }

        return array(
            'summary' => array( 'requested' => count( $targets ), 'inspected' => count( $items ) ),
            'items' => $items,
            'warnings' => array_values( array_unique( array_filter( $warnings ) ) ),
            'next_actions' => empty( $items ) ? array() : array( __( 'Usa wpgpt/comments-apply con dry_run=true antes de ejecutar cambios.', 'wpgpt-mcp-bridge' ) ),
        );
    }

    public function apply( array $input = array() ): array|WP_Error {
        $action  = isset( $input['action'] ) ? sanitize_key( (string) $input['action'] ) : '';
        $dry_run = ! empty( $input['dry_run'] );
        $payload = isset( $input['payload'] ) && is_array( $input['payload'] ) ? $input['payload'] : array();

        if ( ! in_array( $action, array( 'create', 'update', 'status', 'delete' ), true ) ) {
            return new WP_Error( 'wpgpt_comment_action_invalid', __( 'La acción indicada no es válida.', 'wpgpt-mcp-bridge' ), array( 'status' => 400 ) );
        }

        $targets = $this->resolve_comment_apply_targets( $action, $input, $payload );
        if ( empty( $targets ) ) {
            return new WP_Error( 'wpgpt_comment_apply_target_required', __( 'No se han resuelto comentarios objetivo para la acción indicada.', 'wpgpt-mcp-bridge' ), array( 'status' => 400 ) );
        }

        $items = array();
        $blocked = array();
        $executed = 0;

        foreach ( $targets as $target ) {
            $validation = $this->validate_comment_action( $action, $target, $payload );
            if ( ! empty( $validation ) ) {
                $blocked[] = array( 'target' => $target, 'reasons' => $validation );
                continue;
            }
            if ( $dry_run ) {
                $items[] = array( 'target' => $target, 'status' => 'dry_run', 'action' => $action, 'message' => __( 'Acción validada, no ejecutada por dry_run.', 'wpgpt-mcp-bridge' ) );
                continue;
            }

            if ( 'create' === $action ) {
                $result = $this->upsert_comment( $payload );
            } elseif ( 'update' === $action ) {
                $result = $this->upsert_comment( $payload + array( 'comment_id' => (int) $target['comment_id'] ) );
            } elseif ( 'status' === $action ) {
                $result = $this->apply_status_action( (int) $target['comment_id'], sanitize_key( (string) ( $payload['status'] ?? '' ) ) );
            } else {
                $result = $this->delete_comment_data( array( 'comment_id' => (int) $target['comment_id'], 'force' => ! empty( $payload['force'] ) ) );
            }

            if ( is_wp_error( $result ) ) {
                $blocked[] = array( 'target' => $target, 'reasons' => array( $result->get_error_message() ) );
                continue;
            }
            if ( is_array( $result ) && array_key_exists( 'success', $result ) && false === $result['success'] ) {
                $blocked[] = array( 'target' => $target, 'reasons' => array( __( 'La acción no se pudo completar.', 'wpgpt-mcp-bridge' ) ) );
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
        $ids     = array_values( array_filter( array_map( 'absint', (array) ( $input['comment_ids'] ?? array() ) ) ) );
        $action  = sanitize_key( (string) ( $input['action'] ?? '' ) );
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

    private function apply_status_action( int $comment_id, string $action ) {
        $ok = false;
        switch ( $action ) {
            case 'approve': $ok = wp_set_comment_status( $comment_id, 'approve' ); break;
            case 'hold': $ok = wp_set_comment_status( $comment_id, 'hold' ); break;
            case 'spam': $ok = wp_spam_comment( $comment_id ); break;
            case 'unspam': $ok = wp_unspam_comment( $comment_id ); break;
            case 'trash': $ok = wp_trash_comment( $comment_id ); break;
            case 'untrash': $ok = wp_untrash_comment( $comment_id ); break;
            case 'delete': $ok = wp_delete_comment( $comment_id, true ); break;
            default:
                return new WP_Error( 'wpgpt_comment_status_invalid', __( 'La acción de estado indicada no es válida.', 'wpgpt-mcp-bridge' ), array( 'status' => 400 ) );
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
            'comment_id'      => (int) $comment->comment_ID,
            'post_id'         => (int) $comment->comment_post_ID,
            'post_title'      => $post ? get_the_title( $post ) : '',
            'author_name'     => (string) $comment->comment_author,
            'author_email'    => (string) $comment->comment_author_email,
            'author_url'      => (string) $comment->comment_author_url,
            'author_user_id'  => (int) $comment->user_id,
            'content'         => (string) $comment->comment_content,
            'status'          => wp_get_comment_status( $comment ),
            'parent'          => (int) $comment->comment_parent,
            'date_gmt'        => (string) $comment->comment_date_gmt,
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

    private function build_comment_inventory(): array {
        $comments = get_comments( array(
            'number' => 500,
            'orderby' => 'comment_ID',
            'order' => 'DESC',
            'status' => 'all',
        ) );
        return array_map( array( $this, 'normalize_comment' ), is_array( $comments ) ? $comments : array() );
    }

    private function comment_matches_filters( array $item, array $filters, string $search ): bool {
        if ( isset( $filters['comment_id'] ) && (int) $item['comment_id'] !== (int) $filters['comment_id'] ) {
            return false;
        }
        if ( isset( $filters['post_id'] ) && (int) $item['post_id'] !== (int) $filters['post_id'] ) {
            return false;
        }
        if ( isset( $filters['status'] ) && strtolower( (string) $item['status'] ) !== strtolower( (string) $filters['status'] ) ) {
            return false;
        }
        if ( isset( $filters['author_email'] ) && strtolower( (string) $item['author_email'] ) !== strtolower( (string) $filters['author_email'] ) ) {
            return false;
        }
        if ( isset( $filters['author_name'] ) && false === strpos( strtolower( (string) $item['author_name'] ), strtolower( (string) $filters['author_name'] ) ) ) {
            return false;
        }
        if ( isset( $filters['author_user_id'] ) && (int) $item['author_user_id'] !== (int) $filters['author_user_id'] ) {
            return false;
        }
        if ( isset( $filters['parent'] ) && (int) $item['parent'] !== (int) $filters['parent'] ) {
            return false;
        }
        if ( '' !== $search ) {
            $haystack = strtolower( implode( ' ', array( $item['author_name'], $item['author_email'], $item['content'], $item['post_title'] ) ) );
            if ( false === strpos( $haystack, strtolower( $search ) ) ) {
                return false;
            }
        }
        return true;
    }

    private function count_by_status( array $items ): array {
        $counts = array();
        foreach ( $items as $item ) {
            $status = (string) ( $item['status'] ?? 'unknown' );
            if ( ! isset( $counts[ $status ] ) ) {
                $counts[ $status ] = 0;
            }
            $counts[ $status ]++;
        }
        ksort( $counts );
        return $counts;
    }

    private function collect_comment_targets( array $input ): array {
        $targets = array();
        if ( ! empty( $input['comment_id'] ) ) {
            $targets[] = array( 'comment_id' => absint( $input['comment_id'] ) );
        }
        if ( ! empty( $input['comment_ids'] ) && is_array( $input['comment_ids'] ) ) {
            foreach ( $input['comment_ids'] as $comment_id ) {
                $targets[] = array( 'comment_id' => absint( $comment_id ) );
            }
        }
        return array_values( array_filter( $targets ) );
    }

    private function resolve_comment_target( array $target ) {
        if ( ! empty( $target['comment_id'] ) ) {
            return get_comment( absint( $target['comment_id'] ) );
        }
        return false;
    }

    private function resolve_comment_apply_targets( string $action, array $input, array $payload ): array {
        if ( 'create' === $action ) {
            return array( array( 'post_id' => absint( $payload['post_id'] ?? 0 ) ) );
        }

        $targets = array();
        if ( ! empty( $input['targets'] ) && is_array( $input['targets'] ) ) {
            foreach ( $input['targets'] as $target ) {
                if ( ! is_array( $target ) || empty( $target['comment_id'] ) ) {
                    continue;
                }
                $comment = get_comment( absint( $target['comment_id'] ) );
                if ( $comment ) {
                    $targets[] = array( 'comment_id' => (int) $comment->comment_ID );
                }
            }
        }
        if ( empty( $targets ) && ! empty( $payload['comment_id'] ) ) {
            $comment = get_comment( absint( $payload['comment_id'] ) );
            if ( $comment ) {
                $targets[] = array( 'comment_id' => (int) $comment->comment_ID );
            }
        }
        if ( empty( $targets ) ) {
            $filters = isset( $input['filters'] ) && is_array( $input['filters'] ) ? $input['filters'] : array();
            $search = isset( $filters['search'] ) ? sanitize_text_field( (string) $filters['search'] ) : '';
            foreach ( $this->build_comment_inventory() as $item ) {
                if ( $this->comment_matches_filters( $item, $filters, $search ) ) {
                    $targets[] = array( 'comment_id' => (int) $item['comment_id'] );
                }
            }
        }
        return $targets;
    }

    private function validate_comment_action( string $action, array $target, array $payload ): array {
        $reasons = array();
        if ( 'create' === $action ) {
            if ( empty( $payload['post_id'] ) || empty( $payload['content'] ) ) {
                $reasons[] = __( 'Para crear un comentario debes indicar post_id y content.', 'wpgpt-mcp-bridge' );
            }
            return $reasons;
        }

        $comment_id = isset( $target['comment_id'] ) ? absint( $target['comment_id'] ) : 0;
        $comment    = $comment_id ? get_comment( $comment_id ) : false;
        if ( ! $comment ) {
            $reasons[] = __( 'No se ha encontrado el comentario objetivo.', 'wpgpt-mcp-bridge' );
            return $reasons;
        }
        if ( 'status' === $action && empty( $payload['status'] ) ) {
            $reasons[] = __( 'Para cambiar el estado debes indicar payload.status.', 'wpgpt-mcp-bridge' );
        }
        return $reasons;
    }

    private function comment_risk_level( \WP_Comment $comment ): string {
        $status = wp_get_comment_status( $comment );
        if ( in_array( $status, array( 'spam', 'trash' ), true ) ) {
            return 'low';
        }
        return (int) $comment->user_id > 0 ? 'medium' : 'low';
    }
}
