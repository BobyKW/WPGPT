<?php

namespace WPGPT\MCPBridge\SEO;

use WP_Error;

if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

class SEO_Service {
    private const RANK_MATH_OPTION_WHITELIST = array( 'rank-math-options-titles', 'rank-math-options-general', 'rank-math-options-sitemap' );
    private const YOAST_OPTION_WHITELIST = array( 'wpseo_titles', 'wpseo_social', 'wpseo' );

    public function plugin_status(): array {
        return array(
            'rank_math_active' => defined( 'RANK_MATH_VERSION' ) || class_exists( '\RankMath' ),
            'yoast_active'     => defined( 'WPSEO_VERSION' ) || class_exists( '\WPSEO_Options' ),
        );
    }

    public function query( array $input = array() ): array|WP_Error {
        $filters = isset( $input['filters'] ) && is_array( $input['filters'] ) ? $input['filters'] : array();
        $search  = sanitize_text_field( (string) ( $input['search'] ?? '' ) );
        $limit   = max( 1, min( 100, (int) ( $input['limit'] ?? 20 ) ) );
        $offset  = max( 0, (int) ( $input['offset'] ?? 0 ) );

        $items = array();
        $statuses = $this->plugin_status();
        foreach ( array( 'rank_math', 'yoast' ) as $plugin ) {
            if ( ! empty( $filters['plugin'] ) && sanitize_key( (string) $filters['plugin'] ) !== $plugin ) {
                continue;
            }
            $is_active = ( 'rank_math' === $plugin ) ? $statuses['rank_math_active'] : $statuses['yoast_active'];
            $items[] = array(
                'entity_type' => 'plugin',
                'plugin' => $plugin,
                'active' => (bool) $is_active,
                'settings_options' => 'rank_math' === $plugin ? self::RANK_MATH_OPTION_WHITELIST : self::YOAST_OPTION_WHITELIST,
            );
        }

        $q = new \WP_Query(array(
            'post_type' => sanitize_key( (string) ( $filters['post_type'] ?? 'any' ) ),
            'post_status' => sanitize_key( (string) ( $filters['post_status'] ?? 'any' ) ),
            'posts_per_page' => $limit,
            'offset' => $offset,
            's' => $search,
            'post__in' => ! empty( $filters['post_id'] ) ? array( absint( $filters['post_id'] ) ) : array(),
        ));
        foreach ( (array) $q->posts as $post ) {
            $items[] = $this->build_post_item( $post->ID );
        }

        return array(
            'summary' => array(
                'plugin_status' => $statuses,
                'matched' => count( $items ),
                'returned' => count( $items ),
                'offset' => $offset,
                'limit' => $limit,
            ),
            'items' => $items,
            'warnings' => empty( $items ) ? array( __( 'No se han encontrado resultados SEO con esos filtros.', 'wpgpt-mcp-bridge' ) ) : array(),
            'next_actions' => array(),
        );
    }

    public function inspect( array $input = array() ): array|WP_Error {
        $post_ids = array();
        if ( ! empty( $input['post_id'] ) ) { $post_ids[] = absint( $input['post_id'] ); }
        foreach ( (array) ( $input['post_ids'] ?? array() ) as $id ) {
            $post_ids[] = absint( $id );
        }
        $post_ids = array_values( array_unique( array_filter( $post_ids ) ) );
        $include_analysis = ! isset( $input['include_analysis'] ) || (bool) $input['include_analysis'];
        $include_settings = (bool) ( $input['include_settings'] ?? false );
        $plugin = sanitize_key( (string) ( $input['plugin'] ?? '' ) );
        $option_name = sanitize_key( (string) ( $input['option_name'] ?? '' ) );

        $items = array();
        $warnings = array();

        foreach ( $post_ids as $post_id ) {
            if ( ! get_post( $post_id ) ) {
                $warnings[] = sprintf( __( 'No se ha encontrado el post %d.', 'wpgpt-mcp-bridge' ), $post_id );
                continue;
            }
            $item = $this->build_post_item( $post_id );
            if ( $include_analysis ) {
                $item['analysis'] = $this->get_analysis( $post_id );
            }
            $items[] = $item;
        }

        if ( $include_settings && $plugin && $option_name ) {
            $settings = $this->get_settings( $plugin, $option_name );
            if ( is_wp_error( $settings ) ) {
                $warnings[] = $settings->get_error_message();
            } else {
                $items[] = array_merge( array( 'entity_type' => 'settings' ), $settings );
            }
        }

        if ( ! $items && ! $warnings ) {
            $warnings[] = __( 'Debes indicar al menos un post_id/post_ids o plugin + option_name.', 'wpgpt-mcp-bridge' );
        }

        return array(
            'summary' => array( 'requested' => count( $post_ids ) + ( $plugin && $option_name ? 1 : 0 ), 'inspected' => count( $items ) ),
            'items' => $items,
            'warnings' => array_values( array_unique( array_filter( $warnings ) ) ),
            'next_actions' => $items ? array( __( 'Usa wpgpt/seo-apply con dry_run=true antes de ejecutar cambios.', 'wpgpt-mcp-bridge' ) ) : array(),
        );
    }

