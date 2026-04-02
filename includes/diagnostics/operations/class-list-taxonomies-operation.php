<?php

namespace WPGPT\MCPBridge\Diagnostics\Operations;

use WPGPT\MCPBridge\Diagnostics\Diagnostic_Operation;
use WP_Error;

if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

class List_Taxonomies_Operation implements Diagnostic_Operation {
    public function name(): string {
        return 'list_taxonomies';
    }

    public function label(): string {
        return __( 'List taxonomies', 'wpgpt-mcp-bridge' );
    }

    public function description(): string {
        return __( 'Lista las taxonomías registradas.', 'wpgpt-mcp-bridge' );
    }

    public function input_schema(): array {
        return array(
            'type'       => 'object',
            'properties' => array(),
        );
    }

    public function execute( array $input ): array|WP_Error {
        $items = array();
        foreach ( get_taxonomies( array(), 'objects' ) as $taxonomy ) {
            $items[] = array(
                'name'   => $taxonomy->name,
                'label'  => $taxonomy->label,
                'public' => (bool) $taxonomy->public,
            );
        }

        return array(
            'operation' => $this->name(),
            'count'     => count( $items ),
            'items'     => $items,
        );
    }
}
