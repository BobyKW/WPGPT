<?php

namespace WPGPT\MCPBridge;

use WPGPT\MCPBridge\Database\Database_Inspector_Service;
use WPGPT\MCPBridge\Database\Database_Audit_Service;
use WP_Error;

if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

class Database_Provider extends Base_Ability_Provider {
    private ?Database_Inspector_Service $service = null;
    private ?Database_Audit_Service $audit_service = null;

    public function get_abilities(): array {
        return array(

            'wpgpt/db-audit-orphan-postmeta' => array(
                'label' => __( 'DB audit orphan postmeta', 'wpgpt-mcp-bridge' ),
                'description' => __( 'Detecta metadatos de post cuyos post_id ya no existen.', 'wpgpt-mcp-bridge' ),
                'input_schema' => $this->audit_limit_schema(),
                'execute_callback' => array( $this, 'db_audit_orphan_postmeta' ),
                'output_schema' => $this->object_schema(),
            ),
            'wpgpt/db-audit-orphan-usermeta' => array(
                'label' => __( 'DB audit orphan usermeta', 'wpgpt-mcp-bridge' ),
                'description' => __( 'Detecta metadatos de usuario cuyos user_id ya no existen.', 'wpgpt-mcp-bridge' ),
                'input_schema' => $this->audit_limit_schema(),
                'execute_callback' => array( $this, 'db_audit_orphan_usermeta' ),
                'output_schema' => $this->object_schema(),
            ),
            'wpgpt/db-audit-orphan-term-relationships' => array(
                'label' => __( 'DB audit orphan term relationships', 'wpgpt-mcp-bridge' ),
                'description' => __( 'Detecta relaciones de taxonomía a objetos o taxonomías inexistentes.', 'wpgpt-mcp-bridge' ),
                'input_schema' => $this->audit_limit_schema(),
                'execute_callback' => array( $this, 'db_audit_orphan_term_relationships' ),
                'output_schema' => $this->object_schema(),
            ),
            'wpgpt/db-audit-unused-terms' => array(
                'label' => __( 'DB audit unused terms', 'wpgpt-mcp-bridge' ),
                'description' => __( 'Lista términos sin uso efectivo (count 0), con filtro opcional por taxonomía.', 'wpgpt-mcp-bridge' ),
                'input_schema' => $this->unused_terms_input_schema(),
                'execute_callback' => array( $this, 'db_audit_unused_terms' ),
                'output_schema' => $this->object_schema(),
            ),
            'wpgpt/db-list-tables'    => array(
                'label'            => __( 'DB list tables', 'wpgpt-mcp-bridge' ),
                'description'      => __( 'Lista tablas seguras de la base de datos de WordPress.', 'wpgpt-mcp-bridge' ),
                'execute_callback' => array( $this, 'db_list_tables' ),
                'output_schema'    => $this->object_schema(),
            ),
            'wpgpt/db-describe-table' => array(
                'label'            => __( 'DB describe table', 'wpgpt-mcp-bridge' ),
                'description'      => __( 'Describe columnas de una tabla segura.', 'wpgpt-mcp-bridge' ),
                'input_schema'     => $this->table_input_schema(),
                'execute_callback' => array( $this, 'db_describe_table' ),
                'output_schema'    => $this->object_schema(),
            ),
            'wpgpt/db-select-safe'    => array(
                'label'            => __( 'DB select safe', 'wpgpt-mcp-bridge' ),
                'description'      => __( 'Consulta segura de lectura sobre tablas permitidas.', 'wpgpt-mcp-bridge' ),
                'input_schema'     => $this->db_select_input_schema(),
                'execute_callback' => array( $this, 'db_select_safe' ),
                'output_schema'    => $this->object_schema(),
            ),
            'wpgpt/db-count-safe'     => array(
                'label'            => __( 'DB count safe', 'wpgpt-mcp-bridge' ),
                'description'      => __( 'Cuenta filas de una tabla permitida con filtros seguros.', 'wpgpt-mcp-bridge' ),
                'input_schema'     => $this->db_count_input_schema(),
                'execute_callback' => array( $this, 'db_count_safe' ),
                'output_schema'    => $this->object_schema(),
            ),
            'wpgpt/db-search-safe'    => array(
                'label'            => __( 'DB search safe', 'wpgpt-mcp-bridge' ),
                'description'      => __( 'Busca texto con LIKE en una columna permitida.', 'wpgpt-mcp-bridge' ),
                'input_schema'     => $this->db_search_input_schema(),
                'execute_callback' => array( $this, 'db_search_safe' ),
                'output_schema'    => $this->object_schema(),
            ),
            'wpgpt/db-distinct-safe'  => array(
                'label'            => __( 'DB distinct safe', 'wpgpt-mcp-bridge' ),
                'description'      => __( 'Devuelve valores distintos de una columna permitida.', 'wpgpt-mcp-bridge' ),
                'input_schema'     => $this->db_distinct_input_schema(),
                'execute_callback' => array( $this, 'db_distinct_safe' ),
                'output_schema'    => $this->object_schema(),
            ),
        );
    }

    public function db_audit_orphan_postmeta( array $input ): array { return $this->audit_service()->orphan_postmeta( $input ); }

    public function db_audit_orphan_usermeta( array $input ): array { return $this->audit_service()->orphan_usermeta( $input ); }