    public function apply( array $input = array() ): array|WP_Error {
        $action = sanitize_key( (string) ( $input['action'] ?? '' ) );
        $dry_run = (bool) ( $input['dry_run'] ?? false );
        $targets = is_array( $input['targets'] ?? null ) ? $input['targets'] : array();
        $payload = is_array( $input['payload'] ?? null ) ? $input['payload'] : array();
        $items = array();
        $blocked = array();

        if ( ! in_array( $action, array( 'meta_update', 'settings_update' ), true ) ) {
            return new WP_Error( 'wpgpt_seo_action_invalid', __( 'La acción SEO indicada no es válida.', 'wpgpt-mcp-bridge' ), array( 'status' => 400 ) );
        }

        if ( 'meta_update' === $action ) {
            foreach ( $targets as $target ) {
                $post_id = absint( $target['post_id'] ?? 0 );
                if ( $post_id <= 0 || ! get_post( $post_id ) ) {
                    $blocked[] = array( 'post_id' => $post_id, 'reason' => __( 'Post SEO no válido.', 'wpgpt-mcp-bridge' ) );
                    continue;
                }
                $request = array( 'post_id' => $post_id, 'plugin' => $payload['plugin'] ?? ( $target['plugin'] ?? 'auto' ), 'meta' => is_array( $payload['meta'] ?? null ) ? $payload['meta'] : array() );
                if ( empty( $request['meta'] ) ) {
                    $blocked[] = array( 'post_id' => $post_id, 'reason' => __( 'Debes indicar payload.meta.', 'wpgpt-mcp-bridge' ) );
                    continue;
                }
                if ( $dry_run ) {
                    $items[] = array( 'post_id' => $post_id, 'action' => $action, 'status' => 'dry_run', 'message' => __( 'Acción validada, no ejecutada por dry_run.', 'wpgpt-mcp-bridge' ) );
                    continue;
                }
                $result = $this->update_post_meta( $request );
                if ( is_wp_error( $result ) ) {
                    $blocked[] = array( 'post_id' => $post_id, 'reason' => $result->get_error_message() );
                    continue;
                }
                $items[] = array( 'post_id' => $post_id, 'action' => $action, 'status' => 'updated', 'result' => $result );
            }
        }

        if ( 'settings_update' === $action ) {
            $target = $targets[0] ?? array();
            $plugin = sanitize_key( (string) ( $payload['plugin'] ?? ( $target['plugin'] ?? '' ) ) );
            $option_name = sanitize_key( (string) ( $payload['option_name'] ?? ( $target['option_name'] ?? '' ) ) );
            $patch = is_array( $payload['settings_patch'] ?? null ) ? $payload['settings_patch'] : array();
            if ( ! $plugin || ! $option_name || ! $patch ) {
                $blocked[] = array( 'plugin' => $plugin, 'option_name' => $option_name, 'reason' => __( 'Debes indicar plugin, option_name y payload.settings_patch.', 'wpgpt-mcp-bridge' ) );
            } elseif ( $dry_run ) {
                $items[] = array( 'plugin' => $plugin, 'option_name' => $option_name, 'action' => $action, 'status' => 'dry_run', 'message' => __( 'Acción validada, no ejecutada por dry_run.', 'wpgpt-mcp-bridge' ) );
            } else {
                $result = $this->update_settings( $plugin, $option_name, $patch );
                if ( is_wp_error( $result ) ) {
                    $blocked[] = array( 'plugin' => $plugin, 'option_name' => $option_name, 'reason' => $result->get_error_message() );
                } else {
                    $items[] = array( 'plugin' => $plugin, 'option_name' => $option_name, 'action' => $action, 'status' => 'updated', 'result' => $result );
                }
            }
        }

        return array(
            'summary' => array( 'action' => $action, 'dry_run' => $dry_run, 'resolved_targets' => count( $targets ), 'executed' => count( $items ), 'blocked' => count( $blocked ) ),
            'items' => $items,
            'warnings' => array(),
            'blocked' => $blocked,
            'next_actions' => $dry_run ? array( __( 'Repite la misma llamada con dry_run=false para aplicar los cambios validados.', 'wpgpt-mcp-bridge' ) ) : array(),
        );
    }

