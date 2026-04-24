<?php

namespace WPGPT\MCPBridge;

use WP_Error;
use WPGPT\MCPBridge\Support\Ability_Catalog;
use WPGPT\MCPBridge\Support\Compact_Ability_Catalog;
use WP_REST_Request;
use WP_REST_Response;

if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

class Native_MCP_Server {
    private const REST_NAMESPACE = 'wpgpt-mcp/v1';
    private const PROTOCOL_VERSION = '2025-06-18';
    private const SUPPORTED_PROTOCOL_VERSIONS = array( '2024-11-05', '2025-06-18' );
    private const SESSION_TRANSIENT_PREFIX = 'wpgpt_mcp_session_';

    public static function init(): void {
        add_action( 'rest_api_init', array( __CLASS__, 'register_routes' ) );
    }

    public static function register_routes(): void {
        register_rest_route( self::REST_NAMESPACE, '/http', array(
            'methods' => array( 'GET', 'POST', 'DELETE', 'HEAD' ),
            'callback' => array( __CLASS__, 'handle_streamable_http' ),
            'permission_callback' => array( __CLASS__, 'permission_callback' ),
            'show_in_index' => false,
        ) );

        register_rest_route( self::REST_NAMESPACE, '/(?P<wpgpt_token>[A-Za-z0-9_\-]{20,})', array(
            'methods' => array( 'GET', 'POST', 'DELETE', 'HEAD' ),
            'callback' => array( __CLASS__, 'handle_streamable_http' ),
            'permission_callback' => array( __CLASS__, 'permission_callback' ),
            'show_in_index' => false,
        ) );

        register_rest_route( self::REST_NAMESPACE, '/(?P<wpgpt_token>[A-Za-z0-9_\-]{20,})/sse', array(
            'methods' => array( 'GET', 'POST', 'HEAD' ),
            'callback' => array( __CLASS__, 'handle_streamable_http' ),
            'permission_callback' => array( __CLASS__, 'permission_callback' ),
            'show_in_index' => false,
        ) );

        register_rest_route( self::REST_NAMESPACE, '/(?P<wpgpt_token>[A-Za-z0-9_\-]{20,})/messages', array(
            'methods' => 'POST',
            'callback' => array( __CLASS__, 'handle_streamable_http_post' ),
            'permission_callback' => array( __CLASS__, 'permission_callback' ),
            'show_in_index' => false,
        ) );
    }

    public static function permission_callback( WP_REST_Request $request ): bool {
        $route_token = (string) $request->get_param( 'wpgpt_token' );
        if ( '' !== $route_token ) {
            $_GET['wpgpt_token'] = $route_token;
        }
        return Security::check_mcp_transport_permission( $request );
    }

    public static function handle_streamable_http( WP_REST_Request $request ) {
        $method = strtoupper( $request->get_method() );
        if ( 'HEAD' === $method ) {
            return new WP_REST_Response( null, 200, array( 'Content-Type' => 'application/json' ) );
        }
        if ( 'POST' === $method ) {
            return self::handle_streamable_http_post( $request );
        }
        if ( 'GET' === $method ) {
            return self::handle_streamable_http_get( $request );
        }
        if ( 'DELETE' === $method ) {
            return self::handle_streamable_http_delete( $request );
        }
        return new WP_REST_Response( array( 'error' => 'Method not allowed' ), 405 );
    }

    public static function handle_streamable_http_post( WP_REST_Request $request ) {
        $raw_body = $request->get_body();
        if ( '' === trim( (string) $raw_body ) ) {
            return self::json_rpc_response( null, null, self::rpc_error_payload( -32700, 'Parse error: empty body' ), 400, $request );
        }
        $data = json_decode( $raw_body, true );
        if ( JSON_ERROR_NONE !== json_last_error() ) {
            return self::json_rpc_response( null, null, self::rpc_error_payload( -32700, 'Parse error: invalid JSON' ), 400, $request );
        }
        if ( self::is_list_array( $data ) ) {
            $responses = array();
            foreach ( $data as $item ) {
                $response = self::handle_json_rpc_message( is_array( $item ) ? $item : array(), $request );
                if ( null !== $response ) {
                    $responses[] = $response;
                }
            }
            $rest = new WP_REST_Response( $responses, 200 );
            $rest->header( 'Content-Type', 'application/json' );
            return self::attach_session_header( $rest, self::get_or_create_session_id( $request, false ) );
        }
        $response_payload = self::handle_json_rpc_message( is_array( $data ) ? $data : array(), $request );
        if ( null === $response_payload ) {
            return self::attach_session_header( new WP_REST_Response( null, 204 ), self::get_or_create_session_id( $request, false ) );
        }
        $rest = new WP_REST_Response( $response_payload, 200 );
        $rest->header( 'Content-Type', 'application/json' );
        return self::attach_session_header( $rest, self::get_or_create_session_id( $request, true ) );
    }

