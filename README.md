# WPGPT - MCP Extensor & ChatGPT Connection

WPGPT is a standalone WordPress MCP bridge for connecting **ChatGPT** and other MCP-compatible clients to a controlled set of WordPress abilities.

It exposes a native MCP endpoint from WordPress, lets you choose exactly which abilities are available, and includes a secure sandbox area for advanced filesystem and PHP-assisted maintenance workflows.

## Current version

**1.2.0**

## Main features

- Standalone WordPress MCP endpoint. MCP Adapter is no longer required for the ChatGPT connection.
- Bearer-token connection flow for ChatGPT and remote MCP clients.
- WordPress Application Password helper for editors or clients that use WordPress credentials.
- Selectable MCP operating user.
- Ability allowlist: expose only the abilities you actually want to use.
- Safer permissions model:
  - Safe mode: block changes.
  - Read files.
  - Write/edit files.
  - Delete files.
  - Explicit deletion confirmation.
- `Danger` ability category for advanced filesystem and sandbox operations.
- Secure sandbox folder at `wp-content/wpgpt-sandbox/`.
- Optional persistent sandbox PHP loader with safe mode and crash detection.
- `.disabled` support for enabling/disabling sandbox PHP files.
- `.crashed` safe mode marker when sandbox loading fails.
- Automatic backups before dangerous file operations.
- JSONL audit log for dangerous actions.
- Admin sidebar menu with separate Settings and Sandbox pages.
- GitHub-based plugin update support.

## Requirements

- WordPress 6.6 or higher.
- PHP 8.1 or higher.
- Administrator access to configure the plugin.

MCP Adapter is not required for the native WPGPT ChatGPT connection.

## Installation

1. Install and activate **WPGPT - MCP Extensor & ChatGPT Connection**.
2. Open **WPGPT MCP** in the WordPress admin sidebar.
3. Select the WordPress user that MCP should operate as.
4. Configure permissions.
5. Choose the abilities that should be exposed.
6. Generate or reuse the MCP token.
7. Copy the full MCP endpoint URL into ChatGPT or your MCP client.

## Updating

Updating the plugin does not require regenerating the MCP token.

Use **Regenerate token** only when you want to invalidate the previous connection, for example after a suspected leak or when intentionally replacing the client connection.

Changing abilities or permissions only requires saving the settings. The MCP endpoint reads the current configuration during discovery.

## Connection modes

### ChatGPT

Use the full tokenized endpoint shown in the plugin settings page.

The endpoint follows this structure:

```text
/wp-json/wpgpt-mcp/v1/YOUR_TOKEN
```

### Editors and other clients

For clients that support WordPress credentials, the plugin can generate a WordPress Application Password for the selected user.

## Dangerous abilities

The `Danger` category contains advanced abilities for filesystem and sandbox work:

```text
wpgpt/danger-list-directory
wpgpt/danger-read-file
wpgpt/danger-write-file
wpgpt/danger-edit-file
wpgpt/danger-delete-file
wpgpt/danger-execute-php
wpgpt/danger-disable-file
wpgpt/danger-enable-file
```

These abilities are powerful. Keep them disabled unless you need them for a specific task.

Recommended default posture:

- Enable read/list abilities when auditing.
- Enable write/edit only during controlled work.
- Keep delete disabled unless needed.
- Keep PHP execution disabled unless needed.
- Keep persistent sandbox loading disabled unless you intentionally want active sandbox snippets.

## Sandbox

The sandbox lives at:

```text
wp-content/wpgpt-sandbox/
```

The plugin can create, read, edit, disable, enable, and delete files inside the sandbox. PHP files are restricted to the sandbox for write/edit flows.

When persistent sandbox loading is enabled, active root-level PHP files in the sandbox can be loaded by WordPress. If a fatal error is detected, WPGPT creates `.crashed` and stops loading sandbox PHP files until safe mode is cleared.

## Security notes

This plugin can expose highly privileged operations. Treat the MCP token like an administrator credential.

Recommended production settings:

- Enable **Safe mode: block changes** unless actively working.
- Expose only the abilities needed for the current task.
- Keep `wpgpt/danger-execute-php` disabled by default.
- Keep deletion disabled by default.
- Use a dedicated MCP user.
- Review logs and backups after dangerous actions.
- Add `define( 'DISALLOW_FILE_EDIT', true );` to `wp-config.php` where appropriate.

## Development notes

WPGPT provides a native endpoint and does not depend on MCP Adapter for the ChatGPT flow. Some clients may still use other WordPress/MCP tooling alongside WPGPT, but that is optional.
