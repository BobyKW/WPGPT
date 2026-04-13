<?php

namespace WPGPT\MCPBridge\Filesystem;

use WP_Error;

if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

class Filesystem_Service {
    private const ALLOWED_EXTENSIONS = array( 'php', 'js', 'css', 'scss', 'json', 'txt', 'md', 'xml', 'yml', 'yaml', 'html', 'htm', 'svg' );
    private const BACKUP_DIR         = 'wpgpt-mcp-backups';

    public function theme_file_tree( string $stylesheet, int $max_depth = 4, int $limit = 500 ): array|WP_Error {
        $stylesheet = sanitize_text_field( $stylesheet );
        $theme = wp_get_theme( $stylesheet );
        if ( ! $theme->exists() ) {
            return new WP_Error( 'wpgpt_fs_theme_not_found', __( 'No se ha encontrado el tema indicado.', 'wpgpt-mcp-bridge' ), array( 'status' => 404 ) );
        }
        $path = $this->ensure_allowed_root( wp_normalize_path( $theme->get_stylesheet_directory() ) );
        if ( is_wp_error( $path ) ) { return $path; }
        $items = array();
        $this->scan_tree( $path, $path, 0, max(1,min(8,$max_depth)), max(1,min(2000,$limit)), $items );
        return array( 'stylesheet' => $stylesheet, 'root' => $path, 'count' => count( $items ), 'items' => $items );
    }

    public function diff_files( string $path_a, string $path_b ): array|WP_Error {
        $a = $this->read_file( $path_a, 1, 10000 );
        if ( is_wp_error( $a ) ) { return $a; }
        $b = $this->read_file( $path_b, 1, 10000 );
        if ( is_wp_error( $b ) ) { return $b; }
        $lines_a = preg_split( "/\r\n|\n|\r/", (string) $a['content'] );
        $lines_b = preg_split( "/\r\n|\n|\r/", (string) $b['content'] );
        $max = max( count( $lines_a ), count( $lines_b ) );
        $items = array();
        for ( $i = 0; $i < $max; $i++ ) {
            $la = $lines_a[ $i ] ?? null;
            $lb = $lines_b[ $i ] ?? null;
            if ( $la !== $lb ) {
                $items[] = array( 'line' => $i + 1, 'left' => $la, 'right' => $lb );
                if ( count( $items ) >= 200 ) { break; }
            }
        }
        return array( 'left' => $a['relative'], 'right' => $b['relative'], 'count' => count( $items ), 'items' => $items );
    }

    public function zip_create( string $source, string $destination ): array|WP_Error {
        if ( ! class_exists( 'ZipArchive' ) ) {
            return new WP_Error( 'wpgpt_fs_zip_unavailable', __( 'ZipArchive no está disponible.', 'wpgpt-mcp-bridge' ), array( 'status' => 500 ) );
        }
        $src = $this->resolve_allowed_path( $source );
        if ( is_wp_error( $src ) ) { return $src; }
        $dst = $this->resolve_allowed_path( $destination, true );
        if ( is_wp_error( $dst ) ) { return $dst; }
        $zip = new \ZipArchive();
        if ( true !== $zip->open( $dst, \ZipArchive::CREATE | \ZipArchive::OVERWRITE ) ) {
            return new WP_Error( 'wpgpt_fs_zip_create_failed', __( 'No se pudo crear el zip.', 'wpgpt-mcp-bridge' ), array( 'status' => 500 ) );
        }
        if ( is_file( $src ) ) {
            $zip->addFile( $src, basename( $src ) );
        } else {
            $rootLen = strlen( dirname( $src ) ) + 1;
            $rii = new \RecursiveIteratorIterator( new \RecursiveDirectoryIterator( $src, \FilesystemIterator::SKIP_DOTS ) );
            foreach ( $rii as $file ) {
                $path = $file->getPathname();
                $local = substr( $path, $rootLen );
                if ( $file->isDir() ) { $zip->addEmptyDir( $local ); } else { $zip->addFile( $path, $local ); }
            }
        }
        $zip->close();
        return array( 'created' => true, 'path' => $dst, 'relative' => $this->relative_display_path( $dst ) );
    }

    public function zip_extract( string $zip_path, string $destination ): array|WP_Error {
        if ( ! class_exists( 'ZipArchive' ) ) {
            return new WP_Error( 'wpgpt_fs_zip_unavailable', __( 'ZipArchive no está disponible.', 'wpgpt-mcp-bridge' ), array( 'status' => 500 ) );
        }
        $zip_path = $this->resolve_allowed_path( $zip_path );
        if ( is_wp_error( $zip_path ) ) { return $zip_path; }
        $destination = $this->resolve_allowed_path( $destination, true );
        if ( is_wp_error( $destination ) ) { return $destination; }
        if ( ! is_dir( $destination ) ) { wp_mkdir_p( $destination ); }
        $zip = new \ZipArchive();
        if ( true !== $zip->open( $zip_path ) ) {
            return new WP_Error( 'wpgpt_fs_zip_open_failed', __( 'No se pudo abrir el zip.', 'wpgpt-mcp-bridge' ), array( 'status' => 500 ) );
        }
        $zip->extractTo( $destination );
        $zip->close();
        return array( 'extracted' => true, 'zip_path' => $zip_path, 'destination' => $destination, 'relative_destination' => $this->relative_display_path( $destination ) );
    }

