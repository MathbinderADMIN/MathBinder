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
    public static function enroll($class_id,$email,$role='student') {
        global $wpdb; $class_id=absint($class_id); $email=strtolower(sanitize_email($email)); $role=sanitize_key($role);
        if(!$class_id || !is_email($email) || !in_array($role,['student','teacher'],true)) return new WP_Error('enrollment_input','A valid class, email, and enrollment role are required.');
        $user=get_user_by('email',$email); $now=current_time('mysql',true); $uid=$user?(int)$user->ID:0; $status=$user?'active':'invited'; $source=$role==='student'?'teacher_invitation':'administrator'; $approver=get_current_user_id();
        $class=$wpdb->get_row($wpdb->prepare("SELECT c.name,c.section_name,c.class_code,c.organization_id,u.display_name AS teacher_name FROM {$wpdb->prefix}mb_classes c LEFT JOIN {$wpdb->users} u ON u.ID=c.teacher_user_id WHERE c.id=%d AND c.status='active'",$class_id),ARRAY_A);
        if(!$class) return new WP_Error('enrollment_class','The selected classroom is not active.');

        // A student without an account is an invitation, not an enrollment.
        // Keeping this record separate avoids all placeholder-user collisions.
        if(!$user && $role==='student'){
            $existing_invite=$wpdb->get_row($wpdb->prepare("SELECT id FROM {$wpdb->prefix}mb_student_invitations WHERE class_id=%d AND invited_email=%s",$class_id,$email),ARRAY_A);
            $invite_data=['status'=>'invited','invited_by'=>$approver,'updated_at'=>$now,'accepted_at'=>null];
            if($existing_invite) $ok=$wpdb->update($wpdb->prefix.'mb_student_invitations',$invite_data,['id'=>(int)$existing_invite['id']],['%s','%d','%s','%s'],['%d']);
            else { $invite_data['class_id']=$class_id; $invite_data['invited_email']=$email; $invite_data['created_at']=$now; $ok=$wpdb->insert($wpdb->prefix.'mb_student_invitations',$invite_data,['%s','%d','%s','%s','%d','%s','%s']); }
            if($ok===false) return new WP_Error('student_invitation_save','The pending student invitation could not be saved.');
            $destination=add_query_arg(['account_type'=>'student','class_code'=>$class['class_code']],home_url('/sign-up/'));
            $subject='You are invited to a MathBinder class';
            $message=sprintf("%s has invited you to join %s in MathBinder.\n\nOpen the invitation and create your own password:\n%s\n\nYour enrollment remains pending until you create your password and sign in for the first time.",$class['teacher_name']?:'Your teacher',trim($class['name'].($class['section_name']?' · '.$class['section_name']:'')),esc_url_raw($destination));
            $mail_sent=wp_mail($email,$subject,$message);
            MathBinder_Audit_Log::record('invite_student','class',$class_id,['email'=>$email,'status'=>'invited','email_sent'=>(bool)$mail_sent]);
            return ['status'=>'invited','user_id'=>0,'email_sent'=>$mail_sent];
        }
        $existing=$uid
            ? $wpdb->get_row($wpdb->prepare("SELECT id FROM {$wpdb->prefix}mb_enrollments WHERE class_id=%d AND role_key=%s AND (user_id=%d OR invited_email=%s) ORDER BY id ASC LIMIT 1",$class_id,$role,$uid,$email),ARRAY_A)
            : $wpdb->get_row($wpdb->prepare("SELECT id FROM {$wpdb->prefix}mb_enrollments WHERE class_id=%d AND role_key=%s AND invited_email=%s ORDER BY id ASC LIMIT 1",$class_id,$role,$email),ARRAY_A);
        $data=['user_id'=>$uid,'invited_email'=>$email,'role_key'=>$role,'status'=>$status,'source'=>$source,'approved_by'=>$approver,'updated_at'=>$now];
        if($existing) $ok=$wpdb->update($wpdb->prefix.'mb_enrollments',$data,['id'=>(int)$existing['id']],['%d','%s','%s','%s','%s','%d','%s'],['%d']);
        else { $data['class_id']=$class_id; $data['created_at']=$now; $ok=$wpdb->insert($wpdb->prefix.'mb_enrollments',$data,['%d','%s','%s','%s','%s','%d','%s','%d','%s']); }
        if($ok===false)return new WP_Error('enrollment_save','The student enrollment could not be saved.');
        if($user) { MathBinder_Identity_Service::assign_role($uid,$role,'class',$class_id); if($role==='student'){ $organization_id=absint($wpdb->get_var($wpdb->prepare("SELECT organization_id FROM {$wpdb->prefix}mb_classes WHERE id=%d",$class_id))); MathBinder_Verification_Service::authorize_minor($uid,0,'','school',$organization_id); } }
        $mail_sent=null;
        if($role==='student'){
            if($class){
                $destination=$user ? add_query_arg('class_id',$class_id,home_url('/student-dashboard/')).'#mb-student-classroom' : add_query_arg(['account_type'=>'student','class_code'=>$class['class_code']],home_url('/sign-up/'));
                $subject=$user ? 'You were added to a MathBinder class' : 'You are invited to a MathBinder class';
                $message=sprintf("%s has %s you %s %s in MathBinder.\n\n%s\n%s\n\n%s", $class['teacher_name']?:'Your teacher', $user?'added':'invited', $user?'to':'to join', trim($class['name'].($class['section_name']?' · '.$class['section_name']:'')), $user?'Open your class:':'Open the invitation and create your own password:', esc_url_raw($destination), $user?'Use your existing MathBinder login.':'Your enrollment remains pending until you create your password and sign in for the first time.');
                $mail_sent=wp_mail($email,$subject,$message);
            }
        }
        MathBinder_Audit_Log::record('enroll_user','class',$class_id,['user_id'=>$uid,'email'=>$email,'status'=>$status,'coverage_candidate'=>$role==='student','email_sent'=>$mail_sent]);
        return ['status'=>$status,'user_id'=>$uid,'email_sent'=>$mail_sent];
    }
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
        return $wpdb->get_row($wpdb->prepare("SELECT s.*,l.organization_id,l.plan_key,l.status AS license_status,l.trial_ends_at,l.grace_ends_at,l.renews_at,l.canceled_at FROM {$wpdb->prefix}mb_seat_allocations s JOIN {$wpdb->prefix}mb_licenses l ON l.id=s.license_id WHERE s.user_id=%d AND s.status='active' AND l.status IN ('active','trial','grace') ORDER BY s.coverage_priority ASC,s.id ASC LIMIT 1",$user_id),ARRAY_A);
    }
}
