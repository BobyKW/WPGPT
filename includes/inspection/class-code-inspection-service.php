<?php

namespace WPGPT\MCPBridge\Inspection;

use WPGPT\MCPBridge\Filesystem\Filesystem_Service;
use WP_Error;

if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

class Code_Inspection_Service {
    private Filesystem_Service $fs;

    public function __construct() {
        $this->fs = new Filesystem_Service();
    }

    public function search_pattern( string $type, string $query, string $scope = 'plugins', int $limit = 50 ): array|WP_Error {
        $query = trim( $query );
        if ( '' === $query ) {
            return new WP_Error( 'wpgpt_code_empty_query', __( 'Debes indicar un texto de búsqueda.', 'wpgpt-mcp-bridge' ), array( 'status' => 400 ) );
        }
        $scope_root = $this->scope_to_root( $scope );
        if ( is_wp_error( $scope_root ) ) {
            return $scope_root;
        }
        $pattern = $this->build_pattern( $type, $query );
        if ( is_wp_error( $pattern ) ) {
            return $pattern;
        }
        $items = array();
        $rii = new \RecursiveIteratorIterator( new \RecursiveDirectoryIterator( $scope_root, \FilesystemIterator::SKIP_DOTS ) );
        foreach ( $rii as $file ) {
            if ( count( $items ) >= $limit ) {
                break;
            }
            if ( ! $file->isFile() ) {
                continue;
            }
            $path = $file->getPathname();
            if ( 'php' !== strtolower( pathinfo( $path, PATHINFO_EXTENSION ) ) ) {
                continue;
            }
            $lines = @file( $path );
            if ( false === $lines ) {
                continue;
            }
            foreach ( $lines as $index => $line ) {
                if ( preg_match( $pattern, $line ) ) {
                    $items[] = array(
                        'type' => $type,
                        'path' => $path,
                        'relative' => $this->relative_path( $path ),
                        'line_number' => $index + 1,
                        'excerpt' => trim( $line ),
                    );
                    break;
                }
            }
        }
        return array( 'type' => $type, 'query' => $query, 'scope' => $scope, 'count' => count( $items ), 'items' => $items );
    }

    private function build_pattern( string $type, string $query ): string|WP_Error {
        $safe = preg_quote( $query, '/' );
        return match ( $type ) {
            'class' => '/\bclass\s+' . $safe . '\b/i',
            'function' => '/\bfunction\s+' . $safe . '\s*\(/i',
            'hook' => "/(?:add_action|add_filter|do_action|apply_filters)\\s*\\(\\s*[\"']" . $safe . "[\"']/i",
            'shortcode' => "/add_shortcode\\s*\\(\\s*[\"']" . $safe . "[\"']/i",
            'rest_route' => "/register_rest_route\\s*\\(\\s*[\"']" . $safe . "/i",
            'constant' => "/\\b(?:define\\s*\\(\\s*[\"']" . $safe . "[\"']|const\\s+" . $safe . "\\b)/i",
            default => new WP_Error( 'wpgpt_code_invalid_type', __( 'Tipo de inspección no soportado.', 'wpgpt-mcp-bridge' ), array( 'status' => 400 ) ),
        };
    }

    private function scope_to_root( string $scope ): string|WP_Error {
        $scope = sanitize_text_field( $scope );
        return match ( $scope ) {
            'plugins' => wp_normalize_path( WP_PLUGIN_DIR ),
            'themes' => wp_normalize_path( get_theme_root() ),
            'mu-plugins' => wp_normalize_path( WPMU_PLUGIN_DIR ),
            default => new WP_Error( 'wpgpt_code_scope_invalid', __( 'Scope no permitido.', 'wpgpt-mcp-bridge' ), array( 'status' => 400 ) ),
        };
    }

    private function relative_path( string $absolute ): string {
        $absolute = wp_normalize_path( $absolute );
        $base = wp_normalize_path( ABSPATH );
        return 0 === strpos( $absolute, $base ) ? ltrim( substr( $absolute, strlen( $base ) ), '/' ) : $absolute;
    }
}
