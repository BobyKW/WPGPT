<?php

namespace WPGPT\MCPBridge;

use WPGPT\MCPBridge\Settings\Settings_Service;
use WP_Error;

if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

class Settings_Provider extends Base_Ability_Provider {
    private ?Settings_Service $service = null;

    public function get_abilities(): array {
        return array(
            'wpgpt/options-search-whitelisted' => array(
                'label' => __( 'Options search whitelisted', 'wpgpt-mcp-bridge' ),
                'description' => __( 'Busca opciones permitidas por whitelist.', 'wpgpt-mcp-bridge' ),
                'input_schema' => $this->options_search_schema(),
                'execute_callback' => array( $this, 'options_search_whitelisted' ),
                'output_schema' => $this->object_schema(),
            ),
            'wpgpt/options-update-whitelisted' => array(
                'label' => __( 'Options update whitelisted', 'wpgpt-mcp-bridge' ),
                'description' => __( 'Actualiza una opción permitida por whitelist.', 'wpgpt-mcp-bridge' ),
                'input_schema' => $this->options_update_schema(),
                'execute_callback' => array( $this, 'options_update_whitelisted' ),
                'output_schema' => $this->object_schema(),
                'permission_callback' => array( $this, 'can_manage_site' ),
            ),
            'wpgpt/theme-info' => array(
                'label' => __( 'Theme info', 'wpgpt-mcp-bridge' ),
                'description' => __( 'Obtiene información detallada de un tema.', 'wpgpt-mcp-bridge' ),
                'input_schema' => $this->theme_activate_schema(),
                'execute_callback' => array( $this, 'theme_info' ),
                'output_schema' => $this->object_schema(),
            ),
            'wpgpt/theme-list' => array(
                'label' => __( 'Theme list', 'wpgpt-mcp-bridge' ),
                'description' => __( 'Lista temas instalados.', 'wpgpt-mcp-bridge' ),
                'execute_callback' => array( $this, 'theme_list' ),
                'output_schema' => $this->object_schema(),
            ),
            'wpgpt/theme-delete' => array(
                'label' => __( 'Theme delete', 'wpgpt-mcp-bridge' ),
                'description' => __( 'Elimina un tema instalado.', 'wpgpt-mcp-bridge' ),
                'input_schema' => $this->theme_activate_schema(),
                'execute_callback' => array( $this, 'theme_delete' ),
                'output_schema' => $this->object_schema(),
                'permission_callback' => array( $this, 'can_delete_structure' ),
            ),
            'wpgpt/theme-activate' => array(
                'label' => __( 'Theme activate', 'wpgpt-mcp-bridge' ),
                'description' => __( 'Activa un tema instalado por stylesheet.', 'wpgpt-mcp-bridge' ),
                'input_schema' => $this->theme_activate_schema(),
                'execute_callback' => array( $this, 'theme_activate' ),
                'output_schema' => $this->object_schema(),
                'permission_callback' => array( $this, 'can_manage_site' ),
            ),
            'wpgpt/general-settings-get' => array(
                'label' => __( 'General settings get', 'wpgpt-mcp-bridge' ),
                'description' => __( 'Lee los ajustes generales principales del sitio.', 'wpgpt-mcp-bridge' ),
                'execute_callback' => array( $this, 'general_settings_get' ),
                'output_schema' => $this->object_schema(),
            ),
            'wpgpt/general-settings-update' => array(
                'label' => __( 'General settings update', 'wpgpt-mcp-bridge' ),
                'description' => __( 'Actualiza ajustes generales del sitio.', 'wpgpt-mcp-bridge' ),
                'input_schema' => array( 'type' => 'object', 'properties' => array( 'blogname' => array( 'type' => 'string' ), 'blogdescription' => array( 'type' => 'string' ), 'admin_email' => array( 'type' => 'string' ), 'timezone_string' => array( 'type' => 'string' ), 'date_format' => array( 'type' => 'string' ), 'time_format' => array( 'type' => 'string' ), 'start_of_week' => array( 'type' => 'integer' ), 'site_icon' => array( 'type' => 'integer' ) ) ),
                'execute_callback' => array( $this, 'general_settings_update' ),
                'output_schema' => $this->object_schema(),
                'permission_callback' => array( $this, 'can_manage_site' ),
            ),
            'wpgpt/privacy-page-set' => array(
                'label' => __( 'Privacy page set', 'wpgpt-mcp-bridge' ),
                'description' => __( 'Define la página de política de privacidad.', 'wpgpt-mcp-bridge' ),
                'input_schema' => array( 'type' => 'object', 'properties' => array( 'page_id' => array( 'type' => 'integer' ) ), 'required' => array( 'page_id' ) ),
                'execute_callback' => array( $this, 'privacy_page_set' ),
                'output_schema' => $this->object_schema(),
                'permission_callback' => array( $this, 'can_manage_site' ),
            ),
            'wpgpt/privacy-page-get' => array(
                'label' => __( 'Privacy page get', 'wpgpt-mcp-bridge' ),
                'description' => __( 'Lee la página de política de privacidad.', 'wpgpt-mcp-bridge' ),
                'execute_callback' => array( $this, 'privacy_page_get' ),
                'output_schema' => $this->object_schema(),
            ),
            'wpgpt/discussion-settings-get' => array(
                'label' => __( 'Discussion settings get', 'wpgpt-mcp-bridge' ),
                'description' => __( 'Lee los ajustes de comentarios y discusión.', 'wpgpt-mcp-bridge' ),
                'execute_callback' => array( $this, 'discussion_settings_get' ),
                'output_schema' => $this->object_schema(),
            ),
            'wpgpt/discussion-settings-update' => array(
                'label' => __( 'Discussion settings update', 'wpgpt-mcp-bridge' ),
                'description' => __( 'Actualiza ajustes de comentarios y discusión.', 'wpgpt-mcp-bridge' ),
                'input_schema' => $this->discussion_update_schema(),
                'execute_callback' => array( $this, 'discussion_settings_update' ),
                'output_schema' => $this->object_schema(),
                'permission_callback' => array( $this, 'can_manage_site' ),
            ),
            'wpgpt/writing-settings-get' => array(
                'label' => __( 'Writing settings get', 'wpgpt-mcp-bridge' ),
                'description' => __( 'Lee los ajustes de escritura.', 'wpgpt-mcp-bridge' ),
                'execute_callback' => array( $this, 'writing_settings_get' ),
                'output_schema' => $this->object_schema(),
            ),
            'wpgpt/writing-settings-update' => array(
                'label' => __( 'Writing settings update', 'wpgpt-mcp-bridge' ),
                'description' => __( 'Actualiza ajustes de escritura.', 'wpgpt-mcp-bridge' ),
                'input_schema' => $this->writing_update_schema(),
                'execute_callback' => array( $this, 'writing_settings_update' ),
                'output_schema' => $this->object_schema(),
                'permission_callback' => array( $this, 'can_manage_site' ),
            ),
            'wpgpt/reading-settings-get' => array(
                'label' => __( 'Reading settings get', 'wpgpt-mcp-bridge' ),
                'description' => __( 'Lee los ajustes principales de lectura.', 'wpgpt-mcp-bridge' ),
                'execute_callback' => array( $this, 'reading_settings_get' ),
                'output_schema' => $this->object_schema(),
            ),
            'wpgpt/reading-settings-update' => array(
                'label' => __( 'Reading settings update', 'wpgpt-mcp-bridge' ),
                'description' => __( 'Actualiza ajustes principales de lectura.', 'wpgpt-mcp-bridge' ),
                'input_schema' => $this->reading_update_schema(),
                'execute_callback' => array( $this, 'reading_settings_update' ),
                'output_schema' => $this->object_schema(),
                'permission_callback' => array( $this, 'can_manage_site' ),
            ),
            'wpgpt/permalink-settings-get' => array(
                'label' => __( 'Permalink settings get', 'wpgpt-mcp-bridge' ),
                'description' => __( 'Lee la estructura de enlaces permanentes.', 'wpgpt-mcp-bridge' ),
                'execute_callback' => array( $this, 'permalink_settings_get' ),
                'output_schema' => $this->object_schema(),
            ),
            'wpgpt/permalink-settings-update' => array(
                'label' => __( 'Permalink settings update', 'wpgpt-mcp-bridge' ),
                'description' => __( 'Actualiza la estructura de enlaces permanentes.', 'wpgpt-mcp-bridge' ),
                'input_schema' => $this->permalink_update_schema(),
                'execute_callback' => array( $this, 'permalink_settings_update' ),
                'output_schema' => $this->object_schema(),
                'permission_callback' => array( $this, 'can_manage_site' ),
            ),
            'wpgpt/homepage-set' => array(
                'label' => __( 'Homepage set', 'wpgpt-mcp-bridge' ),
                'description' => __( 'Define la portada del sitio y, opcionalmente, la página de entradas.', 'wpgpt-mcp-bridge' ),
                'input_schema' => $this->homepage_set_schema(),
                'execute_callback' => array( $this, 'homepage_set' ),
                'output_schema' => $this->object_schema(),
                'permission_callback' => array( $this, 'can_manage_site' ),
            ),
        );
    }

