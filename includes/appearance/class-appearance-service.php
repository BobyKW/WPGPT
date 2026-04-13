<?php

namespace WPGPT\MCPBridge\Appearance;

use WP_Error;

if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

class Appearance_Service {
    public function get_theme_mods( array $input ): array {
        $mods = get_theme_mods();
        $keys = ! empty( $input['keys'] ) && is_array( $input['keys'] ) ? array_map( 'sanitize_text_field', $input['keys'] ) : array();
        if ( ! empty( $keys ) ) {
            $mods = array_intersect_key( (array) $mods, array_flip( $keys ) );
        }
        return array( 'success' => true, 'theme' => wp_get_theme()->get_stylesheet(), 'mods' => $mods );
    }

    public function update_theme_mods( array $input ): array {
        $updates = is_array( $input['updates'] ?? null ) ? $input['updates'] : array();
        foreach ( $updates as $key => $value ) {
            set_theme_mod( sanitize_key( (string) $key ), $value );
        }
        return $this->get_theme_mods( array( 'keys' => array_keys( $updates ) ) );
    }

    public function get_site_identity(): array {
        return array(
            'success' => true,
            'identity' => array(
                'title' => get_bloginfo( 'name' ),
                'tagline' => get_bloginfo( 'description' ),
                'logo_id' => (int) get_theme_mod( 'custom_logo', 0 ),
                'site_icon_id' => (int) get_option( 'site_icon', 0 ),
                'home_url' => home_url( '/' ),
                'admin_email' => get_option( 'admin_email' ),
            ),
        );
    }

    public function update_site_identity( array $input ): array {
        if ( isset( $input['title'] ) ) { update_option( 'blogname', sanitize_text_field( (string) $input['title'] ) ); }
        if ( isset( $input['tagline'] ) ) { update_option( 'blogdescription', sanitize_text_field( (string) $input['tagline'] ) ); }
        if ( isset( $input['logo_id'] ) ) { set_theme_mod( 'custom_logo', absint( $input['logo_id'] ) ); }
        if ( isset( $input['site_icon_id'] ) ) { update_option( 'site_icon', absint( $input['site_icon_id'] ) ); }
        return $this->get_site_identity();
    }

    public function query( array $input = array() ): array {
        $scope = isset( $input['scope'] ) ? sanitize_key( (string) $input['scope'] ) : 'all';
        $keys  = isset( $input['keys'] ) && is_array( $input['keys'] ) ? array_values( array_map( 'sanitize_text_field', $input['keys'] ) ) : array();
        $items = array();
        if ( 'all' === $scope || 'identity' === $scope ) {
            $identity = $this->get_site_identity()['identity'];
            $items[] = array( 'scope' => 'identity', 'item' => $identity );
        }
        if ( 'all' === $scope || 'theme_mods' === $scope ) {
            $mods = $this->get_theme_mods( array( 'keys' => $keys ) );
            $items[] = array( 'scope' => 'theme_mods', 'theme' => $mods['theme'], 'item' => $mods['mods'] );
        }
        return array(
            'summary' => array( 'scope' => $scope, 'returned' => count( $items ), 'theme' => wp_get_theme()->get_stylesheet() ),
            'items' => $items,
            'warnings' => empty( $items ) ? array( __( 'No se han encontrado datos de apariencia para ese scope.', 'wpgpt-mcp-bridge' ) ) : array(),
            'next_actions' => array(),
        );
    }

    public function inspect( array $input = array() ): array {
        $scope = isset( $input['scope'] ) ? sanitize_key( (string) $input['scope'] ) : 'identity';
        if ( 'theme_mods' === $scope ) {
            $mods = $this->get_theme_mods( array( 'keys' => $input['keys'] ?? array() ) );
            return array('summary'=>array('scope'=>'theme_mods','inspected'=>1),'items'=>array(array('scope'=>'theme_mods','theme'=>$mods['theme'],'mods'=>$mods['mods'],'available_actions'=>array('update_theme_mods'),'risk_level'=>'medium')),'warnings'=>array(),'next_actions'=>array(__( 'Usa wpgpt/appearance-apply con dry_run=true antes de ejecutar cambios.', 'wpgpt-mcp-bridge' )));
        }
        $identity = $this->get_site_identity()['identity'];
        return array('summary'=>array('scope'=>'identity','inspected'=>1),'items'=>array(array_merge(array('scope'=>'identity'),$identity,array('available_actions'=>array('update_identity'),'risk_level'=>'medium'))),'warnings'=>array(),'next_actions'=>array(__( 'Usa wpgpt/appearance-apply con dry_run=true antes de ejecutar cambios.', 'wpgpt-mcp-bridge' )));
    }

    public function apply( array $input = array() ): array|WP_Error {
        $action = isset( $input['action'] ) ? sanitize_key( (string) $input['action'] ) : '';
        $dry_run = ! empty( $input['dry_run'] );
        $payload = isset( $input['payload'] ) && is_array( $input['payload'] ) ? $input['payload'] : array();
        if ( ! in_array( $action, array( 'update_identity', 'update_theme_mods' ), true ) ) {
            return new WP_Error( 'wpgpt_appearance_action_invalid', __( 'La acción indicada no es válida.', 'wpgpt-mcp-bridge' ), array( 'status' => 400 ) );
        }
        if ( $dry_run ) {
            return array('summary'=>array('action'=>$action,'dry_run'=>true,'executed'=>0,'blocked'=>0),'items'=>array(array('status'=>'dry_run','action'=>$action,'payload_keys'=>array_keys($payload))),'warnings'=>array(),'blocked'=>array(),'next_actions'=>array(__( 'Repite la misma llamada con dry_run=false para aplicar los cambios validados.', 'wpgpt-mcp-bridge' )));
        }
        $result = 'update_identity' === $action ? $this->update_site_identity( $payload ) : $this->update_theme_mods( array( 'updates' => $payload['updates'] ?? array() ) );
        return array('summary'=>array('action'=>$action,'dry_run'=>false,'executed'=>1,'blocked'=>0),'items'=>array(array('status'=>'applied','action'=>$action,'result'=>$result)),'warnings'=>array(),'blocked'=>array(),'next_actions'=>array());
    }
}
