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
        return array(
            'wpgpt/code-query' => array(
                'label'               => __( 'Code query', 'wpgpt-mcp-bridge' ),
                'description'         => __( 'Busca y resume símbolos de código en plugins, temas o mu-plugins.', 'wpgpt-mcp-bridge' ),
                'input_schema'        => $this->query_schema(),
                'execute_callback'    => array( $this, 'code_query' ),
                'output_schema'       => $this->object_schema(),
                'permission_callback' => array( $this, 'can_read_files' ),
            ),
            'wpgpt/code-inspect' => array(
                'label'               => __( 'Code inspect', 'wpgpt-mcp-bridge' ),
                'description'         => __( 'Inspecciona coincidencias de símbolos de código y puede incluir contexto alrededor de cada línea.', 'wpgpt-mcp-bridge' ),
                'input_schema'        => $this->inspect_schema(),
                'execute_callback'    => array( $this, 'code_inspect' ),
                'output_schema'       => $this->object_schema(),
                'permission_callback' => array( $this, 'can_read_files' ),
            ),
            'wpgpt/code-apply' => array(
                'label'               => __( 'Code apply', 'wpgpt-mcp-bridge' ),
                'description'         => __( 'Ejecuta búsquedas de código de forma controlada, con soporte dry_run.', 'wpgpt-mcp-bridge' ),
                'input_schema'        => $this->apply_schema(),
                'execute_callback'    => array( $this, 'code_apply' ),
                'output_schema'       => $this->object_schema(),
                'permission_callback' => array( $this, 'can_read_files' ),
            ),
        );
    }

    public function code_query( array $input = array() ): array|WP_Error { return $this->service()->query( $input ); }
    public function code_inspect( array $input = array() ): array|WP_Error { return $this->service()->inspect( $input ); }
    public function code_apply( array $input = array() ): array|WP_Error { return $this->service()->apply( $input ); }

    private function service(): Code_Inspection_Service {
        return $this->service ??= new Code_Inspection_Service();
    }

    private function query_schema(): array {
        return array(
            'type'       => 'object',
            'properties' => array(
                'query' => array( 'type' => 'string' ),
                'type'  => array( 'type' => 'string', 'enum' => array( 'class', 'function', 'hook', 'shortcode', 'rest_route', 'constant', 'all' ) ),
                'scope' => array( 'type' => 'string', 'enum' => array( 'plugins', 'themes', 'mu-plugins' ) ),
                'limit' => array( 'type' => 'integer' ),
            ),
        );
    }

    private function inspect_schema(): array {
        return array(
            'type'       => 'object',
            'properties' => array(
                'query'           => array( 'type' => 'string' ),
                'type'            => array( 'type' => 'string', 'enum' => array( 'class', 'function', 'hook', 'shortcode', 'rest_route', 'constant', 'all' ) ),
                'scope'           => array( 'type' => 'string', 'enum' => array( 'plugins', 'themes', 'mu-plugins' ) ),
                'limit'           => array( 'type' => 'integer' ),
                'include_context' => array( 'type' => 'boolean' ),
                'context_lines'   => array( 'type' => 'integer' ),
            ),
            'required'   => array( 'query' ),
        );
    }

    private function apply_schema(): array {
        return array(
            'type'       => 'object',
            'properties' => array(
                'action'  => array( 'type' => 'string', 'enum' => array( 'search' ) ),
                'dry_run' => array( 'type' => 'boolean' ),
                'payload' => array( 'type' => 'object', 'additionalProperties' => true ),
            ),
            'required'   => array( 'action' ),
        );
    }
}
