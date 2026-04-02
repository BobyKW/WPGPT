<?php

namespace WPGPT\MCPBridge\Diagnostics;

use WPGPT\MCPBridge\Diagnostics\Operations\Active_Hooks_Sample_Operation;
use WPGPT\MCPBridge\Diagnostics\Operations\List_Cron_Events_Operation;
use WPGPT\MCPBridge\Diagnostics\Operations\List_Hooks_Operation;
use WPGPT\MCPBridge\Diagnostics\Operations\List_Post_Types_Operation;
use WPGPT\MCPBridge\Diagnostics\Operations\List_Rest_Routes_Operation;
use WPGPT\MCPBridge\Diagnostics\Operations\List_Shortcodes_Operation;
use WPGPT\MCPBridge\Diagnostics\Operations\List_Taxonomies_Operation;
use WPGPT\MCPBridge\Diagnostics\Operations\Plugin_Status_Operation;
use WP_Error;

if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

class Diagnostic_Registry {
    /**
     * @var Diagnostic_Operation[]|null
     */
    private static ?array $operations = null;

    public static function all(): array {
        if ( null === self::$operations ) {
            self::$operations = array(
                new List_Post_Types_Operation(),
                new List_Taxonomies_Operation(),
                new Active_Hooks_Sample_Operation(),
                new Plugin_Status_Operation(),
                new List_Rest_Routes_Operation(),
                new List_Shortcodes_Operation(),
                new List_Hooks_Operation(),
                new List_Cron_Events_Operation(),
            );
        }

        return self::$operations;
    }

    public static function names(): array {
        return array_map(
            static function ( Diagnostic_Operation $operation ): string {
                return $operation->name();
            },
            self::all()
        );
    }

    public static function find( string $name ): ?Diagnostic_Operation {
        foreach ( self::all() as $operation ) {
            if ( $operation->name() === $name ) {
                return $operation;
            }
        }

        return null;
    }

    public static function execute( string $name, array $input = array() ): array|WP_Error {
        $operation = self::find( $name );
        if ( ! $operation ) {
            return new WP_Error(
                'wpgpt_invalid_operation',
                sprintf(
                    /* translators: %s: comma-separated list of operations */
                    __( 'Operación no permitida. Usa %s.', 'wpgpt-mcp-bridge' ),
                    implode( ', ', self::names() )
                ),
                array( 'status' => 400 )
            );
        }

        return $operation->execute( $input );
    }

    public static function info(): array {
        $items = array();

        foreach ( self::all() as $operation ) {
            $items[] = array(
                'name'        => $operation->name(),
                'label'       => $operation->label(),
                'description' => $operation->description(),
                'has_input'   => ! empty( $operation->input_schema() ),
            );
        }

        return $items;
    }
}
