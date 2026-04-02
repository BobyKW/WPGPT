<?php

namespace WPGPT\MCPBridge;

if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

class Security {
    private const OPTION_TOKEN_HASH        = 'wpgpt_mcp_bridge_token_hash';
    private const OPTION_TOKEN_LAST4       = 'wpgpt_mcp_bridge_token_last4';
    private const OPTION_USER_ID           = 'wpgpt_mcp_bridge_user_id';
    private const OPTION_READ_ONLY         = 'wpgpt_mcp_bridge_read_only';
    private const OPTION_ALLOW_DELETE      = 'wpgpt_mcp_bridge_allow_delete';
    private const OPTION_FS_READ           = 'wpgpt_mcp_bridge_fs_read';
    private const OPTION_FS_WRITE          = 'wpgpt_mcp_bridge_fs_write';
    private const OPTION_FS_DELETE         = 'wpgpt_mcp_bridge_fs_delete';
    private const OPTION_ALLOWED_ABILITIES = 'wpgpt_mcp_bridge_allowed_abilities';
    private const OPTION_ALLOWED_TABLES    = 'wpgpt_mcp_bridge_allowed_tables';

    public static function init(): void {
        add_filter( 'determine_current_user', array( __CLASS__, 'maybe_authenticate_mcp_user' ), 20 );
    }

    public static function generate_token(): string {
        $token = wp_generate_password( 40, false, false );
        update_option( self::OPTION_TOKEN_HASH, wp_hash_password( $token ), false );
        update_option( self::OPTION_TOKEN_LAST4, substr( $token, -4 ), false );

        return $token;
    }

    public static function has_token(): bool {
        $hash = get_option( self::OPTION_TOKEN_HASH );
        return is_string( $hash ) && '' !== $hash;
    }

    public static function token_last_four(): string {
        $last4 = get_option( self::OPTION_TOKEN_LAST4, '' );
        return is_string( $last4 ) ? $last4 : '';
    }

    public static function set_user_id( int $user_id ): void {
        update_option( self::OPTION_USER_ID, max( 0, $user_id ), false );
    }

    public static function get_user_id(): int {
        return absint( get_option( self::OPTION_USER_ID, 0 ) );
    }

    public static function update_read_only( bool $read_only ): void {
        update_option( self::OPTION_READ_ONLY, $read_only ? '1' : '0', false );
    }

    public static function get_read_only(): bool {
        return '1' === (string) get_option( self::OPTION_READ_ONLY, '1' );
    }

    public static function update_allow_delete( bool $allow_delete ): void {
        update_option( self::OPTION_ALLOW_DELETE, $allow_delete ? '1' : '0', false );
    }

    public static function get_allow_delete(): bool {
        return '1' === (string) get_option( self::OPTION_ALLOW_DELETE, '0' );
    }

    public static function update_fs_read( bool $enabled ): void {
        update_option( self::OPTION_FS_READ, $enabled ? '1' : '0', false );
    }

    public static function get_fs_read(): bool {
        return '1' === (string) get_option( self::OPTION_FS_READ, '1' );
    }

    public static function update_fs_write( bool $enabled ): void {
        update_option( self::OPTION_FS_WRITE, $enabled ? '1' : '0', false );
    }

    public static function get_fs_write(): bool {
        return '1' === (string) get_option( self::OPTION_FS_WRITE, '0' );
    }

    public static function update_fs_delete( bool $enabled ): void {
        update_option( self::OPTION_FS_DELETE, $enabled ? '1' : '0', false );
    }

    public static function get_fs_delete(): bool {
        return '1' === (string) get_option( self::OPTION_FS_DELETE, '0' );
    }

    public static function update_allowed_abilities( array $abilities, array $all_declared ): void {
        $all_declared = array_values( array_unique( array_map( 'strval', $all_declared ) ) );
        $allowed      = array_values( array_intersect( $all_declared, array_values( array_unique( array_map( 'strval', $abilities ) ) ) ) );

        if ( count( $allowed ) === count( $all_declared ) ) {
            delete_option( self::OPTION_ALLOWED_ABILITIES );
            return;
        }

        update_option( self::OPTION_ALLOWED_ABILITIES, $allowed, false );
    }

