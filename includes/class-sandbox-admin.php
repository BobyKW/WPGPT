<?php

namespace WPGPT\MCPBridge;

if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

class Sandbox_Admin {
    public static function init(): void {
        add_action( 'admin_post_wpgpt_mcp_sandbox_action', array( __CLASS__, 'handle_post' ) );
    }

    public static function root(): string {
        return trailingslashit( WP_CONTENT_DIR ) . 'wpgpt-sandbox';
    }

    public static function ensure_root(): bool {
        return is_dir( self::root() ) || wp_mkdir_p( self::root() );
    }

    public static function handle_post(): void {
        if ( ! current_user_can( 'manage_options' ) ) {
            wp_die( esc_html__( 'No tienes permisos suficientes.', 'wpgpt-mcp-bridge' ) );
        }
        check_admin_referer( 'wpgpt_mcp_sandbox_action' );
        $action = isset( $_POST['sandbox_action'] ) ? sanitize_key( wp_unslash( (string) $_POST['sandbox_action'] ) ) : '';

        if ( 'create_file' === $action ) { self::create_file(); }
        elseif ( 'create_dir' === $action ) { self::create_dir(); }
        elseif ( 'save_file' === $action ) { self::save_file(); }
        elseif ( 'delete' === $action ) { self::delete_path(); }
        elseif ( 'toggle' === $action ) { self::toggle_file(); }
        elseif ( 'clear_crash' === $action ) { if ( class_exists( Sandbox_Loader::class ) ) { Sandbox_Loader::clear_crash(); } self::redirect( 'safe_mode_cleared' ); }
        elseif ( 'enable_loader' === $action ) { if ( class_exists( Sandbox_Loader::class ) ) { Sandbox_Loader::set_enabled( true ); } self::redirect( 'loader_enabled' ); }
        elseif ( 'disable_loader' === $action ) { if ( class_exists( Sandbox_Loader::class ) ) { Sandbox_Loader::set_enabled( false ); } self::redirect( 'loader_disabled' ); }

        self::redirect();
    }

