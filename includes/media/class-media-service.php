<?php

namespace WPGPT\MCPBridge\Media;

use WP_Error;

if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

class Media_Service {
    public function query( array $input = array() ): array|WP_Error {
        $filters = isset( $input['filters'] ) && is_array( $input['filters'] ) ? $input['filters'] : array();
        $search  = isset( $input['search'] ) ? sanitize_text_field( (string) $input['search'] ) : '';
        $limit   = isset( $input['limit'] ) ? max( 1, min( 200, (int) $input['limit'] ) ) : 100;
        $offset  = isset( $input['offset'] ) ? max( 0, (int) $input['offset'] ) : 0;

        $items   = $this->build_media_inventory();
        $matched = array_values( array_filter( $items, fn( $item ) => $this->media_matches_filters( $item, $filters, $search ) ) );
        $paged   = array_slice( $matched, $offset, $limit );

        return array(
            'summary' => array(
                'total_media' => count( $items ),
                'matched' => count( $matched ),
                'returned' => count( $paged ),
                'mime_types' => $this->count_media_by_mime( $matched ),
                'unattached' => count( array_filter( $matched, fn( $item ) => ! empty( $item['unattached'] ) ) ),
                'offset' => $offset,
                'limit' => $limit,
            ),
            'items' => $paged,
            'warnings' => empty( $matched ) ? array( __( 'No se han encontrado adjuntos con esos filtros.', 'wpgpt-mcp-bridge' ) ) : array(),
            'next_actions' => count( $matched ) > $offset + count( $paged ) ? array( 'Usa offset=' . ( $offset + count( $paged ) ) . ' para continuar la consulta.' ) : array(),
        );
    }

    public function inspect( array $input = array() ): array|WP_Error {
        $targets = $this->collect_media_targets( $input );
        if ( empty( $targets ) ) {
            return new WP_Error( 'wpgpt_media_target_required', __( 'Debes indicar al menos un adjunto por attachment_id.', 'wpgpt-mcp-bridge' ), array( 'status' => 400 ) );
        }

        $items = array();
        $warnings = array();
        foreach ( $targets as $attachment_id ) {
            $post = get_post( $attachment_id );
            if ( ! $post || 'attachment' !== $post->post_type ) {
                $warnings[] = __( 'No se ha encontrado uno de los adjuntos solicitados.', 'wpgpt-mcp-bridge' );
                continue;
            }

            $item = $this->format_attachment( (int) $attachment_id, true );
            $item['available_actions'] = array( 'update', 'rename_file', 'set_featured_image', 'delete' );
            $item['risk_level'] = str_starts_with( (string) $item['mime_type'], 'image/' ) ? 'low' : 'medium';
            $items[] = $item;
        }

        return array(
            'summary' => array( 'requested' => count( $targets ), 'inspected' => count( $items ) ),
            'items' => $items,
            'warnings' => array_values( array_unique( array_filter( $warnings ) ) ),
            'next_actions' => empty( $items ) ? array() : array( __( 'Usa wpgpt/media-apply con dry_run=true antes de ejecutar cambios.', 'wpgpt-mcp-bridge' ) ),
        );
    }