    public function delete_backup( string $backup_id ): array|WP_Error {
        $backup_id = preg_replace( '/[^a-zA-Z0-9_\-]/', '', $backup_id );
        if ( '' === $backup_id ) {
            return new WP_Error( 'wpgpt_fs_invalid_backup_id', __( 'Debes indicar un backup_id válido.', 'wpgpt-mcp-bridge' ), array( 'status' => 400 ) );
        }
        $json = $this->backup_root() . DIRECTORY_SEPARATOR . $backup_id . '.json';
        $bak = $this->backup_root() . DIRECTORY_SEPARATOR . $backup_id . '.bak';
        if ( is_file( $json ) ) { @unlink( $json ); }
        if ( is_file( $bak ) ) { @unlink( $bak ); }
        return array( 'deleted' => true, 'backup_id' => $backup_id );
    }

    public function plugin_list(): array {
        if ( ! function_exists( 'get_plugins' ) ) {
            require_once ABSPATH . 'wp-admin/includes/plugin.php';
        }

        $plugins        = get_plugins();
        $active_plugins = (array) get_option( 'active_plugins', array() );
        $items          = array();

        foreach ( $plugins as $plugin_file => $plugin ) {
            $slug = dirname( $plugin_file );
            if ( '.' === $slug ) {
                $slug = basename( $plugin_file, '.php' );
            }
            $items[] = array(
                'slug'        => $slug,
                'plugin_file' => $plugin_file,
                'name'        => $plugin['Name'] ?? $plugin_file,
                'version'     => $plugin['Version'] ?? '',
                'active'      => in_array( $plugin_file, $active_plugins, true ),
                'path'        => trailingslashit( WP_PLUGIN_DIR ) . $slug,
            );
        }

        usort( $items, static fn( $a, $b ) => strcasecmp( (string) $a['slug'], (string) $b['slug'] ) );

        return array( 'count' => count( $items ), 'items' => $items );
    }

    public function plugin_file_tree( string $plugin_slug, int $max_depth = 4, int $limit = 500 ): array|WP_Error {
        $plugin_slug = sanitize_key( $plugin_slug );
        $path = $this->plugin_root_from_slug( $plugin_slug );
        if ( is_wp_error( $path ) ) {
            return $path;
        }

        $max_depth = max( 1, min( 8, $max_depth ) );
        $limit     = max( 1, min( 2000, $limit ) );
        $items     = array();
        $this->scan_tree( $path, $path, 0, $max_depth, $limit, $items );

        return array(
            'plugin_slug' => $plugin_slug,
            'root'        => $path,
            'count'       => count( $items ),
            'items'       => $items,
        );
    }

    public function read_file( string $path, int $start_line = 1, int $limit_lines = 200 ): array|WP_Error {
        $absolute = $this->resolve_allowed_path( $path );
        if ( is_wp_error( $absolute ) ) {
            return $absolute;
        }

        if ( ! is_file( $absolute ) || ! is_readable( $absolute ) ) {
            return new WP_Error( 'wpgpt_fs_not_readable', __( 'El archivo no existe o no se puede leer.', 'wpgpt-mcp-bridge' ), array( 'status' => 404 ) );
        }

        $extension_check = $this->assert_allowed_extension( $absolute );
        if ( is_wp_error( $extension_check ) ) {
            return $extension_check;
        }
        $content = (string) file_get_contents( $absolute );
        $lines   = preg_split( "/\r\n|\n|\r/", $content );
        $total   = is_array( $lines ) ? count( $lines ) : 0;

        $start_line  = max( 1, $start_line );
        $limit_lines = max( 1, min( 500, $limit_lines ) );
        $offset      = $start_line - 1;
        $slice       = array_slice( (array) $lines, $offset, $limit_lines );
        $end_line    = $slice ? ( $start_line + count( $slice ) - 1 ) : $start_line;

        return array(
            'path'        => $absolute,
            'relative'    => $this->relative_display_path( $absolute ),
            'total_lines' => $total,
            'start_line'  => $start_line,
            'end_line'    => $end_line,
            'content'     => implode( "\n", $slice ),
        );
    }