    private static function handle_streamable_http_get( WP_REST_Request $request ) {
        $accept = strtolower( (string) $request->get_header( 'accept' ) );
        if ( false === strpos( $accept, 'text/event-stream' ) ) {
            return new WP_REST_Response( array(
                'ok' => true,
                'server' => 'WPGPT Native MCP Server',
                'site' => get_bloginfo( 'name' ),
                'transport' => 'streamable-http',
                'tools' => array( 'wpgpt_ping', 'wpgpt_discover_abilities', 'wpgpt_get_ability_info', 'wpgpt_execute_ability' ),
            ), 200 );
        }

        $session_id = self::get_or_create_session_id( $request, true );
        @ini_set( 'zlib.output_compression', '0' );
        @ini_set( 'output_buffering', '0' );
        @ini_set( 'implicit_flush', '1' );
        if ( function_exists( 'ob_implicit_flush' ) ) { ob_implicit_flush( true ); }
        header( 'Content-Type: text/event-stream' );
        header( 'Cache-Control: no-cache, no-transform' );
        header( 'X-Accel-Buffering: no' );
        header( 'Connection: keep-alive' );
        header( 'Mcp-Session-Id: ' . $session_id );
        while ( ob_get_level() ) { @ob_end_flush(); }
        echo "event: open\n";
        echo 'data: ' . wp_json_encode( array( 'session' => $session_id ) ) . "\n\n";
        flush();
        $started = time();
        while ( ! connection_aborted() && ( time() - $started ) < 12 ) {
            echo ": heartbeat\n\n";
            flush();
            sleep( 4 );
        }
        echo "event: close\n";
        echo "data: {}\n\n";
        flush();
        exit;
    }

    private static function handle_streamable_http_delete( WP_REST_Request $request ) {
        $session_id = self::get_or_create_session_id( $request, false );
        if ( '' !== $session_id ) {
            delete_transient( self::SESSION_TRANSIENT_PREFIX . $session_id );
        }
        return self::attach_session_header( new WP_REST_Response( null, 204 ), $session_id );
    }

    private static function handle_json_rpc_message( array $message, WP_REST_Request $request ): ?array {
        $id = $message['id'] ?? null;
        $method = isset( $message['method'] ) ? (string) $message['method'] : '';
        $params = isset( $message['params'] ) && is_array( $message['params'] ) ? $message['params'] : array();
        if ( '' === $method ) {
            return array( 'jsonrpc' => '2.0', 'id' => $id, 'error' => self::rpc_error_payload( -32600, 'Invalid Request' ) );
        }
        if ( null === $id && 0 === strpos( $method, 'notifications/' ) ) {
            return null;
        }
        switch ( $method ) {
            case 'initialize': return self::handle_initialize( $id, $params, $request );
            case 'ping': return array( 'jsonrpc' => '2.0', 'id' => $id, 'result' => (object) array() );
            case 'tools/list': return array( 'jsonrpc' => '2.0', 'id' => $id, 'result' => array( 'tools' => self::tools_list() ) );
            case 'tools/call': return self::handle_tools_call( $id, $params );
            case 'resources/list': return array( 'jsonrpc' => '2.0', 'id' => $id, 'result' => array( 'resources' => array() ) );
            case 'prompts/list': return array( 'jsonrpc' => '2.0', 'id' => $id, 'result' => array( 'prompts' => array() ) );
            default: return array( 'jsonrpc' => '2.0', 'id' => $id, 'error' => self::rpc_error_payload( -32601, 'Method not found: ' . $method ) );
        }
    }

    private static function handle_initialize( $id, array $params, WP_REST_Request $request ): array {
        $requested = isset( $params['protocolVersion'] ) ? (string) $params['protocolVersion'] : '';
        $version = in_array( $requested, self::SUPPORTED_PROTOCOL_VERSIONS, true ) ? $requested : self::PROTOCOL_VERSION;
        self::get_or_create_session_id( $request, true );
        return array(
            'jsonrpc' => '2.0',
            'id' => $id,
            'result' => array(
                'protocolVersion' => $version,
                'serverInfo' => array( 'name' => 'WPGPT MCP Bridge - ' . get_bloginfo( 'name' ), 'version' => WPGPT_MCP_BRIDGE_VERSION ),
                'capabilities' => array( 'tools' => (object) array() ),
            ),
        );
    }

