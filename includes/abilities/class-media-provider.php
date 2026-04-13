<?php

namespace WPGPT\MCPBridge;

use WPGPT\MCPBridge\Media\Media_Service;
use WPGPT\MCPBridge\Media\Media_Audit_Service;
use WP_Error;

if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

class Media_Provider extends Base_Ability_Provider {
    private ?Media_Service $service = null;
    private ?Media_Audit_Service $audit_service = null;

    public function get_abilities(): array {
        return array(
            'wpgpt/media-audits-query' => array(
                'label' => __( 'Media audits query', 'wpgpt-mcp-bridge' ),
                'description' => __( 'Lista auditorías de medios y adjuntos sin uso detectado.', 'wpgpt-mcp-bridge' ),
                'input_schema' => $this->media_list_schema(),
                'execute_callback' => array( $this, 'media_audits_query' ),
                'output_schema' => $this->object_schema(),
                'permission_callback' => array( $this, 'can_edit_content' ),
            ),
            'wpgpt/media-audits-inspect' => array(
                'label' => __( 'Media audits inspect', 'wpgpt-mcp-bridge' ),
                'description' => __( 'Inspecciona el uso o los archivos físicos de un adjunto.', 'wpgpt-mcp-bridge' ),
                'input_schema' => $this->media_audits_inspect_schema(),
                'execute_callback' => array( $this, 'media_audits_inspect' ),
                'output_schema' => $this->object_schema(),
                'permission_callback' => array( $this, 'can_edit_content' ),
            ),
            'wpgpt/media-audits-apply' => array(
                'label' => __( 'Media audits apply', 'wpgpt-mcp-bridge' ),
                'description' => __( 'Valida acciones de auditoría de medios, con soporte dry_run.', 'wpgpt-mcp-bridge' ),
                'input_schema' => $this->media_audits_apply_schema(),
                'execute_callback' => array( $this, 'media_audits_apply' ),
                'output_schema' => $this->object_schema(),
                'permission_callback' => array( $this, 'can_edit_content' ),
            ),
            'wpgpt/media-query' => array(
                'label' => __( 'Media query', 'wpgpt-mcp-bridge' ),
                'description' => __( 'Lista, filtra y resume adjuntos y medios del sitio.', 'wpgpt-mcp-bridge' ),
                'input_schema' => $this->media_query_schema(),
                'execute_callback' => array( $this, 'media_query' ),
                'output_schema' => $this->object_schema(),
                'permission_callback' => array( $this, 'can_edit_content' ),
            ),
            'wpgpt/media-inspect' => array(
                'label' => __( 'Media inspect', 'wpgpt-mcp-bridge' ),
                'description' => __( 'Inspecciona uno o varios adjuntos por attachment_id.', 'wpgpt-mcp-bridge' ),
                'input_schema' => $this->media_inspect_schema(),
                'execute_callback' => array( $this, 'media_inspect' ),
                'output_schema' => $this->object_schema(),
                'permission_callback' => array( $this, 'can_edit_content' ),
            ),
            'wpgpt/media-apply' => array(
                'label' => __( 'Media apply', 'wpgpt-mcp-bridge' ),
                'description' => __( 'Ejecuta acciones controladas sobre adjuntos, con soporte dry_run.', 'wpgpt-mcp-bridge' ),
                'input_schema' => $this->media_apply_schema(),
                'execute_callback' => array( $this, 'media_apply' ),
                'output_schema' => $this->object_schema(),
                'permission_callback' => array( $this, 'can_write_content' ),
            ),
        );
    }

    public function media_audits_query( array $input ) { return $this->audit_service()->unused_list( $input ); }
    public function media_audits_inspect( array $input ) { $scope = (string)($input['scope'] ?? 'usage'); if($scope === 'files') return $this->audit_service()->files_audit($input); return $this->audit_service()->usage_audit($input); }
    public function media_audits_apply( array $input ) { $dry = ! empty($input['dry_run']); return array('summary'=>array('action'=>'audit','dry_run'=>$dry,'executed'=>0),'items'=>array(),'warnings'=>array(),'blocked'=>array(),'next_actions'=>array()); }
    public function media_query( array $input = array() ): array|WP_Error { return $this->service()->query( $input ); }
    public function media_inspect( array $input = array() ): array|WP_Error { return $this->service()->inspect( $input ); }
    public function media_apply( array $input = array() ): array|WP_Error { return $this->service()->apply( $input ); }

