<?php

namespace WPGPT\MCPBridge\Settings;

use WP_Error;

if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

class Settings_Service {
    private const OPTION_WHITELIST = array(
        'blogname',
        'blogdescription',
        'admin_email',
        'timezone_string',
        'permalink_structure',
        'stylesheet',
        'template',
        'show_on_front',
        'page_on_front',
        'page_for_posts',
        'posts_per_page',
        'default_category',
        'default_ping_status',
        'default_comment_status',
    );

    public function search_whitelisted_options( array $input ): array {
        global $wpdb;
        $term  = isset( $input['search'] ) ? sanitize_text_field( (string) $input['search'] ) : '';
        $limit = isset( $input['limit'] ) ? max( 1, min( 50, (int) $input['limit'] ) ) : 20;

        $sql   = "SELECT option_name, option_value FROM {$wpdb->options} WHERE option_name IN (" . implode( ',', array_fill( 0, count( self::OPTION_WHITELIST ), '%s' ) ) . ')';
        $args  = self::OPTION_WHITELIST;
        if ( '' !== $term ) {
            $sql   .= ' AND option_name LIKE %s';
            $args[] = '%' . $wpdb->esc_like( $term ) . '%';
        }
        $sql   .= ' ORDER BY option_name ASC LIMIT %d';
        $args[] = $limit;
        $prepared = $wpdb->prepare( $sql, ...$args );
        $rows = $wpdb->get_results( $prepared, ARRAY_A );
        return array( 'count' => count( $rows ), 'items' => array_values( $rows ), 'whitelist' => self::OPTION_WHITELIST );
    }

    public function update_whitelisted_option( array $input ): array|WP_Error {
        $option_name = isset( $input['option_name'] ) ? sanitize_key( (string) $input['option_name'] ) : '';
        if ( ! in_array( $option_name, self::OPTION_WHITELIST, true ) ) {
            return new WP_Error( 'wpgpt_option_not_allowed', __( 'La opción solicitada no está permitida.', 'wpgpt-mcp-bridge' ), array( 'status' => 403 ) );
        }
        $value = $input['option_value'] ?? null;
        update_option( $option_name, $value, false );
        return array( 'updated' => true, 'option_name' => $option_name, 'option_value' => get_option( $option_name ) );
    }


    public function get_theme_info( array $input ): array|WP_Error {
        $stylesheet = isset( $input['stylesheet'] ) ? sanitize_text_field( (string) $input['stylesheet'] ) : '';
        $theme = wp_get_theme( $stylesheet );
        if ( ! $theme->exists() ) {
            return new WP_Error( 'wpgpt_theme_not_found', __( 'No se ha encontrado el tema indicado.', 'wpgpt-mcp-bridge' ), array( 'status' => 404 ) );
        }
        return array(
            'stylesheet' => $theme->get_stylesheet(),
            'template' => $theme->get_template(),
            'name' => $theme->get( 'Name' ),
            'version' => $theme->get( 'Version' ),
            'description' => $theme->get( 'Description' ),
            'active' => wp_get_theme()->get_stylesheet() === $theme->get_stylesheet(),
        );
    }

    public function delete_theme( array $input ): array|WP_Error {
        if ( ! function_exists( 'delete_theme' ) ) {
            require_once ABSPATH . 'wp-admin/includes/theme.php';
        }
        $stylesheet = isset( $input['stylesheet'] ) ? sanitize_text_field( (string) $input['stylesheet'] ) : '';
        $theme = wp_get_theme( $stylesheet );
        if ( ! $theme->exists() ) {
            return new WP_Error( 'wpgpt_theme_not_found', __( 'No se ha encontrado el tema indicado.', 'wpgpt-mcp-bridge' ), array( 'status' => 404 ) );
        }
        if ( wp_get_theme()->get_stylesheet() === $theme->get_stylesheet() ) {
            return new WP_Error( 'wpgpt_theme_active', __( 'No se puede eliminar el tema activo.', 'wpgpt-mcp-bridge' ), array( 'status' => 400 ) );
        }
        $deleted = delete_theme( $theme->get_stylesheet() );
        if ( is_wp_error( $deleted ) ) { return $deleted; }
        return array( 'deleted' => true, 'stylesheet' => $stylesheet );
    }

    public function get_general_settings(): array {
        return array(
            'blogname' => get_option( 'blogname' ),
            'blogdescription' => get_option( 'blogdescription' ),
            'admin_email' => get_option( 'admin_email' ),
            'timezone_string' => get_option( 'timezone_string' ),
            'date_format' => get_option( 'date_format' ),
            'time_format' => get_option( 'time_format' ),
            'start_of_week' => (int) get_option( 'start_of_week' ),
            'site_icon' => (int) get_option( 'site_icon' ),
        );
    }