    public function get_analysis( int $post_id ): array|WP_Error {
        if ( $post_id <= 0 || ! get_post( $post_id ) ) {
            return new WP_Error( 'wpgpt_post_not_found', __( 'Debes indicar un post válido.', 'wpgpt-mcp-bridge' ), array( 'status' => 404 ) );
        }

        $plugin_status = $this->plugin_status();
        $rank_math_score_raw = get_post_meta( $post_id, 'rank_math_seo_score', true );
        $yoast_score_raw     = get_post_meta( $post_id, '_yoast_wpseo_linkdex', true );

        return array(
            'post_id'       => $post_id,
            'plugin_status' => $plugin_status,
            'rank_math'     => array(
                'score'            => is_numeric( $rank_math_score_raw ) ? (int) $rank_math_score_raw : null,
                'focus_keyword'    => get_post_meta( $post_id, 'rank_math_focus_keyword', true ),
                'title'            => get_post_meta( $post_id, 'rank_math_title', true ),
                'description'      => get_post_meta( $post_id, 'rank_math_description', true ),
                'robots'           => get_post_meta( $post_id, 'rank_math_robots', true ),
                'internal_links_processed' => get_post_meta( $post_id, 'rank_math_internal_links_processed', true ),
                'available'        => '' !== (string) $rank_math_score_raw || '' !== (string) get_post_meta( $post_id, 'rank_math_focus_keyword', true ),
            ),
            'yoast'         => array(
                'score'         => is_numeric( $yoast_score_raw ) ? (int) $yoast_score_raw : null,
                'focus_keyword' => get_post_meta( $post_id, '_yoast_wpseo_focuskw', true ),
                'title'         => get_post_meta( $post_id, '_yoast_wpseo_title', true ),
                'description'   => get_post_meta( $post_id, '_yoast_wpseo_metadesc', true ),
                'canonical'     => get_post_meta( $post_id, '_yoast_wpseo_canonical', true ),
                'available'     => '' !== (string) $yoast_score_raw || '' !== (string) get_post_meta( $post_id, '_yoast_wpseo_focuskw', true ),
            ),
        );
    }


    public function get_analysis( int $post_id ): array|WP_Error {
        if ( $post_id <= 0 || ! get_post( $post_id ) ) {
            return new WP_Error( 'wpgpt_post_not_found', __( 'Debes indicar un post válido.', 'wpgpt-mcp-bridge' ), array( 'status' => 404 ) );
        }

        $plugin_status = $this->plugin_status();
        $rank_math_score_raw = get_post_meta( $post_id, 'rank_math_seo_score', true );
        $yoast_score_raw     = get_post_meta( $post_id, '_yoast_wpseo_linkdex', true );

        $analysis = array(
            'post_id'       => $post_id,
            'plugin_status' => $plugin_status,
            'rank_math'     => array(
                'score'            => is_numeric( $rank_math_score_raw ) ? (int) $rank_math_score_raw : null,
                'focus_keyword'    => get_post_meta( $post_id, 'rank_math_focus_keyword', true ),
                'title'            => get_post_meta( $post_id, 'rank_math_title', true ),
                'description'      => get_post_meta( $post_id, 'rank_math_description', true ),
                'robots'           => get_post_meta( $post_id, 'rank_math_robots', true ),
                'internal_links_processed' => get_post_meta( $post_id, 'rank_math_internal_links_processed', true ),
                'available'        => '' !== (string) $rank_math_score_raw || '' !== (string) get_post_meta( $post_id, 'rank_math_focus_keyword', true ),
            ),
            'yoast'         => array(
                'score'         => is_numeric( $yoast_score_raw ) ? (int) $yoast_score_raw : null,
                'focus_keyword' => get_post_meta( $post_id, '_yoast_wpseo_focuskw', true ),
                'title'         => get_post_meta( $post_id, '_yoast_wpseo_title', true ),
                'description'   => get_post_meta( $post_id, '_yoast_wpseo_metadesc', true ),
                'canonical'     => get_post_meta( $post_id, '_yoast_wpseo_canonical', true ),
                'available'     => '' !== (string) $yoast_score_raw || '' !== (string) get_post_meta( $post_id, '_yoast_wpseo_focuskw', true ),
            ),
        );

        return $analysis;
    }

