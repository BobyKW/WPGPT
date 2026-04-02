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
}
