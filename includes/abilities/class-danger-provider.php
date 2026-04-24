<?php

namespace WPGPT\MCPBridge;

use WP_Error;

if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

/**
 * Danger-zone filesystem and PHP abilities.
 *
 * These abilities intentionally expose powerful filesystem and PHP execution
 * operations. They are grouped under the "peligro" category and remain governed
 * by the existing WPGPT read-only / filesystem write / delete settings.
 */
class Danger_Provider extends Base_Ability_Provider {
    protected const CATEGORY = 'peligro';

    private const SANDBOX_RELATIVE = 'wp-content/wpgpt-sandbox/';

    public function get_abilities(): array {
        return array(
            'wpgpt/danger-list-directory' => array(
                'label' => __( 'List Directory', 'wpgpt-mcp-bridge' ),
                'description' => __( 'Lists files and directories under the WordPress filesystem. Relative paths are resolved from ABSPATH.', 'wpgpt-mcp-bridge' ),
                'category' => self::CATEGORY,
                'input_schema' => array(
                    'type' => 'object',
                    'additionalProperties' => false,
                    'properties' => array(
                        'path' => array( 'type' => 'string', 'description' => 'Directory path. Defaults to ABSPATH.' ),
                        'pattern' => array( 'type' => 'string', 'description' => 'Glob pattern, e.g. *.php or wp-*.' ),
                        'recursive' => array( 'type' => 'boolean', 'default' => false ),
                        'max_depth' => array( 'type' => 'integer', 'minimum' => 0, 'maximum' => 10, 'default' => 2 ),
                        'include_hidden' => array( 'type' => 'boolean', 'default' => false ),
                        'limit' => array( 'type' => 'integer', 'minimum' => 1, 'maximum' => 1000, 'default' => 200 ),
                    ),
                ),
                'output_schema' => $this->object_schema(),
                'execute_callback' => array( $this, 'list_directory' ),
                'permission_callback' => array( $this, 'can_read_files' ),
                'meta' => $this->danger_meta( true, false, true ),
            ),
            'wpgpt/danger-read-file' => array(
                'label' => __( 'Read File', 'wpgpt-mcp-bridge' ),
                'description' => __( 'Reads a file from the WordPress filesystem. Text is returned as UTF-8 and binary/invalid UTF-8 as base64.', 'wpgpt-mcp-bridge' ),
                'category' => self::CATEGORY,
                'input_schema' => array(
                    'type' => 'object',
                    'additionalProperties' => false,
                    'properties' => array(
                        'path' => array( 'type' => 'string', 'minLength' => 1 ),
                        'offset' => array( 'type' => 'integer', 'minimum' => 0, 'default' => 0 ),
                        'limit' => array( 'type' => 'integer', 'minimum' => -1, 'maximum' => 1048576, 'default' => 65536 ),
                    ),
                    'required' => array( 'path' ),
                ),
                'output_schema' => $this->object_schema(),
                'execute_callback' => array( $this, 'read_file' ),
                'permission_callback' => array( $this, 'can_read_files' ),
                'meta' => $this->danger_meta( true, false, true ),
            ),
            'wpgpt/danger-write-file' => array(
                'label' => __( 'Write File', 'wpgpt-mcp-bridge' ),
                'description' => __( 'Writes or appends content to a file. PHP files can only be written inside wp-content/wpgpt-sandbox/.', 'wpgpt-mcp-bridge' ),
                'category' => self::CATEGORY,
                'input_schema' => array(
                    'type' => 'object',
                    'additionalProperties' => false,
                    'properties' => array(
                        'path' => array( 'type' => 'string', 'minLength' => 1 ),
                        'content' => array( 'type' => 'string' ),
                        'encoding' => array( 'type' => 'string', 'enum' => array( 'utf-8', 'base64' ), 'default' => 'utf-8' ),
                        'mode' => array( 'type' => 'string', 'enum' => array( 'overwrite', 'append' ), 'default' => 'overwrite' ),
                        'create_directories' => array( 'type' => 'boolean', 'default' => true ),
                    ),
                    'required' => array( 'path', 'content' ),
                ),
                'output_schema' => $this->object_schema(),
                'execute_callback' => array( $this, 'write_file' ),
                'permission_callback' => array( $this, 'can_write_files' ),
                'meta' => $this->danger_meta( false, false, true ),
            ),
            'wpgpt/danger-edit-file' => array(
                'label' => __( 'Edit File', 'wpgpt-mcp-bridge' ),
                'description' => __( 'Edits a file by replacing exact text. PHP files can only be edited inside wp-content/wpgpt-sandbox/.', 'wpgpt-mcp-bridge' ),
                'category' => self::CATEGORY,
                'input_schema' => array(
                    'type' => 'object',
                    'additionalProperties' => false,
                    'properties' => array(
                        'path' => array( 'type' => 'string', 'minLength' => 1 ),
                        'old_string' => array( 'type' => 'string' ),
                        'new_string' => array( 'type' => 'string' ),
                        'replace_all' => array( 'type' => 'boolean', 'default' => false ),
                    ),
                    'required' => array( 'path', 'old_string', 'new_string' ),
                ),
                'output_schema' => $this->object_schema(),
                'execute_callback' => array( $this, 'edit_file' ),
                'permission_callback' => array( $this, 'can_write_files' ),
                'meta' => $this->danger_meta( false, false, true ),
            ),
            'wpgpt/danger-delete-file' => array(
                'label' => __( 'Delete File', 'wpgpt-mcp-bridge' ),
                'description' => __( 'Deletes a file or directory. Recursive mode is required for non-empty directories.', 'wpgpt-mcp-bridge' ),
                'category' => self::CATEGORY,
                'input_schema' => array(
                    'type' => 'object',
                    'additionalProperties' => false,
                    'properties' => array(
                        'path' => array( 'type' => 'string', 'minLength' => 1 ),
                        'recursive' => array( 'type' => 'boolean', 'default' => false ),
                    ),
                    'required' => array( 'path' ),
                ),
                'output_schema' => $this->object_schema(),
                'execute_callback' => array( $this, 'delete_file' ),
                'permission_callback' => array( $this, 'can_delete_files' ),
                'meta' => $this->danger_meta( false, true, false ),
            ),
            'wpgpt/danger-execute-php' => array(
                'label' => __( 'Execute PHP Code', 'wpgpt-mcp-bridge' ),
                'description' => __( 'Executes arbitrary PHP code in WordPress context. Do not include <?php tags. Use return $value; to return data.', 'wpgpt-mcp-bridge' ),
                'category' => self::CATEGORY,
                'input_schema' => array(
                    'type' => 'object',
                    'additionalProperties' => false,
                    'properties' => array(
                        'code' => array( 'type' => 'string', 'minLength' => 1 ),
                    ),
                    'required' => array( 'code' ),
                ),
                'output_schema' => $this->object_schema(),
                'execute_callback' => array( $this, 'execute_php' ),
                'permission_callback' => array( $this, 'can_write_files' ),
                'meta' => $this->danger_meta( false, false, false, 'Arbitrary PHP execution. Development/staging only.' ),
            ),
            'wpgpt/danger-disable-file' => array(
                'label' => __( 'Disable File', 'wpgpt-mcp-bridge' ),
                'description' => __( 'Disables a sandbox PHP file by renaming it to .disabled. Only paths inside wp-content/wpgpt-sandbox/ are allowed.', 'wpgpt-mcp-bridge' ),
                'category' => self::CATEGORY,
                'input_schema' => array(
                    'type' => 'object',
                    'additionalProperties' => false,
                    'properties' => array( 'path' => array( 'type' => 'string', 'minLength' => 1 ) ),
                    'required' => array( 'path' ),
                ),
                'output_schema' => $this->object_schema(),
                'execute_callback' => array( $this, 'disable_file' ),
                'permission_callback' => array( $this, 'can_write_files' ),
                'meta' => $this->danger_meta( false, false, true ),
            ),
            'wpgpt/danger-enable-file' => array(
                'label' => __( 'Enable File', 'wpgpt-mcp-bridge' ),
                'description' => __( 'Re-enables a sandbox file by removing the .disabled suffix. Only paths inside wp-content/wpgpt-sandbox/ are allowed.', 'wpgpt-mcp-bridge' ),
                'category' => self::CATEGORY,
                'input_schema' => array(
                    'type' => 'object',
                    'additionalProperties' => false,
                    'properties' => array( 'path' => array( 'type' => 'string', 'minLength' => 1 ) ),
                    'required' => array( 'path' ),
                ),
                'output_schema' => $this->object_schema(),
                'execute_callback' => array( $this, 'enable_file' ),
                'permission_callback' => array( $this, 'can_write_files' ),
                'meta' => $this->danger_meta( false, false, true ),
            ),
        );
    }