    public static function get_allowed_abilities( array $all_declared ): array {
        $saved = get_option( self::OPTION_ALLOWED_ABILITIES, null );
        if ( ! is_array( $saved ) || empty( $saved ) ) {
            return array_values( array_unique( array_map( 'strval', $all_declared ) ) );
        }

        return array_values( array_intersect( array_values( array_unique( array_map( 'strval', $all_declared ) ) ), array_values( array_unique( array_map( 'strval', $saved ) ) ) ) );
    }

    public static function is_ability_enabled( string $ability_name, array $all_declared = array() ): bool {
        if ( empty( $all_declared ) ) {
            $all_declared = Abilities::get_all_registered_names();
        }

        return in_array( $ability_name, self::get_allowed_abilities( $all_declared ), true );
    }

    public static function update_allowed_tables( array $table_keys, array $all_supported ): void {
        $all_supported = array_values( array_unique( array_map( 'sanitize_key', $all_supported ) ) );
        $allowed       = array_values( array_intersect( $all_supported, array_values( array_unique( array_map( 'sanitize_key', $table_keys ) ) ) ) );

        if ( count( $allowed ) === count( $all_supported ) ) {
            delete_option( self::OPTION_ALLOWED_TABLES );
            return;
        }

        update_option( self::OPTION_ALLOWED_TABLES, $allowed, false );
    }

    public static function get_allowed_tables( array $all_supported ): array {
        $saved = get_option( self::OPTION_ALLOWED_TABLES, null );
        if ( ! is_array( $saved ) || empty( $saved ) ) {
            return array_values( array_unique( array_map( 'sanitize_key', $all_supported ) ) );
        }

        return array_values( array_intersect( array_values( array_unique( array_map( 'sanitize_key', $all_supported ) ) ), array_values( array_unique( array_map( 'sanitize_key', $saved ) ) ) ) );
    }

    public static function is_table_enabled( string $table_key, array $all_supported = array() ): bool {
        if ( empty( $all_supported ) ) {
            $all_supported = array_keys( ( new \WPGPT\MCPBridge\Database\Database_Catalog() )->supported_tables() );
        }

        return in_array( sanitize_key( $table_key ), self::get_allowed_tables( $all_supported ), true );
    }



    public static function is_ability_exposed_by_policy( string $ability_name, ?array $ability = null ): bool {
        $permission_method = self::extract_permission_method( $ability );

        if ( 'can_read_files' === $permission_method && ! self::get_fs_read() ) {
            return false;
        }

        if ( 'can_write_files' === $permission_method && ( self::get_read_only() || ! self::get_fs_write() ) ) {
            return false;
        }

        if ( 'can_delete_files' === $permission_method && ( self::get_read_only() || ! self::get_fs_delete() ) ) {
            return false;
        }

        if ( in_array( $permission_method, array( 'can_write_content', 'can_write_structure', 'can_write_plugins' ), true ) && self::get_read_only() ) {
            return false;
        }

        if ( in_array( $permission_method, array( 'can_delete_content', 'can_delete_structure', 'can_delete_plugins' ), true ) && ( self::get_read_only() || ! self::get_allow_delete() ) ) {
            return false;
        }

        if ( self::get_read_only() && in_array( $ability_name, self::read_only_hidden_abilities(), true ) ) {
            return false;
        }

        return true;
    }

    private static function extract_permission_method( ?array $ability ): string {
        if ( ! is_array( $ability ) || ! isset( $ability['permission_callback'] ) || ! is_array( $ability['permission_callback'] ) ) {
            return '';
        }

        if ( ! isset( $ability['permission_callback'][1] ) || ! is_string( $ability['permission_callback'][1] ) ) {
            return '';
        }

        return $ability['permission_callback'][1];
    }

