<?php

namespace WPGPT\MCPBridge\Media;

use WP_Error;

if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

class Media_Service {
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
}