    private function danger_meta( bool $readonly, bool $destructive, bool $idempotent, string $instructions = '' ): array {
        $base = array(
            'show_in_rest' => true,
            'mcp' => array( 'public' => true ),
            'annotations' => array(
                'readonly' => $readonly,
                'destructive' => $destructive,
                'idempotent' => $idempotent,
            ),
        );
        if ( '' !== $instructions ) {
            $base['annotations']['instructions'] = $instructions;
        }
        return $base;
    }

    public function sandbox_dir(): string {
        return trailingslashit( ABSPATH ) . self::SANDBOX_RELATIVE;
    }

    private function normalize_relative_path( string $path ): string {
        $path = str_replace( "\0", '', trim( $path ) );
        $path = str_replace( '\\', '/', $path );
        $path = preg_replace( '#/+#', '/', $path );
        return is_string( $path ) ? $path : '';
    }

    private function resolve_path( string $path = '', bool $must_exist = true ): string|WP_Error {
        $path = $this->normalize_relative_path( $path );
        if ( '' === $path ) {
            $path = ABSPATH;
        }

        $is_absolute = str_starts_with( $path, '/' ) || preg_match( '#^[A-Za-z]:/#', $path );
        $candidate = $is_absolute ? $path : trailingslashit( ABSPATH ) . ltrim( $path, '/' );
        $candidate = $this->normalize_relative_path( $candidate );

        $root = realpath( ABSPATH );
        if ( false === $root ) {
            return new WP_Error( 'invalid_root', 'Could not resolve ABSPATH.' );
        }
        $root = rtrim( str_replace( '\\', '/', $root ), '/' ) . '/';

        if ( $must_exist ) {
            $real = realpath( $candidate );
            if ( false === $real ) {
                return new WP_Error( 'not_found', sprintf( 'Path does not exist: %s', $candidate ) );
            }
            $real = str_replace( '\\', '/', $real );
            if ( ! str_starts_with( rtrim( $real, '/' ) . ( is_dir( $real ) ? '/' : '' ), $root ) && rtrim( $real, '/' ) !== rtrim( $root, '/' ) ) {
                return new WP_Error( 'outside_root', 'Path must be inside the WordPress root.' );
            }
            return $real;
        }

        $parent = dirname( $candidate );
        while ( ! is_dir( $parent ) && dirname( $parent ) !== $parent ) {
            $parent = dirname( $parent );
        }
        $real_parent = realpath( $parent );
        if ( false === $real_parent ) {
            return new WP_Error( 'invalid_parent', sprintf( 'Could not resolve parent directory: %s', dirname( $candidate ) ) );
        }
        $real_parent = rtrim( str_replace( '\\', '/', $real_parent ), '/' ) . '/';
        if ( ! str_starts_with( $real_parent, $root ) ) {
            return new WP_Error( 'outside_root', 'Path must be inside the WordPress root.' );
        }
        return $candidate;
    }

