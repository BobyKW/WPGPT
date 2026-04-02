<?php

namespace WPGPT\MCPBridge;

use WPGPT\MCPBridge\Comments\Comments_Service;

if ( ! defined( 'ABSPATH' ) ) { exit; }

class Comments_Provider extends Base_Ability_Provider {
    private ?Comments_Service $service = null;
    public function get_abilities(): array {
        return array(
            'wpgpt/comment-query' => array('label'=>__('Comment query','wpgpt-mcp-bridge'),'description'=>__('Lista comentarios con filtros.','wpgpt-mcp-bridge'),'input_schema'=>$this->query_schema(),'execute_callback'=>array($this,'comment_query'),'output_schema'=>$this->object_schema(),'permission_callback'=>array($this,'can_edit_content')),
            'wpgpt/comment-get' => array('label'=>__('Comment get','wpgpt-mcp-bridge'),'description'=>__('Obtiene un comentario.','wpgpt-mcp-bridge'),'input_schema'=>array('type'=>'object','properties'=>array('comment_id'=>array('type'=>'integer')),'required'=>array('comment_id')),'execute_callback'=>array($this,'comment_get'),'output_schema'=>$this->object_schema(),'permission_callback'=>array($this,'can_edit_content')),
            'wpgpt/comment-upsert' => array('label'=>__('Comment upsert','wpgpt-mcp-bridge'),'description'=>__('Crea o actualiza un comentario.','wpgpt-mcp-bridge'),'input_schema'=>$this->upsert_schema(),'execute_callback'=>array($this,'comment_upsert'),'output_schema'=>$this->object_schema(),'permission_callback'=>array($this,'can_write_content')),
            'wpgpt/comment-status-bulk' => array('label'=>__('Comment status bulk','wpgpt-mcp-bridge'),'description'=>__('Aplica acciones masivas a comentarios.','wpgpt-mcp-bridge'),'input_schema'=>array('type'=>'object','properties'=>array('comment_ids'=>array('type'=>'array','items'=>array('type'=>'integer')),'action'=>array('type'=>'string')),'required'=>array('comment_ids','action')),'execute_callback'=>array($this,'comment_status_bulk'),'output_schema'=>$this->object_schema(),'permission_callback'=>array($this,'can_write_content')),
            'wpgpt/comment-delete' => array('label'=>__('Comment delete','wpgpt-mcp-bridge'),'description'=>__('Elimina un comentario.','wpgpt-mcp-bridge'),'input_schema'=>array('type'=>'object','properties'=>array('comment_id'=>array('type'=>'integer'),'force'=>array('type'=>'boolean')),'required'=>array('comment_id')),'execute_callback'=>array($this,'comment_delete'),'output_schema'=>$this->object_schema(),'permission_callback'=>array($this,'can_delete_content')),
        );
    }
    public function comment_query(array $input){ return $this->service()->query_comments($input);} public function comment_get(array $input){ return $this->service()->get_comment($input);} public function comment_upsert(array $input){ return $this->service()->upsert_comment($input);} public function comment_status_bulk(array $input){ return $this->service()->bulk_status($input);} public function comment_delete(array $input){ return $this->service()->delete_comment_data($input);} private function service(): Comments_Service { return $this->service ??= new Comments_Service(); }
    private function query_schema(): array { return array('type'=>'object','properties'=>array('post_id'=>array('type'=>'integer'),'author_email'=>array('type'=>'string'),'author_name'=>array('type'=>'string'),'status'=>array('type'=>'string'),'search'=>array('type'=>'string'),'date_from'=>array('type'=>'string'),'date_to'=>array('type'=>'string'),'parent'=>array('type'=>'integer'),'orderby'=>array('type'=>'string'),'order'=>array('type'=>'string'),'page'=>array('type'=>'integer'),'per_page'=>array('type'=>'integer'))); }
    private function upsert_schema(): array { return array('type'=>'object','properties'=>array('comment_id'=>array('type'=>'integer'),'post_id'=>array('type'=>'integer'),'content'=>array('type'=>'string'),'author_name'=>array('type'=>'string'),'author_email'=>array('type'=>'string'),'author_url'=>array('type'=>'string'),'parent'=>array('type'=>'integer'),'status'=>array('type'=>'string')),'required'=>array('post_id','content')); }
}
