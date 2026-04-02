<?php
/**
 * Plugin Name: WPGPT - MCP Extensor & ChatGPT Connection
 * Description: Extends MCP Adapter with secure WordPress tools and a simple connection flow for ChatGPT, VS Code, and other MCP-compatible clients.
 * Version: 2.2.0
 * Author: WPGPT
 * Plugin URI: https://github.com/BobyKW/WPGPT
 * Update URI: https://github.com/BobyKW/WPGPT
 * License: GPLv2 or later
 * License URI: https://www.gnu.org/licenses/gpl-2.0.html
 * Text Domain: wpgpt-mcp-bridge
 */

if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

define( 'WPGPT_MCP_BRIDGE_VERSION', '2.2.0' );
define( 'WPGPT_MCP_BRIDGE_FILE', __FILE__ );
define( 'WPGPT_MCP_BRIDGE_DIR', plugin_dir_path( __FILE__ ) );

require_once WPGPT_MCP_BRIDGE_DIR . 'includes/class-plugin.php';

register_activation_hook( __FILE__, array( 'WPGPT\\MCPBridge\\Plugin', 'activate' ) );
register_deactivation_hook( __FILE__, array( 'WPGPT\\MCPBridge\\Plugin', 'deactivate' ) );

WPGPT\MCPBridge\Plugin::instance();
