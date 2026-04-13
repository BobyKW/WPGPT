<?php

namespace WPGPT\MCPBridge;

use WPGPT\MCPBridge\Structure\Metabox_Manager;
use WPGPT\MCPBridge\Structure\Post_Type_Manager;
use WPGPT\MCPBridge\Structure\Taxonomy_Manager;
use WP_Error;

if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

class Structure_Provider extends Base_Ability_Provider {
    public function get_abilities(): array {
        return array(
            'wpgpt/structure-query' => array(
                'label' => __( 'Structure query', 'wpgpt-mcp-bridge' ),
                'description' => __( 'Lista y resume tipos de contenido, taxonomías y metaboxes.', 'wpgpt-mcp-bridge' ),
                'input_schema' => array('type'=>'object','additionalProperties'=>false,'properties'=>array('scope'=>array('type'=>'string','enum'=>array('all','cpt','taxonomy','metabox')),'managed_only'=>array('type'=>'boolean'))),
                'execute_callback' => array( $this, 'structure_query' ),
                'output_schema' => $this->object_schema(),
            ),
            'wpgpt/structure-inspect' => array(
                'label' => __( 'Structure inspect', 'wpgpt-mcp-bridge' ),
                'description' => __( 'Inspecciona un CPT, taxonomía o metabox concreto.', 'wpgpt-mcp-bridge' ),
                'input_schema' => array('type'=>'object','additionalProperties'=>false,'properties'=>array('scope'=>array('type'=>'string','enum'=>array('cpt','taxonomy','metabox')),'slug'=>array('type'=>'string'),'key'=>array('type'=>'string')),'required'=>array('scope')),
                'execute_callback' => array( $this, 'structure_inspect' ),
                'output_schema' => $this->object_schema(),
            ),
            'wpgpt/structure-apply' => array(
                'label' => __( 'Structure apply', 'wpgpt-mcp-bridge' ),
                'description' => __( 'Ejecuta acciones controladas sobre CPTs, taxonomías y metaboxes, con soporte dry_run.', 'wpgpt-mcp-bridge' ),
                'input_schema' => array('type'=>'object','additionalProperties'=>false,'properties'=>array('scope'=>array('type'=>'string','enum'=>array('cpt','taxonomy','metabox')),'action'=>array('type'=>'string','enum'=>array('create','delete')),'dry_run'=>array('type'=>'boolean'),'payload'=>array('type'=>'object','additionalProperties'=>true)),'required'=>array('scope','action')),
                'execute_callback' => array( $this, 'structure_apply' ),
                'output_schema' => $this->object_schema(),
                'permission_callback' => array( $this, 'can_write_structure' ),
            ),
        );
    }

    public function structure_query( array $input ): array {
        $scope = isset($input['scope']) ? sanitize_key((string)$input['scope']) : 'all';
        $managed_only = ! empty($input['managed_only']);
        $items = array();
        if ( 'all' === $scope || 'cpt' === $scope ) {
            $list = Post_Type_Manager::list();
            $entries = isset($list['managed']) && $managed_only ? (array)$list['managed'] : array_merge((array)($list['managed'] ?? array()), (array)($list['registered'] ?? array()));
            $items[] = array('scope'=>'cpt','items'=>array_values($entries));
        }
        if ( 'all' === $scope || 'taxonomy' === $scope ) {
            $list = Taxonomy_Manager::list();
            $entries = isset($list['managed']) && $managed_only ? (array)$list['managed'] : array_merge((array)($list['managed'] ?? array()), (array)($list['registered'] ?? array()));
            $items[] = array('scope'=>'taxonomy','items'=>array_values($entries));
        }
        if ( 'all' === $scope || 'metabox' === $scope ) {
            $list = Metabox_Manager::list();
            $items[] = array('scope'=>'metabox','items'=>array_values((array)($list['items'] ?? $list)));
        }
        return array('summary'=>array('scope'=>$scope,'returned'=>count($items)),'items'=>$items,'warnings'=>array(),'next_actions'=>array());
    }