    public static function render_page(): void {
        if ( ! current_user_can( 'manage_options' ) ) { return; }

        self::ensure_root();
        $notice   = isset( $_GET['notice'] ) ? sanitize_key( wp_unslash( (string) $_GET['notice'] ) ) : '';
        $error    = get_transient( 'wpgpt_mcp_sandbox_error' );
        $relative = isset( $_GET['path'] ) ? self::normalize_relative( sanitize_text_field( wp_unslash( (string) $_GET['path'] ) ) ) : '';
        $path     = self::resolve( $relative, false );
        if ( is_wp_error( $path ) ) { $path = self::root(); }
        $is_file = is_file( $path );
        $dir     = $is_file ? dirname( $path ) : $path;
        $items   = self::items( $dir );
        $content = '';
        $size    = 0;
        if ( $is_file ) {
            $size = (int) filesize( $path );
            if ( $size <= 524288 ) {
                $read = file_get_contents( $path );
                $content = false === $read ? '' : (string) $read;
            }
        }
        $loader_enabled = class_exists( Sandbox_Loader::class ) && Sandbox_Loader::is_enabled();
        $safe_mode      = class_exists( Sandbox_Loader::class ) && Sandbox_Loader::is_safe_mode();
        $crash_info     = class_exists( Sandbox_Loader::class ) ? Sandbox_Loader::crash_info() : array();
        $active_php     = 0;
        foreach ( glob( trailingslashit( self::root() ) . '*.php' ) ?: array() as $php_file ) { if ( is_file( $php_file ) ) { $active_php++; } }
        ?>
        <div class="wrap wpgpt-admin-wrap wpgpt-sandbox-wrap">
            <style>
                .wpgpt-admin-wrap{max-width:1280px}.wpgpt-sandbox-grid{display:grid;grid-template-columns:minmax(320px,430px) 1fr;gap:18px;align-items:start;margin-top:18px}.wpgpt-card{background:#fff;border:1px solid #dcdcde;border-radius:10px;box-shadow:0 1px 1px rgba(0,0,0,.03);overflow:hidden}.wpgpt-card-body{padding:18px 20px 20px}.wpgpt-muted{color:#646970}.wpgpt-path{background:#f6f7f7;border:1px solid #dcdcde;border-radius:6px;padding:8px 10px;font-family:monospace;word-break:break-all}.wpgpt-file-list{margin:0}.wpgpt-file-list li{display:flex;justify-content:space-between;gap:10px;align-items:center;border-bottom:1px solid #f0f0f1;padding:10px 0;margin:0}.wpgpt-file-main{min-width:0}.wpgpt-file-main a{text-decoration:none;font-weight:600}.wpgpt-file-meta{display:block;margin-top:3px;color:#646970;font-size:12px}.wpgpt-editor{width:100%;min-height:460px;font-family:Consolas,Monaco,monospace;font-size:13px;line-height:1.5}.wpgpt-inline-form{display:inline;margin:0}.wpgpt-new-grid{display:grid;grid-template-columns:1fr auto;gap:8px;margin-top:10px}.wpgpt-actions{white-space:nowrap}.wpgpt-warning{border-left:4px solid #d63638}.wpgpt-status-ok{color:#008a20;font-weight:600}.wpgpt-status-bad{color:#b32d2e;font-weight:600}@media(max-width:960px){.wpgpt-sandbox-grid{grid-template-columns:1fr}}
            </style>
            <h1><?php echo esc_html__( 'WPGPT MCP Sandbox', 'wpgpt-mcp-bridge' ); ?></h1>
            <p class="wpgpt-muted"><?php echo esc_html__( 'Área controlada para archivos creados por IA. Puede cargar PHP persistente solo si lo activas manualmente; si un archivo rompe WordPress, entra en safe mode.', 'wpgpt-mcp-bridge' ); ?></p>
            <?php if ( $notice ) : ?><div class="notice notice-success"><p><?php echo esc_html( self::notice_text( $notice ) ); ?></p></div><?php endif; ?>
            <?php if ( $error ) : ?><div class="notice notice-error"><p><?php echo esc_html( (string) $error ); ?></p></div><?php delete_transient( 'wpgpt_mcp_sandbox_error' ); endif; ?>
            <div class="wpgpt-path"><strong><?php echo esc_html__( 'Ruta:', 'wpgpt-mcp-bridge' ); ?></strong> <?php echo esc_html( self::root() ); ?></div>

            <div class="wpgpt-sandbox-grid">
                <div class="wpgpt-card"><div class="wpgpt-card-body">
                    <h2><?php echo esc_html__( 'Estado del sandbox', 'wpgpt-mcp-bridge' ); ?></h2>
                    <p><strong><?php echo esc_html__( 'Carga PHP persistente:', 'wpgpt-mcp-bridge' ); ?></strong> <span class="<?php echo $loader_enabled ? 'wpgpt-status-ok' : ''; ?>"><?php echo $loader_enabled ? esc_html__( 'Activa', 'wpgpt-mcp-bridge' ) : esc_html__( 'Desactivada', 'wpgpt-mcp-bridge' ); ?></span></p>
                    <p><strong><?php echo esc_html__( 'Safe mode:', 'wpgpt-mcp-bridge' ); ?></strong> <span class="<?php echo $safe_mode ? 'wpgpt-status-bad' : 'wpgpt-status-ok'; ?>"><?php echo $safe_mode ? esc_html__( 'Activo', 'wpgpt-mcp-bridge' ) : esc_html__( 'Inactivo', 'wpgpt-mcp-bridge' ); ?></span></p>
                    <p><strong><?php echo esc_html__( 'PHP activos en raíz:', 'wpgpt-mcp-bridge' ); ?></strong> <?php echo esc_html( (string) $active_php ); ?></p>
                    <p class="wpgpt-muted"><?php echo esc_html__( 'Solo se cargan automáticamente archivos *.php ubicados directamente en la raíz del sandbox. Los archivos .disabled se ignoran.', 'wpgpt-mcp-bridge' ); ?></p>
                    <p><?php self::action_button( $loader_enabled ? 'disable_loader' : 'enable_loader', '', $loader_enabled ? __( 'Desactivar carga persistente', 'wpgpt-mcp-bridge' ) : __( 'Activar carga persistente', 'wpgpt-mcp-bridge' ), true ); ?> <?php if ( $safe_mode ) : ?><?php self::action_button( 'clear_crash', '', __( 'Salir de safe mode', 'wpgpt-mcp-bridge' ), true ); ?><?php endif; ?></p>
                    <?php if ( $safe_mode ) : ?><div class="notice notice-error inline"><p><strong><?php echo esc_html__( 'Último error:', 'wpgpt-mcp-bridge' ); ?></strong> <?php echo esc_html( isset( $crash_info['message'] ) ? (string) $crash_info['message'] : __( 'No disponible', 'wpgpt-mcp-bridge' ) ); ?></p><?php if ( ! empty( $crash_info['sandbox_file'] ) ) : ?><p><code><?php echo esc_html( (string) $crash_info['sandbox_file'] ); ?></code></p><?php endif; ?></div><?php endif; ?>
                </div></div>

                <div class="wpgpt-card"><div class="wpgpt-card-body">
                    <h2><?php echo esc_html__( 'Crear', 'wpgpt-mcp-bridge' ); ?></h2>
                    <?php self::create_form( 'create_file', __( 'Crear archivo', 'wpgpt-mcp-bridge' ), 'test.php' ); ?>
                    <?php self::create_form( 'create_dir', __( 'Crear carpeta', 'wpgpt-mcp-bridge' ), 'scripts' ); ?>
                </div></div>

                <div class="wpgpt-card"><div class="wpgpt-card-body">
                    <h2><?php echo esc_html__( 'Explorador', 'wpgpt-mcp-bridge' ); ?></h2>
                    <p class="wpgpt-muted"><?php echo esc_html__( 'Crea, abre, activa, desactiva o elimina elementos dentro de la sandbox.', 'wpgpt-mcp-bridge' ); ?></p>
                    <?php $dir_rel = self::relative_from_root( $dir ); if ( '' !== $dir_rel ) : ?><p><a class="button" href="<?php echo esc_url( self::admin_url( dirname( $dir_rel ) ) ); ?>">← <?php echo esc_html__( 'Subir nivel', 'wpgpt-mcp-bridge' ); ?></a></p><?php endif; ?>
                    <ul class="wpgpt-file-list">
                        <?php if ( empty( $items ) ) : ?><li><span class="wpgpt-muted"><?php echo esc_html__( 'La carpeta está vacía.', 'wpgpt-mcp-bridge' ); ?></span></li><?php endif; ?>
                        <?php foreach ( $items as $item ) : ?>
                            <li>
                                <div class="wpgpt-file-main"><a href="<?php echo esc_url( self::admin_url( $item['relative'] ) ); ?>"><?php echo esc_html( $item['icon'] . ' ' . $item['name'] ); ?></a><span class="wpgpt-file-meta"><?php echo esc_html( $item['type'] . ' · ' . $item['size'] . ' · ' . $item['modified'] ); ?></span></div>
                                <div class="wpgpt-actions"><?php if ( 'file' === $item['type'] ) : ?><?php self::action_button( 'toggle', $item['relative'], str_ends_with( $item['name'], '.disabled' ) ? __( 'Activar', 'wpgpt-mcp-bridge' ) : __( 'Desactivar', 'wpgpt-mcp-bridge' ) ); ?><?php endif; ?> <?php self::action_button( 'delete', $item['relative'], __( 'Eliminar', 'wpgpt-mcp-bridge' ), true ); ?></div>
                            </li>
                        <?php endforeach; ?>
                    </ul>
                </div></div>

                <div class="wpgpt-card <?php echo $is_file ? '' : 'wpgpt-warning'; ?>" style="grid-column:1/-1"><div class="wpgpt-card-body">
                    <?php if ( $is_file ) : ?>
                        <h2><?php echo esc_html__( 'Editor', 'wpgpt-mcp-bridge' ); ?></h2>
                        <p><code><?php echo esc_html( self::relative_from_root( $path ) ); ?></code></p>
                        <?php if ( $size > 524288 ) : ?><p class="wpgpt-muted"><?php echo esc_html__( 'El archivo supera 512 KB y no se carga en el editor.', 'wpgpt-mcp-bridge' ); ?></p><?php else : ?>
                            <form method="post" action="<?php echo esc_url( admin_url( 'admin-post.php' ) ); ?>">
                                <?php wp_nonce_field( 'wpgpt_mcp_sandbox_action' ); ?>
                                <input type="hidden" name="action" value="wpgpt_mcp_sandbox_action" />
                                <input type="hidden" name="sandbox_action" value="save_file" />
                                <input type="hidden" name="path" value="<?php echo esc_attr( self::relative_from_root( $path ) ); ?>" />
                                <textarea class="wpgpt-editor" name="content" spellcheck="false"><?php echo esc_textarea( $content ); ?></textarea>
                                <p><?php submit_button( __( 'Guardar archivo', 'wpgpt-mcp-bridge' ), 'primary', 'submit', false ); ?></p>
                            </form>
                        <?php endif; ?>
                    <?php else : ?><h2><?php echo esc_html__( 'Sin archivo seleccionado', 'wpgpt-mcp-bridge' ); ?></h2><p class="wpgpt-muted"><?php echo esc_html__( 'Selecciona un archivo del explorador para editarlo aquí.', 'wpgpt-mcp-bridge' ); ?></p><?php endif; ?>
                </div></div>
            </div>
        </div>
        <?php
    }

    private static function action_button( string $action, string $path, string $label, bool $confirm = false ): void { ?>
        <form class="wpgpt-inline-form" method="post" action="<?php echo esc_url( admin_url( 'admin-post.php' ) ); ?>" <?php echo $confirm ? 'onsubmit="return confirm(\'' . esc_js( __( '¿Confirmas la operación?', 'wpgpt-mcp-bridge' ) ) . '\');"' : ''; ?>>
            <?php wp_nonce_field( 'wpgpt_mcp_sandbox_action' ); ?>
            <input type="hidden" name="action" value="wpgpt_mcp_sandbox_action" />
            <input type="hidden" name="sandbox_action" value="<?php echo esc_attr( $action ); ?>" />
            <input type="hidden" name="path" value="<?php echo esc_attr( $path ); ?>" />
            <button class="button button-small" type="submit"><?php echo esc_html( $label ); ?></button>
        </form>
    <?php }

    private static function create_form( string $action, string $label, string $placeholder ): void { ?>
        <form method="post" action="<?php echo esc_url( admin_url( 'admin-post.php' ) ); ?>">
            <?php wp_nonce_field( 'wpgpt_mcp_sandbox_action' ); ?>
            <input type="hidden" name="action" value="wpgpt_mcp_sandbox_action" />
            <input type="hidden" name="sandbox_action" value="<?php echo esc_attr( $action ); ?>" />
            <div class="wpgpt-new-grid"><input type="text" name="path" placeholder="<?php echo esc_attr( $placeholder ); ?>" /><button class="button button-secondary" type="submit"><?php echo esc_html( $label ); ?></button></div>
        </form>
    <?php }

    private static function normalize_relative( string $path ): string {
        $path = str_replace( "\0", '', str_replace( '\\', '/', $path ) );
        $path = preg_replace( '#/+#', '/', $path );
        $parts = array();
        foreach ( explode( '/', trim( is_string( $path ) ? $path : '', '/' ) ) as $part ) {
            if ( '' === $part || '.' === $part ) { continue; }
            if ( '..' === $part ) { array_pop( $parts ); continue; }
            $parts[] = $part;
        }
        return implode( '/', $parts );
    }

    private static function resolve( string $relative, bool $must_exist = true ): string|\WP_Error {
        self::ensure_root();
        $root = realpath( self::root() );
        if ( false === $root ) { return new \WP_Error( 'sandbox_root', __( 'No se pudo resolver la sandbox.', 'wpgpt-mcp-bridge' ) ); }
        $root = rtrim( str_replace( '\\', '/', $root ), '/' );
        $relative = self::normalize_relative( $relative );
        $candidate = '' === $relative ? $root : $root . '/' . $relative;
        if ( $must_exist ) {
            $real = realpath( $candidate );
            if ( false === $real ) { return new \WP_Error( 'not_found', __( 'La ruta no existe.', 'wpgpt-mcp-bridge' ) ); }
            $real = rtrim( str_replace( '\\', '/', $real ), '/' );
            if ( $real !== $root && ! str_starts_with( $real . '/', $root . '/' ) ) { return new \WP_Error( 'outside', __( 'La ruta debe estar dentro de la sandbox.', 'wpgpt-mcp-bridge' ) ); }
            return $real;
        }
        $parent = dirname( $candidate );
        while ( ! is_dir( $parent ) && dirname( $parent ) !== $parent ) { $parent = dirname( $parent ); }
        $real_parent = realpath( $parent );
        if ( false === $real_parent ) { return new \WP_Error( 'parent', __( 'No se pudo resolver la carpeta padre.', 'wpgpt-mcp-bridge' ) ); }
        $real_parent = rtrim( str_replace( '\\', '/', $real_parent ), '/' );
        if ( $real_parent !== $root && ! str_starts_with( $real_parent . '/', $root . '/' ) ) { return new \WP_Error( 'outside', __( 'La ruta debe estar dentro de la sandbox.', 'wpgpt-mcp-bridge' ) ); }
        return $candidate;
    }

    private static function relative_from_root( string $path ): string {
        $root = realpath( self::root() );
        $real = realpath( $path );
        if ( false === $root || false === $real ) { return ''; }
        $root = rtrim( str_replace( '\\', '/', $root ), '/' );
        $real = str_replace( '\\', '/', $real );
        return trim( preg_replace( '#^' . preg_quote( $root, '#' ) . '#', '', $real ), '/' );
    }

    private static function admin_url( string $relative = '' ): string {
        $args = array( 'page' => 'wpgpt-mcp-sandbox' );
        $relative = self::normalize_relative( $relative );
        if ( '' !== $relative && '.' !== $relative ) { $args['path'] = $relative; }
        return add_query_arg( $args, admin_url( 'admin.php' ) );
    }

    private static function items( string $dir ): array {
        if ( ! is_dir( $dir ) ) { return array(); }
        $entries = scandir( $dir );
        if ( ! is_array( $entries ) ) { return array(); }
        $items = array();
        foreach ( $entries as $entry ) {
            if ( '.' === $entry || '..' === $entry || '.loading' === $entry ) { continue; }
            $full = trailingslashit( $dir ) . $entry;
            $is_dir = is_dir( $full );
            $items[] = array( 'name' => $entry, 'relative' => self::relative_from_root( $full ), 'type' => $is_dir ? 'directory' : 'file', 'icon' => $is_dir ? '📁' : '📄', 'size' => $is_dir ? '—' : size_format( (int) filesize( $full ), 1 ), 'modified' => gmdate( 'Y-m-d H:i', (int) filemtime( $full ) ) );
        }
        usort( $items, static function ( array $a, array $b ): int { return $a['type'] === $b['type'] ? strcasecmp( $a['name'], $b['name'] ) : ( 'directory' === $a['type'] ? -1 : 1 ); } );
        return $items;
    }

    private static function notice_text( string $notice ): string {
        $map = array( 'created' => __( 'Elemento creado.', 'wpgpt-mcp-bridge' ), 'saved' => __( 'Archivo guardado.', 'wpgpt-mcp-bridge' ), 'deleted' => __( 'Elemento eliminado.', 'wpgpt-mcp-bridge' ), 'toggled' => __( 'Estado actualizado.', 'wpgpt-mcp-bridge' ), 'safe_mode_cleared' => __( 'Safe mode desactivado.', 'wpgpt-mcp-bridge' ), 'loader_enabled' => __( 'Carga persistente activada.', 'wpgpt-mcp-bridge' ), 'loader_disabled' => __( 'Carga persistente desactivada.', 'wpgpt-mcp-bridge' ) );
        return isset( $map[ $notice ] ) ? (string) $map[ $notice ] : __( 'Operación completada.', 'wpgpt-mcp-bridge' );
    }

    private static function redirect( string $notice = '', string $path = '' ): void {
        $args = array( 'page' => 'wpgpt-mcp-sandbox' );
        if ( '' !== $notice ) { $args['notice'] = $notice; }
        $path = self::normalize_relative( $path );
        if ( '' !== $path ) { $args['path'] = $path; }
        wp_safe_redirect( add_query_arg( $args, admin_url( 'admin.php' ) ) ); exit;
    }

    private static function fail( string $message ): void { set_transient( 'wpgpt_mcp_sandbox_error', $message, 2 * MINUTE_IN_SECONDS ); self::redirect(); }

    private static function create_file(): void {
        $relative = isset( $_POST['path'] ) ? self::normalize_relative( sanitize_text_field( wp_unslash( (string) $_POST['path'] ) ) ) : '';
        if ( '' === $relative ) { self::fail( __( 'Indica una ruta de archivo.', 'wpgpt-mcp-bridge' ) ); }
        $path = self::resolve( $relative, false ); if ( is_wp_error( $path ) ) { self::fail( $path->get_error_message() ); }
        if ( file_exists( $path ) ) { self::fail( __( 'Ya existe un elemento con esa ruta.', 'wpgpt-mcp-bridge' ) ); }
        if ( ! wp_mkdir_p( dirname( $path ) ) || false === file_put_contents( $path, '', LOCK_EX ) ) { self::fail( __( 'No se pudo crear el archivo.', 'wpgpt-mcp-bridge' ) ); }
        @chmod( $path, 0644 ); self::redirect( 'created', $relative );
    }

    private static function create_dir(): void {
        $relative = isset( $_POST['path'] ) ? self::normalize_relative( sanitize_text_field( wp_unslash( (string) $_POST['path'] ) ) ) : '';
        if ( '' === $relative ) { self::fail( __( 'Indica una ruta de carpeta.', 'wpgpt-mcp-bridge' ) ); }
        $path = self::resolve( $relative, false ); if ( is_wp_error( $path ) ) { self::fail( $path->get_error_message() ); }
        if ( ! wp_mkdir_p( $path ) ) { self::fail( __( 'No se pudo crear la carpeta.', 'wpgpt-mcp-bridge' ) ); }
        self::redirect( 'created', $relative );
    }

    private static function save_file(): void {
        $relative = isset( $_POST['path'] ) ? self::normalize_relative( sanitize_text_field( wp_unslash( (string) $_POST['path'] ) ) ) : '';
        $content = isset( $_POST['content'] ) ? wp_unslash( (string) $_POST['content'] ) : '';
        $path = self::resolve( $relative, true ); if ( is_wp_error( $path ) ) { self::fail( $path->get_error_message() ); }
        if ( ! is_file( $path ) || false === file_put_contents( $path, $content, LOCK_EX ) ) { self::fail( __( 'No se pudo guardar el archivo.', 'wpgpt-mcp-bridge' ) ); }
        self::redirect( 'saved', $relative );
    }

    private static function delete_path(): void {
        $relative = isset( $_POST['path'] ) ? self::normalize_relative( sanitize_text_field( wp_unslash( (string) $_POST['path'] ) ) ) : '';
        $path = self::resolve( $relative, true ); if ( is_wp_error( $path ) ) { self::fail( $path->get_error_message() ); }
        if ( rtrim( $path, '/' ) === rtrim( self::root(), '/' ) ) { self::fail( __( 'No se puede eliminar la raíz de la sandbox.', 'wpgpt-mcp-bridge' ) ); }
        $ok = is_dir( $path ) ? self::delete_dir_recursive( $path ) : @unlink( $path );
        if ( ! $ok ) { self::fail( __( 'No se pudo eliminar el elemento.', 'wpgpt-mcp-bridge' ) ); }
        self::redirect( 'deleted', dirname( $relative ) );
    }

    private static function delete_dir_recursive( string $dir ): bool {
        foreach ( array_diff( scandir( $dir ) ?: array(), array( '.', '..' ) ) as $entry ) {
            $full = trailingslashit( $dir ) . $entry;
            if ( is_dir( $full ) && ! is_link( $full ) ) { if ( ! self::delete_dir_recursive( $full ) ) { return false; } }
            elseif ( ! @unlink( $full ) ) { return false; }
        }
        return @rmdir( $dir );
    }

    private static function toggle_file(): void {
        $relative = isset( $_POST['path'] ) ? self::normalize_relative( sanitize_text_field( wp_unslash( (string) $_POST['path'] ) ) ) : '';
        $path = self::resolve( $relative, true ); if ( is_wp_error( $path ) ) { self::fail( $path->get_error_message() ); }
        if ( ! is_file( $path ) ) { self::fail( __( 'La ruta seleccionada no es un archivo.', 'wpgpt-mcp-bridge' ) ); }
        $target = str_ends_with( $path, '.disabled' ) ? substr( $path, 0, -9 ) : $path . '.disabled';
        if ( file_exists( $target ) || ! @rename( $path, $target ) ) { self::fail( __( 'No se pudo cambiar el estado del archivo.', 'wpgpt-mcp-bridge' ) ); }
        self::redirect( 'toggled', self::relative_from_root( $target ) );
    }
}
