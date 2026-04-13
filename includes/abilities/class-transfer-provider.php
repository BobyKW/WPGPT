<?php
namespace WPGPT\MCPBridge;
use WPGPT\MCPBridge\Transfer\Transfer_Service;
use WP_Error;
if ( ! defined( 'ABSPATH' ) ) { exit; }
class Transfer_Provider extends Base_Ability_Provider {
    private ?Transfer_Service $service = null;
    public function get_abilities(): array { return array(
        'wpgpt/transfer-query'=>array('label'=>__('Transfer query','wpgpt-mcp-bridge'),'description'=>__('Resume recursos exportables e importables.','wpgpt-mcp-bridge'),'input_schema'=>array('type'=>'object','properties'=>array('scope'=>array('type'=>'string','enum'=>array('all','export','import')))),'execute_callback'=>array($this,'transfer_query'),'output_schema'=>$this->object_schema(),'permission_callback'=>array($this,'can_manage_site')),
        'wpgpt/transfer-inspect'=>array('label'=>__('Transfer inspect','wpgpt-mcp-bridge'),'description'=>__('Inspecciona una operación de exportación o importación.','wpgpt-mcp-bridge'),'input_schema'=>array('type'=>'object','properties'=>array('scope'=>array('type'=>'string','enum'=>array('export','import')),'resource'=>array('type'=>'string'),'source_type'=>array('type'=>'string'),'source_content'=>array('type'=>'string'))),'execute_callback'=>array($this,'transfer_inspect'),'output_schema'=>$this->object_schema(),'permission_callback'=>array($this,'can_manage_site')),
        'wpgpt/transfer-apply'=>array('label'=>__('Transfer apply','wpgpt-mcp-bridge'),'description'=>__('Ejecuta exportaciones o importaciones controladas, con soporte dry_run.','wpgpt-mcp-bridge'),'input_schema'=>array('type'=>'object','properties'=>array('action'=>array('type'=>'string','enum'=>array('export','import')),'dry_run'=>array('type'=>'boolean'),'payload'=>array('type'=>'object','additionalProperties'=>true)),'required'=>array('action','payload')),'execute_callback'=>array($this,'transfer_apply'),'output_schema'=>$this->object_schema(),'permission_callback'=>array($this,'can_manage_site')),
    ); }
    public function transfer_query(array $input=array()): array|WP_Error { return $this->service()->query($input);} public function transfer_inspect(array $input=array()): array|WP_Error { return $this->service()->inspect($input);} public function transfer_apply(array $input=array()): array|WP_Error { return $this->service()->apply($input);} private function service(): Transfer_Service { return $this->service ??= new Transfer_Service(); }
}
