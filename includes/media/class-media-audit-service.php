<?php

namespace WPGPT\MCPBridge\Media;

use WP_Error;

if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

class Media_Audit_Service {
    public function usage_audit( array $input ): array|WP_Error {
        global $wpdb;

        $attachment_id = absint( $input['attachment_id'] ?? 0 );
        if ( $attachment_id <= 0 || 'attachment' !== get_post_type( $attachment_id ) ) {
            return new WP_Error( 'wpgpt_invalid_attachment', __( 'Debes indicar un adjunto válido.', 'wpgpt-mcp-bridge' ), array( 'status' => 404 ) );
        }

        $thumb_sql = $wpdb->prepare(
            "SELECT ID, post_type, post_status, post_title FROM {$wpdb->posts}
             WHERE ID IN (
                SELECT post_id FROM {$wpdb->postmeta}
                WHERE meta_key = '_thumbnail_id' AND meta_value = %s
             )
             ORDER BY ID ASC
             LIMIT 100",
            (string) $attachment_id
        );
        $featured_usage = $wpdb->get_results( $thumb_sql, ARRAY_A ); // phpcs:ignore WordPress.DB.PreparedSQL.NotPrepared

        $attachment_url = wp_get_attachment_url( $attachment_id );
        $attached_file  = get_attached_file( $attachment_id );
        $basename       = $attached_file ? basename( $attached_file ) : '';

        $content_matches = array();
        if ( $attachment_url || $basename ) {
            $where = array( "post_status NOT IN ('auto-draft','inherit','trash')" );
            $args  = array();
            if ( $attachment_url ) {
                $where[] = 'post_content LIKE %s';
                $args[]  = '%' . $wpdb->esc_like( $attachment_url ) . '%';
            } elseif ( $basename ) {
                $where[] = 'post_content LIKE %s';
                $args[]  = '%' . $wpdb->esc_like( $basename ) . '%';
            }
            $sql = "SELECT ID, post_type, post_status, post_title FROM {$wpdb->posts} WHERE " . implode( ' AND ', $where ) . ' ORDER BY ID ASC LIMIT 100';
            if ( ! empty( $args ) ) {
                array_unshift( $args, $sql );
                $sql = call_user_func_array( array( $wpdb, 'prepare' ), $args );
            }
            $content_matches = $wpdb->get_results( $sql, ARRAY_A ); // phpcs:ignore WordPress.DB.PreparedSQL.NotPrepared
        }

        $meta_matches = array();
        if ( $basename ) {
            $meta_sql = $wpdb->prepare(
                "SELECT post_id, meta_key
                 FROM {$wpdb->postmeta}
                 WHERE meta_key NOT IN ('_thumbnail_id','_wp_attached_file')
                   AND meta_value LIKE %s
                 ORDER BY post_id ASC
                 LIMIT 100",
                '%' . $wpdb->esc_like( $basename ) . '%'
            );
            $meta_matches = $wpdb->get_results( $meta_sql, ARRAY_A ); // phpcs:ignore WordPress.DB.PreparedSQL.NotPrepared
        }

        return array(
            'attachment_id'         => $attachment_id,
            'url'                   => $attachment_url,
            'file'                  => $attached_file,
            'featured_in'           => array_values( array_map( array( $this, 'normalize_post_row' ), $featured_usage ) ),
            'content_references'    => array_values( array_map( array( $this, 'normalize_post_row' ), $content_matches ) ),
            'meta_references'       => array_values( array_map( static function ( array $row ): array {
                return array(
                    'post_id'  => (int) $row['post_id'],
                    'meta_key' => (string) $row['meta_key'],
                );
            }, $meta_matches ) ),
            'usage_summary'         => array(
                'featured_count'        => count( $featured_usage ),
                'content_reference_count' => count( $content_matches ),
                'meta_reference_count'  => count( $meta_matches ),
                'is_probably_unused'    => empty( $featured_usage ) && empty( $content_matches ) && empty( $meta_matches ),
            ),
        );
    }

    public function unused_list( array $input ): array {
        global $wpdb;

        $limit = max( 1, min( 200, absint( $input['limit'] ?? 50 ) ) );
        $items = array();
        $query = new \WP_Query(
            array(
                'post_type'      => 'attachment',
                'post_status'    => 'inherit',
                'posts_per_page' => $limit,
                'orderby'        => 'date',
                'order'          => 'DESC',
                'fields'         => 'ids',
                'no_found_rows'  => true,
            )
        );

        foreach ( $query->posts as $attachment_id ) {
            $attachment_id = (int) $attachment_id;
            $featured_count = (int) $wpdb->get_var( $wpdb->prepare( "SELECT COUNT(*) FROM {$wpdb->postmeta} WHERE meta_key = '_thumbnail_id' AND meta_value = %s", (string) $attachment_id ) );
            if ( $featured_count > 0 ) {
                continue;
            }
            $url      = wp_get_attachment_url( $attachment_id );
            $file     = get_attached_file( $attachment_id );
            $basename = $file ? basename( $file ) : '';
            $needle   = $url ?: $basename;
            $has_content = false;
            if ( '' !== $needle ) {
                $content_sql = $wpdb->prepare(
                    "SELECT ID FROM {$wpdb->posts}
                     WHERE post_type <> 'attachment' AND post_status NOT IN ('trash','auto-draft','inherit') AND post_content LIKE %s
                     LIMIT 1",
                    '%' . $wpdb->esc_like( $needle ) . '%'
                );
                $has_content = (bool) $wpdb->get_var( $content_sql ); // phpcs:ignore WordPress.DB.PreparedSQL.NotPrepared
            }
            if ( $has_content ) {
                continue;
            }
            $items[] = array(
                'attachment_id' => $attachment_id,
                'title'         => get_the_title( $attachment_id ),
                'url'           => $url,
                'file'          => $file,
                'mime_type'     => get_post_mime_type( $attachment_id ),
                'date_gmt'      => (string) get_post_field( 'post_date_gmt', $attachment_id ),
            );
        }

        return array(
            'count' => count( $items ),
            'items' => array_values( $items ),
        );
    }

