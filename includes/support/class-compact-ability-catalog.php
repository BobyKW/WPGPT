<?php

namespace WPGPT\MCPBridge\Support;

if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

/**
 * Builds compact MCP-facing ability groups from the detailed WPGPT abilities.
 *
 * Detailed abilities remain the internal security boundary. Compact abilities are
 * only a discovery/execution facade to reduce MCP handshake size and avoid clients
 * inspecting dozens of near-identical query/inspect/apply tools.
 */
class Compact_Ability_Catalog {
    public static function is_compact_name( string $name ): bool {
        if ( 0 !== strpos( $name, 'wpgpt/' ) ) {
            return false;
        }

        $slug = substr( $name, 6 );
        return '' !== $slug && false === strpos( $slug, '-' );
    }

    /**
     * @param array<int,array{name:string,label?:string,description?:string}> $abilities
     * @return array<string,array<string,mixed>>
     */
    public static function build_groups( array $abilities ): array {
        $groups = array();

        foreach ( $abilities as $ability ) {
            $name = isset( $ability['name'] ) ? (string) $ability['name'] : '';
            if ( '' === $name || 0 !== strpos( $name, 'wpgpt/' ) ) {
                continue;
            }

            $mapping = self::mapping_for_detailed_name( $name );
            if ( null === $mapping ) {
                // Keep uncommon single abilities visible as-is rather than hiding them.
                $groups[ $name ] = array(
                    'name'                 => $name,
                    'label'                => isset( $ability['label'] ) ? (string) $ability['label'] : self::human_label( substr( $name, 6 ) ),
                    'description'          => isset( $ability['description'] ) ? (string) $ability['description'] : '',
                    'actions'              => array(),
                    'action_map'           => array(),
                    'underlying_abilities' => array( $name ),
                    'single'               => true,
                );
                continue;
            }

            $compact_name = $mapping['compact_name'];
            if ( ! isset( $groups[ $compact_name ] ) ) {
                $groups[ $compact_name ] = array(
                    'name'                 => $compact_name,
                    'label'                => self::group_label( $mapping['group'] ),
                    'description'          => self::group_description( $mapping['group'] ),
                    'actions'              => array(),
                    'action_map'           => array(),
                    'underlying_abilities' => array(),
                    'single'               => false,
                );
            }

            $groups[ $compact_name ]['actions'][] = $mapping['action'];
            $groups[ $compact_name ]['action_map'][ $mapping['action'] ] = $name;
            $groups[ $compact_name ]['underlying_abilities'][] = $name;
        }

        foreach ( $groups as &$group ) {
            $group['actions'] = array_values( array_unique( $group['actions'] ) );
            usort( $group['actions'], array( __CLASS__, 'sort_actions' ) );
            $group['underlying_abilities'] = array_values( array_unique( $group['underlying_abilities'] ) );
        }
        unset( $group );

        uasort(
            $groups,
            static function ( array $a, array $b ): int {
                if ( 'wpgpt/danger' === $a['name'] && 'wpgpt/danger' !== $b['name'] ) {
                    return -1;
                }
                if ( 'wpgpt/danger' !== $a['name'] && 'wpgpt/danger' === $b['name'] ) {
                    return 1;
                }
                return strcmp( (string) $a['name'], (string) $b['name'] );
            }
        );

        return $groups;
    }

    public static function mapping_for_detailed_name( string $name ): ?array {
        if ( 0 !== strpos( $name, 'wpgpt/' ) ) {
            return null;
        }

        $slug = substr( $name, 6 );

        $danger_actions = array(
            'danger-list-directory' => 'list_directory',
            'danger-read-file'      => 'read_file',
            'danger-write-file'     => 'write_file',
            'danger-edit-file'      => 'edit_file',
            'danger-delete-file'    => 'delete_file',
            'danger-execute-php'    => 'execute_php',
            'danger-disable-file'   => 'disable_file',
            'danger-enable-file'    => 'enable_file',
        );

        if ( isset( $danger_actions[ $slug ] ) ) {
            return array(
                'compact_name' => 'wpgpt/danger',
                'group'        => 'danger',
                'action'       => $danger_actions[ $slug ],
            );
        }

        foreach ( array( 'query', 'inspect', 'apply' ) as $action ) {
            $suffix = '-' . $action;
            if ( str_ends_with( $slug, $suffix ) ) {
                $group = substr( $slug, 0, - strlen( $suffix ) );
                if ( '' === $group ) {
                    return null;
                }

                return array(
                    'compact_name' => 'wpgpt/' . $group,
                    'group'        => $group,
                    'action'       => $action,
                );
            }
        }

        return null;
    }

    public static function input_schema_for_group( array $group ): array {
        $actions = isset( $group['actions'] ) && is_array( $group['actions'] ) ? array_values( $group['actions'] ) : array();

        return array(
            'type'                 => 'object',
            'additionalProperties' => false,
            'properties'           => array(
                'action'     => array(
                    'type'        => 'string',
                    'enum'        => $actions,
                    'description' => __( 'Acción interna permitida para este módulo.', 'wpgpt-mcp-bridge' ),
                ),
                'parameters' => array(
                    'type'                 => 'object',
                    'additionalProperties' => true,
                    'description'          => __( 'Parámetros de la acción interna. Usa get_ability_info sobre la ability detallada indicada en action_map si necesitas el schema completo.', 'wpgpt-mcp-bridge' ),
                ),
            ),
            'required'             => array( 'action' ),
        );
    }

    public static function output_schema_for_group(): array {
        return array( 'type' => 'object', 'additionalProperties' => true );
    }

