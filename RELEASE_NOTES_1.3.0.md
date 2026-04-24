# WPGPT MCP Bridge v1.3.0

## Overview

WPGPT v1.3.0 introduces **Compact Ability Discovery**, a major performance and usability optimization for the ChatGPT experience. 

Instead of exposing dozens of individual tools, WPGPT now groups them into logical modules. This makes the "Discovery" phase significantly faster, reduces token consumption, and prevents ChatGPT from getting overwhelmed by too many tool definitions.

## Highlights

- **Compact Discovery**: Grouped tools for `posts`, `media`, `settings`, `danger`, and more.
- **Improved Settings UI**: A new "Compact Discovery Preview" section to see exactly what ChatGPT will see.
- **Hardened Security**: Revalidation of all internal permissions and safe mode settings during compact action routing.
- **Double-Lock Deletion**: Deleting files now requires both the "Delete" permission AND the "Explicit Deletion Confirmation" switch to be active.
- **Danger Facade**: A single `wpgpt/danger` tool that handles filesystem, sandbox, and PHP execution securely.

## New Compact Abilities

ChatGPT will now primarily use these grouped tools:

```text
wpgpt/posts      - Manage posts, pages, and custom post types (query, inspect, apply)
wpgpt/media      - Manage the media library (query, inspect, apply)
wpgpt/settings   - Manage WordPress and plugin settings
wpgpt/danger     - Advanced filesystem and PHP maintenance
wpgpt/system     - System information and status
```

*Note: Detailed abilities still exist internally as the real security boundary, but they are now routed through these compact facades.*

## Improved Settings Page

- **Compact Discovery Preview**: A new section (collapsed by default) allows you to verify which internal actions are exposed through the compact tools.
- **Live Permission Status**: The preview shows which actions are "Available" and which are "Blocked" based on your current permission switches (e.g., Safe Mode, Delete Confirmation).
- **Clearer Terminology**: Renamed confusing counters like "detailed active" to "detailed selected".

## Security & Reliability

- **Routing Revalidation**: Every compact action call (`action=query`, `action=apply`, etc.) re-checks the detailed allowlist, user capabilities, safe mode status, and specific permission flags before execution.
- **Strict Deletion Policy**: Hardened the policy for destructive operations to ensure they are never exposed by accident.
- **Consistent Mapping**: Aligned detailed ability category names with compact module names for a more intuitive configuration experience.

## Upgrade Notes

1. Update the plugin to v1.3.0.
2. Visit the **WPGPT MCP** settings page.
3. Review the **Compact Discovery Preview** to ensure your desired tools are exposed.
4. No token regeneration is required for this update. ChatGPT will automatically see the new grouped tools on the next discovery request.
