<?php

namespace WPGPT\MCPBridge\Support;

use WPGPT\MCPBridge\Abilities;
use WPGPT\MCPBridge\Security;
use WP_Error;

if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

class Ability_Catalog {
    public static function declared(): array {
        $abilities = array();

        foreach ( Abilities::get_providers() as $provider ) {
            foreach ( $provider->get_abilities() as $name => $ability ) {
                $abilities[ $name ] = array_merge(
                    array(
                        'name'                => $name,
                        'label'               => '',
                        'description'         => '',
                        'category'            => 'wpgpt-readonly',
                        'show_in_rest'        => true,
                        'permission_callback' => null,
                        'input_schema'        => null,
                        'output_schema'       => null,
                        'meta'                => array(
                            'mcp' => array(
                                'public' => true,
                            ),
                        ),
                    ),
                    $ability
                );
            }
        }

        self::log_declared_abilities( $abilities );

        return $abilities;
    }

    /**
     * Log the number of declared abilities for debugging purposes.
     */
    private static function log_declared_abilities( array $abilities ): void {
        if ( defined( 'WP_DEBUG' ) && WP_DEBUG ) {
            error_log( sprintf( 'WPGPT MCP Bridge: %d abilities declared.', count( $abilities ) ) );
        }
    }

    public static function declared_names(): array {
        return array_keys( self::declared() );
    }

    public static function enabled_names(): array {
        return Security::get_allowed_abilities( self::declared_names() );
    }

    public static function runtime_registry(): array {
        return Abilities::get_raw_registry();
    }

    public static function visible_for_current_user(): array {
        $response = array();

        foreach ( self::declared() as $name => $ability ) {
            if ( ! self::is_enabled( $name ) ) {
                continue;
            }

            if ( ! self::is_allowed( $ability ) ) {
                continue;
            }

            $response[] = self::format_summary( $name, $ability );
        }

        return $response;
    }

    public static function find( string $name ): ?array {
        $declared = self::declared();
        return $declared[ $name ] ?? null;
    }

    public static function info( string $name ): array|WP_Error {
        $ability = self::find( $name );
        if ( ! is_array( $ability ) ) {
            return new WP_Error( 'wpgpt_ability_not_found', __( 'Ability no encontrada.', 'wpgpt-mcp-bridge' ), array( 'status' => 404 ) );
        }

        $runtime = self::runtime_registry();
        $runtime_ability = isset( $runtime[ $name ] ) && is_array( $runtime[ $name ] ) ? $runtime[ $name ] : null;

        return array(
            'name'                  => $name,
            'label'                 => $ability['label'] ?? '',
            'description'           => $ability['description'] ?? '',
            'category'              => $ability['category'] ?? '',
            'show_in_rest'          => (bool) ( $ability['show_in_rest'] ?? false ),
            'public_mcp'            => (bool) ( $ability['meta']['mcp']['public'] ?? false ),
            'enabled_in_admin'      => self::is_enabled( $name ),
            'has_input'             => ! empty( $ability['input_schema'] ),
            'has_output'            => ! empty( $ability['output_schema'] ),
            'registered_in_runtime' => null !== $runtime_ability,
            'permission_allowed'    => self::is_allowed( $ability ),
        );
    }

    public static function missing_from_runtime(): array {
        $declared = self::enabled_names();
        $runtime  = array_keys( self::runtime_registry() );

        return array_values( array_diff( $declared, $runtime ) );
    }