    public function structure_inspect( array $input ): array|WP_Error {
        $scope = isset($input['scope']) ? sanitize_key((string)$input['scope']) : '';
        $slug = isset($input['slug']) ? sanitize_key((string)$input['slug']) : '';
        $key = isset($input['key']) ? sanitize_key((string)$input['key']) : '';
        if ( 'cpt' === $scope ) {
            $list = Post_Type_Manager::list();
            foreach (array_merge((array)($list['managed'] ?? array()), (array)($list['registered'] ?? array())) as $item) { if (($item['slug'] ?? '') === $slug) return array('summary'=>array('scope'=>'cpt','inspected'=>1),'items'=>array(array_merge($item,array('available_actions'=>array('delete'),'risk_level'=>'medium'))),'warnings'=>array(),'next_actions'=>array(__( 'Usa wpgpt/structure-apply con dry_run=true antes de ejecutar cambios.', 'wpgpt-mcp-bridge' ))); }
            return new WP_Error('wpgpt_structure_not_found',__('No se ha encontrado el CPT indicado.','wpgpt-mcp-bridge'),array('status'=>404));
        }
        if ( 'taxonomy' === $scope ) {
            $list = Taxonomy_Manager::list();
            foreach (array_merge((array)($list['managed'] ?? array()), (array)($list['registered'] ?? array())) as $item) { if (($item['slug'] ?? '') === $slug) return array('summary'=>array('scope'=>'taxonomy','inspected'=>1),'items'=>array(array_merge($item,array('available_actions'=>array('delete'),'risk_level'=>'medium'))),'warnings'=>array(),'next_actions'=>array(__( 'Usa wpgpt/structure-apply con dry_run=true antes de ejecutar cambios.', 'wpgpt-mcp-bridge' ))); }
            return new WP_Error('wpgpt_structure_not_found',__('No se ha encontrado la taxonomía indicada.','wpgpt-mcp-bridge'),array('status'=>404));
        }
        if ( 'metabox' === $scope ) {
            $list = Metabox_Manager::list();
            foreach ((array)($list['items'] ?? $list) as $item) { $item_key = sanitize_key((string)($item['key'] ?? ($item['id'] ?? ''))); if ($item_key === $key) return array('summary'=>array('scope'=>'metabox','inspected'=>1),'items'=>array(array_merge($item,array('available_actions'=>array('delete'),'risk_level'=>'medium'))),'warnings'=>array(),'next_actions'=>array(__( 'Usa wpgpt/structure-apply con dry_run=true antes de ejecutar cambios.', 'wpgpt-mcp-bridge' ))); }
            return new WP_Error('wpgpt_structure_not_found',__('No se ha encontrado el metabox indicado.','wpgpt-mcp-bridge'),array('status'=>404));
        }
        return new WP_Error('wpgpt_structure_scope_invalid',__('Debes indicar un scope válido.','wpgpt-mcp-bridge'),array('status'=>400));
    }

    public function structure_apply( array $input ): array|WP_Error {
        $scope = isset($input['scope']) ? sanitize_key((string)$input['scope']) : '';
        $action = isset($input['action']) ? sanitize_key((string)$input['action']) : '';
        $dry_run = ! empty($input['dry_run']);
        $payload = isset($input['payload']) && is_array($input['payload']) ? $input['payload'] : array();
        if ( ! in_array($scope,array('cpt','taxonomy','metabox'),true) || ! in_array($action,array('create','delete'),true) ) {
            return new WP_Error('wpgpt_structure_action_invalid',__('La combinación scope/action no es válida.','wpgpt-mcp-bridge'),array('status'=>400));
        }
        if ($dry_run) {
            return array('summary'=>array('scope'=>$scope,'action'=>$action,'dry_run'=>true,'executed'=>0,'blocked'=>0),'items'=>array(array('status'=>'dry_run','scope'=>$scope,'action'=>$action)),'warnings'=>array(),'blocked'=>array(),'next_actions'=>array(__( 'Repite la misma llamada con dry_run=false para aplicar los cambios validados.', 'wpgpt-mcp-bridge' )));
        }
        if ('cpt' === $scope) { $result = 'create' === $action ? Post_Type_Manager::create($payload) : Post_Type_Manager::delete($payload); }
        elseif ('taxonomy' === $scope) { $result = 'create' === $action ? Taxonomy_Manager::create($payload) : Taxonomy_Manager::delete($payload); }
        else { $result = 'create' === $action ? Metabox_Manager::create($payload) : Metabox_Manager::delete($payload); }
        if (is_wp_error($result)) { return $result; }
        return array('summary'=>array('scope'=>$scope,'action'=>$action,'dry_run'=>false,'executed'=>1,'blocked'=>0),'items'=>array(array('status'=>'applied','scope'=>$scope,'action'=>$action,'result'=>$result)),'warnings'=>array(),'blocked'=>array(),'next_actions'=>array());
    }
}
