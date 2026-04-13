<?php
namespace WPGPT\MCPBridge;
use WPGPT\MCPBridge\Environment\Environment_Service;
if ( ! defined( 'ABSPATH' ) ) { exit; }
class Environment_Provider extends Base_Ability_Provider {
    private ?Environment_Service $service = null;
    public function get_abilities(): array {
        return array(
            'wpgpt/environment-query' => array(
                'label' => __( 'Environment query', 'wpgpt-mcp-bridge' ),
                'description' => __( 'Resume el entorno del sitio por secciones.', 'wpgpt-mcp-bridge' ),
                'input_schema' => array('type'=>'object','properties'=>array('sections'=>array('type'=>'array','items'=>array('type'=>'string')))),
                'execute_callback' => array( $this, 'environment_query' ),
                'output_schema' => $this->object_schema(),
                'permission_callback' => array( $this, 'can_manage_site' ),
            ),
            'wpgpt/environment-inspect' => array(
                'label' => __( 'Environment inspect', 'wpgpt-mcp-bridge' ),
                'description' => __( 'Inspecciona secciones concretas del entorno y puede incluir hallazgos.', 'wpgpt-mcp-bridge' ),
                'input_schema' => array('type'=>'object','properties'=>array('sections'=>array('type'=>'array','items'=>array('type'=>'string')),'include_audit'=>array('type'=>'boolean'))),
                'execute_callback' => array( $this, 'environment_inspect' ),
                'output_schema' => $this->object_schema(),
                'permission_callback' => array( $this, 'can_manage_site' ),
            ),
            'wpgpt/environment-apply' => array(
                'label' => __( 'Environment apply', 'wpgpt-mcp-bridge' ),
                'description' => __( 'Ejecuta comprobaciones controladas del entorno, con soporte dry_run.', 'wpgpt-mcp-bridge' ),
                'input_schema' => array('type'=>'object','properties'=>array('action'=>array('type'=>'string','enum'=>array('audit')),'dry_run'=>array('type'=>'boolean'),'sections'=>array('type'=>'array','items'=>array('type'=>'string'))),'required'=>array('action')),
                'execute_callback' => array( $this, 'environment_apply' ),
                'output_schema' => $this->object_schema(),
                'permission_callback' => array( $this, 'can_manage_site' ),
            ),
        );
    }
    private function service(): Environment_Service { return $this->service ??= new Environment_Service(); }
    public function environment_query( array $input ): array {
        $report = $this->service()->report( $input );
        $sections = isset($report['report']) && is_array($report['report']) ? $report['report'] : array();
        return array('summary'=>array('sections'=>array_keys($sections),'count'=>count($sections)),'items'=>$sections,'warnings'=>array(),'next_actions'=>array());
    }
    public function environment_inspect( array $input ): array {
        $report = $this->service()->report( $input );
        $items = array('report'=>$report['report'] ?? array());
        if ( ! empty( $input['include_audit'] ) ) {
            $items['audit'] = $this->service()->audit( $input );
        }
        return array('summary'=>array('sections'=>array_keys($items['report'] ?? array()),'inspected'=>count($items['report'] ?? array())),'items'=>$items,'warnings'=>array(),'next_actions'=>array());
    }
    public function environment_apply( array $input ): array {
        $dry = ! empty( $input['dry_run'] );
        $action = sanitize_key( (string) ( $input['action'] ?? '' ) );
        if ( 'audit' !== $action ) {
            return array('summary'=>array('action'=>$action,'dry_run'=>$dry,'executed'=>0,'blocked'=>1),'items'=>array(),'warnings'=>array(),'blocked'=>array(array('reason'=>'Acción no soportada.')),'next_actions'=>array());
        }
        $audit = $this->service()->audit( $input );
        return array('summary'=>array('action'=>'audit','dry_run'=>$dry,'executed'=>$dry ? 0 : 1,'blocked'=>0),'items'=>array($audit),'warnings'=>$audit['warnings'] ?? array(),'blocked'=>array(),'next_actions'=>array());
    }
}
