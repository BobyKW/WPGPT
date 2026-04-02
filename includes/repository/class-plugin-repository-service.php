<?php

namespace WPGPT\MCPBridge\Repository;

use WP_Error;

if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

class Plugin_Repository_Service {
    public function search( string $search, int $page = 1, int $limit = 10 ): array|WP_Error {
        if ( '' === $search ) {
            return new WP_Error( 'wpgpt_empty_plugin_search', __( 'Debes indicar un texto de búsqueda.', 'wpgpt-mcp-bridge' ), array( 'status' => 400 ) );
        }

        require_once ABSPATH . 'wp-admin/includes/plugin-install.php';

        $page  = max( 1, $page );
        $limit = max( 1, min( 20, $limit ) );

        $query = plugins_api(
            'query_plugins',
            array(
                'search'   => $search,
                'page'     => $page,
                'per_page' => $limit,
                'fields'   => array(
                    'short_description' => true,
                    'active_installs'   => true,
                    'tested'            => true,
                    'requires'          => true,
                    'requires_php'      => true,
                    'rating'            => true,
                    'ratings'           => false,
                    'downloaded'        => true,
                    'last_updated'      => true,
                    'sections'          => false,
                    'tags'              => false,
                    'versions'          => false,
                    'donate_link'       => false,
                    'banners'           => false,
                ),
            )
        );

        if ( is_wp_error( $query ) ) {
            return $query;
        }

        $items = array();
        foreach ( (array) ( $query->plugins ?? array() ) as $plugin ) {
            $items[] = $this->format_plugin_summary( $plugin );
        }

        return array(
            'search'  => $search,
            'page'    => $page,
            'limit'   => $limit,
            'results' => (int) ( $query->info['results'] ?? count( $items ) ),
            'pages'   => (int) ( $query->info['pages'] ?? 1 ),
            'count'   => count( $items ),
            'items'   => $items,
        );
    }

    public function info( string $slug ): array|WP_Error {
        if ( '' === $slug ) {
            return new WP_Error( 'wpgpt_empty_plugin_slug', __( 'Debes indicar un slug de plugin.', 'wpgpt-mcp-bridge' ), array( 'status' => 400 ) );
        }

        require_once ABSPATH . 'wp-admin/includes/plugin-install.php';

        $plugin = plugins_api(
            'plugin_information',
            array(
                'slug'   => $slug,
                'fields' => array(
                    'short_description' => true,
                    'active_installs'   => true,
                    'tested'            => true,
                    'requires'          => true,
                    'requires_php'      => true,
                    'rating'            => true,
                    'downloaded'        => true,
                    'last_updated'      => true,
                    'sections'          => false,
                    'tags'              => true,
                    'versions'          => false,
                    'banners'           => false,
                ),
            )
        );

        if ( is_wp_error( $plugin ) ) {
            return $plugin;
        }

        return array(
            'slug'              => (string) ( $plugin->slug ?? '' ),
            'name'              => (string) ( $plugin->name ?? '' ),
            'version'           => (string) ( $plugin->version ?? '' ),
            'author'            => wp_strip_all_tags( (string) ( $plugin->author ?? '' ) ),
            'homepage'          => (string) ( $plugin->homepage ?? '' ),
            'download_link'     => (string) ( $plugin->download_link ?? '' ),
            'requires'          => (string) ( $plugin->requires ?? '' ),
            'requires_php'      => (string) ( $plugin->requires_php ?? '' ),
            'tested'            => (string) ( $plugin->tested ?? '' ),
            'last_updated'      => (string) ( $plugin->last_updated ?? '' ),
            'rating'            => (int) ( $plugin->rating ?? 0 ),
            'num_ratings'       => (int) ( $plugin->num_ratings ?? 0 ),
            'active_installs'   => (int) ( $plugin->active_installs ?? 0 ),
            'downloaded'        => (int) ( $plugin->downloaded ?? 0 ),
            'short_description' => wp_strip_all_tags( (string) ( $plugin->short_description ?? '' ) ),
        );
    }

    private function format_plugin_summary( $plugin ): array {
        if ( is_array( $plugin ) ) {
            $plugin = (object) $plugin;
        }
        return array(
            'slug'              => (string) ( $plugin->slug ?? '' ),
            'name'              => (string) ( $plugin->name ?? '' ),
            'version'           => (string) ( $plugin->version ?? '' ),
            'author'            => wp_strip_all_tags( (string) ( $plugin->author ?? '' ) ),
            'tested'            => (string) ( $plugin->tested ?? '' ),
            'requires'          => (string) ( $plugin->requires ?? '' ),
            'requires_php'      => (string) ( $plugin->requires_php ?? '' ),
            'rating'            => (int) ( $plugin->rating ?? 0 ),
            'active_installs'   => (int) ( $plugin->active_installs ?? 0 ),
            'last_updated'      => (string) ( $plugin->last_updated ?? '' ),
            'short_description' => wp_strip_all_tags( (string) ( $plugin->short_description ?? '' ) ),
        );
    }
}