    public function update_general_settings( array $input ): array {
        foreach ( array( 'blogname', 'blogdescription', 'admin_email', 'timezone_string', 'date_format', 'time_format' ) as $option_name ) {
            if ( array_key_exists( $option_name, $input ) ) {
                update_option( $option_name, sanitize_text_field( (string) $input[ $option_name ] ), false );
            }
        }
        if ( array_key_exists( 'start_of_week', $input ) ) {
            update_option( 'start_of_week', absint( $input['start_of_week'] ), false );
        }
        if ( array_key_exists( 'site_icon', $input ) ) {
            update_option( 'site_icon', absint( $input['site_icon'] ), false );
        }
        return array( 'updated' => true ) + $this->get_general_settings();
    }

    public function set_privacy_page( array $input ): array|WP_Error {
        $page_id = absint( $input['page_id'] ?? 0 );
        if ( $page_id <= 0 || 'page' !== get_post_type( $page_id ) ) {
            return new WP_Error( 'wpgpt_invalid_privacy_page', __( 'Debes indicar una página válida.', 'wpgpt-mcp-bridge' ), array( 'status' => 400 ) );
        }
        update_option( 'wp_page_for_privacy_policy', $page_id, false );
        return array( 'updated' => true, 'page_id' => $page_id );
    }

    public function list_themes(): array {
        $themes  = wp_get_themes();
        $current = wp_get_theme()->get_stylesheet();
        $items   = array();
        foreach ( $themes as $stylesheet => $theme ) {
            $items[] = array(
                'stylesheet' => $stylesheet,
                'template'   => $theme->get_template(),
                'name'       => $theme->get( 'Name' ),
                'version'    => $theme->get( 'Version' ),
                'active'     => $stylesheet === $current,
            );
        }
        return array( 'count' => count( $items ), 'items' => $items );
    }

    public function activate_theme( array $input ): array|WP_Error {
        $stylesheet = isset( $input['stylesheet'] ) ? sanitize_text_field( (string) $input['stylesheet'] ) : '';
        if ( '' === $stylesheet ) {
            return new WP_Error( 'wpgpt_invalid_theme', __( 'Debes indicar un stylesheet válido.', 'wpgpt-mcp-bridge' ), array( 'status' => 400 ) );
        }
        $theme = wp_get_theme( $stylesheet );
        if ( ! $theme->exists() ) {
            return new WP_Error( 'wpgpt_theme_not_found', __( 'No se ha encontrado el tema indicado.', 'wpgpt-mcp-bridge' ), array( 'status' => 404 ) );
        }
        switch_theme( $theme->get_stylesheet(), $theme->get_template() );
        return array( 'activated' => true, 'stylesheet' => $theme->get_stylesheet(), 'template' => $theme->get_template(), 'name' => $theme->get( 'Name' ) );
    }

    public function query_themes( array $input = array() ): array|WP_Error {
        $filters = isset( $input['filters'] ) && is_array( $input['filters'] ) ? $input['filters'] : array();
        $search  = isset( $input['search'] ) ? sanitize_text_field( (string) $input['search'] ) : '';
        $limit   = isset( $input['limit'] ) ? max( 1, min( 200, (int) $input['limit'] ) ) : 100;
        $offset  = isset( $input['offset'] ) ? max( 0, (int) $input['offset'] ) : 0;
        if ( '' === $search && isset( $filters['search'] ) ) {
            $search = sanitize_text_field( (string) $filters['search'] );
        }

        $items = $this->build_theme_inventory();
        $matched = array_values( array_filter( $items, function( $item ) use ( $filters, $search ) {
            return $this->theme_matches_filters( $item, $filters, $search );
        } ) );
        $paged = array_slice( $matched, $offset, $limit );
        $warnings = empty( $matched ) ? array( __( 'No se han encontrado temas con esos filtros.', 'wpgpt-mcp-bridge' ) ) : array();
        $next_actions = array();
        if ( count( $matched ) > $offset + count( $paged ) ) {
            $next_actions[] = 'Usa offset=' . ( $offset + count( $paged ) ) . ' para continuar la consulta.';
        }

        return array(
            'summary' => array(
                'total_installed' => count( $items ),
                'matched' => count( $matched ),
                'returned' => count( $paged ),
                'active' => count( array_filter( $matched, fn( $i ) => ! empty( $i['active'] ) ) ),
                'inactive' => count( array_filter( $matched, fn( $i ) => empty( $i['active'] ) ) ),
                'with_updates' => count( array_filter( $matched, fn( $i ) => ! empty( $i['update_available'] ) ) ),
                'block_themes' => count( array_filter( $matched, fn( $i ) => ! empty( $i['block_theme'] ) ) ),
                'child_themes' => count( array_filter( $matched, fn( $i ) => ! empty( $i['is_child_theme'] ) ) ),
                'offset' => $offset,
                'limit' => $limit,
            ),
            'items' => array_values( $paged ),
            'warnings' => $warnings,
            'next_actions' => $next_actions,
        );
    }

