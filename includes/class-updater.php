<?php

namespace WPGPT\MCPBridge;

if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

class Updater {
    private const REPO_OWNER       = 'BobyKW';
    private const REPO_NAME        = 'WPGPT';
    private const REPO_URL         = 'https://github.com/BobyKW/WPGPT';
    private const RELEASES_API_URL = 'https://api.github.com/repos/BobyKW/WPGPT/releases/latest';
    private const CACHE_KEY        = 'wpgpt_mcp_bridge_github_release';
    private const CACHE_TTL        = 6 * HOUR_IN_SECONDS;

    public static function init(): void {
        add_filter( 'update_plugins_github.com', array( __CLASS__, 'filter_update' ), 10, 4 );
        add_filter( 'plugins_api', array( __CLASS__, 'plugins_api' ), 10, 3 );
        add_filter( 'http_request_args', array( __CLASS__, 'add_github_headers' ), 10, 2 );
    }

    public static function filter_update( $update, array $plugin_data, string $plugin_file, array $locales ) {
        unset( $plugin_data, $locales );

        if ( plugin_basename( WPGPT_MCP_BRIDGE_FILE ) !== $plugin_file ) {
            return $update;
        }

        $release = self::get_latest_release();
        if ( is_wp_error( $release ) ) {
            return false;
        }

        $latest_version = self::normalize_version( $release['version'] ?? '' );
        if ( '' === $latest_version || version_compare( $latest_version, WPGPT_MCP_BRIDGE_VERSION, '<=' ) ) {
            return false;
        }

        $package_url = $release['package_url'] ?? '';
        if ( '' === $package_url ) {
            return false;
        }

        return array(
            'id'            => self::REPO_URL,
            'slug'          => dirname( plugin_basename( WPGPT_MCP_BRIDGE_FILE ) ),
            'plugin'        => plugin_basename( WPGPT_MCP_BRIDGE_FILE ),
            'version'       => $latest_version,
            'new_version'   => $latest_version,
            'url'           => self::REPO_URL,
            'package'       => $package_url,
            'icons'         => array(),
            'banners'       => array(),
            'banners_rtl'   => array(),
            'tested'        => '',
            'requires_php'  => '',
            'compatibility' => new \stdClass(),
        );
    }

    public static function plugins_api( $result, string $action, $args ) {
        if ( 'plugin_information' !== $action || empty( $args->slug ) || 'wpgpt-mcp-bridge' !== $args->slug ) {
            return $result;
        }

        $release = self::get_latest_release();
        if ( is_wp_error( $release ) ) {
            return $result;
        }

        return (object) array(
            'name'           => 'WPGPT - MCP Extensor & ChatGPT Connection',
            'slug'           => 'wpgpt-mcp-bridge',
            'version'        => self::normalize_version( $release['version'] ?? WPGPT_MCP_BRIDGE_VERSION ),
            'author'         => '<a href="' . esc_url( self::REPO_URL ) . '">WPGPT</a>',
            'homepage'       => self::REPO_URL,
            'requires'       => '6.6',
            'tested'         => '',
            'requires_php'   => '8.1',
            'last_updated'   => $release['published_at'] ?? '',
            'download_link'  => $release['package_url'] ?? '',
            'sections'       => array(
                'description' => wp_kses_post( wpautop( $release['body'] ?? 'GitHub release for WPGPT MCP Bridge.' ) ),
                'installation' => wp_kses_post( wpautop( 'Install and activate WPGPT MCP Bridge, then configure the MCP user, permissions, allowed abilities, and connection credentials from the plugin admin screen.' ) ),
                'changelog'   => wp_kses_post( wpautop( $release['body'] ?? 'See the latest GitHub release notes.' ) ),
            ),
            'banners'        => array(),
            'icons'          => array(),
        );
    }

    public static function add_github_headers( array $args, string $url ): array {
        if ( 0 !== strpos( $url, 'https://api.github.com/' ) ) {
            return $args;
        }

        if ( ! isset( $args['headers'] ) || ! is_array( $args['headers'] ) ) {
            $args['headers'] = array();
        }

        $args['headers']['Accept']     = 'application/vnd.github+json';
        $args['headers']['User-Agent'] = 'WPGPT-MCP-Bridge/' . WPGPT_MCP_BRIDGE_VERSION . '; ' . home_url( '/' );

        return $args;
    }

    private static function get_latest_release() {
        $cached = get_site_transient( self::CACHE_KEY );
        if ( is_array( $cached ) && ! empty( $cached['version'] ) ) {
            return $cached;
        }

        $response = wp_remote_get(
            self::RELEASES_API_URL,
            array(
                'timeout' => 15,
                'headers' => array(
                    'Accept'     => 'application/vnd.github+json',
                    'User-Agent' => 'WPGPT-MCP-Bridge/' . WPGPT_MCP_BRIDGE_VERSION . '; ' . home_url( '/' ),
                ),
            )
        );

        if ( is_wp_error( $response ) ) {
            return $response;
        }

        $code = (int) wp_remote_retrieve_response_code( $response );
        if ( 200 !== $code ) {
            return new \WP_Error( 'wpgpt_updater_http_error', sprintf( 'GitHub updater HTTP error: %d', $code ) );
        }

        $data = json_decode( (string) wp_remote_retrieve_body( $response ), true );
        if ( ! is_array( $data ) || empty( $data['tag_name'] ) ) {
            return new \WP_Error( 'wpgpt_updater_invalid_payload', 'GitHub updater returned an invalid payload.' );
        }

        $release = array(
            'version'      => (string) $data['tag_name'],
            'body'         => isset( $data['body'] ) ? (string) $data['body'] : '',
            'published_at' => isset( $data['published_at'] ) ? (string) $data['published_at'] : '',
            'package_url'  => self::extract_package_url( $data ),
        );

        set_site_transient( self::CACHE_KEY, $release, self::CACHE_TTL );

        return $release;
    }

    private static function extract_package_url( array $release ): string {
        if ( ! empty( $release['assets'] ) && is_array( $release['assets'] ) ) {
            foreach ( $release['assets'] as $asset ) {
                if ( empty( $asset['browser_download_url'] ) ) {
                    continue;
                }

                $name = isset( $asset['name'] ) ? strtolower( (string) $asset['name'] ) : '';
                $url  = (string) $asset['browser_download_url'];

                if ( str_ends_with( $name, '.zip' ) || str_ends_with( strtolower( $url ), '.zip' ) ) {
                    return $url;
                }
            }
        }

        return '';
    }

    private static function normalize_version( string $version ): string {
        return ltrim( trim( $version ), "vV \t\n\r\0\x0B" );
    }
}