    private function is_inside_sandbox( string $path ): bool {
        $sandbox = $this->normalize_relative_path( $this->sandbox_dir() );
        return str_starts_with( $this->normalize_relative_path( $path ), rtrim( $sandbox, '/' ) . '/' );
    }

    private function ensure_php_sandbox( string $path ): bool|WP_Error {
        if ( 'php' !== strtolower( pathinfo( $path, PATHINFO_EXTENSION ) ) ) {
            return true;
        }
        if ( ! $this->is_inside_sandbox( $path ) ) {
            return new WP_Error( 'php_sandbox_required', sprintf( 'PHP files can only be written or edited inside: %s', $this->sandbox_dir() ) );
        }
        return true;
    }

    private function decode_content( string $content, string $encoding ): string|WP_Error {
        if ( 'base64' === $encoding ) {
            $decoded = base64_decode( $content, true );
            return false === $decoded ? new WP_Error( 'invalid_base64', 'Invalid base64 content.' ) : $decoded;
        }
        return $content;
    }

    private function is_text_mime( string $mime ): bool {
        foreach ( array( 'text/', 'application/json', 'application/xml', 'application/javascript' ) as $prefix ) {
            if ( str_starts_with( $mime, $prefix ) ) {
                return true;
            }
        }
        return str_ends_with( $mime, '+xml' ) || str_ends_with( $mime, '+json' );
    }