    public function inspect_themes( array $input = array() ): array|WP_Error {
        $stylesheets = array();
        if ( ! empty( $input['stylesheet'] ) ) {
            $stylesheets[] = sanitize_text_field( (string) $input['stylesheet'] );
        }
        if ( ! empty( $input['stylesheets'] ) && is_array( $input['stylesheets'] ) ) {
            foreach ( $input['stylesheets'] as $stylesheet ) {
                $stylesheet = sanitize_text_field( (string) $stylesheet );
                if ( '' !== $stylesheet ) {
                    $stylesheets[] = $stylesheet;
                }
            }
        }
        $stylesheets = array_values( array_unique( array_filter( $stylesheets ) ) );
        if ( empty( $stylesheets ) ) {
            return new WP_Error( 'wpgpt_theme_target_required', __( 'Debes indicar al menos un stylesheet.', 'wpgpt-mcp-bridge' ), array( 'status' => 400 ) );
        }

        $items = array();
        $warnings = array();
        foreach ( $stylesheets as $stylesheet ) {
            $theme = wp_get_theme( $stylesheet );
            if ( ! $theme->exists() ) {
                $warnings[] = sprintf( __( 'No se ha encontrado el tema %s.', 'wpgpt-mcp-bridge' ), $stylesheet );
                continue;
            }
            $items[] = $this->build_theme_inspection( $theme );
        }

        return array(
            'summary' => array(
                'requested' => count( $stylesheets ),
                'inspected' => count( $items ),
                'active' => count( array_filter( $items, fn( $i ) => ! empty( $i['active'] ) ) ),
                'with_updates' => count( array_filter( $items, fn( $i ) => ! empty( $i['update_available'] ) ) ),
            ),
            'items' => $items,
            'warnings' => array_values( array_unique( array_filter( $warnings ) ) ),
            'next_actions' => empty( $items ) ? array() : array( __( 'Usa wpgpt/themes-apply con dry_run=true antes de ejecutar cambios.', 'wpgpt-mcp-bridge' ) ),
        );
    }

    public function apply_themes( array $input = array() ): array|WP_Error {
        $action = isset( $input['action'] ) ? sanitize_key( (string) $input['action'] ) : '';
        $dry_run = ! empty( $input['dry_run'] );
        if ( ! in_array( $action, array( 'activate', 'update', 'delete' ), true ) ) {
            return new WP_Error( 'wpgpt_theme_action_invalid', __( 'La acción indicada no es válida.', 'wpgpt-mcp-bridge' ), array( 'status' => 400 ) );
        }
        $targets = $this->resolve_theme_apply_targets( $input );
        $items = array();
        $blocked = array();
        $warnings = array();
        $executed = 0;

        foreach ( $targets as $stylesheet ) {
            $theme = wp_get_theme( $stylesheet );
            if ( ! $theme->exists() ) {
                $warnings[] = sprintf( __( 'No se ha encontrado el tema %s.', 'wpgpt-mcp-bridge' ), $stylesheet );
                continue;
            }
            $reasons = $this->validate_theme_action( $action, $theme );
            if ( ! empty( $reasons ) ) {
                $blocked[] = array( 'stylesheet' => $stylesheet, 'name' => $theme->get( 'Name' ), 'reasons' => $reasons );
                continue;
            }
            if ( $dry_run ) {
                $items[] = array( 'stylesheet' => $stylesheet, 'name' => $theme->get( 'Name' ), 'status' => 'dry_run', 'action' => $action, 'message' => __( 'Acción validada, no ejecutada por dry_run.', 'wpgpt-mcp-bridge' ) );
                continue;
            }
            if ( 'activate' === $action ) {
                $result = $this->activate_theme( array( 'stylesheet' => $stylesheet ) );
            } elseif ( 'delete' === $action ) {
                $result = $this->delete_theme( array( 'stylesheet' => $stylesheet ) );
            } else {
                $result = $this->update_theme( $stylesheet );
            }
            if ( is_wp_error( $result ) ) {
                $blocked[] = array( 'stylesheet' => $stylesheet, 'name' => $theme->get( 'Name' ), 'reasons' => array( $result->get_error_message() ) );
                continue;
            }
            $executed++;
            $items[] = array( 'stylesheet' => $stylesheet, 'name' => $theme->get( 'Name' ), 'status' => 'applied', 'action' => $action, 'result' => $result );
        }
        $next_actions = $dry_run ? array( __( 'Repite la misma llamada con dry_run=false para aplicar los cambios validados.', 'wpgpt-mcp-bridge' ) ) : array();
        return array(
            'summary' => array( 'action' => $action, 'dry_run' => $dry_run, 'resolved_targets' => count( $targets ), 'executed' => $executed, 'blocked' => count( $blocked ) ),
            'items' => $items,
            'warnings' => array_values( array_unique( array_filter( $warnings ) ) ),
            'blocked' => $blocked,
            'next_actions' => $next_actions,
        );
    }

