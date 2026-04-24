# Changelog

## 1.3.0

- Added compact MCP ability discovery to reduce the number of tools exposed to ChatGPT and compatible MCP clients.
- ChatGPT now sees one grouped ability per module, such as `wpgpt/posts`, `wpgpt/media`, `wpgpt/settings` or `wpgpt/danger`, instead of every detailed `query` / `inspect` / `apply` tool.
- Added compact action routing using `action=query`, `action=inspect` and `action=apply`, while keeping detailed abilities as the real internal security boundary.
- Added a compact `wpgpt/danger` facade for filesystem, sandbox and PHP execution actions.
- Hardened compact routing so every internal action revalidates the detailed allowlist, WordPress user permissions, safe mode, filesystem permissions and delete confirmation settings.
- Improved the settings page with a clearer compact discovery preview.
- Compact preview now shows which internal actions are available and which are blocked by current permission switches.
- Aligned detailed ability category names with compact module names to reduce confusion in the settings page.
- Moved the compact discovery preview into a collapsed-by-default section to keep the settings page cleaner.
- Renamed confusing counters from “detailed active” to “detailed selected”.
- Improved delete exposure policy so file deletion requires both filesystem delete permission and the explicit deletion confirmation switch.
- Kept the standalone native MCP endpoint, persistent token behavior, secure sandbox, backups, audit logs and dedicated admin sidebar introduced in the 1.2 line.

## 1.2.0

- Added standalone native MCP endpoint.
- Added secure sandbox workflow.
- Added Danger filesystem/PHP abilities.
- Added persistent token behavior.
- Added dedicated WPGPT MCP sidebar menu.