    private function backup_root(): string {
        return trailingslashit( WP_CONTENT_DIR ) . 'wpgpt-mcp-backups';
    }

    private function log_root(): string {
        return trailingslashit( WP_CONTENT_DIR ) . 'wpgpt-mcp-logs';
    }

    private function audit_log( string $action, string $path, bool $success, array $context = array() ): void {
        $dir = $this->log_root();
        if ( ! is_dir( $dir ) ) { wp_mkdir_p( $dir ); }
        $entry = array(
            'time_gmt' => gmdate( 'c' ),
            'action' => $action,
            'path' => $path,
            'success' => $success,
            'user_id' => get_current_user_id(),
            'context' => $context,
        );
        @file_put_contents( trailingslashit( $dir ) . 'audit.log', wp_json_encode( $entry ) . "\n", FILE_APPEND | LOCK_EX );
    }

    private function relative_to_abspath( string $path ): string {
        $root = rtrim( str_replace( '\\', '/', realpath( ABSPATH ) ?: ABSPATH ), '/' ) . '/';
        $norm = str_replace( '\\', '/', $path );
        return str_starts_with( $norm, $root ) ? ltrim( substr( $norm, strlen( $root ) ), '/' ) : basename( $norm );
    }

    private function create_backup( string $path, string $reason ): array|WP_Error {
        if ( ! is_file( $path ) || str_starts_with( str_replace( '\\', '/', $path ), str_replace( '\\', '/', $this->backup_root() ) ) ) {
            return array( 'created' => false );
        }
        $relative = $this->relative_to_abspath( $path );
        $safe = preg_replace( '/[^A-Za-z0-9._-]+/', '_', $relative );
        $dir = trailingslashit( $this->backup_root() ) . gmdate( 'Ymd' );
        if ( ! is_dir( $dir ) && ! wp_mkdir_p( $dir ) ) {
            return new WP_Error( 'backup_failed', sprintf( 'Could not create backup directory: %s', $dir ) );
        }
        $backup = trailingslashit( $dir ) . gmdate( 'His' ) . '__' . $reason . '__' . $safe . '.bak';
        if ( ! @copy( $path, $backup ) ) {
            return new WP_Error( 'backup_failed', sprintf( 'Could not create backup for: %s', $path ) );
        }
        @chmod( $backup, 0644 );
        return array( 'created' => true, 'path' => $backup, 'original' => $path, 'size' => (int) filesize( $backup ) );
    }

    private function is_protected_path( string $path, bool $recursive = false ): bool {
        $real = realpath( $path );
        $normalized = rtrim( str_replace( '\\', '/', false === $real ? $path : $real ), '/' );
        $protected = array_filter( array(
            realpath( ABSPATH ),
            realpath( ABSPATH . 'wp-admin' ),
            realpath( ABSPATH . 'wp-includes' ),
            realpath( WP_CONTENT_DIR . '/mu-plugins' ),
            realpath( WP_CONTENT_DIR . '/plugins/wpgpt-mcp' ),
            realpath( $this->backup_root() ),
            realpath( ABSPATH . 'wp-config.php' ),
            realpath( ABSPATH . '.htaccess' ),
            realpath( ABSPATH . '.user.ini' ),
            realpath( ABSPATH . 'php.ini' ),
            realpath( $this->sandbox_dir() ),
        ) );
        foreach ( $protected as $item ) {
            $item = rtrim( str_replace( '\\', '/', $item ), '/' );
            if ( $normalized === $item ) { return true; }
            if ( $recursive && str_starts_with( $item . '/', $normalized . '/' ) ) { return true; }
        }
        $base = basename( $normalized );
        if ( ! $this->is_inside_sandbox( $normalized ) && in_array( $base, array( 'wp-config.php', '.htaccess', '.user.ini', 'php.ini' ), true ) ) {
            return true;
        }
        return false;
    }