    public function get_post_meta( int $post_id ): array|WP_Error {
        if ( $post_id <= 0 || ! get_post( $post_id ) ) {
            return new WP_Error( 'wpgpt_post_not_found', __( 'Debes indicar un post válido.', 'wpgpt-mcp-bridge' ), array( 'status' => 404 ) );
        }

        return array(
            'post_id' => $post_id,
            'rank_math' => array(
                'title' => get_post_meta( $post_id, 'rank_math_title', true ),
                'description' => get_post_meta( $post_id, 'rank_math_description', true ),
                'focus_keyword' => get_post_meta( $post_id, 'rank_math_focus_keyword', true ),
                'robots' => get_post_meta( $post_id, 'rank_math_robots', true ),
            ),
            'yoast' => array(
                'title' => get_post_meta( $post_id, '_yoast_wpseo_title', true ),
                'metadesc' => get_post_meta( $post_id, '_yoast_wpseo_metadesc', true ),
                'focus_keyword' => get_post_meta( $post_id, '_yoast_wpseo_focuskw', true ),
                'canonical' => get_post_meta( $post_id, '_yoast_wpseo_canonical', true ),
                'robots_noindex' => get_post_meta( $post_id, '_yoast_wpseo_meta-robots-noindex', true ),
            ),
        );
    }

    public function update_post_meta( array $input ): array|WP_Error {
        $post_id = isset( $input['post_id'] ) ? absint( $input['post_id'] ) : 0;
        if ( $post_id <= 0 || ! get_post( $post_id ) ) {
            return new WP_Error( 'wpgpt_post_not_found', __( 'Debes indicar un post válido.', 'wpgpt-mcp-bridge' ), array( 'status' => 404 ) );
        }

        $plugin = isset( $input['plugin'] ) ? sanitize_key( (string) $input['plugin'] ) : 'auto';
        $targets = array();
        $status = $this->plugin_status();
        if ( 'rank_math' === $plugin || ( 'auto' === $plugin && $status['rank_math_active'] ) ) {
            $targets[] = 'rank_math';
        }
        if ( 'yoast' === $plugin || ( 'auto' === $plugin && $status['yoast_active'] ) ) {
            $targets[] = 'yoast';
        }
        if ( empty( $targets ) ) {
            return new WP_Error( 'wpgpt_no_supported_seo_plugin', __( 'No hay un plugin SEO soportado activo o indicado.', 'wpgpt-mcp-bridge' ), array( 'status' => 400 ) );
        }

        $payload = is_array( $input['meta'] ?? null ) ? $input['meta'] : array();
        foreach ( $targets as $target ) {
            if ( 'rank_math' === $target ) {
                $this->update_meta_if_present( $post_id, 'rank_math_title', $payload, 'title' );
                $this->update_meta_if_present( $post_id, 'rank_math_description', $payload, 'description' );
                $this->update_meta_if_present( $post_id, 'rank_math_focus_keyword', $payload, 'focus_keyword' );
                $this->update_meta_if_present( $post_id, 'rank_math_robots', $payload, 'robots' );
            }
            if ( 'yoast' === $target ) {
                $this->update_meta_if_present( $post_id, '_yoast_wpseo_title', $payload, 'title' );
                $this->update_meta_if_present( $post_id, '_yoast_wpseo_metadesc', $payload, 'description' );
                $this->update_meta_if_present( $post_id, '_yoast_wpseo_focuskw', $payload, 'focus_keyword' );
                $this->update_meta_if_present( $post_id, '_yoast_wpseo_canonical', $payload, 'canonical' );
                $this->update_meta_if_present( $post_id, '_yoast_wpseo_meta-robots-noindex', $payload, 'robots_noindex' );
            }
        }

        return array(
            'updated' => true,
            'post_id' => $post_id,
            'targets' => $targets,
            'meta' => $this->get_post_meta( $post_id ),
        );
    }

