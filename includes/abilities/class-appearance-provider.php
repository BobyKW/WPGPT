<?php
namespace WPGPT\MCPBridge;
use WPGPT\MCPBridge\Appearance\Appearance_Service;
if ( ! defined( 'ABSPATH' ) ) { exit; }
class Appearance_Provider extends Base_Ability_Provider {
    private ?Appearance_Service $service = null;
    public function get_abilities(): array { return array(
        'wpgpt/theme-mods-get'=>array('label'=>__('Theme mods get','wpgpt-mcp-bridge'),'description'=>__('Lee los theme mods.','wpgpt-mcp-bridge'),'input_schema'=>array('type'=>'object','properties'=>array('keys'=>array('type'=>'array','items'=>array('type'=>'string')))),'execute_callback'=>array($this,'theme_mods_get'),'output_schema'=>$this->object_schema(),'permission_callback'=>array($this,'can_manage_site')),
        'wpgpt/theme-mods-update'=>array('label'=>__('Theme mods update','wpgpt-mcp-bridge'),'description'=>__('Actualiza theme mods.','wpgpt-mcp-bridge'),'input_schema'=>array('type'=>'object','properties'=>array('updates'=>array('type'=>'object','additionalProperties'=>true)),'required'=>array('updates')),'execute_callback'=>array($this,'theme_mods_update'),'output_schema'=>$this->object_schema(),'permission_callback'=>array($this,'can_manage_site')),
        'wpgpt/site-identity-get'=>array('label'=>__('Site identity get','wpgpt-mcp-bridge'),'description'=>__('Lee la identidad del sitio.','wpgpt-mcp-bridge'),'execute_callback'=>array($this,'site_identity_get'),'output_schema'=>$this->object_schema(),'permission_callback'=>array($this,'can_manage_site')),
        'wpgpt/site-identity-update'=>array('label'=>__('Site identity update','wpgpt-mcp-bridge'),'description'=>__('Actualiza la identidad del sitio.','wpgpt-mcp-bridge'),'input_schema'=>array('type'=>'object','properties'=>array('title'=>array('type'=>'string'),'tagline'=>array('type'=>'string'),'logo_id'=>array('type'=>'integer'),'site_icon_id'=>array('type'=>'integer'))),'execute_callback'=>array($this,'site_identity_update'),'output_schema'=>$this->object_schema(),'permission_callback'=>array($this,'can_manage_site')),
    ); }
    public function theme_mods_get(array $input){ return $this->service()->get_theme_mods($input);} public function theme_mods_update(array $input){ return $this->service()->update_theme_mods($input);} public function site_identity_get(){ return $this->service()->get_site_identity();} public function site_identity_update(array $input){ return $this->service()->update_site_identity($input);} private function service(): Appearance_Service { return $this->service ??= new Appearance_Service(); }
}