    private function update_theme( string $stylesheet ): array|WP_Error {
        require_once ABSPATH . 'wp-admin/includes/class-wp-upgrader.php';
        require_once ABSPATH . 'wp-admin/includes/file.php';
        require_once ABSPATH . 'wp-admin/includes/theme.php';
        $skin = new \WP_Ajax_Upgrader_Skin();
        $upgrader = new \Theme_Upgrader( $skin );
        $result = $upgrader->upgrade( $stylesheet );
        if ( is_wp_error( $result ) ) {
            return $result;
        }
        if ( false === $result ) {
            return new WP_Error( 'wpgpt_theme_update_failed', __( 'No se pudo actualizar el tema.', 'wpgpt-mcp-bridge' ), array( 'status' => 500 ) );
        }
        return array( 'updated' => true, 'stylesheet' => $stylesheet );
    }

    private function build_theme_inventory(): array {
        $themes = wp_get_themes();
        $updates = get_site_transient( 'update_themes' );
        $responses = is_object( $updates ) && isset( $updates->response ) && is_array( $updates->response ) ? $updates->response : array();
        $current = wp_get_theme()->get_stylesheet();
        $items = array();
        foreach ( $themes as $stylesheet => $theme ) {
            $update = $responses[ $stylesheet ] ?? null;
            $items[] = array(
                'stylesheet' => $stylesheet,
                'template' => $theme->get_template(),
                'name' => $theme->get( 'Name' ),
                'version_installed' => $theme->get( 'Version' ),
                'version_available' => is_array( $update ) ? (string) ( $update['new_version'] ?? '' ) : '',
                'active' => $stylesheet === $current,
                'update_available' => is_array( $update ) && ! empty( $update['new_version'] ),
                'block_theme' => method_exists( $theme, 'is_block_theme' ) ? (bool) $theme->is_block_theme() : false,
                'is_child_theme' => $theme->get_stylesheet() !== $theme->get_template(),
                'risk_level' => $stylesheet === $current ? 'medium' : 'low',
            );
        }
        return $items;
    }

    private function theme_matches_filters( array $item, array $filters, string $search ): bool {
        foreach ( array( 'stylesheet', 'template', 'active', 'update_available', 'block_theme', 'is_child_theme' ) as $key ) {
            if ( ! array_key_exists( $key, $filters ) ) {
                continue;
            }
            if ( $item[ $key ] !== $filters[ $key ] ) {
                return false;
            }
        }
        if ( '' !== $search ) {
            $haystack = strtolower( implode( ' ', array( $item['name'], $item['stylesheet'], $item['template'] ) ) );
            if ( false === strpos( $haystack, strtolower( $search ) ) ) {
                return false;
            }
        }
        return true;
    }

