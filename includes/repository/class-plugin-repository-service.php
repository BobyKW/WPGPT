<?php

namespace WPGPT\MCPBridge\Repository;

use WPGPT\MCPBridge\Plugins\Plugin_Manager_Service;
use WP_Error;

if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

class Plugin_Repository_Service {
    public function search( string $search, int $page = 1, int $limit = 10 ): array|WP_Error {
        if ( '' === $search ) {
            return new WP_Error( 'wpgpt_empty_plugin_search', __( 'Debes indicar un texto de búsqueda.', 'wpgpt-mcp-bridge' ), array( 'status' => 400 ) );
        }
        require_once ABSPATH . 'wp-admin/includes/plugin-install.php';
        $page  = max( 1, $page );
        $limit = max( 1, min( 20, $limit ) );
        $query = plugins_api('query_plugins', array('search'=>$search,'page'=>$page,'per_page'=>$limit,'fields'=>array('short_description'=>true,'active_installs'=>true,'tested'=>true,'requires'=>true,'requires_php'=>true,'rating'=>true,'ratings'=>false,'downloaded'=>true,'last_updated'=>true,'sections'=>false,'tags'=>false,'versions'=>false,'donate_link'=>false,'banners'=>false)));
        if ( is_wp_error( $query ) ) { return $query; }
        $items = array();
        foreach ( (array) ( $query->plugins ?? array() ) as $plugin ) { $items[] = $this->format_plugin_summary( $plugin ); }
        return array('search'=>$search,'page'=>$page,'limit'=>$limit,'results'=>(int)($query->info['results'] ?? count($items)),'pages'=>(int)($query->info['pages'] ?? 1),'count'=>count($items),'items'=>$items);
    }
    public function info( string $slug ): array|WP_Error {
        if ( '' === $slug ) { return new WP_Error( 'wpgpt_empty_plugin_slug', __( 'Debes indicar un slug de plugin.', 'wpgpt-mcp-bridge' ), array( 'status' => 400 ) ); }
        require_once ABSPATH . 'wp-admin/includes/plugin-install.php';
        $plugin = plugins_api('plugin_information', array('slug'=>$slug,'fields'=>array('short_description'=>true,'active_installs'=>true,'tested'=>true,'requires'=>true,'requires_php'=>true,'rating'=>true,'downloaded'=>true,'last_updated'=>true,'sections'=>false,'tags'=>true,'versions'=>false,'banners'=>false)));
        if ( is_wp_error( $plugin ) ) { return $plugin; }
        return array('slug'=>(string)($plugin->slug ?? ''),'name'=>(string)($plugin->name ?? ''),'version'=>(string)($plugin->version ?? ''),'author'=>wp_strip_all_tags((string)($plugin->author ?? '')),'homepage'=>(string)($plugin->homepage ?? ''),'download_link'=>(string)($plugin->download_link ?? ''),'requires'=>(string)($plugin->requires ?? ''),'requires_php'=>(string)($plugin->requires_php ?? ''),'tested'=>(string)($plugin->tested ?? ''),'last_updated'=>(string)($plugin->last_updated ?? ''),'rating'=>(int)($plugin->rating ?? 0),'num_ratings'=>(int)($plugin->num_ratings ?? 0),'active_installs'=>(int)($plugin->active_installs ?? 0),'downloaded'=>(int)($plugin->downloaded ?? 0),'short_description'=>wp_strip_all_tags((string)($plugin->short_description ?? '')));
    }
    public function query( array $input = array() ): array|WP_Error {
        $search = isset($input['search']) ? sanitize_text_field((string)$input['search']) : '';
        $page = isset($input['page']) ? (int)$input['page'] : 1;
        $limit = isset($input['limit']) ? (int)$input['limit'] : 10;
        $results = $this->search($search,$page,$limit);
        if ( is_wp_error($results) ) { return $results; }
        return array('summary'=>array('search'=>$search,'page'=>$page,'limit'=>$limit,'matched'=>(int)($results['results'] ?? 0),'returned'=>count($results['items'] ?? array())),'items'=>$results['items'] ?? array(),'warnings'=>empty($results['items'])?array(__( 'No se han encontrado plugins del repositorio con esos filtros.', 'wpgpt-mcp-bridge' )):array(),'next_actions'=>(($results['pages'] ?? 1) > ($results['page'] ?? 1)) ? array('Usa page=' . ((int)$results['page'] + 1) . ' para continuar la consulta.') : array());
    }
    public function inspect( array $input = array() ): array|WP_Error {
        $targets = array();
        if ( ! empty( $input['slug'] ) ) { $targets[] = sanitize_key((string)$input['slug']); }
        if ( ! empty( $input['slugs'] ) && is_array( $input['slugs'] ) ) { foreach ($input['slugs'] as $slug) { $slug = sanitize_key((string)$slug); if ($slug !== '') $targets[] = $slug; } }
        $targets = array_values(array_unique(array_filter($targets)));
        if ( empty( $targets ) ) { return new WP_Error( 'wpgpt_repository_slug_required', __( 'Debes indicar al menos un slug.', 'wpgpt-mcp-bridge' ), array( 'status' => 400 ) ); }
        $items=[]; $warnings=[];
        foreach ($targets as $slug) { $info = $this->info($slug); if (is_wp_error($info)) { $warnings[]=$info->get_error_message(); continue; } $items[] = array_merge($info,array('available_actions'=>array('install'),'risk_level'=>'medium')); }
        return array('summary'=>array('requested'=>count($targets),'inspected'=>count($items)),'items'=>$items,'warnings'=>array_values(array_unique($warnings)),'next_actions'=>array(__( 'Usa wpgpt/plugin-repository-apply con dry_run=true antes de instalar.', 'wpgpt-mcp-bridge' )));
    }
    public function apply( array $input = array() ): array|WP_Error {
        $action = isset($input['action']) ? sanitize_key((string)$input['action']) : '';
        $dry_run = ! empty($input['dry_run']);
        if ( 'install' !== $action ) { return new WP_Error( 'wpgpt_repository_action_invalid', __( 'La acción indicada no es válida.', 'wpgpt-mcp-bridge' ), array( 'status' => 400 ) ); }
        $targets = array();
        if ( ! empty($input['targets']) && is_array($input['targets']) ) { foreach ($input['targets'] as $t) { if (is_array($t) && !empty($t['slug'])) $targets[] = sanitize_key((string)$t['slug']); } }
        if ( empty($targets) && ! empty($input['search']) ) { $query = $this->search(sanitize_text_field((string)$input['search']),1,1); if (!is_wp_error($query) && !empty($query['items'][0]['slug'])) $targets[] = sanitize_key((string)$query['items'][0]['slug']); }
        $targets = array_values(array_unique(array_filter($targets)));
        if ( empty($targets) ) { return new WP_Error( 'wpgpt_repository_target_required', __( 'Debes indicar al menos un slug o search para instalar.', 'wpgpt-mcp-bridge' ), array( 'status' => 400 ) ); }
        $items=[]; $warnings=[]; $blocked=[]; $executed=0; $pm = new Plugin_Manager_Service();
        foreach ($targets as $slug) {
            if ($dry_run) { $items[] = array('slug'=>$slug,'status'=>'dry_run','action'=>'install','message'=>__( 'Acción validada, no ejecutada por dry_run.', 'wpgpt-mcp-bridge' )); continue; }
            $result = $pm->install($slug); if (is_wp_error($result)) { $blocked[] = array('slug'=>$slug,'reasons'=>array($result->get_error_message())); continue; }
            $executed++; $items[] = array('slug'=>$slug,'status'=>'applied','action'=>'install','result'=>$result);
        }
        return array('summary'=>array('action'=>'install','dry_run'=>$dry_run,'resolved_targets'=>count($targets),'executed'=>$executed,'blocked'=>count($blocked)),'items'=>$items,'warnings'=>$warnings,'blocked'=>$blocked,'next_actions'=>$dry_run?array(__( 'Repite la misma llamada con dry_run=false para aplicar los cambios validados.', 'wpgpt-mcp-bridge' )):array());
    }
    private function format_plugin_summary( $plugin ): array {
        if ( is_array( $plugin ) ) { $plugin = (object) $plugin; }
        return array('slug'=>(string)($plugin->slug ?? ''),'name'=>(string)($plugin->name ?? ''),'version'=>(string)($plugin->version ?? ''),'author'=>wp_strip_all_tags((string)($plugin->author ?? '')),'tested'=>(string)($plugin->tested ?? ''),'requires'=>(string)($plugin->requires ?? ''),'requires_php'=>(string)($plugin->requires_php ?? ''),'rating'=>(int)($plugin->rating ?? 0),'active_installs'=>(int)($plugin->active_installs ?? 0),'last_updated'=>(string)($plugin->last_updated ?? ''),'short_description'=>wp_strip_all_tags((string)($plugin->short_description ?? '')));
    }
}