    public function write_file( string $path, string $content, bool $create_if_missing = true ): array|WP_Error {
        $absolute = $this->resolve_allowed_path( $path, $create_if_missing );
        if ( is_wp_error( $absolute ) ) {
            return $absolute;
        }

        $extension_check = $this->assert_allowed_extension( $absolute );
        if ( is_wp_error( $extension_check ) ) {
            return $extension_check;
        }

        if ( file_exists( $absolute ) && ! is_writable( $absolute ) ) {
            return new WP_Error( 'wpgpt_fs_not_writable', __( 'El archivo existe pero no se puede escribir.', 'wpgpt-mcp-bridge' ), array( 'status' => 403 ) );
        }

        $dir = dirname( $absolute );
        if ( ! is_dir( $dir ) && ! wp_mkdir_p( $dir ) ) {
            return new WP_Error( 'wpgpt_fs_dir_create_failed', __( 'No se ha podido crear la carpeta destino.', 'wpgpt-mcp-bridge' ), array( 'status' => 500 ) );
        }

        $backup = $this->backup_file( $absolute );
        $result = file_put_contents( $absolute, $content );
        if ( false === $result ) {
            return new WP_Error( 'wpgpt_fs_write_failed', __( 'No se ha podido escribir el archivo.', 'wpgpt-mcp-bridge' ), array( 'status' => 500 ) );
        }

        return array(
            'written'     => true,
            'path'        => $absolute,
            'relative'    => $this->relative_display_path( $absolute ),
            'bytes'       => (int) $result,
            'backup'      => $backup,
            'created'     => ! $backup['had_original'],
        );
    }

    public function patch_file( string $path, string $search, string $replace, bool $replace_all = false ): array|WP_Error {
        if ( '' === $search ) {
            return new WP_Error( 'wpgpt_fs_empty_search', __( 'Debes indicar el texto a buscar para aplicar el parche.', 'wpgpt-mcp-bridge' ), array( 'status' => 400 ) );
        }

        $absolute = $this->resolve_allowed_path( $path );
        if ( is_wp_error( $absolute ) ) {
            return $absolute;
        }

        if ( ! is_file( $absolute ) || ! is_readable( $absolute ) || ! is_writable( $absolute ) ) {
            return new WP_Error( 'wpgpt_fs_patch_not_possible', __( 'El archivo no existe, no se puede leer o no se puede escribir.', 'wpgpt-mcp-bridge' ), array( 'status' => 403 ) );
        }

        $extension_check = $this->assert_allowed_extension( $absolute );
        if ( is_wp_error( $extension_check ) ) {
            return $extension_check;
        }

        $original = (string) file_get_contents( $absolute );
        if ( false === strpos( $original, $search ) ) {
            return new WP_Error( 'wpgpt_fs_search_not_found', __( 'El texto de búsqueda no se ha encontrado en el archivo.', 'wpgpt-mcp-bridge' ), array( 'status' => 404 ) );
        }

        $occurrences = substr_count( $original, $search );
        $updated = $replace_all ? str_replace( $search, $replace, $original ) : preg_replace( '/' . preg_quote( $search, '/' ) . '/', $replace, $original, 1 );
        if ( ! is_string( $updated ) ) {
            return new WP_Error( 'wpgpt_fs_patch_failed', __( 'No se ha podido generar el contenido parcheado.', 'wpgpt-mcp-bridge' ), array( 'status' => 500 ) );
        }

        $backup = $this->backup_file( $absolute );
        $result = file_put_contents( $absolute, $updated );
        if ( false === $result ) {
            return new WP_Error( 'wpgpt_fs_patch_write_failed', __( 'No se ha podido escribir el parche en el archivo.', 'wpgpt-mcp-bridge' ), array( 'status' => 500 ) );
        }

        return array(
            'patched'            => true,
            'path'               => $absolute,
            'relative'           => $this->relative_display_path( $absolute ),
            'occurrences_found'  => $occurrences,
            'occurrences_replaced' => $replace_all ? $occurrences : 1,
            'replace_all'        => $replace_all,
            'backup'             => $backup,
        );
    }

    public function list_backups( int $limit = 100 ): array|WP_Error {
        $backup_root = $this->backup_root();
        if ( ! is_dir( $backup_root ) ) {
            return array( 'count' => 0, 'items' => array() );
        }

        $limit = max( 1, min( 500, $limit ) );
        $items = array();
        $rii   = new \RecursiveIteratorIterator( new \RecursiveDirectoryIterator( $backup_root, \FilesystemIterator::SKIP_DOTS ) );
        foreach ( $rii as $file ) {
            if ( count( $items ) >= $limit ) {
                break;
            }
            if ( ! $file->isFile() ) {
                continue;
            }
            $path = $file->getPathname();
            if ( 'json' !== strtolower( pathinfo( $path, PATHINFO_EXTENSION ) ) ) {
                continue;
            }
            $meta = json_decode( (string) file_get_contents( $path ), true );
            if ( ! is_array( $meta ) ) {
                continue;
            }
            $items[] = $meta;
        }

        usort( $items, static fn( $a, $b ) => strcmp( (string) ( $b['created_gmt'] ?? '' ), (string) ( $a['created_gmt'] ?? '' ) ) );

        return array( 'count' => count( $items ), 'items' => $items );
    }

