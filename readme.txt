=== WPGPT - MCP Extensor & ChatGPT Connection ===
Contributors: wpgpt
Requires at least: 6.6
Tested up to: 6.9
Requires PHP: 8.1
Stable tag: 1.2.0
License: GPLv2 or later
License URI: https://www.gnu.org/licenses/gpl-2.0.html

Standalone MCP bridge for WordPress with controlled abilities, secure sandbox tools, and connection flows for ChatGPT and MCP-compatible clients.

== Description ==

WPGPT exposes a native MCP endpoint from WordPress and lets site owners decide exactly which WordPress abilities are available to ChatGPT and other MCP-compatible clients.

It includes a focused admin interface, token-based ChatGPT connection flow, selectable MCP operating user, permission controls, ability allowlisting, and a secure sandbox for advanced filesystem and PHP-assisted maintenance workflows.

Main features:

* Native standalone MCP endpoint for WordPress.
* MCP Adapter is not required for the ChatGPT connection.
* Bearer-token connection flow for ChatGPT.
* WordPress Application Password helper for compatible editors and clients.
* Selectable MCP operating user.
* Ability allowlist with grouped categories.
* Danger category for advanced filesystem and sandbox abilities.
* Safe mode: block changes.
* Read, write, edit, and delete filesystem permissions.
* Explicit deletion confirmation.
* Secure sandbox at wp-content/wpgpt-sandbox/.
* Optional persistent sandbox PHP loader.
* Safe mode with .crashed marker when sandbox loading fails.
* .disabled support for sandbox PHP files.
* Backups before dangerous file operations.
* JSONL audit log for dangerous actions.
* Dedicated WordPress admin sidebar menu.

== Requirements ==

* WordPress 6.6 or higher
* PHP 8.1 or higher
* Administrator access

MCP Adapter is optional and is not required for WPGPT's native ChatGPT endpoint.

== Installation ==

1. Install and activate WPGPT - MCP Extensor & ChatGPT Connection.
2. Open WPGPT MCP in the WordPress admin sidebar.
3. Select the WordPress user that MCP should operate as.
4. Configure permissions.
5. Choose the abilities that should be exposed.
6. Generate or reuse the MCP token.
7. Copy the full MCP endpoint URL into ChatGPT or your MCP client.

== Usage ==

= ChatGPT =

Use the full tokenized endpoint shown in the plugin settings page.

The endpoint follows this structure:

/wp-json/wpgpt-mcp/v1/YOUR_TOKEN

= Editors and other clients =

For clients that support WordPress credentials, the plugin can generate a WordPress Application Password for the selected user.

= Sandbox =

The sandbox lives at wp-content/wpgpt-sandbox/.

The plugin can manage files inside the sandbox and can optionally load root-level PHP files from the sandbox. If sandbox loading causes a fatal error, WPGPT enters safe mode using a .crashed marker.

== Security ==

The MCP token should be treated like an administrator credential. Keep dangerous abilities disabled unless needed.

Recommended production posture:

* Enable Safe mode: block changes unless actively working.
* Expose only the abilities needed for the current task.
* Keep wpgpt/danger-execute-php disabled by default.
* Keep deletion disabled by default.
* Use a dedicated MCP user.
* Review logs and backups after dangerous actions.

== Frequently Asked Questions ==

= Does this plugin require MCP Adapter? =

No. WPGPT provides its own native MCP endpoint for the ChatGPT connection.

= Do I need to regenerate the token after updating the plugin? =

No. Updating the plugin does not require a new token. Regenerate the token only when you intentionally want to invalidate the previous connection.

= Do I need to regenerate the token after changing abilities? =

No. Save the settings and the next discovery request will use the current allowed abilities.

= Is PHP execution safe? =

PHP execution is powerful and risky. Keep wpgpt/danger-execute-php disabled unless you need it for a controlled task.

== Changelog ==

= 1.2.0 =
* Added native standalone MCP endpoint for ChatGPT connections.
* Removed MCP Adapter as a requirement for the ChatGPT flow.
* Added Danger ability category for advanced filesystem and sandbox operations.
* Added secure sandbox page and filesystem abilities.
* Added optional persistent sandbox PHP loader with .crashed safe mode.
* Added backups and JSONL audit logs for dangerous operations.
* Added improved permission UI with Safe mode: block changes.
* Reorganized settings page: permissions, access, abilities, database.
* Added token persistence across plugin updates and ability changes.
