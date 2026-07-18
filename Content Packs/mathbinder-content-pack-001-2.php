<?php
/*
Plugin Name: MathBinder Content Pack 001.2 - Number Operations
Description: Adds the Number Operations binder page to MathBinder Core.
Version: 0.1.2
*/
defined('ABSPATH')||exit;

register_activation_hook(__FILE__,'mbcp0012_install');
function mbcp0012_install(){
 if(!post_type_exists('mb_binder_page')) return;
 $existing=get_page_by_title('Number Operations',OBJECT,'mb_binder_page');
 if($existing) return;
 $id=wp_insert_post([
   'post_type'=>'mb_binder_page',
   'post_status'=>'publish',
   'post_title'=>'Number Operations',
   'post_content'=>''
 ]);
 if(!$id||is_wp_error($id)) return;
 update_post_meta($id,'_mb_subtitle','The Four Operations');
 update_post_meta($id,'_mb_essential_question','How do the four operations work together to solve problems?');
 update_post_meta($id,'_mb_learning_targets',"- Explain each operation\n- Select the correct operation\n- Solve real-world problems");
 update_post_meta($id,'_mb_vocabulary',"Addition\nSubtraction\nMultiplication\nDivision\nSum\nDifference\nProduct\nQuotient");
 update_post_meta($id,'_mb_real_life','Students use number operations every day when shopping, cooking, budgeting and playing games.');
}