    public function restore_backup( string $backup_id ): array|WP_Error {
        $backup_id = preg_replace( '/[^a-zA-Z0-9_\-]/', '', $backup_id );
        if ( '' === $backup_id ) {
            return new WP_Error( 'wpgpt_fs_invalid_backup_id', __( 'Debes indicar un backup_id válido.', 'wpgpt-mcp-bridge' ), array( 'status' => 400 ) );
        }

        $meta_path = $this->backup_root() . DIRECTORY_SEPARATOR . $backup_id . '.json';
        if ( ! is_file( $meta_path ) ) {
            return new WP_Error( 'wpgpt_fs_backup_not_found', __( 'No se ha encontrado el backup solicitado.', 'wpgpt-mcp-bridge' ), array( 'status' => 404 ) );
        }
        $meta = json_decode( (string) file_get_contents( $meta_path ), true );
        if ( ! is_array( $meta ) ) {
            return new WP_Error( 'wpgpt_fs_backup_invalid', __( 'El backup solicitado está dañado o incompleto.', 'wpgpt-mcp-bridge' ), array( 'status' => 500 ) );
        }

        $target = $this->resolve_allowed_path( (string) ( $meta['path'] ?? '' ), true );
        if ( is_wp_error( $target ) ) {
            return $target;
        }

        $content_path = (string) ( $meta['backup_content_path'] ?? '' );
        if ( ! is_file( $content_path ) ) {
            return new WP_Error( 'wpgpt_fs_backup_missing_content', __( 'No se ha encontrado el contenido del backup.', 'wpgpt-mcp-bridge' ), array( 'status' => 404 ) );
        }

        $content = (string) file_get_contents( $content_path );
        $current_backup = $this->backup_file( $target );
        $result = file_put_contents( $target, $content );
        if ( false === $result ) {
            return new WP_Error( 'wpgpt_fs_restore_failed', __( 'No se ha podido restaurar el backup.', 'wpgpt-mcp-bridge' ), array( 'status' => 500 ) );
        }

        return array(
            'restored'      => true,
            'backup_id'     => $backup_id,
            'path'          => $target,
            'relative'      => $this->relative_display_path( $target ),
            'previous_backup' => $current_backup,
        );
    }


    public function make_directory( string $path ): array|WP_Error {
        $absolute = $this->resolve_allowed_path( $path, true );
        if ( is_wp_error( $absolute ) ) { return $absolute; }
        if ( is_dir( $absolute ) ) {
            return array( 'created' => true, 'path' => $absolute, 'relative' => $this->relative_display_path( $absolute ), 'already_exists' => true );
        }
        if ( ! wp_mkdir_p( $absolute ) ) {
            return new WP_Error( 'wpgpt_fs_mkdir_failed', __( 'No se pudo crear la carpeta indicada.', 'wpgpt-mcp-bridge' ), array( 'status' => 500 ) );
        }
        return array( 'created' => true, 'path' => $absolute, 'relative' => $this->relative_display_path( $absolute ) );
    }

    public function delete_path( string $path ): array|WP_Error {
        $absolute = $this->resolve_allowed_path( $path );
        if ( is_wp_error( $absolute ) ) { return $absolute; }
        if ( is_dir( $absolute ) ) {
            $this->delete_dir_recursive( $absolute );
            return array( 'deleted' => true, 'type' => 'dir', 'path' => $absolute, 'relative' => $this->relative_display_path( $absolute ) );
        }
        if ( is_file( $absolute ) ) {
            $backup = $this->backup_file( $absolute );
            if ( ! @unlink( $absolute ) ) {
                return new WP_Error( 'wpgpt_fs_delete_failed', __( 'No se pudo eliminar el archivo indicado.', 'wpgpt-mcp-bridge' ), array( 'status' => 500 ) );
            }
            return array( 'deleted' => true, 'type' => 'file', 'path' => $absolute, 'relative' => $this->relative_display_path( $absolute ), 'backup' => $backup );
        }
        return new WP_Error( 'wpgpt_fs_not_found', __( 'La ruta indicada no existe.', 'wpgpt-mcp-bridge' ), array( 'status' => 404 ) );
    }

    public function copy_path( string $source, string $destination, bool $overwrite = false ): array|WP_Error {
        $src = $this->resolve_allowed_path( $source );
        if ( is_wp_error( $src ) ) { return $src; }
        $dst = $this->resolve_allowed_path( $destination, true );
        if ( is_wp_error( $dst ) ) { return $dst; }
        if ( file_exists( $dst ) && ! $overwrite ) {
            return new WP_Error( 'wpgpt_fs_target_exists', __( 'La ruta destino ya existe.', 'wpgpt-mcp-bridge' ), array( 'status' => 409 ) );
        }
        if ( is_file( $src ) ) {
            if ( ! is_dir( dirname( $dst ) ) ) { wp_mkdir_p( dirname( $dst ) ); }
            if ( ! @copy( $src, $dst ) ) {
                return new WP_Error( 'wpgpt_fs_copy_failed', __( 'No se pudo copiar el archivo.', 'wpgpt-mcp-bridge' ), array( 'status' => 500 ) );
            }
            return array( 'copied' => true, 'source' => $src, 'destination' => $dst );
        }
        if ( is_dir( $src ) ) {
            $this->copy_dir_recursive( $src, $dst, $overwrite );
            return array( 'copied' => true, 'source' => $src, 'destination' => $dst, 'type' => 'dir' );
        }
        return new WP_Error( 'wpgpt_fs_not_found', __( 'La ruta origen no existe.', 'wpgpt-mcp-bridge' ), array( 'status' => 404 ) );
    }