    public static function action_description( string $action ): string {
        $map = array(
            'query'          => __( 'Listar, filtrar o resumir recursos.', 'wpgpt-mcp-bridge' ),
            'inspect'        => __( 'Inspeccionar recursos concretos con más detalle.', 'wpgpt-mcp-bridge' ),
            'apply'          => __( 'Ejecutar cambios controlados. Usa dry_run=true cuando esté disponible.', 'wpgpt-mcp-bridge' ),
            'list_directory' => __( 'Listar directorios.', 'wpgpt-mcp-bridge' ),
            'read_file'      => __( 'Leer archivos.', 'wpgpt-mcp-bridge' ),
            'write_file'     => __( 'Crear, sobrescribir o añadir contenido a archivos.', 'wpgpt-mcp-bridge' ),
            'edit_file'      => __( 'Editar archivos reemplazando texto exacto.', 'wpgpt-mcp-bridge' ),
            'delete_file'    => __( 'Borrar archivos o carpetas permitidas.', 'wpgpt-mcp-bridge' ),
            'execute_php'    => __( 'Ejecutar PHP arbitrario en contexto WordPress. Máximo riesgo.', 'wpgpt-mcp-bridge' ),
            'disable_file'   => __( 'Desactivar un PHP del sandbox añadiendo .disabled.', 'wpgpt-mcp-bridge' ),
            'enable_file'    => __( 'Reactivar un PHP del sandbox quitando .disabled.', 'wpgpt-mcp-bridge' ),
        );

        return $map[ $action ] ?? $action;
    }

    public static function group_label( string $group ): string {
        $map = array(
            'acf'               => 'ACF',
            'appearance'        => __( 'Appearance', 'wpgpt-mcp-bridge' ),
            'blocks'            => __( 'Blocks', 'wpgpt-mcp-bridge' ),
            'code'              => __( 'Code', 'wpgpt-mcp-bridge' ),
            'comments'          => __( 'Comments', 'wpgpt-mcp-bridge' ),
            'database'          => __( 'Database', 'wpgpt-mcp-bridge' ),
            'danger'            => __( 'Danger', 'wpgpt-mcp-bridge' ),
            'diagnostics'       => __( 'Diagnostics', 'wpgpt-mcp-bridge' ),
            'discussion'        => __( 'Discussion', 'wpgpt-mcp-bridge' ),
            'drafts'            => __( 'Drafts', 'wpgpt-mcp-bridge' ),
            'environment'       => __( 'Environment', 'wpgpt-mcp-bridge' ),
            'filesystem'        => __( 'Filesystem', 'wpgpt-mcp-bridge' ),
            'jetengine'         => 'JetEngine',
            'maintenance'       => __( 'Maintenance', 'wpgpt-mcp-bridge' ),
            'media'             => __( 'Media', 'wpgpt-mcp-bridge' ),
            'media-audits'      => __( 'Media Audits', 'wpgpt-mcp-bridge' ),
            'navigation'        => __( 'Navigation', 'wpgpt-mcp-bridge' ),
            'options'           => __( 'Options', 'wpgpt-mcp-bridge' ),
            'plugin-repository' => __( 'Plugin Repository', 'wpgpt-mcp-bridge' ),
            'plugins'           => __( 'Plugins', 'wpgpt-mcp-bridge' ),
            'posts'             => __( 'Posts', 'wpgpt-mcp-bridge' ),
            'roles'             => __( 'Roles', 'wpgpt-mcp-bridge' ),
            'runtime'           => __( 'Runtime', 'wpgpt-mcp-bridge' ),
            'seo'               => 'SEO',
            'settings'          => __( 'Settings', 'wpgpt-mcp-bridge' ),
            'structure'         => __( 'Structure', 'wpgpt-mcp-bridge' ),
            'system'            => __( 'System', 'wpgpt-mcp-bridge' ),
            'terms'             => __( 'Terms', 'wpgpt-mcp-bridge' ),
            'themes'            => __( 'Themes', 'wpgpt-mcp-bridge' ),
            'transfer'          => __( 'Transfer', 'wpgpt-mcp-bridge' ),
            'users'             => __( 'Users', 'wpgpt-mcp-bridge' ),
            'wc'                => 'WooCommerce',
        );

        return $map[ $group ] ?? self::human_label( $group );
    }

    public static function group_description( string $group ): string {
        if ( 'danger' === $group ) {
            return __( 'Filesystem, sandbox y ejecución PHP agrupados en una sola ability compacta. Las acciones internas siguen respetando permisos, modo seguro y allowlist.', 'wpgpt-mcp-bridge' );
        }

        return sprintf(
            /* translators: %s: module name. */
            __( 'Ability compacta para %s. Usa action=query, inspect o apply según las acciones disponibles.', 'wpgpt-mcp-bridge' ),
            self::group_label( $group )
        );
    }

    private static function human_label( string $slug ): string {
        $label = str_replace( array( '-', '_' ), ' ', $slug );
        return ucwords( $label );
    }

    private static function sort_actions( string $a, string $b ): int {
        $order = array(
            'query' => 10,
            'inspect' => 20,
            'apply' => 30,
            'list_directory' => 10,
            'read_file' => 20,
            'write_file' => 30,
            'edit_file' => 40,
            'disable_file' => 50,
            'enable_file' => 60,
            'execute_php' => 70,
            'delete_file' => 80,
        );

        return ( $order[ $a ] ?? 999 ) <=> ( $order[ $b ] ?? 999 ) ?: strcmp( $a, $b );
    }
}