    public function db_audit_orphan_term_relationships( array $input ): array { return $this->audit_service()->orphan_term_relationships( $input ); }

    public function db_audit_unused_terms( array $input ): array { return $this->audit_service()->unused_terms( $input ); }

    public function db_list_tables(): array {
        return $this->service()->list_tables();
    }

    public function db_describe_table( array $input ): array|WP_Error {
        $table_key = isset( $input['table'] ) ? sanitize_key( (string) $input['table'] ) : '';
        return $this->service()->describe_table( $table_key );
    }

    public function db_select_safe( array $input ): array|WP_Error {
        $table_key = isset( $input['table'] ) ? sanitize_key( (string) $input['table'] ) : '';
        $columns   = isset( $input['columns'] ) && is_array( $input['columns'] ) ? $input['columns'] : array();
        $where     = isset( $input['where'] ) && is_array( $input['where'] ) ? $input['where'] : array();
        $limit     = isset( $input['limit'] ) ? (int) $input['limit'] : 10;

        return $this->service()->select( $table_key, $columns, $where, $limit );
    }

    public function db_count_safe( array $input ): array|WP_Error {
        $table_key = isset( $input['table'] ) ? sanitize_key( (string) $input['table'] ) : '';
        $where     = isset( $input['where'] ) && is_array( $input['where'] ) ? $input['where'] : array();

        return $this->service()->count( $table_key, $where );
    }

    public function db_search_safe( array $input ): array|WP_Error {
        $table_key = isset( $input['table'] ) ? sanitize_key( (string) $input['table'] ) : '';
        $column    = isset( $input['column'] ) ? sanitize_key( (string) $input['column'] ) : '';
        $term      = isset( $input['term'] ) ? sanitize_text_field( (string) $input['term'] ) : '';
        $columns   = isset( $input['columns'] ) && is_array( $input['columns'] ) ? $input['columns'] : array();
        $limit     = isset( $input['limit'] ) ? (int) $input['limit'] : 20;

        return $this->service()->search( $table_key, $column, $term, $columns, $limit );
    }

    public function db_distinct_safe( array $input ): array|WP_Error {
        $table_key = isset( $input['table'] ) ? sanitize_key( (string) $input['table'] ) : '';
        $column    = isset( $input['column'] ) ? sanitize_key( (string) $input['column'] ) : '';
        $where     = isset( $input['where'] ) && is_array( $input['where'] ) ? $input['where'] : array();
        $limit     = isset( $input['limit'] ) ? (int) $input['limit'] : 50;

        return $this->service()->distinct( $table_key, $column, $where, $limit );
    }

    private function audit_service(): Database_Audit_Service {
        if ( null === $this->audit_service ) {
            $this->audit_service = new Database_Audit_Service();
        }

        return $this->audit_service;
    }

    private function service(): Database_Inspector_Service {
        if ( null === $this->service ) {
            $this->service = new Database_Inspector_Service();
        }

        return $this->service;
    }

    private function audit_limit_schema(): array {
        return array(
            'type'       => 'object',
            'properties' => array(
                'limit' => array( 'type' => 'integer' ),
            ),
        );
    }

    private function unused_terms_input_schema(): array {
        return array(
            'type'       => 'object',
            'properties' => array(
                'taxonomy' => array( 'type' => 'string' ),
                'limit'    => array( 'type' => 'integer' ),
            ),
        );
    }

    private function table_input_schema(): array {
        return array(
            'type'       => 'object',
            'properties' => array(
                'table' => array( 'type' => 'string' ),
            ),
            'required'   => array( 'table' ),
        );
    }

    private function db_select_input_schema(): array {
        return array(
            'type'       => 'object',
            'properties' => array(
                'table'   => array( 'type' => 'string' ),
                'columns' => array( 'type' => 'array', 'items' => array( 'type' => 'string' ) ),
                'where'   => array( 'type' => 'object', 'additionalProperties' => true ),
                'limit'   => array( 'type' => 'integer' ),
            ),
            'required'   => array( 'table' ),
        );
    }

    private function db_count_input_schema(): array {
        return array(
            'type'       => 'object',
            'properties' => array(
                'table' => array( 'type' => 'string' ),
                'where' => array( 'type' => 'object', 'additionalProperties' => true ),
            ),
            'required'   => array( 'table' ),
        );
    }

    private function db_search_input_schema(): array {
        return array(
            'type'       => 'object',
            'properties' => array(
                'table'   => array( 'type' => 'string' ),
                'column'  => array( 'type' => 'string' ),
                'term'    => array( 'type' => 'string' ),
                'columns' => array( 'type' => 'array', 'items' => array( 'type' => 'string' ) ),
                'limit'   => array( 'type' => 'integer' ),
            ),
            'required'   => array( 'table', 'column', 'term' ),
        );
    }

    private function db_distinct_input_schema(): array {
        return array(
            'type'       => 'object',
            'properties' => array(
                'table'  => array( 'type' => 'string' ),
                'column' => array( 'type' => 'string' ),
                'where'  => array( 'type' => 'object', 'additionalProperties' => true ),
                'limit'  => array( 'type' => 'integer' ),
            ),
            'required'   => array( 'table', 'column' ),
        );
    }
}
