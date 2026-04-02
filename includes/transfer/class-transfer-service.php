<?php

namespace WPGPT\MCPBridge\Transfer;

use WP_Error;

if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

class Transfer_Service {
    public function export_run( array $input ) {
        $resource = sanitize_key( (string) ( $input['resource'] ?? '' ) );
        $format   = sanitize_key( (string) ( $input['format'] ?? 'json' ) );
        $fields   = ! empty( $input['fields'] ) && is_array( $input['fields'] ) ? array_map( 'sanitize_text_field', $input['fields'] ) : array();
        $items    = match ( $resource ) {
            'posts' => $this->export_posts( $input, $fields ),
            'users' => $this->export_users( $fields ),
            'terms' => $this->export_terms( $input, $fields ),
            'media' => $this->export_media( $fields ),
            'options' => $this->export_options( $input ),
            default => new WP_Error( 'wpgpt_export_invalid_resource', __( 'Recurso de exportación no soportado.', 'wpgpt-mcp-bridge' ), array( 'status' => 400 ) ),
        };
        if ( is_wp_error( $items ) ) { return $items; }
        return array( 'success' => true, 'resource' => $resource, 'format' => $format, 'total' => count( $items ), 'data' => 'csv' === $format ? $this->to_csv( $items ) : wp_json_encode( $items, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES | JSON_PRETTY_PRINT ) );
    }

    public function import_parse( array $input ) {
        $rows = $this->parse_source( $input );
        if ( is_wp_error( $rows ) ) { return $rows; }
        return array( 'success' => true, 'resource' => sanitize_key( (string) ( $input['resource'] ?? '' ) ), 'row_count' => count( $rows ), 'preview' => array_slice( $rows, 0, 5 ) );
    }

    public function import_run( array $input ) {
        $resource = sanitize_key( (string) ( $input['resource'] ?? '' ) );
        $rows = $this->parse_source( $input );
        if ( is_wp_error( $rows ) ) { return $rows; }
        $mode = sanitize_key( (string) ( $input['mode'] ?? 'create' ) );
        $results = array();
        foreach ( $rows as $row ) {
            $results[] = match ( $resource ) {
                'posts' => $this->import_post_row( $row, $mode ),
                'terms' => $this->import_term_row( $row, $mode ),
                'users' => $this->import_user_row( $row, $mode ),
                default => new WP_Error( 'wpgpt_import_invalid_resource', __( 'Recurso de importación no soportado.', 'wpgpt-mcp-bridge' ) ),
            };
        }
        return array( 'success' => true, 'resource' => $resource, 'mode' => $mode, 'results' => $results );
    }

    private function parse_source( array $input ) {
        $source_type = sanitize_key( (string) ( $input['source_type'] ?? 'json' ) );
        $content = (string) ( $input['source_content'] ?? '' );
        if ( 'json' === $source_type ) {
            $decoded = json_decode( $content, true );
            return is_array( $decoded ) ? array_values( $decoded ) : new WP_Error( 'wpgpt_import_parse_failed', __( 'JSON inválido.', 'wpgpt-mcp-bridge' ), array( 'status' => 400 ) );
        }
        if ( 'csv' === $source_type ) {
            $lines = preg_split( '/\r\n|\r|\n/', trim( $content ) );
            $headers = str_getcsv( (string) array_shift( $lines ) );
            $rows = array();
            foreach ( $lines as $line ) {
                if ( '' === trim( $line ) ) { continue; }
                $rows[] = array_combine( $headers, str_getcsv( $line ) );
            }
            return $rows;
        }
        return new WP_Error( 'wpgpt_import_source_invalid', __( 'source_type no soportado.', 'wpgpt-mcp-bridge' ), array( 'status' => 400 ) );
    }

