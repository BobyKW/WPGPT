# WPGPT - MCP Extensor & ChatGPT Connection

WPGPT is a standalone WordPress MCP bridge for ChatGPT and compatible MCP clients. It exposes controlled WordPress abilities through a native MCP endpoint, with persistent token authentication, granular permissions, compact ability discovery, and a secure sandbox for advanced filesystem and PHP-assisted maintenance.

## Highlights

- Native WPGPT MCP endpoint. MCP Adapter is not required for the ChatGPT connection.
- Persistent MCP token: plugin updates and ability changes do not require reconnecting ChatGPT.
- Compact ability discovery: ChatGPT sees one grouped ability per module instead of dozens of detailed tools.
- Compact preview shows available actions and actions blocked by the current permission switches.
- Detailed internal allowlist: query, inspect and apply actions remain individually controlled.
- Safer permission UI with **Safe mode: block changes**.
- Dedicated WordPress admin sidebar menu.
- Secure sandbox at `wp-content/wpgpt-sandbox/`.
- Optional persistent sandbox PHP loader with safe mode and `.crashed` recovery.
- Backups before risky file operations.
- JSONL audit logs for dangerous actions.

## Version 1.3.0

This release consolidates the compact discovery work on top of the 1.2 standalone MCP and secure sandbox foundation. It keeps the detailed abilities internally, but exposes a cleaner grouped interface to MCP clients so users can enable broad functionality without flooding discovery with dozens of tools.

## Compact ability discovery

Earlier versions could expose many detailed abilities such as:

```text
wpgpt/posts-query
wpgpt/posts-inspect
wpgpt/posts-apply
```

WPGPT now exposes these as one compact MCP-facing ability:

```text
wpgpt/posts
```

The client then chooses the internal action:

```json
{
  "action": "query",
  "parameters": {}
}
```

The detailed abilities still exist internally and remain the security boundary. Each compact action re-validates:

- ability allowlist;
- WordPress user permissions;
- safe mode / read-only state;
- filesystem read/write/delete permissions;
- deletion confirmation settings;
- detailed ability policy.

This greatly reduces discovery noise and helps avoid excessive MCP handshake requests. The admin preview separates actions that are actually executable from actions that are selected but blocked by current permissions.

## Danger abilities

Danger abilities are grouped as:

```text
wpgpt/danger
```

Available actions depend on what is enabled in the settings:

```text
list_directory
read_file
write_file
edit_file
delete_file
execute_php
disable_file
enable_file
```

These actions are powerful. Keep write, delete and PHP execution disabled unless you are performing controlled maintenance.

## Requirements

- WordPress 6.6+
- PHP 8.1+

## Installation

1. Upload and activate the plugin.
2. Open **WPGPT MCP** in the WordPress sidebar.
3. Select the WordPress user used by MCP.
4. Review permissions and allowed abilities.
5. Generate or reuse the MCP token.
6. Connect ChatGPT using the full tokenized MCP endpoint.

## Token behavior

Do not regenerate the token for normal updates or ability changes.

- Updating the plugin keeps the existing token.
- Saving abilities keeps the existing token.
- Regenerate the token only when you want to invalidate the previous ChatGPT connection.

## Security notes

The sandbox is not a true OS-level isolation boundary. PHP execution runs inside WordPress context. Use it carefully, keep dangerous actions disabled by default, and maintain backups.