    private function audit_service(): Media_Audit_Service {
        if ( null === $this->audit_service ) {
            $this->audit_service = new Media_Audit_Service();
        }
        return $this->audit_service;
    }

    private function service(): Media_Service {
        if ( null === $this->service ) {
            $this->service = new Media_Service();
        }
        return $this->service;
    }

    private function media_list_schema(): array { return array( 'type' => 'object', 'properties' => array( 'search' => array( 'type' => 'string' ), 'limit' => array( 'type' => 'integer' ) ) ); }
    private function media_audits_inspect_schema(): array {
        return array('type'=>'object','additionalProperties'=>false,'properties'=>array('scope'=>array('type'=>'string','enum'=>array('usage','files')),'attachment_id'=>array('type'=>'integer'),'max_scan'=>array('type'=>'integer'),'limit'=>array('type'=>'integer')));
    }
    private function media_audits_apply_schema(): array {
        return array('type'=>'object','additionalProperties'=>false,'properties'=>array('action'=>array('type'=>'string','enum'=>array('audit')),'dry_run'=>array('type'=>'boolean')),'required'=>array('action'));
    }

    private function media_query_schema(): array {
        return array(
            'type' => 'object',
            'additionalProperties' => false,
            'properties' => array(
                'search' => array( 'type' => 'string' ),
                'filters' => array(
                    'type' => 'object',
                    'additionalProperties' => false,
                    'properties' => array(
                        'attachment_id' => array( 'type' => 'integer' ),
                        'mime_type' => array( 'type' => 'string' ),
                        'author_id' => array( 'type' => 'integer' ),
                        'uploaded_to_post_id' => array( 'type' => 'integer' ),
                        'unattached' => array( 'type' => 'boolean' ),
                    ),
                ),
                'limit' => array( 'type' => 'integer', 'minimum' => 1, 'maximum' => 200 ),
                'offset' => array( 'type' => 'integer', 'minimum' => 0 ),
            ),
        );
    }

    private function media_inspect_schema(): array {
        return array(
            'type' => 'object',
            'additionalProperties' => false,
            'properties' => array(
                'attachment_id' => array( 'type' => 'integer' ),
                'attachment_ids' => array( 'type' => 'array', 'items' => array( 'type' => 'integer' ) ),
            ),
        );
    }

    private function media_apply_schema(): array {
        return array(
            'type' => 'object',
            'additionalProperties' => false,
            'properties' => array(
                'action' => array( 'type' => 'string', 'enum' => array( 'update', 'rename_file', 'sideload', 'set_featured_image', 'delete' ) ),
                'dry_run' => array( 'type' => 'boolean' ),
                'targets' => array(
                    'type' => 'array',
                    'items' => array(
                        'type' => 'object',
                        'additionalProperties' => false,
                        'properties' => array(
                            'attachment_id' => array( 'type' => 'integer' ),
                        ),
                    ),
                ),
                'filters' => array(
                    'type' => 'object',
                    'additionalProperties' => false,
                    'properties' => array(
                        'attachment_id' => array( 'type' => 'integer' ),
                        'mime_type' => array( 'type' => 'string' ),
                        'author_id' => array( 'type' => 'integer' ),
                        'uploaded_to_post_id' => array( 'type' => 'integer' ),
                        'unattached' => array( 'type' => 'boolean' ),
                        'search' => array( 'type' => 'string' ),
                    ),
                ),
                'payload' => array(
                    'type' => 'object',
                    'additionalProperties' => true,
                    'properties' => array(
                        'attachment_id' => array( 'type' => 'integer' ),
                        'title' => array( 'type' => 'string' ),
                        'alt' => array( 'type' => 'string' ),
                        'caption' => array( 'type' => 'string' ),
                        'description' => array( 'type' => 'string' ),
                        'new_filename' => array( 'type' => 'string' ),
                        'url' => array( 'type' => 'string' ),
                        'post_id' => array( 'type' => 'integer' ),
                        'force' => array( 'type' => 'boolean' ),
                    ),
                ),
            ),
            'required' => array( 'action' ),
        );
    }
}
