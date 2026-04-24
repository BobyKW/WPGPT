# Changelog

## 1.2.0

- Added native standalone MCP endpoint for WordPress.
- Removed MCP Adapter as a requirement for the ChatGPT connection flow.
- Added persistent token behavior across plugin updates and ability changes.
- Added dedicated WordPress admin sidebar menu.
- Added Settings and Sandbox admin pages.
- Added `Danger` ability category.
- Added advanced sandbox/filesystem abilities:
  - `wpgpt/danger-list-directory`
  - `wpgpt/danger-read-file`
  - `wpgpt/danger-write-file`
  - `wpgpt/danger-edit-file`
  - `wpgpt/danger-delete-file`
  - `wpgpt/danger-execute-php`
  - `wpgpt/danger-disable-file`
  - `wpgpt/danger-enable-file`
- Added secure sandbox at `wp-content/wpgpt-sandbox/`.
- Added optional persistent sandbox PHP loader.
- Added safe mode with `.crashed` marker.
- Added `.disabled` file handling for sandbox PHP files.
- Added backups before dangerous file operations.
- Added JSONL audit log for dangerous actions.
- Added clearer permissions UI with **Safe mode: block changes**.
- Reorganized settings page layout.
- Improved ability grouping and ordering.
- Updated documentation and GitHub release notes.