    public function move_path( string $source, string $destination, bool $overwrite = false ): array|WP_Error {
        $copied = $this->copy_path( $source, $destination, $overwrite );
        if ( is_wp_error( $copied ) ) { return $copied; }
        $deleted = $this->delete_path( $source );
        if ( is_wp_error( $deleted ) ) { return $deleted; }
        return array( 'moved' => true, 'source' => $source, 'destination' => $destination );
    }

    public function rename_path( string $path, string $new_name ): array|WP_Error {
        $absolute = $this->resolve_allowed_path( $path );
        if ( is_wp_error( $absolute ) ) { return $absolute; }
        $new_name = basename( sanitize_file_name( $new_name ) );
        if ( '' === $new_name ) {
            return new WP_Error( 'wpgpt_fs_invalid_name', __( 'Debes indicar un nuevo nombre válido.', 'wpgpt-mcp-bridge' ), array( 'status' => 400 ) );
        }
        $destination = trailingslashit( dirname( $absolute ) ) . $new_name;
        return $this->move_path( $absolute, $destination, false );
    }

    public function search_files( string $query, string $scope = 'plugins', array $extensions = array(), int $limit = 50 ): array|WP_Error {
        $query = (string) $query;
        if ( '' === trim( $query ) ) {
            return new WP_Error( 'wpgpt_fs_empty_query', __( 'Debes indicar un texto de búsqueda.', 'wpgpt-mcp-bridge' ), array( 'status' => 400 ) );
        }

        $root = $this->scope_to_root( $scope );
        if ( is_wp_error( $root ) ) {
            return $root;
        }

        $allowed_exts = empty( $extensions ) ? self::ALLOWED_EXTENSIONS : array_values( array_intersect( array_map( 'strtolower', $extensions ), self::ALLOWED_EXTENSIONS ) );
        if ( empty( $allowed_exts ) ) {
            $allowed_exts = self::ALLOWED_EXTENSIONS;
        }

        $limit   = max( 1, min( 200, $limit ) );
        $items   = array();
        $rii     = new \RecursiveIteratorIterator( new \RecursiveDirectoryIterator( $root, \FilesystemIterator::SKIP_DOTS ) );

        foreach ( $rii as $file ) {
            if ( count( $items ) >= $limit ) {
                break;
            }
            if ( ! $file->isFile() ) {
                continue;
            }
            $path = $file->getPathname();
            $ext  = strtolower( pathinfo( $path, PATHINFO_EXTENSION ) );
            if ( ! in_array( $ext, $allowed_exts, true ) ) {
                continue;
            }
            $content = @file( $path );
            if ( false === $content ) {
                continue;
            }
            foreach ( $content as $index => $line ) {
                if ( false !== stripos( $line, $query ) ) {
                    $items[] = array(
                        'path'        => $path,
                        'relative'    => $this->relative_display_path( $path ),
                        'line_number' => $index + 1,
                        'excerpt'     => trim( $line ),
                    );
                    break;
                }
            }
        }

        return array(
            'query'  => $query,
            'scope'  => $scope,
            'count'  => count( $items ),
            'items'  => $items,
        );
    }

    private function scan_tree( string $root, string $current, int $depth, int $max_depth, int $limit, array &$items ): void {
        if ( $depth > $max_depth || count( $items ) >= $limit ) {
            return;
        }

        $entries = @scandir( $current );
        if ( ! is_array( $entries ) ) {
            return;
        }

        foreach ( $entries as $entry ) {
            if ( '.' === $entry || '..' === $entry ) {
                continue;
            }
            if ( count( $items ) >= $limit ) {
                break;
            }
            $path = $current . DIRECTORY_SEPARATOR . $entry;
            $is_dir = is_dir( $path );
            $items[] = array(
                'path'     => $path,
                'relative' => ltrim( str_replace( $root, '', $path ), DIRECTORY_SEPARATOR ),
                'type'     => $is_dir ? 'dir' : 'file',
            );
            if ( $is_dir ) {
                $this->scan_tree( $root, $path, $depth + 1, $max_depth, $limit, $items );
            }
        }
    }

    private function plugin_root_from_slug( string $plugin_slug ): string|WP_Error {
        $candidate = trailingslashit( WP_PLUGIN_DIR ) . $plugin_slug;
        $resolved  = realpath( $candidate );
        if ( false === $resolved || ! is_dir( $resolved ) ) {
            return new WP_Error( 'wpgpt_fs_plugin_not_found', __( 'No se ha encontrado la carpeta del plugin.', 'wpgpt-mcp-bridge' ), array( 'status' => 404 ) );
        }

        return $this->ensure_allowed_root( $resolved );
    }

