<?php

namespace WPGPT\MCPBridge;

use WPGPT\MCPBridge\Support\Provider_Registry;

if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

require_once __DIR__ . '/interface-ability-provider.php';
require_once __DIR__ . '/class-base-ability-provider.php';
require_once __DIR__ . '/class-security.php';

require_once __DIR__ . '/support/class-provider-registry.php';
require_once __DIR__ . '/support/class-ability-catalog.php';

require_once __DIR__ . '/database/class-database-catalog.php';
require_once __DIR__ . '/database/class-safe-query-builder.php';
require_once __DIR__ . '/database/class-database-inspector-service.php';
require_once __DIR__ . '/database/class-database-audit-service.php';

require_once __DIR__ . '/content/class-content-write-service.php';
require_once __DIR__ . '/publishing/class-publishing-service.php';

require_once __DIR__ . '/structure/class-post-type-manager.php';
require_once __DIR__ . '/structure/class-taxonomy-manager.php';
require_once __DIR__ . '/structure/class-metabox-manager.php';

require_once __DIR__ . '/plugins/class-plugin-manager-service.php';

require_once __DIR__ . '/diagnostics/interface-diagnostic-operation.php';
require_once __DIR__ . '/diagnostics/class-diagnostic-registry.php';
require_once __DIR__ . '/diagnostics/operations/class-list-post-types-operation.php';
require_once __DIR__ . '/diagnostics/operations/class-list-taxonomies-operation.php';
require_once __DIR__ . '/diagnostics/operations/class-active-hooks-sample-operation.php';
require_once __DIR__ . '/diagnostics/operations/class-plugin-status-operation.php';
require_once __DIR__ . '/diagnostics/operations/class-list-rest-routes-operation.php';
require_once __DIR__ . '/diagnostics/operations/class-list-shortcodes-operation.php';
require_once __DIR__ . '/diagnostics/operations/class-list-hooks-operation.php';
require_once __DIR__ . '/diagnostics/operations/class-list-cron-events-operation.php';

require_once __DIR__ . '/repository/class-plugin-repository-service.php';
require_once __DIR__ . '/seo/class-seo-service.php';
require_once __DIR__ . '/integrations/class-jetengine-service.php';
require_once __DIR__ . '/filesystem/class-filesystem-service.php';
require_once __DIR__ . '/media/class-media-service.php';
require_once __DIR__ . '/media/class-media-audit-service.php';
require_once __DIR__ . '/users/class-user-manager-service.php';
require_once __DIR__ . '/settings/class-settings-service.php';
require_once __DIR__ . '/settings/class-options-audit-service.php';
require_once __DIR__ . '/navigation/class-navigation-service.php';
require_once __DIR__ . '/inspection/class-code-inspection-service.php';

require_once __DIR__ . '/comments/class-comments-service.php';
require_once __DIR__ . '/acf/class-acf-service.php';
require_once __DIR__ . '/woocommerce/class-woocommerce-service.php';
require_once __DIR__ . '/appearance/class-appearance-service.php';
require_once __DIR__ . '/block_editor/class-block-editor-service.php';
require_once __DIR__ . '/maintenance/class-maintenance-service.php';
require_once __DIR__ . '/transfer/class-transfer-service.php';
require_once __DIR__ . '/environment/class-environment-service.php';

require_once __DIR__ . '/abilities/class-system-provider.php';
require_once __DIR__ . '/abilities/class-content-provider.php';
require_once __DIR__ . '/abilities/class-content-write-provider.php';
require_once __DIR__ . '/abilities/class-database-provider.php';
require_once __DIR__ . '/abilities/class-utility-provider.php';
require_once __DIR__ . '/abilities/class-diagnostics-provider.php';
require_once __DIR__ . '/abilities/class-runtime-provider.php';
require_once __DIR__ . '/abilities/class-repository-provider.php';
require_once __DIR__ . '/abilities/class-structure-provider.php';
require_once __DIR__ . '/abilities/class-plugin-management-provider.php';
require_once __DIR__ . '/abilities/class-seo-provider.php';
require_once __DIR__ . '/abilities/class-publishing-provider.php';
require_once __DIR__ . '/abilities/class-jetengine-provider.php';
require_once __DIR__ . '/abilities/class-filesystem-provider.php';
require_once __DIR__ . '/abilities/class-media-provider.php';
require_once __DIR__ . '/abilities/class-user-provider.php';
require_once __DIR__ . '/abilities/class-settings-provider.php';
require_once __DIR__ . '/abilities/class-navigation-provider.php';
require_once __DIR__ . '/abilities/class-inspection-provider.php';

require_once __DIR__ . '/abilities/class-comments-provider.php';
require_once __DIR__ . '/abilities/class-acf-provider.php';
require_once __DIR__ . '/abilities/class-woocommerce-provider.php';
require_once __DIR__ . '/abilities/class-appearance-provider.php';
require_once __DIR__ . '/abilities/class-block-editor-provider.php';
require_once __DIR__ . '/abilities/class-maintenance-provider.php';
require_once __DIR__ . '/abilities/class-transfer-provider.php';
require_once __DIR__ . '/abilities/class-environment-provider.php';
require_once __DIR__ . '/abilities/class-danger-provider.php';

class Abilities {
    private static array $providers = array();

    public static function init(): void {
        self::load_providers();

        add_action( 'wp_abilities_api_categories_init', array( __CLASS__, 'register_categories' ) );
        add_action( 'wp_abilities_api_init', array( __CLASS__, 'register_abilities' ) );
    }

    private static function load_providers(): void {
        self::$providers = array();

        foreach ( Provider_Registry::definitions() as $key => $class_name ) {
            if ( class_exists( $class_name ) ) {
                self::$providers[ $key ] = new $class_name();
            }
        }
    }

    public static function get_providers(): array {
        if ( empty( self::$providers ) ) {
            self::load_providers();
        }

        return self::$providers;
    }

    public static function register_categories(): void {
        if ( ! function_exists( 'wp_register_ability_category' ) ) {
            return;
        }

        wp_register_ability_category(
            'wpgpt-readonly',
            array(
                'label'       => __( 'WPGPT Read Only', 'wpgpt-mcp-bridge' ),
                'description' => __( 'Herramientas de lectura y gestión controlada para contenido, estructura, plugins, repositorio, diagnósticos y base de datos.', 'wpgpt-mcp-bridge' ),
            )
        );

        wp_register_ability_category(
            'peligro',
            array(
                'label'       => __( 'Peligro', 'wpgpt-mcp-bridge' ),
                'description' => __( 'Habilidades avanzadas para filesystem y ejecución PHP. Usar solo en desarrollo o staging.', 'wpgpt-mcp-bridge' ),
            )
        );
    }

    public static function register_abilities(): void {
        foreach ( self::get_providers() as $provider ) {
            $provider->register();
        }
    }

    public static function get_all_registered_names(): array {
        $names = array();
        foreach ( self::get_providers() as $provider ) {
            $names = array_merge( $names, array_keys( $provider->get_abilities() ) );
        }

        return array_values( array_unique( $names ) );
    }

    public static function get_raw_registry(): array {
        global $wp_abilities_registry;

        if ( is_object( $wp_abilities_registry ) && property_exists( $wp_abilities_registry, 'abilities' ) && is_array( $wp_abilities_registry->abilities ) ) {
            return $wp_abilities_registry->abilities;
        }

        return array();
    }
}
