<?php
if (!defined('ABSPATH')) exit;

final class MathBinder_Organization_Service {
    public static function create_organization($name, $type = 'school') {
        global $wpdb; $now = current_time('mysql', true);
        $name = sanitize_text_field($name);
        if ($name === '') return new WP_Error('organization_name', 'Organization name is required.');
        $wpdb->insert($wpdb->prefix.'mb_organizations', ['name'=>$name,'organization_type'=>sanitize_key($type),'status'=>'active','verification_status'=>'pending','owner_user_id'=>get_current_user_id(),'settings_json'=>'{}','created_at'=>$now,'updated_at'=>$now], ['%s','%s','%s','%s','%d','%s','%s','%s']);
        $id=(int)$wpdb->insert_id;
        if (!$id) return new WP_Error('organization_create', 'The organization could not be created.');
        MathBinder_Identity_Service::assign_role(get_current_user_id(), 'administrator', 'organization', $id);
        MathBinder_Audit_Log::record('create_organization','organization',$id,['name'=>$name]); return $id;
    }
    public static function organizations() { global $wpdb; return $wpdb->get_results("SELECT * FROM {$wpdb->prefix}mb_organizations ORDER BY name", ARRAY_A) ?: []; }
    public static function create_term($organization_id,$name,$start='',$end='') { global $wpdb; $now=current_time('mysql',true); $ok=$wpdb->insert($wpdb->prefix.'mb_terms',['organization_id'=>absint($organization_id),'name'=>sanitize_text_field($name),'starts_on'=>$start?sanitize_text_field($start):null,'ends_on'=>$end?sanitize_text_field($end):null,'status'=>'active','created_at'=>$now,'updated_at'=>$now]); $id=(int)$wpdb->insert_id; if($ok) MathBinder_Audit_Log::record('create_term','term',$id,['organization_id'=>absint($organization_id)]); return $id; }
    public static function create_class($organization_id,$term_id,$name,$section,$teacher_id=0) { global $wpdb; $now=current_time('mysql',true); do{$code=strtoupper(wp_generate_password(8,false,false));$exists=$wpdb->get_var($wpdb->prepare("SELECT id FROM {$wpdb->prefix}mb_classes WHERE class_code=%s",$code));}while($exists); $ok=$wpdb->insert($wpdb->prefix.'mb_classes',['organization_id'=>absint($organization_id),'term_id'=>absint($term_id),'name'=>sanitize_text_field($name),'section_name'=>sanitize_text_field($section),'teacher_user_id'=>absint($teacher_id),'class_code'=>$code,'status'=>'active','created_at'=>$now,'updated_at'=>$now]); $id=(int)$wpdb->insert_id; if($ok) MathBinder_Audit_Log::record('create_class','class',$id,['organization_id'=>absint($organization_id)]); return $id; }
    public static function enroll($class_id,$email,$role='student') { global $wpdb; $user=get_user_by('email',sanitize_email($email)); $now=current_time('mysql',true); $uid=$user?(int)$user->ID:0; $status=$user?'active':'invited'; $ok=$wpdb->insert($wpdb->prefix.'mb_enrollments',['class_id'=>absint($class_id),'user_id'=>$uid,'invited_email'=>sanitize_email($email),'role_key'=>sanitize_key($role),'status'=>$status,'source'=>'administrator','approved_by'=>get_current_user_id(),'created_at'=>$now,'updated_at'=>$now],['%d','%d','%s','%s','%s','%s','%d','%s','%s']); if(!$ok && $uid) $wpdb->update($wpdb->prefix.'mb_enrollments',['status'=>'active','updated_at'=>$now],['class_id'=>absint($class_id),'user_id'=>$uid,'role_key'=>sanitize_key($role)]); if($user) MathBinder_Identity_Service::assign_role($uid,$role,'class',absint($class_id)); MathBinder_Audit_Log::record('enroll_user','class',absint($class_id),['user_id'=>$uid,'email'=>sanitize_email($email),'status'=>$status]); return true; }
    public static function create_license($organization_id,$seats,$trial_days=30) { global $wpdb; $now=current_time('mysql',true); $trial=gmdate('Y-m-d H:i:s',time()+max(1,absint($trial_days))*DAY_IN_SECONDS); $wpdb->insert($wpdb->prefix.'mb_licenses',['organization_id'=>absint($organization_id),'plan_key'=>'school_premium','status'=>'trial','seat_limit'=>absint($seats),'trial_ends_at'=>$trial,'provider'=>'manual','created_at'=>$now,'updated_at'=>$now],['%d','%s','%s','%d','%s','%s','%s','%s']); $id=(int)$wpdb->insert_id; MathBinder_Audit_Log::record('create_license','license',$id,['organization_id'=>absint($organization_id),'seats'=>absint($seats)]); return $id; }
    public static function allocate_seat($license_id,$email) {
        global $wpdb;
        $license_id=absint($license_id); $email=sanitize_email($email);
        if(!$email || !is_email($email))return new WP_Error('seat_email','A valid account email is required.');
        $license=$wpdb->get_row($wpdb->prepare("SELECT * FROM {$wpdb->prefix}mb_licenses WHERE id=%d",$license_id),ARRAY_A);
        if(!$license)return new WP_Error('license','License not found.');
        $existing=$wpdb->get_row($wpdb->prepare("SELECT * FROM {$wpdb->prefix}mb_seat_allocations WHERE license_id=%d AND account_email=%s",$license_id,$email),ARRAY_A);
        $used=(int)$wpdb->get_var($wpdb->prepare("SELECT COUNT(*) FROM {$wpdb->prefix}mb_seat_allocations WHERE license_id=%d AND status='active'",$license_id));
        if((!$existing || $existing['status']!=='active') && $used >= (int)$license['seat_limit'])return new WP_Error('seat_limit','No seats remain on this license.');
        $user=get_user_by('email',$email); $now=current_time('mysql',true);
        $data=['license_id'=>$license_id,'user_id'=>$user?(int)$user->ID:0,'account_email'=>$email,'coverage_priority'=>100,'status'=>'active','allocated_by'=>get_current_user_id(),'updated_at'=>$now];
        if($existing){$ok=$wpdb->update($wpdb->prefix.'mb_seat_allocations',$data,['id'=>(int)$existing['id']]);}
        else{$data['created_at']=$now;$ok=$wpdb->insert($wpdb->prefix.'mb_seat_allocations',$data);}
        if($ok===false)return new WP_Error('seat_save','The premium seat could not be saved.');
        MathBinder_Audit_Log::record('allocate_seat','license',$license_id,['user_id'=>$user?(int)$user->ID:0,'account_email'=>$email]); return true;
    }
    public static function update_record_status($record_type,$id,$status) {
        global $wpdb; $record_type=sanitize_key($record_type); $id=absint($id); $status=sanitize_key($status); $now=current_time('mysql',true);
        $map=['organization'=>['mb_organizations',['active','inactive','archived']],'term'=>['mb_terms',['active','inactive','archived']],'class'=>['mb_classes',['active','inactive','archived']],'enrollment'=>['mb_enrollments',['active','invited','inactive','removed']],'license'=>['mb_licenses',['trial','active','grace','inactive','canceled']],'seat'=>['mb_seat_allocations',['active','revoked']]];
        if(!$id || !isset($map[$record_type]) || !in_array($status,$map[$record_type][1],true))return new WP_Error('record_status','Invalid status change.');
        $ok=$wpdb->update($wpdb->prefix.$map[$record_type][0],['status'=>$status,'updated_at'=>$now],['id'=>$id],['%s','%s'],['%d']);
        if($ok===false)return new WP_Error('record_update','The record could not be updated.');
        MathBinder_Audit_Log::record('update_'.$record_type.'_status',$record_type,$id,['status'=>$status]); return true;
    }
    public static function coverage_for_user($user_id) {
        global $wpdb; $user_id=absint($user_id); $user=get_user_by('id',$user_id);
        if($user && $user->user_email){$wpdb->query($wpdb->prepare("UPDATE {$wpdb->prefix}mb_seat_allocations SET user_id=%d,updated_at=%s WHERE user_id=0 AND account_email=%s",$user_id,current_time('mysql',true),sanitize_email($user->user_email)));}
        return $wpdb->get_row($wpdb->prepare("SELECT s.*,l.organization_id,l.plan_key,l.status AS license_status,l.trial_ends_at,l.grace_ends_at FROM {$wpdb->prefix}mb_seat_allocations s JOIN {$wpdb->prefix}mb_licenses l ON l.id=s.license_id WHERE s.user_id=%d AND s.status='active' AND l.status IN ('active','trial','grace') ORDER BY s.coverage_priority ASC,s.id ASC LIMIT 1",$user_id),ARRAY_A);
    }
}
