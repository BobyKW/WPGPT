<?php
namespace WPGPT\MCPBridge;
use WPGPT\MCPBridge\Environment\Environment_Service;
if ( ! defined( 'ABSPATH' ) ) { exit; }
class Environment_Provider extends Base_Ability_Provider {
    private ?Environment_Service $service = null;
    public function get_abilities(): array { return array(
        'wpgpt/environment-report'=>array('label'=>__('Environment report','wpgpt-mcp-bridge'),'description'=>__('Genera un reporte estructurado del entorno.','wpgpt-mcp-bridge'),'input_schema'=>array('type'=>'object','properties'=>array('sections'=>array('type'=>'array','items'=>array('type'=>'string')))),'execute_callback'=>array($this,'environment_report'),'output_schema'=>$this->object_schema(),'permission_callback'=>array($this,'can_manage_site')),
        'wpgpt/environment-audit'=>array('label'=>__('Environment audit','wpgpt-mcp-bridge'),'description'=>__('Audita el entorno y devuelve hallazgos.','wpgpt-mcp-bridge'),'input_schema'=>array('type'=>'object','properties'=>array('scope'=>array('type'=>'string'),'include_recommendations'=>array('type'=>'boolean'))),'execute_callback'=>array($this,'environment_audit'),'output_schema'=>$this->object_schema(),'permission_callback'=>array($this,'can_manage_site')),
    ); }
    public function environment_report(array $input){ return $this->service()->report($input);} public function environment_audit(array $input){ return $this->service()->audit($input);} private function service(): Environment_Service { return $this->service ??= new Environment_Service(); }
}
