<?php
namespace WPGPT\MCPBridge;
use WPGPT\MCPBridge\Diagnostics\Diagnostic_Registry;
use WP_Error;
if ( ! defined( 'ABSPATH' ) ) { exit; }
class Runtime_Provider extends Base_Ability_Provider {
    public function get_abilities(): array {
        return array(
            'wpgpt/runtime-query' => array(
                'label' => __( 'Runtime query', 'wpgpt-mcp-bridge' ),
                'description' => __( 'Lista rutas REST, hooks, shortcodes y eventos cron del runtime.', 'wpgpt-mcp-bridge' ),
                'input_schema' => array('type'=>'object','properties'=>array('scope'=>array('type'=>'string','enum'=>array('all','routes','hooks','shortcodes','cron')),'namespace_like'=>array('type'=>'string'),'hook_like'=>array('type'=>'string'),'limit'=>array('type'=>'integer'))),
                'execute_callback' => array( $this, 'runtime_query' ),
                'output_schema' => $this->object_schema(),
            ),
            'wpgpt/runtime-inspect' => array(
                'label' => __( 'Runtime inspect', 'wpgpt-mcp-bridge' ),
                'description' => __( 'Inspecciona un subconjunto concreto del runtime.', 'wpgpt-mcp-bridge' ),
                'input_schema' => array('type'=>'object','properties'=>array('scope'=>array('type'=>'string','enum'=>array('routes','hooks','shortcodes','cron')),'namespace_like'=>array('type'=>'string'),'hook_like'=>array('type'=>'string'),'limit'=>array('type'=>'integer')),'required'=>array('scope')),
                'execute_callback' => array( $this, 'runtime_inspect' ),
                'output_schema' => $this->object_schema(),
            ),
            'wpgpt/runtime-apply' => array(
                'label' => __( 'Runtime apply', 'wpgpt-mcp-bridge' ),
                'description' => __( 'Ejecuta una inspección runtime controlada, con soporte dry_run.', 'wpgpt-mcp-bridge' ),
                'input_schema' => array('type'=>'object','properties'=>array('action'=>array('type'=>'string','enum'=>array('snapshot')),'scope'=>array('type'=>'string','enum'=>array('routes','hooks','shortcodes','cron')),'dry_run'=>array('type'=>'boolean'),'namespace_like'=>array('type'=>'string'),'hook_like'=>array('type'=>'string'),'limit'=>array('type'=>'integer')),'required'=>array('action','scope')),
                'execute_callback' => array( $this, 'runtime_apply' ),
                'output_schema' => $this->object_schema(),
            ),
        );
    }
    private function execute_scope( string $scope, array $input ): array|WP_Error {
        return match ($scope) {
            'routes' => Diagnostic_Registry::execute( 'list_rest_routes', $input ),
            'hooks' => Diagnostic_Registry::execute( 'list_hooks', $input ),
            'shortcodes' => Diagnostic_Registry::execute( 'list_shortcodes', $input ),
            'cron' => Diagnostic_Registry::execute( 'list_cron_events', $input ),
            default => new WP_Error('wpgpt_invalid_scope', __( 'Scope no soportado.', 'wpgpt-mcp-bridge' ), array('status'=>400)),
        };
    }
    public function runtime_query( array $input ): array|WP_Error {
        $scope = sanitize_key((string)($input['scope'] ?? 'all'));
        $scopes = 'all' === $scope ? array('routes','hooks','shortcodes','cron') : array($scope);
        $items=[];
        foreach($scopes as $s){ $items[$s] = $this->execute_scope($s,$input); }
        return array('summary'=>array('scope'=>$scope,'returned'=>count($items)),'items'=>$items,'warnings'=>array(),'next_actions'=>array());
    }
    public function runtime_inspect( array $input ): array|WP_Error {
        $scope = sanitize_key((string)($input['scope'] ?? ''));
        $result = $this->execute_scope($scope,$input);
        if ( is_wp_error($result) ) { return $result; }
        return array('summary'=>array('scope'=>$scope,'inspected'=>1),'items'=>array($result),'warnings'=>array(),'next_actions'=>array());
    }
    public function runtime_apply( array $input ): array|WP_Error {
        $dry = ! empty($input['dry_run']);
        $scope = sanitize_key((string)($input['scope'] ?? ''));
        $result = $this->execute_scope($scope,$input);
        if ( is_wp_error($result) ) { return $result; }
        return array('summary'=>array('action'=>'snapshot','scope'=>$scope,'dry_run'=>$dry,'executed'=>$dry?0:1,'blocked'=>0),'items'=>array($result),'warnings'=>array(),'blocked'=>array(),'next_actions'=>array());
    }
}