    private function scope_to_root( string $scope ): string|WP_Error {
        $scope = sanitize_text_field( $scope );
        if ( 'plugins' === $scope ) {
            return $this->ensure_allowed_root( WP_PLUGIN_DIR );
        }
        if ( 'themes' === $scope ) {
            return $this->ensure_allowed_root( get_theme_root() );
        }
        if ( 'mu-plugins' === $scope ) {
            return $this->ensure_allowed_root( WPMU_PLUGIN_DIR );
        }

        return new WP_Error( 'wpgpt_fs_scope_not_allowed', __( 'El scope indicado no está permitido.', 'wpgpt-mcp-bridge' ), array( 'status' => 400 ) );
    }

    private function resolve_allowed_path( string $path, bool $allow_missing = false ): string|WP_Error {
        $path = trim( $path );
        if ( '' === $path ) {
            return new WP_Error( 'wpgpt_fs_empty_path', __( 'Debes indicar una ruta.', 'wpgpt-mcp-bridge' ), array( 'status' => 400 ) );
        }

        if ( 0 === strpos( $path, 'wp-content/' ) ) {
            $candidate = trailingslashit( ABSPATH ) . ltrim( $path, '/' );
        } else {
            $candidate = $path;
        }

        $candidate = wp_normalize_path( $candidate );
        $resolved  = realpath( $candidate );
        if ( false === $resolved ) {
            if ( $allow_missing ) {
                $parent = realpath( dirname( $candidate ) );
                if ( false === $parent ) {
                    return new WP_Error( 'wpgpt_fs_path_not_found', __( 'La ruta indicada no existe.', 'wpgpt-mcp-bridge' ), array( 'status' => 404 ) );
                }
                $parent = $this->ensure_allowed_root( $parent );
                if ( is_wp_error( $parent ) ) {
                    return $parent;
                }
                return wp_normalize_path( $parent . DIRECTORY_SEPARATOR . basename( $candidate ) );
            }
            return new WP_Error( 'wpgpt_fs_path_not_found', __( 'La ruta indicada no existe.', 'wpgpt-mcp-bridge' ), array( 'status' => 404 ) );
        }

        return $this->ensure_allowed_root( $resolved );
    }

    private function ensure_allowed_root( string $resolved ): string|WP_Error {
        $resolved = wp_normalize_path( $resolved );
        $allowed_roots = array(
            wp_normalize_path( WP_PLUGIN_DIR ),
            wp_normalize_path( get_theme_root() ),
            wp_normalize_path( WPMU_PLUGIN_DIR ),
        );

        foreach ( $allowed_roots as $root ) {
            if ( '' !== $root && 0 === strpos( $resolved, $root ) ) {
                return $resolved;
            }
        }

        return new WP_Error( 'wpgpt_fs_path_not_allowed', __( 'La ruta indicada está fuera de las carpetas permitidas.', 'wpgpt-mcp-bridge' ), array( 'status' => 403 ) );
    }

    private function assert_allowed_extension( string $absolute ): true|WP_Error {
        $extension = strtolower( pathinfo( $absolute, PATHINFO_EXTENSION ) );
        if ( '' === $extension || ! in_array( $extension, self::ALLOWED_EXTENSIONS, true ) ) {
            return new WP_Error( 'wpgpt_fs_extension_not_allowed', __( 'La extensión del archivo no está permitida.', 'wpgpt-mcp-bridge' ), array( 'status' => 403 ) );
        }

        return true;
    }

    private function relative_display_path( string $absolute ): string {
        $absolute = wp_normalize_path( $absolute );
        $base     = wp_normalize_path( ABSPATH );
        if ( 0 === strpos( $absolute, $base ) ) {
            return ltrim( substr( $absolute, strlen( $base ) ), '/' );
        }

        return $absolute;
    }

    private function backup_root(): string {
        return wp_normalize_path( trailingslashit( WP_CONTENT_DIR ) . self::BACKUP_DIR );
    }

    private function backup_file( string $absolute ): array|WP_Error {
        $absolute = wp_normalize_path( $absolute );
        $backup_root = $this->backup_root();
        if ( ! is_dir( $backup_root ) && ! wp_mkdir_p( $backup_root ) ) {
            return new WP_Error( 'wpgpt_fs_backup_dir_failed', __( 'No se ha podido crear la carpeta de backups.', 'wpgpt-mcp-bridge' ), array( 'status' => 500 ) );
        }

        $backup_id = gmdate( 'Ymd_His' ) . '_' . wp_generate_password( 6, false, false );
        $content_path = $backup_root . DIRECTORY_SEPARATOR . $backup_id . '.bak';
        $meta_path    = $backup_root . DIRECTORY_SEPARATOR . $backup_id . '.json';
        $had_original = is_file( $absolute );
        $content      = $had_original ? (string) file_get_contents( $absolute ) : '';
        file_put_contents( $content_path, $content );
        $meta = array(
            'backup_id'           => $backup_id,
            'path'                => $absolute,
            'relative'            => $this->relative_display_path( $absolute ),
            'created_gmt'         => gmdate( 'c' ),
            'had_original'        => $had_original,
            'backup_content_path' => $content_path,
            'backup_meta_path'    => $meta_path,
        );
        file_put_contents( $meta_path, wp_json_encode( $meta, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES ) );

        return $meta;
    }