    public function options_search_whitelisted( array $input ): array { return $this->service()->search_whitelisted_options( $input ); }
    public function options_update_whitelisted( array $input ): array|WP_Error { return $this->service()->update_whitelisted_option( $input ); }
    public function theme_info( array $input ): array|WP_Error { return $this->service()->get_theme_info( $input ); }
    public function theme_list(): array { return $this->service()->list_themes(); }
    public function theme_delete( array $input ): array|WP_Error { return $this->service()->delete_theme( $input ); }
    public function theme_activate( array $input ): array|WP_Error { return $this->service()->activate_theme( $input ); }
    public function general_settings_get(): array { return $this->service()->get_general_settings(); }
    public function general_settings_update( array $input ): array { return $this->service()->update_general_settings( $input ); }
    public function privacy_page_set( array $input ): array|WP_Error { return $this->service()->set_privacy_page( $input ); }
    public function privacy_page_get(): array { return $this->service()->get_privacy_page(); }
    public function discussion_settings_get(): array { return $this->service()->get_discussion_settings(); }
    public function discussion_settings_update( array $input ): array { return $this->service()->update_discussion_settings( $input ); }
    public function writing_settings_get(): array { return $this->service()->get_writing_settings(); }
    public function writing_settings_update( array $input ): array { return $this->service()->update_writing_settings( $input ); }
    public function reading_settings_get(): array { return $this->service()->get_reading_settings(); }
    public function reading_settings_update( array $input ): array { return $this->service()->update_reading_settings( $input ); }
    public function permalink_settings_get(): array { return $this->service()->get_permalink_settings(); }
    public function permalink_settings_update( array $input ): array|WP_Error { return $this->service()->update_permalink_settings( $input ); }
    public function homepage_set( array $input ): array|WP_Error { return $this->service()->set_homepage( $input ); }

