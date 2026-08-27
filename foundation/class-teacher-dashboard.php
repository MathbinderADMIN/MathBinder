<?php
if (!defined('ABSPATH')) exit;

final class MathBinder_Teacher_Dashboard {
    const SHORTCODE = 'mathbinder_teacher_dashboard';
    const PAGE_SLUG = 'teacher-dashboard';
    const CLASS_PAGE_SLUG = 'teacher-class';

    public static function register() {
        add_shortcode(self::SHORTCODE, [__CLASS__, 'shortcode']);
        add_action('wp_enqueue_scripts', [__CLASS__, 'enqueue_assets'], 30);
        add_action('admin_post_mb_teacher_evidence_review', [__CLASS__, 'handle_evidence_review']);
        add_action('admin_post_mb_teacher_mastery_path', [__CLASS__, 'handle_mastery_path']);
        add_action('admin_post_mb_teacher_canvas_prepare', [__CLASS__, 'handle_canvas_prepare']);
        add_action('admin_post_mb_teacher_progress_export', [__CLASS__, 'handle_progress_export']);
        add_action('admin_post_mb_teacher_create_class', [__CLASS__, 'handle_create_class']);
        add_action('admin_post_mb_teacher_edit_class', [__CLASS__, 'handle_edit_class']);
        add_action('admin_post_mb_teacher_invite_student', [__CLASS__, 'handle_invite_student']);
        add_action('admin_post_mb_teacher_bulk_invite_students', [__CLASS__, 'handle_bulk_invite_students']);
        add_action('admin_post_mb_teacher_resend_student_invitation', [__CLASS__, 'handle_resend_student_invitation']);
        add_action('admin_post_mb_teacher_remove_student_invitation', [__CLASS__, 'handle_remove_student_invitation']);
        add_action('admin_post_mb_teacher_remove_student', [__CLASS__, 'handle_remove_student']);
        add_action('admin_post_mb_teacher_class_status', [__CLASS__, 'handle_class_status']);
        add_action('wp_ajax_mb_generate_mastery_path', [__CLASS__, 'ajax_generate_mastery_path']);
    }

    public static function ensure_page() {
        $page = get_page_by_path(self::PAGE_SLUG, OBJECT, 'page');
        $data = ['post_type'=>'page','post_status'=>'publish','post_title'=>'Teacher Dashboard','post_name'=>self::PAGE_SLUG,'post_content'=>'['.self::SHORTCODE.']'];
        if ($page) $data['ID'] = $page->ID;
        wp_insert_post($data);
        $class_page = get_page_by_path(self::CLASS_PAGE_SLUG, OBJECT, 'page');
        $class_data = ['post_type'=>'page','post_status'=>'publish','post_title'=>'Class Workspace','post_name'=>self::CLASS_PAGE_SLUG,'post_content'=>'['.self::SHORTCODE.']'];
        if ($class_page) $class_data['ID'] = $class_page->ID;
        wp_insert_post($class_data);
    }

    public static function enqueue_assets() {
        if (is_page([self::PAGE_SLUG,self::CLASS_PAGE_SLUG])) wp_enqueue_style('mathbinder-teacher-dashboard', plugins_url('assets/teacher-dashboard.css', __FILE__), [], MathBinder_Core::VERSION);
        if (is_page([self::PAGE_SLUG,self::CLASS_PAGE_SLUG])) wp_enqueue_style('mathbinder-teacher-evidence-review', plugins_url('assets/teacher-evidence-review.css', __FILE__), ['mathbinder-teacher-dashboard'], MathBinder_Core::VERSION);
        if (is_page([self::PAGE_SLUG,self::CLASS_PAGE_SLUG])) wp_enqueue_style('mathbinder-teacher-mastery-paths', plugins_url('assets/teacher-mastery-paths.css', __FILE__), ['mathbinder-teacher-dashboard'], MathBinder_Core::VERSION);
        if (is_page([self::PAGE_SLUG,self::CLASS_PAGE_SLUG])) {
            wp_enqueue_script('mathbinder-teacher-mastery-paths', plugins_url('assets/teacher-mastery-paths.js', __FILE__), [], MathBinder_Core::VERSION, true);
            wp_localize_script('mathbinder-teacher-mastery-paths', 'MathBinderMasteryAI', [
                'ajaxUrl' => admin_url('admin-ajax.php'),
                'nonce' => wp_create_nonce('mb_generate_mastery_path'),
            ]);
        }
        if (is_page([self::PAGE_SLUG,self::CLASS_PAGE_SLUG])) wp_enqueue_style('mathbinder-teacher-canvas', plugins_url('assets/teacher-canvas.css', __FILE__), ['mathbinder-teacher-dashboard'], MathBinder_Core::VERSION);
        if (is_page([self::PAGE_SLUG,self::CLASS_PAGE_SLUG])) wp_enqueue_script('mathbinder-teacher-dashboard', plugins_url('assets/teacher-dashboard.js', __FILE__), [], MathBinder_Core::VERSION, true);
        if (is_page([self::PAGE_SLUG,self::CLASS_PAGE_SLUG])) wp_enqueue_style('mathbinder-teacher-dashboard-complete', plugins_url('assets/teacher-dashboard-complete.css', __FILE__), ['mathbinder-teacher-dashboard'], MathBinder_Core::VERSION);
        if (is_page('evidence-folder')) wp_enqueue_style('mathbinder-student-teacher-review', plugins_url('assets/student-teacher-review.css', __FILE__), [], MathBinder_Core::VERSION);
    }

    private static function can_view() {
        $user = wp_get_current_user();
        return $user->exists() && (in_array('mb_teacher', (array)$user->roles, true) || in_array('mb_school_admin', (array)$user->roles, true) || in_array('mb_class_staff', (array)$user->roles, true) || MathBinder_Class_Staff::has_any_access($user->ID) || user_can($user, 'manage_options'));
    }

    private static function classes($teacher_id) {
        global $wpdb;
        if (user_can($teacher_id, 'manage_options') || user_can($teacher_id, MathBinder_Capabilities::MANAGE_ORGANIZATIONS)) {
            return $wpdb->get_results("SELECT * FROM {$wpdb->prefix}mb_classes WHERE status='active' ORDER BY name,section_name", ARRAY_A) ?: [];
        }
        $now = current_time('mysql', true);
        return $wpdb->get_results($wpdb->prepare("SELECT DISTINCT c.* FROM {$wpdb->prefix}mb_classes c LEFT JOIN {$wpdb->prefix}mb_enrollments e ON e.class_id=c.id AND e.user_id=%d AND e.role_key='teacher' AND e.status='active' LEFT JOIN {$wpdb->prefix}mb_class_staff_access s ON s.class_id=c.id AND s.user_id=%d AND s.status='active' AND (s.starts_at IS NULL OR s.starts_at<=%s) AND (s.expires_at IS NULL OR s.expires_at>=%s) WHERE c.status='active' AND (c.teacher_user_id=%d OR e.id IS NOT NULL OR s.id IS NOT NULL) ORDER BY c.name,c.section_name", $teacher_id, $teacher_id, $now, $now, $teacher_id), ARRAY_A) ?: [];
    }

    private static function teacher_can_manage_class($teacher_id, $class_id) {
        return MathBinder_Class_Staff::primary_can_manage($teacher_id, $class_id) || MathBinder_Class_Staff::can($teacher_id, $class_id, 'edit_class_settings');
    }

    private static function class_profiles() {
        $profiles = get_option('mb_teacher_class_profiles_v1', []);
        return is_array($profiles) ? $profiles : [];
    }

    private static function teacher_organizations($teacher_id) {
        global $wpdb;
        if (user_can($teacher_id, 'manage_options') || user_can($teacher_id, MathBinder_Capabilities::MANAGE_ORGANIZATIONS)) {
            return MathBinder_Organization_Service::organizations();
        }
        return $wpdb->get_results($wpdb->prepare("SELECT DISTINCT o.* FROM {$wpdb->prefix}mb_organizations o LEFT JOIN {$wpdb->prefix}mb_classes c ON c.organization_id=o.id LEFT JOIN {$wpdb->prefix}mb_enrollments e ON e.class_id=c.id AND e.user_id=%d AND e.role_key='teacher' WHERE o.status='active' AND (o.owner_user_id=%d OR c.teacher_user_id=%d OR e.id IS NOT NULL) ORDER BY o.name", $teacher_id, $teacher_id, $teacher_id), ARRAY_A) ?: [];
    }

    private static function ensure_independent_workspace($teacher_id) {
        global $wpdb;
        $organizations = self::teacher_organizations($teacher_id);
        if ($organizations) return (int)$organizations[0]['id'];
        $user = get_userdata($teacher_id);
        $name = ($user && $user->display_name ? $user->display_name : 'Independent Teacher') . ' Classroom';
        $organization_id = MathBinder_Organization_Service::create_organization($name, 'independent_teacher');
        return is_wp_error($organization_id) ? $organization_id : (int)$organization_id;
    }

    private static function active_terms($organization_id) {
        global $wpdb;
        return $wpdb->get_results($wpdb->prepare("SELECT * FROM {$wpdb->prefix}mb_terms WHERE organization_id=%d AND status='active' ORDER BY starts_on DESC,id DESC", absint($organization_id)), ARRAY_A) ?: [];
    }

    public static function handle_create_class() {
        if (!is_user_logged_in() || !self::can_view()) wp_die('Teacher access required.', 'Teacher access required', ['response'=>403]);
        $current = wp_get_current_user();
        if (in_array('mb_class_staff',(array)$current->roles,true) && !in_array('mb_teacher',(array)$current->roles,true) && !in_array('mb_school_admin',(array)$current->roles,true) && !user_can($current,'manage_options')) wp_die('Delegated classroom staff cannot create new classrooms.', 'Permission required', ['response'=>403]);
        check_admin_referer('mb_teacher_create_class', 'mb_teacher_class_nonce');
        $teacher_id = get_current_user_id();
        $name = isset($_POST['class_name']) ? sanitize_text_field(wp_unslash($_POST['class_name'])) : '';
        $section = isset($_POST['section_name']) ? sanitize_text_field(wp_unslash($_POST['section_name'])) : '';
        $subject = isset($_POST['subject']) ? sanitize_text_field(wp_unslash($_POST['subject'])) : '';
        $grade = isset($_POST['grade_level']) ? sanitize_text_field(wp_unslash($_POST['grade_level'])) : '';
        $school_year = isset($_POST['school_year']) ? sanitize_text_field(wp_unslash($_POST['school_year'])) : '';
        $enrollment = isset($_POST['enrollment_mode']) ? sanitize_key(wp_unslash($_POST['enrollment_mode'])) : 'code';
        if (!in_array($enrollment, ['code','approval','closed'], true)) $enrollment = 'code';
        if ($name === '' || $subject === '' || $grade === '' || $school_year === '') {
            wp_safe_redirect(add_query_arg('class_notice', 'invalid', home_url('/'.self::PAGE_SLUG.'/')).'#classes'); exit;
        }
        $organization_id = isset($_POST['organization_id']) ? absint($_POST['organization_id']) : 0;
        $allowed_organizations = array_map('absint', wp_list_pluck(self::teacher_organizations($teacher_id), 'id'));
        if (!$organization_id) {
            $organization_id = self::ensure_independent_workspace($teacher_id);
            if (is_wp_error($organization_id)) wp_die(esc_html($organization_id->get_error_message()), 'Classroom setup failed', ['response'=>400]);
        } elseif (!in_array($organization_id, $allowed_organizations, true)) {
            wp_die('This organization is not available in your teacher workspace.', 'Organization unavailable', ['response'=>403]);
        }
        $term_id = isset($_POST['term_id']) ? absint($_POST['term_id']) : 0;
        $valid_term = false;
        foreach (self::active_terms($organization_id) as $term) if ((int)$term['id'] === $term_id) $valid_term = true;
        if (!$valid_term) $term_id = MathBinder_Organization_Service::create_term($organization_id, $school_year);
        $class_id = MathBinder_Organization_Service::create_class($organization_id, $term_id, $name, $section, $teacher_id);
        if (!$class_id) wp_die('The classroom could not be created.', 'Classroom setup failed', ['response'=>500]);
        MathBinder_Organization_Service::enroll($class_id, wp_get_current_user()->user_email, 'teacher');
        $profiles = self::class_profiles();
        $profiles[(string)$class_id] = ['subject'=>$subject,'grade_level'=>$grade,'school_year'=>$school_year,'enrollment_mode'=>$enrollment,'created_by'=>$teacher_id,'updated_at'=>current_time('mysql', true)];
        update_option('mb_teacher_class_profiles_v1', $profiles, false);
        wp_safe_redirect(add_query_arg(['class_notice'=>'created','class_id'=>$class_id], home_url('/'.self::PAGE_SLUG.'/')).'#classes'); exit;
    }

    public static function handle_invite_student() {
        if (!is_user_logged_in() || !self::can_view()) wp_die('Teacher access required.', 'Teacher access required', ['response'=>403]);
        check_admin_referer('mb_teacher_invite_student', 'mb_teacher_invite_nonce');
        $teacher_id = get_current_user_id();
        $class_id = isset($_POST['class_id']) ? absint($_POST['class_id']) : 0;
        $email = isset($_POST['student_email']) ? sanitize_email(wp_unslash($_POST['student_email'])) : '';
        if (!MathBinder_Class_Staff::can($teacher_id, $class_id, 'enroll_students') || !is_email($email)) {
            wp_safe_redirect(add_query_arg('class_notice', 'invite_invalid', home_url('/'.self::PAGE_SLUG.'/')).'#classes'); exit;
        }
        $result = MathBinder_Organization_Service::enroll($class_id, $email, 'student');
        if (is_wp_error($result)) $notice = 'invite_failed';
        elseif (empty($result['email_sent'])) $notice = $result['status']==='active' ? 'enrolled_email_failed' : 'invited_email_failed';
        else $notice = $result['status']==='active' ? 'enrolled' : 'invited';
        wp_safe_redirect(add_query_arg('class_notice', $notice, home_url('/'.self::PAGE_SLUG.'/')).'#classes'); exit;
    }