    private function build_theme_inspection( \WP_Theme $theme ): array {
        $inventory = null;
        foreach ( $this->build_theme_inventory() as $item ) {
            if ( $item['stylesheet'] === $theme->get_stylesheet() ) {
                $inventory = $item;
                break;
            }
        }
        $inventory = is_array( $inventory ) ? $inventory : array();
        return array(
            'name' => $theme->get( 'Name' ),
            'stylesheet' => $theme->get_stylesheet(),
            'template' => $theme->get_template(),
            'header' => array(
                'name' => $theme->get( 'Name' ),
                'theme_uri' => $theme->get( 'ThemeURI' ),
                'author' => $theme->get( 'Author' ),
                'author_uri' => $theme->get( 'AuthorURI' ),
                'description' => $theme->get( 'Description' ),
                'tags' => $theme->get( 'Tags' ),
                'text_domain' => $theme->get( 'TextDomain' ),
            ),
            'version_installed' => $theme->get( 'Version' ),
            'version_available' => $inventory['version_available'] ?? '',
            'active' => ! empty( $inventory['active'] ),
            'update_available' => ! empty( $inventory['update_available'] ),
            'block_theme' => ! empty( $inventory['block_theme'] ),
            'is_child_theme' => ! empty( $inventory['is_child_theme'] ),
            'runtime_signals' => array(
                'theme_root' => $theme->get_theme_root(),
                'stylesheet_directory_exists' => is_dir( $theme->get_stylesheet_directory() ),
                'template_directory_exists' => is_dir( $theme->get_template_directory() ),
            ),
            'compatibility' => array(
                'site_wp_version' => get_bloginfo( 'version' ),
                'site_php_version' => PHP_VERSION,
                'requires_php' => $theme->get( 'RequiresPHP' ),
                'requires_wp' => $theme->get( 'RequiresWP' ),
            ),
            'available_actions' => array_values( array_filter( array(
                empty( $inventory['active'] ) ? 'activate' : null,
                ! empty( $inventory['update_available'] ) ? 'update' : null,
                empty( $inventory['active'] ) ? 'delete' : null,
            ) ) ),
            'risk_level' => $inventory['risk_level'] ?? 'low',
        );
    }

    private function resolve_theme_apply_targets( array $input ): array {
        $targets = array();
        if ( ! empty( $input['targets'] ) && is_array( $input['targets'] ) ) {
            foreach ( $input['targets'] as $target ) {
                if ( is_array( $target ) && ! empty( $target['stylesheet'] ) ) {
                    $targets[] = sanitize_text_field( (string) $target['stylesheet'] );
                }
            }
        }
        if ( empty( $targets ) ) {
            $filters = isset( $input['filters'] ) && is_array( $input['filters'] ) ? $input['filters'] : array();
            $search = isset( $filters['search'] ) ? sanitize_text_field( (string) $filters['search'] ) : '';
            foreach ( $this->build_theme_inventory() as $item ) {
                if ( $this->theme_matches_filters( $item, $filters, $search ) ) {
                    $targets[] = $item['stylesheet'];
                }
            }
        }
        return array_values( array_unique( array_filter( $targets ) ) );
    }

    private function validate_theme_action( string $action, \WP_Theme $theme ): array {
        $inventory = $this->build_theme_inspection( $theme );
        $reasons = array();
        if ( 'activate' === $action && ! empty( $inventory['active'] ) ) {
            $reasons[] = __( 'El tema ya está activo.', 'wpgpt-mcp-bridge' );
        }
        if ( 'update' === $action && empty( $inventory['update_available'] ) ) {
            $reasons[] = __( 'El tema no tiene actualización disponible.', 'wpgpt-mcp-bridge' );
        }
        if ( 'delete' === $action && ! empty( $inventory['active'] ) ) {
            $reasons[] = __( 'No se puede eliminar el tema activo.', 'wpgpt-mcp-bridge' );
        }
        return $reasons;
    }

    public function get_privacy_page(): array {
        return array( 'privacy_policy_page_id' => (int) get_option( 'wp_page_for_privacy_policy', 0 ) );
    }

    public function get_discussion_settings(): array {
        return array(
            'default_ping_status' => (string) get_option( 'default_ping_status', 'open' ),
            'default_comment_status' => (string) get_option( 'default_comment_status', 'open' ),
            'comment_registration' => (bool) get_option( 'comment_registration', false ),
            'close_comments_for_old_posts' => (bool) get_option( 'close_comments_for_old_posts', false ),
            'close_comments_days_old' => (int) get_option( 'close_comments_days_old', 14 ),
            'thread_comments' => (bool) get_option( 'thread_comments', false ),
            'thread_comments_depth' => (int) get_option( 'thread_comments_depth', 5 ),
        );
    }

    public function update_discussion_settings( array $input ): array {
        foreach ( array( 'default_ping_status', 'default_comment_status', 'comment_registration', 'close_comments_for_old_posts', 'close_comments_days_old', 'thread_comments', 'thread_comments_depth' ) as $key ) {
            if ( ! array_key_exists( $key, $input ) ) {
                continue;
            }
            $value = $input[ $key ];
            if ( in_array( $key, array( 'comment_registration', 'close_comments_for_old_posts', 'thread_comments' ), true ) ) {
                $value = (bool) $value;
            } elseif ( in_array( $key, array( 'close_comments_days_old', 'thread_comments_depth' ), true ) ) {
                $value = absint( $value );
            } else {
                $value = sanitize_text_field( (string) $value );
            }
            update_option( $key, $value, false );
        }
        return array( 'updated' => true ) + $this->get_discussion_settings();
    }

