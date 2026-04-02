<?php

namespace WPGPT\MCPBridge;

use WPGPT\MCPBridge\Inspection\Code_Inspection_Service;
use WP_Error;

if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

class Inspection_Provider extends Base_Ability_Provider {
    private ?Code_Inspection_Service $service = null;

    public function get_abilities(): array {
        $abilities = array();
        foreach ( array(
            'class' => 'Code search class',
            'function' => 'Code search function',
            'hook' => 'Code search hook',
            'shortcode' => 'Code search shortcode',
            'rest_route' => 'Code search REST route',
            'constant' => 'Code search constant',
        ) as $type => $label ) {
            $abilities[ 'wpgpt/code-search-' . str_replace( '_', '-', $type ) ] = array(
                'label' => __( $label, 'wpgpt-mcp-bridge' ),
                'description' => __( 'Busca símbolos de código en plugins, themes o mu-plugins.', 'wpgpt-mcp-bridge' ),
                'input_schema' => $this->search_schema(),
                'execute_callback' => function( array $input ) use ( $type ) { return $this->run_search( $type, $input ); },
                'output_schema' => $this->object_schema(),
                'permission_callback' => array( $this, 'can_read_files' ),
            );
        }
        return $abilities;
    }

    public function run_search( string $type, array $input ): array|WP_Error {
        return $this->service()->search_pattern( $type, (string) ( $input['query'] ?? '' ), sanitize_text_field( (string) ( $input['scope'] ?? 'plugins' ) ), isset( $input['limit'] ) ? (int) $input['limit'] : 50 );
    }

    private function service(): Code_Inspection_Service {
        if ( null === $this->service ) {
            $this->service = new Code_Inspection_Service();
        }
        return $this->service;
    }

    private function search_schema(): array {
        return array('type'=>'object','properties'=>array('query'=>array('type'=>'string'),'scope'=>array('type'=>'string'),'limit'=>array('type'=>'integer')),'required'=>array('query'));
    }
}