    public static function handle_bulk_invite_students() {
        if (!is_user_logged_in() || !self::can_view()) wp_die('Teacher access required.', 'Teacher access required', ['response'=>403]);
        check_admin_referer('mb_teacher_bulk_invite_students', 'mb_teacher_bulk_invite_nonce');
        $teacher_id=get_current_user_id(); $class_id=absint($_POST['class_id']??0);
        if(!MathBinder_Class_Staff::can($teacher_id,$class_id,'enroll_students')) wp_die('You cannot enroll students in this class.','Permission required',['response'=>403]);
        $emails=[]; $raw=isset($_POST['student_emails'])?(string)wp_unslash($_POST['student_emails']):'';
        foreach(preg_split('/[\s,;]+/',$raw,-1,PREG_SPLIT_NO_EMPTY) as $value){$email=sanitize_email($value);if(is_email($email))$emails[strtolower($email)]=$email;}
        if(!empty($_FILES['student_csv']['tmp_name']) && is_uploaded_file($_FILES['student_csv']['tmp_name'])){
            $handle=fopen($_FILES['student_csv']['tmp_name'],'r'); $rows=0;
            while($handle && ($row=fgetcsv($handle))!==false && $rows<250){$rows++; foreach($row as $cell){$email=sanitize_email(trim((string)$cell));if(is_email($email)){$emails[strtolower($email)]=$email;break;}}}
            if($handle)fclose($handle);
        }
        $emails=array_slice(array_values($emails),0,50); if(!$emails){wp_safe_redirect(add_query_arg('class_notice','bulk_empty',home_url('/'.self::PAGE_SLUG.'/')).'#enrollment');exit;}
        $saved=0;$mail_failed=0;$failed=0; foreach($emails as $email){$result=MathBinder_Organization_Service::enroll($class_id,$email,'student');if(is_wp_error($result))$failed++;else{$saved++;if(empty($result['email_sent']))$mail_failed++;}}
        $notice=$saved?($mail_failed?'bulk_saved_mail':'bulk_saved'):'bulk_failed';
        wp_safe_redirect(add_query_arg(['class_notice'=>$notice,'invite_count'=>$saved,'invite_failed'=>$failed],home_url('/'.self::PAGE_SLUG.'/')).'#enrollment');exit;
    }

    public static function handle_resend_student_invitation() {
        if (!is_user_logged_in() || !self::can_view()) wp_die('Teacher access required.', 'Teacher access required', ['response'=>403]);
        check_admin_referer('mb_teacher_resend_student_invitation','mb_resend_invitation_nonce');
        global $wpdb;
        $teacher_id=get_current_user_id(); $class_id=absint($_POST['class_id']??0); $invitation_id=absint($_POST['invitation_id']??0);
        $authorized=false; foreach(self::classes($teacher_id) as $class) if((int)$class['id']===$class_id){$authorized=true;break;}
        if(!$authorized || !$invitation_id) wp_die('You cannot manage invitations for this class.','Permission required',['response'=>403]);
        $invitation=$wpdb->get_row($wpdb->prepare(
            "SELECT i.*,c.name,c.section_name,c.class_code,u.display_name teacher_name
             FROM {$wpdb->prefix}mb_student_invitations i
             INNER JOIN {$wpdb->prefix}mb_classes c ON c.id=i.class_id AND c.status='active'
             LEFT JOIN {$wpdb->users} u ON u.ID=c.teacher_user_id
             WHERE i.id=%d AND i.class_id=%d AND i.status='invited'",
            $invitation_id,$class_id
        ),ARRAY_A);
        if(!$invitation) wp_die('That pending invitation is no longer available.','Invitation unavailable',['response'=>404]);
        $email=strtolower(sanitize_email($invitation['invited_email'])); $existing_user=get_user_by('email',$email); $class_label=trim($invitation['name'].($invitation['section_name']?' · '.$invitation['section_name']:''));
        if($existing_user){
            $login=MathBinder_Frontend_Auth::login_url(home_url('/student-dashboard/'));
            $reset=wp_lostpassword_url($login);
            $message=sprintf("%s has invited you to join %s in MathBinder.\n\nAn account already exists for this email address. Log in here:\n%s\n\nIf you do not know the password, reset it here:\n%s\n\nYour class enrollment will activate automatically after you sign in.",$invitation['teacher_name']?:'Your teacher',$class_label,esc_url_raw($login),esc_url_raw($reset));
        }else{
            $signup=add_query_arg(['account_type'=>'student','class_code'=>$invitation['class_code']],home_url('/sign-up/'));
            $message=sprintf("%s has invited you to join %s in MathBinder.\n\nOpen the invitation and create your own password:\n%s\n\nYour enrollment remains pending until you create your password and sign in for the first time.",$invitation['teacher_name']?:'Your teacher',$class_label,esc_url_raw($signup));
        }
        $sent=wp_mail($email,'Reminder: your MathBinder class invitation',$message);
        if($sent)$wpdb->update($wpdb->prefix.'mb_student_invitations',['updated_at'=>current_time('mysql',true),'invited_by'=>$teacher_id],['id'=>$invitation_id],['%s','%d'],['%d']);
        MathBinder_Audit_Log::record('resend_student_invitation','class',$class_id,['invitation_id'=>$invitation_id,'email'=>$email,'email_sent'=>(bool)$sent]);
        wp_safe_redirect(add_query_arg(['class_id'=>$class_id,'roster_notice'=>$sent?'resend_sent':'resend_failed'],home_url('/'.self::CLASS_PAGE_SLUG.'/')).'#students');exit;
    }

    public static function handle_remove_student_invitation() {
        if (!is_user_logged_in() || !self::can_view()) wp_die('Teacher access required.', 'Teacher access required', ['response'=>403]);
        check_admin_referer('mb_teacher_remove_student_invitation','mb_remove_invitation_nonce');
        global $wpdb;
        $teacher_id=get_current_user_id(); $class_id=absint($_POST['class_id']??0); $invitation_id=absint($_POST['invitation_id']??0);
        $authorized=false; foreach(self::classes($teacher_id) as $class) if((int)$class['id']===$class_id){$authorized=true;break;}
        if(!$authorized || !$invitation_id) wp_die('You cannot manage invitations for this class.','Permission required',['response'=>403]);
        $invitation=$wpdb->get_row($wpdb->prepare("SELECT id,invited_email FROM {$wpdb->prefix}mb_student_invitations WHERE id=%d AND class_id=%d AND status='invited'",$invitation_id,$class_id),ARRAY_A);
        if(!$invitation){wp_safe_redirect(add_query_arg(['class_id'=>$class_id,'roster_notice'=>'invitation_missing'],home_url('/'.self::CLASS_PAGE_SLUG.'/')).'#students');exit;}
        $updated=$wpdb->update($wpdb->prefix.'mb_student_invitations',['status'=>'cancelled','updated_at'=>current_time('mysql',true)],['id'=>$invitation_id],['%s','%s'],['%d']);
        if($updated===false){wp_safe_redirect(add_query_arg(['class_id'=>$class_id,'roster_notice'=>'invitation_remove_failed'],home_url('/'.self::CLASS_PAGE_SLUG.'/')).'#students');exit;}
        MathBinder_Audit_Log::record('remove_student_invitation','class',$class_id,['invitation_id'=>$invitation_id,'email'=>$invitation['invited_email'],'token_invalidated'=>true]);
        wp_safe_redirect(add_query_arg(['class_id'=>$class_id,'roster_notice'=>'invitation_removed'],home_url('/'.self::CLASS_PAGE_SLUG.'/')).'#students');exit;
    }

    public static function handle_remove_student() {
        if (!is_user_logged_in() || !self::can_view()) wp_die('Teacher access required.', 'Teacher access required', ['response'=>403]);
        check_admin_referer('mb_teacher_remove_student', 'mb_teacher_remove_nonce');
        $teacher_id = get_current_user_id();
        $class_id = isset($_POST['class_id']) ? absint($_POST['class_id']) : 0;
        $student_id = isset($_POST['student_id']) ? absint($_POST['student_id']) : 0;
        if (!$class_id || !$student_id || !MathBinder_Class_Staff::can($teacher_id, $class_id, 'remove_students')) {
            wp_safe_redirect(add_query_arg('roster_notice', 'remove_denied', home_url('/'.self::PAGE_SLUG.'/')).'#roster'); exit;
        }
        global $wpdb;
        $enrollment = $wpdb->get_row($wpdb->prepare("SELECT id FROM {$wpdb->prefix}mb_enrollments WHERE class_id=%d AND user_id=%d AND role_key='student' AND status='active'", $class_id, $student_id), ARRAY_A);
        if (!$enrollment) {
            wp_safe_redirect(add_query_arg('roster_notice', 'remove_missing', home_url('/'.self::PAGE_SLUG.'/')).'#roster'); exit;
        }
        $updated = $wpdb->update($wpdb->prefix.'mb_enrollments', ['status'=>'removed','updated_at'=>current_time('mysql', true)], ['id'=>(int)$enrollment['id']], ['%s','%s'], ['%d']);
        if ($updated === false) {
            wp_safe_redirect(add_query_arg('roster_notice', 'remove_failed', home_url('/'.self::PAGE_SLUG.'/')).'#roster'); exit;
        }
        MathBinder_Audit_Log::record('remove_student', 'enrollment', (int)$enrollment['id'], ['class_id'=>$class_id,'student_id'=>$student_id,'preserved_account'=>true,'preserved_student_work'=>true], 'class', $class_id);
        wp_safe_redirect(add_query_arg('roster_notice', 'removed', home_url('/'.self::PAGE_SLUG.'/')).'#roster'); exit;
    }

    public static function handle_edit_class() {
        if (!is_user_logged_in() || !self::can_view()) wp_die('Teacher access required.', 'Teacher access required', ['response'=>403]);
        check_admin_referer('mb_teacher_edit_class', 'mb_teacher_edit_class_nonce');
        $teacher_id = get_current_user_id();
        $class_id = isset($_POST['class_id']) ? absint($_POST['class_id']) : 0;
        $return_to_class = !empty($_POST['return_to_class']);
        if (!self::teacher_can_manage_class($teacher_id, $class_id)) wp_die('You do not have permission to edit this class.', 'Permission required', ['response'=>403]);

        global $wpdb;
        $class = $wpdb->get_row($wpdb->prepare("SELECT * FROM {$wpdb->prefix}mb_classes WHERE id=%d", $class_id), ARRAY_A);
        if (!$class) wp_die('The class could not be found.', 'Class unavailable', ['response'=>404]);

        $name = isset($_POST['class_name']) ? sanitize_text_field(wp_unslash($_POST['class_name'])) : '';
        $section = isset($_POST['section_name']) ? sanitize_text_field(wp_unslash($_POST['section_name'])) : '';
        $subject = isset($_POST['subject']) ? sanitize_text_field(wp_unslash($_POST['subject'])) : '';
        $grade = isset($_POST['grade_level']) ? sanitize_text_field(wp_unslash($_POST['grade_level'])) : '';
        $school_year = isset($_POST['school_year']) ? sanitize_text_field(wp_unslash($_POST['school_year'])) : '';
        $enrollment = isset($_POST['enrollment_mode']) ? sanitize_key(wp_unslash($_POST['enrollment_mode'])) : 'code';
        $status = isset($_POST['class_status']) ? sanitize_key(wp_unslash($_POST['class_status'])) : 'active';
        $term_id = isset($_POST['term_id']) ? absint($_POST['term_id']) : (int)$class['term_id'];
        if ($name === '' || $subject === '' || $grade === '' || $school_year === '' || !in_array($enrollment, ['code','approval','closed'], true) || !in_array($status, ['active','inactive','archived'], true)) {
            if ($return_to_class) { wp_safe_redirect(add_query_arg(['class_id'=>$class_id,'class_notice'=>'edit_invalid'],home_url('/'.self::CLASS_PAGE_SLUG.'/')).'#settings'); exit; }
            wp_safe_redirect(add_query_arg(['class_notice'=>'edit_invalid','edit_class'=>$class_id], home_url('/'.self::PAGE_SLUG.'/')).'#class-'.$class_id); exit;
        }
        $valid_term = false;
        foreach (self::active_terms((int)$class['organization_id']) as $term) if ((int)$term['id'] === $term_id) $valid_term = true;
        if (!$valid_term) $term_id = (int)$class['term_id'];

        $ok = $wpdb->update($wpdb->prefix.'mb_classes', [
            'name'=>$name, 'section_name'=>$section, 'term_id'=>$term_id,
            'status'=>$status, 'updated_at'=>current_time('mysql', true)
        ], ['id'=>$class_id], ['%s','%s','%d','%s','%s'], ['%d']);
        if ($ok === false) wp_die('The class changes could not be saved.', 'Class update failed', ['response'=>500]);

        $profiles = self::class_profiles();
        $existing = isset($profiles[(string)$class_id]) && is_array($profiles[(string)$class_id]) ? $profiles[(string)$class_id] : [];
        $profiles[(string)$class_id] = array_merge($existing, ['subject'=>$subject,'grade_level'=>$grade,'school_year'=>$school_year,'enrollment_mode'=>$enrollment,'updated_by'=>$teacher_id,'updated_at'=>current_time('mysql', true)]);
        update_option('mb_teacher_class_profiles_v1', $profiles, false);
        MathBinder_Audit_Log::record('edit_class','class',$class_id,['name'=>$name,'term_id'=>$term_id,'status'=>$status]);
        if ($return_to_class && $status === 'active') { wp_safe_redirect(add_query_arg(['class_id'=>$class_id,'class_notice'=>'updated'],home_url('/'.self::CLASS_PAGE_SLUG.'/')).'#settings'); exit; }
        wp_safe_redirect(add_query_arg('class_notice', $status === 'active' ? 'updated' : 'status_updated', home_url('/'.self::PAGE_SLUG.'/')).'#classes'); exit;
    }

    public static function handle_class_status() {
        if (!is_user_logged_in() || !self::can_view()) wp_die('Teacher access required.', 'Teacher access required', ['response'=>403]);
        check_admin_referer('mb_teacher_class_status', 'mb_teacher_class_status_nonce');
        $teacher_id = get_current_user_id();
        $class_id = isset($_POST['class_id']) ? absint($_POST['class_id']) : 0;
        if (!self::teacher_can_manage_class($teacher_id, $class_id)) wp_die('This class is not available in your teacher workspace.', 'Class unavailable', ['response'=>403]);
        MathBinder_Organization_Service::update_record_status('class', $class_id, 'archived');
        wp_safe_redirect(add_query_arg('class_notice', 'archived', home_url('/'.self::PAGE_SLUG.'/')).'#classes'); exit;
    }