    private function ensure_safe_write_target( string $path ): bool|WP_Error {
        if ( $this->is_protected_path( $path ) ) {
            return new WP_Error( 'protected_path', sprintf( 'Refusing to write protected path: %s', $path ) );
        }
        if ( ! $this->is_inside_sandbox( $path ) && str_starts_with( basename( $path ), '.' ) ) {
            return new WP_Error( 'protected_dotfile', 'Dotfiles outside the sandbox cannot be written by this ability.' );
        }
        return true;
    }

    public function list_directory( array $input = array() ): array|WP_Error {
        $dir = $this->resolve_path( (string) ( $input['path'] ?? '' ), true );
        if ( is_wp_error( $dir ) ) { return $dir; }
        if ( ! is_dir( $dir ) ) { return new WP_Error( 'not_directory', sprintf( 'Not a directory: %s', $dir ) ); }

        $pattern = (string) ( $input['pattern'] ?? '*' );
        $recursive = ! empty( $input['recursive'] );
        $max_depth = max( 0, min( 10, (int) ( $input['max_depth'] ?? 2 ) ) );
        $include_hidden = ! empty( $input['include_hidden'] );
        $limit = max( 1, min( 1000, (int) ( $input['limit'] ?? 200 ) ) );
        $items = array();
        $total = 0;

        $scan = function ( string $base, int $depth ) use ( &$scan, &$items, &$total, $pattern, $recursive, $max_depth, $include_hidden, $limit ) {
            $entries = @scandir( $base );
            if ( ! is_array( $entries ) ) { return; }
            foreach ( $entries as $entry ) {
                if ( '.' === $entry || '..' === $entry ) { continue; }
                if ( ! $include_hidden && str_starts_with( $entry, '.' ) ) { continue; }
                $full = trailingslashit( $base ) . $entry;
                $matches = fnmatch( $pattern, $entry );
                if ( $matches ) {
                    $total++;
                    if ( count( $items ) < $limit ) {
                        $items[] = array(
                            'path' => $full,
                            'name' => $entry,
                            'type' => is_dir( $full ) ? 'directory' : 'file',
                            'size' => is_file( $full ) ? (int) filesize( $full ) : null,
                            'permissions' => substr( sprintf( '%o', @fileperms( $full ) ?: 0 ), -4 ),
                            'modified' => gmdate( 'c', (int) @filemtime( $full ) ),
                        );
                    }
                }
                if ( $recursive && is_dir( $full ) && $depth < $max_depth ) {
                    $scan( $full, $depth + 1 );
                }
            }
        };
        $scan( $dir, 0 );

        return array( 'path' => $dir, 'entries' => $items, 'total' => $total, 'truncated' => $total > count( $items ) );
    }

    public function read_file( array $input ): array|WP_Error {
        $file = $this->resolve_path( (string) $input['path'], true );
        if ( is_wp_error( $file ) ) { return $file; }
        if ( ! is_file( $file ) ) { return new WP_Error( 'not_file', sprintf( 'Not a file: %s', $file ) ); }

        $size = (int) filesize( $file );
        $offset = max( 0, (int) ( $input['offset'] ?? 0 ) );
        if ( $offset > $size ) { return new WP_Error( 'offset_out_of_range', 'Offset is beyond end of file.' ); }
        $limit = (int) ( $input['limit'] ?? 65536 );
        $limit = -1 === $limit ? -1 : max( 1, min( 1048576, $limit ) );
        $length = -1 === $limit ? $size - $offset : min( $limit, $size - $offset );

        $handle = fopen( $file, 'rb' );
        if ( false === $handle ) { return new WP_Error( 'read_failed', sprintf( 'Could not open: %s', $file ) ); }
        if ( $offset > 0 ) { fseek( $handle, $offset ); }
        $content = $length > 0 ? fread( $handle, $length ) : '';
        fclose( $handle );
        if ( false === $content ) { return new WP_Error( 'read_failed', sprintf( 'Could not read: %s', $file ) ); }

        $mime = function_exists( 'mime_content_type' ) ? ( mime_content_type( $file ) ?: 'application/octet-stream' ) : 'application/octet-stream';
        $is_text = $this->is_text_mime( $mime ) && preg_match( '//u', $content );
        return array(
            'path' => $file,
            'content' => $is_text ? $content : base64_encode( $content ),
            'encoding' => $is_text ? 'utf-8' : 'base64',
            'size' => $size,
            'bytes_read' => strlen( $content ),
            'truncated' => ( $offset + strlen( $content ) ) < $size,
            'mime_type' => $mime,
        );
    }

