<?php

namespace WPGPT\MCPBridge\Environment;

if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

class Environment_Service {
    public function report( array $input ): array {
        global $wpdb;
        $sections = ! empty( $input['sections'] ) && is_array( $input['sections'] ) ? array_map( 'sanitize_key', $input['sections'] ) : array( 'wordpress', 'php', 'database', 'filesystem', 'rest', 'cron', 'debug', 'plugins', 'themes', 'performance' );
        $data = array();
        if ( in_array( 'wordpress', $sections, true ) ) { $data['wordpress'] = array( 'version' => get_bloginfo( 'version' ), 'url' => home_url( '/' ), 'language' => get_locale(), 'multisite' => is_multisite() ); }
        if ( in_array( 'php', $sections, true ) ) { $data['php'] = array( 'version' => phpversion(), 'memory_limit' => ini_get( 'memory_limit' ), 'max_execution_time' => ini_get( 'max_execution_time' ), 'extensions' => get_loaded_extensions() ); }
        if ( in_array( 'database', $sections, true ) ) { $data['database'] = array( 'server_info' => $wpdb->db_server_info(), 'prefix' => $wpdb->prefix, 'charset' => $wpdb->charset, 'tables' => count( $wpdb->get_col( 'SHOW TABLES' ) ) ); }
        if ( in_array( 'filesystem', $sections, true ) ) { $upload = wp_upload_dir(); $data['filesystem'] = array( 'abspath_writable' => wp_is_writable( ABSPATH ), 'uploads_basedir' => $upload['basedir'] ?? '', 'uploads_writable' => ! empty( $upload['basedir'] ) ? wp_is_writable( $upload['basedir'] ) : false ); }
        if ( in_array( 'rest', $sections, true ) ) { $data['rest'] = array( 'rest_url' => rest_url(), 'pretty_permalinks' => get_option( 'permalink_structure' ) ? true : false ); }
        if ( in_array( 'cron', $sections, true ) ) { $cron = _get_cron_array(); $data['cron'] = array( 'event_groups' => is_array( $cron ) ? count( $cron ) : 0, 'spawn_enabled' => ! defined( 'DISABLE_WP_CRON' ) || ! DISABLE_WP_CRON ); }
        if ( in_array( 'debug', $sections, true ) ) { $data['debug'] = array( 'wp_debug' => defined( 'WP_DEBUG' ) && WP_DEBUG, 'script_debug' => defined( 'SCRIPT_DEBUG' ) && SCRIPT_DEBUG, 'wp_debug_log' => defined( 'WP_DEBUG_LOG' ) && WP_DEBUG_LOG ); }
        if ( in_array( 'plugins', $sections, true ) ) { require_once ABSPATH . 'wp-admin/includes/plugin.php'; $plugins = get_plugins(); $active = get_option( 'active_plugins', array() ); $data['plugins'] = array( 'installed' => count( $plugins ), 'active' => count( $active ) ); }
        if ( in_array( 'themes', $sections, true ) ) { $themes = wp_get_themes(); $data['themes'] = array( 'installed' => count( $themes ), 'active' => wp_get_theme()->get( 'Name' ) ); }
        if ( in_array( 'performance', $sections, true ) ) { $autoload_size = (int) $wpdb->get_var( "SELECT SUM(LENGTH(option_value)) FROM {$wpdb->options} WHERE autoload='yes'" ); $data['performance'] = array( 'autoload_bytes' => $autoload_size, 'object_cache' => wp_using_ext_object_cache() ); }
        return array( 'success' => true, 'report' => $data );
    }

    public function audit( array $input ): array {
        $report = $this->report( array() )['report'];
        $critical = array(); $warnings = array(); $info = array();
        if ( version_compare( (string) ( $report['php']['version'] ?? '0' ), '8.0', '<' ) ) { $critical[] = 'PHP version is below 8.0.'; }
        if ( ! empty( $report['debug']['wp_debug'] ) ) { $warnings[] = 'WP_DEBUG is enabled.'; }
        if ( ! empty( $report['performance']['autoload_bytes'] ) && $report['performance']['autoload_bytes'] > 3 * 1024 * 1024 ) { $warnings[] = 'Autoloaded options size is high.'; }
        if ( empty( $report['rest']['pretty_permalinks'] ) ) { $info[] = 'Pretty permalinks are disabled.'; }
        if ( empty( $report['filesystem']['uploads_writable'] ) ) { $critical[] = 'Uploads directory is not writable.'; }
        return array( 'success' => true, 'critical' => $critical, 'warnings' => $warnings, 'info' => $info, 'recommendations' => array_merge( $critical ? array( 'Review critical environment issues before automating write operations.' ) : array(), $warnings ? array( 'Resolve warnings to improve reliability.' ) : array() ) );
    }
}