    private static function students($classes) {
        global $wpdb;
        $ids = array_values(array_filter(array_map('absint', wp_list_pluck($classes, 'id'))));
        if (!$ids) return [];
        $placeholders = implode(',', array_fill(0, count($ids), '%d'));
        $sql = $wpdb->prepare("SELECT e.class_id,e.user_id,u.display_name,u.user_email,c.name AS class_name,c.section_name FROM {$wpdb->prefix}mb_enrollments e JOIN {$wpdb->users} u ON u.ID=e.user_id JOIN {$wpdb->prefix}mb_classes c ON c.id=e.class_id WHERE e.class_id IN ($placeholders) AND e.role_key='student' AND e.status='active' ORDER BY c.name,u.display_name", $ids);
        return $wpdb->get_results($sql, ARRAY_A) ?: [];
    }

    private static function authorized_student($teacher_id, $student_id, $permission = 'view_progress') {
        foreach (self::students(self::classes($teacher_id)) as $student) {
            if ((int)$student['user_id'] === (int)$student_id && MathBinder_Class_Staff::can($teacher_id, $student['class_id'], $permission)) return true;
        }
        return false;
    }

    public static function teacher_authorized_for_student($teacher_id, $student_id) {
        return self::can_view() && self::authorized_student(absint($teacher_id), absint($student_id), 'view_evidence');
    }

    private static function reviews($student_id) {
        $reviews = get_user_meta(absint($student_id), 'mb_teacher_evidence_reviews_v1', true);
        return is_array($reviews) ? $reviews : [];
    }

    private static function mastery_paths() {
        $paths = get_option('mb_teacher_mastery_paths_v1', []);
        return is_array($paths) ? $paths : [];
    }

    private static function teacher_paths($teacher_id) {
        return array_values(array_filter(self::mastery_paths(), function($path) use ($teacher_id) {
            if ((int)($path['teacher_id'] ?? 0) === (int)$teacher_id || user_can($teacher_id, 'manage_options')) return true;
            $type=(string)($path['target_type']??''); $target=absint($path['target_id']??0);
            if ($type==='class') return MathBinder_Class_Staff::can($teacher_id,$target,'view_progress');
            if ($type==='student') return self::authorized_student($teacher_id,$target,'view_progress');
            return false;
        }));
    }

    private static function find_teacher_path($teacher_id, $path_id) {
        foreach (self::teacher_paths($teacher_id) as $path) {
            if ((string)($path['id'] ?? '') === (string)$path_id) return $path;
        }
        return null;
    }

    private static function lessons() {
        return get_posts(['post_type'=>'mb_binder_page','post_status'=>'publish','numberposts'=>-1,'orderby'=>'title','order'=>'ASC']);
    }

    private static function mastery_generation_schema() {
        $strings = ['type'=>'array','items'=>['type'=>'string'],'minItems'=>1];
        $questions = ['type'=>'array','items'=>['type'=>'string'],'minItems'=>8,'maxItems'=>8];
        return [
            'type'=>'object','additionalProperties'=>false,
            'properties'=>[
                'verified'=>['type'=>'boolean'], 'verification_message'=>['type'=>'string'],
                'standard_code'=>['type'=>'string'], 'standard_text'=>['type'=>'string'],
                'grade_course'=>['type'=>'string'], 'domain'=>['type'=>'string'],
                'skills'=>$strings, 'objectives'=>['type'=>'string'], 'prerequisites'=>['type'=>'string'],
                'pretest_questions'=>$questions, 'foundational'=>['type'=>'string'],
                'developing'=>['type'=>'string'], 'near_mastery'=>['type'=>'string'],
                'extension'=>['type'=>'string'], 'evidence'=>['type'=>'string'],
                'posttest_questions'=>$questions, 'reassessment'=>['type'=>'string'],
                'extension_activity'=>['type'=>'string'], 'alignment_check'=>['type'=>'string']
            ],
            'required'=>['verified','verification_message','standard_code','standard_text','grade_course','domain','skills','objectives','prerequisites','pretest_questions','foundational','developing','near_mastery','extension','evidence','posttest_questions','reassessment','extension_activity','alignment_check']
        ];
    }

    public static function ajax_generate_mastery_path() {
        check_ajax_referer('mb_generate_mastery_path', 'nonce');
        if (!is_user_logged_in() || !self::can_view()) wp_send_json_error(['message'=>'Teacher access required.'], 403);
        if (!defined('MATHBINDER_OPENAI_API_KEY') || trim((string)MATHBINDER_OPENAI_API_KEY) === '') {
            wp_send_json_error(['message'=>'The secure AI connection is not configured.'], 503);
        }
        $title = isset($_POST['title']) ? sanitize_text_field(wp_unslash($_POST['title'])) : '';
        $standard = isset($_POST['standard']) ? sanitize_text_field(wp_unslash($_POST['standard'])) : '';
        $grade = isset($_POST['grade']) ? sanitize_text_field(wp_unslash($_POST['grade'])) : '';
        $allowed_grades = array_merge(['K'], array_map('strval', range(1, 12)));
        if ($title === '' || $standard === '' || !in_array($grade, $allowed_grades, true) || strlen($title) > 160 || strlen($standard) > 1000) {
            wp_send_json_error(['message'=>'Select a grade and enter a topic title plus one California mathematics standard code or its complete wording.'], 400);
        }
        $rate_key = 'mb_mastery_ai_' . get_current_user_id();
        $uses = absint(get_transient($rate_key));
        if ($uses >= 20) wp_send_json_error(['message'=>'The hourly generation limit has been reached. Please try again later.'], 429);
        set_transient($rate_key, $uses + 1, HOUR_IN_SECONDS);

        $instructions = <<<'PROMPT'
You are MathBinder's California K-12 mathematics curriculum generator. The authoritative content standards are the California Common Core State Standards for Mathematics, adopted August 2010 and modified January 2013. Apply the instructional guidance of California's State Board-adopted 2023 Mathematics Framework and the eight Standards for Mathematical Practice. First verify the supplied code and wording. Accept California-specific additions and high-school conceptual-category codes. Never infer a different standard from only the topic title. If the code is unknown, incomplete, ambiguous, non-mathematics, or conflicts with supplied wording, set verified=false, explain exactly what the teacher must correct, and return empty strings plus eight empty strings for each question array; do not generate generic content. If verified, preserve the exact standard code and faithful wording, identify grade/course and domain, decompose the standard into assessable skills, and generate a coherent mastery path. Pretest and posttest must each contain exactly eight complete, student-ready questions with sufficient numbers/data/context to solve; use parallel skills and difficulty but materially different values and contexts. Cover every skill in the standard, not neighboring standards. Include conceptual understanding, procedural fluency, application, reasoning, representation, and at least one error-analysis item when appropriate. Do not include answers in student questions. Assignments must state concrete student work, quantity, representations, and success evidence—not generic directions. Differentiate foundational, developing, near-mastery, and extension routes. The standard controls all content. Use plain text suitable for editable WordPress textareas. Do not publish; a teacher reviews everything.
PROMPT;
        $payload = [
            'model'=>'gpt-5.6-luna','store'=>false,
            'safety_identifier'=>hash('sha256', wp_salt('auth').'teacher-'.get_current_user_id()),
            'instructions'=>$instructions,
            'input'=>[[ 'role'=>'user', 'content'=>[[ 'type'=>'input_text', 'text'=>"Teacher-selected grade: {$grade}\nTopic or unit title: {$title}\nCalifornia mathematics standard supplied by teacher: {$standard}\nVerify that the selected grade is compatible with the standard. For a high-school conceptual-category standard, use the selected grade as the instructional difficulty and context. If a K-8 grade-specific standard conflicts with the selected grade, set verified=false." ]] ]],
            'text'=>['format'=>['type'=>'json_schema','name'=>'mathbinder_ca_mastery_path','strict'=>true,'schema'=>self::mastery_generation_schema()]]
        ];
        $response = wp_remote_post('https://api.openai.com/v1/responses', [
            'timeout'=>90,
            'headers'=>['Authorization'=>'Bearer '.trim((string)MATHBINDER_OPENAI_API_KEY),'Content-Type'=>'application/json'],
            'body'=>wp_json_encode($payload)
        ]);
        if (is_wp_error($response)) wp_send_json_error(['message'=>'MathBinder could not reach the generation service. Please try again.'], 502);
        $body = json_decode(wp_remote_retrieve_body($response), true);
        if (wp_remote_retrieve_response_code($response) >= 300) wp_send_json_error(['message'=>'The generation service could not complete this request.'], 502);
        $text = '';
        foreach (($body['output'] ?? []) as $output) foreach (($output['content'] ?? []) as $item) if (($item['type'] ?? '') === 'output_text') $text .= (string)($item['text'] ?? '');
        $draft = json_decode($text, true);
        if (!is_array($draft) || !array_key_exists('verified', $draft)) wp_send_json_error(['message'=>'The generated draft could not be validated. Please try again.'], 502);
        if (!$draft['verified']) wp_send_json_error(['message'=>sanitize_text_field($draft['verification_message'] ?? 'The standard could not be verified.')], 422);
        wp_send_json_success(['draft'=>$draft]);
    }

    public static function handle_canvas_prepare() {
        if (!is_user_logged_in() || !self::can_view()) wp_die('Teacher access required.', 'Teacher access required', ['response'=>403]);
        check_admin_referer('mb_teacher_canvas_prepare', 'mb_canvas_prepare_nonce');
        $teacher_id = get_current_user_id();
        $path_id = isset($_POST['path_id']) ? sanitize_text_field(wp_unslash($_POST['path_id'])) : '';
        $path = null;
        foreach (self::teacher_paths($teacher_id) as $candidate) if ((string)($candidate['id'] ?? '') === $path_id) { $path = $candidate; break; }
        if (!$path) wp_die('This mastery path is not available in your teacher workspace.', 'Mastery path unavailable', ['response'=>403]);
        if (($path['target_type']??'')==='class' && !MathBinder_Class_Staff::can($teacher_id,absint($path['target_id']??0),'assign_lessons')) wp_die('Your delegated access does not include assignment preparation.', 'Permission required', ['response'=>403]);
        $result = MathBinder_Canvas_Integration::prepare_assignment($path, $teacher_id);
        if (is_wp_error($result)) wp_die(esc_html($result->get_error_message()), 'Canvas preparation failed', ['response'=>400]);
        MathBinder_Audit_Log::record('prepare', 'canvas_assignment', $path_id, ['target_type'=>$path['target_type'],'target_id'=>$path['target_id']]);
        wp_safe_redirect(add_query_arg('canvas_notice', 'prepared', home_url('/'.self::PAGE_SLUG.'/')).'#canvas'); exit;
    }

    public static function handle_mastery_path() {
        if (!is_user_logged_in() || !self::can_view()) wp_die('Teacher access required.', 'Teacher access required', ['response'=>403]);
        check_admin_referer('mb_teacher_mastery_path', 'mb_mastery_path_nonce');
        $teacher_id = get_current_user_id();
        $path_id = isset($_POST['path_id']) ? sanitize_text_field(wp_unslash($_POST['path_id'])) : '';
        $save_mode = isset($_POST['save_mode']) ? sanitize_key(wp_unslash($_POST['save_mode'])) : 'draft';
        if (!in_array($save_mode, ['draft','published'], true)) $save_mode = 'draft';
        $existing = $path_id !== '' ? self::find_teacher_path($teacher_id, $path_id) : null;
        if ($path_id !== '' && !$existing) wp_die('This mastery path is not available in your teacher workspace.', 'Mastery path unavailable', ['response'=>403]);
        $title = isset($_POST['path_title']) ? sanitize_text_field(wp_unslash($_POST['path_title'])) : '';
        $standard = isset($_POST['target_standard']) ? sanitize_text_field(wp_unslash($_POST['target_standard'])) : '';
        $grade_level = isset($_POST['mastery_grade_level']) ? sanitize_text_field(wp_unslash($_POST['mastery_grade_level'])) : '';
        $objectives = isset($_POST['objectives']) ? sanitize_textarea_field(wp_unslash($_POST['objectives'])) : '';
        $prerequisites = isset($_POST['prerequisites']) ? sanitize_textarea_field(wp_unslash($_POST['prerequisites'])) : '';
        $pretest_title = isset($_POST['pretest_title']) ? sanitize_text_field(wp_unslash($_POST['pretest_title'])) : '';
        $pretest_instructions = isset($_POST['pretest_instructions']) ? sanitize_textarea_field(wp_unslash($_POST['pretest_instructions'])) : '';
        $pretest_url = isset($_POST['pretest_url']) ? esc_url_raw(wp_unslash($_POST['pretest_url'])) : '';
        $evidence_requirements = isset($_POST['evidence_requirements']) ? sanitize_textarea_field(wp_unslash($_POST['evidence_requirements'])) : '';
        $posttest_title = isset($_POST['posttest_title']) ? sanitize_text_field(wp_unslash($_POST['posttest_title'])) : '';
        $posttest_instructions = isset($_POST['posttest_instructions']) ? sanitize_textarea_field(wp_unslash($_POST['posttest_instructions'])) : '';
        $posttest_url = isset($_POST['posttest_url']) ? esc_url_raw(wp_unslash($_POST['posttest_url'])) : '';
        $reassessment = isset($_POST['reassessment']) ? sanitize_textarea_field(wp_unslash($_POST['reassessment'])) : '';
        $extension_activity = isset($_POST['extension_activity']) ? sanitize_textarea_field(wp_unslash($_POST['extension_activity'])) : '';
        $threshold = isset($_POST['mastery_threshold']) ? absint($_POST['mastery_threshold']) : 80;
        $target = isset($_POST['target']) ? sanitize_text_field(wp_unslash($_POST['target'])) : '';
        $target_parts = explode(':', $target, 2);
        $target_type = sanitize_key($target_parts[0] ?? '');
        $target_id = absint($target_parts[1] ?? 0);
        $due_date = isset($_POST['due_date']) ? sanitize_text_field(wp_unslash($_POST['due_date'])) : '';
        $lesson_order = isset($_POST['lesson_order']) ? sanitize_text_field(wp_unslash($_POST['lesson_order'])) : '';
        $lesson_ids = array_values(array_unique(array_filter(array_map('absint', explode(',', $lesson_order)))));
        $branches = [];
        foreach (['foundational','developing','near_mastery','extension'] as $branch) $branches[$branch] = isset($_POST[$branch]) ? sanitize_textarea_field(wp_unslash($_POST[$branch])) : '';
        $valid_target = false;
        if ($target_type === 'class') $valid_target = MathBinder_Class_Staff::can($teacher_id, $target_id, 'assign_lessons') && (!$due_date || MathBinder_Class_Staff::can($teacher_id,$target_id,'manage_due_dates'));
        if ($target_type === 'student') $valid_target = self::authorized_student($teacher_id, $target_id, 'assign_lessons');
        $valid_due_date = $due_date === '' || (bool)preg_match('/^\d{4}-\d{2}-\d{2}$/', $due_date);
        $allowed_mastery_grades = array_merge(['K'], array_map('strval', range(1, 12)));
        $publish_fields = [$title,$standard,$grade_level,$objectives,$prerequisites,$pretest_title,$pretest_instructions,$evidence_requirements,$posttest_title,$posttest_instructions,$reassessment];
        if ($title === '' || $standard === '' || !in_array($grade_level, $allowed_mastery_grades, true) || !$valid_due_date || $threshold < 1 || $threshold > 100 || ($save_mode === 'published' && (in_array('', $publish_fields, true) || in_array('', $branches, true) || !$valid_target || !$lesson_ids))) {
            wp_safe_redirect(add_query_arg('path_notice', 'invalid', home_url('/'.self::PAGE_SLUG.'/')).'#mastery-paths'); exit;
        }
        $published_lessons = $lesson_ids ? get_posts(['post_type'=>'mb_binder_page','post_status'=>'publish','post__in'=>$lesson_ids,'numberposts'=>-1,'fields'=>'ids']) : [];
        if ($lesson_ids && count($published_lessons) !== count($lesson_ids)) {
            wp_safe_redirect(add_query_arg('path_notice', 'invalid', home_url('/'.self::PAGE_SLUG.'/')).'#mastery-paths'); exit;
        }
        $paths = self::mastery_paths(); if ($path_id === '') $path_id = wp_generate_uuid4();
        $created_at = $existing['created_at'] ?? current_time('mysql', true);
        $paths[$path_id] = ['id'=>$path_id,'teacher_id'=>$teacher_id,'teacher_name'=>wp_get_current_user()->display_name,'title'=>$title,'standard'=>$standard,'objectives'=>$objectives,'prerequisites'=>$prerequisites,'pretest'=>['title'=>$pretest_title,'instructions'=>$pretest_instructions,'url'=>$pretest_url],'mastery_threshold'=>$threshold,'target_type'=>$target_type,'target_id'=>$target_id,'due_date'=>$due_date,'lesson_ids'=>$lesson_ids,'branches'=>$branches,'evidence_requirements'=>$evidence_requirements,'posttest'=>['title'=>$posttest_title,'instructions'=>$posttest_instructions,'url'=>$posttest_url],'reassessment'=>$reassessment,'extension_activity'=>$extension_activity,'status'=>$save_mode,'created_at'=>$created_at,'updated_at'=>current_time('mysql', true)];
        $paths[$path_id]['grade_level'] = $grade_level;
        update_option('mb_teacher_mastery_paths_v1', $paths, false);
        MathBinder_Audit_Log::record($existing ? 'update' : 'create', 'teacher_mastery_path', $target_id, ['path_id'=>$path_id,'target_type'=>$target_type,'mastery_threshold'=>$threshold,'status'=>$save_mode]);
        wp_safe_redirect(add_query_arg('path_notice', $save_mode === 'published' ? 'published' : 'saved', home_url('/'.self::PAGE_SLUG.'/')).'#mastery-paths'); exit;
    }

