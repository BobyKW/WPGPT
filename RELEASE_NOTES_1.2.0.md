# WPGPT MCP Bridge v1.2.0

## Overview

WPGPT v1.2.0 is the first consolidated release of the standalone WPGPT MCP Bridge experience.

This version no longer requires MCP Adapter for the ChatGPT connection. WPGPT now exposes its own native WordPress MCP endpoint, adds a safer ability allowlist model, and introduces the new secure sandbox workflow for advanced filesystem and PHP-assisted maintenance tasks.

## Highlights

- Native standalone MCP endpoint for ChatGPT and compatible clients.
- MCP Adapter is no longer required for the ChatGPT flow.
- Persistent MCP token across plugin updates and ability changes.
- Dedicated WordPress admin sidebar menu.
- Improved settings layout:
  - permissions first;
  - ChatGPT/MCP access second;
  - full-width ability list;
  - database controls below abilities.
- New `Danger` category for advanced abilities.
- Secure sandbox at `wp-content/wpgpt-sandbox/`.
- Optional persistent sandbox PHP loader.
- Safe mode with `.crashed` marker after fatal sandbox errors.
- `.disabled` support for enabling/disabling sandbox PHP files.
- Backups before dangerous file operations.
- JSONL audit logs for dangerous actions.
- Clearer permissions UI with **Safe mode: block changes**.

## New Danger abilities

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

These abilities are intentionally powerful. Keep them disabled unless needed for a controlled task.

## Security improvements

- Safer permissions model for read, write, edit, delete, and destructive operations.
- Read-only/safe mode blocks incompatible dangerous permissions.
- Critical path protections for filesystem operations.
- PHP write/edit flows restricted to the sandbox.
- Optional sandbox loader is disabled by default.
- Crash detection prevents broken sandbox PHP from repeatedly taking the site down.
- Dangerous actions are logged.
- Existing files are backed up before overwrite/edit/delete flows.

## Token behavior

Updating the plugin does not require regenerating the MCP token.

Changing allowed abilities or permissions does not require regenerating the MCP token either. Save the settings and the next discovery request will reflect the current configuration.

Use **Regenerate token** only when you intentionally want to invalidate the previous connection.

## Upgrade notes

1. Upload and activate the plugin normally.
2. Open **WPGPT MCP** in the WordPress admin sidebar.
3. Review the selected MCP user.
4. Review permissions and exposed abilities.
5. Keep dangerous abilities disabled unless needed.
6. Verify the ChatGPT endpoint still works with the existing token.

## Recommended production posture

- Enable **Safe mode: block changes** when not actively working.
- Keep deletion disabled by default.
- Keep `wpgpt/danger-execute-php` disabled by default.
- Use a dedicated MCP user.
- Keep only the abilities needed for the current workflow enabled.
- Review backups and audit logs after dangerous operations.
