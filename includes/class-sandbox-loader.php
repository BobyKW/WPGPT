<?php

namespace WPGPT\MCPBridge;

if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

class Sandbox_Loader {
    public const OPTION_ENABLED = 'wpgpt_mcp_sandbox_loader_enabled';
    private const MAX_FILES = 100;

    public static function root(): string { return trailingslashit( WP_CONTENT_DIR ) . 'wpgpt-sandbox'; }
    public static function crashed_file(): string { return trailingslashit( self::root() ) . '.crashed'; }
    public static function loading_file(): string { return trailingslashit( self::root() ) . '.loading'; }
    public static function is_enabled(): bool { return '1' === (string) get_option( self::OPTION_ENABLED, '0' ); }
    public static function set_enabled( bool $enabled ): void { update_option( self::OPTION_ENABLED, $enabled ? '1' : '0', false ); }
    public static function ensure_root(): bool { return is_dir( self::root() ) || wp_mkdir_p( self::root() ); }
    public static function is_safe_mode(): bool { return file_exists( self::crashed_file() ); }

    public static function crash_info(): array {
        if ( ! file_exists( self::crashed_file() ) ) { return array(); }
        $raw = file_get_contents( self::crashed_file() );
        $json = is_string( $raw ) ? json_decode( $raw, true ) : null;
        return is_array( $json ) ? $json : array( 'message' => (string) $raw );
    }

    public static function clear_crash(): bool { return ! file_exists( self::crashed_file() ) || @unlink( self::crashed_file() ); }

    public static function init(): void {
        self::ensure_root();
        add_action( 'admin_notices', array( __CLASS__, 'admin_notice' ) );
        self::load();
    }

    public static function admin_notice(): void {
        if ( ! current_user_can( 'manage_options' ) || ! self::is_safe_mode() ) { return; }
        $info = self::crash_info();
        $message = isset( $info['message'] ) ? (string) $info['message'] : __( 'Error fatal detectado durante la carga del sandbox.', 'wpgpt-mcp-bridge' );
        echo '<div class="notice notice-error"><p><strong>' . esc_html__( 'WPGPT Sandbox: safe mode activo.', 'wpgpt-mcp-bridge' ) . '</strong> ' . esc_html( $message ) . ' ' . esc_html__( 'Los PHP del sandbox no se cargan hasta corregir el error y salir de safe mode.', 'wpgpt-mcp-bridge' ) . '</p></div>';
    }

    private static function write_crash( array $payload ): void {
        self::ensure_root();
        $payload['time_gmt'] = gmdate( 'c' );
        @file_put_contents( self::crashed_file(), wp_json_encode( $payload, JSON_PRETTY_PRINT ), LOCK_EX );
        if ( file_exists( self::loading_file() ) ) { @unlink( self::loading_file() ); }
    }

    private static function detect_previous_incomplete_load(): void {
        if ( file_exists( self::loading_file() ) && ! file_exists( self::crashed_file() ) ) {
            $raw = file_get_contents( self::loading_file() );
            $info = is_string( $raw ) ? json_decode( $raw, true ) : array();
            self::write_crash( array(
                'type' => 'incomplete_previous_load',
                'message' => 'Previous sandbox loading did not complete. Safe mode was enabled.',
                'sandbox_file' => is_array( $info ) && isset( $info['current_file'] ) ? $info['current_file'] : null,
            ) );
        }
    }

    private static function files(): array {
        $files = glob( trailingslashit( self::root() ) . '*.php' );
        if ( ! is_array( $files ) ) { return array(); }
        $files = array_filter( $files, static function ( string $file ): bool { return is_file( $file ) && ! str_ends_with( $file, '.disabled' ); } );
        sort( $files, SORT_NATURAL | SORT_FLAG_CASE );
        return array_slice( array_values( $files ), 0, self::MAX_FILES );
    }

    private static function load(): void {
        if ( ! self::ensure_root() ) { return; }
        self::detect_previous_incomplete_load();
        if ( ! self::is_enabled() || self::is_safe_mode() || ( isset( $_GET['wpgpt_sandbox_safe_mode'] ) && '1' === (string) $_GET['wpgpt_sandbox_safe_mode'] ) ) { return; } // phpcs:ignore WordPress.Security.NonceVerification.Recommended
        $files = self::files();
        if ( empty( $files ) ) { return; }
        $current_file = null;
        register_shutdown_function( static function () use ( &$current_file ): void {
            if ( null === $current_file ) { return; }
            $error = error_get_last();
            if ( null === $error ) { return; }
            if ( empty( $error['type'] ) || ! ( (int) $error['type'] & ( E_ERROR | E_PARSE | E_CORE_ERROR | E_COMPILE_ERROR | E_RECOVERABLE_ERROR ) ) ) { return; }
            $error['sandbox_file'] = $current_file;
            $error['message'] = isset( $error['message'] ) ? (string) $error['message'] : 'Fatal sandbox error.';
            self::write_crash( $error );
        } );
        @file_put_contents( self::loading_file(), wp_json_encode( array( 'started_gmt' => gmdate( 'c' ), 'files' => $files ), JSON_PRETTY_PRINT ), LOCK_EX );
        foreach ( $files as $file ) {
            $current_file = $file;
            @file_put_contents( self::loading_file(), wp_json_encode( array( 'started_gmt' => gmdate( 'c' ), 'current_file' => $file ), JSON_PRETTY_PRINT ), LOCK_EX );
            require_once $file;
        }
        $current_file = null;
        if ( file_exists( self::loading_file() ) ) { @unlink( self::loading_file() ); }
    }
}