    public function write_file( array $input ): array|WP_Error {
        $file = $this->resolve_path( (string) $input['path'], false );
        if ( is_wp_error( $file ) ) { return $file; }
        $write_check = $this->ensure_safe_write_target( $file );
        if ( is_wp_error( $write_check ) ) { return $write_check; }
        $check = $this->ensure_php_sandbox( $file );
        if ( is_wp_error( $check ) ) { return $check; }
        $content = $this->decode_content( (string) $input['content'], (string) ( $input['encoding'] ?? 'utf-8' ) );
        if ( is_wp_error( $content ) ) { return $content; }

        $parent = dirname( $file );
        $created_dirs = array();
        if ( ! is_dir( $parent ) ) {
            if ( empty( $input['create_directories'] ) ) { return new WP_Error( 'directory_not_found', sprintf( 'Parent directory does not exist: %s', $parent ) ); }
            if ( ! wp_mkdir_p( $parent ) ) { return new WP_Error( 'mkdir_failed', sprintf( 'Could not create directory: %s', $parent ) ); }
            $created_dirs[] = $parent;
        }
        $created = ! file_exists( $file );
        $backup = $created ? array( 'created' => false ) : $this->create_backup( $file, 'write' );
        if ( is_wp_error( $backup ) ) { return $backup; }
        $mode = (string) ( $input['mode'] ?? 'overwrite' );
        $bytes = file_put_contents( $file, $content, 'append' === $mode ? FILE_APPEND | LOCK_EX : LOCK_EX );
        if ( false === $bytes ) { $this->audit_log( 'write-file', $file, false ); return new WP_Error( 'write_failed', sprintf( 'Could not write file: %s', $file ) ); }
        @chmod( $file, 0644 );
        $result = array( 'path' => $file, 'bytes_written' => $bytes, 'created' => $created, 'directories_created' => $created_dirs, 'size' => (int) filesize( $file ), 'backup' => $backup );
        $this->audit_log( 'write-file', $file, true, array( 'bytes_written' => $bytes, 'created' => $created, 'backup' => $backup ) );
        return $result;
    }


    public function edit_file( array $input ): array|WP_Error {
        $file = $this->resolve_path( (string) $input['path'], true );
        if ( is_wp_error( $file ) ) { return $file; }
        if ( ! is_file( $file ) ) { return new WP_Error( 'not_file', sprintf( 'Not a file: %s', $file ) ); }
        $write_check = $this->ensure_safe_write_target( $file );
        if ( is_wp_error( $write_check ) ) { return $write_check; }
        $check = $this->ensure_php_sandbox( $file );
        if ( is_wp_error( $check ) ) { return $check; }
        $content = file_get_contents( $file );
        if ( false === $content ) { return new WP_Error( 'read_failed', sprintf( 'Could not read file: %s', $file ) ); }
        $old = (string) $input['old_string'];
        $new = (string) $input['new_string'];
        if ( '' === $old ) { return new WP_Error( 'empty_old_string', 'old_string cannot be empty.' ); }
        $count = substr_count( $content, $old );
        if ( 0 === $count ) { return new WP_Error( 'old_string_not_found', 'old_string was not found.' ); }
        if ( empty( $input['replace_all'] ) && 1 !== $count ) { return new WP_Error( 'ambiguous_match', sprintf( 'old_string appears %d times. Use replace_all=true or provide a more specific string.', $count ) ); }
        $backup = $this->create_backup( $file, 'edit' );
        if ( is_wp_error( $backup ) ) { return $backup; }
        $updated = empty( $input['replace_all'] ) ? preg_replace( '/' . preg_quote( $old, '/' ) . '/', str_replace( '$', '\\$', $new ), $content, 1 ) : str_replace( $old, $new, $content );
        if ( ! is_string( $updated ) ) { return new WP_Error( 'replace_failed', 'Replacement failed.' ); }
        $bytes = file_put_contents( $file, $updated, LOCK_EX );
        if ( false === $bytes ) { $this->audit_log( 'edit-file', $file, false ); return new WP_Error( 'write_failed', sprintf( 'Could not write file: %s', $file ) ); }
        $result = array( 'path' => $file, 'replacements' => empty( $input['replace_all'] ) ? 1 : $count, 'size' => (int) filesize( $file ), 'backup' => $backup );
        $this->audit_log( 'edit-file', $file, true, array( 'replacements' => $result['replacements'], 'backup' => $backup ) );
        return $result;
    }


