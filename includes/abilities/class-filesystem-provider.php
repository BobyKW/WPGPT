<?php

namespace WPGPT\MCPBridge;

use WPGPT\MCPBridge\Filesystem\Filesystem_Service;
use WP_Error;

if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

class Filesystem_Provider extends Base_Ability_Provider {
    private ?Filesystem_Service $service = null;

    public function get_abilities(): array {
        return array(
            'wpgpt/filesystem-query' => array(
                'label'               => __( 'Filesystem query', 'wpgpt-mcp-bridge' ),
                'description'         => __( 'Lista y resume recursos del sistema de archivos permitidos, incluidos plugins, temas, árboles, búsquedas y backups.', 'wpgpt-mcp-bridge' ),
                'input_schema'        => $this->query_schema(),
                'execute_callback'    => array( $this, 'filesystem_query' ),
                'output_schema'       => $this->object_schema(),
                'permission_callback' => array( $this, 'can_read_files' ),
            ),
            'wpgpt/filesystem-inspect' => array(
                'label'               => __( 'Filesystem inspect', 'wpgpt-mcp-bridge' ),
                'description'         => __( 'Inspecciona rutas, diffs, árboles y backups del sistema de archivos permitido.', 'wpgpt-mcp-bridge' ),
                'input_schema'        => $this->inspect_schema(),
                'execute_callback'    => array( $this, 'filesystem_inspect' ),
                'output_schema'       => $this->object_schema(),
                'permission_callback' => array( $this, 'can_read_files' ),
            ),
            'wpgpt/filesystem-apply' => array(
                'label'               => __( 'Filesystem apply', 'wpgpt-mcp-bridge' ),
                'description'         => __( 'Ejecuta acciones controladas sobre el sistema de archivos permitido, con soporte dry_run.', 'wpgpt-mcp-bridge' ),
                'input_schema'        => $this->apply_schema(),
                'execute_callback'    => array( $this, 'filesystem_apply' ),
                'output_schema'       => $this->object_schema(),
                'permission_callback' => array( $this, 'can_write_files' ),
            ),
        );
    }

    public function filesystem_query( array $input = array() ): array|WP_Error {
        return $this->service()->query( $input );
    }

    public function filesystem_inspect( array $input = array() ): array|WP_Error {
        return $this->service()->inspect( $input );
    }

    public function filesystem_apply( array $input = array() ): array|WP_Error {
        return $this->service()->apply( $input );
    }

    private function service(): Filesystem_Service {
        return $this->service ??= new Filesystem_Service();
    }

    private function query_schema(): array {
        return array(
            'type'       => 'object',
            'properties' => array(
                'scope'      => array( 'type' => 'string', 'enum' => array( 'all', 'plugins', 'themes', 'plugin_tree', 'theme_tree', 'search', 'backups' ) ),
                'plugin_slug'=> array( 'type' => 'string' ),
                'stylesheet' => array( 'type' => 'string' ),
                'query'      => array( 'type' => 'string' ),
                'extensions' => array( 'type' => 'array', 'items' => array( 'type' => 'string' ) ),
                'max_depth'  => array( 'type' => 'integer' ),
                'limit'      => array( 'type' => 'integer' ),
            ),
        );
    }

    private function inspect_schema(): array {
        return array(
            'type'       => 'object',
            'properties' => array(
                'mode'        => array( 'type' => 'string', 'enum' => array( 'path', 'diff', 'plugin_tree', 'theme_tree', 'backup' ) ),
                'path'        => array( 'type' => 'string' ),
                'path_a'      => array( 'type' => 'string' ),
                'path_b'      => array( 'type' => 'string' ),
                'start_line'  => array( 'type' => 'integer' ),
                'limit_lines' => array( 'type' => 'integer' ),
                'plugin_slug' => array( 'type' => 'string' ),
                'stylesheet'  => array( 'type' => 'string' ),
                'max_depth'   => array( 'type' => 'integer' ),
                'limit'       => array( 'type' => 'integer' ),
                'backup_id'   => array( 'type' => 'string' ),
            ),
        );
    }

    private function apply_schema(): array {
        return array(
            'type'       => 'object',
            'properties' => array(
                'action'  => array( 'type' => 'string', 'enum' => array( 'mkdir', 'copy', 'move', 'rename', 'delete', 'zip_create', 'zip_extract', 'write', 'patch', 'backup_restore', 'backup_delete' ) ),
                'dry_run' => array( 'type' => 'boolean' ),
                'payload' => array( 'type' => 'object', 'additionalProperties' => true ),
            ),
            'required'   => array( 'action' ),
        );
    }
}
