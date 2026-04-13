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
            'wpgpt/options-query' => array(
                'label' => __( 'Options query', 'wpgpt-mcp-bridge' ),
                'description' => __( 'Lista, filtra y resume opciones y transients de WordPress de forma segura.', 'wpgpt-mcp-bridge' ),
                'input_schema' => $this->options_query_schema(),
                'execute_callback' => array( $this, 'options_query' ),
                'output_schema' => $this->object_schema(),
                'permission_callback' => array( $this, 'can_manage_site' ),
            ),
            'wpgpt/options-inspect' => array(
                'label' => __( 'Options inspect', 'wpgpt-mcp-bridge' ),
                'description' => __( 'Inspecciona una o varias opciones o auditorías de autoload/transients.', 'wpgpt-mcp-bridge' ),
                'input_schema' => $this->options_inspect_schema(),
                'execute_callback' => array( $this, 'options_inspect' ),
                'output_schema' => $this->object_schema(),
                'permission_callback' => array( $this, 'can_manage_site' ),
            ),
            'wpgpt/options-apply' => array(
                'label' => __( 'Options apply', 'wpgpt-mcp-bridge' ),
                'description' => __( 'Ejecuta cambios controlados sobre opciones o transients, con soporte dry_run.', 'wpgpt-mcp-bridge' ),
                'input_schema' => $this->options_apply_schema(),
                'execute_callback' => array( $this, 'options_apply' ),
                'output_schema' => $this->object_schema(),
                'permission_callback' => array( $this, 'can_manage_site' ),
            ),
            'wpgpt/themes-query' => array(
                'label' => __( 'Themes query', 'wpgpt-mcp-bridge' ),
                'description' => __( 'Lista, filtra y resume temas instalados con prioridad a información local del sitio.', 'wpgpt-mcp-bridge' ),
                'input_schema' => $this->themes_query_schema(),
                'execute_callback' => array( $this, 'themes_query' ),
                'output_schema' => $this->object_schema(),
            ),
            'wpgpt/themes-inspect' => array(
                'label' => __( 'Themes inspect', 'wpgpt-mcp-bridge' ),
                'description' => __( 'Inspecciona uno o varios temas instalados por stylesheet.', 'wpgpt-mcp-bridge' ),
                'input_schema' => $this->themes_inspect_schema(),
                'execute_callback' => array( $this, 'themes_inspect' ),
                'output_schema' => $this->object_schema(),
            ),
            'wpgpt/themes-apply' => array(
                'label' => __( 'Themes apply', 'wpgpt-mcp-bridge' ),
                'description' => __( 'Ejecuta acciones controladas sobre temas instalados, con soporte dry_run.', 'wpgpt-mcp-bridge' ),
                'input_schema' => $this->themes_apply_schema(),
                'execute_callback' => array( $this, 'themes_apply' ),
                'output_schema' => $this->object_schema(),
                'permission_callback' => array( $this, 'can_manage_site' ),
            ),
            'wpgpt/settings-query' => array(
                'label' => __( 'Settings query', 'wpgpt-mcp-bridge' ),
                'description' => __( 'Resume ajustes generales, lectura, escritura, discusión, permalinks y privacidad.', 'wpgpt-mcp-bridge' ),
                'input_schema' => $this->settings_query_schema(),
                'execute_callback' => array( $this, 'settings_query' ),
                'output_schema' => $this->object_schema(),
                'permission_callback' => array( $this, 'can_manage_site' ),
            ),
            'wpgpt/settings-inspect' => array(
                'label' => __( 'Settings inspect', 'wpgpt-mcp-bridge' ),
                'description' => __( 'Inspecciona un grupo concreto de ajustes del sitio.', 'wpgpt-mcp-bridge' ),
                'input_schema' => $this->settings_inspect_schema(),
                'execute_callback' => array( $this, 'settings_inspect' ),
                'output_schema' => $this->object_schema(),
                'permission_callback' => array( $this, 'can_manage_site' ),
            ),
            'wpgpt/settings-apply' => array(
                'label' => __( 'Settings apply', 'wpgpt-mcp-bridge' ),
                'description' => __( 'Ejecuta cambios controlados sobre ajustes del sitio, con soporte dry_run.', 'wpgpt-mcp-bridge' ),
                'input_schema' => $this->settings_apply_schema(),
                'execute_callback' => array( $this, 'settings_apply' ),
                'output_schema' => $this->object_schema(),
                'permission_callback' => array( $this, 'can_manage_site' ),
            ),
        );
    }

    public function options_query( array $input = array() ): array|WP_Error { return $this->service()->query_options( $input ); }
    public function options_inspect( array $input = array() ): array|WP_Error { return $this->service()->inspect_options( $input ); }
    public function options_apply( array $input = array() ): array|WP_Error { return $this->service()->apply_options( $input ); }
    public function themes_query( array $input = array() ): array|WP_Error { return $this->service()->query_themes( $input ); }
    public function themes_inspect( array $input = array() ): array|WP_Error { return $this->service()->inspect_themes( $input ); }
    public function themes_apply( array $input = array() ): array|WP_Error { return $this->service()->apply_themes( $input ); }
    public function settings_query( array $input = array() ): array|WP_Error { return $this->service()->query_settings( $input ); }
    public function settings_inspect( array $input = array() ): array|WP_Error { return $this->service()->inspect_settings( $input ); }
    public function settings_apply( array $input = array() ): array|WP_Error { return $this->service()->apply_settings( $input ); }

    private function service(): Settings_Service { return $this->service ??= new Settings_Service(); }

    private function options_query_schema(): array { return array( 'type'=>'object', 'additionalProperties'=>false, 'properties'=>array( 'scope'=>array('type'=>'string','enum'=>array('options','autoload_audit','transients')), 'search'=>array('type'=>'string'), 'filters'=>array('type'=>'object','additionalProperties'=>false,'properties'=>array('autoload'=>array('type'=>'string'),'name'=>array('type'=>'string'),'keys'=>array('type'=>'array','items'=>array('type'=>'string')))), 'limit'=>array('type'=>'integer','minimum'=>1,'maximum'=>200), 'offset'=>array('type'=>'integer','minimum'=>0) ) ); }
    private function options_inspect_schema(): array { return array( 'type'=>'object', 'additionalProperties'=>false, 'properties'=>array( 'scope'=>array('type'=>'string','enum'=>array('option','autoload_audit','transient')), 'option_name'=>array('type'=>'string'), 'option_names'=>array('type'=>'array','items'=>array('type'=>'string')), 'key'=>array('type'=>'string'), 'keys'=>array('type'=>'array','items'=>array('type'=>'string')), 'search'=>array('type'=>'string') ) ); }
    private function options_apply_schema(): array { return array( 'type'=>'object', 'additionalProperties'=>false, 'properties'=>array( 'action'=>array('type'=>'string','enum'=>array('update_option','delete_transient','delete_expired_transients')), 'dry_run'=>array('type'=>'boolean'), 'targets'=>array('type'=>'array','items'=>array('type'=>'object','additionalProperties'=>false,'properties'=>array('option_name'=>array('type'=>'string'),'key'=>array('type'=>'string')))), 'payload'=>array('type'=>'object','additionalProperties'=>true,'properties'=>array('option_name'=>array('type'=>'string'),'option_value'=>array(),'keys'=>array('type'=>'array','items'=>array('type'=>'string')))) ), 'required'=>array('action') ); }
    private function themes_query_schema(): array { return array( 'type'=>'object', 'additionalProperties'=>false, 'properties'=>array( 'search'=>array('type'=>'string'), 'filters'=>array('type'=>'object','additionalProperties'=>false,'properties'=>array('stylesheet'=>array('type'=>'string'),'template'=>array('type'=>'string'),'active'=>array('type'=>'boolean'),'update_available'=>array('type'=>'boolean'),'block_theme'=>array('type'=>'boolean'),'is_child_theme'=>array('type'=>'boolean'))), 'limit'=>array('type'=>'integer','minimum'=>1,'maximum'=>200), 'offset'=>array('type'=>'integer','minimum'=>0) ) ); }
    private function themes_inspect_schema(): array { return array( 'type'=>'object', 'additionalProperties'=>false, 'properties'=>array( 'stylesheet'=>array('type'=>'string'), 'stylesheets'=>array('type'=>'array','items'=>array('type'=>'string')) ) ); }
    private function themes_apply_schema(): array { return array( 'type'=>'object', 'additionalProperties'=>false, 'properties'=>array( 'action'=>array('type'=>'string','enum'=>array('activate','update','delete')), 'dry_run'=>array('type'=>'boolean'), 'targets'=>array('type'=>'array','items'=>array('object','additionalProperties'=>false,'properties'=>array('stylesheet'=>array('type'=>'string')))), 'filters'=>array('type'=>'object','additionalProperties'=>false,'properties'=>array('stylesheet'=>array('type'=>'string'),'active'=>array('type'=>'boolean'),'update_available'=>array('type'=>'boolean'))) ), 'required'=>array('action') ); }
    private function settings_query_schema(): array { return array( 'type'=>'object', 'additionalProperties'=>false, 'properties'=>array( 'scope'=>array('type'=>'string','enum'=>array('all','general','discussion','writing','reading','permalinks','privacy')) ) ); }
    private function settings_inspect_schema(): array { return $this->settings_query_schema(); }
    private function settings_apply_schema(): array { return array( 'type'=>'object', 'additionalProperties'=>false, 'properties'=>array( 'action'=>array('type'=>'string','enum'=>array('update_general','set_privacy_page','update_discussion','update_writing','update_reading','update_permalinks','set_homepage')), 'dry_run'=>array('type'=>'boolean'), 'payload'=>array('type'=>'object','additionalProperties'=>true) ), 'required'=>array('action') ); }
}
