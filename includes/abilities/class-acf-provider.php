<?php
namespace WPGPT\MCPBridge;
use WPGPT\MCPBridge\ACF\ACF_Service;
use WP_Error;
if ( ! defined( 'ABSPATH' ) ) { exit; }
class ACF_Provider extends Base_Ability_Provider {
    private ?ACF_Service $service = null;
    public function get_abilities(): array { return array(
        'wpgpt/acf-query'=>array('label'=>__('ACF query','wpgpt-mcp-bridge'),'description'=>__('Lista y resume grupos de campos ACF y objetivos de valores ACF.','wpgpt-mcp-bridge'),'input_schema'=>$this->query_schema(),'execute_callback'=>array($this,'acf_query'),'output_schema'=>$this->object_schema(),'permission_callback'=>array($this,'can_edit_content')),
        'wpgpt/acf-inspect'=>array('label'=>__('ACF inspect','wpgpt-mcp-bridge'),'description'=>__('Inspecciona grupos de campos ACF y/o valores ACF de un objetivo.','wpgpt-mcp-bridge'),'input_schema'=>$this->inspect_schema(),'execute_callback'=>array($this,'acf_inspect'),'output_schema'=>$this->object_schema(),'permission_callback'=>array($this,'can_edit_content')),
        'wpgpt/acf-apply'=>array('label'=>__('ACF apply','wpgpt-mcp-bridge'),'description'=>__('Ejecuta cambios controlados sobre valores ACF, con soporte dry_run.','wpgpt-mcp-bridge'),'input_schema'=>$this->apply_schema(),'execute_callback'=>array($this,'acf_apply'),'output_schema'=>$this->object_schema(),'permission_callback'=>array($this,'can_write_content')),
    ); }
    public function acf_query(array $input=array()): array|WP_Error { return $this->service()->query($input);} public function acf_inspect(array $input=array()): array|WP_Error { return $this->service()->inspect($input);} public function acf_apply(array $input=array()): array|WP_Error { return $this->service()->apply($input);} private function service(): ACF_Service { return $this->service ??= new ACF_Service(); }
    private function query_schema(): array { return array('type'=>'object','additionalProperties'=>false,'properties'=>array('filters'=>array('type'=>'object','additionalProperties'=>false,'properties'=>array('active_only'=>array('type'=>'boolean'),'group_key'=>array('type'=>'string'),'group_id'=>array('type'=>'integer'),'target_type'=>array('type'=>'string'),'target_id'=>array('type'=>'integer'),'taxonomy'=>array('type'=>'string'))))); }
    private function inspect_schema(): array { return array('type'=>'object','additionalProperties'=>false,'properties'=>array('group_key'=>array('type'=>'string'),'group_id'=>array('type'=>'integer'),'target_type'=>array('type'=>'string'),'target_id'=>array('type'=>'integer'),'taxonomy'=>array('type'=>'string'),'fields'=>array('type'=>'array','items'=>array('type'=>'string')),'include_fields'=>array('type'=>'boolean'),'include_values'=>array('type'=>'boolean'))); }
    private function apply_schema(): array { return array('type'=>'object','additionalProperties'=>false,'properties'=>array('action'=>array('type'=>'string','enum'=>array('values_update')),'dry_run'=>array('type'=>'boolean'),'target'=>array('type'=>'object','additionalProperties'=>false,'properties'=>array('target_type'=>array('type'=>'string'),'target_id'=>array('type'=>'integer'),'taxonomy'=>array('type'=>'string'))),'payload'=>array('type'=>'object','additionalProperties'=>true,'properties'=>array('values'=>array('type'=>'object','additionalProperties'=>true)))),'required'=>array('action')); }
}