    public function get_writing_settings(): array {
        return array(
            'default_category' => (int) get_option( 'default_category', 1 ),
            'default_post_format' => (string) get_option( 'default_post_format', '0' ),
            'use_smilies' => (bool) get_option( 'use_smilies', true ),
            'default_link_category' => (int) get_option( 'default_link_category', 2 ),
        );
    }

    public function update_writing_settings( array $input ): array {
        foreach ( array( 'default_category', 'default_post_format', 'use_smilies', 'default_link_category' ) as $key ) {
            if ( ! array_key_exists( $key, $input ) ) {
                continue;
            }
            $value = $input[ $key ];
            if ( in_array( $key, array( 'default_category', 'default_link_category' ), true ) ) {
                $value = absint( $value );
            } elseif ( 'use_smilies' === $key ) {
                $value = (bool) $value;
            } else {
                $value = sanitize_text_field( (string) $value );
            }
            update_option( $key, $value, false );
        }
        return array( 'updated' => true ) + $this->get_writing_settings();
    }

    public function get_reading_settings(): array {
        return array(
            'show_on_front'  => get_option( 'show_on_front' ),
            'page_on_front'  => (int) get_option( 'page_on_front' ),
            'page_for_posts' => (int) get_option( 'page_for_posts' ),
            'posts_per_page' => (int) get_option( 'posts_per_page' ),
        );
    }

    public function update_reading_settings( array $input ): array {
        foreach ( array( 'show_on_front', 'page_on_front', 'page_for_posts', 'posts_per_page' ) as $option_name ) {
            if ( ! array_key_exists( $option_name, $input ) ) {
                continue;
            }
            $value = $input[ $option_name ];
            if ( in_array( $option_name, array( 'page_on_front', 'page_for_posts', 'posts_per_page' ), true ) ) {
                $value = absint( $value );
            } else {
                $value = sanitize_text_field( (string) $value );
            }
            update_option( $option_name, $value, false );
        }
        return array( 'updated' => true ) + $this->get_reading_settings();
    }

    public function get_permalink_settings(): array {
        return array( 'permalink_structure' => (string) get_option( 'permalink_structure', '' ) );
    }

    public function update_permalink_settings( array $input ): array|WP_Error {
        $structure = isset( $input['permalink_structure'] ) ? (string) $input['permalink_structure'] : '';
        update_option( 'permalink_structure', $structure, false );
        flush_rewrite_rules( false );
        return array( 'updated' => true, 'permalink_structure' => (string) get_option( 'permalink_structure', '' ) );
    }

    public function set_homepage( array $input ): array|WP_Error {
        $page_on_front  = isset( $input['page_on_front'] ) ? absint( $input['page_on_front'] ) : 0;
        $page_for_posts = isset( $input['page_for_posts'] ) ? absint( $input['page_for_posts'] ) : 0;
        if ( $page_on_front <= 0 || 'page' !== get_post_type( $page_on_front ) ) {
            return new WP_Error( 'wpgpt_invalid_front_page', __( 'Debes indicar una página válida para la portada.', 'wpgpt-mcp-bridge' ), array( 'status' => 400 ) );
        }
        update_option( 'show_on_front', 'page', false );
        update_option( 'page_on_front', $page_on_front, false );
        if ( $page_for_posts > 0 ) {
            update_option( 'page_for_posts', $page_for_posts, false );
        }
        return array( 'updated' => true ) + $this->get_reading_settings();
    }


    public function query_options( array $input = array() ): array|WP_Error {
        $scope = sanitize_key( (string) ( $input['scope'] ?? 'options' ) );
        if ( 'autoload_audit' === $scope ) {
            $items = $this->autoload_audit_items( isset( $input['limit'] ) ? (int) $input['limit'] : 20 );
            return array( 'summary' => array( 'scope' => $scope, 'returned' => count( $items ) ), 'items' => $items, 'warnings' => array(), 'next_actions' => array() );
        }
        if ( 'transients' === $scope ) {
            return $this->inspect_options( array( 'scope' => 'transient', 'search' => $input['search'] ?? '', 'keys' => $input['filters']['keys'] ?? array() ) );
        }
        $result = $this->search_whitelisted_options( array( 'search' => $input['search'] ?? '', 'limit' => $input['limit'] ?? 20 ) );
        return array( 'summary' => array( 'scope' => 'options', 'returned' => (int) ( $result['count'] ?? 0 ) ), 'items' => $result['items'] ?? array(), 'warnings' => array(), 'next_actions' => array() );
    }

