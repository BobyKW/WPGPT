=== WPGPT - MCP Extensor & ChatGPT Connection ===
Contributors: openai
Requires at least: 6.6
Tested up to: 6.9
Requires PHP: 8.1
Stable tag: 1.3.0
License: GPLv2 or later
License URI: https://www.gnu.org/licenses/gpl-2.0.html

Standalone WordPress MCP bridge for ChatGPT with compact ability discovery, granular permissions, secure sandbox, backups and audit logs.

== Description ==

WPGPT exposes controlled WordPress abilities through a native MCP endpoint for ChatGPT and compatible clients.

Main features:

* Native WPGPT MCP endpoint. MCP Adapter is not required for the ChatGPT connection.
* Persistent MCP token across plugin updates and ability changes.
* Compact ability discovery: one grouped ability per module, reducing handshake noise and excessive requests.
* Detailed allowlist remains the security boundary for query, inspect and apply actions.
* Safer permissions UI with Safe mode: block changes.
* Dedicated WordPress admin sidebar menu.
* Secure sandbox at wp-content/wpgpt-sandbox/.
* Optional persistent sandbox PHP loader with .crashed safe mode.
* Backups before risky filesystem operations.
* JSONL audit logs for dangerous actions.

== Compact ability discovery ==

Instead of exposing many detailed abilities such as wpgpt/posts-query, wpgpt/posts-inspect and wpgpt/posts-apply, WPGPT exposes one compact ability such as wpgpt/posts. The client chooses action=query, action=inspect or action=apply.

Each compact action still validates the detailed ability allowlist, WordPress permissions, safe mode and filesystem/delete settings before execution. The settings page shows available and blocked actions per compact module so users can understand what is exposed versus what is selected but blocked by current permissions.

== Installation ==

1. Upload and activate the plugin.
2. Open WPGPT MCP in the WordPress sidebar.
3. Select the MCP user.
4. Review permissions and allowed abilities.
5. Generate or reuse the MCP token.
6. Connect ChatGPT using the full tokenized endpoint.

== Security ==

Danger abilities can read/write files and execute PHP in WordPress context. Keep write, delete and PHP execution disabled unless needed for controlled maintenance.

== Changelog ==

= 1.3.0 =
* Added compact MCP ability discovery: ChatGPT sees one grouped ability per module instead of every detailed query/inspect/apply tool.
* Added compact action routing with action=query, action=inspect and action=apply while keeping detailed abilities as the internal security boundary.
* Added compact wpgpt/danger facade for filesystem, sandbox and PHP execution actions.
* Hardened compact routing with allowlist, permission, safe-mode and delete-confirmation validation.
* Improved settings page preview to show available actions and actions blocked by current permissions.
* Aligned category/module names between detailed ability selection and compact discovery preview.
* Moved compact discovery preview into a collapsed-by-default section to reduce visual noise.
* Improved delete exposure policy: deletion requires both filesystem delete permission and explicit deletion confirmation.
