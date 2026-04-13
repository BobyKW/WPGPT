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

    public function supported_types(): array {
        return array( 'class', 'function', 'hook', 'shortcode', 'rest_route', 'constant' );
    }

    public function query( array $input = array() ): array|WP_Error {
        $query = trim( (string) ( $input['query'] ?? '' ) );
        $type  = sanitize_key( (string) ( $input['type'] ?? 'all' ) );
        $scope = sanitize_text_field( (string) ( $input['scope'] ?? 'plugins' ) );
        $limit = isset( $input['limit'] ) ? (int) $input['limit'] : 20;
        if ( '' === $query ) {
            return array(
                'summary' => array( 'scope' => $scope, 'supported_types' => $this->supported_types() ),
                'items' => array(),
                'warnings' => array(),
                'next_actions' => array( __( 'Indica query para buscar símbolos de código.', 'wpgpt-mcp-bridge' ) ),
            );
        }
        $types = ( 'all' === $type || '' === $type ) ? $this->supported_types() : array( $type );
        $items = array();
        foreach ( $types as $one_type ) {
            $result = $this->search_pattern( $one_type, $query, $scope, $limit );
            if ( is_wp_error( $result ) ) { return $result; }
            $items[] = array( 'type' => $one_type, 'item' => $result );
        }
        return array(
            'summary' => array( 'query' => $query, 'scope' => $scope, 'types' => $types, 'returned' => count( $items ) ),
            'items' => $items,
            'warnings' => array(),
            'next_actions' => array(),
        );
    }

    public function inspect( array $input = array() ): array|WP_Error {
        $query = trim( (string) ( $input['query'] ?? '' ) );
        if ( '' === $query ) {
            return new WP_Error( 'wpgpt_code_empty_query', __( 'Debes indicar query para inspeccionar código.', 'wpgpt-mcp-bridge' ), array( 'status' => 400 ) );
        }
        $type = sanitize_key( (string) ( $input['type'] ?? 'all' ) );
        $scope = sanitize_text_field( (string) ( $input['scope'] ?? 'plugins' ) );
        $limit = isset( $input['limit'] ) ? (int) $input['limit'] : 10;
        $include_context = ! empty( $input['include_context'] );
        $context_lines = isset( $input['context_lines'] ) ? max( 1, min( 20, (int) $input['context_lines'] ) ) : 3;
        $types = ( 'all' === $type || '' === $type ) ? $this->supported_types() : array( $type );
        $items = array();
        foreach ( $types as $one_type ) {
            $result = $this->search_pattern( $one_type, $query, $scope, $limit );
            if ( is_wp_error( $result ) ) { return $result; }
            if ( $include_context ) {
                foreach ( $result['items'] as &$match ) {
                    $context = $this->fs->read_file( (string) $match['path'], max( 1, (int) $match['line_number'] - $context_lines ), ( $context_lines * 2 ) + 1 );
                    if ( ! is_wp_error( $context ) ) {
                        $match['context'] = array(
                            'start_line' => $context['start_line'],
                            'end_line' => $context['end_line'],
                            'content' => $context['content'],
                        );
                    }
                }
                unset( $match );
            }
            $items[] = array( 'type' => $one_type, 'item' => $result );
        }
        return array(
            'summary' => array( 'query' => $query, 'scope' => $scope, 'types' => $types, 'inspected' => count( $items ) ),
            'items' => $items,
            'warnings' => array(),
            'next_actions' => array( __( 'Usa wpgpt/code-apply con dry_run=true para validar una búsqueda antes de repetirla operativamente.', 'wpgpt-mcp-bridge' ) ),
        );
    }

    public function apply( array $input = array() ): array|WP_Error {
        $action = sanitize_key( (string) ( $input['action'] ?? '' ) );
        $dry_run = ! empty( $input['dry_run'] );
        $payload = isset( $input['payload'] ) && is_array( $input['payload'] ) ? $input['payload'] : array();
        if ( 'search' !== $action ) {
            return new WP_Error( 'wpgpt_code_action_invalid', __( 'Acción de code no válida.', 'wpgpt-mcp-bridge' ), array( 'status' => 400 ) );
        }
        if ( $dry_run ) {
            return array(
                'summary' => array( 'action' => $action, 'dry_run' => true ),
                'items' => array(),
                'warnings' => array(),
                'blocked' => array(),
                'next_actions' => array( __( 'Repite la misma llamada con dry_run=false para ejecutar la búsqueda validada.', 'wpgpt-mcp-bridge' ) ),
            );
        }
        $result = $this->inspect( $payload );
        if ( is_wp_error( $result ) ) { return $result; }
        $result['summary']['action'] = $action;
        $result['summary']['dry_run'] = false;
        $result['blocked'] = array();
        return $result;
    }

}