    public static function handle_evidence_review() {
        if (!is_user_logged_in() || !self::can_view()) wp_die('Teacher access required.', 'Teacher access required', ['response'=>403]);
        check_admin_referer('mb_teacher_evidence_review', 'mb_teacher_review_nonce');
        $teacher_id = get_current_user_id();
        $student_id = isset($_POST['student_id']) ? absint($_POST['student_id']) : 0;
        $lesson_id = isset($_POST['lesson_id']) ? sanitize_text_field(wp_unslash($_POST['lesson_id'])) : '';
        $decision = isset($_POST['decision']) ? sanitize_key(wp_unslash($_POST['decision'])) : '';
        $feedback = isset($_POST['feedback']) ? sanitize_textarea_field(wp_unslash($_POST['feedback'])) : '';
        $required_permission = $decision === 'mastered' ? 'mark_mastery' : ($decision === 'verified' ? ($lesson_id && strpos($lesson_id,'external:')===0 ? 'verify_external_practice' : 'approve_work') : 'provide_feedback');
        if (!$student_id || $lesson_id === '' || !in_array($decision, ['feedback','verified','revision_requested','mastered'], true) || !self::authorized_student($teacher_id, $student_id, $required_permission)) wp_die('This evidence record is not available with your classroom permissions.', 'Permission required', ['response'=>403]);
        $is_external = strpos($lesson_id, 'external:') === 0;
        $activity = self::activity($student_id);
        if ($is_external) {
            $external_id = substr($lesson_id, 9);
            if (!MathBinder_External_Practice::record($student_id, $external_id)) wp_die('This external practice record could not be found.', 'Evidence unavailable', ['response'=>404]);
        } elseif (empty($activity['lessons'][$lesson_id]['completed'])) wp_die('Only completed lesson evidence can be reviewed.', 'Evidence incomplete', ['response'=>400]);
        if (($decision === 'feedback' || $decision === 'revision_requested') && $feedback === '') {
            wp_safe_redirect(add_query_arg(['student'=>$student_id,'review_notice'=>'feedback_required'], home_url('/'.self::PAGE_SLUG.'/')).'#evidence'); exit;
        }
        $reviews = self::reviews($student_id);
        $reviews[$lesson_id] = ['lesson_id'=>$lesson_id,'decision'=>$decision,'feedback'=>$feedback,'teacher_id'=>$teacher_id,'teacher_name'=>wp_get_current_user()->display_name,'reviewed_at'=>current_time('mysql', true)];
        update_user_meta($student_id, 'mb_teacher_evidence_reviews_v1', $reviews);
        if ($is_external && in_array($decision, ['verified','revision_requested','mastered'], true)) {
            $external_records = MathBinder_External_Practice::records($student_id);
            $external_records[$external_id]['status'] = $decision;
            $external_records[$external_id]['updated_at'] = current_time('mysql', true);
            update_user_meta($student_id, MathBinder_External_Practice::META_KEY, $external_records);
        }
        MathBinder_Audit_Log::record('update', 'teacher_evidence_review', $student_id, ['lesson_id'=>$lesson_id,'decision'=>$decision]);
        wp_safe_redirect(add_query_arg(['student'=>$student_id,'review_notice'=>'saved'], home_url('/'.self::PAGE_SLUG.'/')).'#evidence'); exit;
    }

    private static function activity($user_id) {
        $activity = get_user_meta(absint($user_id), 'mb_student_activity_v1', true);
        if (!is_array($activity)) $activity = ['lessons'=>[]];
        if (empty($activity['lessons']) || !is_array($activity['lessons'])) $activity['lessons'] = [];
        return $activity;
    }

    private static function metrics($user_id) {
        $activity = self::activity($user_id); $completed = 0; $notes = 0; $last = '';
        foreach ($activity['lessons'] as $lesson) {
            if (!empty($lesson['completed'])) $completed++;
            if (!empty($lesson['hasNotes'])) $notes++;
            $updated = (string)($lesson['updatedAt'] ?? '');
            if ($updated > $last) $last = $updated;
        }
        return ['completed'=>$completed,'notes'=>$notes,'last'=>$last,'activity'=>$activity];
    }

    private static function assignments_for_student($student_id, $paths, $class_id) {
        $items = []; $activity = self::activity($student_id); $reviews = self::reviews($student_id);
        foreach ($paths as $path) {
            if (($path['status'] ?? '') !== 'published') continue;
            $type = (string)($path['target_type'] ?? ''); $target = absint($path['target_id'] ?? 0);
            if (!(($type === 'student' && $target === (int)$student_id) || ($type === 'class' && $target === (int)$class_id))) continue;
            $lesson_ids = array_values(array_filter(array_map('absint', (array)($path['lesson_ids'] ?? []))));
            $completed = 0; $mastered = 0;
            foreach ($lesson_ids as $lesson_id) {
                if (!empty($activity['lessons'][(string)$lesson_id]['completed'])) $completed++;
                if (($reviews[(string)$lesson_id]['decision'] ?? '') === 'mastered') $mastered++;
            }
            $total = count($lesson_ids); $percent = $total ? (int)round(($completed / $total) * 100) : 0;
            $due = (string)($path['due_date'] ?? ''); $status = $percent >= 100 ? 'Complete' : ($completed ? 'In progress' : 'Not started');
            if ($due && $percent < 100 && strtotime($due.' 23:59:59') < current_time('timestamp')) $status = 'Past due';
            $items[] = ['path'=>$path,'total'=>$total,'completed'=>$completed,'mastered'=>$mastered,'percent'=>$percent,'status'=>$status];
        }
        return $items;
    }

    public static function handle_progress_export() {
        if (!is_user_logged_in() || !self::can_view()) wp_die('Teacher access required.', 'Teacher access required', ['response'=>403]);
        check_admin_referer('mb_teacher_progress_export');
        $teacher_id = get_current_user_id(); $classes = array_values(array_filter(self::classes($teacher_id),function($class)use($teacher_id){return MathBinder_Class_Staff::can($teacher_id,$class['id'],'export_progress');})); $students = self::students($classes); $paths = self::teacher_paths($teacher_id);
        nocache_headers(); header('Content-Type: text/csv; charset=utf-8'); header('Content-Disposition: attachment; filename="mathbinder-student-progress-'.gmdate('Y-m-d').'.csv"');
        $out = fopen('php://output', 'w');
        fputcsv($out, ['Student','Email','Class','Completed Lessons','Saved Notes','Last Activity','Assigned Paths','Completed Paths','Past Due Paths']);
        foreach ($students as $student) {
            $metrics = self::metrics($student['user_id']); $assignments = self::assignments_for_student($student['user_id'], $paths, $student['class_id']);
            $complete = count(array_filter($assignments, function($item){ return $item['status'] === 'Complete'; }));
            $past_due = count(array_filter($assignments, function($item){ return $item['status'] === 'Past due'; }));
            fputcsv($out, [$student['display_name'],$student['user_email'],$student['class_name'].($student['section_name'] ? ' · '.$student['section_name'] : ''),$metrics['completed'],$metrics['notes'],$metrics['last'],count($assignments),$complete,$past_due]);
        }
        fclose($out); exit;
    }

    private static function class_roster_records($class_id) {
        global $wpdb;
        $class_id = absint($class_id);
        $active = $wpdb->get_results($wpdb->prepare(
            "SELECT e.user_id,e.invited_email,e.status,e.created_at,e.updated_at,u.display_name,u.user_email,u.user_registered
             FROM {$wpdb->prefix}mb_enrollments e
             INNER JOIN {$wpdb->users} u ON u.ID=e.user_id
             WHERE e.class_id=%d AND e.role_key='student' AND e.status='active'
             ORDER BY u.display_name,u.user_email",
            $class_id
        ), ARRAY_A) ?: [];
        $pending = $wpdb->get_results($wpdb->prepare(
            "SELECT id,invited_email,status,created_at,updated_at
             FROM {$wpdb->prefix}mb_student_invitations
             WHERE class_id=%d AND status='invited'
             ORDER BY invited_email",
            $class_id
        ), ARRAY_A) ?: [];
        return ['active'=>$active,'pending'=>$pending];
    }

    private static function render_class_roster($class) {
        $class_id = absint($class['id']);
        $roster = self::class_roster_records($class_id);
        $active_count = count($roster['active']);
        $pending_count = count($roster['pending']);
        $total = $active_count + $pending_count;
        $roster_notice = isset($_GET['roster_notice']) ? sanitize_key(wp_unslash($_GET['roster_notice'])) : '';
        ?>
        <section id="class-roster" class="mb-class-roster">
            <div class="mb-class-roster-heading"><div><small>Class roster</small><h3><?php echo esc_html($class['name'].($class['section_name'] ? ' · '.$class['section_name'] : '')); ?></h3><p><?php echo absint($total); ?> total · <?php echo absint($active_count); ?> active · <?php echo absint($pending_count); ?> pending</p></div><a href="<?php echo esc_url(remove_query_arg('view_class',get_permalink()).'#classes'); ?>">Close Roster</a></div>
            <?php if($roster_notice==='resend_sent'):?><div class="mb-teacher-review-notice is-success" role="status">The invitation email was resent.</div><?php elseif($roster_notice==='resend_failed'):?><div class="mb-teacher-review-notice is-error" role="alert">The invitation is still pending, but the email could not be sent. Check the site’s email delivery settings and try again.</div><?php endif;?>
            <div class="mb-class-roster-body">
                <?php if (!$total): ?>
                    <p class="mb-class-roster-empty">No students or pending invitations are attached to this class yet.</p>
                <?php else: ?>
                    <div class="mb-class-roster-table-wrap"><table class="mb-class-roster-table">
                        <thead><tr><th>Student</th><th>Email</th><th>Status</th><th>Completed</th><th>Saved notes</th><th>Last activity</th><th>Progress</th></tr></thead>
                        <tbody>
                        <?php foreach ($roster['active'] as $student): $metrics=self::metrics((int)$student['user_id']); ?>
                            <tr><td><strong><?php echo esc_html($student['display_name'] ?: 'Student'); ?></strong></td><td><?php echo esc_html($student['user_email'] ?: $student['invited_email']); ?></td><td><span class="mb-roster-status is-active">Active</span><small>Enrolled <?php echo esc_html(wp_date(get_option('date_format'), strtotime($student['created_at']))); ?></small></td><td><?php echo absint($metrics['completed']); ?> lesson(s)</td><td><?php echo absint($metrics['notes']); ?> note(s)</td><td><?php echo $metrics['last'] ? esc_html(wp_date(get_option('date_format'), strtotime($metrics['last']))) : 'No activity yet'; ?></td><td><a class="mb-roster-progress-link" href="<?php echo esc_url(add_query_arg('student',(int)$student['user_id'],get_permalink()).'#student-details'); ?>">View Full Progress</a></td></tr>
                        <?php endforeach; ?>
                        <?php foreach ($roster['pending'] as $invitation): ?>
                            <tr><td><strong>Invited student</strong></td><td><?php echo esc_html($invitation['invited_email']); ?></td><td><span class="mb-roster-status is-pending">Pending invitation</span><small>Invited <?php echo esc_html(wp_date(get_option('date_format'), strtotime($invitation['created_at']))); ?></small></td><td>—</td><td>—</td><td>Not activated</td><td><span class="mb-roster-progress-unavailable">Available after activation</span><form class="mb-resend-invitation" method="post" action="<?php echo esc_url(admin_url('admin-post.php'));?>"><input type="hidden" name="action" value="mb_teacher_resend_student_invitation"><input type="hidden" name="class_id" value="<?php echo absint($class_id);?>"><input type="hidden" name="invitation_id" value="<?php echo absint($invitation['id']);?>"><?php wp_nonce_field('mb_teacher_resend_student_invitation','mb_resend_invitation_nonce');?><button type="submit">Resend Invitation</button></form></td></tr>
                        <?php endforeach; ?>
                        </tbody>
                    </table></div>
                <?php endif; ?>
            </div>
        </section>
        <?php
    }