    private static function tools_list(): array {
        return array(
            array( 'name' => 'wpgpt_ping', 'description' => 'Connectivity check. Returns current GMT time, site name, and WordPress URL.', 'inputSchema' => array( 'type' => 'object', 'properties' => (object) array(), 'required' => array() ), 'annotations' => array( 'readOnlyHint' => true, 'destructiveHint' => false, 'idempotentHint' => true, 'openWorldHint' => false ) ),
            array( 'name' => 'wpgpt_discover_abilities', 'description' => 'Discover all WPGPT WordPress abilities available to this MCP connection.', 'inputSchema' => array( 'type' => 'object', 'properties' => (object) array(), 'required' => array() ), 'annotations' => array( 'readOnlyHint' => true, 'destructiveHint' => false, 'idempotentHint' => true, 'openWorldHint' => false ) ),
            array( 'name' => 'wpgpt_get_ability_info', 'description' => 'Get input schema, output schema, description, and metadata for one WPGPT ability by name.', 'inputSchema' => array( 'type' => 'object', 'properties' => array( 'ability_name' => array( 'type' => 'string', 'description' => 'Full ability name, for example wpgpt/system-query.' ) ), 'required' => array( 'ability_name' ) ), 'annotations' => array( 'readOnlyHint' => true, 'destructiveHint' => false, 'idempotentHint' => true, 'openWorldHint' => false ) ),
            array( 'name' => 'wpgpt_execute_ability', 'description' => 'Execute one WPGPT ability by name with parameters. Use dry_run=true when available before making changes.', 'inputSchema' => array( 'type' => 'object', 'properties' => array( 'ability_name' => array( 'type' => 'string', 'description' => 'Full ability name to execute.' ), 'parameters' => array( 'type' => 'object', 'description' => 'Parameters to pass to the ability.', 'additionalProperties' => true ) ), 'required' => array( 'ability_name', 'parameters' ) ), 'annotations' => array( 'readOnlyHint' => false, 'destructiveHint' => true, 'idempotentHint' => false, 'openWorldHint' => true ) ),
        );
    }

    private static function handle_tools_call( $id, array $params ): array {
        $tool = isset( $params['name'] ) ? (string) $params['name'] : '';
        $arguments = isset( $params['arguments'] ) && is_array( $params['arguments'] ) ? $params['arguments'] : array();
        switch ( $tool ) {
            case 'wpgpt_ping': return self::tool_success( $id, array( 'time_gmt' => gmdate( 'Y-m-d H:i:s' ), 'site' => get_bloginfo( 'name' ), 'home_url' => home_url( '/' ), 'version' => WPGPT_MCP_BRIDGE_VERSION ) );
            case 'wpgpt_discover_abilities': return self::tool_success( $id, array( 'abilities' => self::discover_abilities() ) );
            case 'wpgpt_get_ability_info':
                $ability_name = isset( $arguments['ability_name'] ) ? (string) $arguments['ability_name'] : '';
                if ( '' === $ability_name ) { return self::rpc_error( $id, -32602, 'ability_name is required' ); }
                $info = self::get_ability_info( $ability_name );
                if ( is_wp_error( $info ) ) { return self::rpc_error( $id, -32602, $info->get_error_message() ); }
                return self::tool_success( $id, $info );
            case 'wpgpt_execute_ability':
                $ability_name = isset( $arguments['ability_name'] ) ? (string) $arguments['ability_name'] : '';
                $parameters = isset( $arguments['parameters'] ) && is_array( $arguments['parameters'] ) ? $arguments['parameters'] : array();
                if ( '' === $ability_name ) { return self::rpc_error( $id, -32602, 'ability_name is required' ); }
                $result = self::execute_ability( $ability_name, $parameters );
                if ( is_wp_error( $result ) ) { return self::tool_success( $id, array( 'success' => false, 'error' => $result->get_error_message(), 'code' => $result->get_error_code() ), true ); }
                return self::tool_success( $id, array( 'success' => true, 'data' => $result ) );
            default: return self::rpc_error( $id, -32602, 'Unknown tool: ' . $tool );
        }
    }

    private static function discover_abilities(): array {
        $groups = self::get_compact_public_groups();
        $list = array();

        foreach ( $groups as $group ) {
            $list[] = array(
                'name'        => $group['name'],
                'label'       => $group['label'],
                'description' => $group['description'],
            );
        }

        return $list;
    }

