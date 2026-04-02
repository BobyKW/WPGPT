<?php

namespace WPGPT\MCPBridge\Plugins;

use WP_Ajax_Upgrader_Skin;
use WP_Error;

if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

class Plugin_Manager_Service {
    public function list_installed(): array {
        if ( ! function_exists( 'get_plugins' ) ) {
            require_once ABSPATH . 'wp-admin/includes/plugin.php';
        }
        $plugins = get_plugins();
        $active = (array) get_option( 'active_plugins', array() );
        $items = array();
        foreach ( $plugins as $file => $data ) {
            $items[] = array( 'plugin_file' => $file, 'name' => $data['Name'] ?? $file, 'version' => $data['Version'] ?? '', 'active' => in_array( $file, $active, true ) );
        }
        return array( 'count' => count( $items ), 'items' => $items );
    }

    public function get_plugin( string $plugin_file ): array|WP_Error {
        $plugin_file = $this->resolve_plugin_file( $plugin_file );
        if ( is_wp_error( $plugin_file ) ) { return $plugin_file; }
        if ( ! function_exists( 'get_plugins' ) ) { require_once ABSPATH . 'wp-admin/includes/plugin.php'; }
        $plugins = get_plugins();
        $data = $plugins[ $plugin_file ] ?? null;
        if ( ! is_array( $data ) ) {
            return new WP_Error( 'wpgpt_plugin_not_found', __( 'No se ha encontrado el plugin indicado.', 'wpgpt-mcp-bridge' ), array( 'status' => 404 ) );
        }
        return array( 'plugin_file' => $plugin_file, 'name' => $data['Name'] ?? '', 'version' => $data['Version'] ?? '', 'author' => $data['Author'] ?? '', 'requires' => $data['RequiresWP'] ?? '', 'requires_php' => $data['RequiresPHP'] ?? '', 'active' => is_plugin_active( $plugin_file ) );
    }

    public function update( string $plugin_file ): array|WP_Error {
        require_once ABSPATH . 'wp-admin/includes/class-wp-upgrader.php';
        require_once ABSPATH . 'wp-admin/includes/file.php';
        $plugin_file = $this->resolve_plugin_file( $plugin_file );
        if ( is_wp_error( $plugin_file ) ) { return $plugin_file; }
        $skin = new WP_Ajax_Upgrader_Skin();
        $upgrader = new \Plugin_Upgrader( $skin );
        $result = $upgrader->upgrade( $plugin_file );
        if ( is_wp_error( $result ) ) { return $result; }
        if ( false === $result ) {
            return new WP_Error( 'wpgpt_plugin_update_failed', __( 'No se pudo actualizar el plugin.', 'wpgpt-mcp-bridge' ), array( 'status' => 500 ) );
        }
        return array( 'updated' => true, 'plugin_file' => $plugin_file );
    }

    public function install( string $slug ): array|WP_Error {
        if ( '' === $slug ) {
            return new WP_Error( 'wpgpt_plugin_slug_required', __( 'Debes indicar el slug del plugin.', 'wpgpt-mcp-bridge' ), array( 'status' => 400 ) );
        }

        require_once ABSPATH . 'wp-admin/includes/plugin-install.php';
        require_once ABSPATH . 'wp-admin/includes/class-wp-upgrader.php';
        require_once ABSPATH . 'wp-admin/includes/file.php';
        require_once ABSPATH . 'wp-admin/includes/plugin.php';

        $api = plugins_api( 'plugin_information', array( 'slug' => $slug ) );
        if ( is_wp_error( $api ) ) {
            return $api;
        }

        $skin     = new WP_Ajax_Upgrader_Skin();
        $upgrader = new \Plugin_Upgrader( $skin );
        $result   = $upgrader->install( (string) ( $api->download_link ?? '' ) );
        if ( is_wp_error( $result ) ) {
            return $result;
        }
        if ( false === $result ) {
            return new WP_Error( 'wpgpt_plugin_install_failed', __( 'No se pudo instalar el plugin.', 'wpgpt-mcp-bridge' ), array( 'status' => 500 ) );
        }

        $plugin_file = $upgrader->plugin_info();

        return array(
            'installed'   => true,
            'slug'        => $slug,
            'plugin_file' => (string) $plugin_file,
        );
    }

    public function activate( string $plugin_file ): array|WP_Error {
        require_once ABSPATH . 'wp-admin/includes/plugin.php';
        $plugin_file = $this->resolve_plugin_file( $plugin_file );
        if ( is_wp_error( $plugin_file ) ) {
            return $plugin_file;
        }

        $result = activate_plugin( $plugin_file, '', false, false );
        if ( is_wp_error( $result ) ) {
            return $result;
        }

        return array(
            'activated'   => true,
            'plugin_file' => $plugin_file,
        );
    }

    public function deactivate( string $plugin_file ): array|WP_Error {
        require_once ABSPATH . 'wp-admin/includes/plugin.php';
        $plugin_file = $this->resolve_plugin_file( $plugin_file );
        if ( is_wp_error( $plugin_file ) ) {
            return $plugin_file;
        }

        deactivate_plugins( $plugin_file, false, false );

        return array(
            'deactivated' => true,
            'plugin_file' => $plugin_file,
        );
    }

    public function delete( string $plugin_file ): array|WP_Error {
        require_once ABSPATH . 'wp-admin/includes/plugin.php';
        require_once ABSPATH . 'wp-admin/includes/file.php';

        $plugin_file = $this->resolve_plugin_file( $plugin_file );
        if ( is_wp_error( $plugin_file ) ) {
            return $plugin_file;
        }

        if ( is_plugin_active( $plugin_file ) ) {
            return new WP_Error( 'wpgpt_plugin_active', __( 'Debes desactivar el plugin antes de eliminarlo.', 'wpgpt-mcp-bridge' ), array( 'status' => 409 ) );
        }

        $result = delete_plugins( array( $plugin_file ) );
        if ( is_wp_error( $result ) ) {
            return $result;
        }
        if ( false === $result ) {
            return new WP_Error( 'wpgpt_plugin_delete_failed', __( 'No se pudo eliminar el plugin indicado.', 'wpgpt-mcp-bridge' ), array( 'status' => 500 ) );
        }

        return array(
            'deleted'     => true,
            'plugin_file' => $plugin_file,
        );
    }

    private function resolve_plugin_file( string $plugin_file ): string|WP_Error {
        $plugin_file = trim( $plugin_file );
        if ( '' === $plugin_file ) {
            return new WP_Error( 'wpgpt_plugin_file_required', __( 'Debes indicar el plugin_file.', 'wpgpt-mcp-bridge' ), array( 'status' => 400 ) );
        }

        if ( ! function_exists( 'get_plugins' ) ) {
            require_once ABSPATH . 'wp-admin/includes/plugin.php';
        }

        $plugins = get_plugins();
        if ( isset( $plugins[ $plugin_file ] ) ) {
            return $plugin_file;
        }

        $slug = sanitize_key( $plugin_file );
        foreach ( array_keys( $plugins ) as $file ) {
            if ( 0 === strpos( $file, $slug . '/' ) ) {
                return $file;
            }
        }

        return new WP_Error( 'wpgpt_plugin_not_found', __( 'No se ha encontrado el plugin indicado.', 'wpgpt-mcp-bridge' ), array( 'status' => 404 ) );
    }
}
