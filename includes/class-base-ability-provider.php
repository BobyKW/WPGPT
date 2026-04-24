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

            $ability = $this->normalize_ability_for_mcp_adapter( array_merge( $defaults, $ability ) );

            wp_register_ability( $name, $ability );
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


    /**
     * Normalize ability schemas for MCP clients.
     *
     * Some MCP clients use typed protocol DTOs and validate tool schemas more
     * strictly. In PHP, an empty array may be encoded as [] even when JSON Schema
     * expects an object such as properties: {}. This method keeps existing ability
     * definitions compatible by normalizing empty schema nodes before registration.
     */
    protected function normalize_ability_for_mcp_adapter( array $ability ): array {
        if ( isset( $ability['input_schema'] ) && is_array( $ability['input_schema'] ) ) {
            $ability['input_schema'] = $this->normalize_json_schema( $ability['input_schema'] );
        }

        if ( isset( $ability['output_schema'] ) && is_array( $ability['output_schema'] ) ) {
            $ability['output_schema'] = $this->normalize_json_schema( $ability['output_schema'] );
        }

        return $ability;
    }

    /**
     * Normalize a JSON Schema fragment so it serializes as valid JSON Schema.
     *
     * @param mixed  $schema  Schema fragment.
     * @param string $context Current schema context.
     * @return mixed
     */
    protected function normalize_json_schema( $schema, string $context = 'schema' ) {
        if ( ! is_array( $schema ) ) {
            return $schema;
        }

        if ( array() === $schema ) {
            if ( 'properties' === $context ) {
                return (object) array();
            }

            return $this->mixed_json_schema();
        }

        if ( 'properties' === $context ) {
            $normalized_properties = array();
            foreach ( $schema as $property_name => $property_schema ) {
                $normalized_properties[ $property_name ] = $this->normalize_json_schema( $property_schema, 'property' );
            }

            return empty( $normalized_properties ) ? (object) array() : $normalized_properties;
        }

        $normalized = array();
        foreach ( $schema as $key => $value ) {
            switch ( $key ) {
                case 'properties':
                    $normalized[ $key ] = $this->normalize_json_schema( $value, 'properties' );
                    break;

                case 'items':
                    if ( is_array( $value ) && array() === $value ) {
                        $normalized[ $key ] = $this->mixed_json_schema();
                    } else {
                        $normalized[ $key ] = $this->normalize_json_schema( $value, 'schema' );
                    }
                    break;

                case 'anyOf':
                case 'oneOf':
                case 'allOf':
                    $items = array();
                    foreach ( (array) $value as $candidate ) {
                        $items[] = $this->normalize_json_schema( $candidate, 'schema' );
                    }
                    $normalized[ $key ] = $items;
                    break;

                case 'additionalProperties':
                    if ( is_bool( $value ) ) {
                        $normalized[ $key ] = $value;
                    } elseif ( is_array( $value ) && array() === $value ) {
                        $normalized[ $key ] = true;
                    } else {
                        $normalized[ $key ] = $this->normalize_json_schema( $value, 'schema' );
                    }
                    break;

                case 'enum':
                case 'required':
                case 'type':
                case 'description':
                case 'format':
                case 'default':
                case 'minimum':
                case 'maximum':
                case 'minLength':
                case 'maxLength':
                case 'minItems':
                case 'maxItems':
                    $normalized[ $key ] = $value;
                    break;

                default:
                    $normalized[ $key ] = is_array( $value ) ? $this->normalize_json_schema( $value, 'schema' ) : $value;
                    break;
            }
        }

        if ( isset( $normalized['properties'] ) && ! isset( $normalized['type'] ) && ! isset( $normalized['anyOf'] ) && ! isset( $normalized['oneOf'] ) && ! isset( $normalized['allOf'] ) ) {
            $normalized = array_merge( array( 'type' => 'object' ), $normalized );
        }

        return $normalized;
    }

    protected function mixed_json_schema(): array {
        return array(
            'anyOf' => array(
                array( 'type' => 'string' ),
                array( 'type' => 'number' ),
                array( 'type' => 'integer' ),
                array( 'type' => 'boolean' ),
                array( 'type' => 'object', 'additionalProperties' => true ),
                array( 'type' => 'array', 'items' => array( 'type' => 'string' ) ),
                array( 'type' => 'null' ),
            ),
        );
    }

    protected function object_schema(): array {
        return array( 'type' => 'object', 'additionalProperties' => true );
    }
}