    public function get_settings( string $plugin, string $option_name ): array|WP_Error {
        $option_name = sanitize_key( $option_name );
        if ( ! $this->is_allowed_option( $plugin, $option_name ) ) {
            return new WP_Error( 'wpgpt_option_not_allowed', __( 'La opción SEO solicitada no está permitida.', 'wpgpt-mcp-bridge' ), array( 'status' => 403 ) );
        }
        return array(
            'plugin' => $plugin,
            'option_name' => $option_name,
            'option_value' => get_option( $option_name ),
        );
    }

    public function update_settings( string $plugin, string $option_name, $patch ): array|WP_Error {
        $option_name = sanitize_key( $option_name );
        if ( ! $this->is_allowed_option( $plugin, $option_name ) ) {
            return new WP_Error( 'wpgpt_option_not_allowed', __( 'La opción SEO solicitada no está permitida.', 'wpgpt-mcp-bridge' ), array( 'status' => 403 ) );
        }
        $current = get_option( $option_name, array() );
        if ( ! is_array( $current ) ) { $current = array(); }
        if ( ! is_array( $patch ) ) {
            return new WP_Error( 'wpgpt_invalid_patch', __( 'settings_patch debe ser un objeto.', 'wpgpt-mcp-bridge' ), array( 'status' => 400 ) );
        }
        $merged = $this->deep_merge( $current, $this->sanitize_recursive( $patch ) );
        update_option( $option_name, $merged, false );
        return array( 'updated' => true, 'plugin' => $plugin, 'option_name' => $option_name, 'option_value' => get_option( $option_name ) );
    }

    private function build_post_item( int $post_id ): array {
        $post = get_post( $post_id );
        return array(
            'entity_type' => 'post',
            'post_id' => $post_id,
            'post_type' => $post ? $post->post_type : '',
            'post_status' => $post ? $post->post_status : '',
            'title' => $post ? get_the_title( $post ) : '',
            'plugin_status' => $this->plugin_status(),
            'meta' => $this->get_post_meta( $post_id ),
            'available_actions' => array( 'meta_update' ),
            'risk_level' => 'low',
        );
    }

    private function update_meta_if_present( int $post_id, string $meta_key, array $payload, string $field ): void {
        if ( array_key_exists( $field, $payload ) ) { update_post_meta( $post_id, $meta_key, $payload[ $field ] ); }
    }
    private function is_allowed_option( string $plugin, string $option_name ): bool {
        if ( 'rank_math' === $plugin ) { return in_array( $option_name, self::RANK_MATH_OPTION_WHITELIST, true ); }
        if ( 'yoast' === $plugin ) { return in_array( $option_name, self::YOAST_OPTION_WHITELIST, true ); }
        return false;
    }
    private function sanitize_recursive( $value ) { if ( is_array( $value ) ) { $result = array(); foreach ( $value as $k => $v ) { $result[ is_string( $k ) ? sanitize_key( $k ) : $k ] = $this->sanitize_recursive( $v ); } return $result; } if ( is_bool( $value ) || is_int( $value ) || is_float( $value ) || null === $value ) { return $value; } return sanitize_text_field( (string) $value ); }
    private function deep_merge( array $current, array $patch ): array { foreach ( $patch as $k => $v ) { if ( isset( $current[ $k ] ) && is_array( $current[ $k ] ) && is_array( $v ) ) { $current[ $k ] = $this->deep_merge( $current[ $k ], $v ); } else { $current[ $k ] = $v; } } return $current; }
}
