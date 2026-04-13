<?php

namespace WPGPT\MCPBridge;

use WPGPT\MCPBridge\Users\User_Manager_Service;
use WP_Error;

if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

class User_Provider extends Base_Ability_Provider {
    private ?User_Manager_Service $service = null;

    public function get_abilities(): array {
        return array(
            'wpgpt/users-query' => array('label'=>__('Users query','wpgpt-mcp-bridge'),'description'=>__('Lista, filtra y resume usuarios del sitio.','wpgpt-mcp-bridge'),'input_schema'=>$this->users_query_schema(),'execute_callback'=>array($this,'users_query'),'output_schema'=>$this->object_schema()),
            'wpgpt/users-inspect' => array('label'=>__('Users inspect','wpgpt-mcp-bridge'),'description'=>__('Inspecciona uno o varios usuarios por user_id, login o email.','wpgpt-mcp-bridge'),'input_schema'=>$this->users_inspect_schema(),'execute_callback'=>array($this,'users_inspect'),'output_schema'=>$this->object_schema()),
            'wpgpt/users-apply' => array('label'=>__('Users apply','wpgpt-mcp-bridge'),'description'=>__('Ejecuta acciones controladas sobre usuarios, con soporte dry_run.','wpgpt-mcp-bridge'),'input_schema'=>$this->users_apply_schema(),'execute_callback'=>array($this,'users_apply'),'output_schema'=>$this->object_schema(),'permission_callback'=>array($this,'can_manage_site')),
            'wpgpt/roles-query' => array('label'=>__('Roles query','wpgpt-mcp-bridge'),'description'=>__('Lista y resume roles y capacidades del sitio.','wpgpt-mcp-bridge'),'input_schema'=>array('type'=>'object','properties'=>array('search'=>array('type'=>'string'))),'execute_callback'=>array($this,'roles_query'),'output_schema'=>$this->object_schema()),
            'wpgpt/roles-inspect' => array('label'=>__('Roles inspect','wpgpt-mcp-bridge'),'description'=>__('Inspecciona un rol concreto y sus capacidades.','wpgpt-mcp-bridge'),'input_schema'=>array('type'=>'object','properties'=>array('role'=>array('type'=>'string'))),'execute_callback'=>array($this,'roles_inspect'),'output_schema'=>$this->object_schema()),
            'wpgpt/roles-apply' => array('label'=>__('Roles apply','wpgpt-mcp-bridge'),'description'=>__('Ejecuta cambios controlados sobre roles y capacidades, con soporte dry_run.','wpgpt-mcp-bridge'),'input_schema'=>array('type'=>'object','properties'=>array('action'=>array('type'=>'string','enum'=>array('create','delete','grant_capability','revoke_capability')),'dry_run'=>array('type'=>'boolean'),'payload'=>array('type'=>'object','additionalProperties'=>true)),'required'=>array('action')),'execute_callback'=>array($this,'roles_apply'),'output_schema'=>$this->object_schema(),'permission_callback'=>array($this,'can_manage_site')),
        );
    }
    public function users_query( array $input = array() ): array|WP_Error { return $this->service()->query( $input ); }
    public function users_inspect( array $input = array() ): array|WP_Error { return $this->service()->inspect( $input ); }
    public function users_apply( array $input = array() ): array|WP_Error { return $this->service()->apply( $input ); }
    public function roles_query( array $input = array() ): array|WP_Error { return $this->service()->query_roles( $input ); }
    public function roles_inspect( array $input = array() ): array|WP_Error { return $this->service()->inspect_roles( $input ); }
    public function roles_apply( array $input = array() ): array|WP_Error { return $this->service()->apply_roles( $input ); }
    private function service(): User_Manager_Service { return $this->service ??= new User_Manager_Service(); }
    private function users_query_schema(): array { return array( 'type' => 'object', 'additionalProperties' => false, 'properties' => array( 'search' => array( 'type' => 'string' ), 'filters' => array( 'type' => 'object', 'additionalProperties' => false, 'properties' => array( 'user_id' => array( 'type' => 'integer' ), 'role' => array( 'type' => 'string' ), 'email' => array( 'type' => 'string' ), 'login' => array( 'type' => 'string' ) ) ), 'limit' => array( 'type' => 'integer', 'minimum' => 1, 'maximum' => 200 ), 'offset' => array( 'type' => 'integer', 'minimum' => 0 ) ) ); }
    private function users_inspect_schema(): array { return array( 'type' => 'object', 'additionalProperties' => false, 'properties' => array( 'user_id' => array( 'type' => 'integer' ), 'user_ids' => array( 'type' => 'array', 'items' => array( 'type' => 'integer' ) ), 'login' => array( 'type' => 'string' ), 'email' => array( 'type' => 'string' ) ) ); }
    private function users_apply_schema(): array { return array( 'type' => 'object', 'additionalProperties' => false, 'properties' => array( 'action' => array( 'type' => 'string', 'enum' => array( 'create', 'update', 'delete' ) ), 'dry_run' => array( 'type' => 'boolean' ), 'targets' => array( 'type' => 'array', 'items' => array( 'type' => 'object', 'additionalProperties' => false, 'properties' => array( 'user_id' => array( 'type' => 'integer' ), 'login' => array( 'type' => 'string' ), 'email' => array( 'type' => 'string' ), 'reassign' => array( 'type' => 'integer' ) ) ) ), 'filters' => array( 'type' => 'object', 'additionalProperties' => false, 'properties' => array( 'role' => array( 'type' => 'string' ), 'email' => array( 'type' => 'string' ), 'login' => array( 'type' => 'string' ), 'search' => array( 'type' => 'string' ) ) ), 'payload' => array( 'type' => 'object', 'additionalProperties' => true, 'properties' => array( 'user_login' => array( 'type' => 'string' ), 'user_email' => array( 'type' => 'string' ), 'display_name' => array( 'type' => 'string' ), 'role' => array( 'type' => 'string' ), 'user_pass' => array( 'type' => 'string' ), 'reassign' => array( 'type' => 'integer' ) ) ) ), 'required' => array( 'action' ) ); }
}
