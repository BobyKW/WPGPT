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
            'wpgpt/user-list' => array(
                'label' => __( 'User list', 'wpgpt-mcp-bridge' ),
                'description' => __( 'Lista usuarios del sitio.', 'wpgpt-mcp-bridge' ),
                'input_schema' => $this->user_list_schema(),
                'execute_callback' => array( $this, 'user_list' ),
                'output_schema' => $this->object_schema(),
            ),
            'wpgpt/user-get' => array(
                'label' => __( 'User get', 'wpgpt-mcp-bridge' ),
                'description' => __( 'Obtiene un usuario concreto.', 'wpgpt-mcp-bridge' ),
                'input_schema' => $this->user_id_schema(),
                'execute_callback' => array( $this, 'user_get' ),
                'output_schema' => $this->object_schema(),
            ),
            'wpgpt/user-create' => array(
                'label' => __( 'User create', 'wpgpt-mcp-bridge' ),
                'description' => __( 'Crea un usuario nuevo.', 'wpgpt-mcp-bridge' ),
                'input_schema' => $this->user_create_schema(),
                'execute_callback' => array( $this, 'user_create' ),
                'output_schema' => $this->object_schema(),
                'permission_callback' => array( $this, 'can_manage_site' ),
            ),
            'wpgpt/user-update' => array(
                'label' => __( 'User update', 'wpgpt-mcp-bridge' ),
                'description' => __( 'Actualiza un usuario existente.', 'wpgpt-mcp-bridge' ),
                'input_schema' => $this->user_update_schema(),
                'execute_callback' => array( $this, 'user_update' ),
                'output_schema' => $this->object_schema(),
                'permission_callback' => array( $this, 'can_manage_site' ),
            ),
            'wpgpt/user-delete' => array(
                'label' => __( 'User delete', 'wpgpt-mcp-bridge' ),
                'description' => __( 'Elimina un usuario del sitio.', 'wpgpt-mcp-bridge' ),
                'input_schema' => $this->user_delete_schema(),
                'execute_callback' => array( $this, 'user_delete' ),
                'output_schema' => $this->object_schema(),
                'permission_callback' => array( $this, 'can_delete_structure' ),
            ),
            'wpgpt/role-create' => array(
                'label' => __( 'Role create', 'wpgpt-mcp-bridge' ),
                'description' => __( 'Crea un rol nuevo.', 'wpgpt-mcp-bridge' ),
                'input_schema' => array( 'type' => 'object', 'properties' => array( 'role' => array( 'type' => 'string' ), 'label' => array( 'type' => 'string' ), 'capabilities' => array( 'type' => 'array', 'items' => array( 'type' => 'string' ) ) ), 'required' => array( 'role', 'label' ) ),
                'execute_callback' => array( $this, 'role_create' ),
                'output_schema' => $this->object_schema(),
                'permission_callback' => array( $this, 'can_manage_site' ),
            ),
            'wpgpt/role-delete' => array(
                'label' => __( 'Role delete', 'wpgpt-mcp-bridge' ),
                'description' => __( 'Elimina un rol custom.', 'wpgpt-mcp-bridge' ),
                'input_schema' => array( 'type' => 'object', 'properties' => array( 'role' => array( 'type' => 'string' ) ), 'required' => array( 'role' ) ),
                'execute_callback' => array( $this, 'role_delete' ),
                'output_schema' => $this->object_schema(),
                'permission_callback' => array( $this, 'can_delete_structure' ),
            ),
            'wpgpt/capability-grant' => array(
                'label' => __( 'Capability grant', 'wpgpt-mcp-bridge' ),
                'description' => __( 'Concede una capacidad a un rol.', 'wpgpt-mcp-bridge' ),
                'input_schema' => array( 'type' => 'object', 'properties' => array( 'role' => array( 'type' => 'string' ), 'capability' => array( 'type' => 'string' ) ), 'required' => array( 'role', 'capability' ) ),
                'execute_callback' => array( $this, 'capability_grant' ),
                'output_schema' => $this->object_schema(),
                'permission_callback' => array( $this, 'can_manage_site' ),
            ),
            'wpgpt/capability-revoke' => array(
                'label' => __( 'Capability revoke', 'wpgpt-mcp-bridge' ),
                'description' => __( 'Revoca una capacidad de un rol.', 'wpgpt-mcp-bridge' ),
                'input_schema' => array( 'type' => 'object', 'properties' => array( 'role' => array( 'type' => 'string' ), 'capability' => array( 'type' => 'string' ) ), 'required' => array( 'role', 'capability' ) ),
                'execute_callback' => array( $this, 'capability_revoke' ),
                'output_schema' => $this->object_schema(),
                'permission_callback' => array( $this, 'can_manage_site' ),
            ),
            'wpgpt/role-list' => array(
                'label' => __( 'Role list', 'wpgpt-mcp-bridge' ),
                'description' => __( 'Lista roles y capacidades disponibles.', 'wpgpt-mcp-bridge' ),
                'execute_callback' => array( $this, 'role_list' ),
                'output_schema' => $this->object_schema(),
            ),
        );
    }

    public function user_list( array $input ): array { return $this->service()->list_users( $input ); }
    public function user_get( array $input ): array|WP_Error { return $this->service()->get_user_data( $input ); }
    public function user_create( array $input ): array|WP_Error { return $this->service()->create_user_data( $input ); }
    public function user_update( array $input ): array|WP_Error { return $this->service()->update_user_data( $input ); }
    public function user_delete( array $input ): array|WP_Error { return $this->service()->delete_user_data( $input ); }
    public function role_create( array $input ): array|WP_Error { return $this->service()->create_role_data( $input ); }
    public function role_delete( array $input ): array|WP_Error { return $this->service()->delete_role_data( $input ); }
    public function capability_grant( array $input ): array|WP_Error { return $this->service()->grant_capability( $input ); }
    public function capability_revoke( array $input ): array|WP_Error { return $this->service()->revoke_capability( $input ); }
    public function role_list(): array { return $this->service()->list_roles(); }

    private function service(): User_Manager_Service {
        if ( null === $this->service ) {
            $this->service = new User_Manager_Service();
        }
        return $this->service;
    }

    private function user_list_schema(): array { return array( 'type' => 'object', 'properties' => array( 'search' => array( 'type' => 'string' ), 'limit' => array( 'type' => 'integer' ) ) ); }
    private function user_id_schema(): array { return array( 'type' => 'object', 'properties' => array( 'user_id' => array( 'type' => 'integer' ) ), 'required' => array( 'user_id' ) ); }
    private function user_create_schema(): array { return array( 'type' => 'object', 'properties' => array( 'user_login' => array( 'type' => 'string' ), 'user_email' => array( 'type' => 'string' ), 'display_name' => array( 'type' => 'string' ), 'role' => array( 'type' => 'string' ), 'user_pass' => array( 'type' => 'string' ) ), 'required' => array( 'user_login', 'user_email' ) ); }
    private function user_update_schema(): array { return array( 'type' => 'object', 'properties' => array( 'user_id' => array( 'type' => 'integer' ), 'user_email' => array( 'type' => 'string' ), 'display_name' => array( 'type' => 'string' ), 'role' => array( 'type' => 'string' ), 'user_pass' => array( 'type' => 'string' ) ), 'required' => array( 'user_id' ) ); }
    private function user_delete_schema(): array { return array( 'type' => 'object', 'properties' => array( 'user_id' => array( 'type' => 'integer' ), 'reassign' => array( 'type' => 'integer' ) ), 'required' => array( 'user_id' ) ); }
}
