<?php
namespace WPGPT\MCPBridge;
use WPGPT\MCPBridge\Diagnostics\Diagnostic_Registry;
use WP_Error;
if ( ! defined( 'ABSPATH' ) ) { exit; }
class Diagnostics_Provider extends Base_Ability_Provider {
    public function get_abilities(): array {
        return array(
            'wpgpt/diagnostics-query' => array(
                'label' => __( 'Diagnostics query', 'wpgpt-mcp-bridge' ),
                'description' => __( 'Lista operaciones de diagnóstico disponibles.', 'wpgpt-mcp-bridge' ),
                'execute_callback' => array( $this, 'diagnostics_query' ),
                'output_schema' => $this->object_schema(),
            ),
            'wpgpt/diagnostics-inspect' => array(
                'label' => __( 'Diagnostics inspect', 'wpgpt-mcp-bridge' ),
                'description' => __( 'Inspecciona una o varias operaciones de diagnóstico por nombre.', 'wpgpt-mcp-bridge' ),
                'input_schema' => array('type'=>'object','properties'=>array('operation'=>array('type'=>'string'),'operations'=>array('type'=>'array','items'=>array('type'=>'string')))),
                'execute_callback' => array( $this, 'diagnostics_inspect' ),
                'output_schema' => $this->object_schema(),
            ),
            'wpgpt/diagnostics-apply' => array(
                'label' => __( 'Diagnostics apply', 'wpgpt-mcp-bridge' ),
                'description' => __( 'Ejecuta una operación de diagnóstico de forma controlada.', 'wpgpt-mcp-bridge' ),
                'input_schema' => array('type'=>'object','properties'=>array('action'=>array('type'=>'string','enum'=>array('run')),'operation'=>array('type'=>'string'),'dry_run'=>array('type'=>'boolean'),'namespace_like'=>array('type'=>'string'),'hook_like'=>array('type'=>'string'),'limit'=>array('type'=>'integer')),'required'=>array('action','operation')),
                'execute_callback' => array( $this, 'diagnostics_apply' ),
                'output_schema' => $this->object_schema(),
            ),
        );
    }
    public function diagnostics_query(): array {
        $items = Diagnostic_Registry::info();
        return array('summary'=>array('count'=>count($items)),'items'=>$items,'warnings'=>array(),'next_actions'=>array());
    }
    public function diagnostics_inspect( array $input ): array {
        $ops = array();
        if ( ! empty( $input['operation'] ) ) { $ops[] = sanitize_key((string)$input['operation']); }
        if ( ! empty( $input['operations'] ) && is_array( $input['operations'] ) ) { foreach ( $input['operations'] as $op ) { $ops[] = sanitize_key((string)$op); } }
        $all = Diagnostic_Registry::info();
        $items = array_values(array_filter($all, fn($i)=> empty($ops) || in_array($i['name'],$ops,true)));
        return array('summary'=>array('requested'=>count($ops),'inspected'=>count($items)),'items'=>$items,'warnings'=>array(),'next_actions'=>array());
    }
    public function diagnostics_apply( array $input ): array|WP_Error {
        $dry = ! empty( $input['dry_run'] );
        $operation = sanitize_key((string)($input['operation'] ?? ''));
        $payload = $input; unset($payload['action'],$payload['dry_run'],$payload['operation']);
        $result = Diagnostic_Registry::execute( $operation, $payload );
        if ( is_wp_error( $result ) ) { return $result; }
        return array('summary'=>array('action'=>'run','operation'=>$operation,'dry_run'=>$dry,'executed'=>$dry ? 0 : 1,'blocked'=>0),'items'=>array($result),'warnings'=>array(),'blocked'=>array(),'next_actions'=>array());
    }
}
