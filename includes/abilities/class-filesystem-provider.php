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
            'wpgpt/fs-plugin-list' => array(
                'label'               => __( 'Filesystem plugin list', 'wpgpt-mcp-bridge' ),
                'description'         => __( 'Lista plugins instalados para inspección de archivos y código.', 'wpgpt-mcp-bridge' ),
                'execute_callback'    => array( $this, 'fs_plugin_list' ),
                'output_schema'       => $this->object_schema(),
                'permission_callback' => array( $this, 'can_read_files' ),
            ),
            'wpgpt/fs-theme-file-tree' => array(
                'label' => __( 'Filesystem theme file tree', 'wpgpt-mcp-bridge' ),
                'description' => __( 'Devuelve un árbol de archivos de un tema instalado.', 'wpgpt-mcp-bridge' ),
                'input_schema' => array( 'type' => 'object', 'properties' => array( 'stylesheet' => array( 'type' => 'string' ), 'max_depth' => array( 'type' => 'integer' ), 'limit' => array( 'type' => 'integer' ) ), 'required' => array( 'stylesheet' ) ),
                'execute_callback' => array( $this, 'fs_theme_file_tree' ),
                'output_schema' => $this->object_schema(),
                'permission_callback' => array( $this, 'can_read_files' ),
            ),
            'wpgpt/fs-plugin-file-tree' => array(
                'label'               => __( 'Filesystem plugin file tree', 'wpgpt-mcp-bridge' ),
                'description'         => __( 'Devuelve un árbol de archivos de un plugin instalado.', 'wpgpt-mcp-bridge' ),
                'input_schema'        => $this->plugin_tree_schema(),
                'execute_callback'    => array( $this, 'fs_plugin_file_tree' ),
                'output_schema'       => $this->object_schema(),
                'permission_callback' => array( $this, 'can_read_files' ),
            ),
            'wpgpt/fs-read' => array(
                'label'               => __( 'Filesystem read', 'wpgpt-mcp-bridge' ),
                'description'         => __( 'Lee un archivo permitido con paginación por líneas.', 'wpgpt-mcp-bridge' ),
                'input_schema'        => $this->read_schema(),
                'execute_callback'    => array( $this, 'fs_read' ),
                'output_schema'       => $this->object_schema(),
                'permission_callback' => array( $this, 'can_read_files' ),
            ),
            'wpgpt/fs-mkdir' => array(
                'label' => __( 'Filesystem mkdir', 'wpgpt-mcp-bridge' ),
                'description' => __( 'Crea una carpeta permitida.', 'wpgpt-mcp-bridge' ),
                'input_schema' => array( 'type' => 'object', 'properties' => array( 'path' => array( 'type' => 'string' ) ), 'required' => array( 'path' ) ),
                'execute_callback' => array( $this, 'fs_mkdir' ),
                'output_schema' => $this->object_schema(),
                'permission_callback' => array( $this, 'can_write_files' ),
            ),
            'wpgpt/fs-copy' => array(
                'label' => __( 'Filesystem copy', 'wpgpt-mcp-bridge' ),
                'description' => __( 'Copia una ruta permitida.', 'wpgpt-mcp-bridge' ),
                'input_schema' => array( 'type' => 'object', 'properties' => array( 'source' => array( 'type' => 'string' ), 'destination' => array( 'type' => 'string' ), 'overwrite' => array( 'type' => 'boolean' ) ), 'required' => array( 'source', 'destination' ) ),
                'execute_callback' => array( $this, 'fs_copy' ),
                'output_schema' => $this->object_schema(),
                'permission_callback' => array( $this, 'can_write_files' ),
            ),
            'wpgpt/fs-move' => array(
                'label' => __( 'Filesystem move', 'wpgpt-mcp-bridge' ),
                'description' => __( 'Mueve una ruta permitida.', 'wpgpt-mcp-bridge' ),
                'input_schema' => array( 'type' => 'object', 'properties' => array( 'source' => array( 'type' => 'string' ), 'destination' => array( 'type' => 'string' ), 'overwrite' => array( 'type' => 'boolean' ) ), 'required' => array( 'source', 'destination' ) ),
                'execute_callback' => array( $this, 'fs_move' ),
                'output_schema' => $this->object_schema(),
                'permission_callback' => array( $this, 'can_write_files' ),
            ),
            'wpgpt/fs-rename' => array(
                'label' => __( 'Filesystem rename', 'wpgpt-mcp-bridge' ),
                'description' => __( 'Renombra una ruta permitida.', 'wpgpt-mcp-bridge' ),
                'input_schema' => array( 'type' => 'object', 'properties' => array( 'path' => array( 'type' => 'string' ), 'new_name' => array( 'type' => 'string' ) ), 'required' => array( 'path', 'new_name' ) ),
                'execute_callback' => array( $this, 'fs_rename' ),
                'output_schema' => $this->object_schema(),
                'permission_callback' => array( $this, 'can_write_files' ),
            ),
            'wpgpt/fs-delete' => array(
                'label' => __( 'Filesystem delete', 'wpgpt-mcp-bridge' ),
                'description' => __( 'Elimina una ruta permitida.', 'wpgpt-mcp-bridge' ),
                'input_schema' => array( 'type' => 'object', 'properties' => array( 'path' => array( 'type' => 'string' ) ), 'required' => array( 'path' ) ),
                'execute_callback' => array( $this, 'fs_delete' ),
                'output_schema' => $this->object_schema(),
                'permission_callback' => array( $this, 'can_delete_files' ),
            ),
            'wpgpt/fs-diff' => array(
                'label' => __( 'Filesystem diff', 'wpgpt-mcp-bridge' ),
                'description' => __( 'Compara dos archivos permitidos línea a línea.', 'wpgpt-mcp-bridge' ),
                'input_schema' => array( 'type' => 'object', 'properties' => array( 'path_a' => array( 'type' => 'string' ), 'path_b' => array( 'type' => 'string' ) ), 'required' => array( 'path_a', 'path_b' ) ),
                'execute_callback' => array( $this, 'fs_diff' ),
                'output_schema' => $this->object_schema(),
                'permission_callback' => array( $this, 'can_read_files' ),
            ),
            'wpgpt/fs-zip-create' => array(
                'label' => __( 'Filesystem zip create', 'wpgpt-mcp-bridge' ),
                'description' => __( 'Crea un archivo zip de una ruta permitida.', 'wpgpt-mcp-bridge' ),
                'input_schema' => array( 'type' => 'object', 'properties' => array( 'source' => array( 'type' => 'string' ), 'destination' => array( 'type' => 'string' ) ), 'required' => array( 'source', 'destination' ) ),
                'execute_callback' => array( $this, 'fs_zip_create' ),
                'output_schema' => $this->object_schema(),
                'permission_callback' => array( $this, 'can_write_files' ),
            ),
            'wpgpt/fs-zip-extract' => array(
                'label' => __( 'Filesystem zip extract', 'wpgpt-mcp-bridge' ),
                'description' => __( 'Extrae un zip permitido a una carpeta permitida.', 'wpgpt-mcp-bridge' ),
                'input_schema' => array( 'type' => 'object', 'properties' => array( 'zip_path' => array( 'type' => 'string' ), 'destination' => array( 'type' => 'string' ) ), 'required' => array( 'zip_path', 'destination' ) ),
                'execute_callback' => array( $this, 'fs_zip_extract' ),
                'output_schema' => $this->object_schema(),
                'permission_callback' => array( $this, 'can_write_files' ),
            ),
            'wpgpt/fs-search' => array(
                'label'               => __( 'Filesystem search', 'wpgpt-mcp-bridge' ),
                'description'         => __( 'Busca texto en archivos permitidos dentro de plugins, themes o mu-plugins.', 'wpgpt-mcp-bridge' ),
                'input_schema'        => $this->search_schema(),
                'execute_callback'    => array( $this, 'fs_search' ),
                'output_schema'       => $this->object_schema(),
                'permission_callback' => array( $this, 'can_read_files' ),
            ),
            'wpgpt/fs-write' => array(
                'label'               => __( 'Filesystem write', 'wpgpt-mcp-bridge' ),
                'description'         => __( 'Escribe o crea un archivo permitido y guarda backup automático.', 'wpgpt-mcp-bridge' ),
                'input_schema'        => $this->write_schema(),
                'execute_callback'    => array( $this, 'fs_write' ),
                'output_schema'       => $this->object_schema(),
                'permission_callback' => array( $this, 'can_write_files' ),
            ),
            'wpgpt/fs-patch' => array(
                'label'               => __( 'Filesystem patch', 'wpgpt-mcp-bridge' ),
                'description'         => __( 'Aplica un parche simple por búsqueda y reemplazo en un archivo permitido con backup automático.', 'wpgpt-mcp-bridge' ),
                'input_schema'        => $this->patch_schema(),
                'execute_callback'    => array( $this, 'fs_patch' ),
                'output_schema'       => $this->object_schema(),
                'permission_callback' => array( $this, 'can_write_files' ),
            ),
            'wpgpt/fs-backup-list' => array(
                'label'               => __( 'Filesystem backup list', 'wpgpt-mcp-bridge' ),
                'description'         => __( 'Lista backups automáticos generados por escritura o patching.', 'wpgpt-mcp-bridge' ),
                'input_schema'        => $this->backup_list_schema(),
                'execute_callback'    => array( $this, 'fs_backup_list' ),
                'output_schema'       => $this->object_schema(),
                'permission_callback' => array( $this, 'can_read_files' ),
            ),
            'wpgpt/fs-backup-delete' => array(
                'label' => __( 'Filesystem backup delete', 'wpgpt-mcp-bridge' ),
                'description' => __( 'Elimina un backup automático por su backup_id.', 'wpgpt-mcp-bridge' ),
                'input_schema' => $this->backup_restore_schema(),
                'execute_callback' => array( $this, 'fs_backup_delete' ),
                'output_schema' => $this->object_schema(),
                'permission_callback' => array( $this, 'can_delete_files' ),
            ),
            'wpgpt/fs-backup-restore' => array(
                'label'               => __( 'Filesystem backup restore', 'wpgpt-mcp-bridge' ),
                'description'         => __( 'Restaura un backup automático por su backup_id.', 'wpgpt-mcp-bridge' ),
                'input_schema'        => $this->backup_restore_schema(),
                'execute_callback'    => array( $this, 'fs_backup_restore' ),
                'output_schema'       => $this->object_schema(),
                'permission_callback' => array( $this, 'can_write_files' ),
            ),
        );
    }

    public function fs_plugin_list(): array { return $this->service()->plugin_list(); }
    public function fs_theme_file_tree( array $input ): array|WP_Error {
        $stylesheet = isset( $input['stylesheet'] ) ? sanitize_text_field( (string) $input['stylesheet'] ) : '';
        return $this->service()->theme_file_tree( $stylesheet, isset( $input['max_depth'] ) ? (int) $input['max_depth'] : 4, isset( $input['limit'] ) ? (int) $input['limit'] : 500 );
    }
    public function fs_plugin_file_tree( array $input ): array|WP_Error {
        $plugin_slug = isset( $input['plugin_slug'] ) ? sanitize_key( (string) $input['plugin_slug'] ) : '';
        $max_depth   = isset( $input['max_depth'] ) ? (int) $input['max_depth'] : 4;
        $limit       = isset( $input['limit'] ) ? (int) $input['limit'] : 500;
        return $this->service()->plugin_file_tree( $plugin_slug, $max_depth, $limit );
    }
    public function fs_read( array $input ): array|WP_Error {
        $path       = isset( $input['path'] ) ? (string) $input['path'] : '';
        $start_line = isset( $input['start_line'] ) ? (int) $input['start_line'] : 1;
        $limit      = isset( $input['limit_lines'] ) ? (int) $input['limit_lines'] : 200;
        return $this->service()->read_file( $path, $start_line, $limit );
    }
    public function fs_diff( array $input ): array|WP_Error { return $this->service()->diff_files( (string) ( $input['path_a'] ?? '' ), (string) ( $input['path_b'] ?? '' ) ); }
    public function fs_zip_create( array $input ): array|WP_Error { return $this->service()->zip_create( (string) ( $input['source'] ?? '' ), (string) ( $input['destination'] ?? '' ) ); }
    public function fs_zip_extract( array $input ): array|WP_Error { return $this->service()->zip_extract( (string) ( $input['zip_path'] ?? '' ), (string) ( $input['destination'] ?? '' ) ); }
    public function fs_search( array $input ): array|WP_Error {
        $query      = isset( $input['query'] ) ? (string) $input['query'] : '';
        $scope      = isset( $input['scope'] ) ? sanitize_text_field( (string) $input['scope'] ) : 'plugins';
        $extensions = isset( $input['extensions'] ) && is_array( $input['extensions'] ) ? array_values( array_map( 'sanitize_key', $input['extensions'] ) ) : array();
        $limit      = isset( $input['limit'] ) ? (int) $input['limit'] : 50;
        return $this->service()->search_files( $query, $scope, $extensions, $limit );
    }
    public function fs_write( array $input ): array|WP_Error {
        $path = isset( $input['path'] ) ? (string) $input['path'] : '';
        $content = isset( $input['content'] ) ? (string) $input['content'] : '';
        $create_if_missing = isset( $input['create_if_missing'] ) ? (bool) $input['create_if_missing'] : true;
        return $this->service()->write_file( $path, $content, $create_if_missing );
    }
    public function fs_patch( array $input ): array|WP_Error {
        $path = isset( $input['path'] ) ? (string) $input['path'] : '';
        $search = isset( $input['search'] ) ? (string) $input['search'] : '';
        $replace = isset( $input['replace'] ) ? (string) $input['replace'] : '';
        $replace_all = isset( $input['replace_all'] ) ? (bool) $input['replace_all'] : false;
        return $this->service()->patch_file( $path, $search, $replace, $replace_all );
    }
    public function fs_backup_list( array $input ): array|WP_Error {
        $limit = isset( $input['limit'] ) ? (int) $input['limit'] : 100;
        return $this->service()->list_backups( $limit );
    }
    public function fs_backup_delete( array $input ): array|WP_Error { return $this->service()->delete_backup( (string) ( $input['backup_id'] ?? '' ) ); }
    public function fs_backup_restore( array $input ): array|WP_Error {
        $backup_id = isset( $input['backup_id'] ) ? sanitize_text_field( (string) $input['backup_id'] ) : '';
        return $this->service()->restore_backup( $backup_id );
    }

    private function service(): Filesystem_Service {
        if ( null === $this->service ) { $this->service = new Filesystem_Service(); }
        return $this->service;
    }
    private function plugin_tree_schema(): array { return array('type'=>'object','properties'=>array('plugin_slug'=>array('type'=>'string'),'max_depth'=>array('type'=>'integer'),'limit'=>array('type'=>'integer')),'required'=>array('plugin_slug')); }
    private function read_schema(): array { return array('type'=>'object','properties'=>array('path'=>array('type'=>'string'),'start_line'=>array('type'=>'integer'),'limit_lines'=>array('type'=>'integer')),'required'=>array('path')); }
    private function search_schema(): array { return array('type'=>'object','properties'=>array('query'=>array('type'=>'string'),'scope'=>array('type'=>'string'),'extensions'=>array('type'=>'array','items'=>array('type'=>'string')),'limit'=>array('type'=>'integer')),'required'=>array('query')); }
    private function write_schema(): array { return array('type'=>'object','properties'=>array('path'=>array('type'=>'string'),'content'=>array('type'=>'string'),'create_if_missing'=>array('type'=>'boolean')),'required'=>array('path','content')); }
    private function patch_schema(): array { return array('type'=>'object','properties'=>array('path'=>array('type'=>'string'),'search'=>array('type'=>'string'),'replace'=>array('type'=>'string'),'replace_all'=>array('type'=>'boolean')),'required'=>array('path','search','replace')); }
    private function backup_list_schema(): array { return array('type'=>'object','properties'=>array('limit'=>array('type'=>'integer'))); }
    private function backup_restore_schema(): array { return array('type'=>'object','properties'=>array('backup_id'=>array('type'=>'string')),'required'=>array('backup_id')); }
}
