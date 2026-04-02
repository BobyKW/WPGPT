<?php

namespace WPGPT\MCPBridge;

use WP_Error;

if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

abstract class Base_Ability_Provider implements Ability_Provider {
    protected const CATEGORY = 'wpgpt-readonly';

    public function register(): void {
        if ( ! function_exists( 'wp_register_ability' ) ) {
            return;
        }

        $abilities = $this->get_abilities();

        foreach ( $abilities as $name => $ability ) {
            if ( ! Security::is_ability_enabled( (string) $name ) ) {
                continue;
            }

            if ( ! Security::is_ability_exposed_by_policy( (string) $name, is_array( $ability ) ? $ability : array() ) ) {
                continue;
            }

            $defaults = array(
                'category'            => self::CATEGORY,
                'permission_callback' => array( $this, 'can_manage_site' ),
                'show_in_rest'        => true,
                'meta'                => array(
                    'mcp' => array(
                        'public' => true,
                    ),
                ),
            );

            wp_register_ability( $name, array_merge( $defaults, $ability ) );
        }
    }

    public function can_manage_site(): bool|WP_Error {
        if ( current_user_can( 'manage_options' ) ) {
            return true;
        }

        return new WP_Error( 'wpgpt_forbidden', __( 'No tienes permisos para usar esta herramienta.', 'wpgpt-mcp-bridge' ), array( 'status' => 403 ) );
    }

    public function can_edit_content(): bool|WP_Error {
        if ( current_user_can( 'edit_posts' ) ) {
            return true;
        }

        return new WP_Error( 'wpgpt_forbidden', __( 'No tienes permisos para consultar contenido.', 'wpgpt-mcp-bridge' ), array( 'status' => 403 ) );
    }

    public function can_write_content(): bool|WP_Error {
        if ( Security::get_read_only() ) {
            return new WP_Error( 'wpgpt_read_only', __( 'El plugin está en modo solo lectura.', 'wpgpt-mcp-bridge' ), array( 'status' => 403 ) );
        }

        if ( current_user_can( 'edit_posts' ) ) {
            return true;
        }

        return new WP_Error( 'wpgpt_forbidden', __( 'No tienes permisos para modificar contenido.', 'wpgpt-mcp-bridge' ), array( 'status' => 403 ) );
    }

    public function can_delete_content(): bool|WP_Error {
        if ( Security::get_read_only() ) {
            return new WP_Error( 'wpgpt_read_only', __( 'El plugin está en modo solo lectura.', 'wpgpt-mcp-bridge' ), array( 'status' => 403 ) );
        }

        if ( ! Security::get_allow_delete() ) {
            return new WP_Error( 'wpgpt_delete_disabled', __( 'Las operaciones de eliminación están desactivadas en los ajustes del plugin.', 'wpgpt-mcp-bridge' ), array( 'status' => 403 ) );
        }

        if ( current_user_can( 'delete_posts' ) || current_user_can( 'delete_pages' ) ) {
            return true;
        }

        return new WP_Error( 'wpgpt_forbidden', __( 'No tienes permisos para eliminar contenido.', 'wpgpt-mcp-bridge' ), array( 'status' => 403 ) );
    }

    public function can_write_structure(): bool|WP_Error {
        if ( Security::get_read_only() ) {
            return new WP_Error( 'wpgpt_read_only', __( 'El plugin está en modo solo lectura.', 'wpgpt-mcp-bridge' ), array( 'status' => 403 ) );
        }

        return $this->can_manage_site();
    }

    public function can_delete_structure(): bool|WP_Error {
        if ( Security::get_read_only() ) {
            return new WP_Error( 'wpgpt_read_only', __( 'El plugin está en modo solo lectura.', 'wpgpt-mcp-bridge' ), array( 'status' => 403 ) );
        }

        if ( ! Security::get_allow_delete() ) {
            return new WP_Error( 'wpgpt_delete_disabled', __( 'Las operaciones de eliminación están desactivadas en los ajustes del plugin.', 'wpgpt-mcp-bridge' ), array( 'status' => 403 ) );
        }

        return $this->can_manage_site();
    }

    public function can_write_plugins(): bool|WP_Error {
        if ( Security::get_read_only() ) {
            return new WP_Error( 'wpgpt_read_only', __( 'El plugin está en modo solo lectura.', 'wpgpt-mcp-bridge' ), array( 'status' => 403 ) );
        }

        if ( current_user_can( 'activate_plugins' ) || current_user_can( 'install_plugins' ) ) {
            return true;
        }

        return new WP_Error( 'wpgpt_forbidden', __( 'No tienes permisos para gestionar plugins.', 'wpgpt-mcp-bridge' ), array( 'status' => 403 ) );
    }

    public function can_delete_plugins(): bool|WP_Error {
        if ( Security::get_read_only() ) {
            return new WP_Error( 'wpgpt_read_only', __( 'El plugin está en modo solo lectura.', 'wpgpt-mcp-bridge' ), array( 'status' => 403 ) );
        }

        if ( ! Security::get_allow_delete() ) {
            return new WP_Error( 'wpgpt_delete_disabled', __( 'Las operaciones de eliminación están desactivadas en los ajustes del plugin.', 'wpgpt-mcp-bridge' ), array( 'status' => 403 ) );
        }

        if ( current_user_can( 'delete_plugins' ) ) {
            return true;
        }

        return new WP_Error( 'wpgpt_forbidden', __( 'No tienes permisos para eliminar plugins.', 'wpgpt-mcp-bridge' ), array( 'status' => 403 ) );
    }


    public function can_read_files(): bool|WP_Error {
        if ( ! Security::get_fs_read() ) {
            return new WP_Error( 'wpgpt_fs_read_disabled', __( 'La lectura de archivos está desactivada en los ajustes del plugin.', 'wpgpt-mcp-bridge' ), array( 'status' => 403 ) );
        }

        return $this->can_manage_site();
    }

    public function can_write_files(): bool|WP_Error {
        if ( Security::get_read_only() ) {
            return new WP_Error( 'wpgpt_read_only', __( 'El plugin está en modo solo lectura.', 'wpgpt-mcp-bridge' ), array( 'status' => 403 ) );
        }

        if ( ! Security::get_fs_write() ) {
            return new WP_Error( 'wpgpt_fs_write_disabled', __( 'La escritura de archivos está desactivada en los ajustes del plugin.', 'wpgpt-mcp-bridge' ), array( 'status' => 403 ) );
        }

        return $this->can_manage_site();
    }

    public function can_delete_files(): bool|WP_Error {
        if ( Security::get_read_only() ) {
            return new WP_Error( 'wpgpt_read_only', __( 'El plugin está en modo solo lectura.', 'wpgpt-mcp-bridge' ), array( 'status' => 403 ) );
        }

        if ( ! Security::get_fs_delete() ) {
            return new WP_Error( 'wpgpt_fs_delete_disabled', __( 'El borrado de archivos está desactivado en los ajustes del plugin.', 'wpgpt-mcp-bridge' ), array( 'status' => 403 ) );
        }

        return $this->can_manage_site();
    }

    protected function object_schema(): array {
        return array( 'type' => 'object', 'additionalProperties' => true );
    }
}
