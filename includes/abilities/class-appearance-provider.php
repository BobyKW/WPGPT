<?php
namespace WPGPT\MCPBridge;
use WPGPT\MCPBridge\Appearance\Appearance_Service;
if ( ! defined( 'ABSPATH' ) ) { exit; }
class Appearance_Provider extends Base_Ability_Provider {
    private ?Appearance_Service $service = null;
    public function get_abilities(): array { return array(
        'wpgpt/appearance-query'=>array('label'=>__('Appearance query','wpgpt-mcp-bridge'),'description'=>__('Lista y resume identidad del sitio y theme mods del tema activo.','wpgpt-mcp-bridge'),'input_schema'=>array('type'=>'object','additionalProperties'=>false,'properties'=>array('scope'=>array('type'=>'string','enum'=>array('all','identity','theme_mods')),'keys'=>array('type'=>'array','items'=>array('type'=>'string')))),'execute_callback'=>array($this,'appearance_query'),'output_schema'=>$this->object_schema(),'permission_callback'=>array($this,'can_manage_site')),
        'wpgpt/appearance-inspect'=>array('label'=>__('Appearance inspect','wpgpt-mcp-bridge'),'description'=>__('Inspecciona la identidad del sitio o claves concretas de theme mods.','wpgpt-mcp-bridge'),'input_schema'=>array('type'=>'object','additionalProperties'=>false,'properties'=>array('scope'=>array('type'=>'string','enum'=>array('identity','theme_mods')),'keys'=>array('type'=>'array','items'=>array('type'=>'string')))),'execute_callback'=>array($this,'appearance_inspect'),'output_schema'=>$this->object_schema(),'permission_callback'=>array($this,'can_manage_site')),
        'wpgpt/appearance-apply'=>array('label'=>__('Appearance apply','wpgpt-mcp-bridge'),'description'=>__('Ejecuta acciones controladas sobre identidad del sitio y theme mods, con soporte dry_run.','wpgpt-mcp-bridge'),'input_schema'=>array('type'=>'object','additionalProperties'=>false,'properties'=>array('action'=>array('type'=>'string','enum'=>array('update_identity','update_theme_mods')),'dry_run'=>array('type'=>'boolean'),'payload'=>array('type'=>'object','additionalProperties'=>true)),'required'=>array('action')),'execute_callback'=>array($this,'appearance_apply'),'output_schema'=>$this->object_schema(),'permission_callback'=>array($this,'can_manage_site')),
    ); }
    public function appearance_query(array $input){ return $this->service()->query($input);} 
    public function appearance_inspect(array $input){ return $this->service()->inspect($input);} 
    public function appearance_apply(array $input){ return $this->service()->apply($input);} 
    private function service(): Appearance_Service { return $this->service ??= new Appearance_Service(); }
}
