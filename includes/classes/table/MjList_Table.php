<?php

namespace Mj\Member\Classes\Table;

use Mj\Member\Classes\MjTools;
use WP_List_Table;

if (!defined('ABSPATH')) {
    exit;
}

if (!class_exists('\\WP_List_Table')) {
    require_once ABSPATH . 'wp-admin/includes/class-wp-list-table.php';
}

class MjList_Table extends WP_List_Table{
    private static $_message = array();
    public function addMessage($message){
        self::$_message[] = $message;
    }
    public function getMessage() {
        if(empty(self::$_message))
            return false;
        
        $output = '<ul class="notice notice-success">';
        foreach(self::$_message as $message)
            $output .= "<li>$message</li>";
        
        return $output . '</ul>';
    }
    
    function process_public_action(){
        
        if ( isset( $_REQUEST['id_public_on'] ) &&  !empty( $_REQUEST['id_public_on'] )) {
            MjTools::getWpdb()->query("UPDATE $this->table_name SET public = 1 WHERE id = ". intval($_REQUEST['id_public_on']));
            $this->addMessage('Public set on');
        }
        elseif ( isset( $_REQUEST['id_public_off'] ) &&  !empty( $_REQUEST['id_public_off'] )) {
            MjTools::getWpdb()->query("UPDATE $this->table_name SET public = 0 WHERE id = ". intval($_REQUEST['id_public_off']));
            $this->addMessage('Public set off');
        }
        
    }
    
    protected function row_actions( $actions, $always_visible = false ) {
		$action_count = count( $actions );

		if ( ! $action_count ) {
			return '';
		}

		$mode = get_user_setting( 'posts_list_mode', 'list' );

		if ( 'excerpt' === $mode ) {
			$always_visible = true;
		}

		$output = '<div class="' . ( $always_visible ? 'row-actions visible' : 'row-actions' ) . '">';

		$i = 0;

		foreach ( $actions as $action =>  $data) {
			++$i;
                        $link = (isset($data['link']))?$data['link']: $data;
                        $separator_item = (isset($data['separator']))?$data['separator']: ' | ';
			$separator = ( $i < $action_count ) ? $separator_item : '';

			$output .= "<span class='$action'>{$link}{$separator}</span>";
		}

		$output .= '</div>';

		$output .= '<button type="button" class="toggle-row"><span class="screen-reader-text">' .
			/* translators: Hidden accessibility text. */
			__( 'Show more details' ) .
		'</span></button>';

		return $output;
	}
    
    function column_public($item)
    {
        if($item['public'])
        {
            return sprintf('<a class="active-yes" href="?page=point_livraison&id_public_off=%s"> <span class="dashicons dashicons-yes"></span> </a>', $item['id']);
        }
        else {
            return sprintf('<a class="active-no" href="?page=point_livraison&id_public_on=%s"><span class="dashicons dashicons-no"></span></a>', $item['id']);
        }
    }
    
    function column_active($item)
    {
        if($item['active'])
        {
            return sprintf('<a class="active-yes" href="?page='.$this->page_name. '&id_active_off=%s"> <span class="dashicons dashicons-yes"></span> </a>', $item['id']);
        }
        else {
            return sprintf('<a class="active-no" href="?page='.$this->page_name. '&id_active_on=%s"><span class="dashicons dashicons-no"></span></a>', $item['id'] );
        }
    }
    
    function process_active_action(){
           
        if ( isset( $_REQUEST['id_active_on'] ) &&  is_numeric( $_REQUEST['id_active_on'] )) {
            MjTools::getWpdb()->query("UPDATE $this->table_name SET active = 1 WHERE id = ". intval($_REQUEST['id_active_on']));
            $this->addMessage('Active On');
        }
        elseif ( isset( $_REQUEST['id_active_off'] ) &&  is_numeric( $_REQUEST['id_active_off'] )) {
            MjTools::getWpdb()->query("UPDATE $this->table_name SET active = 0 WHERE id = ". intval($_REQUEST['id_active_off']));
            $this->addMessage('Active Off');
        }
        
    }
    
    
    function process_bulk_action()
    {
        if ('delete' === $this->current_action()) {
            $ids = isset($_REQUEST['id']) ? $_REQUEST['id'] : array();
            
            
            if (is_array($ids)) 
                $ids = implode(',', $ids);
            
            
            if (!empty($ids)) {
                MjTools::getWpdb()->query("DELETE FROM $this->table_name WHERE id IN($ids)");
                $this->addMessage(
                            sprintf(__('Items deleted: %d', 'wpbc'), $ids)
                        );
            }
        }       
    
    }
}