    public static function grouped_for_admin(): array {
        $groups   = array();
        $enabled  = array_flip( self::enabled_names() );

        foreach ( self::declared() as $name => $ability ) {
            $group = self::infer_admin_group( $name, $ability );
            $group_key = $group['key'];

            if ( ! isset( $groups[ $group_key ] ) ) {
                $groups[ $group_key ] = array(
                    'key'           => $group_key,
                    'label'         => $group['label'],
                    'description'   => $group['description'],
                    'enabled_count' => 0,
                    'total_count'   => 0,
                    'items'         => array(),
                );
            }

            $is_enabled = isset( $enabled[ $name ] );
            if ( $is_enabled ) {
                $groups[ $group_key ]['enabled_count']++;
            }
            $groups[ $group_key ]['total_count']++;
            $groups[ $group_key ]['items'][] = array(
                'name'        => $name,
                'label'       => (string) ( $ability['label'] ?? $name ),
                'description' => (string) ( $ability['description'] ?? '' ),
                'enabled'     => $is_enabled,
            );
        }

        uasort(
            $groups,
            static function ( array $a, array $b ): int {
                if ( 'danger' === $a['key'] && 'danger' !== $b['key'] ) {
                    return -1;
                }
                if ( 'danger' !== $a['key'] && 'danger' === $b['key'] ) {
                    return 1;
                }

                return strcmp( $a['label'], $b['label'] );
            }
        );

        foreach ( $groups as &$group ) {
            usort(
                $group['items'],
                static function ( array $a, array $b ): int {
                    return strcmp( $a['name'], $b['name'] );
                }
            );
        }

        return $groups;
    }

