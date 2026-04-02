<?php

namespace WPGPT\MCPBridge\Diagnostics\Operations;

use WPGPT\MCPBridge\Diagnostics\Diagnostic_Operation;
use WP_Error;

if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

class List_Cron_Events_Operation implements Diagnostic_Operation {
    public function name(): string {
        return 'list_cron_events';
    }

    public function label(): string {
        return __( 'List cron events', 'wpgpt-mcp-bridge' );
    }

    public function description(): string {
        return __( 'Lista eventos cron programados con filtro opcional por hook.', 'wpgpt-mcp-bridge' );
    }

    public function input_schema(): array {
        return array(
            'type'       => 'object',
            'properties' => array(
                'hook_like' => array( 'type' => 'string' ),
                'limit'     => array( 'type' => 'integer' ),
            ),
        );
    }

    public function execute( array $input ): array|WP_Error {
        $cron      = _get_cron_array();
        $hook_like = isset( $input['hook_like'] ) ? sanitize_text_field( (string) $input['hook_like'] ) : '';
        $limit     = isset( $input['limit'] ) ? max( 1, min( 300, (int) $input['limit'] ) ) : 100;
        $items     = array();

        if ( ! is_array( $cron ) ) {
            return array(
                'operation' => $this->name(),
                'count'     => 0,
                'items'     => array(),
            );
        }

        foreach ( $cron as $timestamp => $hooks ) {
            foreach ( (array) $hooks as $hook => $instances ) {
                if ( '' !== $hook_like && false === stripos( (string) $hook, $hook_like ) ) {
                    continue;
                }

                foreach ( (array) $instances as $instance ) {
                    $items[] = array(
                        'hook'         => (string) $hook,
                        'timestamp'    => (int) $timestamp,
                        'datetime_gmt' => gmdate( 'c', (int) $timestamp ),
                        'schedule'     => isset( $instance['schedule'] ) ? (string) $instance['schedule'] : '',
                    );

                    if ( count( $items ) >= $limit ) {
                        break 3;
                    }
                }
            }
        }

        return array(
            'operation' => $this->name(),
            'count'     => count( $items ),
            'items'     => array_values( $items ),
        );
    }
}