    private function export_posts( array $input, array $fields ): array {
        $posts = get_posts( array( 'post_type' => sanitize_key( (string) ( $input['filters']['post_type'] ?? 'post' ) ), 'post_status' => 'any', 'numberposts' => min( 200, max( 1, absint( $input['filters']['limit'] ?? 50 ) ) ) ) );
        return array_map( fn($p)=>$this->pick_fields( array( 'ID'=>$p->ID,'post_type'=>$p->post_type,'post_title'=>$p->post_title,'post_name'=>$p->post_name,'post_status'=>$p->post_status,'post_content'=>$p->post_content ), $fields ), $posts );
    }
    private function export_users( array $fields ): array { $users = get_users( array( 'number' => 200 ) ); return array_map( fn($u)=>$this->pick_fields( array( 'ID'=>$u->ID,'user_login'=>$u->user_login,'user_email'=>$u->user_email,'display_name'=>$u->display_name,'roles'=>implode(',', (array)$u->roles) ), $fields ), $users ); }
    private function export_terms( array $input, array $fields ): array { $terms = get_terms( array( 'taxonomy' => sanitize_key( (string) ( $input['filters']['taxonomy'] ?? 'category' ) ), 'hide_empty' => false, 'number' => 200 ) ); return array_map( fn($t)=>$this->pick_fields( array( 'term_id'=>$t->term_id,'taxonomy'=>$t->taxonomy,'name'=>$t->name,'slug'=>$t->slug,'description'=>$t->description ), $fields ), is_array($terms)?$terms:array() ); }
    private function export_media( array $fields ): array { $items = get_posts( array( 'post_type' => 'attachment', 'numberposts' => 200, 'post_status' => 'inherit' ) ); return array_map( fn($p)=>$this->pick_fields( array( 'ID'=>$p->ID,'title'=>$p->post_title,'mime_type'=>$p->post_mime_type,'url'=>wp_get_attachment_url($p->ID) ), $fields ), $items ); }
    private function export_options( array $input ): array { $keys = array_map( 'sanitize_text_field', (array) ( $input['filters']['keys'] ?? array() ) ); $rows = array(); foreach ( $keys as $key ) { $rows[] = array( 'option_name' => $key, 'option_value' => get_option( $key ) ); } return $rows; }

    private function import_post_row( array $row, string $mode ) {
        $postarr = array( 'post_type' => sanitize_key( (string) ( $row['post_type'] ?? 'post' ) ), 'post_title' => sanitize_text_field( (string) ( $row['post_title'] ?? '' ) ), 'post_name' => sanitize_title( (string) ( $row['post_name'] ?? '' ) ), 'post_status' => sanitize_key( (string) ( $row['post_status'] ?? 'draft' ) ), 'post_content' => (string) ( $row['post_content'] ?? '' ) );
        if ( 'update' === $mode || 'upsert' === $mode ) { $postarr['ID'] = absint( $row['ID'] ?? 0 ); }
        $id = wp_insert_post( wp_slash( $postarr ), true );
        return is_wp_error( $id ) ? array( 'success' => false, 'error' => $id->get_error_message() ) : array( 'success' => true, 'id' => $id );
    }
    private function import_term_row( array $row, string $mode ) { $taxonomy = sanitize_key( (string) ( $row['taxonomy'] ?? 'category' ) ); if ( ( 'update' === $mode || 'upsert' === $mode ) && ! empty( $row['term_id'] ) ) { $res = wp_update_term( absint( $row['term_id'] ), $taxonomy, array( 'name' => sanitize_text_field( (string) ( $row['name'] ?? '' ) ), 'slug' => sanitize_title( (string) ( $row['slug'] ?? '' ) ), 'description' => sanitize_text_field( (string) ( $row['description'] ?? '' ) ) ) ); } else { $res = wp_insert_term( sanitize_text_field( (string) ( $row['name'] ?? '' ) ), $taxonomy, array( 'slug' => sanitize_title( (string) ( $row['slug'] ?? '' ) ), 'description' => sanitize_text_field( (string) ( $row['description'] ?? '' ) ) ) ); } return is_wp_error( $res ) ? array( 'success' => false, 'error' => $res->get_error_message() ) : array( 'success' => true, 'term_id' => (int) ( $res['term_id'] ?? 0 ) ); }
    private function import_user_row( array $row, string $mode ) { $userdata = array( 'user_login' => sanitize_user( (string) ( $row['user_login'] ?? '' ), true ), 'user_email' => sanitize_email( (string) ( $row['user_email'] ?? '' ) ), 'display_name' => sanitize_text_field( (string) ( $row['display_name'] ?? '' ) ) ); if ( ! empty( $row['roles'] ) ) { $userdata['role'] = sanitize_key( explode( ',', (string) $row['roles'] )[0] ); } if ( 'update' === $mode || 'upsert' === $mode ) { $userdata['ID'] = absint( $row['ID'] ?? 0 ); $res = wp_update_user( $userdata ); } else { if ( empty( $row['user_pass'] ) ) { $userdata['user_pass'] = wp_generate_password( 20, true, true ); } else { $userdata['user_pass'] = (string) $row['user_pass']; } $res = wp_insert_user( $userdata ); } return is_wp_error( $res ) ? array( 'success' => false, 'error' => $res->get_error_message() ) : array( 'success' => true, 'user_id' => (int) $res ); }

    private function pick_fields( array $row, array $fields ): array { return empty( $fields ) ? $row : array_intersect_key( $row, array_flip( $fields ) ); }
    private function to_csv( array $rows ): string { if ( empty( $rows ) ) { return ''; } $fh = fopen( 'php://temp', 'r+' ); fputcsv( $fh, array_keys( (array) $rows[0] ) ); foreach ( $rows as $row ) { fputcsv( $fh, array_values( (array) $row ) ); } rewind( $fh ); return (string) stream_get_contents( $fh ); }
}