    private static function infer_admin_group( string $name, array $ability ): array {
        $slug = preg_replace( '#^[^/]+/#', '', $name );
        $slug = is_string( $slug ) ? $slug : $name;

        $map = array(
            'danger-'             => array( 'key' => 'danger', 'label' => __( 'Danger', 'wpgpt-mcp-bridge' ), 'description' => __( 'Filesystem, sandbox y ejecución PHP. Estas abilities pueden reemplazar muchas operaciones avanzadas y deben activarse con cuidado.', 'wpgpt-mcp-bridge' ) ),
            'acf-'                 => array( 'key' => 'acf', 'label' => __( 'ACF', 'wpgpt-mcp-bridge' ), 'description' => __( 'Campos y grupos de Advanced Custom Fields.', 'wpgpt-mcp-bridge' ) ),
            'block-'               => array( 'key' => 'block_editor', 'label' => __( 'Block Editor', 'wpgpt-mcp-bridge' ), 'description' => __( 'Plantillas, partes, bloques reutilizables y navegación moderna.', 'wpgpt-mcp-bridge' ) ),
            'blog-'                => array( 'key' => 'content', 'label' => __( 'Content', 'wpgpt-mcp-bridge' ), 'description' => __( 'Contenido editorial y publicación.', 'wpgpt-mcp-bridge' ) ),
            'post-'                => array( 'key' => 'content', 'label' => __( 'Content', 'wpgpt-mcp-bridge' ), 'description' => __( 'Contenido editorial y publicación.', 'wpgpt-mcp-bridge' ) ),
            'posts-'               => array( 'key' => 'content', 'label' => __( 'Content', 'wpgpt-mcp-bridge' ), 'description' => __( 'Contenido editorial y publicación.', 'wpgpt-mcp-bridge' ) ),
            'comment-'             => array( 'key' => 'comments', 'label' => __( 'Comments', 'wpgpt-mcp-bridge' ), 'description' => __( 'Consulta, edición y moderación de comentarios.', 'wpgpt-mcp-bridge' ) ),
            'db-'                  => array( 'key' => 'database', 'label' => __( 'Database', 'wpgpt-mcp-bridge' ), 'description' => __( 'Consultas seguras y exploración de tablas permitidas.', 'wpgpt-mcp-bridge' ) ),
            'diagnostics-'         => array( 'key' => 'diagnostics', 'label' => __( 'Diagnostics', 'wpgpt-mcp-bridge' ), 'description' => __( 'Diagnóstico técnico y muestreo del sitio.', 'wpgpt-mcp-bridge' ) ),
            'php-diagnostic'       => array( 'key' => 'diagnostics', 'label' => __( 'Diagnostics', 'wpgpt-mcp-bridge' ), 'description' => __( 'Diagnóstico técnico y muestreo del sitio.', 'wpgpt-mcp-bridge' ) ),
            'environment-'         => array( 'key' => 'environment', 'label' => __( 'Environment', 'wpgpt-mcp-bridge' ), 'description' => __( 'Estado técnico, auditoría y configuración del entorno.', 'wpgpt-mcp-bridge' ) ),
            'export-'              => array( 'key' => 'transfer', 'label' => __( 'Import / Export', 'wpgpt-mcp-bridge' ), 'description' => __( 'Transferencia de datos y previsualización de importaciones.', 'wpgpt-mcp-bridge' ) ),
            'import-'              => array( 'key' => 'transfer', 'label' => __( 'Import / Export', 'wpgpt-mcp-bridge' ), 'description' => __( 'Transferencia de datos y previsualización de importaciones.', 'wpgpt-mcp-bridge' ) ),
            'fs-'                  => array( 'key' => 'filesystem', 'label' => __( 'Filesystem', 'wpgpt-mcp-bridge' ), 'description' => __( 'Lectura, escritura, backups y operaciones de archivos.', 'wpgpt-mcp-bridge' ) ),
            'jetengine-'           => array( 'key' => 'integrations', 'label' => __( 'Integrations', 'wpgpt-mcp-bridge' ), 'description' => __( 'Integraciones con plugins y servicios concretos.', 'wpgpt-mcp-bridge' ) ),
            'maintenance-'         => array( 'key' => 'maintenance', 'label' => __( 'Maintenance', 'wpgpt-mcp-bridge' ), 'description' => __( 'Cache, transients, regeneración y mantenimiento técnico.', 'wpgpt-mcp-bridge' ) ),
            'media-'               => array( 'key' => 'media', 'label' => __( 'Media', 'wpgpt-mcp-bridge' ), 'description' => __( 'Biblioteca multimedia, sideload y archivos adjuntos.', 'wpgpt-mcp-bridge' ) ),
            'menu-'                => array( 'key' => 'navigation', 'label' => __( 'Navigation', 'wpgpt-mcp-bridge' ), 'description' => __( 'Menús, items y ubicaciones de navegación.', 'wpgpt-mcp-bridge' ) ),
            'nav-location-'        => array( 'key' => 'navigation', 'label' => __( 'Navigation', 'wpgpt-mcp-bridge' ), 'description' => __( 'Menús, items y ubicaciones de navegación.', 'wpgpt-mcp-bridge' ) ),
            'option'               => array( 'key' => 'settings', 'label' => __( 'Settings', 'wpgpt-mcp-bridge' ), 'description' => __( 'Opciones, ajustes del sitio y apariencia base.', 'wpgpt-mcp-bridge' ) ),
            'general-settings-'    => array( 'key' => 'settings', 'label' => __( 'Settings', 'wpgpt-mcp-bridge' ), 'description' => __( 'Opciones, ajustes del sitio y apariencia base.', 'wpgpt-mcp-bridge' ) ),
            'discussion-settings-' => array( 'key' => 'settings', 'label' => __( 'Settings', 'wpgpt-mcp-bridge' ), 'description' => __( 'Opciones, ajustes del sitio y apariencia base.', 'wpgpt-mcp-bridge' ) ),
            'writing-settings-'    => array( 'key' => 'settings', 'label' => __( 'Settings', 'wpgpt-mcp-bridge' ), 'description' => __( 'Opciones, ajustes del sitio y apariencia base.', 'wpgpt-mcp-bridge' ) ),
            'reading-settings-'    => array( 'key' => 'settings', 'label' => __( 'Settings', 'wpgpt-mcp-bridge' ), 'description' => __( 'Opciones, ajustes del sitio y apariencia base.', 'wpgpt-mcp-bridge' ) ),
            'permalink-settings-'  => array( 'key' => 'settings', 'label' => __( 'Settings', 'wpgpt-mcp-bridge' ), 'description' => __( 'Opciones, ajustes del sitio y apariencia base.', 'wpgpt-mcp-bridge' ) ),
            'privacy-page-'        => array( 'key' => 'settings', 'label' => __( 'Settings', 'wpgpt-mcp-bridge' ), 'description' => __( 'Opciones, ajustes del sitio y apariencia base.', 'wpgpt-mcp-bridge' ) ),
            'homepage-'            => array( 'key' => 'settings', 'label' => __( 'Settings', 'wpgpt-mcp-bridge' ), 'description' => __( 'Opciones, ajustes del sitio y apariencia base.', 'wpgpt-mcp-bridge' ) ),
            'theme-'               => array( 'key' => 'appearance', 'label' => __( 'Appearance', 'wpgpt-mcp-bridge' ), 'description' => __( 'Temas, identidad visual y ajustes del tema.', 'wpgpt-mcp-bridge' ) ),
            'site-identity-'       => array( 'key' => 'appearance', 'label' => __( 'Appearance', 'wpgpt-mcp-bridge' ), 'description' => __( 'Temas, identidad visual y ajustes del tema.', 'wpgpt-mcp-bridge' ) ),
            'plugin-'              => array( 'key' => 'plugins', 'label' => __( 'Plugins', 'wpgpt-mcp-bridge' ), 'description' => __( 'Plugins instalados, activación y operaciones del repositorio.', 'wpgpt-mcp-bridge' ) ),
            'plugins-'             => array( 'key' => 'plugins', 'label' => __( 'Plugins', 'wpgpt-mcp-bridge' ), 'description' => __( 'Plugins instalados, activación y operaciones del repositorio.', 'wpgpt-mcp-bridge' ) ),
            'rest-routes-'         => array( 'key' => 'runtime', 'label' => __( 'Runtime & System', 'wpgpt-mcp-bridge' ), 'description' => __( 'Sistema, abilities, rutas REST, hooks y eventos.', 'wpgpt-mcp-bridge' ) ),
            'shortcodes-'          => array( 'key' => 'runtime', 'label' => __( 'Runtime & System', 'wpgpt-mcp-bridge' ), 'description' => __( 'Sistema, abilities, rutas REST, hooks y eventos.', 'wpgpt-mcp-bridge' ) ),
            'hooks-'               => array( 'key' => 'runtime', 'label' => __( 'Runtime & System', 'wpgpt-mcp-bridge' ), 'description' => __( 'Sistema, abilities, rutas REST, hooks y eventos.', 'wpgpt-mcp-bridge' ) ),
            'cron-events-'         => array( 'key' => 'runtime', 'label' => __( 'Runtime & System', 'wpgpt-mcp-bridge' ), 'description' => __( 'Sistema, abilities, rutas REST, hooks y eventos.', 'wpgpt-mcp-bridge' ) ),
            'self-test'            => array( 'key' => 'runtime', 'label' => __( 'Runtime & System', 'wpgpt-mcp-bridge' ), 'description' => __( 'Sistema, abilities, rutas REST, hooks y eventos.', 'wpgpt-mcp-bridge' ) ),
            'site-info'            => array( 'key' => 'runtime', 'label' => __( 'Runtime & System', 'wpgpt-mcp-bridge' ), 'description' => __( 'Sistema, abilities, rutas REST, hooks y eventos.', 'wpgpt-mcp-bridge' ) ),
            'discover-abilities'   => array( 'key' => 'runtime', 'label' => __( 'Runtime & System', 'wpgpt-mcp-bridge' ), 'description' => __( 'Sistema, abilities, rutas REST, hooks y eventos.', 'wpgpt-mcp-bridge' ) ),
            'ability-info'         => array( 'key' => 'runtime', 'label' => __( 'Runtime & System', 'wpgpt-mcp-bridge' ), 'description' => __( 'Sistema, abilities, rutas REST, hooks y eventos.', 'wpgpt-mcp-bridge' ) ),
            'seo-'                 => array( 'key' => 'seo', 'label' => __( 'SEO', 'wpgpt-mcp-bridge' ), 'description' => __( 'Metadatos, ajustes y estado SEO.', 'wpgpt-mcp-bridge' ) ),
            'cpt-'                 => array( 'key' => 'structure', 'label' => __( 'Structure', 'wpgpt-mcp-bridge' ), 'description' => __( 'Post types, taxonomías y metaboxes.', 'wpgpt-mcp-bridge' ) ),
            'taxonomy-'            => array( 'key' => 'structure', 'label' => __( 'Structure', 'wpgpt-mcp-bridge' ), 'description' => __( 'Post types, taxonomías y metaboxes.', 'wpgpt-mcp-bridge' ) ),
            'metabox-'             => array( 'key' => 'structure', 'label' => __( 'Structure', 'wpgpt-mcp-bridge' ), 'description' => __( 'Post types, taxonomías y metaboxes.', 'wpgpt-mcp-bridge' ) ),
            'term-'                => array( 'key' => 'taxonomy', 'label' => __( 'Taxonomies & Terms', 'wpgpt-mcp-bridge' ), 'description' => __( 'Términos, asignaciones y gestión editorial.', 'wpgpt-mcp-bridge' ) ),
            'user-'                => array( 'key' => 'users', 'label' => __( 'Users & Roles', 'wpgpt-mcp-bridge' ), 'description' => __( 'Usuarios, roles y capacidades.', 'wpgpt-mcp-bridge' ) ),
            'role-'                => array( 'key' => 'users', 'label' => __( 'Users & Roles', 'wpgpt-mcp-bridge' ), 'description' => __( 'Usuarios, roles y capacidades.', 'wpgpt-mcp-bridge' ) ),
            'capability-'          => array( 'key' => 'users', 'label' => __( 'Users & Roles', 'wpgpt-mcp-bridge' ), 'description' => __( 'Usuarios, roles y capacidades.', 'wpgpt-mcp-bridge' ) ),
            'wc-'                  => array( 'key' => 'woocommerce', 'label' => __( 'WooCommerce', 'wpgpt-mcp-bridge' ), 'description' => __( 'Productos, pedidos, clientes y reportes ecommerce.', 'wpgpt-mcp-bridge' ) ),
        );

        foreach ( $map as $prefix => $group ) {
            if ( str_starts_with( $slug, $prefix ) ) {
                return $group;
            }
        }

        $category = (string) ( $ability['category'] ?? 'other' );

        if ( 'peligro' === $category || 'danger' === $category ) {
            return array(
                'key'         => 'danger',
                'label'       => __( 'Danger', 'wpgpt-mcp-bridge' ),
                'description' => __( 'Filesystem, sandbox y ejecución PHP. Estas abilities pueden reemplazar muchas operaciones avanzadas y deben activarse con cuidado.', 'wpgpt-mcp-bridge' ),
            );
        }

        return array(
            'key'         => $category,
            'label'       => __( 'Other', 'wpgpt-mcp-bridge' ),
            'description' => __( 'Abilities agrupadas sin categoría específica.', 'wpgpt-mcp-bridge' ),
        );
    }

    private static function is_enabled( string $name ): bool {
        $ability = self::find( $name );

        if ( ! Security::is_ability_enabled( $name, self::declared_names() ) ) {
            return false;
        }

        return Security::is_ability_exposed_by_policy( $name, is_array( $ability ) ? $ability : array() );
    }

    private static function is_allowed( array $ability ): bool {
        if ( ! isset( $ability['permission_callback'] ) || ! is_callable( $ability['permission_callback'] ) ) {
            return true;
        }

        $permission = call_user_func( $ability['permission_callback'] );
        return true === $permission;
    }

    private static function format_summary( string $name, array $ability ): array {
        $runtime = self::runtime_registry();
        return array(
            'name'                  => $name,
            'label'                 => $ability['label'] ?? '',
            'description'           => $ability['description'] ?? '',
            'category'              => $ability['category'] ?? '',
            'public_mcp'            => (bool) ( $ability['meta']['mcp']['public'] ?? false ),
            'enabled_in_admin'      => self::is_enabled( $name ),
            'registered_in_runtime' => isset( $runtime[ $name ] ) && is_array( $runtime[ $name ] ),
        );
    }
}