    private static function read_only_hidden_abilities(): array {
        return array(
            'wpgpt/options-update-whitelisted',
            'wpgpt/theme-activate',
            'wpgpt/general-settings-update',
            'wpgpt/privacy-page-set',
            'wpgpt/discussion-settings-update',
            'wpgpt/writing-settings-update',
            'wpgpt/reading-settings-update',
            'wpgpt/permalink-settings-update',
            'wpgpt/homepage-set',
            'wpgpt/menu-create',
            'wpgpt/menu-item-create',
            'wpgpt/menu-item-update',
            'wpgpt/nav-location-assign',
            'wpgpt/seo-settings-update',
            'wpgpt/theme-mods-update',
            'wpgpt/site-identity-update',
            'wpgpt/block-entity-upsert',
            'wpgpt/acf-values-update',
            'wpgpt/wc-resource-upsert',
            'wpgpt/wc-order-action',
            'wpgpt/import-run',
            'wpgpt/user-create',
            'wpgpt/user-update',
            'wpgpt/role-create',
            'wpgpt/capability-grant',
            'wpgpt/capability-revoke',
            'wpgpt/maintenance-media-regenerate',
            'wpgpt/maintenance-search-replace',
            'wpgpt/maintenance-mode-set',
        );
    }

    public static function generate_application_password( int $user_id, string $app_name = 'WPGPT MCP Bridge' ) {
        $user_id = absint( $user_id );
        if ( $user_id <= 0 ) {
            return new \WP_Error( 'wpgpt_invalid_user', __( 'Selecciona un usuario MCP válido antes de generar la contraseña de aplicación.', 'wpgpt-mcp-bridge' ) );
        }

        if ( ! class_exists( '\WP_Application_Passwords' ) || ! method_exists( '\WP_Application_Passwords', 'create_new_application_password' ) ) {
            return new \WP_Error( 'wpgpt_app_passwords_unavailable', __( 'Este sitio no tiene disponible la API nativa de Application Passwords.', 'wpgpt-mcp-bridge' ) );
        }

        $user = get_user_by( 'id', $user_id );
        if ( ! $user ) {
            return new \WP_Error( 'wpgpt_invalid_user', __( 'No se ha encontrado el usuario MCP seleccionado.', 'wpgpt-mcp-bridge' ) );
        }

        $result = \WP_Application_Passwords::create_new_application_password(
            $user_id,
            array(
                'name'   => sprintf( '%s • %s', $app_name, wp_date( 'Y-m-d H:i' ) ),
                'app_id' => home_url( '/' ),
            )
        );

        if ( is_wp_error( $result ) ) {
            return $result;
        }

        if ( ! is_array( $result ) || empty( $result[0] ) || empty( $result[1] ) || ! is_array( $result[1] ) ) {
            return new \WP_Error( 'wpgpt_app_password_creation_failed', __( 'No se pudo crear la contraseña de aplicación.', 'wpgpt-mcp-bridge' ) );
        }

        return array(
            'password' => (string) $result[0],
            'item'     => $result[1],
            'user'     => $user,
        );
    }

    public static function get_best_username_for_application_password( int $user_id ): string {
        $user = get_user_by( 'id', $user_id );
        if ( ! $user ) {
            return '';
        }

        if ( ! empty( $user->user_email ) && is_email( $user->user_email ) ) {
            return (string) $user->user_email;
        }

        return (string) $user->user_login;
    }

    public static function maybe_authenticate_mcp_user( $user_id ) {
        if ( ! empty( $user_id ) ) {
            return $user_id;
        }

        $token = self::extract_token_from_request();
        if ( '' === $token ) {
            return $user_id;
        }

        $hash = get_option( self::OPTION_TOKEN_HASH, '' );
        if ( ! is_string( $hash ) || '' === $hash || ! wp_check_password( $token, $hash ) ) {
            return $user_id;
        }

        $configured_user_id = self::get_user_id();
        if ( $configured_user_id > 0 ) {
            return $configured_user_id;
        }

        return $user_id;
    }

    private static function extract_token_from_request(): string {
        if ( isset( $_GET['wpgpt_token'] ) ) {
            return sanitize_text_field( wp_unslash( (string) $_GET['wpgpt_token'] ) );
        }

        $header = '';
        if ( isset( $_SERVER['HTTP_AUTHORIZATION'] ) ) {
            $header = (string) $_SERVER['HTTP_AUTHORIZATION'];
        } elseif ( isset( $_SERVER['REDIRECT_HTTP_AUTHORIZATION'] ) ) {
            $header = (string) $_SERVER['REDIRECT_HTTP_AUTHORIZATION'];
        }

        if ( preg_match( '/Bearer\s+(.+)$/i', $header, $matches ) ) {
            return sanitize_text_field( trim( (string) $matches[1] ) );
        }

        return '';
    }
}
