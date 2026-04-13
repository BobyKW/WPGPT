<?php

namespace WPGPT\MCPBridge\Plugins;

use WPGPT\MCPBridge\Security;
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

    public function query( array $input = array() ): array|WP_Error {
        $filters = isset( $input['filters'] ) && is_array( $input['filters'] ) ? $input['filters'] : array();
        $search  = isset( $input['search'] ) ? sanitize_text_field( (string) $input['search'] ) : '';
        $limit   = isset( $input['limit'] ) ? max( 1, min( 200, (int) $input['limit'] ) ) : 100;
        $offset  = isset( $input['offset'] ) ? max( 0, (int) $input['offset'] ) : 0;

        if ( '' === $search && isset( $filters['search'] ) ) {
            $search = sanitize_text_field( (string) $filters['search'] );
        }

        $items        = $this->build_plugin_inventory();
        $warnings     = array();
        $next_actions = array();
        $matched      = array();

        foreach ( $items as $item ) {
            if ( ! $this->plugin_matches_filters( $item, $filters, $search ) ) {
                continue;
            }
            $matched[] = $item;
        }

        $total_matches = count( $matched );
        $paged_items   = array_slice( $matched, $offset, $limit );

        if ( $total_matches > ( $offset + count( $paged_items ) ) ) {
            $next_actions[] = 'Usa offset=' . ( $offset + count( $paged_items ) ) . ' para continuar la consulta.';
        }

        if ( 0 === $total_matches ) {
            $warnings[] = __( 'No se han encontrado plugins con esos filtros.', 'wpgpt-mcp-bridge' );
        }

        $summary = array(
            'total_installed'       => count( $items ),
            'matched'               => $total_matches,
            'returned'              => count( $paged_items ),
            'active'                => $this->count_by_bool( $matched, 'active' ),
            'inactive'              => $this->count_by_bool( $matched, 'active', false ),
            'with_updates'          => $this->count_by_bool( $matched, 'update_available' ),
            'auto_update_enabled'   => $this->count_by_bool( $matched, 'auto_update_enabled' ),
            'risk_counts'           => $this->count_by_key( $matched, 'risk_level' ),
            'sources'               => $this->count_by_key( $matched, 'source' ),
            'offset'                => $offset,
            'limit'                 => $limit,
        );

        return array(
            'summary'      => $summary,
            'items'        => array_values( $paged_items ),
            'warnings'     => $warnings,
            'next_actions' => $next_actions,
        );
    }

    public function inspect( array $input = array() ): array|WP_Error {
        $include_repo = isset( $input['include_repo'] ) ? (bool) $input['include_repo'] : true;
        $targets      = $this->collect_inspect_targets( $input );

        if ( empty( $targets ) ) {
            return new WP_Error( 'wpgpt_plugin_target_required', __( 'Debes indicar al menos un plugin mediante plugin_file, slug, plugin_files o slugs.', 'wpgpt-mcp-bridge' ), array( 'status' => 400 ) );
        }

        $items        = array();
        $warnings     = array();
        $next_actions = array();

        foreach ( $targets as $target ) {
            $plugin_file = $this->resolve_plugin_file_from_target( $target );
            if ( is_wp_error( $plugin_file ) ) {
                $warnings[] = $plugin_file->get_error_message();
                continue;
            }

            $items[] = $this->build_plugin_inspection( $plugin_file, $include_repo );
        }

        if ( empty( $items ) ) {
            $warnings[] = __( 'No se ha podido inspeccionar ningún plugin con los objetivos indicados.', 'wpgpt-mcp-bridge' );
        } else {
            $next_actions[] = __( 'Usa wpgpt/plugins.apply con dry_run=true antes de ejecutar cambios.', 'wpgpt-mcp-bridge' );
        }

        return array(
            'summary'      => array(
                'requested' => count( $targets ),
                'inspected' => count( $items ),
                'with_updates' => $this->count_by_bool( $items, 'update_available' ),
                'active' => $this->count_by_bool( $items, 'active' ),
            ),
            'items'        => $items,
            'warnings'     => array_values( array_unique( array_filter( $warnings ) ) ),
            'next_actions' => $next_actions,
        );
    }

    public function apply( array $input = array() ): array|WP_Error {
        $action = isset( $input['action'] ) ? sanitize_key( (string) $input['action'] ) : '';
        $dry_run = isset( $input['dry_run'] ) ? (bool) $input['dry_run'] : false;
        $targets = isset( $input['targets'] ) && is_array( $input['targets'] ) ? $input['targets'] : array();
        $filters = isset( $input['filters'] ) && is_array( $input['filters'] ) ? $input['filters'] : array();

        if ( ! in_array( $action, array( 'install', 'update', 'activate', 'deactivate', 'delete' ), true ) ) {
            return new WP_Error( 'wpgpt_plugin_action_invalid', __( 'La acción indicada no es válida.', 'wpgpt-mcp-bridge' ), array( 'status' => 400 ) );
        }

        if ( 'delete' === $action ) {
            $delete_permission = $this->assert_delete_allowed();
            if ( is_wp_error( $delete_permission ) ) {
                return $delete_permission;
            }
        }

        $resolved = $this->resolve_apply_candidates( $action, $targets, $filters );
        $items = array();
        $warnings = $resolved['warnings'];
        $blocked = array();
        $executed = 0;

        foreach ( $resolved['items'] as $candidate ) {
            $validation = $this->validate_candidate_for_action( $action, $candidate );
            if ( ! empty( $validation ) ) {
                $blocked[] = array_merge( $this->candidate_identity( $candidate ), array( 'reasons' => $validation ) );
                continue;
            }

            if ( $dry_run ) {
                $items[] = array_merge(
                    $this->candidate_identity( $candidate ),
                    array(
                        'status' => 'dry_run',
                        'action' => $action,
                        'message' => __( 'Acción validada, no ejecutada por dry_run.', 'wpgpt-mcp-bridge' ),
                    )
                );
                continue;
            }

            $result = $this->execute_action( $action, $candidate );
            if ( is_wp_error( $result ) ) {
                $blocked[] = array_merge( $this->candidate_identity( $candidate ), array( 'reasons' => array( $result->get_error_message() ) ) );
                continue;
            }

            $executed++;
            $items[] = array_merge(
                $this->candidate_identity( $candidate ),
                array(
                    'status' => 'applied',
                    'action' => $action,
                    'result' => $result,
                )
            );
        }

        $next_actions = array();
        if ( $dry_run ) {
            $next_actions[] = __( 'Repite la misma llamada con dry_run=false para aplicar los cambios validados.', 'wpgpt-mcp-bridge' );
        }
        if ( 'update' === $action ) {
            $next_actions[] = __( 'Después de actualizar, conviene revisar activación y compatibilidad básica del plugin.', 'wpgpt-mcp-bridge' );
        }

        return array(
            'summary' => array(
                'action' => $action,
                'dry_run' => $dry_run,
                'resolved_targets' => count( $resolved['items'] ),
                'executed' => $executed,
                'blocked' => count( $blocked ),
            ),
            'items' => $items,
            'warnings' => array_values( array_unique( array_filter( $warnings ) ) ),
            'blocked' => $blocked,
            'next_actions' => $next_actions,
        );
    }

    private function build_plugin_inventory(): array {
        if ( ! function_exists( 'get_plugins' ) ) {
            require_once ABSPATH . 'wp-admin/includes/plugin.php';
        }

        $plugins = get_plugins();
        $items   = array();

        foreach ( array_keys( $plugins ) as $plugin_file ) {
            $items[] = $this->build_plugin_snapshot( $plugin_file, $plugins[ $plugin_file ] );
        }

        return $items;
    }

    private function build_plugin_snapshot( string $plugin_file, array $data ): array {
        require_once ABSPATH . 'wp-admin/includes/plugin.php';

        $slug                = $this->derive_slug_from_plugin_file( $plugin_file );
        $updates             = $this->get_update_map();
        $update_item         = $updates[ $plugin_file ] ?? null;
        $update_available    = is_object( $update_item );
        $new_version         = $update_available ? (string) ( $update_item->new_version ?? '' ) : '';
        $source              = $this->detect_source( $plugin_file, $data, $update_item );
        $auto_update_enabled = $this->is_auto_update_enabled( $plugin_file );
        $active              = is_plugin_active( $plugin_file );

        return array(
            'name'                 => isset( $data['Name'] ) ? wp_strip_all_tags( (string) $data['Name'] ) : $plugin_file,
            'plugin_file'          => $plugin_file,
            'slug'                 => $slug,
            'version_installed'    => isset( $data['Version'] ) ? (string) $data['Version'] : '',
            'version_available'    => $new_version,
            'active'               => $active,
            'auto_update_enabled'  => $auto_update_enabled,
            'update_available'     => $update_available,
            'source'               => $source,
            'risk_level'           => $this->calculate_risk_level( array(
                'active' => $active,
                'update_available' => $update_available,
                'source' => $source,
                'auto_update_enabled' => $auto_update_enabled,
            ) ),
        );
    }

    private function build_plugin_inspection( string $plugin_file, bool $include_repo = true ): array {
        if ( ! function_exists( 'get_plugins' ) ) {
            require_once ABSPATH . 'wp-admin/includes/plugin.php';
        }

        $plugins    = get_plugins();
        $data       = $plugins[ $plugin_file ] ?? array();
        $snapshot   = $this->build_plugin_snapshot( $plugin_file, is_array( $data ) ? $data : array() );
        $slug       = $snapshot['slug'];
        $update_map = $this->get_update_map();
        $update     = $update_map[ $plugin_file ] ?? null;
        $runtime    = $this->collect_runtime_signals( $plugin_file );
        $compat     = $this->build_compatibility_summary( $data, $update );
        $actions    = $this->available_actions_for_plugin( $plugin_file, $snapshot );
        $repo       = null;

        if ( $include_repo && 'wordpress.org' === $snapshot['source'] && '' !== $slug ) {
            $repo = $this->maybe_fetch_repository_info( $slug );
        }

        return array(
            'name'                  => $snapshot['name'],
            'plugin_file'           => $plugin_file,
            'slug'                  => $slug,
            'header'                => array(
                'name'        => isset( $data['Name'] ) ? (string) $data['Name'] : '',
                'plugin_uri'  => isset( $data['PluginURI'] ) ? (string) $data['PluginURI'] : '',
                'author'      => isset( $data['Author'] ) ? wp_strip_all_tags( (string) $data['Author'] ) : '',
                'author_uri'  => isset( $data['AuthorURI'] ) ? (string) $data['AuthorURI'] : '',
                'description' => isset( $data['Description'] ) ? wp_strip_all_tags( (string) $data['Description'] ) : '',
                'text_domain' => isset( $data['TextDomain'] ) ? (string) $data['TextDomain'] : '',
                'network'     => ! empty( $data['Network'] ),
                'update_uri'  => isset( $data['UpdateURI'] ) ? (string) $data['UpdateURI'] : '',
            ),
            'version_installed'     => $snapshot['version_installed'],
            'update_available'      => $snapshot['update_available'],
            'version_available'     => $snapshot['version_available'],
            'active'                => $snapshot['active'],
            'auto_update_enabled'   => $snapshot['auto_update_enabled'],
            'source'                => $snapshot['source'],
            'compatibility'         => $compat,
            'runtime_signals'       => $runtime,
            'available_actions'     => $actions,
            'risk_level'            => $snapshot['risk_level'],
            'repository'            => $repo,
        );
    }

    private function collect_runtime_signals( string $plugin_file ): array {
        require_once ABSPATH . 'wp-admin/includes/plugin.php';

        $signals = array(
            'is_active' => is_plugin_active( $plugin_file ),
            'is_paused' => function_exists( 'is_plugin_paused' ) ? (bool) is_plugin_paused( $plugin_file ) : false,
            'plugin_path_exists' => file_exists( WP_PLUGIN_DIR . '/' . $plugin_file ),
            'network_active' => is_multisite() ? is_plugin_active_for_network( $plugin_file ) : false,
        );

        return $signals;
    }

    private function build_compatibility_summary( array $data, $update_item ): array {
        global $wp_version;

        $requires_wp        = isset( $data['RequiresWP'] ) ? (string) $data['RequiresWP'] : '';
        $requires_php       = isset( $data['RequiresPHP'] ) ? (string) $data['RequiresPHP'] : '';
        $tested_up_to       = is_object( $update_item ) && isset( $update_item->tested ) ? (string) $update_item->tested : '';
        $requires_php_repo  = is_object( $update_item ) && isset( $update_item->requires_php ) ? (string) $update_item->requires_php : '';

        return array(
            'site_wp_version' => (string) $wp_version,
            'site_php_version' => (string) PHP_VERSION,
            'requires_wp' => $requires_wp,
            'requires_php' => $requires_php,
            'tested_up_to' => $tested_up_to,
            'wp_requirement_ok' => '' === $requires_wp ? null : version_compare( (string) $wp_version, $requires_wp, '>=' ),
            'php_requirement_ok' => '' === $requires_php ? null : version_compare( (string) PHP_VERSION, $requires_php, '>=' ),
            'repo_requires_php' => $requires_php_repo,
        );
    }

    private function available_actions_for_plugin( string $plugin_file, array $snapshot ): array {
        $actions = array();

        if ( ! $snapshot['active'] ) {
            $actions[] = 'activate';
            $actions[] = 'delete';
        } else {
            $actions[] = 'deactivate';
        }

        if ( $snapshot['update_available'] ) {
            $actions[] = 'update';
        }

        return $actions;
    }

    private function maybe_fetch_repository_info( string $slug ): ?array {
        require_once ABSPATH . 'wp-admin/includes/plugin-install.php';

        $plugin = plugins_api(
            'plugin_information',
            array(
                'slug'   => $slug,
                'fields' => array(
                    'sections'        => false,
                    'tags'            => false,
                    'versions'        => false,
                    'banners'         => false,
                    'contributors'    => false,
                    'rating'          => true,
                    'active_installs' => true,
                    'tested'          => true,
                    'requires'        => true,
                    'requires_php'    => true,
                    'last_updated'    => true,
                ),
            )
        );

        if ( is_wp_error( $plugin ) ) {
            return null;
        }

        return array(
            'name' => (string) ( $plugin->name ?? '' ),
            'slug' => (string) ( $plugin->slug ?? '' ),
            'version' => (string) ( $plugin->version ?? '' ),
            'tested' => (string) ( $plugin->tested ?? '' ),
            'requires' => (string) ( $plugin->requires ?? '' ),
            'requires_php' => (string) ( $plugin->requires_php ?? '' ),
            'last_updated' => (string) ( $plugin->last_updated ?? '' ),
            'active_installs' => (int) ( $plugin->active_installs ?? 0 ),
            'rating' => (int) ( $plugin->rating ?? 0 ),
        );
    }

    private function plugin_matches_filters( array $item, array $filters, string $search ): bool {
        if ( '' !== $search ) {
            $haystack = strtolower( implode( ' ', array_filter( array(
                (string) ( $item['name'] ?? '' ),
                (string) ( $item['plugin_file'] ?? '' ),
                (string) ( $item['slug'] ?? '' ),
            ) ) ) );
            if ( false === strpos( $haystack, strtolower( $search ) ) ) {
                return false;
            }
        }

        foreach ( array( 'plugin_file', 'slug', 'source' ) as $key ) {
            if ( isset( $filters[ $key ] ) && '' !== (string) $filters[ $key ] ) {
                if ( (string) $item[ $key ] !== (string) $filters[ $key ] ) {
                    return false;
                }
            }
        }

        foreach ( array( 'active', 'update_available', 'auto_update_enabled' ) as $key ) {
            if ( array_key_exists( $key, $filters ) ) {
                if ( (bool) $item[ $key ] !== (bool) $filters[ $key ] ) {
                    return false;
                }
            }
        }

        return true;
    }

    private function resolve_apply_candidates( string $action, array $targets, array $filters ): array {
        $warnings = array();
        $items    = array();

        if ( 'install' === $action ) {
            $slug_targets = $this->extract_install_targets( $targets, $filters );
            foreach ( $slug_targets as $slug ) {
                $items[] = array(
                    'slug' => $slug,
                    'plugin_file' => $this->find_plugin_file_by_slug( $slug ),
                );
            }

            if ( empty( $items ) ) {
                $warnings[] = __( 'Para install debes indicar al menos un slug en targets o filters.slug.', 'wpgpt-mcp-bridge' );
            }

            return array(
                'items' => $items,
                'warnings' => $warnings,
            );
        }

        $inventory_filters = $filters;
        $search = '';
        if ( isset( $inventory_filters['search'] ) ) {
            $search = sanitize_text_field( (string) $inventory_filters['search'] );
            unset( $inventory_filters['search'] );
        }

        $inventory = $this->query(
            array(
                'search' => $search,
                'filters' => $inventory_filters,
                'limit' => 500,
                'offset' => 0,
            )
        );

        $matched = is_wp_error( $inventory ) ? array() : (array) ( $inventory['items'] ?? array() );
        $by_file = array();
        foreach ( $matched as $item ) {
            if ( isset( $item['plugin_file'] ) ) {
                $by_file[ (string) $item['plugin_file'] ] = $item;
            }
        }

        if ( ! empty( $targets ) ) {
            foreach ( $targets as $target ) {
                if ( ! is_array( $target ) ) {
                    continue;
                }

                $plugin_file = $this->resolve_plugin_file_from_target( $target );
                if ( is_wp_error( $plugin_file ) ) {
                    $warnings[] = $plugin_file->get_error_message();
                    continue;
                }

                if ( ! isset( $by_file[ $plugin_file ] ) && ! empty( $filters ) ) {
                    $warnings[] = sprintf( __( 'El plugin %s no coincide con los filtros indicados.', 'wpgpt-mcp-bridge' ), $plugin_file );
                    continue;
                }

                $items[ $plugin_file ] = isset( $by_file[ $plugin_file ] ) ? $by_file[ $plugin_file ] : $this->build_plugin_snapshot_from_file( $plugin_file );
            }
        } else {
            foreach ( $matched as $item ) {
                if ( isset( $item['plugin_file'] ) ) {
                    $items[ (string) $item['plugin_file'] ] = $item;
                }
            }
        }

        if ( empty( $items ) ) {
            $warnings[] = __( 'No se han resuelto plugins para ejecutar la acción solicitada.', 'wpgpt-mcp-bridge' );
        }

        return array(
            'items' => array_values( $items ),
            'warnings' => array_values( array_unique( array_filter( $warnings ) ) ),
        );
    }

    private function validate_candidate_for_action( string $action, array $candidate ): array {
        $reasons = array();
        $plugin_file = isset( $candidate['plugin_file'] ) ? (string) $candidate['plugin_file'] : '';
        $slug = isset( $candidate['slug'] ) ? (string) $candidate['slug'] : '';

        switch ( $action ) {
            case 'install':
                if ( '' === $slug ) {
                    $reasons[] = __( 'No se ha resuelto el slug para instalar.', 'wpgpt-mcp-bridge' );
                    break;
                }
                if ( '' !== $plugin_file ) {
                    $reasons[] = __( 'El plugin ya parece estar instalado.', 'wpgpt-mcp-bridge' );
                }
                break;

            case 'update':
                if ( empty( $candidate['update_available'] ) ) {
                    $reasons[] = __( 'No hay actualización disponible para este plugin.', 'wpgpt-mcp-bridge' );
                }
                break;

            case 'activate':
                if ( ! empty( $candidate['active'] ) ) {
                    $reasons[] = __( 'El plugin ya está activo.', 'wpgpt-mcp-bridge' );
                }
                break;

            case 'deactivate':
                if ( empty( $candidate['active'] ) ) {
                    $reasons[] = __( 'El plugin ya está inactivo.', 'wpgpt-mcp-bridge' );
                }
                break;

            case 'delete':
                if ( ! empty( $candidate['active'] ) ) {
                    $reasons[] = __( 'No se permite borrar plugins activos.', 'wpgpt-mcp-bridge' );
                }
                if ( '' !== $plugin_file && $this->is_current_plugin( $plugin_file ) ) {
                    $reasons[] = __( 'No se permite borrar este plugin puente.', 'wpgpt-mcp-bridge' );
                }
                break;
        }

        return $reasons;
    }

    private function execute_action( string $action, array $candidate ): array|WP_Error {
        switch ( $action ) {
            case 'install':
                return $this->install( (string) ( $candidate['slug'] ?? '' ) );
            case 'update':
                return $this->update( (string) ( $candidate['plugin_file'] ?? '' ) );
            case 'activate':
                return $this->activate( (string) ( $candidate['plugin_file'] ?? '' ) );
            case 'deactivate':
                return $this->deactivate( (string) ( $candidate['plugin_file'] ?? '' ) );
            case 'delete':
                return $this->delete( (string) ( $candidate['plugin_file'] ?? '' ) );
        }

        return new WP_Error( 'wpgpt_plugin_action_invalid', __( 'La acción indicada no es válida.', 'wpgpt-mcp-bridge' ), array( 'status' => 400 ) );
    }

    private function candidate_identity( array $candidate ): array {
        return array(
            'plugin_file' => isset( $candidate['plugin_file'] ) ? (string) $candidate['plugin_file'] : '',
            'slug' => isset( $candidate['slug'] ) ? (string) $candidate['slug'] : '',
            'name' => isset( $candidate['name'] ) ? (string) $candidate['name'] : '',
        );
    }

    private function collect_inspect_targets( array $input ): array {
        $targets = array();

        if ( ! empty( $input['plugin_file'] ) ) {
            $targets[] = array( 'plugin_file' => sanitize_text_field( (string) $input['plugin_file'] ) );
        }
        if ( ! empty( $input['slug'] ) ) {
            $targets[] = array( 'slug' => sanitize_key( (string) $input['slug'] ) );
        }
        if ( ! empty( $input['plugin_files'] ) && is_array( $input['plugin_files'] ) ) {
            foreach ( $input['plugin_files'] as $plugin_file ) {
                $targets[] = array( 'plugin_file' => sanitize_text_field( (string) $plugin_file ) );
            }
        }
        if ( ! empty( $input['slugs'] ) && is_array( $input['slugs'] ) ) {
            foreach ( $input['slugs'] as $slug ) {
                $targets[] = array( 'slug' => sanitize_key( (string) $slug ) );
            }
        }

        $normalized = array();
        $seen = array();
        foreach ( $targets as $target ) {
            $key = ! empty( $target['plugin_file'] ) ? 'plugin_file:' . $target['plugin_file'] : 'slug:' . ( $target['slug'] ?? '' );
            if ( isset( $seen[ $key ] ) ) {
                continue;
            }
            $seen[ $key ] = true;
            $normalized[] = $target;
        }

        return $normalized;
    }

    private function resolve_plugin_file_from_target( array $target ): string|WP_Error {
        if ( ! empty( $target['plugin_file'] ) ) {
            return $this->resolve_plugin_file( (string) $target['plugin_file'] );
        }
        if ( ! empty( $target['slug'] ) ) {
            return $this->resolve_plugin_file( (string) $target['slug'] );
        }

        return new WP_Error( 'wpgpt_plugin_target_invalid', __( 'El objetivo del plugin no es válido.', 'wpgpt-mcp-bridge' ), array( 'status' => 400 ) );
    }

    private function build_plugin_snapshot_from_file( string $plugin_file ): array {
        if ( ! function_exists( 'get_plugins' ) ) {
            require_once ABSPATH . 'wp-admin/includes/plugin.php';
        }
        $plugins = get_plugins();
        $data    = isset( $plugins[ $plugin_file ] ) && is_array( $plugins[ $plugin_file ] ) ? $plugins[ $plugin_file ] : array();

        return $this->build_plugin_snapshot( $plugin_file, $data );
    }

    private function derive_slug_from_plugin_file( string $plugin_file ): string {
        $parts = explode( '/', $plugin_file );
        if ( count( $parts ) > 1 && '' !== trim( (string) $parts[0] ) ) {
            return sanitize_key( (string) $parts[0] );
        }

        return sanitize_key( basename( $plugin_file, '.php' ) );
    }

    private function get_update_map(): array {
        $transient = get_site_transient( 'update_plugins' );
        if ( is_object( $transient ) && isset( $transient->response ) && is_array( $transient->response ) ) {
            return $transient->response;
        }

        return array();
    }

    private function is_auto_update_enabled( string $plugin_file ): bool {
        if ( function_exists( 'wp_is_auto_update_enabled_for_type' ) && ! wp_is_auto_update_enabled_for_type( 'plugin' ) ) {
            return false;
        }

        $enabled = (array) get_site_option( 'auto_update_plugins', array() );
        return in_array( $plugin_file, $enabled, true );
    }

    private function detect_source( string $plugin_file, array $data, $update_item ): string {
        $update_uri = isset( $data['UpdateURI'] ) ? trim( (string) $data['UpdateURI'] ) : '';

        if ( '' !== $update_uri && 'false' !== strtolower( $update_uri ) ) {
            return 'custom';
        }

        if ( is_object( $update_item ) && ! empty( $update_item->package ) ) {
            $package = (string) $update_item->package;
            if ( false !== strpos( $package, 'wordpress.org' ) ) {
                return 'wordpress.org';
            }
            return 'custom';
        }

        if ( isset( $data['PluginURI'] ) && false !== strpos( (string) $data['PluginURI'], 'wordpress.org/plugins/' ) ) {
            return 'wordpress.org';
        }

        return 'local_or_unknown';
    }

    private function calculate_risk_level( array $context ): string {
        if ( ! empty( $context['update_available'] ) && 'custom' === ( $context['source'] ?? '' ) ) {
            return 'high';
        }
        if ( ! empty( $context['update_available'] ) ) {
            return 'medium';
        }
        if ( 'local_or_unknown' === ( $context['source'] ?? '' ) ) {
            return 'medium';
        }

        return 'low';
    }

    private function count_by_bool( array $items, string $key, bool $expected = true ): int {
        $count = 0;
        foreach ( $items as $item ) {
            if ( (bool) ( $item[ $key ] ?? false ) === $expected ) {
                $count++;
            }
        }
        return $count;
    }

    private function count_by_key( array $items, string $key ): array {
        $counts = array();
        foreach ( $items as $item ) {
            $value = (string) ( $item[ $key ] ?? '' );
            if ( '' === $value ) {
                $value = 'unknown';
            }
            if ( ! isset( $counts[ $value ] ) ) {
                $counts[ $value ] = 0;
            }
            $counts[ $value ]++;
        }
        ksort( $counts );
        return $counts;
    }

    private function extract_install_targets( array $targets, array $filters ): array {
        $slugs = array();

        foreach ( $targets as $target ) {
            if ( ! is_array( $target ) ) {
                continue;
            }
            if ( ! empty( $target['slug'] ) ) {
                $slugs[] = sanitize_key( (string) $target['slug'] );
                continue;
            }
            if ( ! empty( $target['plugin_file'] ) ) {
                $slugs[] = sanitize_key( (string) $target['plugin_file'] );
            }
        }

        if ( ! empty( $filters['slug'] ) ) {
            $slugs[] = sanitize_key( (string) $filters['slug'] );
        }

        $slugs = array_values( array_unique( array_filter( $slugs ) ) );
        return $slugs;
    }

    private function find_plugin_file_by_slug( string $slug ): string {
        if ( ! function_exists( 'get_plugins' ) ) {
            require_once ABSPATH . 'wp-admin/includes/plugin.php';
        }

        $plugins = get_plugins();
        foreach ( array_keys( $plugins ) as $plugin_file ) {
            if ( $this->derive_slug_from_plugin_file( $plugin_file ) === $slug ) {
                return $plugin_file;
            }
        }

        return '';
    }

    private function assert_delete_allowed(): true|WP_Error {
        if ( Security::get_read_only() ) {
            return new WP_Error( 'wpgpt_read_only', __( 'El plugin está en modo solo lectura.', 'wpgpt-mcp-bridge' ), array( 'status' => 403 ) );
        }
        if ( ! Security::get_allow_delete() ) {
            return new WP_Error( 'wpgpt_delete_disabled', __( 'Las operaciones de eliminación están desactivadas en los ajustes del plugin.', 'wpgpt-mcp-bridge' ), array( 'status' => 403 ) );
        }
        if ( ! current_user_can( 'delete_plugins' ) ) {
            return new WP_Error( 'wpgpt_forbidden', __( 'No tienes permisos para eliminar plugins.', 'wpgpt-mcp-bridge' ), array( 'status' => 403 ) );
        }

        return true;
    }

    private function is_current_plugin( string $plugin_file ): bool {
        if ( ! defined( 'WPGPT_MCP_BRIDGE_FILE' ) ) {
            return false;
        }

        return plugin_basename( WPGPT_MCP_BRIDGE_FILE ) === $plugin_file;
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