    public function inspect_options( array $input = array() ): array|WP_Error {
        global $wpdb;
        $scope = sanitize_key( (string) ( $input['scope'] ?? 'option' ) );
        if ( 'autoload_audit' === $scope ) {
            $items = $this->autoload_audit_items( 50 );
            return array( 'summary' => array( 'scope' => $scope, 'inspected' => count( $items ) ), 'items' => $items, 'warnings' => array(), 'next_actions' => array() );
        }
        if ( 'transient' === $scope ) {
            $keys = array();
            if ( ! empty( $input['key'] ) ) { $keys[] = sanitize_text_field( (string) $input['key'] ); }
            foreach ( (array) ( $input['keys'] ?? array() ) as $key ) { $keys[] = sanitize_text_field( (string) $key ); }
            $search = sanitize_text_field( (string) ( $input['search'] ?? '' ) );
            if ( empty( $keys ) ) {
                $like = '_transient_%' . ( '' !== $search ? $wpdb->esc_like( $search ) . '%' : '' );
                $rows = $wpdb->get_results( $wpdb->prepare( "SELECT option_name, option_value FROM {$wpdb->options} WHERE option_name LIKE %s ORDER BY option_name ASC LIMIT %d", $like, 20 ), ARRAY_A );
                return array( 'summary' => array( 'scope' => 'transient', 'inspected' => count( $rows ) ), 'items' => $rows, 'warnings' => array(), 'next_actions' => array() );
            }
            $items = array();
            foreach ( $keys as $key ) {
                $normalized = preg_replace( '/^_transient_/', '', $key );
                $items[] = array( 'key' => $normalized, 'value' => get_transient( $normalized ) );
            }
            return array( 'summary' => array( 'scope' => 'transient', 'inspected' => count( $items ) ), 'items' => $items, 'warnings' => array(), 'next_actions' => array() );
        }
        $names = array();
        if ( ! empty( $input['option_name'] ) ) { $names[] = sanitize_key( (string) $input['option_name'] ); }
        foreach ( (array) ( $input['option_names'] ?? array() ) as $name ) { $names[] = sanitize_key( (string) $name ); }
        $items = array();
        foreach ( array_unique( $names ) as $name ) {
            if ( in_array( $name, self::OPTION_WHITELIST, true ) ) {
                $items[] = array( 'option_name' => $name, 'option_value' => get_option( $name ), 'autoload' => wp_load_alloptions()[ $name ] ?? null );
            }
        }
        return array( 'summary' => array( 'scope' => 'option', 'inspected' => count( $items ) ), 'items' => $items, 'warnings' => empty( $items ) ? array( __( 'No se han encontrado opciones con esos filtros.', 'wpgpt-mcp-bridge' ) ) : array(), 'next_actions' => array() );
    }

    public function apply_options( array $input = array() ): array|WP_Error {
        $action = sanitize_key( (string) ( $input['action'] ?? '' ) );
        $dry_run = ! empty( $input['dry_run'] );
        $payload = isset( $input['payload'] ) && is_array( $input['payload'] ) ? $input['payload'] : array();
        if ( '' === $action ) { return new WP_Error( 'wpgpt_options_action_invalid', __( 'Debes indicar una acción válida.', 'wpgpt-mcp-bridge' ), array( 'status' => 400 ) ); }
        if ( $dry_run ) { return array( 'summary' => array( 'action' => $action, 'dry_run' => true ), 'items' => array(), 'warnings' => array(), 'blocked' => array(), 'next_actions' => array( __( 'Repite la misma llamada con dry_run=false para aplicar los cambios validados.', 'wpgpt-mcp-bridge' ) ) ); }
        if ( 'update_option' === $action ) { return array( 'summary' => array( 'action' => $action, 'dry_run' => false ), 'items' => array( $this->update_whitelisted_option( $payload ) ), 'warnings' => array(), 'blocked' => array(), 'next_actions' => array() ); }
        if ( 'delete_transient' === $action ) { $keys = (array) ( $payload['keys'] ?? array() ); return array( 'summary' => array( 'action' => $action, 'dry_run' => false ), 'items' => array( $this->delete_transients_items( $keys ) ), 'warnings' => array(), 'blocked' => array(), 'next_actions' => array() ); }
        if ( 'delete_expired_transients' === $action ) { if ( function_exists( 'delete_expired_transients' ) ) { delete_expired_transients( true ); } return array( 'summary' => array( 'action' => $action, 'dry_run' => false ), 'items' => array( array( 'deleted' => 'expired' ) ), 'warnings' => array(), 'blocked' => array(), 'next_actions' => array() ); }
        return new WP_Error( 'wpgpt_options_action_invalid', __( 'Acción de options no válida.', 'wpgpt-mcp-bridge' ), array( 'status' => 400 ) );
    }

