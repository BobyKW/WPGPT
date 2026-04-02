<?php

namespace WPGPT\MCPBridge\Appearance;

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
}
