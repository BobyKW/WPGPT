<?php
namespace WPGPT\MCPBridge;
use WPGPT\MCPBridge\WooCommerce\WooCommerce_Service;
use WP_Error;
if ( ! defined( 'ABSPATH' ) ) { exit; }
class WooCommerce_Provider extends Base_Ability_Provider {
    private ?WooCommerce_Service $service = null;
    public function get_abilities(): array { return array(
        'wpgpt/wc-query'=>array('label'=>__('WC query','wpgpt-mcp-bridge'),'description'=>__('Lista y resume recursos o informes WooCommerce.','wpgpt-mcp-bridge'),'input_schema'=>$this->wc_query_schema(),'execute_callback'=>array($this,'wc_query'),'output_schema'=>$this->object_schema(),'permission_callback'=>array($this,'can_manage_site')),
        'wpgpt/wc-inspect'=>array('label'=>__('WC inspect','wpgpt-mcp-bridge'),'description'=>__('Inspecciona un recurso WooCommerce concreto.','wpgpt-mcp-bridge'),'input_schema'=>$this->wc_inspect_schema(),'execute_callback'=>array($this,'wc_inspect'),'output_schema'=>$this->object_schema(),'permission_callback'=>array($this,'can_manage_site')),
        'wpgpt/wc-apply'=>array('label'=>__('WC apply','wpgpt-mcp-bridge'),'description'=>__('Ejecuta acciones controladas sobre WooCommerce, con soporte dry_run.','wpgpt-mcp-bridge'),'input_schema'=>$this->wc_apply_schema(),'execute_callback'=>array($this,'wc_apply'),'output_schema'=>$this->object_schema(),'permission_callback'=>array($this,'can_manage_site')),
    ); }
    public function wc_query(array $input){
        if ( ! empty( $input['report'] ) ) { return $this->service()->report_summary($input); }
        return $this->service()->resource_query($input);
    }
    public function wc_inspect(array $input){ return $this->service()->resource_get($input); }
    public function wc_apply(array $input){
        $action = isset($input['action']) ? sanitize_key((string)$input['action']) : '';
        $dry_run = ! empty($input['dry_run']);
        if ($dry_run) {
            return array(
                'summary'=>array('action'=>$action,'dry_run'=>true,'executed'=>0),
                'items'=>array(array('status'=>'dry_run','action'=>$action,'message'=>__('Acción validada, no ejecutada por dry_run.','wpgpt-mcp-bridge'))),
                'warnings'=>array(),'blocked'=>array(),'next_actions'=>array(),
            );
        }
        if ('order_action' === $action) { return $this->service()->order_action($input); }
        if ('upsert' === $action) { return $this->service()->resource_upsert($input); }
        return new WP_Error('wpgpt_invalid_action', __('Acción WooCommerce no soportada.','wpgpt-mcp-bridge'), array('status'=>400));
    }
    private function service(): WooCommerce_Service { return $this->service ??= new WooCommerce_Service(); }
    private function wc_query_schema(): array { return array('type'=>'object','additionalProperties'=>false,'properties'=>array('resource'=>array('type'=>'string'),'filters'=>array('type'=>'object','additionalProperties'=>true),'search'=>array('type'=>'string'),'page'=>array('type'=>'integer'),'per_page'=>array('type'=>'integer'),'orderby'=>array('type'=>'string'),'order'=>array('type'=>'string'),'report'=>array('type'=>'string'),'date_from'=>array('type'=>'string'),'date_to'=>array('type'=>'string'),'limit'=>array('type'=>'integer'))); }
    private function wc_inspect_schema(): array { return array('type'=>'object','additionalProperties'=>false,'properties'=>array('resource'=>array('type'=>'string'),'id'=>array('type'=>'integer')),'required'=>array('resource','id')); }
    private function wc_apply_schema(): array { return array('type'=>'object','additionalProperties'=>false,'properties'=>array('action'=>array('type'=>'string','enum'=>array('upsert','order_action')),'dry_run'=>array('type'=>'boolean'),'resource'=>array('type'=>'string'),'id'=>array('type'=>'integer'),'data'=>array('type'=>'object','additionalProperties'=>true),'order_id'=>array('type'=>'integer'),'params'=>array('type'=>'object','additionalProperties'=>true)),'required'=>array('action')); }
}
