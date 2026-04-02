<?php

namespace WPGPT\MCPBridge\Diagnostics\Operations;

use WPGPT\MCPBridge\Diagnostics\Diagnostic_Operation;
use WP_Error;

if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

class List_Shortcodes_Operation implements Diagnostic_Operation {
    public function name(): string {
        return 'list_shortcodes';
    }

    public function label(): string {
        return __( 'List shortcodes', 'wpgpt-mcp-bridge' );
    }

    public function description(): string {
        return __( 'Lista shortcodes registrados en el runtime.', 'wpgpt-mcp-bridge' );
    }

    public function input_schema(): array {
        return array(
            'type'       => 'object',
            'properties' => array(),
        );
    }

    public function execute( array $input ): array|WP_Error {
        global $shortcode_tags;

        $items = array();
        foreach ( array_keys( (array) $shortcode_tags ) as $tag ) {
            $items[] = array( 'tag' => (string) $tag );
        }

        sort( $items );

        return array(
            'operation' => $this->name(),
            'count'     => count( $items ),
            'items'     => array_values( $items ),
        );
    }
}
