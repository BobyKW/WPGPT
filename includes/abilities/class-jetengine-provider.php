<?php
namespace WPGPT\MCPBridge;
use WPGPT\MCPBridge\Integrations\JetEngine_Service;
if ( ! defined( 'ABSPATH' ) ) { exit; }
class JetEngine_Provider extends Base_Ability_Provider {
    private ?JetEngine_Service $service = null;
    private function service(): JetEngine_Service { return $this->service ??= new JetEngine_Service(); }
    public function get_abilities(): array {
        return array(
            'wpgpt/jetengine-query' => array(
                'label' => __( 'JetEngine query', 'wpgpt-mcp-bridge' ),
                'description' => __( 'Resume el estado de JetEngine y, opcionalmente, sus opciones detectables.', 'wpgpt-mcp-bridge' ),
                'input_schema' => array('type'=>'object','properties'=>array('include_options'=>array('type'=>'boolean'),'search'=>array('type'=>'string'),'limit'=>array('type'=>'integer'))),
                'execute_callback' => array($this,'jetengine_query'),'output_schema'=>$this->object_schema(),
            ),
            'wpgpt/jetengine-inspect' => array(
                'label' => __( 'JetEngine inspect', 'wpgpt-mcp-bridge' ),
                'description' => __( 'Inspecciona el estado de JetEngine o un escaneo concreto de opciones.', 'wpgpt-mcp-bridge' ),
                'input_schema' => array('type'=>'object','properties'=>array('scope'=>array('type'=>'string','enum'=>array('status','options')),'search'=>array('type'=>'string'),'limit'=>array('type'=>'integer')),'required'=>array('scope')),
                'execute_callback' => array($this,'jetengine_inspect'),'output_schema'=>$this->object_schema(),
            ),
            'wpgpt/jetengine-apply' => array(
                'label' => __( 'JetEngine apply', 'wpgpt-mcp-bridge' ),
                'description' => __( 'Ejecuta una comprobación controlada de JetEngine, con soporte dry_run.', 'wpgpt-mcp-bridge' ),
                'input_schema' => array('type'=>'object','properties'=>array('action'=>array('type'=>'string','enum'=>array('scan_options')),'search'=>array('type'=>'string'),'limit'=>array('type'=>'integer'),'dry_run'=>array('type'=>'boolean')),'required'=>array('action')),
                'execute_callback' => array($this,'jetengine_apply'),'output_schema'=>$this->object_schema(),
            ),
        );
    }
    public function jetengine_query(array $input): array {
        $items = array('status'=>$this->service()->status());
        if ( ! empty($input['include_options']) ) { $items['options'] = $this->service()->options_scan( sanitize_key((string)($input['search'] ?? 'jet_engine')), (int)($input['limit'] ?? 50) ); }
        return array('summary'=>array('returned'=>count($items)),'items'=>$items,'warnings'=>array(),'next_actions'=>array());
    }
    public function jetengine_inspect(array $input): array {
        $scope = sanitize_key((string)($input['scope'] ?? 'status'));
        $item = 'options' === $scope ? $this->service()->options_scan( sanitize_key((string)($input['search'] ?? 'jet_engine')), (int)($input['limit'] ?? 50) ) : $this->service()->status();
        return array('summary'=>array('scope'=>$scope,'inspected'=>1),'items'=>array($item),'warnings'=>array(),'next_actions'=>array());
    }
    public function jetengine_apply(array $input): array {
        $dry = ! empty($input['dry_run']);
        $item = $this->service()->options_scan( sanitize_key((string)($input['search'] ?? 'jet_engine')), (int)($input['limit'] ?? 50) );
        return array('summary'=>array('action'=>'scan_options','dry_run'=>$dry,'executed'=>$dry?0:1,'blocked'=>0),'items'=>array($item),'warnings'=>array(),'blocked'=>array(),'next_actions'=>array());
    }
}