    public function files_audit( array $input ): array {
        $uploads = wp_get_upload_dir();
        $base    = wp_normalize_path( (string) ( $uploads['basedir'] ?? '' ) );
        if ( '' === $base || ! is_dir( $base ) ) {
            return array(
                'base_dir'              => $base,
                'registered_file_count' => 0,
                'scanned_file_count'    => 0,
                'missing_registered'    => array(),
                'unregistered_files'    => array(),
            );
        }

        $max_scan = max( 100, min( 20000, absint( $input['max_scan'] ?? 5000 ) ) );
        $limit    = max( 1, min( 500, absint( $input['limit'] ?? 100 ) ) );

        $registered = $this->registered_upload_files( $base );
        $missing    = array();
        foreach ( $registered as $relative => $info ) {
            $absolute = $base . '/' . $relative;
            if ( ! is_file( $absolute ) ) {
                $missing[] = array(
                    'relative'      => $relative,
                    'attachment_id' => $info['attachment_id'],
                    'source'        => $info['source'],
                );
                if ( count( $missing ) >= $limit ) {
                    break;
                }
            }
        }

        $unregistered = array();
        $scanned      = 0;
        $rii = new \RecursiveIteratorIterator( new \RecursiveDirectoryIterator( $base, \FilesystemIterator::SKIP_DOTS ) );
        foreach ( $rii as $file ) {
            if ( $scanned >= $max_scan || count( $unregistered ) >= $limit ) {
                break;
            }
            if ( ! $file->isFile() ) {
                continue;
            }
            $scanned++;
            $absolute = wp_normalize_path( $file->getPathname() );
            $relative = ltrim( str_replace( trailingslashit( $base ), '', $absolute ), '/' );
            if ( isset( $registered[ $relative ] ) ) {
                continue;
            }
            $unregistered[] = array(
                'relative' => $relative,
                'size'     => (int) $file->getSize(),
            );
        }

        return array(
            'base_dir'              => $base,
            'registered_file_count' => count( $registered ),
            'scanned_file_count'    => $scanned,
            'missing_registered'    => array_values( $missing ),
            'unregistered_files'    => array_values( $unregistered ),
            'limit'                 => $limit,
            'max_scan'              => $max_scan,
        );
    }

    private function registered_upload_files( string $base_dir ): array {
        $query = new \WP_Query(
            array(
                'post_type'      => 'attachment',
                'post_status'    => 'inherit',
                'posts_per_page' => -1,
                'fields'         => 'ids',
                'no_found_rows'  => true,
            )
        );

        $registered = array();
        foreach ( $query->posts as $attachment_id ) {
            $attachment_id = (int) $attachment_id;
            $relative      = (string) get_post_meta( $attachment_id, '_wp_attached_file', true );
            if ( '' !== $relative ) {
                $registered[ ltrim( wp_normalize_path( $relative ), '/' ) ] = array(
                    'attachment_id' => $attachment_id,
                    'source'        => 'attached_file',
                );
            }
            $meta = wp_get_attachment_metadata( $attachment_id );
            if ( ! is_array( $meta ) ) {
                continue;
            }
            $subdir = '';
            if ( ! empty( $meta['file'] ) ) {
                $subdir = trim( dirname( (string) $meta['file'] ), './' );
                if ( '.' === $subdir ) {
                    $subdir = '';
                }
            }
            if ( ! empty( $meta['sizes'] ) && is_array( $meta['sizes'] ) ) {
                foreach ( $meta['sizes'] as $size_data ) {
                    if ( empty( $size_data['file'] ) ) {
                        continue;
                    }
                    $relative_size = ltrim( ( '' !== $subdir ? $subdir . '/' : '' ) . $size_data['file'], '/' );
                    $registered[ wp_normalize_path( $relative_size ) ] = array(
                        'attachment_id' => $attachment_id,
                        'source'        => 'metadata_size',
                    );
                }
            }
        }

        return $registered;
    }

    private function normalize_post_row( array $row ): array {
        return array(
            'post_id'     => (int) $row['ID'],
            'post_type'   => (string) $row['post_type'],
            'post_status' => (string) $row['post_status'],
            'post_title'  => (string) $row['post_title'],
        );
    }
}