    private static function render_class_workspace($user, $class, $profiles, $paths) {
        $class_id=(int)$class['id']; $profile=$profiles[(string)$class_id]??[]; $roster=self::class_roster_records($class_id);
        $active=[]; $completed=0; $in_progress=0; $not_started=0; $recent=0;
        foreach($roster['active'] as $student){
            $student['metrics']=self::metrics((int)$student['user_id']);
            $student['assignments']=self::assignments_for_student((int)$student['user_id'],$paths,$class_id);
            $active[]=$student; $completed+=(int)$student['metrics']['completed'];
            if(!$student['metrics']['last'])$not_started++; elseif($student['metrics']['completed']>0)$completed_students=($completed_students??0)+1; else $in_progress++;
            if($student['metrics']['last'] && strtotime($student['metrics']['last'])>=strtotime('-7 days'))$recent++;
        }
        $completed_students=(int)($completed_students??0); $active_count=count($active); $pending_count=count($roster['pending']); $total=$active_count+$pending_count;
        $den=max(1,$total); $active_pct=round($active_count/$den*100); $pending_pct=100-$active_pct;
        $activity_den=max(1,$active_count); $complete_pct=round($completed_students/$activity_den*100); $progress_pct=round($in_progress/$activity_den*100); $not_pct=max(0,100-$complete_pct-$progress_pct);
        $join_url=add_query_arg('class_code',$class['class_code'],home_url('/sign-up/')); $notice=sanitize_key(wp_unslash($_GET['roster_notice']??'')); $class_notice=sanitize_key(wp_unslash($_GET['class_notice']??'')); $can_edit=self::teacher_can_manage_class($user->ID,$class_id); $terms=self::active_terms((int)$class['organization_id']);
        ob_start(); ?>
        <div class="mb-teacher-dashboard mb-class-workspace">
            <header class="mb-class-page-hero"><a href="<?php echo esc_url(home_url('/'.self::PAGE_SLUG.'/')); ?>">← Teacher Dashboard</a><div><small>Class workspace</small><h1><?php echo esc_html($class['name'].($class['section_name']?' · '.$class['section_name']:'')); ?></h1><p><?php echo esc_html(trim(($profile['subject']??'Mathematics').' · '.($profile['grade_level']??'').' · '.($profile['school_year']??''),' ·')); ?></p></div><div class="mb-class-code-card"><span>Class code</span><strong><?php echo esc_html($class['class_code']); ?></strong><a href="<?php echo esc_url($join_url); ?>">Open enrollment link</a></div></header>
            <nav class="mb-class-tabs"><a href="#overview">Overview</a><a href="#students">Students</a><a href="#assignments">Assignments &amp; Lessons</a><a href="#settings">Class Settings</a></nav>
            <?php if($notice==='resend_sent'):?><div class="mb-teacher-review-notice is-success">Invitation resent.</div><?php elseif($notice==='resend_failed'):?><div class="mb-teacher-review-notice is-error">The invitation is pending, but email delivery failed.</div><?php elseif($notice==='invitation_removed'):?><div class="mb-teacher-review-notice is-success">Invitation removed. The student is no longer pending.</div><?php elseif(in_array($notice,['invitation_missing','invitation_remove_failed'],true)):?><div class="mb-teacher-review-notice is-error">The invitation could not be removed. Refresh and try again.</div><?php endif;?>
            <section id="overview" class="mb-teacher-panel"><div class="mb-teacher-heading"><div><small>Live class snapshot</small><h2>Overview</h2></div></div><div class="mb-class-chart-grid">
                <article><h3>Enrollment</h3><div class="mb-donut" style="--active:<?php echo absint($active_pct); ?>%"><span><strong><?php echo absint($total); ?></strong>Total</span></div><p><i class="is-teal"></i><?php echo absint($active_count); ?> active <i class="is-purple"></i><?php echo absint($pending_count); ?> pending</p></article>
                <article><h3>Student activity</h3><div class="mb-chart-bars"><label>Completed <b><?php echo absint($complete_pct); ?>%</b></label><span><i class="is-complete" style="width:<?php echo absint($complete_pct); ?>%"></i></span><label>In progress <b><?php echo absint($progress_pct); ?>%</b></label><span><i class="is-progress" style="width:<?php echo absint($progress_pct); ?>%"></i></span><label>Not started <b><?php echo absint($not_pct); ?>%</b></label><span><i class="is-not" style="width:<?php echo absint($not_pct); ?>%"></i></span></div></article>
                <article class="mb-attention-card"><h3>Quick signals</h3><strong><?php echo absint($recent); ?></strong><p>active in the last 7 days</p><strong><?php echo absint($not_started); ?></strong><p>students with no activity yet</p><strong><?php echo absint($completed); ?></strong><p>lessons completed across the class</p></article>
            </div></section>
            <section id="students" class="mb-teacher-panel"><div class="mb-teacher-heading"><div><small>Enrollment and progress</small><h2>Students</h2><p><?php echo absint($active_count); ?> active · <?php echo absint($pending_count); ?> pending</p></div></div>
                <?php if(!$total):?><div class="mb-teacher-empty"><strong>No students yet.</strong><p>Share the enrollment link or invite students from the Teacher Dashboard.</p></div><?php else:?><div class="mb-class-roster-table-wrap"><table class="mb-class-roster-table"><thead><tr><th>Student</th><th>Status</th><th>Progress</th><th>Last activity</th><th>Actions</th></tr></thead><tbody>
                <?php foreach($active as $student):?><tr><td><strong><?php echo esc_html($student['display_name']?:'Student');?></strong><small><?php echo esc_html($student['user_email']);?></small></td><td><span class="mb-roster-status is-active">Active</span></td><td><?php echo absint($student['metrics']['completed']);?> lessons<small><?php echo absint($student['metrics']['notes']);?> saved notes · <?php echo count($student['assignments']);?> assignments</small></td><td><?php echo $student['metrics']['last']?esc_html(wp_date(get_option('date_format'),strtotime($student['metrics']['last']))):'No activity yet';?></td><td><details class="mb-student-detail"><summary>View full progress</summary><div><h4>Assignments</h4><?php if(!$student['assignments']):?><p>No published assignments.</p><?php else:?><?php foreach($student['assignments'] as $item):?><p><strong><?php echo esc_html($item['path']['title']);?></strong> — <?php echo absint($item['percent']);?>% · <?php echo esc_html($item['status']);?></p><?php endforeach;?><?php endif;?><h4>Completed lessons and evidence</h4><?php $found=false;foreach($student['metrics']['activity']['lessons'] as $lesson):if(empty($lesson['completed']))continue;$found=true;?><p><?php echo esc_html($lesson['title']??'Completed lesson');?><?php echo !empty($lesson['hasNotes'])?' · Notes saved':'';?></p><?php endforeach;if(!$found):?><p>No completed lessons yet.</p><?php endif;?></div></details></td></tr><?php endforeach;?>
                <?php foreach($roster['pending'] as $invitation):?><tr><td><strong>Invited student</strong><small><?php echo esc_html($invitation['invited_email']);?></small></td><td><span class="mb-roster-status is-pending">Pending</span><small>Invited <?php echo esc_html(wp_date(get_option('date_format'),strtotime($invitation['created_at'])));?></small></td><td>Available after activation</td><td>Not activated</td><td><div class="mb-invitation-actions"><form method="post" action="<?php echo esc_url(admin_url('admin-post.php'));?>"><input type="hidden" name="action" value="mb_teacher_resend_student_invitation"><input type="hidden" name="class_id" value="<?php echo absint($class_id);?>"><input type="hidden" name="invitation_id" value="<?php echo absint($invitation['id']);?>"><?php wp_nonce_field('mb_teacher_resend_student_invitation','mb_resend_invitation_nonce');?><button type="submit">Resend</button></form><form method="post" action="<?php echo esc_url(admin_url('admin-post.php'));?>" onsubmit="return confirm('Remove this pending invitation?');"><input type="hidden" name="action" value="mb_teacher_remove_student_invitation"><input type="hidden" name="class_id" value="<?php echo absint($class_id);?>"><input type="hidden" name="invitation_id" value="<?php echo absint($invitation['id']);?>"><?php wp_nonce_field('mb_teacher_remove_student_invitation','mb_remove_invitation_nonce');?><button class="is-danger" type="submit">Remove</button></form></div></td></tr><?php endforeach;?></tbody></table></div><?php endif;?>
            </section>
            <section id="assignments" class="mb-teacher-panel"><div class="mb-teacher-heading"><div><small>Instruction</small><h2>Assignments &amp; Lessons</h2></div></div><?php $class_paths=array_values(array_filter($paths,function($p)use($class_id){return ($p['target_type']??'')==='class'&&(int)($p['target_id']??0)===$class_id;}));if(!$class_paths):?><div class="mb-teacher-empty"><strong>No class assignments yet.</strong><p>Create and publish a Mastery Path from the Teacher Dashboard.</p></div><?php else:?><div class="mb-assignment-progress-grid"><?php foreach($class_paths as $path):?><article><small><?php echo esc_html(ucfirst($path['status']??'published'));?></small><h3><?php echo esc_html($path['title']);?></h3><p><?php echo count((array)($path['lesson_ids']??[]));?> lesson(s) · <?php echo absint($path['mastery_threshold']??80);?>% mastery</p></article><?php endforeach;?></div><?php endif;?></section>
            <section id="settings" class="mb-teacher-panel"><div class="mb-teacher-heading"><div><small>Class controls</small><h2>Class Settings</h2><p>Update the classroom information and enrollment rules here.</p></div></div>
                <?php if($class_notice==='updated'):?><div class="mb-teacher-review-notice is-success">Class settings saved.</div><?php elseif($class_notice==='edit_invalid'):?><div class="mb-teacher-review-notice is-error">Check the required class information and try again.</div><?php endif;?>
                <div class="mb-class-settings-link"><div><span>Student enrollment link</span><a href="<?php echo esc_url($join_url);?>"><?php echo esc_html($join_url);?></a></div><button type="button" data-mb-copy-link="<?php echo esc_attr($join_url);?>">Copy Link</button></div>
                <?php if(!$can_edit):?><div class="mb-teacher-empty"><strong>View-only access</strong><p>Your assigned staff permissions allow you to view this class, but not change its settings.</p></div><?php else:?><form class="mb-class-settings-form" method="post" action="<?php echo esc_url(admin_url('admin-post.php'));?>"><input type="hidden" name="action" value="mb_teacher_edit_class"><input type="hidden" name="class_id" value="<?php echo absint($class_id);?>"><input type="hidden" name="return_to_class" value="1"><?php wp_nonce_field('mb_teacher_edit_class','mb_teacher_edit_class_nonce');?><div class="mb-class-form-grid">
                    <label>Class name<input type="text" name="class_name" maxlength="190" required value="<?php echo esc_attr($class['name']);?>"></label><label>Section (optional)<input type="text" name="section_name" maxlength="120" value="<?php echo esc_attr($class['section_name']);?>"></label>
                    <label>Subject<input type="text" name="subject" maxlength="100" required value="<?php echo esc_attr($profile['subject']??'Mathematics');?>"></label><label>Grade level<input type="text" name="grade_level" maxlength="80" required value="<?php echo esc_attr($profile['grade_level']??'');?>"></label>
                    <label>School year or term<input type="text" name="school_year" maxlength="120" required value="<?php echo esc_attr($profile['school_year']??'');?>"></label><label>Associated term<select name="term_id"><?php foreach($terms as $term):?><option value="<?php echo absint($term['id']);?>" <?php selected((int)$class['term_id'],(int)$term['id']);?>><?php echo esc_html($term['name']);?></option><?php endforeach;?></select></label>
                    <label>Student enrollment<select name="enrollment_mode"><option value="code" <?php selected($profile['enrollment_mode']??'code','code');?>>Students may join with class code</option><option value="approval" <?php selected($profile['enrollment_mode']??'code','approval');?>>Teacher approval required</option><option value="closed" <?php selected($profile['enrollment_mode']??'code','closed');?>>Invitations only</option></select></label><label>Class status<select name="class_status"><option value="active" <?php selected($class['status'],'active');?>>Active</option><option value="inactive" <?php selected($class['status'],'inactive');?>>Inactive</option><option value="archived" <?php selected($class['status'],'archived');?>>Archived</option></select></label>
                </div><p class="mb-class-edit-note">Changing these settings does not delete students, notes, assignments, or saved progress. Inactive or archived classes return you to the Teacher Dashboard.</p><button class="mb-teacher-primary" type="submit">Save Class Settings</button></form><?php endif;?>
            </section>
        </div><?php return ob_get_clean();
    }

    private static function render_dashboard_snapshot($classes) {
        if(!$classes){echo '<div class="mb-teacher-empty"><strong>No class data yet.</strong><p>Create a class to begin tracking enrollment and progress.</p></div>';return;}
        echo '<div class="mb-dashboard-chart-grid">';
        foreach($classes as $class){$roster=self::class_roster_records((int)$class['id']);$active=count($roster['active']);$pending=count($roster['pending']);$total=max(1,$active+$pending);$active_pct=round($active/$total*100);$with_activity=0;$completed=0;foreach($roster['active'] as $student){$m=self::metrics((int)$student['user_id']);if($m['last'])$with_activity++;$completed+=(int)$m['completed'];}$engagement=$active?round($with_activity/$active*100):0;$url=add_query_arg('class_id',(int)$class['id'],home_url('/'.self::CLASS_PAGE_SLUG.'/'));echo '<article><div><small>'.esc_html($class['section_name']?:'Class snapshot').'</small><h3>'.esc_html($class['name']).'</h3></div><div class="mb-mini-donut" style="--active:'.absint($active_pct).'%"><span>'.absint($active).'<small>active</small></span></div><dl><div><dt>Pending</dt><dd>'.absint($pending).'</dd></div><div><dt>Engaged</dt><dd>'.absint($engagement).'%</dd></div><div><dt>Lessons done</dt><dd>'.absint($completed).'</dd></div></dl><a href="'.esc_url($url).'">Open Class Workspace</a></article>';}
        echo '</div>';
    }

