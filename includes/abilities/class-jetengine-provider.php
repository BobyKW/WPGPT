<?php

namespace WPGPT\MCPBridge;

use WPGPT\MCPBridge\Integrations\JetEngine_Service;

if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

class JetEngine_Provider extends Base_Ability_Provider {
    private ?JetEngine_Service $service = null;

    public function get_abilities(): array {
        return array(
            'wpgpt/jetengine-status' => array(
                'label' => __( 'JetEngine status', 'wpgpt-mcp-bridge' ),
                'description' => __( 'Comprueba si JetEngine está activo y si expone namespaces REST detectables.', 'wpgpt-mcp-bridge' ),
                'execute_callback' => array( $this, 'jetengine_status' ),
                'output_schema' => $this->object_schema(),
            ),
            'wpgpt/jetengine-options-scan' => array(
                'label' => __( 'JetEngine options scan', 'wpgpt-mcp-bridge' ),
                'description' => __( 'Escanea opciones relacionadas con JetEngine por prefijo para ayudar a inspeccionar su configuración.', 'wpgpt-mcp-bridge' ),
                'input_schema' => array( 'type' => 'object', 'properties' => array( 'prefix' => array( 'type' => 'string' ), 'limit' => array( 'type' => 'integer' ) ) ),
                'execute_callback' => array( $this, 'jetengine_options_scan' ),
                'output_schema' => $this->object_schema(),
            ),
        );
    }

    public function jetengine_status(): array { return $this->service()->status(); }
    public function jetengine_options_scan( array $input ): array { return $this->service()->options_scan( sanitize_key( (string) ( $input['prefix'] ?? 'jet_engine' ) ), (int) ( $input['limit'] ?? 50 ) ); }

    private function service(): JetEngine_Service { if ( null === $this->service ) { $this->service = new JetEngine_Service(); } return $this->service; }
}
