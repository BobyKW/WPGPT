<?php
namespace WPGPT\MCPBridge;
use WPGPT\MCPBridge\Database\Database_Inspector_Service;
use WPGPT\MCPBridge\Database\Database_Audit_Service;
use WP_Error;
if ( ! defined( 'ABSPATH' ) ) { exit; }
class Database_Provider extends Base_Ability_Provider {
    private ?Database_Inspector_Service $service = null;
    private ?Database_Audit_Service $audit_service = null;

    private function service(): Database_Inspector_Service { return $this->service ??= new Database_Inspector_Service(); }
    private function audit_service(): Database_Audit_Service { return $this->audit_service ??= new Database_Audit_Service(); }

    public function get_abilities(): array {
        return array(
            'wpgpt/database-query' => array(
                'label' => __( 'Database query', 'wpgpt-mcp-bridge' ),
                'description' => __( 'Lista tablas seguras y resume auditorías básicas de base de datos.', 'wpgpt-mcp-bridge' ),
                'input_schema' => array('type'=>'object','properties'=>array('include_audits'=>array('type'=>'boolean'),'limit'=>array('type'=>'integer'),'taxonomy'=>array('type'=>'string'))),
                'execute_callback' => array($this,'database_query'),'output_schema'=>$this->object_schema(),
            ),
            'wpgpt/database-inspect' => array(
                'label' => __( 'Database inspect', 'wpgpt-mcp-bridge' ),
                'description' => __( 'Inspecciona una tabla segura o ejecuta una consulta segura controlada.', 'wpgpt-mcp-bridge' ),
                'input_schema' => array('type'=>'object','properties'=>array('action'=>array('type'=>'string','enum'=>array('describe','select','count','search','distinct','audit_orphan_postmeta','audit_orphan_usermeta','audit_orphan_term_relationships','audit_unused_terms')),'table'=>array('type'=>'string'),'columns'=>array('type'=>'array','items'=>array('type'=>'string')),'where'=>array('type'=>'object','additionalProperties'=>true),'limit'=>array('type'=>'integer'),'column'=>array('type'=>'string'),'term'=>array('type'=>'string'),'taxonomy'=>array('type'=>'string')),'required'=>array('action')),
                'execute_callback' => array($this,'database_inspect'),'output_schema'=>$this->object_schema(),
            ),
            'wpgpt/database-apply' => array(
                'label' => __( 'Database apply', 'wpgpt-mcp-bridge' ),
                'description' => __( 'Ejecuta auditorías seguras de base de datos, con soporte dry_run.', 'wpgpt-mcp-bridge' ),
                'input_schema' => array('type'=>'object','properties'=>array('action'=>array('type'=>'string','enum'=>array('audit_orphan_postmeta','audit_orphan_usermeta','audit_orphan_term_relationships','audit_unused_terms')),'dry_run'=>array('type'=>'boolean'),'limit'=>array('type'=>'integer'),'taxonomy'=>array('type'=>'string')),'required'=>array('action')),
                'execute_callback' => array($this,'database_apply'),'output_schema'=>$this->object_schema(),
            ),
        );
    }

    public function database_query(array $input): array {
        $items = array('tables'=>$this->service()->list_tables());
        if ( ! empty($input['include_audits']) ) {
            $items['audits'] = array(
                'orphan_postmeta'=>$this->audit_service()->orphan_postmeta($input),
                'orphan_usermeta'=>$this->audit_service()->orphan_usermeta($input),
                'orphan_term_relationships'=>$this->audit_service()->orphan_term_relationships($input),
                'unused_terms'=>$this->audit_service()->unused_terms($input),
            );
        }
        return array('summary'=>array('returned'=>count($items)),'items'=>$items,'warnings'=>array(),'next_actions'=>array());
    }

    private function run_action(string $action, array $input): array|WP_Error {
        return match($action) {
            'describe' => $this->service()->describe_table( sanitize_key((string)($input['table'] ?? '')) ),
            'select' => $this->service()->select( sanitize_key((string)($input['table'] ?? '')), is_array($input['columns'] ?? null)?$input['columns']:array(), is_array($input['where'] ?? null)?$input['where']:array(), (int)($input['limit'] ?? 10) ),
            'count' => $this->service()->count( sanitize_key((string)($input['table'] ?? '')), is_array($input['where'] ?? null)?$input['where']:array() ),
            'search' => $this->service()->search( sanitize_key((string)($input['table'] ?? '')), sanitize_key((string)($input['column'] ?? '')), sanitize_text_field((string)($input['term'] ?? '')), is_array($input['columns'] ?? null)?$input['columns']:array(), (int)($input['limit'] ?? 20) ),
            'distinct' => $this->service()->distinct( sanitize_key((string)($input['table'] ?? '')), sanitize_key((string)($input['column'] ?? '')), is_array($input['where'] ?? null)?$input['where']:array(), (int)($input['limit'] ?? 50) ),
            'audit_orphan_postmeta' => $this->audit_service()->orphan_postmeta($input),
            'audit_orphan_usermeta' => $this->audit_service()->orphan_usermeta($input),
            'audit_orphan_term_relationships' => $this->audit_service()->orphan_term_relationships($input),
            'audit_unused_terms' => $this->audit_service()->unused_terms($input),
            default => new WP_Error('wpgpt_invalid_action', __( 'Acción no soportada.', 'wpgpt-mcp-bridge' ), array('status'=>400)),
        };
    }

    public function database_inspect(array $input): array|WP_Error {
        $action = sanitize_key((string)($input['action'] ?? ''));
        $result = $this->run_action($action,$input);
        if ( is_wp_error($result) ) { return $result; }
        return array('summary'=>array('action'=>$action,'inspected'=>1),'items'=>array($result),'warnings'=>array(),'next_actions'=>array());
    }

    public function database_apply(array $input): array|WP_Error {
        $action = sanitize_key((string)($input['action'] ?? ''));
        $dry = ! empty($input['dry_run']);
        $result = $this->run_action($action,$input);
        if ( is_wp_error($result) ) { return $result; }
        return array('summary'=>array('action'=>$action,'dry_run'=>$dry,'executed'=>$dry?0:1,'blocked'=>0),'items'=>array($result),'warnings'=>array(),'blocked'=>array(),'next_actions'=>array());
    }
}