    public static function shortcode() {
        if (!is_user_logged_in()) return '<section class="mb-dashboard-gate"><h1>Teacher Dashboard</h1><p>Log in with your teacher account to continue.</p><a class="mb-button mb-button-primary" href="'.esc_url(MathBinder_Frontend_Auth::login_url(get_permalink())).'">Log In</a></section>';
        if (!self::can_view()) return '<section class="mb-dashboard-gate"><h1>Teacher access required</h1><p>This dashboard is available only in a Teacher or School Administrator workspace.</p></section>';

        $user = wp_get_current_user(); $classes = self::classes($user->ID); $visible_classes=array_values(array_filter($classes,function($class)use($user){return MathBinder_Class_Staff::can($user->ID,$class['id'],'view_roster')||MathBinder_Class_Staff::can($user->ID,$class['id'],'view_progress')||MathBinder_Class_Staff::can($user->ID,$class['id'],'view_evidence');})); $students = self::students($visible_classes); $paths = self::teacher_paths($user->ID); $published_paths = array_values(array_filter($paths, function($path){ return ($path['status'] ?? 'published') === 'published'; })); $lessons = self::lessons(); $canvas_status = MathBinder_Canvas_Integration::status(); $canvas_queue = MathBinder_Canvas_Integration::for_teacher($user->ID); $organizations = self::teacher_organizations($user->ID); $class_profiles = self::class_profiles();
        if(is_page(self::CLASS_PAGE_SLUG)){$requested=absint($_GET['class_id']??0);$class=null;foreach($visible_classes as $candidate)if((int)$candidate['id']===$requested){$class=$candidate;break;}if(!$class)return '<section class="mb-dashboard-gate"><h1>Class unavailable</h1><p>This class is not available in your teacher workspace.</p><a class="mb-button mb-button-primary" href="'.esc_url(home_url('/'.self::PAGE_SLUG.'/')).'">Return to Teacher Dashboard</a></section>';return self::render_class_workspace($user,$class,$class_profiles,$published_paths);}
        $total_completed = 0; $active_students = 0; $rows = [];
        foreach ($students as $student) { $student['can_view_progress']=MathBinder_Class_Staff::can($user->ID,$student['class_id'],'view_progress'); $student['can_view_evidence']=MathBinder_Class_Staff::can($user->ID,$student['class_id'],'view_evidence'); $student['metrics'] = $student['can_view_progress'] ? self::metrics($student['user_id']) : ['completed'=>0,'notes'=>0,'last'=>'','activity'=>['lessons'=>[]]]; $student['assignments'] = $student['can_view_progress'] ? self::assignments_for_student($student['user_id'], $published_paths, $student['class_id']) : []; $rows[] = $student; $total_completed += $student['metrics']['completed']; if ($student['metrics']['last']) $active_students++; }
        $selected_id = isset($_GET['student']) ? absint($_GET['student']) : 0; $selected = null;
        foreach ($rows as $row) if ((int)$row['user_id'] === $selected_id && ($row['can_view_progress'] || $row['can_view_evidence'])) { $selected = $row; break; }
        $reviews = $selected ? self::reviews($selected_id) : [];
        $review_notice = isset($_GET['review_notice']) ? sanitize_key(wp_unslash($_GET['review_notice'])) : '';
        $path_notice = isset($_GET['path_notice']) ? sanitize_key(wp_unslash($_GET['path_notice'])) : '';
        $class_notice = isset($_GET['class_notice']) ? sanitize_key(wp_unslash($_GET['class_notice'])) : '';
        $roster_notice = isset($_GET['roster_notice']) ? sanitize_key(wp_unslash($_GET['roster_notice'])) : '';
        $staff_notice = isset($_GET['staff_notice']) ? sanitize_key(wp_unslash($_GET['staff_notice'])) : '';
        $view_class_id = isset($_GET['view_class']) ? absint($_GET['view_class']) : 0;
        $view_class = null;
        // self::classes() is already the authorization boundary for this workspace.
        // Do not reject a primary teacher or administrator with a second delegated-staff check.
        foreach ($classes as $candidate_class) if ((int)$candidate_class['id'] === $view_class_id) { $view_class = $candidate_class; break; }
        $edit_path_id = isset($_GET['edit_path']) ? sanitize_text_field(wp_unslash($_GET['edit_path'])) : '';
        $edit_path = $edit_path_id !== '' ? self::find_teacher_path($user->ID, $edit_path_id) : null;
        $builder = wp_parse_args((array)$edit_path, ['id'=>'','title'=>'','standard'=>'','grade_level'=>'','objectives'=>'','prerequisites'=>'','mastery_threshold'=>80,'target_type'=>'class','target_id'=>(int)($classes[0]['id'] ?? 0),'due_date'=>'','lesson_ids'=>[],'branches'=>[],'pretest'=>[],'evidence_requirements'=>'','posttest'=>[],'reassessment'=>'','extension_activity'=>'','status'=>'draft']);
        $builder['branches'] = wp_parse_args((array)$builder['branches'], ['foundational'=>'','developing'=>'','near_mastery'=>'','extension'=>'']);
        $builder['pretest'] = wp_parse_args((array)$builder['pretest'], ['title'=>'','instructions'=>'','url'=>'']);
        $builder['posttest'] = wp_parse_args((array)$builder['posttest'], ['title'=>'','instructions'=>'','url'=>'']);
        $canvas_notice = isset($_GET['canvas_notice']) ? sanitize_key(wp_unslash($_GET['canvas_notice'])) : '';
        ob_start(); ?>
        <div class="mb-teacher-dashboard">
            <header class="mb-teacher-hero"><div><span>Teacher workspace</span><h1>Welcome, <?php echo esc_html($user->display_name ?: $user->user_login); ?></h1><p>See class enrollment and real MathBinder activity in one place.</p></div><a href="<?php echo esc_url(home_url('/mathbinder-account/')); ?>">Account &amp; Workspaces</a></header>
            <nav class="mb-teacher-nav" aria-label="Teacher dashboard sections"><a class="is-active" href="#overview">Overview</a><a href="#classes">My Classes</a><a href="#class-staff">Class Staff</a><a href="#roster">Student Progress</a><a href="#mastery-paths">Mastery Paths</a><a href="#evidence">Evidence</a><a href="#canvas">Canvas</a></nav>
            <section id="overview" class="mb-teacher-stats">
                <article><small>Active classes</small><strong><?php echo count($classes); ?></strong><span>Assigned to this workspace</span></article>
                <article><small>Enrolled students</small><strong><?php echo count($students); ?></strong><span>Across your classes</span></article>
                <article><small>Students with activity</small><strong><?php echo $active_students; ?></strong><span>Synced lesson activity</span></article>
                <article><small>Completed lessons</small><strong><?php echo $total_completed; ?></strong><span>All enrolled students</span></article>
            </section>
            <section id="classes" class="mb-teacher-panel"><div class="mb-teacher-heading mb-teacher-heading-actions"><div><small>Classroom</small><h2>My Classes</h2><p>Create a classroom, share its enrollment code, and manage the roster from this dashboard.</p></div><a class="mb-teacher-export" href="#create-class">Create a Class</a></div>
                <?php if ($class_notice === 'created'): ?><div class="mb-teacher-review-notice is-success" role="status">Class created. Its class code is ready to share, and the Mastery Path Builder is now unlocked.</div><?php elseif ($class_notice === 'updated'): ?><div class="mb-teacher-review-notice is-success" role="status">Class changes saved. The class code and roster were not changed.</div><?php elseif ($class_notice === 'status_updated'): ?><div class="mb-teacher-review-notice is-success" role="status">Class changes saved. Inactive or archived classes no longer appear in the active class list.</div><?php elseif ($class_notice === 'enrolled'): ?><div class="mb-teacher-review-notice is-success" role="status">The existing student account was enrolled, and an email notification was sent.</div><?php elseif ($class_notice === 'invited'): ?><div class="mb-teacher-review-notice is-success" role="status">The student invitation was saved, and the invitation email was sent.</div><?php elseif ($class_notice === 'enrolled_email_failed'): ?><div class="mb-teacher-review-notice is-error" role="alert">The existing student account was enrolled, but MathBinder could not send the notification email. The student can still open the class from their dashboard.</div><?php elseif ($class_notice === 'invited_email_failed'): ?><div class="mb-teacher-review-notice is-error" role="alert">The invitation was saved, but MathBinder could not send the invitation email. Check site email delivery before asking the student to use it.</div><?php elseif ($class_notice === 'invite_failed'): ?><div class="mb-teacher-review-notice is-error" role="alert">The student could not be enrolled or invited. Please try again.</div><?php elseif ($class_notice === 'archived'): ?><div class="mb-teacher-review-notice is-success" role="status">The class was archived.</div><?php elseif (in_array($class_notice, ['invalid','invite_invalid','edit_invalid'], true)): ?><div class="mb-teacher-review-notice is-error" role="alert">Please check the classroom information and try again.</div><?php endif; ?>
                <?php if (!$classes): ?><div class="mb-teacher-empty"><strong>No classes yet.</strong><p>Create your own classroom below. Organization administrators may also assign centrally managed classes.</p></div>
                <?php else: ?><div class="mb-teacher-class-grid"><?php foreach ($classes as $class): $profile=$class_profiles[(string)$class['id']] ?? []; $join_url=add_query_arg('class_code',$class['class_code'],home_url('/student-dashboard/')); $can_edit=self::teacher_can_manage_class($user->ID,$class['id']); ?><article id="class-<?php echo absint($class['id']); ?>"><span><?php echo esc_html($class['section_name'] ?: ($profile['subject'] ?? 'Class')); ?></span><h3><?php echo esc_html($class['name']); ?></h3><p><?php echo esc_html(trim(($profile['subject'] ?? '').' · '.($profile['grade_level'] ?? ''), ' ·')); ?></p><p>Class code: <strong><?php echo esc_html($class['class_code']); ?></strong></p><div class="mb-class-actions"><a class="mb-view-class-button" href="<?php echo esc_url(add_query_arg('class_id',(int)$class['id'],home_url('/'.self::CLASS_PAGE_SLUG.'/'))); ?>">View Class</a><a href="<?php echo esc_url($join_url); ?>">Enrollment link</a><?php if($can_edit): ?><details class="mb-class-edit" <?php echo isset($_GET['edit_class']) && absint($_GET['edit_class'])===(int)$class['id'] ? 'open' : ''; ?>><summary>Edit Class</summary><form method="post" action="<?php echo esc_url(admin_url('admin-post.php')); ?>"><input type="hidden" name="action" value="mb_teacher_edit_class"><input type="hidden" name="class_id" value="<?php echo absint($class['id']); ?>"><?php wp_nonce_field('mb_teacher_edit_class','mb_teacher_edit_class_nonce'); ?><div class="mb-class-form-grid"><label>Class name<input type="text" name="class_name" maxlength="190" required value="<?php echo esc_attr($class['name']); ?>"></label><label>Section (optional)<input type="text" name="section_name" maxlength="120" value="<?php echo esc_attr($class['section_name']); ?>"></label><label>Subject<input type="text" name="subject" maxlength="100" required value="<?php echo esc_attr($profile['subject'] ?? 'Mathematics'); ?>"></label><label>Grade level<input type="text" name="grade_level" maxlength="80" required value="<?php echo esc_attr($profile['grade_level'] ?? ''); ?>"></label><label>School year or term<input type="text" name="school_year" maxlength="120" required value="<?php echo esc_attr($profile['school_year'] ?? ''); ?>"></label><label>Associated term<select name="term_id"><?php foreach(self::active_terms((int)$class['organization_id']) as $term): ?><option value="<?php echo absint($term['id']); ?>" <?php selected((int)$class['term_id'],(int)$term['id']); ?>><?php echo esc_html($term['name']); ?></option><?php endforeach; ?></select></label><label>Enrollment setting<select name="enrollment_mode"><option value="code" <?php selected($profile['enrollment_mode'] ?? 'code','code'); ?>>Students may join with class code</option><option value="approval" <?php selected($profile['enrollment_mode'] ?? 'code','approval'); ?>>Teacher approval required</option><option value="closed" <?php selected($profile['enrollment_mode'] ?? 'code','closed'); ?>>Invitations only</option></select></label><label>Class status<select name="class_status"><option value="active" selected>Active</option><option value="inactive">Inactive</option><option value="archived">Archived</option></select></label></div><p class="mb-class-edit-note">Saving does not change the class code or enrolled students.</p><button type="submit">Save Class</button></form></details><?php endif; ?><details><summary>Invite student</summary><form method="post" action="<?php echo esc_url(admin_url('admin-post.php')); ?>"><input type="hidden" name="action" value="mb_teacher_invite_student"><input type="hidden" name="class_id" value="<?php echo absint($class['id']); ?>"><?php wp_nonce_field('mb_teacher_invite_student','mb_teacher_invite_nonce'); ?><label>Student email<input type="email" name="student_email" required></label><button type="submit">Add or Invite</button></form></details><?php if($can_edit): ?><form method="post" action="<?php echo esc_url(admin_url('admin-post.php')); ?>" onsubmit="return confirm('Archive this class?');"><input type="hidden" name="action" value="mb_teacher_class_status"><input type="hidden" name="class_id" value="<?php echo absint($class['id']); ?>"><?php wp_nonce_field('mb_teacher_class_status','mb_teacher_class_status_nonce'); ?><button class="mb-link-button" type="submit">Archive</button></form><?php endif; ?></div></article><?php endforeach; ?></div><?php endif; ?>
                <?php if($classes): ?><section id="enrollment" class="mb-enrollment-tools"><h3>Student Enrollment</h3><p>Invite as many as 50 students at once by pasting email addresses or uploading a CSV file. New students remain invited until they open the link and create their own password.</p><?php if(in_array($class_notice,['bulk_saved','bulk_saved_mail'],true)):?><div class="mb-teacher-review-notice <?php echo $class_notice==='bulk_saved'?'is-success':'is-error';?>" role="status"><?php echo absint($_GET['invite_count']??0);?> invitation(s) saved.<?php echo $class_notice==='bulk_saved_mail'?' Some email messages could not be delivered.':'';?></div><?php elseif($class_notice==='bulk_empty'):?><div class="mb-teacher-review-notice is-error" role="alert">No valid email addresses were detected. Paste student email addresses or select a CSV file, then try again.</div><?php elseif($class_notice==='bulk_failed'):?><div class="mb-teacher-review-notice is-error" role="alert">The email addresses were detected, but the pending invitations could not be saved. Install the latest MathBinder Core update and try again.</div><?php endif;?><form class="mb-bulk-enrollment-form" method="post" enctype="multipart/form-data" action="<?php echo esc_url(admin_url('admin-post.php'));?>"><input type="hidden" name="action" value="mb_teacher_bulk_invite_students"><?php wp_nonce_field('mb_teacher_bulk_invite_students','mb_teacher_bulk_invite_nonce');?><label>Class<select name="class_id" required><?php foreach($classes as $class):if(!MathBinder_Class_Staff::can($user->ID,$class['id'],'enroll_students'))continue;?><option value="<?php echo absint($class['id']);?>"><?php echo esc_html($class['name'].($class['section_name']?' · '.$class['section_name']:''));?></option><?php endforeach;?></select></label><label>Student emails<textarea name="student_emails" rows="6" placeholder="student1@example.org&#10;student2@example.org"></textarea><small>Separate addresses with new lines, commas, spaces, or semicolons.</small></label><label>Or upload CSV<input type="file" name="student_csv" accept=".csv,text/csv"><small>Accepted columns: first_name, last_name, email. A file containing only an email column also works.</small></label><button class="mb-teacher-primary" type="submit">Send Student Invitations</button></form></section><?php endif;?>
                <details id="create-class" class="mb-class-setup" <?php echo !$classes || $class_notice === 'invalid' ? 'open' : ''; ?>><summary>Create a new classroom</summary><form method="post" action="<?php echo esc_url(admin_url('admin-post.php')); ?>"><input type="hidden" name="action" value="mb_teacher_create_class"><?php wp_nonce_field('mb_teacher_create_class','mb_teacher_class_nonce'); ?><div class="mb-class-form-grid"><label>Class name<input type="text" name="class_name" maxlength="190" required placeholder="Example: Period 2 Math"></label><label>Section (optional)<input type="text" name="section_name" maxlength="120" placeholder="Example: Room 4 or Tuesday/Thursday"></label><label>Subject<input type="text" name="subject" maxlength="100" required value="Mathematics"></label><label>Grade level<input type="text" name="grade_level" maxlength="80" required placeholder="Example: Grades 7–8"></label><label>School year or term<input type="text" name="school_year" maxlength="120" required value="2026–2027"></label><?php if ($organizations): ?><label>Organization<select name="organization_id"><option value="0">Independent teacher workspace</option><?php foreach($organizations as $organization): ?><option value="<?php echo absint($organization['id']); ?>"><?php echo esc_html($organization['name']); ?></option><?php endforeach; ?></select></label><?php endif; ?><label>Enrollment setting<select name="enrollment_mode"><option value="code">Students may join with class code</option><option value="approval">Teacher approval required</option><option value="closed">Invitations only</option></select></label></div><button class="mb-teacher-primary" type="submit">Create Classroom</button></form></details>
            </section>
            <?php if ($view_class): self::render_class_roster($view_class); endif; ?>
            <?php echo MathBinder_Class_Staff::dashboard_section($classes, $staff_notice); ?>
            <section id="roster" class="mb-teacher-panel mb-dashboard-snapshot"><div class="mb-teacher-heading mb-teacher-heading-actions"><div><small>At-a-glance class data</small><h2>Class Progress Snapshot</h2><p>Open a class workspace for the complete roster and individual details.</p></div><a class="mb-teacher-export" href="<?php echo esc_url(wp_nonce_url(admin_url('admin-post.php?action=mb_teacher_progress_export'), 'mb_teacher_progress_export')); ?>">Export CSV</a></div><?php self::render_dashboard_snapshot($classes); ?>
                <?php if ($roster_notice === 'removed'): ?><div class="mb-teacher-review-notice is-success" role="status">The student was removed from this class. Their account, work, notes, progress, and other class enrollments were preserved.</div><?php elseif (in_array($roster_notice,['remove_denied','remove_missing','remove_failed'],true)): ?><div class="mb-teacher-review-notice is-error" role="alert"><?php echo $roster_notice === 'remove_denied' ? 'You do not have permission to remove students from that class.' : ($roster_notice === 'remove_missing' ? 'That active class enrollment could not be found.' : 'The student could not be removed. Please try again.'); ?></div><?php endif; ?>
                <?php if (!$students): ?><div class="mb-teacher-empty"><strong>No active students are enrolled.</strong><p>Students will appear after they are enrolled in one of your assigned classes.</p></div>
                <?php else: ?><div class="mb-teacher-filters"><label>Find a student<input type="search" placeholder="Search by name or email" data-mb-roster-search></label><label>Class<select data-mb-roster-class><option value="">All classes</option><?php foreach ($classes as $class): ?><option value="<?php echo absint($class['id']); ?>"><?php echo esc_html($class['name'].($class['section_name'] ? ' · '.$class['section_name'] : '')); ?></option><?php endforeach; ?></select></label><label>Status<select data-mb-roster-status><option value="">All activity</option><option value="active">Has activity</option><option value="inactive">No activity</option><option value="past-due">Past due</option></select></label></div><div class="mb-teacher-table-wrap"><table><thead><tr><th>Student</th><th>Class</th><th>Assignments</th><th>Completed</th><th>Last activity</th><th>Actions</th></tr></thead><tbody data-mb-roster><?php foreach ($rows as $row): $past_due=(bool)array_filter($row['assignments'],function($item){return $item['status']==='Past due';}); $can_remove=MathBinder_Class_Staff::can($user->ID,$row['class_id'],'remove_students'); ?><tr data-name="<?php echo esc_attr(strtolower($row['display_name'].' '.$row['user_email'])); ?>" data-class="<?php echo absint($row['class_id']); ?>" data-activity="<?php echo $row['metrics']['last']?'active':'inactive'; ?>" data-past-due="<?php echo $past_due?'1':'0'; ?>"><td><strong><?php echo esc_html($row['display_name']); ?></strong><small><?php echo esc_html($row['user_email']); ?></small></td><td><?php echo esc_html($row['class_name'].($row['section_name'] ? ' · '.$row['section_name'] : '')); ?></td><td><?php echo count($row['assignments']); ?></td><td><?php echo intval($row['metrics']['completed']); ?><small><?php echo intval($row['metrics']['notes']); ?> saved note(s)</small></td><td><?php echo $row['metrics']['last'] ? esc_html(wp_date(get_option('date_format'), strtotime($row['metrics']['last']))) : 'No activity yet'; ?></td><td><div class="mb-roster-actions"><a href="<?php echo esc_url(add_query_arg('student', $row['user_id'], get_permalink()).'#student-details'); ?>">View progress</a><?php if($can_remove): ?><form method="post" action="<?php echo esc_url(admin_url('admin-post.php')); ?>" onsubmit="return confirm('Remove this student from this class? Their account and saved work will be preserved.');"><input type="hidden" name="action" value="mb_teacher_remove_student"><input type="hidden" name="class_id" value="<?php echo absint($row['class_id']); ?>"><input type="hidden" name="student_id" value="<?php echo absint($row['user_id']); ?>"><?php wp_nonce_field('mb_teacher_remove_student','mb_teacher_remove_nonce'); ?><button type="submit">Remove from Class</button></form><?php endif; ?></div></td></tr><?php endforeach; ?></tbody></table><p class="mb-teacher-no-results" data-mb-roster-empty hidden>No students match these filters.</p></div><?php endif; ?>
            </section>
            <?php if ($selected): ?><section id="student-details" class="mb-teacher-panel"><div class="mb-teacher-heading"><div><small>Individual progress</small><h2><?php echo esc_html($selected['display_name']); ?></h2><p><?php echo esc_html($selected['class_name']); ?> · <?php echo intval($selected['metrics']['completed']); ?> completed lesson(s) · <?php echo intval($selected['metrics']['notes']); ?> saved note(s)</p></div></div><?php if (!$selected['assignments']): ?><div class="mb-teacher-empty"><strong>No published assignments yet.</strong><p>Assign this student or class a published mastery path to begin tracking progress.</p></div><?php else: ?><div class="mb-assignment-progress-grid"><?php foreach ($selected['assignments'] as $item): ?><article><div><small><?php echo esc_html($item['status']); ?></small><h3><?php echo esc_html($item['path']['title']); ?></h3><p><?php echo intval($item['completed']); ?> of <?php echo intval($item['total']); ?> lessons complete · <?php echo intval($item['mastered']); ?> mastered</p></div><strong><?php echo intval($item['percent']); ?>%</strong><div class="mb-assignment-track"><span style="width:<?php echo intval($item['percent']); ?>%"></span></div><?php if (!empty($item['path']['due_date'])): ?><time>Due <?php echo esc_html(wp_date(get_option('date_format'), strtotime($item['path']['due_date']))); ?></time><?php endif; ?></article><?php endforeach; ?></div><?php endif; ?></section><?php endif; ?>
            <?php if ($selected): echo MathBinder_Math_Notes::teacher_section($selected_id, wp_list_pluck($visible_classes,'id')); endif; ?>
            <section id="mastery-paths" class="mb-teacher-panel"><div class="mb-teacher-heading"><div><small>Instructional engine</small><h2>Mastery Path Builder</h2><p>Select a grade, enter a title, and identify the California mathematics standard. MathBinder builds the pretest, assignments, and posttest for you to review and edit.</p></div></div>
                <?php if ($path_notice === 'published'): ?><div class="mb-teacher-review-notice is-success" role="status">Mastery path previewed, published, and assigned.</div><?php elseif ($path_notice === 'saved'): ?><div class="mb-teacher-review-notice is-success" role="status">Mastery path draft saved.</div><?php elseif ($path_notice === 'invalid'): ?><div class="mb-teacher-review-notice is-error" role="alert">The path could not be published. Complete every required step, select an assignment target, and preview the student sequence.</div><?php endif; ?>
                <?php if (!$classes): ?><div class="mb-teacher-empty"><strong>A class assignment is required.</strong><p>An administrator must assign this teacher to a class before a mastery path can be published.</p></div>
                <?php else: ?><form class="mb-mastery-path-form" data-mb-mastery-builder method="post" action="<?php echo esc_url(admin_url('admin-post.php')); ?>"><input type="hidden" name="action" value="mb_teacher_mastery_path"><input type="hidden" name="path_id" value="<?php echo esc_attr($builder['id']); ?>"><input type="hidden" name="lesson_order" value="<?php echo esc_attr(implode(',', array_map('absint', (array)$builder['lesson_ids']))); ?>" data-mb-lesson-order><?php wp_nonce_field('mb_teacher_mastery_path', 'mb_mastery_path_nonce'); ?>
                    <div class="mb-mastery-builder-head"><div><strong><?php echo $edit_path ? 'Edit generated mastery path' : 'Create a mastery path'; ?></strong><span data-mb-builder-status><?php echo esc_html(ucfirst($builder['status'])); ?></span></div><ol aria-label="Builder progress"><?php foreach (['Describe','Review & edit','Preview & publish'] as $index=>$label): ?><li class="<?php echo $index===0?'is-active':''; ?>" data-mb-step-dot="<?php echo $index+1; ?>"><b><?php echo $index+1; ?></b><span><?php echo esc_html($label); ?></span></li><?php endforeach; ?></ol></div>
                    <section class="mb-mastery-step is-active" data-mb-step="1"><span class="mb-mastery-step-kicker">Step 1 of 3</span><h3>What should MathBinder build?</h3><p>Select the grade, enter the title, and identify the California mathematics standard.</p><div class="mb-mastery-path-grid"><label>Grade level<select name="mastery_grade_level" required><option value="">Select grade</option><?php foreach (array_merge(['K'], array_map('strval', range(1, 12))) as $grade_option): ?><option value="<?php echo esc_attr($grade_option); ?>" <?php selected((string)$builder['grade_level'], $grade_option); ?>><?php echo $grade_option === 'K' ? 'Kindergarten' : 'Grade '.esc_html($grade_option); ?></option><?php endforeach; ?></select></label><label>Topic or unit title<input type="text" name="path_title" maxlength="160" value="<?php echo esc_attr($builder['title']); ?>" required placeholder="Example: Ratios and proportional relationships"></label><label>Target California standard<input type="text" name="target_standard" maxlength="1000" value="<?php echo esc_attr($builder['standard']); ?>" required placeholder="Example: 7.RP.A.2 or paste the complete standard wording"></label></div><button type="button" class="mb-teacher-primary" data-mb-generate><?php echo $edit_path ? 'Regenerate Draft' : 'Generate Pretest, Assignments & Posttest'; ?></button><p class="mb-mastery-path-note" data-mb-generation-status>MathBinder verifies the grade and standard before generating. Nothing publishes until you review and approve it.</p></section>
                    <section class="mb-mastery-step" data-mb-step="2" hidden><span class="mb-mastery-step-kicker">Step 2 of 3</span><h3>Review and edit MathBinder's draft</h3><p>Every field below is editable. Adjust questions, directions, assignments, or routes before previewing.</p><input type="hidden" name="mastery_threshold" value="<?php echo absint($builder['mastery_threshold']); ?>"><div class="mb-generated-card"><h4>Learning goal</h4><label>Learning objective<textarea name="objectives" rows="3" maxlength="3000" required><?php echo esc_textarea($builder['objectives']); ?></textarea></label><label>Prerequisites<textarea name="prerequisites" rows="3" maxlength="3000" required><?php echo esc_textarea($builder['prerequisites']); ?></textarea></label></div><div class="mb-generated-card"><h4>Diagnostic pretest · 8 questions</h4><label>Pretest title<input type="text" name="pretest_title" maxlength="160" value="<?php echo esc_attr($builder['pretest']['title']); ?>" required></label><label>Directions and editable questions<textarea name="pretest_instructions" rows="13" maxlength="6000" required><?php echo esc_textarea($builder['pretest']['instructions']); ?></textarea></label><input type="hidden" name="pretest_url" value="<?php echo esc_attr($builder['pretest']['url']); ?>"></div><div class="mb-generated-card"><h4>Differentiated assignments</h4><div class="mb-mastery-branch-grid"><label>Foundational route<textarea name="foundational" rows="5" maxlength="3000" required><?php echo esc_textarea($builder['branches']['foundational']); ?></textarea></label><label>Developing route<textarea name="developing" rows="5" maxlength="3000" required><?php echo esc_textarea($builder['branches']['developing']); ?></textarea></label><label>Near-mastery route<textarea name="near_mastery" rows="5" maxlength="3000" required><?php echo esc_textarea($builder['branches']['near_mastery']); ?></textarea></label><label>Extension route (80%+)<textarea name="extension" rows="5" maxlength="3000" required><?php echo esc_textarea($builder['branches']['extension']); ?></textarea></label></div><details><summary>Review suggested MathBinder lessons</summary><div class="mb-mastery-sequence-layout"><div class="mb-mastery-lesson-list"><?php foreach ($lessons as $lesson): $checked=in_array((int)$lesson->ID,array_map('intval',(array)$builder['lesson_ids']),true); ?><label><input type="checkbox" data-mb-lesson value="<?php echo absint($lesson->ID); ?>" data-title="<?php echo esc_attr($lesson->post_title); ?>" <?php checked($checked); ?>> <?php echo esc_html($lesson->post_title); ?></label><?php endforeach; ?></div><ol class="mb-mastery-selected" data-mb-selected-lessons></ol></div></details></div><div class="mb-generated-card"><h4>Evidence Folder</h4><label>Generated evidence requirement<textarea name="evidence_requirements" rows="5" maxlength="4000" required><?php echo esc_textarea($builder['evidence_requirements']); ?></textarea></label></div><div class="mb-generated-card"><h4>Posttest · equivalent mastery check</h4><label>Posttest title<input type="text" name="posttest_title" maxlength="160" value="<?php echo esc_attr($builder['posttest']['title']); ?>" required></label><label>Directions and editable questions<textarea name="posttest_instructions" rows="13" maxlength="6000" required><?php echo esc_textarea($builder['posttest']['instructions']); ?></textarea></label><input type="hidden" name="posttest_url" value="<?php echo esc_attr($builder['posttest']['url']); ?>"></div><div class="mb-generated-card"><h4>Next routes</h4><label>Reteaching and reassessment<textarea name="reassessment" rows="4" maxlength="3000" required><?php echo esc_textarea($builder['reassessment']); ?></textarea></label><label>Extension after mastery<textarea name="extension_activity" rows="4" maxlength="3000"><?php echo esc_textarea($builder['extension_activity']); ?></textarea></label></div></section>
                    <section class="mb-mastery-step" data-mb-step="3" hidden><span class="mb-mastery-step-kicker">Step 3 of 3</span><h3>Preview and approve</h3><div class="mb-mastery-path-grid"><label>Assign to class or student<select name="target" required><optgroup label="Classes"><?php foreach ($classes as $class): $selected=$builder['target_type']==='class'&&(int)$builder['target_id']===(int)$class['id']; ?><option value="class:<?php echo absint($class['id']); ?>" <?php selected($selected); ?>><?php echo esc_html($class['name'].($class['section_name'] ? ' · '.$class['section_name'] : '')); ?></option><?php endforeach; ?></optgroup><?php if ($students): ?><optgroup label="Individual students"><?php foreach ($students as $student): $selected=$builder['target_type']==='student'&&(int)$builder['target_id']===(int)$student['user_id']; ?><option value="student:<?php echo absint($student['user_id']); ?>" <?php selected($selected); ?>><?php echo esc_html($student['display_name'].' · '.$student['class_name']); ?></option><?php endforeach; ?></optgroup><?php endif; ?></select></label><label>Due date (optional)<input type="date" name="due_date" value="<?php echo esc_attr($builder['due_date']); ?>"></label></div><p>Review the complete student experience. Publishing remains locked until this preview has been opened.</p><button class="mb-mastery-preview" type="button" data-mb-preview>Open Complete Preview</button><div class="mb-mastery-preview-status" data-mb-preview-status>Preview required before publishing.</div></section>
                    <div class="mb-mastery-builder-actions"><button type="button" data-mb-prev disabled>Back</button><button type="submit" name="save_mode" value="draft">Save Draft</button><button type="button" class="is-primary" data-mb-next>Continue</button><button type="submit" class="is-primary" name="save_mode" value="published" data-mb-publish disabled>Approve &amp; Publish</button></div>
                    <dialog class="mb-student-preview" data-mb-preview-dialog><div class="mb-preview-title"><div><small>Student preview</small><h3 data-preview-title>Mastery Path</h3></div><button type="button" data-mb-close-preview aria-label="Close preview">×</button></div><ol data-mb-preview-sequence></ol><button type="button" class="mb-mastery-publish" data-mb-close-preview>Return to Builder</button></dialog>
                </form><?php endif; ?>
                <div class="mb-mastery-path-list"><h3>Saved Mastery Paths</h3><?php if (!$paths): ?><div class="mb-teacher-empty"><strong>No mastery paths saved yet.</strong><p>Your drafts and published assignments will appear here.</p></div><?php else: ?><?php foreach ($paths as $path): ?><article><div><small><?php echo esc_html(ucwords(str_replace('_',' ', $path['target_type'] ?? 'class'))); ?> assignment</small><strong><?php echo esc_html($path['title']); ?></strong><span><?php echo absint($path['mastery_threshold']); ?>% mastery · <?php echo count((array)($path['lesson_ids'] ?? [])); ?> lesson(s)<?php echo !empty($path['due_date']) ? ' · Due '.esc_html(wp_date(get_option('date_format'), strtotime($path['due_date']))) : ''; ?></span></div><div class="mb-mastery-list-actions"><b class="is-<?php echo esc_attr($path['status'] ?? 'published'); ?>"><?php echo esc_html(ucfirst($path['status'] ?? 'published')); ?></b><a href="<?php echo esc_url(add_query_arg('edit_path',$path['id'],get_permalink()).'#mastery-paths'); ?>">Edit</a></div></article><?php endforeach; ?><?php endif; ?></div>
            </section>
            <section id="evidence" class="mb-teacher-panel"><div class="mb-teacher-heading"><div><small>Completed lesson record</small><h2>Evidence Review</h2></div></div>
                <?php if (!$selected): ?><div class="mb-teacher-empty"><strong>Select a student from the progress table.</strong><p>The student’s synced completed lessons and notes indicators will appear here for review.</p></div>
                <?php else: ?><h3><?php echo esc_html($selected['display_name']); ?></h3>
                    <?php if ($review_notice === 'saved'): ?><div class="mb-teacher-review-notice is-success" role="status">Evidence review saved.</div><?php elseif ($review_notice === 'feedback_required'): ?><div class="mb-teacher-review-notice is-error" role="alert">Add feedback before saving that decision.</div><?php endif; ?>
                    <div class="mb-teacher-evidence-list"><?php $found=false; foreach ($selected['metrics']['activity']['lessons'] as $lesson_id=>$lesson): if (empty($lesson['completed'])) continue; $found=true; $review=$reviews[(string)$lesson_id] ?? []; ?><article class="mb-teacher-evidence-card">
                        <div class="mb-teacher-evidence-summary"><div><small><?php echo esc_html($lesson['section'] ?? 'MathBinder'); ?></small><strong><?php echo esc_html($lesson['title'] ?? 'Completed lesson'); ?></strong></div><span><?php echo !empty($lesson['hasNotes']) ? 'Notes included' : 'Completed'; ?></span><?php if (!empty($lesson['url'])): ?><a href="<?php echo esc_url($lesson['url']); ?>">Open lesson</a><?php endif; ?></div>
                        <?php if ($review): ?><div class="mb-teacher-review-history"><strong><?php echo esc_html($review['decision']==='mastered' ? 'Mastered' : ($review['decision']==='revision_requested' ? 'Revision requested' : 'Feedback sent')); ?></strong><span>Reviewed <?php echo esc_html(wp_date(get_option('date_format'), strtotime($review['reviewed_at']))); ?> by <?php echo esc_html($review['teacher_name']); ?></span><?php if (!empty($review['feedback'])): ?><p><?php echo esc_html($review['feedback']); ?></p><?php endif; ?></div><?php endif; ?>
                        <form class="mb-teacher-review-form" method="post" action="<?php echo esc_url(admin_url('admin-post.php')); ?>"><input type="hidden" name="action" value="mb_teacher_evidence_review"><input type="hidden" name="student_id" value="<?php echo absint($selected_id); ?>"><input type="hidden" name="lesson_id" value="<?php echo esc_attr($lesson_id); ?>"><?php wp_nonce_field('mb_teacher_evidence_review', 'mb_teacher_review_nonce'); ?><label>Teacher feedback<textarea name="feedback" rows="3" maxlength="2000" placeholder="Share specific, helpful feedback with this student."><?php echo esc_textarea($review['feedback'] ?? ''); ?></textarea></label><div class="mb-teacher-review-actions"><button type="submit" name="decision" value="feedback">Save Feedback</button><button class="is-revision" type="submit" name="decision" value="revision_requested">Request Revision</button><button class="is-mastered" type="submit" name="decision" value="mastered">Mark Mastered</button></div></form>
                    </article><?php endforeach; if(!$found): ?><div class="mb-teacher-empty"><strong>No completed lesson evidence yet.</strong><p>Activity will appear after the student marks a lesson complete.</p></div><?php endif; ?></div><?php endif; ?>
                <?php if ($selected): $external_records=MathBinder_External_Practice::records($selected_id); if ($external_records): ?>
                    <h3>External Practice</h3><div class="mb-teacher-evidence-list">
                    <?php foreach ($external_records as $external_id=>$record): $review_key=MathBinder_External_Practice::review_key($external_id); $review=$reviews[$review_key] ?? []; $status=$review['decision'] ?? ($record['status'] ?? 'student_reported'); ?><article class="mb-teacher-evidence-card">
                        <div class="mb-teacher-evidence-summary"><div><small><?php echo esc_html($record['platform'].' · '.$record['topic_title']); ?></small><strong><?php echo esc_html($record['activity_title']); ?></strong><p><?php echo esc_html($record['result'].' · '.wp_date(get_option('date_format'),strtotime($record['completed_on']))); ?></p></div><span><?php echo esc_html(MathBinder_External_Practice::status_label($status)); ?></span><?php if (!empty($record['activity_url'])): ?><a href="<?php echo esc_url($record['activity_url']); ?>" target="_blank" rel="noopener noreferrer">Open activity</a><?php endif; ?><?php if (!empty($record['evidence']['path'])): ?><a href="<?php echo esc_url(MathBinder_External_Practice::evidence_url($selected_id,$external_id)); ?>">View evidence</a><?php endif; ?></div>
                        <p><strong>Student reflection:</strong> <?php echo esc_html($record['reflection']); ?></p>
                        <?php if ($review): ?><div class="mb-teacher-review-history"><strong><?php echo esc_html(MathBinder_External_Practice::status_label($status)); ?></strong><span>Reviewed <?php echo esc_html(wp_date(get_option('date_format'),strtotime($review['reviewed_at']))); ?> by <?php echo esc_html($review['teacher_name']); ?></span><?php if (!empty($review['feedback'])): ?><p><?php echo esc_html($review['feedback']); ?></p><?php endif; ?></div><?php endif; ?>
                        <form class="mb-teacher-review-form" method="post" action="<?php echo esc_url(admin_url('admin-post.php')); ?>"><input type="hidden" name="action" value="mb_teacher_evidence_review"><input type="hidden" name="student_id" value="<?php echo absint($selected_id); ?>"><input type="hidden" name="lesson_id" value="<?php echo esc_attr($review_key); ?>"><?php wp_nonce_field('mb_teacher_evidence_review','mb_teacher_review_nonce'); ?><label>Teacher feedback<textarea name="feedback" rows="3" maxlength="2000"><?php echo esc_textarea($review['feedback'] ?? ''); ?></textarea></label><div class="mb-teacher-review-actions"><button type="submit" name="decision" value="verified">Verify Practice</button><button class="is-revision" type="submit" name="decision" value="revision_requested">Request Revision</button><button class="is-mastered" type="submit" name="decision" value="mastered">Mark Mastered</button></div></form>
                    </article><?php endforeach; ?></div>
                <?php endif; endif; ?>
            </section>
            <section id="canvas" class="mb-teacher-panel mb-canvas-panel"><div class="mb-teacher-heading"><div><small>LTI 1.3 submission and grading</small><h2>Canvas</h2><p>Prepare MathBinder assignments, accept student-selected locked note snapshots, and send only teacher-approved grades to SpeedGrader.</p></div><span class="mb-canvas-status <?php echo $canvas_status['adapter_ready'] ? 'is-ready' : 'is-off'; ?>"><?php echo esc_html($canvas_status['label']); ?></span></div>
                <?php if ($canvas_notice === 'prepared'): ?><div class="mb-teacher-review-notice is-success" role="status">Assignment prepared for Canvas. Nothing was sent because the live connection is disabled.</div><?php endif; ?>
                <div class="mb-canvas-safety"><strong>Safe staging mode</strong><p><?php echo esc_html($canvas_status['detail']); ?></p><p>Canvas IDs remain external mappings. They never replace permanent MathBinder student, class, assignment, or grade records.</p></div>
                <?php if (!$published_paths): ?><div class="mb-teacher-empty"><strong>No published mastery paths are ready.</strong><p>Publish a MathBinder Mastery Path before preparing a Canvas assignment.</p></div>
                <?php else: ?><div class="mb-canvas-path-list"><?php foreach ($published_paths as $path): $prepared = false; foreach ($canvas_queue as $queued) if (($queued['mathbinder_id'] ?? '') === ($path['id'] ?? '')) $prepared = $queued; ?><article><div><small><?php echo esc_html(ucwords(str_replace('_',' ', $path['target_type']))); ?> assignment</small><strong><?php echo esc_html($path['title']); ?></strong><span><?php echo $prepared ? 'Ready for Canvas setup' : 'MathBinder only'; ?></span></div><?php if ($prepared): ?><b>Prepared</b><?php else: ?><form method="post" action="<?php echo esc_url(admin_url('admin-post.php')); ?>"><input type="hidden" name="action" value="mb_teacher_canvas_prepare"><input type="hidden" name="path_id" value="<?php echo esc_attr($path['id']); ?>"><?php wp_nonce_field('mb_teacher_canvas_prepare', 'mb_canvas_prepare_nonce'); ?><button type="submit">Prepare for Canvas</button></form><?php endif; ?></article><?php endforeach; ?></div><?php endif; ?>
                <p class="mb-canvas-future"><strong>Core 30.37 safeguards:</strong> Student selection uses Canvas Homework Submission and a locked MathBinder evidence link. Grade passback requires a saved teacher grade, an approved user mapping, an assignment-specific AGS score endpoint, and an enabled sandbox gate. MathBinder remains the authoritative record.</p>
            </section>
        </div><?php return ob_get_clean();
    }
}