    public function query_settings( array $input = array() ): array|WP_Error {
        $scope = sanitize_key( (string) ( $input['scope'] ?? 'all' ) );
        $items = $this->build_settings_items( $scope );
        return array( 'summary' => array( 'scope' => $scope, 'returned' => count( $items ) ), 'items' => $items, 'warnings' => array(), 'next_actions' => array() );
    }

    public function inspect_settings( array $input = array() ): array|WP_Error {
        $scope = sanitize_key( (string) ( $input['scope'] ?? 'all' ) );
        $items = $this->build_settings_items( $scope );
        return array( 'summary' => array( 'scope' => $scope, 'inspected' => count( $items ) ), 'items' => $items, 'warnings' => array(), 'next_actions' => array() );
    }

    public function apply_settings( array $input = array() ): array|WP_Error {
        $action = sanitize_key( (string) ( $input['action'] ?? '' ) );
        $dry_run = ! empty( $input['dry_run'] );
        $payload = isset( $input['payload'] ) && is_array( $input['payload'] ) ? $input['payload'] : array();
        if ( $dry_run ) { return array( 'summary' => array( 'action' => $action, 'dry_run' => true ), 'items' => array(), 'warnings' => array(), 'blocked' => array(), 'next_actions' => array( __( 'Repite la misma llamada con dry_run=false para aplicar los cambios validados.', 'wpgpt-mcp-bridge' ) ) ); }
        $result = match ( $action ) {
            'update_general' => $this->update_general_settings( $payload ),
            'set_privacy_page' => $this->set_privacy_page( $payload ),
            'update_discussion' => $this->update_discussion_settings( $payload ),
            'update_writing' => $this->update_writing_settings( $payload ),
            'update_reading' => $this->update_reading_settings( $payload ),
            'update_permalinks' => $this->update_permalink_settings( $payload ),
            'set_homepage' => $this->set_homepage( $payload ),
            default => new WP_Error( 'wpgpt_settings_action_invalid', __( 'Acción de settings no válida.', 'wpgpt-mcp-bridge' ), array( 'status' => 400 ) ),
        };
        if ( is_wp_error( $result ) ) { return $result; }
        return array( 'summary' => array( 'action' => $action, 'dry_run' => false ), 'items' => array( $result ), 'warnings' => array(), 'blocked' => array(), 'next_actions' => array() );
    }

    private function autoload_audit_items( int $limit = 20 ): array {
        global $wpdb;
        $rows = $wpdb->get_results( $wpdb->prepare( "SELECT option_name, LENGTH(option_value) AS bytes FROM {$wpdb->options} WHERE autoload = 'yes' ORDER BY bytes DESC LIMIT %d", max( 1, min( 200, $limit ) ) ), ARRAY_A );
        return array_values( $rows ?: array() );
    }

    private function delete_transients_items( array $keys ): array {
        $deleted = array();
        foreach ( $keys as $key ) { $normalized = preg_replace( '/^_transient_/', '', sanitize_text_field( (string) $key ) ); delete_transient( $normalized ); $deleted[] = $normalized; }
        return array( 'deleted_keys' => $deleted );
    }

    private function build_settings_items( string $scope ): array {
        $all = array(
            'general' => $this->get_general_settings(),
            'discussion' => $this->get_discussion_settings(),
            'writing' => $this->get_writing_settings(),
            'reading' => $this->get_reading_settings(),
            'permalinks' => $this->get_permalink_settings(),
            'privacy' => array( 'page_id' => (int) get_option( 'wp_page_for_privacy_policy' ) ),
        );
        if ( 'all' !== $scope && isset( $all[ $scope ] ) ) { return array( array( 'scope' => $scope, 'item' => $all[ $scope ] ) ); }
        $items = array(); foreach ( $all as $key => $value ) { $items[] = array( 'scope' => $key, 'item' => $value ); } return $items;
    }

}