    public function apply( array $input = array() ): array|WP_Error {
        $action  = isset( $input['action'] ) ? sanitize_key( (string) $input['action'] ) : '';
        $dry_run = ! empty( $input['dry_run'] );
        $payload = isset( $input['payload'] ) && is_array( $input['payload'] ) ? $input['payload'] : array();

        if ( ! in_array( $action, array( 'update', 'rename_file', 'sideload', 'set_featured_image', 'delete' ), true ) ) {
            return new WP_Error( 'wpgpt_media_action_invalid', __( 'La acción indicada no es válida.', 'wpgpt-mcp-bridge' ), array( 'status' => 400 ) );
        }

        $targets = $this->resolve_media_apply_targets( $action, $input, $payload );
        if ( empty( $targets ) ) {
            return new WP_Error( 'wpgpt_media_apply_target_required', __( 'No se han resuelto adjuntos objetivo para la acción indicada.', 'wpgpt-mcp-bridge' ), array( 'status' => 400 ) );
        }

        $items = array();
        $blocked = array();
        $executed = 0;
        foreach ( $targets as $target ) {
            $validation = $this->validate_media_action( $action, $target, $payload );
            if ( ! empty( $validation ) ) {
                $blocked[] = array( 'target' => $target, 'reasons' => $validation );
                continue;
            }

            if ( $dry_run ) {
                $items[] = array( 'target' => $target, 'status' => 'dry_run', 'action' => $action, 'message' => __( 'Acción validada, no ejecutada por dry_run.', 'wpgpt-mcp-bridge' ) );
                continue;
            }

            switch ( $action ) {
                case 'update':
                    $result = $this->update_media( $payload + array( 'attachment_id' => (int) $target['attachment_id'] ) );
                    break;
                case 'rename_file':
                    $result = $this->rename_media_file( $payload + array( 'attachment_id' => (int) $target['attachment_id'] ) );
                    break;
                case 'sideload':
                    $result = $this->sideload_media( $payload );
                    break;
                case 'set_featured_image':
                    $result = $this->set_featured_image( $payload + array( 'attachment_id' => (int) $target['attachment_id'] ) );
                    break;
                case 'delete':
                    $result = $this->delete_media( $payload + array( 'attachment_id' => (int) $target['attachment_id'] ) );
                    break;
                default:
                    $result = new WP_Error( 'wpgpt_media_action_invalid', __( 'La acción indicada no es válida.', 'wpgpt-mcp-bridge' ), array( 'status' => 400 ) );
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

    public function list_media( array $input ): array {
        $limit  = isset( $input['limit'] ) ? max( 1, min( 100, (int) $input['limit'] ) ) : 20;
        $search = isset( $input['search'] ) ? sanitize_text_field( (string) $input['search'] ) : '';

        $query = new \WP_Query(
            array(
                'post_type'      => 'attachment',
                'post_status'    => 'inherit',
                'posts_per_page' => $limit,
                's'              => $search,
                'orderby'        => 'date',
                'order'          => 'DESC',
                'fields'         => 'ids',
                'no_found_rows'  => true,
            )
        );

        $items = array();
        foreach ( $query->posts as $attachment_id ) {
            $items[] = $this->format_attachment( (int) $attachment_id );
        }

        return array( 'count' => count( $items ), 'items' => $items );
    }

    public function get_media( array $input ): array|WP_Error {
        $attachment_id = isset( $input['attachment_id'] ) ? absint( $input['attachment_id'] ) : 0;
        $post          = $attachment_id ? get_post( $attachment_id ) : null;
        if ( ! $post || 'attachment' !== $post->post_type ) {
            return new WP_Error( 'wpgpt_media_not_found', __( 'No se ha encontrado el adjunto indicado.', 'wpgpt-mcp-bridge' ), array( 'status' => 404 ) );
        }

        return $this->format_attachment( $attachment_id, true );
    }

    public function sideload_media( array $input ): array|WP_Error {
        if ( ! function_exists( 'download_url' ) ) {
            require_once ABSPATH . 'wp-admin/includes/file.php';
        }
        if ( ! function_exists( 'media_handle_sideload' ) ) {
            require_once ABSPATH . 'wp-admin/includes/media.php';
            require_once ABSPATH . 'wp-admin/includes/file.php';
            require_once ABSPATH . 'wp-admin/includes/image.php';
        }

        $url = isset( $input['url'] ) ? esc_url_raw( (string) $input['url'] ) : '';
        if ( '' === $url ) {
            return new WP_Error( 'wpgpt_media_missing_url', __( 'Debes indicar una URL válida.', 'wpgpt-mcp-bridge' ), array( 'status' => 400 ) );
        }

        $tmp = download_url( $url );
        if ( is_wp_error( $tmp ) ) {
            return $tmp;
        }

        $name = basename( wp_parse_url( $url, PHP_URL_PATH ) ?: 'remote-file' );
        $file_array = array(
            'name'     => sanitize_file_name( $name ),
            'tmp_name' => $tmp,
        );

        $post_id = isset( $input['post_id'] ) ? absint( $input['post_id'] ) : 0;
        $desc    = isset( $input['title'] ) ? sanitize_text_field( (string) $input['title'] ) : null;
        $attachment_id = media_handle_sideload( $file_array, $post_id, $desc );

        if ( is_wp_error( $attachment_id ) ) {
            @unlink( $tmp );
            return $attachment_id;
        }

        return array(
            'sideloaded'    => true,
            'attachment_id' => (int) $attachment_id,
            'post_id'       => $post_id,
            'item'          => $this->format_attachment( (int) $attachment_id, true ),
        );
    }

    public function set_featured_image( array $input ): array|WP_Error {
        $post_id       = isset( $input['post_id'] ) ? absint( $input['post_id'] ) : 0;
        $attachment_id = isset( $input['attachment_id'] ) ? absint( $input['attachment_id'] ) : 0;

        if ( $post_id <= 0 || ! get_post( $post_id ) ) {
            return new WP_Error( 'wpgpt_invalid_post', __( 'Debes indicar un post válido.', 'wpgpt-mcp-bridge' ), array( 'status' => 404 ) );
        }
        if ( $attachment_id <= 0 || 'attachment' !== get_post_type( $attachment_id ) ) {
            return new WP_Error( 'wpgpt_invalid_attachment', __( 'Debes indicar un adjunto válido.', 'wpgpt-mcp-bridge' ), array( 'status' => 404 ) );
        }

        $result = set_post_thumbnail( $post_id, $attachment_id );

        return array(
            'updated'       => false !== $result,
            'post_id'       => $post_id,
            'attachment_id' => $attachment_id,
        );
    }


    public function update_media( array $input ): array|WP_Error {
        $attachment_id = isset( $input['attachment_id'] ) ? absint( $input['attachment_id'] ) : 0;
        $post = $attachment_id ? get_post( $attachment_id ) : null;
        if ( ! $post || 'attachment' !== $post->post_type ) {
            return new WP_Error( 'wpgpt_media_not_found', __( 'No se ha encontrado el adjunto indicado.', 'wpgpt-mcp-bridge' ), array( 'status' => 404 ) );
        }
        $postarr = array( 'ID' => $attachment_id );
        $has = false;
        if ( array_key_exists( 'title', $input ) ) { $postarr['post_title'] = sanitize_text_field( (string) $input['title'] ); $has = true; }
        if ( array_key_exists( 'caption', $input ) ) { $postarr['post_excerpt'] = sanitize_text_field( (string) $input['caption'] ); $has = true; }
        if ( array_key_exists( 'description', $input ) ) { $postarr['post_content'] = wp_kses_post( (string) $input['description'] ); $has = true; }
        if ( $has ) {
            $updated = wp_update_post( $postarr, true, false );
            if ( is_wp_error( $updated ) ) { return $updated; }
        }
        if ( array_key_exists( 'alt', $input ) ) {
            update_post_meta( $attachment_id, '_wp_attachment_image_alt', sanitize_text_field( (string) $input['alt'] ) );
        }
        return array( 'updated' => true, 'item' => $this->format_attachment( $attachment_id, true ) );
    }

    public function rename_media_file( array $input ): array|WP_Error {
        $attachment_id = isset( $input['attachment_id'] ) ? absint( $input['attachment_id'] ) : 0;
        $new_basename  = sanitize_file_name( (string) ( $input['new_filename'] ?? '' ) );
        if ( $attachment_id <= 0 || '' === $new_basename ) {
            return new WP_Error( 'wpgpt_media_rename_invalid', __( 'Debes indicar attachment_id y new_filename válidos.', 'wpgpt-mcp-bridge' ), array( 'status' => 400 ) );
        }
        $file = get_attached_file( $attachment_id );
        if ( ! $file || ! is_file( $file ) ) {
            return new WP_Error( 'wpgpt_media_file_missing', __( 'No se ha encontrado el archivo físico del adjunto.', 'wpgpt-mcp-bridge' ), array( 'status' => 404 ) );
        }
        $dir = dirname( $file );
        $ext = pathinfo( $file, PATHINFO_EXTENSION );
        if ( '' === pathinfo( $new_basename, PATHINFO_EXTENSION ) && '' !== $ext ) {
            $new_basename .= '.' . $ext;
        }
        $target = trailingslashit( $dir ) . $new_basename;
        if ( $target === $file ) {
            return array( 'updated' => true, 'attachment_id' => $attachment_id, 'file' => $target, 'url' => wp_get_attachment_url( $attachment_id ) );
        }
        if ( file_exists( $target ) ) {
            return new WP_Error( 'wpgpt_media_file_exists', __( 'Ya existe un archivo con ese nombre.', 'wpgpt-mcp-bridge' ), array( 'status' => 409 ) );
        }
        if ( ! @rename( $file, $target ) ) {
            return new WP_Error( 'wpgpt_media_rename_failed', __( 'No se pudo renombrar el archivo físico.', 'wpgpt-mcp-bridge' ), array( 'status' => 500 ) );
        }
        $uploads = wp_get_upload_dir();
        $relative = ltrim( str_replace( trailingslashit( $uploads['basedir'] ), '', $target ), '/' );
        update_attached_file( $attachment_id, $target );
        update_post_meta( $attachment_id, '_wp_attached_file', $relative );
        $meta = wp_get_attachment_metadata( $attachment_id );
        if ( is_array( $meta ) && isset( $meta['file'] ) ) {
            $meta['file'] = $relative;
            wp_update_attachment_metadata( $attachment_id, $meta );
        }
        return array( 'updated' => true, 'attachment_id' => $attachment_id, 'file' => $target, 'relative' => $relative, 'url' => wp_get_attachment_url( $attachment_id ) );
    }

    public function delete_media( array $input ): array|WP_Error {
        $attachment_id = isset( $input['attachment_id'] ) ? absint( $input['attachment_id'] ) : 0;
        if ( $attachment_id <= 0 || 'attachment' !== get_post_type( $attachment_id ) ) {
            return new WP_Error( 'wpgpt_invalid_attachment', __( 'Debes indicar un adjunto válido.', 'wpgpt-mcp-bridge' ), array( 'status' => 404 ) );
        }

        $force   = ! empty( $input['force'] );
        $deleted = wp_delete_attachment( $attachment_id, $force );
        if ( ! $deleted ) {
            return new WP_Error( 'wpgpt_media_delete_failed', __( 'No se pudo eliminar el adjunto indicado.', 'wpgpt-mcp-bridge' ), array( 'status' => 500 ) );
        }

        return array(
            'deleted'       => true,
            'force'         => $force,
            'attachment_id' => $attachment_id,
        );
    }

    private function format_attachment( int $attachment_id, bool $include_meta = false ): array {
        $post = get_post( $attachment_id );
        $data = array(
            'attachment_id' => $attachment_id,
            'title'         => get_the_title( $attachment_id ),
            'mime_type'     => get_post_mime_type( $attachment_id ),
            'url'           => wp_get_attachment_url( $attachment_id ),
            'file'          => get_attached_file( $attachment_id ),
            'uploaded_to_post_id' => $post ? (int) $post->post_parent : 0,
            'unattached'    => $post ? 0 === (int) $post->post_parent : true,
            'author_id'     => $post ? (int) $post->post_author : 0,
            'date_gmt'      => $post ? $post->post_date_gmt : '',
            'modified_gmt'  => $post ? $post->post_modified_gmt : '',
        );

        if ( $include_meta ) {
            $meta = wp_get_attachment_metadata( $attachment_id );
            $data['metadata'] = is_array( $meta ) ? $meta : array();
            $data['alt']      = (string) get_post_meta( $attachment_id, '_wp_attachment_image_alt', true );
            $data['caption']  = wp_get_attachment_caption( $attachment_id );
            $data['description'] = $post ? $post->post_content : '';
        }

        return $data;
    }

    private function build_media_inventory(): array {
        $query = new \WP_Query(
            array(
                'post_type' => 'attachment',
                'post_status' => 'inherit',
                'posts_per_page' => -1,
                'fields' => 'ids',
                'orderby' => 'date',
                'order' => 'DESC',
                'no_found_rows' => true,
            )
        );

        $items = array();
        foreach ( $query->posts as $attachment_id ) {
            $items[] = $this->format_attachment( (int) $attachment_id );
        }
        return $items;
    }

    private function media_matches_filters( array $item, array $filters, string $search ): bool {
        if ( isset( $filters['attachment_id'] ) && (int) $filters['attachment_id'] !== (int) $item['attachment_id'] ) {
            return false;
        }
        if ( isset( $filters['mime_type'] ) && '' !== (string) $filters['mime_type'] && stripos( (string) $item['mime_type'], (string) $filters['mime_type'] ) === false ) {
            return false;
        }
        if ( isset( $filters['author_id'] ) && (int) $filters['author_id'] !== (int) $item['author_id'] ) {
            return false;
        }
        if ( isset( $filters['uploaded_to_post_id'] ) && (int) $filters['uploaded_to_post_id'] !== (int) $item['uploaded_to_post_id'] ) {
            return false;
        }
        if ( array_key_exists( 'unattached', $filters ) && (bool) $filters['unattached'] !== (bool) $item['unattached'] ) {
            return false;
        }
        if ( '' !== $search ) {
            $haystack = strtolower( implode( ' ', array( (string) $item['title'], (string) $item['mime_type'], (string) basename( (string) $item['file'] ) ) ) );
            if ( ! str_contains( $haystack, strtolower( $search ) ) ) {
                return false;
            }
        }
        return true;
    }

    private function count_media_by_mime( array $items ): array {
        $counts = array();
        foreach ( $items as $item ) {
            $key = (string) $item['mime_type'];
            $counts[ $key ] = ( $counts[ $key ] ?? 0 ) + 1;
        }
        ksort( $counts );
        return $counts;
    }

    private function collect_media_targets( array $input ): array {
        $targets = array();
        if ( isset( $input['attachment_id'] ) ) {
            $targets[] = absint( $input['attachment_id'] );
        }
        if ( isset( $input['attachment_ids'] ) && is_array( $input['attachment_ids'] ) ) {
            foreach ( $input['attachment_ids'] as $attachment_id ) {
                $targets[] = absint( $attachment_id );
            }
        }
        return array_values( array_unique( array_filter( $targets ) ) );
    }

    private function resolve_media_apply_targets( string $action, array $input, array $payload ): array {
        if ( 'sideload' === $action ) {
            return array( array( 'virtual' => 'sideload' ) );
        }

        $targets = array();
        if ( isset( $input['targets'] ) && is_array( $input['targets'] ) ) {
            foreach ( $input['targets'] as $target ) {
                if ( is_array( $target ) && ! empty( $target['attachment_id'] ) ) {
                    $targets[] = array( 'attachment_id' => absint( $target['attachment_id'] ) );
                }
            }
        }
        if ( empty( $targets ) && ! empty( $input['filters'] ) && is_array( $input['filters'] ) ) {
            $matched = $this->query( array( 'filters' => $input['filters'], 'search' => (string) ( $input['filters']['search'] ?? '' ), 'limit' => 200, 'offset' => 0 ) );
            if ( is_array( $matched ) && isset( $matched['items'] ) && is_array( $matched['items'] ) ) {
                foreach ( $matched['items'] as $item ) {
                    $targets[] = array( 'attachment_id' => (int) $item['attachment_id'] );
                }
            }
        }
        if ( empty( $targets ) && ! empty( $payload['attachment_id'] ) ) {
            $targets[] = array( 'attachment_id' => absint( $payload['attachment_id'] ) );
        }
        return $targets;
    }

    private function validate_media_action( string $action, array $target, array $payload ): array {
        $reasons = array();
        if ( 'sideload' === $action ) {
            if ( empty( $payload['url'] ) ) {
                $reasons[] = __( 'Para sideload debes indicar una URL válida.', 'wpgpt-mcp-bridge' );
            }
            return $reasons;
        }

        $attachment_id = (int) ( $target['attachment_id'] ?? 0 );
        if ( $attachment_id <= 0 || 'attachment' !== get_post_type( $attachment_id ) ) {
            $reasons[] = __( 'El adjunto objetivo no existe.', 'wpgpt-mcp-bridge' );
            return $reasons;
        }

        if ( 'rename_file' === $action && empty( $payload['new_filename'] ) ) {
            $reasons[] = __( 'Para renombrar debes indicar new_filename.', 'wpgpt-mcp-bridge' );
        }
        if ( 'set_featured_image' === $action && empty( $payload['post_id'] ) ) {
            $reasons[] = __( 'Para asignar imagen destacada debes indicar post_id.', 'wpgpt-mcp-bridge' );
        }
        return $reasons;
    }
}
