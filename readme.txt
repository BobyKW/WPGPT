=== WPGPT - MCP Extensor & ChatGPT Connection ===
Contributors: openai
Requires at least: 6.6
Tested up to: 6.9
Requires PHP: 8.1
Stable tag: 2.2.0
License: GPLv2 or later
License URI: https://www.gnu.org/licenses/gpl-2.0.html

Extend MCP Adapter with secure WordPress abilities and easy connection snippets for ChatGPT, VS Code, and other MCP-compatible editors.

== Description ==

WPGPT extends MCP Adapter with a practical admin layer, secure WordPress tools, and guided connection flows for AI clients.

It is designed for site owners and developers who want to expose controlled WordPress capabilities to ChatGPT, VS Code, Cursor, and similar MCP-compatible tools without building a custom integration from scratch.

Main features:

* Extends MCP Adapter instead of replacing it.
* Adds secure MCP abilities for diagnostics, content operations, database inspection, plugin and theme analysis, and repository-oriented tasks.
* Lets you choose the WordPress user used for MCP actions.
* Generates a Bearer token flow for ChatGPT connections.
* Generates a WordPress Application Password flow for VS Code and other editor integrations.
* Includes admin snippets and copy-ready connection examples.
* Keeps configuration focused in a single admin screen.

== Requirements ==

* WordPress 6.6 or higher
* PHP 8.1 or higher
* MCP Adapter installed and active

== Installation ==

1. Install and activate MCP Adapter.
2. Install and activate WPGPT - MCP Extensor & ChatGPT Connection.
3. Go to Tools > WPGPT MCP Bridge.
4. Select the WordPress user that MCP should act as.
5. Save the settings.
6. Generate the credential you want to use:
   * Bearer token for ChatGPT
   * Application Password for VS Code and similar editors
7. Copy the generated connection snippet into your MCP client.

== Usage ==

= ChatGPT =

Use the generated Bearer token and the MCP endpoint shown in the plugin admin.

= VS Code and other editors =

Use the generated Application Password snippet shown in the plugin admin. The plugin provides a ready-to-copy configuration based on the selected WordPress user.

== Notes ==

* WPGPT depends on MCP Adapter and is intended to work as an extension layer on top of it.
* ChatGPT and editor connections use different credential flows by design.
* The selected MCP user determines the WordPress permissions available through the connection.

== Frequently Asked Questions ==

= Does this plugin work without MCP Adapter? =

No. WPGPT extends MCP Adapter and requires it to be installed and active.

= Can I use the same credential for ChatGPT and VS Code? =

No. ChatGPT uses the plugin Bearer token flow, while VS Code and similar editors are intended to use a WordPress Application Password.

= Can I change the MCP user later? =

Yes. You can select a different user in the admin settings, save the change, and generate new credentials if needed.

== Changelog ==

= 2.2.0 =
* Rebranded the plugin as WPGPT.
* Added cleaner admin organization.
* Added Application Password generation for VS Code and similar editors.
* Added connection snippets for ChatGPT and editor workflows.
* Updated branding, naming, and setup guidance.
