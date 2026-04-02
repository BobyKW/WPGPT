<?php

namespace WPGPT\MCPBridge;

use WPGPT\MCPBridge\Database\Database_Catalog;
use WPGPT\MCPBridge\Support\Ability_Catalog;

if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

class Admin {
    private const TRANSIENT_PLAIN_TOKEN       = 'wpgpt_mcp_bridge_plain_token';
    private const TRANSIENT_APP_PASSWORD_DATA = 'wpgpt_mcp_bridge_app_password_data';

    public static function init(): void {
        add_action( 'admin_menu', array( __CLASS__, 'register_menu' ) );
        add_action( 'admin_post_wpgpt_mcp_bridge_action', array( __CLASS__, 'handle_post' ) );
    }

    public static function register_menu(): void {
        add_management_page(
            __( 'WPGPT MCP Bridge', 'wpgpt-mcp-bridge' ),
            __( 'WPGPT MCP Bridge', 'wpgpt-mcp-bridge' ),
            'manage_options',
            'wpgpt-mcp-bridge',
            array( __CLASS__, 'render_settings_page' )
        );
    }

    public static function handle_post(): void {
        if ( ! current_user_can( 'manage_options' ) ) {
            wp_die( esc_html__( 'No tienes permisos suficientes.', 'wpgpt-mcp-bridge' ) );
        }

        check_admin_referer( 'wpgpt_mcp_bridge_action' );

        $action = isset( $_POST['wpgpt_mcp_bridge_action'] ) ? sanitize_text_field( wp_unslash( (string) $_POST['wpgpt_mcp_bridge_action'] ) ) : '';

        if ( 'generate_token' === $action ) {
            $token = Security::generate_token();
            set_transient( self::TRANSIENT_PLAIN_TOKEN, $token, 10 * MINUTE_IN_SECONDS );
            wp_safe_redirect( add_query_arg( array( 'page' => 'wpgpt-mcp-bridge', 'generated' => '1' ), admin_url( 'tools.php' ) ) );
            exit;
        }

        if ( 'generate_app_password' === $action ) {
            $user_id = isset( $_POST['wpgpt_mcp_user_id'] ) ? absint( $_POST['wpgpt_mcp_user_id'] ) : Security::get_user_id();
            $result  = Security::generate_application_password( $user_id );

            if ( is_wp_error( $result ) ) {
                set_transient( 'wpgpt_mcp_bridge_admin_error', $result->get_error_message(), 2 * MINUTE_IN_SECONDS );
                wp_safe_redirect( add_query_arg( array( 'page' => 'wpgpt-mcp-bridge', 'app_password_error' => '1' ), admin_url( 'tools.php' ) ) );
                exit;
            }

            set_transient(
                self::TRANSIENT_APP_PASSWORD_DATA,
                array(
                    'user_id'       => $user_id,
                    'username'      => Security::get_best_username_for_application_password( $user_id ),
                    'password'      => (string) $result['password'],
                    'created_label' => isset( $result['item']['name'] ) ? (string) $result['item']['name'] : '',
                ),
                10 * MINUTE_IN_SECONDS
            );

            wp_safe_redirect( add_query_arg( array( 'page' => 'wpgpt-mcp-bridge', 'app_password_generated' => '1' ), admin_url( 'tools.php' ) ) );
            exit;
        }

        if ( 'save_settings' === $action ) {
            $user_id      = isset( $_POST['wpgpt_mcp_user_id'] ) ? absint( $_POST['wpgpt_mcp_user_id'] ) : 0;
            $read_only    = isset( $_POST['wpgpt_read_only'] ) && '1' === (string) $_POST['wpgpt_read_only'];
            $allow_delete = isset( $_POST['wpgpt_allow_delete'] ) && '1' === (string) $_POST['wpgpt_allow_delete'];
            $fs_read      = isset( $_POST['wpgpt_fs_read'] ) && '1' === (string) $_POST['wpgpt_fs_read'];
            $fs_write     = isset( $_POST['wpgpt_fs_write'] ) && '1' === (string) $_POST['wpgpt_fs_write'];
            $fs_delete    = isset( $_POST['wpgpt_fs_delete'] ) && '1' === (string) $_POST['wpgpt_fs_delete'];

            if ( $read_only ) {
                $allow_delete = false;
                $fs_write     = false;
                $fs_delete    = false;
            }

            $all_abilities = Ability_Catalog::declared_names();
            $allowed_abilities = isset( $_POST['wpgpt_allowed_abilities'] ) && is_array( $_POST['wpgpt_allowed_abilities'] )
                ? array_map( 'sanitize_text_field', wp_unslash( $_POST['wpgpt_allowed_abilities'] ) )
                : array();

            $catalog = new Database_Catalog();
            $all_tables = array_keys( $catalog->supported_tables() );
            $allowed_tables = isset( $_POST['wpgpt_allowed_tables'] ) && is_array( $_POST['wpgpt_allowed_tables'] )
                ? array_map( 'sanitize_key', wp_unslash( $_POST['wpgpt_allowed_tables'] ) )
                : array();

            if ( empty( $allowed_abilities ) ) {
                set_transient( 'wpgpt_mcp_bridge_admin_error', __( 'Debes dejar al menos una ability activa.', 'wpgpt-mcp-bridge' ), 2 * MINUTE_IN_SECONDS );
                wp_safe_redirect( add_query_arg( array( 'page' => 'wpgpt-mcp-bridge', 'settings_error' => '1' ), admin_url( 'tools.php' ) ) );
                exit;
            }

            if ( empty( $allowed_tables ) ) {
                set_transient( 'wpgpt_mcp_bridge_admin_error', __( 'Debes dejar al menos una tabla de base de datos permitida.', 'wpgpt-mcp-bridge' ), 2 * MINUTE_IN_SECONDS );
                wp_safe_redirect( add_query_arg( array( 'page' => 'wpgpt-mcp-bridge', 'settings_error' => '1' ), admin_url( 'tools.php' ) ) );
                exit;
            }

            Security::set_user_id( $user_id );
            Security::update_read_only( $read_only );
            Security::update_allow_delete( $allow_delete );
            Security::update_fs_read( $fs_read );
            Security::update_fs_write( $fs_write );
            Security::update_fs_delete( $fs_delete );
            Security::update_allowed_abilities( $allowed_abilities, $all_abilities );
            Security::update_allowed_tables( $allowed_tables, $all_tables );

            wp_safe_redirect( add_query_arg( array( 'page' => 'wpgpt-mcp-bridge', 'updated' => '1' ), admin_url( 'tools.php' ) ) );
            exit;
        }
    }

