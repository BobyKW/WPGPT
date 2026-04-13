<?php
namespace WPGPT\MCPBridge;
use WPGPT\MCPBridge\BlockEditor\Block_Editor_Service;
use WP_Error;
if ( ! defined( 'ABSPATH' ) ) { exit; }
class Block_Editor_Provider extends Base_Ability_Provider {
    private ?Block_Editor_Service $service = null;
    public function get_abilities(): array { return array(
        'wpgpt/blocks-query'=>array('label'=>__('Blocks query','wpgpt-mcp-bridge'),'description'=>__('Lista, filtra y resume entidades del editor de bloques.','wpgpt-mcp-bridge'),'input_schema'=>$this->query_schema(),'execute_callback'=>array($this,'blocks_query'),'output_schema'=>$this->object_schema(),'permission_callback'=>array($this,'can_manage_site')),
        'wpgpt/blocks-inspect'=>array('label'=>__('Blocks inspect','wpgpt-mcp-bridge'),'description'=>__('Inspecciona una o varias entidades del editor de bloques.','wpgpt-mcp-bridge'),'input_schema'=>$this->inspect_schema(),'execute_callback'=>array($this,'blocks_inspect'),'output_schema'=>$this->object_schema(),'permission_callback'=>array($this,'can_manage_site')),
        'wpgpt/blocks-apply'=>array('label'=>__('Blocks apply','wpgpt-mcp-bridge'),'description'=>__('Ejecuta acciones controladas sobre entidades del editor de bloques, con soporte dry_run.','wpgpt-mcp-bridge'),'input_schema'=>$this->apply_schema(),'execute_callback'=>array($this,'blocks_apply'),'output_schema'=>$this->object_schema(),'permission_callback'=>array($this,'can_manage_site')),
    ); }
    public function blocks_query(array $input=array()): array|WP_Error { return $this->service()->query($input);} public function blocks_inspect(array $input=array()): array|WP_Error { return $this->service()->inspect($input);} public function blocks_apply(array $input=array()): array|WP_Error { return $this->service()->apply($input);} private function service(): Block_Editor_Service { return $this->service ??= new Block_Editor_Service(); }
    private function query_schema(): array { return array('type'=>'object','additionalProperties'=>false,'properties'=>array('filters'=>array('type'=>'object','additionalProperties'=>false,'properties'=>array('entity_type'=>array('type'=>'string'),'status'=>array('type'=>'string'),'theme'=>array('type'=>'string'),'search'=>array('type'=>'string'))),'limit'=>array('type'=>'integer','minimum'=>1,'maximum'=>100),'offset'=>array('type'=>'integer','minimum'=>0))); }
    private function inspect_schema(): array { return array('type'=>'object','additionalProperties'=>false,'properties'=>array('entity_type'=>array('type'=>'string'),'id'=>array('type'=>'integer'),'ids'=>array('type'=>'array','items'=>array('type'=>'integer')))); }
    private function apply_schema(): array { return array('type'=>'object','additionalProperties'=>false,'properties'=>array('action'=>array('type'=>'string','enum'=>array('upsert','delete')),'dry_run'=>array('type'=>'boolean'),'targets'=>array('type'=>'array','items'=>array('type'=>'object','additionalProperties'=>false,'properties'=>array('entity_type'=>array('type'=>'string'),'id'=>array('type'=>'integer')))),'payload'=>array('type'=>'object','additionalProperties'=>true,'properties'=>array('entity_type'=>array('type'=>'string'),'id'=>array('type'=>'integer'),'title'=>array('type'=>'string'),'slug'=>array('type'=>'string'),'status'=>array('type'=>'string'),'content'=>array('type'=>'string'),'force'=>array('type'=>'boolean')))),'required'=>array('action')); }
}