    private function delete_dir_recursive( string $dir ): void {
        $items = array_diff( scandir( $dir ) ?: array(), array( '.', '..' ) );
        foreach ( $items as $item ) {
            $path = $dir . DIRECTORY_SEPARATOR . $item;
            if ( is_dir( $path ) ) {
                $this->delete_dir_recursive( $path );
            } else {
                @unlink( $path );
            }
        }
        @rmdir( $dir );
    }

    private function copy_dir_recursive( string $src, string $dst, bool $overwrite = false ): void {
        if ( ! is_dir( $dst ) ) { wp_mkdir_p( $dst ); }
        $items = array_diff( scandir( $src ) ?: array(), array( '.', '..' ) );
        foreach ( $items as $item ) {
            $from = $src . DIRECTORY_SEPARATOR . $item;
            $to   = $dst . DIRECTORY_SEPARATOR . $item;
            if ( is_dir( $from ) ) {
                $this->copy_dir_recursive( $from, $to, $overwrite );
            } else {
                if ( file_exists( $to ) && ! $overwrite ) {
                    continue;
                }
                @copy( $from, $to );
            }
        }
    }

    public function theme_catalog(): array {
        $items = array();
        foreach ( wp_get_themes() as $stylesheet => $theme ) {
            $items[] = array(
                'stylesheet' => $stylesheet,
                'name'       => $theme->get( 'Name' ),
                'version'    => $theme->get( 'Version' ),
                'active'     => get_stylesheet() === $stylesheet,
                'path'       => wp_normalize_path( $theme->get_stylesheet_directory() ),
            );
        }
        usort( $items, static fn( $a, $b ) => strcasecmp( (string) $a['stylesheet'], (string) $b['stylesheet'] ) );
        return array( 'count' => count( $items ), 'items' => $items );
    }

    public function inspect_backup( string $backup_id ): array|WP_Error {
        $backup_id = preg_replace( '/[^a-zA-Z0-9_\-]/', '', $backup_id );
        if ( '' === $backup_id ) {
            return new WP_Error( 'wpgpt_fs_invalid_backup_id', __( 'Debes indicar un backup_id válido.', 'wpgpt-mcp-bridge' ), array( 'status' => 400 ) );
        }
        $meta_path = $this->backup_root() . DIRECTORY_SEPARATOR . $backup_id . '.json';
        if ( ! is_file( $meta_path ) ) {
            return new WP_Error( 'wpgpt_fs_backup_not_found', __( 'No se ha encontrado el backup solicitado.', 'wpgpt-mcp-bridge' ), array( 'status' => 404 ) );
        }
        $meta = json_decode( (string) file_get_contents( $meta_path ), true );
        if ( ! is_array( $meta ) ) {
            return new WP_Error( 'wpgpt_fs_backup_invalid', __( 'El backup solicitado está dañado o incompleto.', 'wpgpt-mcp-bridge' ), array( 'status' => 500 ) );
        }
        return $meta;
    }

    public function query( array $input = array() ): array|WP_Error {
        $scope = sanitize_key( (string) ( $input['scope'] ?? 'all' ) );
        $limit = isset( $input['limit'] ) ? (int) $input['limit'] : 50;
        $items = array();
        $warnings = array();

        if ( 'all' === $scope || 'plugins' === $scope ) {
            $result = $this->plugin_list();
            $items[] = array( 'scope' => 'plugins', 'item' => $result );
        }
        if ( 'all' === $scope || 'themes' === $scope ) {
            $result = $this->theme_catalog();
            $items[] = array( 'scope' => 'themes', 'item' => $result );
        }
        if ( 'plugin_tree' === $scope ) {
            $result = $this->plugin_file_tree( sanitize_key( (string) ( $input['plugin_slug'] ?? '' ) ), isset( $input['max_depth'] ) ? (int) $input['max_depth'] : 4, $limit );
            if ( is_wp_error( $result ) ) { return $result; }
            $items[] = array( 'scope' => 'plugin_tree', 'item' => $result );
        }
        if ( 'theme_tree' === $scope ) {
            $result = $this->theme_file_tree( sanitize_text_field( (string) ( $input['stylesheet'] ?? '' ) ), isset( $input['max_depth'] ) ? (int) $input['max_depth'] : 4, $limit );
            if ( is_wp_error( $result ) ) { return $result; }
            $items[] = array( 'scope' => 'theme_tree', 'item' => $result );
        }
        if ( 'search' === $scope ) {
            $result = $this->search_files( (string) ( $input['query'] ?? '' ), 'plugins', isset( $input['extensions'] ) && is_array( $input['extensions'] ) ? $input['extensions'] : array(), $limit );
            if ( is_wp_error( $result ) ) { return $result; }
            $items[] = array( 'scope' => 'search', 'item' => $result );
        }
        if ( 'all' === $scope || 'backups' === $scope ) {
            $result = $this->list_backups( $limit );
            if ( is_wp_error( $result ) ) { return $result; }
            $items[] = array( 'scope' => 'backups', 'item' => $result );
        }

        if ( empty( $items ) ) {
            $warnings[] = __( 'No se han encontrado resultados para ese scope.', 'wpgpt-mcp-bridge' );
        }

        return array(
            'summary' => array( 'scope' => $scope, 'returned' => count( $items ) ),
            'items' => $items,
            'warnings' => $warnings,
            'next_actions' => array(),
        );
    }

