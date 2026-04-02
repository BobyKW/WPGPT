<?php
namespace WPGPT\MCPBridge;
use WPGPT\MCPBridge\ACF\ACF_Service;
if ( ! defined( 'ABSPATH' ) ) { exit; }
class ACF_Provider extends Base_Ability_Provider {
    private ?ACF_Service $service = null;
    public function get_abilities(): array { return array(
        'wpgpt/acf-field-groups-list'=>array('label'=>__('ACF field groups list','wpgpt-mcp-bridge'),'description'=>__('Lista grupos de campos ACF.','wpgpt-mcp-bridge'),'input_schema'=>array('type'=>'object','properties'=>array('active_only'=>array('type'=>'boolean'))),'execute_callback'=>array($this,'field_groups_list'),'output_schema'=>$this->object_schema(),'permission_callback'=>array($this,'can_edit_content')),
        'wpgpt/acf-fields-get'=>array('label'=>__('ACF fields get','wpgpt-mcp-bridge'),'description'=>__('Obtiene los campos de un grupo ACF.','wpgpt-mcp-bridge'),'input_schema'=>array('type'=>'object','properties'=>array('group_key'=>array('type'=>'string'),'group_id'=>array('type'=>'integer'))),'execute_callback'=>array($this,'fields_get'),'output_schema'=>$this->object_schema(),'permission_callback'=>array($this,'can_edit_content')),
        'wpgpt/acf-values-get'=>array('label'=>__('ACF values get','wpgpt-mcp-bridge'),'description'=>__('Lee valores ACF de un objeto.','wpgpt-mcp-bridge'),'input_schema'=>$this->values_schema(false),'execute_callback'=>array($this,'values_get'),'output_schema'=>$this->object_schema(),'permission_callback'=>array($this,'can_edit_content')),
        'wpgpt/acf-values-update'=>array('label'=>__('ACF values update','wpgpt-mcp-bridge'),'description'=>__('Actualiza valores ACF.','wpgpt-mcp-bridge'),'input_schema'=>$this->values_schema(true),'execute_callback'=>array($this,'values_update'),'output_schema'=>$this->object_schema(),'permission_callback'=>array($this,'can_write_content')),
    ); }
    public function field_groups_list(array $input){ return $this->service()->list_field_groups($input);} public function fields_get(array $input){ return $this->service()->get_fields($input);} public function values_get(array $input){ return $this->service()->get_values($input);} public function values_update(array $input){ return $this->service()->update_values($input);} private function service(): ACF_Service { return $this->service ??= new ACF_Service(); }
    private function values_schema(bool $update): array { $schema=array('type'=>'object','properties'=>array('target_type'=>array('type'=>'string'),'target_id'=>array('type'=>'integer'),'taxonomy'=>array('type'=>'string'),'fields'=>array('type'=>'array','items'=>array('type'=>'string')))); if($update){$schema['properties']['values']=array('type'=>'object','additionalProperties'=>true); $schema['required']=array('target_type','values');} else {$schema['required']=array('target_type');} return $schema; }
}
