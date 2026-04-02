<?php

namespace WPGPT\MCPBridge;

if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

require_once __DIR__ . '/class-abilities.php';
require_once __DIR__ . '/class-admin.php';
require_once __DIR__ . '/class-security.php';
require_once __DIR__ . '/class-updater.php';

class Plugin {
    private static ?Plugin $instance = null;

    public static function instance(): Plugin {
        if ( null === self::$instance ) {
            self::$instance = new self();
        }

        return self::$instance;
    }

    public static function activate(): void {
        if ( false === get_option( 'wpgpt_mcp_bridge_read_only' ) ) {
            update_option( 'wpgpt_mcp_bridge_read_only', '1', false );
        }
        if ( false === get_option( 'wpgpt_mcp_bridge_allow_delete' ) ) {
            update_option( 'wpgpt_mcp_bridge_allow_delete', '0', false );
        }
        if ( false === get_option( 'wpgpt_mcp_bridge_fs_read' ) ) {
            update_option( 'wpgpt_mcp_bridge_fs_read', '1', false );
        }
        if ( false === get_option( 'wpgpt_mcp_bridge_fs_write' ) ) {
            update_option( 'wpgpt_mcp_bridge_fs_write', '0', false );
        }
        if ( false === get_option( 'wpgpt_mcp_bridge_fs_delete' ) ) {
            update_option( 'wpgpt_mcp_bridge_fs_delete', '0', false );
        }
    }

    public static function deactivate(): void {}

    private function __construct() {
        add_action( 'plugins_loaded', array( $this, 'boot' ) );
    }

    public function boot(): void {
        load_plugin_textdomain( 'wpgpt-mcp-bridge', false, dirname( plugin_basename( WPGPT_MCP_BRIDGE_FILE ) ) . '/languages' );

        Security::init();
        Admin::init();
        Abilities::init();
        Updater::init();

        \WPGPT\MCPBridge\Structure\Post_Type_Manager::init();
        \WPGPT\MCPBridge\Structure\Taxonomy_Manager::init();
        \WPGPT\MCPBridge\Structure\Metabox_Manager::init();
        if ( class_exists( '\\WPGPT\\MCPBridge\\Maintenance\\Maintenance_Service' ) ) {
            \WPGPT\MCPBridge\Maintenance\Maintenance_Service::init();
        }

        if ( class_exists( '\\WP\\MCP\\Core\\McpAdapter' ) ) {
            \WP\MCP\Core\McpAdapter::instance();
        }

        add_action( 'mcp_adapter_init', array( $this, 'register_mcp_server' ) );
    }

    public function register_mcp_server( $adapter ): void {
        if ( ! class_exists( '\\WP\\MCP\\Core\\McpAdapter' ) ) {
            return;
        }

        $adapter   = \WP\MCP\Core\McpAdapter::instance();
        $abilities = Abilities::get_all_registered_names();

        $adapter->create_server(
            'wpgpt-mcp-server',
            'wpgpt-mcp',
            'mcp',
            'WPGPT MCP Server',
            'Servidor MCP agregado para ChatGPT/VS Code con abilities de WordPress, diagnóstico y análisis seguro.',
            WPGPT_MCP_BRIDGE_VERSION,
            array(
                \WP\MCP\Transport\HttpTransport::class,
            ),
            \WP\MCP\Infrastructure\ErrorHandling\ErrorLogMcpErrorHandler::class,
            \WP\MCP\Infrastructure\Observability\NullMcpObservabilityHandler::class,
            $abilities,
            array(),
            array()
        );
    }
}