    public function inspect( array $input = array() ): array|WP_Error {
        $mode = sanitize_key( (string) ( $input['mode'] ?? 'path' ) );
        $result = match ( $mode ) {
            'path' => $this->read_file( (string) ( $input['path'] ?? '' ), isset( $input['start_line'] ) ? (int) $input['start_line'] : 1, isset( $input['limit_lines'] ) ? (int) $input['limit_lines'] : 200 ),
            'diff' => $this->diff_files( (string) ( $input['path_a'] ?? '' ), (string) ( $input['path_b'] ?? '' ) ),
            'plugin_tree' => $this->plugin_file_tree( sanitize_key( (string) ( $input['plugin_slug'] ?? '' ) ), isset( $input['max_depth'] ) ? (int) $input['max_depth'] : 4, isset( $input['limit'] ) ? (int) $input['limit'] : 500 ),
            'theme_tree' => $this->theme_file_tree( sanitize_text_field( (string) ( $input['stylesheet'] ?? '' ) ), isset( $input['max_depth'] ) ? (int) $input['max_depth'] : 4, isset( $input['limit'] ) ? (int) $input['limit'] : 500 ),
            'backup' => $this->inspect_backup( (string) ( $input['backup_id'] ?? '' ) ),
            default => new WP_Error( 'wpgpt_fs_mode_invalid', __( 'Modo de filesystem inspect no válido.', 'wpgpt-mcp-bridge' ), array( 'status' => 400 ) ),
        };
        if ( is_wp_error( $result ) ) { return $result; }
        return array(
            'summary' => array( 'mode' => $mode, 'inspected' => 1 ),
            'items' => array( $result ),
            'warnings' => array(),
            'next_actions' => array(
                __( 'Usa wpgpt/filesystem-apply con dry_run=true antes de ejecutar cambios.', 'wpgpt-mcp-bridge' ),
            ),
        );
    }

    public function apply( array $input = array() ): array|WP_Error {
        $action = sanitize_key( (string) ( $input['action'] ?? '' ) );
        $dry_run = ! empty( $input['dry_run'] );
        $payload = isset( $input['payload'] ) && is_array( $input['payload'] ) ? $input['payload'] : array();
        if ( $dry_run ) {
            return array(
                'summary' => array( 'action' => $action, 'dry_run' => true ),
                'items' => array(),
                'warnings' => array(),
                'blocked' => array(),
                'next_actions' => array( __( 'Repite la misma llamada con dry_run=false para aplicar los cambios validados.', 'wpgpt-mcp-bridge' ) ),
            );
        }
        $result = match ( $action ) {
            'mkdir' => $this->make_directory( (string) ( $payload['path'] ?? '' ) ),
            'copy' => $this->copy_path( (string) ( $payload['source'] ?? '' ), (string) ( $payload['destination'] ?? '' ), ! empty( $payload['overwrite'] ) ),
            'move' => $this->move_path( (string) ( $payload['source'] ?? '' ), (string) ( $payload['destination'] ?? '' ), ! empty( $payload['overwrite'] ) ),
            'rename' => $this->rename_path( (string) ( $payload['path'] ?? '' ), (string) ( $payload['new_name'] ?? '' ) ),
            'delete' => $this->delete_path( (string) ( $payload['path'] ?? '' ) ),
            'zip_create' => $this->zip_create( (string) ( $payload['source'] ?? '' ), (string) ( $payload['destination'] ?? '' ) ),
            'zip_extract' => $this->zip_extract( (string) ( $payload['zip_path'] ?? '' ), (string) ( $payload['destination'] ?? '' ) ),
            'write' => $this->write_file( (string) ( $payload['path'] ?? '' ), (string) ( $payload['content'] ?? '' ), ! array_key_exists( 'create_if_missing', $payload ) || ! empty( $payload['create_if_missing'] ) ),
            'patch' => $this->patch_file( (string) ( $payload['path'] ?? '' ), (string) ( $payload['search'] ?? '' ), (string) ( $payload['replace'] ?? '' ), ! empty( $payload['replace_all'] ) ),
            'backup_restore' => $this->restore_backup( (string) ( $payload['backup_id'] ?? '' ) ),
            'backup_delete' => $this->delete_backup( (string) ( $payload['backup_id'] ?? '' ) ),
            default => new WP_Error( 'wpgpt_fs_action_invalid', __( 'Acción de filesystem no válida.', 'wpgpt-mcp-bridge' ), array( 'status' => 400 ) ),
        };
        if ( is_wp_error( $result ) ) { return $result; }
        return array(
            'summary' => array( 'action' => $action, 'dry_run' => false ),
            'items' => array( $result ),
            'warnings' => array(),
            'blocked' => array(),
            'next_actions' => array(),
        );
    }

}
