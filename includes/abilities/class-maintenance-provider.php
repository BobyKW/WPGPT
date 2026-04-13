<?php
namespace WPGPT\MCPBridge;
use WPGPT\MCPBridge\Maintenance\Maintenance_Service;
use WP_Error;
if ( ! defined( 'ABSPATH' ) ) { exit; }
class Maintenance_Provider extends Base_Ability_Provider {
    private ?Maintenance_Service $service = null;
    public function get_abilities(): array { return array(
        'wpgpt/maintenance-query'=>array('label'=>__('Maintenance query','wpgpt-mcp-bridge'),'description'=>__('Resume caché, transients y modo mantenimiento.','wpgpt-mcp-bridge'),'input_schema'=>array('type'=>'object','properties'=>array('scope'=>array('type'=>'string','enum'=>array('all','cache','transients','maintenance_mode')))),'execute_callback'=>array($this,'maintenance_query'),'output_schema'=>$this->object_schema(),'permission_callback'=>array($this,'can_manage_site')),
        'wpgpt/maintenance-inspect'=>array('label'=>__('Maintenance inspect','wpgpt-mcp-bridge'),'description'=>__('Inspecciona un área concreta de mantenimiento.','wpgpt-mcp-bridge'),'input_schema'=>array('type'=>'object','properties'=>array('scope'=>array('type'=>'string','enum'=>array('cache','transients','maintenance_mode','media_regenerate','search_replace')),'search'=>array('type'=>'string'),'limit'=>array('type'=>'integer'))),'execute_callback'=>array($this,'maintenance_inspect'),'output_schema'=>$this->object_schema(),'permission_callback'=>array($this,'can_manage_site')),
        'wpgpt/maintenance-apply'=>array('label'=>__('Maintenance apply','wpgpt-mcp-bridge'),'description'=>__('Ejecuta acciones controladas de mantenimiento, con soporte dry_run.','wpgpt-mcp-bridge'),'input_schema'=>array('type'=>'object','properties'=>array('action'=>array('type'=>'string','enum'=>array('flush_cache','delete_transients','delete_expired_transients','media_regenerate','search_replace','set_maintenance_mode')),'dry_run'=>array('type'=>'boolean'),'payload'=>array('type'=>'object','additionalProperties'=>true)),'required'=>array('action')),'execute_callback'=>array($this,'maintenance_apply'),'output_schema'=>$this->object_schema(),'permission_callback'=>array($this,'can_manage_site')),
    ); }
    public function maintenance_query(array $input=array()): array|WP_Error { return $this->service()->query($input);} public function maintenance_inspect(array $input=array()): array|WP_Error { return $this->service()->inspect($input);} public function maintenance_apply(array $input=array()): array|WP_Error { return $this->service()->apply($input);} private function service(): Maintenance_Service { return $this->service ??= new Maintenance_Service(); }
}