    private static function get_ability_info( string $ability_name ) {
        $ability = self::get_public_ability( $ability_name );
        if ( $ability ) {
            $info = array( 'name' => $ability->get_name(), 'label' => $ability->get_label(), 'description' => $ability->get_description(), 'input_schema' => self::normalize_schema_for_json( $ability->get_input_schema() ) );
            $output_schema = $ability->get_output_schema();
            if ( ! empty( $output_schema ) ) { $info['output_schema'] = self::normalize_schema_for_json( $output_schema ); }
            $meta = $ability->get_meta();
            if ( ! empty( $meta ) ) { $info['meta'] = $meta; }
            return $info;
        }

        $compact = self::get_compact_public_group( $ability_name );
        if ( is_array( $compact ) ) {
            $actions = array();
            foreach ( $compact['actions'] as $action ) {
                $actions[] = array(
                    'action'           => $action,
                    'description'      => Compact_Ability_Catalog::action_description( (string) $action ),
                    'detailed_ability' => $compact['action_map'][ $action ] ?? null,
                );
            }

            return array(
                'name'          => $compact['name'],
                'label'         => $compact['label'],
                'description'   => $compact['description'],
                'input_schema'  => self::normalize_schema_for_json( Compact_Ability_Catalog::input_schema_for_group( $compact ) ),
                'output_schema' => self::normalize_schema_for_json( Compact_Ability_Catalog::output_schema_for_group() ),
                'meta'          => array(
                    'mcp'                  => array( 'public' => true ),
                    'compact'              => true,
                    'actions'              => $actions,
                    'action_map'           => $compact['action_map'],
                    'underlying_abilities' => $compact['underlying_abilities'],
                    'security_note'        => __( 'Esta es una ability compacta. Cada acción interna vuelve a validar allowlist, permisos globales, modo seguro y permisos del usuario WordPress antes de ejecutarse.', 'wpgpt-mcp-bridge' ),
                ),
            );
        }

        return new WP_Error( 'wpgpt_ability_not_found', sprintf( 'Ability not found or not exposed: %s', $ability_name ) );
    }

    private static function execute_ability( string $ability_name, array $parameters ) {
        $ability = self::get_public_ability( $ability_name );
        if ( $ability ) {
            return self::execute_public_ability_object( $ability, $parameters );
        }

        $compact = self::get_compact_public_group( $ability_name );
        if ( is_array( $compact ) ) {
            $action = isset( $parameters['action'] ) ? sanitize_key( (string) $parameters['action'] ) : '';
            if ( '' === $action ) {
                return new WP_Error( 'wpgpt_compact_action_required', __( 'El parámetro action es obligatorio para una ability compacta.', 'wpgpt-mcp-bridge' ) );
            }

            if ( ! isset( $compact['action_map'][ $action ] ) ) {
                return new WP_Error( 'wpgpt_compact_action_not_allowed', sprintf( 'Action not available for %1$s: %2$s', $ability_name, $action ) );
            }

            $target_name = (string) $compact['action_map'][ $action ];
            $target = self::get_public_ability( $target_name );
            if ( ! $target ) {
                return new WP_Error( 'wpgpt_compact_target_not_public', sprintf( 'Detailed ability not available or not exposed: %s', $target_name ) );
            }

            $inner_parameters = isset( $parameters['parameters'] ) && is_array( $parameters['parameters'] ) ? $parameters['parameters'] : array();
            return self::execute_public_ability_object( $target, $inner_parameters );
        }

        return new WP_Error( 'wpgpt_ability_not_found', sprintf( 'Ability not found or not exposed: %s', $ability_name ) );
    }

    private static function execute_public_ability_object( $ability, array $parameters ) {
        try {
            if ( class_exists( '\\WP\\MCP\\Domain\\Utils\\AbilityArgumentNormalizer' ) ) {
                $parameters = \WP\MCP\Domain\Utils\AbilityArgumentNormalizer::normalize( $ability, $parameters );
            }
            $result = $ability->execute( $parameters );
            return is_wp_error( $result ) ? $result : $result;
        } catch ( \Throwable $e ) {
            return new WP_Error( 'wpgpt_ability_exception', $e->getMessage() );
        }
    }

    private static function get_compact_public_groups(): array {
        $summary = array();
        foreach ( self::get_public_abilities() as $ability ) {
            $summary[] = array(
                'name'        => $ability->get_name(),
                'label'       => $ability->get_label(),
                'description' => $ability->get_description(),
            );
        }

        return Compact_Ability_Catalog::build_groups( $summary );
    }