    private function service(): Settings_Service {
        if ( null === $this->service ) {
            $this->service = new Settings_Service();
        }
        return $this->service;
    }

    private function options_search_schema(): array { return array( 'type' => 'object', 'properties' => array( 'search' => array( 'type' => 'string' ), 'limit' => array( 'type' => 'integer' ) ) ); }
    private function options_update_schema(): array { return array( 'type' => 'object', 'properties' => array( 'option_name' => array( 'type' => 'string' ), 'option_value' => true ), 'required' => array( 'option_name' ) ); }
    private function theme_activate_schema(): array { return array( 'type' => 'object', 'properties' => array( 'stylesheet' => array( 'type' => 'string' ) ), 'required' => array( 'stylesheet' ) ); }
    private function discussion_update_schema(): array { return array( 'type' => 'object', 'properties' => array( 'default_ping_status' => array( 'type' => 'string' ), 'default_comment_status' => array( 'type' => 'string' ), 'comment_registration' => array( 'type' => 'boolean' ), 'close_comments_for_old_posts' => array( 'type' => 'boolean' ), 'close_comments_days_old' => array( 'type' => 'integer' ), 'thread_comments' => array( 'type' => 'boolean' ), 'thread_comments_depth' => array( 'type' => 'integer' ) ) ); }
    private function writing_update_schema(): array { return array( 'type' => 'object', 'properties' => array( 'default_category' => array( 'type' => 'integer' ), 'default_post_format' => array( 'type' => 'string' ), 'use_smilies' => array( 'type' => 'boolean' ), 'default_link_category' => array( 'type' => 'integer' ) ) ); }
    private function reading_update_schema(): array { return array( 'type' => 'object', 'properties' => array( 'show_on_front' => array( 'type' => 'string' ), 'page_on_front' => array( 'type' => 'integer' ), 'page_for_posts' => array( 'type' => 'integer' ), 'posts_per_page' => array( 'type' => 'integer' ) ) ); }
    private function permalink_update_schema(): array { return array( 'type' => 'object', 'properties' => array( 'permalink_structure' => array( 'type' => 'string' ) ), 'required' => array( 'permalink_structure' ) ); }
    private function homepage_set_schema(): array { return array( 'type' => 'object', 'properties' => array( 'page_on_front' => array( 'type' => 'integer' ), 'page_for_posts' => array( 'type' => 'integer' ) ), 'required' => array( 'page_on_front' ) ); }
}
