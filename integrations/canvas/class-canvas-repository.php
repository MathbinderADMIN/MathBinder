<?php
if (!defined('ABSPATH')) exit;

/** Isolates Canvas external IDs from permanent MathBinder records. */
final class MathBinder_Canvas_Repository {
    public static function deployment_key(array $settings) { return hash('sha256', rtrim($settings['canvas_url'],'/').'|'.$settings['deployment_id']); }
    public static function mapping($type, $external_id, array $settings) {
        global $wpdb; $table=$wpdb->prefix.'mb_canvas_mappings';
        return $wpdb->get_row($wpdb->prepare("SELECT * FROM {$table} WHERE deployment_key=%s AND mapping_type=%s AND external_id=%s",self::deployment_key($settings),sanitize_key($type),(string)$external_id),ARRAY_A);
    }
    public static function save_mapping($type,$external_id,$mb_type,$mb_id,array $metadata,array $settings,$status='pending_review') {
        global $wpdb; $table=$wpdb->prefix.'mb_canvas_mappings'; $now=current_time('mysql',true);
        $existing=self::mapping($type,$external_id,$settings);
        $data=['deployment_key'=>self::deployment_key($settings),'mapping_type'=>sanitize_key($type),'external_id'=>sanitize_text_field($external_id),'mathbinder_type'=>sanitize_key($mb_type),'mathbinder_id'=>absint($mb_id),'status'=>sanitize_key($status),'metadata_json'=>wp_json_encode($metadata),'updated_at'=>$now];
        if($existing){$wpdb->update($table,$data,['id'=>(int)$existing['id']]);return (int)$existing['id'];}
        $data['created_at']=$now;$wpdb->insert($table,$data);return (int)$wpdb->insert_id;
    }
    public static function queue($type,$mb_type,$mb_id,array $payload,array $settings,$external_id='') {
        global $wpdb; $table=$wpdb->prefix.'mb_canvas_sync_jobs';$now=current_time('mysql',true);
        $wpdb->insert($table,['deployment_key'=>self::deployment_key($settings),'job_type'=>sanitize_key($type),'direction'=>'outbound','status'=>'queued','mathbinder_type'=>sanitize_key($mb_type),'mathbinder_id'=>(string)$mb_id,'external_id'=>sanitize_text_field($external_id),'payload_json'=>wp_json_encode($payload),'created_at'=>$now,'updated_at'=>$now]);
        return (int)$wpdb->insert_id;
    }
}