    private static function get_compact_public_group( string $compact_name ): ?array {
        $groups = self::get_compact_public_groups();
        return $groups[ $compact_name ] ?? null;
    }

    private static function get_public_ability( string $ability_name ) {
        if ( ! function_exists( 'wp_get_ability' ) ) { return null; }
        $ability = wp_get_ability( $ability_name );
        return ( $ability && self::is_ability_public( $ability ) ) ? $ability : null;
    }

    private static function get_public_abilities(): array {
        if ( ! function_exists( 'wp_get_abilities' ) ) { return array(); }
        $public = array();
        foreach ( wp_get_abilities() as $ability ) {
            if ( self::is_ability_public( $ability ) ) { $public[] = $ability; }
        }
        return $public;
    }

    private static function is_ability_public( $ability ): bool {
        if ( ! is_object( $ability ) || ! method_exists( $ability, 'get_name' ) ) { return false; }
        $name = (string) $ability->get_name();
        if ( 0 !== strpos( $name, 'wpgpt/' ) ) { return false; }
        if ( ! Security::is_ability_enabled( $name ) ) { return false; }

        // Apply global exposure policy during discovery and execution, not only in the admin UI.
        // This keeps compact abilities honest: disabled write/delete permissions remove those
        // detailed actions from the compact action_map before a client can call them.
        $declared_ability = Ability_Catalog::find( $name );
        if ( ! Security::is_ability_exposed_by_policy( $name, is_array( $declared_ability ) ? $declared_ability : array() ) ) {
            return false;
        }

        $meta = method_exists( $ability, 'get_meta' ) ? $ability->get_meta() : array();
        return isset( $meta['mcp']['public'] ) ? (bool) $meta['mcp']['public'] : true;
    }

    private static function tool_success( $id, $data, bool $is_error = false ): array {
        $text = wp_json_encode( $data, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES );
        return array( 'jsonrpc' => '2.0', 'id' => $id, 'result' => array( 'content' => array( array( 'type' => 'text', 'text' => false === $text ? '' : $text ) ), 'isError' => $is_error, 'data' => $data ) );
    }

    private static function rpc_error( $id, int $code, string $message, $data = null ): array {
        return array( 'jsonrpc' => '2.0', 'id' => $id, 'error' => self::rpc_error_payload( $code, $message, $data ) );
    }

    private static function rpc_error_payload( int $code, string $message, $data = null ): array {
        $error = array( 'code' => $code, 'message' => $message );
        if ( null !== $data ) { $error['data'] = $data; }
        return $error;
    }

    private static function json_rpc_response( $id, $result, ?array $error, int $status, WP_REST_Request $request ) {
        $payload = array( 'jsonrpc' => '2.0', 'id' => $id );
        if ( null !== $error ) { $payload['error'] = $error; } else { $payload['result'] = $result; }
        $response = new WP_REST_Response( $payload, $status );
        $response->header( 'Content-Type', 'application/json' );
        return self::attach_session_header( $response, self::get_or_create_session_id( $request, false ) );
    }

    private static function get_or_create_session_id( WP_REST_Request $request, bool $create ): string {
        $header = (string) $request->get_header( 'mcp-session-id' );
        if ( '' !== $header ) { return sanitize_text_field( $header ); }
        if ( ! $create ) { return ''; }
        $session_id = wp_generate_uuid4();
        set_transient( self::SESSION_TRANSIENT_PREFIX . $session_id, time(), 15 * MINUTE_IN_SECONDS );
        return $session_id;
    }

    private static function attach_session_header( WP_REST_Response $response, string $session_id ): WP_REST_Response {
        if ( '' !== $session_id ) { $response->header( 'Mcp-Session-Id', $session_id ); }
        return $response;
    }

    private static function is_list_array( $value ): bool {
        return is_array( $value ) && array_keys( $value ) === range( 0, count( $value ) - 1 );
    }

    private static function normalize_schema_for_json( $schema ) {
        if ( is_array( $schema ) && empty( $schema ) ) { return (object) array(); }
        if ( is_array( $schema ) ) {
            $normalized = array();
            foreach ( $schema as $key => $value ) {
                if ( 'properties' === $key && is_array( $value ) && empty( $value ) ) { $normalized[ $key ] = (object) array(); continue; }
                $normalized[ $key ] = self::normalize_schema_for_json( $value );
            }
            return $normalized;
        }
        return $schema;
    }
}
