<?php
namespace WPGPT\MCPBridge;
use WPGPT\MCPBridge\Transfer\Transfer_Service;
if ( ! defined( 'ABSPATH' ) ) { exit; }
class Transfer_Provider extends Base_Ability_Provider {
    private ?Transfer_Service $service = null;
    public function get_abilities(): array { return array(
        'wpgpt/export-run'=>array('label'=>__('Export run','wpgpt-mcp-bridge'),'description'=>__('Exporta datos del sitio.','wpgpt-mcp-bridge'),'input_schema'=>array('type'=>'object','properties'=>array('resource'=>array('type'=>'string'),'filters'=>array('type'=>'object','additionalProperties'=>true),'format'=>array('type'=>'string'),'fields'=>array('type'=>'array','items'=>array('type'=>'string'))),'required'=>array('resource')),'execute_callback'=>array($this,'export_run'),'output_schema'=>$this->object_schema(),'permission_callback'=>array($this,'can_manage_site')),
        'wpgpt/import-parse'=>array('label'=>__('Import parse','wpgpt-mcp-bridge'),'description'=>__('Valida y previsualiza una importación.','wpgpt-mcp-bridge'),'input_schema'=>$this->import_schema(false),'execute_callback'=>array($this,'import_parse'),'output_schema'=>$this->object_schema(),'permission_callback'=>array($this,'can_manage_site')),
        'wpgpt/import-run'=>array('label'=>__('Import run','wpgpt-mcp-bridge'),'description'=>__('Ejecuta una importación.','wpgpt-mcp-bridge'),'input_schema'=>$this->import_schema(true),'execute_callback'=>array($this,'import_run'),'output_schema'=>$this->object_schema(),'permission_callback'=>array($this,'can_manage_site')),
    ); }
    public function export_run(array $input){ return $this->service()->export_run($input);} public function import_parse(array $input){ return $this->service()->import_parse($input);} public function import_run(array $input){ return $this->service()->import_run($input);} private function service(): Transfer_Service { return $this->service ??= new Transfer_Service(); }
    private function import_schema(bool $run): array { $schema=array('type'=>'object','properties'=>array('resource'=>array('type'=>'string'),'source_type'=>array('type'=>'string'),'source_content'=>array('type'=>'string'),'mapping'=>array('type'=>'object','additionalProperties'=>true)),'required'=>array('resource','source_type','source_content')); if($run){$schema['properties']['mode']=array('type'=>'string');} return $schema; }
}
