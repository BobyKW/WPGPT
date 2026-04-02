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
            'rank_math_active' => defined( 'RANK_MATH_VERSION' ) || class_exists( '\\RankMath' ),
            'yoast_active'     => defined( 'WPSEO_VERSION' ) || class_exists( '\\WPSEO_Options' ),
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
        if ( ! is_array( $current ) ) {
            $current = array();
        }
        if ( ! is_array( $patch ) ) {
            return new WP_Error( 'wpgpt_invalid_patch', __( 'settings_patch debe ser un objeto.', 'wpgpt-mcp-bridge' ), array( 'status' => 400 ) );
        }
        $merged = $this->deep_merge( $current, $this->sanitize_recursive( $patch ) );
        update_option( $option_name, $merged, false );
        return array(
            'updated' => true,
            'plugin' => $plugin,
            'option_name' => $option_name,
            'option_value' => get_option( $option_name ),
        );
    }

    private function update_meta_if_present( int $post_id, string $meta_key, array $payload, string $field ): void {
        if ( array_key_exists( $field, $payload ) ) {
            update_post_meta( $post_id, $meta_key, $payload[ $field ] );
        }
    }

    private function is_allowed_option( string $plugin, string $option_name ): bool {
        if ( 'rank_math' === $plugin ) {
            return in_array( $option_name, self::RANK_MATH_OPTION_WHITELIST, true );
        }
        if ( 'yoast' === $plugin ) {
            return in_array( $option_name, self::YOAST_OPTION_WHITELIST, true );
        }
        return false;
    }

    private function sanitize_recursive( $value ) {
        if ( is_array( $value ) ) {
            $result = array();
            foreach ( $value as $k => $v ) {
                $result[ is_string( $k ) ? sanitize_key( $k ) : $k ] = $this->sanitize_recursive( $v );
            }
            return $result;
        }
        if ( is_bool( $value ) || is_int( $value ) || is_float( $value ) || null === $value ) {
            return $value;
        }
        return sanitize_text_field( (string) $value );
    }

    private function deep_merge( array $current, array $patch ): array {
        foreach ( $patch as $k => $v ) {
            if ( isset( $current[ $k ] ) && is_array( $current[ $k ] ) && is_array( $v ) ) {
                $current[ $k ] = $this->deep_merge( $current[ $k ], $v );
            } else {
                $current[ $k ] = $v;
            }
        }
        return $current;
    }
}