    public function delete_file( array $input ): array|WP_Error {
        $path = $this->resolve_path( (string) $input['path'], true );
        if ( is_wp_error( $path ) ) { return $path; }
        $recursive = ! empty( $input['recursive'] );
        if ( $this->is_protected_path( $path, $recursive ) ) {
            return new WP_Error( 'protected_path', sprintf( 'Refusing to delete protected path: %s', $path ) );
        }
        if ( is_file( $path ) || is_link( $path ) ) {
            $backup = is_file( $path ) ? $this->create_backup( $path, 'delete' ) : array( 'created' => false );
            if ( is_wp_error( $backup ) ) { return $backup; }
            if ( ! @unlink( $path ) ) { $this->audit_log( 'delete-file', $path, false ); return new WP_Error( 'delete_failed', sprintf( 'Could not delete: %s', $path ) ); }
            $this->audit_log( 'delete-file', $path, true, array( 'type' => 'file', 'backup' => $backup ) );
            return array( 'path' => $path, 'type' => 'file', 'deleted' => true, 'items_deleted' => 1, 'backup' => $backup );
        }
        if ( is_dir( $path ) ) {
            $items = array_diff( scandir( $path ) ?: array(), array( '.', '..' ) );
            if ( ! $recursive && ! empty( $items ) ) { return new WP_Error( 'directory_not_empty', 'Directory is not empty. Use recursive=true.' ); }
            $count = $this->delete_directory_recursive( $path );
            if ( is_wp_error( $count ) ) { $this->audit_log( 'delete-file', $path, false, array( 'type' => 'directory' ) ); return $count; }
            $this->audit_log( 'delete-file', $path, true, array( 'type' => 'directory', 'items_deleted' => $count, 'backup' => 'not_available_for_directory' ) );
            return array( 'path' => $path, 'type' => 'directory', 'deleted' => true, 'items_deleted' => $count, 'backup' => array( 'created' => false, 'reason' => 'directory' ) );
        }
        return array( 'path' => $path, 'type' => 'not_found', 'deleted' => false, 'items_deleted' => 0 );
    }


    private function delete_directory_recursive( string $dir ): int|WP_Error {
        $count = 0;
        foreach ( array_diff( scandir( $dir ) ?: array(), array( '.', '..' ) ) as $entry ) {
            $full = trailingslashit( $dir ) . $entry;
            if ( is_dir( $full ) && ! is_link( $full ) ) {
                $deleted = $this->delete_directory_recursive( $full );
                if ( is_wp_error( $deleted ) ) { return $deleted; }
                $count += $deleted;
            } else {
                if ( ! @unlink( $full ) ) { return new WP_Error( 'delete_failed', sprintf( 'Could not delete: %s', $full ) ); }
                $count++;
            }
        }
        if ( ! @rmdir( $dir ) ) { return new WP_Error( 'delete_failed', sprintf( 'Could not delete directory: %s', $dir ) ); }
        return $count + 1;
    }