    public static function render_settings_page(): void {
        if ( ! current_user_can( 'manage_options' ) ) {
            return;
        }

        $base_endpoint       = rest_url( 'wpgpt-mcp/mcp' );
        $adapter_server_url  = rest_url( 'mcp/mcp-adapter-default-server' );
        $plain_token         = get_transient( self::TRANSIENT_PLAIN_TOKEN );
        $app_password_data   = get_transient( self::TRANSIENT_APP_PASSWORD_DATA );
        $admin_error         = get_transient( 'wpgpt_mcp_bridge_admin_error' );
        $has_token           = Security::has_token();
        $full_endpoint       = $has_token && is_string( $plain_token ) && '' !== $plain_token ? add_query_arg( 'wpgpt_token', rawurlencode( $plain_token ), $base_endpoint ) : $base_endpoint;
        $abilities_endpoint  = rest_url( 'wp-abilities/v1/abilities' );
        $rest_index_endpoint = rest_url();
        $selected_user_id    = Security::get_user_id();
        $selected_user       = $selected_user_id ? get_user_by( 'id', $selected_user_id ) : false;
        $selected_username   = $selected_user_id ? Security::get_best_username_for_application_password( $selected_user_id ) : '';
        $users               = get_users(
            array(
                'orderby' => 'display_name',
                'order'   => 'ASC',
                'fields'  => array( 'ID', 'display_name', 'user_login', 'user_email' ),
            )
        );

        $ability_groups   = Ability_Catalog::grouped_for_admin();
        $all_abilities    = Ability_Catalog::declared_names();
        $enabled_abilities = array_flip( Security::get_allowed_abilities( $all_abilities ) );

        $db_catalog       = new Database_Catalog();
        $supported_tables = $db_catalog->supported_tables();
        $enabled_tables   = array_flip( Security::get_allowed_tables( array_keys( $supported_tables ) ) );

        $server_name         = self::build_server_name();
        $vscode_json         = self::build_vscode_json( $adapter_server_url, $app_password_data, $selected_username, $server_name );
        $selected_user_label = $selected_user ? $selected_user->display_name . ' (' . $selected_user->user_login . ')' : __( 'No configurado', 'wpgpt-mcp-bridge' );
        $token_status_label  = $has_token ? __( 'Configurado', 'wpgpt-mcp-bridge' ) : __( 'No configurado', 'wpgpt-mcp-bridge' );
        $token_status_label .= $has_token && Security::token_last_four() ? ' • ****' . Security::token_last_four() : '';
        $app_password_ready  = is_array( $app_password_data ) && ! empty( $app_password_data['password'] );
        ?>
        <div class="wrap wpgpt-admin-wrap">
            <style>
                .wpgpt-admin-wrap { max-width: 1280px; }
                .wpgpt-admin-wrap .wpgpt-lead { max-width: 980px; color: #50575e; }
                .wpgpt-grid { display:grid; grid-template-columns: repeat(auto-fit, minmax(240px, 1fr)); gap:16px; margin-top:18px; }
                .wpgpt-grid-2 { display:grid; grid-template-columns: repeat(auto-fit, minmax(420px, 1fr)); gap:16px; margin-top:18px; }
                .wpgpt-grid-2-top { align-items:start; }
                .wpgpt-card { background:#fff; border:1px solid #dcdcde; border-radius:10px; box-shadow:0 1px 1px rgba(0,0,0,.03); overflow:hidden; }
                .wpgpt-card-header { display:flex; justify-content:space-between; align-items:flex-start; gap:12px; padding:18px 20px 0; }
                .wpgpt-card-body { padding:18px 20px 20px; }
                .wpgpt-card h2, .wpgpt-card h3, .wpgpt-card h4 { margin:0; }
                .wpgpt-card p { color:#50575e; }
                .wpgpt-stat-label { font-size:12px; text-transform:uppercase; letter-spacing:.04em; color:#646970; margin-bottom:10px; }
                .wpgpt-stat-value { font-size:16px; font-weight:600; color:#1d2327; line-height:1.45; word-break:break-word; }
                .wpgpt-muted { color:#646970; }
                .wpgpt-section-title { margin-top:28px; margin-bottom:6px; }
                .wpgpt-section-intro { margin-top:0; max-width:980px; color:#50575e; }
                .wpgpt-code-shell { border:1px solid #dcdcde; border-radius:8px; overflow:hidden; background:#fff; }
                .wpgpt-code-shell-top { background:#1e1e1e; color:#fff; padding:18px 20px; position:relative; }
                .wpgpt-code-shell-bottom { padding:14px 20px; background:#f6f7f7; border-top:1px solid #dcdcde; }
                .wpgpt-code-shell pre { margin:0; color:#fff; white-space:pre-wrap; word-break:break-word; font-size:13px; line-height:1.55; }
                .wpgpt-kv { margin:0; }
                .wpgpt-kv + .wpgpt-kv { margin-top:10px; }
                .wpgpt-kv strong { display:block; margin-bottom:4px; color:#1d2327; }
                .wpgpt-field-grid { display:grid; grid-template-columns: repeat(auto-fit, minmax(220px, 1fr)); gap:12px; }
                .wpgpt-picklist { border:1px solid #dcdcde; border-radius:8px; background:#f6f7f7; max-height:440px; overflow:auto; padding:14px; }
                .wpgpt-picklist-section + .wpgpt-picklist-section { margin-top:12px; }
                .wpgpt-checklist { display:grid; gap:10px; }
                .wpgpt-accordion { display:grid; gap:12px; }
                .wpgpt-accordion-section { border:1px solid #dcdcde; border-radius:10px; background:#fff; overflow:hidden; }
                .wpgpt-accordion-section summary { list-style:none; cursor:pointer; padding:14px 16px; display:flex; align-items:flex-start; justify-content:space-between; gap:14px; }
                .wpgpt-accordion-section summary::-webkit-details-marker { display:none; }
                .wpgpt-accordion-title { display:flex; flex-direction:column; gap:4px; }
                .wpgpt-accordion-title strong { color:#1d2327; font-size:13px; }
                .wpgpt-accordion-title span { color:#646970; font-size:12px; }
                .wpgpt-accordion-meta { display:flex; align-items:center; gap:10px; color:#646970; font-size:12px; white-space:nowrap; }
                .wpgpt-accordion-chevron { transition:transform .15s ease; }
                .wpgpt-accordion-section[open] .wpgpt-accordion-chevron { transform:rotate(180deg); }
                .wpgpt-accordion-body { padding:0 14px 14px; }
                .wpgpt-pill { display:inline-flex; align-items:center; border:1px solid #dcdcde; border-radius:999px; padding:2px 8px; background:#f6f7f7; color:#1d2327; }
                .wpgpt-check-item { background:#fff; border:1px solid #dcdcde; border-radius:8px; padding:10px 12px; }
                .wpgpt-check-item code { font-size:12px; }
                .wpgpt-check-item label { display:flex; gap:10px; align-items:flex-start; }
                .wpgpt-check-item input[type="checkbox"] { margin-top:2px; }
                .wpgpt-check-copy { display:block; color:#646970; margin-top:4px; }
                .wpgpt-actions-row { display:flex; gap:8px; flex-wrap:wrap; margin-bottom:12px; }
                @media (max-width: 782px) { .wpgpt-card-header { flex-direction:column; } }
            </style>

            <h1><?php echo esc_html__( 'WPGPT MCP Bridge', 'wpgpt-mcp-bridge' ); ?></h1>
            <p class="wpgpt-lead"><?php echo esc_html__( 'Panel de control del bridge MCP. Aquí configuras el usuario operativo, las credenciales de conexión y qué superficies del sitio expones realmente a través de MCP.', 'wpgpt-mcp-bridge' ); ?></p>

            <?php if ( isset( $_GET['generated'] ) ) : ?>
                <div class="notice notice-success"><p><?php echo esc_html__( 'Se ha generado un nuevo token MCP.', 'wpgpt-mcp-bridge' ); ?></p></div>
            <?php endif; ?>
            <?php if ( isset( $_GET['updated'] ) ) : ?>
                <div class="notice notice-success"><p><?php echo esc_html__( 'Ajustes guardados.', 'wpgpt-mcp-bridge' ); ?></p></div>
            <?php endif; ?>
            <?php if ( isset( $_GET['app_password_generated'] ) ) : ?>
                <div class="notice notice-success"><p><?php echo esc_html__( 'Application Password generada correctamente.', 'wpgpt-mcp-bridge' ); ?></p></div>
            <?php endif; ?>
            <?php if ( $admin_error ) : ?>
                <div class="notice notice-error"><p><?php echo esc_html( (string) $admin_error ); ?></p></div>
                <?php delete_transient( 'wpgpt_mcp_bridge_admin_error' ); ?>
            <?php endif; ?>

            <div class="wpgpt-grid">
                <div class="wpgpt-card"><div class="wpgpt-card-body"><div class="wpgpt-stat-label"><?php echo esc_html__( 'Usuario MCP', 'wpgpt-mcp-bridge' ); ?></div><div class="wpgpt-stat-value"><?php echo esc_html( $selected_user_label ); ?></div></div></div>
                <div class="wpgpt-card"><div class="wpgpt-card-body"><div class="wpgpt-stat-label"><?php echo esc_html__( 'Token ChatGPT', 'wpgpt-mcp-bridge' ); ?></div><div class="wpgpt-stat-value"><?php echo esc_html( $token_status_label ); ?></div></div></div>
                <div class="wpgpt-card"><div class="wpgpt-card-body"><div class="wpgpt-stat-label"><?php echo esc_html__( 'Abilities expuestas', 'wpgpt-mcp-bridge' ); ?></div><div class="wpgpt-stat-value"><?php echo esc_html( sprintf( '%d / %d', count( $enabled_abilities ), count( $all_abilities ) ) ); ?></div></div></div>
                <div class="wpgpt-card"><div class="wpgpt-card-body"><div class="wpgpt-stat-label"><?php echo esc_html__( 'Tablas permitidas', 'wpgpt-mcp-bridge' ); ?></div><div class="wpgpt-stat-value"><?php echo esc_html( sprintf( '%d / %d', count( $enabled_tables ), count( $supported_tables ) ) ); ?></div></div></div>
            </div>

            <h2 class="wpgpt-section-title"><?php echo esc_html__( '1. Configuración general y superficie expuesta', 'wpgpt-mcp-bridge' ); ?></h2>
            <p class="wpgpt-section-intro"><?php echo esc_html__( 'Aquí decides qué usuario operará vía MCP, qué permisos globales se aplican y qué abilities y tablas quedan realmente disponibles para cualquier cliente conectado.', 'wpgpt-mcp-bridge' ); ?></p>

            <form method="post" action="<?php echo esc_url( admin_url( 'admin-post.php' ) ); ?>">
                <?php wp_nonce_field( 'wpgpt_mcp_bridge_action' ); ?>
                <input type="hidden" name="action" value="wpgpt_mcp_bridge_action" />
                <input type="hidden" name="wpgpt_mcp_bridge_action" value="save_settings" />

                <div class="wpgpt-card" style="margin-top:18px;">
                    <div class="wpgpt-card-body">
                        <div style="max-width:560px; margin-bottom:18px;">
                            <label for="wpgpt_mcp_user_id"><strong><?php echo esc_html__( 'Usuario MCP', 'wpgpt-mcp-bridge' ); ?></strong></label><br>
                            <select id="wpgpt_mcp_user_id" name="wpgpt_mcp_user_id" style="min-width:280px; width:100%; max-width:520px;">
                                <option value="0"><?php echo esc_html__( '— Selecciona un usuario —', 'wpgpt-mcp-bridge' ); ?></option>
                                <?php foreach ( $users as $user ) : ?>
                                    <option value="<?php echo esc_attr( (string) $user->ID ); ?>" <?php selected( $selected_user_id, $user->ID ); ?>>
                                        <?php echo esc_html( $user->display_name . ' (' . $user->user_login . ' · ' . $user->user_email . ')' ); ?>
                                    </option>
                                <?php endforeach; ?>
                            </select>
                        </div>

                        <div class="wpgpt-field-grid">
                            <p><label><input type="checkbox" id="wpgpt_read_only" name="wpgpt_read_only" value="1" <?php checked( Security::get_read_only() ); ?> /> <?php echo esc_html__( 'Modo solo lectura', 'wpgpt-mcp-bridge' ); ?></label></p>
                            <p><label><input type="checkbox" id="wpgpt_allow_delete" name="wpgpt_allow_delete" value="1" <?php checked( Security::get_allow_delete() ); ?> /> <?php echo esc_html__( 'Permitir eliminaciones', 'wpgpt-mcp-bridge' ); ?></label></p>
                            <p><label><input type="checkbox" id="wpgpt_fs_read" name="wpgpt_fs_read" value="1" <?php checked( Security::get_fs_read() ); ?> /> <?php echo esc_html__( 'Filesystem read', 'wpgpt-mcp-bridge' ); ?></label></p>
                            <p><label><input type="checkbox" id="wpgpt_fs_write" name="wpgpt_fs_write" value="1" <?php checked( Security::get_fs_write() ); ?> /> <?php echo esc_html__( 'Filesystem write', 'wpgpt-mcp-bridge' ); ?></label></p>
                            <p><label><input type="checkbox" id="wpgpt_fs_delete" name="wpgpt_fs_delete" value="1" <?php checked( Security::get_fs_delete() ); ?> /> <?php echo esc_html__( 'Filesystem delete', 'wpgpt-mcp-bridge' ); ?></label></p>
                        </div>
                        <p class="description"><?php echo esc_html__( 'Si activas el modo solo lectura, las opciones de escritura y eliminación se desactivan automáticamente.', 'wpgpt-mcp-bridge' ); ?></p>
                    </div>
                </div>

                <div class="wpgpt-grid-2 wpgpt-grid-2-top">
                    <div class="wpgpt-card">
                        <div class="wpgpt-card-header"><div><h3><?php echo esc_html__( 'Abilities permitidas', 'wpgpt-mcp-bridge' ); ?></h3><p><?php echo esc_html__( 'Desmarca abilities para que dejen de registrarse y no aparezcan en la conexión MCP.', 'wpgpt-mcp-bridge' ); ?></p></div></div>
                        <div class="wpgpt-card-body">
                            <div class="wpgpt-actions-row">
                                <button type="button" class="button button-secondary" data-check-group="abilities" data-check-state="all"><?php echo esc_html__( 'Marcar todas', 'wpgpt-mcp-bridge' ); ?></button>
                                <button type="button" class="button button-secondary" data-check-group="abilities" data-check-state="none"><?php echo esc_html__( 'Desmarcar todas', 'wpgpt-mcp-bridge' ); ?></button>
                                <button type="button" class="button button-secondary" data-accordion-group="abilities" data-accordion-state="open"><?php echo esc_html__( 'Expandir categorías', 'wpgpt-mcp-bridge' ); ?></button>
                                <button type="button" class="button button-secondary" data-accordion-group="abilities" data-accordion-state="close"><?php echo esc_html__( 'Contraer categorías', 'wpgpt-mcp-bridge' ); ?></button>
                            </div>
                            <div class="wpgpt-picklist">
                                <div class="wpgpt-accordion">
                                    <?php $ability_group_index = 0; ?>
                                    <?php foreach ( $ability_groups as $group ) : ?>
                                        <details class="wpgpt-accordion-section" data-accordion-item="abilities" <?php echo 0 === $ability_group_index ? 'open' : ''; ?>>
                                            <summary>
                                                <div class="wpgpt-accordion-title">
                                                    <strong><?php echo esc_html( $group['label'] ); ?></strong>
                                                    <span><?php echo esc_html( $group['description'] ); ?></span>
                                                </div>
                                                <div class="wpgpt-accordion-meta">
                                                    <span class="wpgpt-pill"><?php echo esc_html( sprintf( _n( '%d ability', '%d abilities', (int) $group['total_count'], 'wpgpt-mcp-bridge' ), (int) $group['total_count'] ) ); ?></span>
                                                    <span class="wpgpt-pill"><?php echo esc_html( sprintf( __( '%1$d activas', 'wpgpt-mcp-bridge' ), (int) $group['enabled_count'] ) ); ?></span>
                                                    <span class="wpgpt-accordion-chevron">▼</span>
                                                </div>
                                            </summary>
                                            <div class="wpgpt-accordion-body">
                                                <div class="wpgpt-checklist">
                                                    <?php foreach ( $group['items'] as $item ) : ?>
                                                        <div class="wpgpt-check-item">
                                                            <label>
                                                                <input type="checkbox" name="wpgpt_allowed_abilities[]" value="<?php echo esc_attr( $item['name'] ); ?>" data-check-item="abilities" <?php checked( $item['enabled'] ); ?> />
                                                                <span>
                                                                    <code><?php echo esc_html( $item['name'] ); ?></code>
                                                                    <?php if ( ! empty( $item['description'] ) ) : ?>
                                                                        <small class="wpgpt-check-copy"><?php echo esc_html( $item['description'] ); ?></small>
                                                                    <?php endif; ?>
                                                                </span>
                                                            </label>
                                                        </div>
                                                    <?php endforeach; ?>
                                                </div>
                                            </div>
                                        </details>
                                        <?php $ability_group_index++; ?>
                                    <?php endforeach; ?>
                                </div>
                            </div>
                        </div>
                    </div>

                    <div class="wpgpt-card">
                        <div class="wpgpt-card-header"><div><h3><?php echo esc_html__( 'Tablas permitidas para database tools', 'wpgpt-mcp-bridge' ); ?></h3><p><?php echo esc_html__( 'Estas tablas son las únicas que podrán usar las abilities de base de datos seguras.', 'wpgpt-mcp-bridge' ); ?></p></div></div>
                        <div class="wpgpt-card-body">
                            <div class="wpgpt-actions-row">
                                <button type="button" class="button button-secondary" data-check-group="tables" data-check-state="all"><?php echo esc_html__( 'Marcar todas', 'wpgpt-mcp-bridge' ); ?></button>
                                <button type="button" class="button button-secondary" data-check-group="tables" data-check-state="none"><?php echo esc_html__( 'Desmarcar todas', 'wpgpt-mcp-bridge' ); ?></button>
                            </div>
                            <div class="wpgpt-picklist">
                                <div class="wpgpt-checklist">
                                    <?php foreach ( $supported_tables as $table_key => $full_table_name ) : ?>
                                        <div class="wpgpt-check-item">
                                            <label>
                                                <input type="checkbox" name="wpgpt_allowed_tables[]" value="<?php echo esc_attr( $table_key ); ?>" data-check-item="tables" <?php checked( isset( $enabled_tables[ $table_key ] ) ); ?> />
                                                <span><code><?php echo esc_html( $table_key ); ?></code><small class="wpgpt-check-copy"><?php echo esc_html( $full_table_name ); ?></small></span>
                                            </label>
                                        </div>
                                    <?php endforeach; ?>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>

                <p style="margin-top:16px;"><?php submit_button( __( 'Guardar ajustes', 'wpgpt-mcp-bridge' ), 'primary', 'submit', false ); ?></p>
            </form>

            <h2 class="wpgpt-section-title"><?php echo esc_html__( '2. Acceso para ChatGPT y clientes MCP', 'wpgpt-mcp-bridge' ); ?></h2>
            <div class="wpgpt-grid-2">
                <div class="wpgpt-card">
                    <div class="wpgpt-card-header">
                        <div><h3><?php echo esc_html__( 'Token Bearer para ChatGPT', 'wpgpt-mcp-bridge' ); ?></h3><p><?php echo esc_html__( 'Este modo usa el token MCP propio del plugin y autentica como el usuario operativo configurado arriba.', 'wpgpt-mcp-bridge' ); ?></p></div>
                        <form method="post" action="<?php echo esc_url( admin_url( 'admin-post.php' ) ); ?>" style="margin:0;">
                            <?php wp_nonce_field( 'wpgpt_mcp_bridge_action' ); ?>
                            <input type="hidden" name="action" value="wpgpt_mcp_bridge_action" />
                            <input type="hidden" name="wpgpt_mcp_bridge_action" value="generate_token" />
                            <?php submit_button( __( 'Generar token MCP', 'wpgpt-mcp-bridge' ), 'secondary', 'submit', false ); ?>
                        </form>
                    </div>
                    <div class="wpgpt-card-body">
                        <p class="wpgpt-kv"><strong><?php echo esc_html__( 'Endpoint MCP', 'wpgpt-mcp-bridge' ); ?></strong><code><?php echo esc_html( $base_endpoint ); ?></code></p>
                        <p class="wpgpt-kv"><strong><?php echo esc_html__( 'URL completa con token', 'wpgpt-mcp-bridge' ); ?></strong>
                            <?php if ( $has_token && is_string( $plain_token ) && '' !== $plain_token ) : ?>
                                <code><?php echo esc_html( $full_endpoint ); ?></code><br><span class="wpgpt-muted"><?php echo esc_html__( 'Guárdala ahora. El token en texto plano solo se muestra durante 10 minutos tras generarlo.', 'wpgpt-mcp-bridge' ); ?></span>
                            <?php else : ?>
                                <span class="wpgpt-muted"><?php echo esc_html__( 'Genera un token para ver la URL completa.', 'wpgpt-mcp-bridge' ); ?></span>
                            <?php endif; ?>
                        </p>
                    </div>
                </div>

                <div class="wpgpt-card">
                    <div class="wpgpt-card-header">
                        <div><h3><?php echo esc_html__( 'VS Code y otros editores', 'wpgpt-mcp-bridge' ); ?></h3><p><?php echo esc_html__( 'Este modo usa MCP Adapter con el cliente remoto de Automattic y una Application Password nativa de WordPress.', 'wpgpt-mcp-bridge' ); ?></p></div>
                        <form method="post" action="<?php echo esc_url( admin_url( 'admin-post.php' ) ); ?>" style="margin:0;">
                            <?php wp_nonce_field( 'wpgpt_mcp_bridge_action' ); ?>
                            <input type="hidden" name="action" value="wpgpt_mcp_bridge_action" />
                            <input type="hidden" name="wpgpt_mcp_bridge_action" value="generate_app_password" />
                            <input type="hidden" name="wpgpt_mcp_user_id" value="<?php echo esc_attr( (string) $selected_user_id ); ?>" />
                            <?php submit_button( __( 'Generar Application Password', 'wpgpt-mcp-bridge' ), 'secondary', 'submit', false, $selected_user_id ? array() : array( 'disabled' => 'disabled' ) ); ?>
                        </form>
                    </div>
                    <div class="wpgpt-card-body">
                        <div class="wpgpt-code-shell">
                            <div class="wpgpt-code-shell-top">
                                <button type="button" class="button button-small" data-copy-target="wpgpt-vscode-json" style="position:absolute; top:12px; right:12px;"><?php echo esc_html__( 'Copy', 'wpgpt-mcp-bridge' ); ?></button>
                                <pre id="wpgpt-vscode-json"><?php echo esc_html( $vscode_json ); ?></pre>
                            </div>
                            <div class="wpgpt-code-shell-bottom">
                                <p style="margin:0 0 10px;"><?php echo esc_html__( 'Add to mcp.json.', 'wpgpt-mcp-bridge' ); ?></p>
                                <p style="margin:0 0 8px;"><strong><?php echo esc_html__( 'Workspace:', 'wpgpt-mcp-bridge' ); ?></strong> <code>.vscode/mcp.json</code></p>
                                <p style="margin:0;"><strong><?php echo esc_html__( 'User:', 'wpgpt-mcp-bridge' ); ?></strong> <code><?php echo esc_html__( 'Run: MCP: Open User Configuration (command palette)', 'wpgpt-mcp-bridge' ); ?></code></p>
                            </div>
                        </div>
                        <?php if ( $app_password_ready ) : ?>
                            <div style="margin-top:16px;">
                                <p class="wpgpt-kv"><strong><?php echo esc_html__( 'Usuario para VS Code', 'wpgpt-mcp-bridge' ); ?></strong><code><?php echo esc_html( (string) $app_password_data['username'] ); ?></code></p>
                                <p class="wpgpt-kv"><strong><?php echo esc_html__( 'Application Password', 'wpgpt-mcp-bridge' ); ?></strong><code><?php echo esc_html( (string) $app_password_data['password'] ); ?></code><br><span class="wpgpt-muted"><?php echo esc_html__( 'Guárdala ahora. La contraseña en texto plano solo se muestra durante 10 minutos tras generarla.', 'wpgpt-mcp-bridge' ); ?></span></p>
                            </div>
                        <?php else : ?>
                            <p style="margin-top:16px;" class="wpgpt-muted"><?php echo $selected_user_id ? esc_html__( 'Genera una Application Password para ver el bloque completo con credenciales listas para pegar.', 'wpgpt-mcp-bridge' ) : esc_html__( 'Selecciona primero un usuario MCP y guarda los ajustes.', 'wpgpt-mcp-bridge' ); ?></p>
                        <?php endif; ?>
                    </div>
                </div>
            </div>

            <h2 class="wpgpt-section-title"><?php echo esc_html__( '3. Referencias rápidas', 'wpgpt-mcp-bridge' ); ?></h2>
            <div class="wpgpt-grid-2">
                <div class="wpgpt-card"><div class="wpgpt-card-body"><h3><?php echo esc_html__( 'Endpoints útiles', 'wpgpt-mcp-bridge' ); ?></h3><table class="widefat striped" style="margin-top:14px;"><tbody><tr><th style="width:230px;"><?php echo esc_html__( 'REST API', 'wpgpt-mcp-bridge' ); ?></th><td><a href="<?php echo esc_url( $rest_index_endpoint ); ?>" target="_blank" rel="noreferrer"><?php echo esc_html( $rest_index_endpoint ); ?></a></td></tr><tr><th><?php echo esc_html__( 'Abilities REST', 'wpgpt-mcp-bridge' ); ?></th><td><a href="<?php echo esc_url( $abilities_endpoint ); ?>" target="_blank" rel="noreferrer"><?php echo esc_html( $abilities_endpoint ); ?></a></td></tr><tr><th><?php echo esc_html__( 'Endpoint MCP bridge', 'wpgpt-mcp-bridge' ); ?></th><td><a href="<?php echo esc_url( $base_endpoint ); ?>" target="_blank" rel="noreferrer"><?php echo esc_html( $base_endpoint ); ?></a></td></tr><tr><th><?php echo esc_html__( 'Servidor MCP Adapter', 'wpgpt-mcp-bridge' ); ?></th><td><a href="<?php echo esc_url( $adapter_server_url ); ?>" target="_blank" rel="noreferrer"><?php echo esc_html( $adapter_server_url ); ?></a></td></tr></tbody></table></div></div>
                <div class="wpgpt-card"><div class="wpgpt-card-body"><h3><?php echo esc_html__( 'Resumen de exposición', 'wpgpt-mcp-bridge' ); ?></h3><p><?php echo esc_html( sprintf( __( 'Hay %1$d abilities activas de %2$d declaradas, y %3$d tablas permitidas de %4$d soportadas.', 'wpgpt-mcp-bridge' ), count( $enabled_abilities ), count( $all_abilities ), count( $enabled_tables ), count( $supported_tables ) ) ); ?></p><p class="wpgpt-muted"><?php echo esc_html__( 'Los cambios aplican a lo que el plugin registra y permite en runtime. Si desmarcas algo aquí, deja de exponerse a nuevos clientes MCP.', 'wpgpt-mcp-bridge' ); ?></p></div></div>
            </div>
        </div>
        <script>
        document.addEventListener('click', function(event) {
            var copyButton = event.target.closest('[data-copy-target]');
            if (copyButton) {
                var target = document.getElementById(copyButton.getAttribute('data-copy-target'));
                if (target) {
                    var text = target.innerText || target.textContent || '';
                    if (text && navigator.clipboard && navigator.clipboard.writeText) {
                        navigator.clipboard.writeText(text).then(function() {
                            var original = copyButton.textContent;
                            copyButton.textContent = 'Copied';
                            setTimeout(function() { copyButton.textContent = original; }, 1500);
                        });
                    }
                }
                return;
            }

            var accordionButton = event.target.closest('[data-accordion-group]');
            if (accordionButton) {
                var accordionGroup = accordionButton.getAttribute('data-accordion-group');
                var accordionState = accordionButton.getAttribute('data-accordion-state');
                document.querySelectorAll('[data-accordion-item="' + accordionGroup + '"]').forEach(function(section) {
                    section.open = accordionState === 'open';
                });
                return;
            }

            var batchButton = event.target.closest('[data-check-group]');
            if (!batchButton) {
                return;
            }

            var group = batchButton.getAttribute('data-check-group');
            var state = batchButton.getAttribute('data-check-state');
            document.querySelectorAll('[data-check-item="' + group + '"]').forEach(function(input) {
                input.checked = state === 'all';
            });
        });
        </script>
        <?php
    }

    private static function build_server_name(): string {
        $host = wp_parse_url( home_url(), PHP_URL_HOST );
        if ( ! is_string( $host ) || '' === $host ) {
            return 'wpgpt-mcp-server';
        }

        $host = strtolower( preg_replace( '/[^a-z0-9]+/i', '-', $host ) );
        return trim( $host, '-' );
    }

    private static function build_vscode_json( string $adapter_server_url, $app_password_data, string $selected_username, string $server_name ): string {
        $username = $selected_username;
        $password = 'YOUR-APP-PASSWORD';

        if ( is_array( $app_password_data ) ) {
            if ( ! empty( $app_password_data['username'] ) ) {
                $username = (string) $app_password_data['username'];
            }
            if ( ! empty( $app_password_data['password'] ) ) {
                $password = (string) $app_password_data['password'];
            }
        }

        $config = array(
            'servers' => array(
                $server_name => array(
                    'command' => 'npx',
                    'args'    => array(
                        '-y',
                        '@automattic/mcp-wordpress-remote@latest',
                    ),
                    'env'     => array(
                        'WP_API_URL'      => $adapter_server_url,
                        'WP_API_USERNAME' => $username,
                        'WP_API_PASSWORD' => $password,
                    ),
                ),
            ),
        );

        return (string) wp_json_encode( $config, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES );
    }
}
