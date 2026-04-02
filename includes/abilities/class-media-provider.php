<?php

namespace WPGPT\MCPBridge;

use WPGPT\MCPBridge\Media\Media_Service;
use WP_Error;

if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

class Media_Provider extends Base_Ability_Provider {
    private ?Media_Service $service = null;

    public function get_abilities(): array {
        return array(
            'wpgpt/media-list' => array(
                'label' => __( 'Media list', 'wpgpt-mcp-bridge' ),
                'description' => __( 'Lista adjuntos y medios del sitio.', 'wpgpt-mcp-bridge' ),
                'input_schema' => $this->media_list_schema(),
                'execute_callback' => array( $this, 'media_list' ),
                'output_schema' => $this->object_schema(),
                'permission_callback' => array( $this, 'can_edit_content' ),
            ),
            'wpgpt/media-get' => array(
                'label' => __( 'Media get', 'wpgpt-mcp-bridge' ),
                'description' => __( 'Obtiene un adjunto concreto con metadatos.', 'wpgpt-mcp-bridge' ),
                'input_schema' => $this->media_get_schema(),
                'execute_callback' => array( $this, 'media_get' ),
                'output_schema' => $this->object_schema(),
                'permission_callback' => array( $this, 'can_edit_content' ),
            ),
            'wpgpt/media-update' => array(
                'label' => __( 'Media update', 'wpgpt-mcp-bridge' ),
                'description' => __( 'Actualiza título, alt, caption o descripción de un adjunto.', 'wpgpt-mcp-bridge' ),
                'input_schema' => array( 'type' => 'object', 'properties' => array( 'attachment_id' => array( 'type' => 'integer' ), 'title' => array( 'type' => 'string' ), 'alt' => array( 'type' => 'string' ), 'caption' => array( 'type' => 'string' ), 'description' => array( 'type' => 'string' ) ), 'required' => array( 'attachment_id' ) ),
                'execute_callback' => array( $this, 'media_update' ),
                'output_schema' => $this->object_schema(),
                'permission_callback' => array( $this, 'can_write_content' ),
            ),
            'wpgpt/media-file-rename' => array(
                'label' => __( 'Media file rename', 'wpgpt-mcp-bridge' ),
                'description' => __( 'Renombra el archivo físico principal de un adjunto y actualiza su ruta.', 'wpgpt-mcp-bridge' ),
                'input_schema' => array( 'type' => 'object', 'properties' => array( 'attachment_id' => array( 'type' => 'integer' ), 'new_filename' => array( 'type' => 'string' ) ), 'required' => array( 'attachment_id', 'new_filename' ) ),
                'execute_callback' => array( $this, 'media_file_rename' ),
                'output_schema' => $this->object_schema(),
                'permission_callback' => array( $this, 'can_write_files' ),
            ),
            'wpgpt/media-sideload' => array(
                'label' => __( 'Media sideload', 'wpgpt-mcp-bridge' ),
                'description' => __( 'Descarga un archivo remoto y lo adjunta a la biblioteca multimedia.', 'wpgpt-mcp-bridge' ),
                'input_schema' => $this->media_sideload_schema(),
                'execute_callback' => array( $this, 'media_sideload' ),
                'output_schema' => $this->object_schema(),
                'permission_callback' => array( $this, 'can_write_content' ),
            ),
            'wpgpt/post-featured-image-set' => array(
                'label' => __( 'Post featured image set', 'wpgpt-mcp-bridge' ),
                'description' => __( 'Asigna una imagen destacada a un post.', 'wpgpt-mcp-bridge' ),
                'input_schema' => $this->featured_image_schema(),
                'execute_callback' => array( $this, 'post_featured_image_set' ),
                'output_schema' => $this->object_schema(),
                'permission_callback' => array( $this, 'can_write_content' ),
            ),
            'wpgpt/media-delete' => array(
                'label' => __( 'Media delete', 'wpgpt-mcp-bridge' ),
                'description' => __( 'Elimina un adjunto de la biblioteca multimedia.', 'wpgpt-mcp-bridge' ),
                'input_schema' => $this->media_delete_schema(),
                'execute_callback' => array( $this, 'media_delete' ),
                'output_schema' => $this->object_schema(),
                'permission_callback' => array( $this, 'can_delete_content' ),
            ),
        );
    }

    public function media_list( array $input ): array { return $this->service()->list_media( $input ); }
    public function media_get( array $input ): array|WP_Error { return $this->service()->get_media( $input ); }
    public function media_update( array $input ): array|WP_Error { return $this->service()->update_media( $input ); }
    public function media_file_rename( array $input ): array|WP_Error { return $this->service()->rename_media_file( $input ); }
    public function media_sideload( array $input ): array|WP_Error { return $this->service()->sideload_media( $input ); }
    public function post_featured_image_set( array $input ): array|WP_Error { return $this->service()->set_featured_image( $input ); }
    public function media_delete( array $input ): array|WP_Error { return $this->service()->delete_media( $input ); }

    private function service(): Media_Service {
        if ( null === $this->service ) {
            $this->service = new Media_Service();
        }
        return $this->service;
    }

    private function media_list_schema(): array { return array( 'type' => 'object', 'properties' => array( 'search' => array( 'type' => 'string' ), 'limit' => array( 'type' => 'integer' ) ) ); }
    private function media_get_schema(): array { return array( 'type' => 'object', 'properties' => array( 'attachment_id' => array( 'type' => 'integer' ) ), 'required' => array( 'attachment_id' ) ); }
    private function media_sideload_schema(): array { return array( 'type' => 'object', 'properties' => array( 'url' => array( 'type' => 'string' ), 'post_id' => array( 'type' => 'integer' ), 'title' => array( 'type' => 'string' ) ), 'required' => array( 'url' ) ); }
    private function featured_image_schema(): array { return array( 'type' => 'object', 'properties' => array( 'post_id' => array( 'type' => 'integer' ), 'attachment_id' => array( 'type' => 'integer' ) ), 'required' => array( 'post_id', 'attachment_id' ) ); }
    private function media_delete_schema(): array { return array( 'type' => 'object', 'properties' => array( 'attachment_id' => array( 'type' => 'integer' ), 'force' => array( 'type' => 'boolean' ) ), 'required' => array( 'attachment_id' ) ); }
}
