<?php

namespace WPGPT\MCPBridge\Diagnostics\Operations;

use WPGPT\MCPBridge\Diagnostics\Diagnostic_Operation;
use WP_Error;

if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

class List_Post_Types_Operation implements Diagnostic_Operation {
    public function name(): string {
        return 'list_post_types';
    }

    public function label(): string {
        return __( 'List post types', 'wpgpt-mcp-bridge' );
    }

    public function description(): string {
        return __( 'Lista los tipos de contenido registrados.', 'wpgpt-mcp-bridge' );
    }

    public function input_schema(): array {
        return array(
            'type'       => 'object',
            'properties' => array(),
        );
    }

    public function execute( array $input ): array|WP_Error {
        $items = array();
        foreach ( get_post_types( array(), 'objects' ) as $type ) {
            $items[] = array(
                'name'   => $type->name,
                'label'  => $type->label,
                'public' => (bool) $type->public,
            );
        }

        return array(
            'operation' => $this->name(),
            'count'     => count( $items ),
            'items'     => $items,
        );
    }
}