    public function execute_php( array $input ): array {
        $code = (string) $input['code'];
        $code = preg_replace( '/^\s*<\?(php)?/i', '', $code );
        $warnings = array();
        $previous_limit = ini_get( 'max_execution_time' );
        if ( function_exists( 'set_time_limit' ) ) { @set_time_limit( 8 ); }
        $previous = set_error_handler( static function ( int $severity, string $message, string $file, int $line ) use ( &$warnings ): bool {
            $warnings[] = array( 'severity' => $severity, 'message' => $message, 'file' => $file, 'line' => $line );
            return true;
        } );
        $start = microtime( true );
        ob_start();
        try {
            $return = eval( $code );
            $output = ob_get_clean();
            if ( null !== $previous ) { set_error_handler( $previous ); } else { restore_error_handler(); }
            if ( false !== $previous_limit && function_exists( 'set_time_limit' ) ) { @set_time_limit( (int) $previous_limit ); }
            $truncated = false;
            if ( strlen( $output ) > 262144 ) { $output = substr( $output, 0, 262144 ); $truncated = true; }
            $result = array( 'success' => true, 'return_value' => $return, 'output' => $output, 'output_truncated' => $truncated, 'warnings' => $warnings, 'execution_time_ms' => round( ( microtime( true ) - $start ) * 1000, 2 ) );
            $this->audit_log( 'execute-php', '[inline]', true, array( 'execution_time_ms' => $result['execution_time_ms'], 'warnings' => count( $warnings ) ) );
            return $result;
        } catch ( \Throwable $e ) {
            $output = ob_get_clean();
            if ( null !== $previous ) { set_error_handler( $previous ); } else { restore_error_handler(); }
            if ( false !== $previous_limit && function_exists( 'set_time_limit' ) ) { @set_time_limit( (int) $previous_limit ); }
            if ( strlen( $output ) > 262144 ) { $output = substr( $output, 0, 262144 ); }
            $result = array( 'success' => false, 'return_value' => null, 'output' => $output, 'warnings' => $warnings, 'error_message' => $e->getMessage(), 'error_type' => get_class( $e ), 'execution_time_ms' => round( ( microtime( true ) - $start ) * 1000, 2 ) );
            $this->audit_log( 'execute-php', '[inline]', false, array( 'error' => $e->getMessage(), 'type' => get_class( $e ) ) );
            return $result;
        }
    }


    public function disable_file( array $input ): array|WP_Error {
        $file = $this->resolve_path( (string) $input['path'], true );
        if ( is_wp_error( $file ) ) { return $file; }
        if ( ! $this->is_inside_sandbox( $file ) ) { return new WP_Error( 'sandbox_required', sprintf( 'Path must be inside: %s', $this->sandbox_dir() ) ); }
        if ( str_ends_with( $file, '.disabled' ) ) { return array( 'original_path' => substr( $file, 0, -9 ), 'disabled_path' => $file, 'disabled' => false ); }
        $disabled = $file . '.disabled';
        if ( file_exists( $disabled ) ) { return new WP_Error( 'target_exists', sprintf( 'Disabled target already exists: %s', $disabled ) ); }
        if ( ! rename( $file, $disabled ) ) { return new WP_Error( 'rename_failed', sprintf( 'Could not rename: %s', $file ) ); }
        return array( 'original_path' => $file, 'disabled_path' => $disabled, 'disabled' => true );
    }

    public function enable_file( array $input ): array|WP_Error {
        $path = (string) $input['path'];
        $file = $this->resolve_path( $path, file_exists( $path ) );
        if ( is_wp_error( $file ) || ! file_exists( $file ) ) {
            $candidate = $this->resolve_path( $path . '.disabled', true );
            if ( is_wp_error( $candidate ) ) { return $candidate; }
            $file = $candidate;
        }
        if ( ! $this->is_inside_sandbox( $file ) ) { return new WP_Error( 'sandbox_required', sprintf( 'Path must be inside: %s', $this->sandbox_dir() ) ); }
        if ( ! str_ends_with( $file, '.disabled' ) ) { return array( 'disabled_path' => $file . '.disabled', 'enabled_path' => $file, 'enabled' => false ); }
        $enabled = substr( $file, 0, -9 );
        if ( file_exists( $enabled ) ) { return new WP_Error( 'target_exists', sprintf( 'Enabled target already exists: %s', $enabled ) ); }
        if ( ! rename( $file, $enabled ) ) { return new WP_Error( 'rename_failed', sprintf( 'Could not rename: %s', $file ) ); }
        return array( 'disabled_path' => $file, 'enabled_path' => $enabled, 'enabled' => true );
    }
}
