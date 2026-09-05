<?php
if (!defined('ABSPATH')) exit;

/** Invitation-based, class-scoped delegated access. */
final class MathBinder_Class_Staff {
    const SHORTCODE = 'mathbinder_staff_invitation';
    const PAGE_SLUG = 'classroom-staff-invitation';
    const INVITE_LIFETIME = 7 * DAY_IN_SECONDS;

    private static $permissions = [
        'view_roster' => 'View student roster',
        'view_progress' => 'View student progress',
        'view_evidence' => 'View submitted work and Evidence Folders',
        'provide_feedback' => 'Provide feedback',
        'approve_work' => 'Approve student work',
        'mark_mastery' => 'Mark mastery',
        'verify_external_practice' => 'Verify External Practice Records',
        'assign_lessons' => 'Assign or reassign lessons',
        'manage_due_dates' => 'Manage due dates',
        'enroll_students' => 'Add or enroll students',
        'remove_students' => 'Remove students',
        'edit_class_settings' => 'Edit class settings',
        'export_progress' => 'Export student progress',
    ];

    public static function register() {
        add_shortcode(self::SHORTCODE, [__CLASS__, 'invitation_shortcode']);
        add_action('admin_post_mb_invite_class_staff', [__CLASS__, 'handle_invite_safe']);
        add_action('admin_post_mb_update_class_staff', [__CLASS__, 'handle_update']);
        add_action('admin_post_mb_remove_class_staff', [__CLASS__, 'handle_remove']);
        add_action('admin_post_mb_accept_class_staff', [__CLASS__, 'handle_accept']);
        add_action('admin_post_nopriv_mb_accept_class_staff', [__CLASS__, 'handle_accept']);
        add_action('wp_enqueue_scripts', [__CLASS__, 'enqueue_assets']);
    }

    public static function ensure_page() {
        $page = get_page_by_path(self::PAGE_SLUG, OBJECT, 'page');
        $data = ['post_type'=>'page','post_status'=>'publish','post_title'=>'Classroom Staff Invitation','post_name'=>self::PAGE_SLUG,'post_content'=>'['.self::SHORTCODE.']'];
        if ($page) $data['ID'] = $page->ID;
        wp_insert_post($data);
    }

    public static function enqueue_assets() {
        if (is_page([
            self::PAGE_SLUG,
            MathBinder_Teacher_Dashboard::PAGE_SLUG,
            MathBinder_Teacher_Dashboard::STAFF_PAGE_SLUG,
        ])) {
            wp_enqueue_style('mathbinder-class-staff', plugins_url('assets/class-staff.css', __FILE__), [], MathBinder_Core::VERSION);
        }
    }

    public static function permission_labels() { return self::$permissions; }

    public static function full_permissions() { return array_keys(self::$permissions); }

    private static function normalize_permissions($values) {
        $values = array_map('sanitize_key', (array) $values);
        return array_values(array_intersect(array_keys(self::$permissions), $values));
    }

    public static function primary_can_manage($user_id, $class_id) {
        global $wpdb;
        $user_id = absint($user_id); $class_id = absint($class_id);
        if (user_can($user_id, 'manage_options') || user_can($user_id, MathBinder_Capabilities::MANAGE_ORGANIZATIONS)) return true;
        return (int) $wpdb->get_var($wpdb->prepare("SELECT COUNT(*) FROM {$wpdb->prefix}mb_classes WHERE id=%d AND teacher_user_id=%d AND status='active'", $class_id, $user_id)) > 0;
    }

    public static function active_access($user_id, $class_id = 0) {
        global $wpdb;
        $now = current_time('mysql', true);
        $sql = "SELECT s.*,c.name AS class_name,c.section_name,c.teacher_user_id FROM {$wpdb->prefix}mb_class_staff_access s JOIN {$wpdb->prefix}mb_classes c ON c.id=s.class_id WHERE s.user_id=%d AND s.status='active' AND c.status='active' AND (s.starts_at IS NULL OR s.starts_at<=%s) AND (s.expires_at IS NULL OR s.expires_at>=%s)";
        $args = [absint($user_id), $now, $now];
        if ($class_id) { $sql .= ' AND s.class_id=%d'; $args[] = absint($class_id); }
        return $wpdb->get_results($wpdb->prepare($sql, $args), ARRAY_A) ?: [];
    }

    public static function has_any_access($user_id) { return (bool) self::active_access($user_id); }

    public static function can($user_id, $class_id, $permission) {
        if (self::primary_can_manage($user_id, $class_id)) return true;
        $permission = sanitize_key($permission);
        if (!isset(self::$permissions[$permission])) return false;
        $records = self::active_access($user_id, $class_id);
        if (!$records) return false;
        $granted = json_decode((string) $records[0]['permissions_json'], true);
        return in_array($permission, self::normalize_permissions($granted), true);
    }

    public static function class_ids_for_user($user_id) {
        return array_values(array_unique(array_map('absint', wp_list_pluck(self::active_access($user_id), 'class_id'))));
    }

    public static function staff_for_classes(array $class_ids) {
        global $wpdb;
        $class_ids = array_values(array_filter(array_map('absint', $class_ids)));
        if (!$class_ids) return [];
        $holders = implode(',', array_fill(0, count($class_ids), '%d'));
        return $wpdb->get_results($wpdb->prepare("SELECT s.*,u.display_name AS user_display_name,u.user_email,c.name AS class_name,c.section_name FROM {$wpdb->prefix}mb_class_staff_access s LEFT JOIN {$wpdb->users} u ON u.ID=s.user_id JOIN {$wpdb->prefix}mb_classes c ON c.id=s.class_id WHERE s.class_id IN ($holders) ORDER BY c.name,s.display_name,s.account_email", $class_ids), ARRAY_A) ?: [];
    }

    private static function invitation_url($token) {
        return add_query_arg('staff_invitation', rawurlencode($token), home_url('/'.self::PAGE_SLUG.'/'));
    }

    private static function redirect_notice($notice) {
        wp_safe_redirect(add_query_arg('staff_notice', sanitize_key($notice), home_url('/'.MathBinder_Teacher_Dashboard::STAFF_PAGE_SLUG.'/'))); exit;
    }

    private static function parsed_date($value, $end = false) {
        $value = sanitize_text_field(wp_unslash((string) $value));
        if ($value === '') return null;
        $time = strtotime($value . ($end ? ' 23:59:59' : ' 00:00:00'));
        return $time ? gmdate('Y-m-d H:i:s', $time) : null;
    }

    /** Always return staff form failures to the dashboard instead of leaving a blank admin-post.php response. */
    public static function handle_invite_safe() {
        if (!is_user_logged_in()) {
            wp_safe_redirect(MathBinder_Frontend_Auth::login_url(home_url('/'.MathBinder_Teacher_Dashboard::STAFF_PAGE_SLUG.'/')));
            exit;
        }
        $nonce=isset($_POST['mb_staff_nonce'])?sanitize_text_field(wp_unslash($_POST['mb_staff_nonce'])):'';
        if(!$nonce || !wp_verify_nonce($nonce,'mb_invite_class_staff')) self::redirect_notice('session_expired');
        try { self::handle_invite(); }
        catch (Throwable $error) {
            MathBinder_Audit_Log::record('class_staff_invitation_error','class_staff','',['message'=>sanitize_text_field($error->getMessage())]);
            self::redirect_notice('unexpected_error');
        }
    }

    public static function handle_invite() {
        if (!is_user_logged_in()) wp_die('Teacher access required.', 'Teacher access required', ['response'=>403]);
        // The wrapper verified the nonce and guarantees a dashboard redirect on failure.
        global $wpdb;
        $actor = get_current_user_id(); $class_id = absint($_POST['class_id'] ?? 0);
        if (!self::primary_can_manage($actor, $class_id)) wp_die('Only the primary teacher or an administrator may manage classroom staff.', 'Staff management unavailable', ['response'=>403]);
        $name = sanitize_text_field(wp_unslash($_POST['staff_name'] ?? ''));
        $email = strtolower(sanitize_email(wp_unslash($_POST['staff_email'] ?? '')));
        $role = sanitize_key(wp_unslash($_POST['staff_role'] ?? 'class_aide'));
        $level = sanitize_key(wp_unslash($_POST['access_level'] ?? 'custom'));
        if (!$name || !is_email($email) || !in_array($role, ['co_teacher','substitute','class_aide'], true) || !in_array($level, ['full','custom'], true)) self::redirect_notice('invalid');
        $permissions = ($role !== 'class_aide' || $level === 'full') ? self::full_permissions() : self::normalize_permissions($_POST['permissions'] ?? []);
        if (!$permissions) self::redirect_notice('permissions_required');
        $starts = self::parsed_date($_POST['starts_on'] ?? ''); $expires = self::parsed_date($_POST['expires_on'] ?? '', true);
        if ($starts && $expires && $expires < $starts) self::redirect_notice('invalid_dates');
        $now = current_time('mysql', true); $user = get_user_by('email', $email);
        $existing = $wpdb->get_row($wpdb->prepare("SELECT * FROM {$wpdb->prefix}mb_class_staff_access WHERE class_id=%d AND account_email=%s", $class_id, $email), ARRAY_A);
        $data = ['user_id'=>$user?(int)$user->ID:0,'display_name'=>$name,'staff_role'=>$role,'access_level'=>$level,'permissions_json'=>wp_json_encode($permissions),'starts_at'=>$starts,'expires_at'=>$expires,'status'=>'invited','invited_by'=>$actor,'updated_at'=>$now,'accepted_at'=>null];
        if ($existing) { $wpdb->update($wpdb->prefix.'mb_class_staff_access', $data, ['id'=>(int)$existing['id']]); $staff_id=(int)$existing['id']; }
        else { $data += ['class_id'=>$class_id,'account_email'=>$email,'created_at'=>$now]; $wpdb->insert($wpdb->prefix.'mb_class_staff_access', $data); $staff_id=(int)$wpdb->insert_id; }
        if (!$staff_id) self::redirect_notice('save_failed');
        $wpdb->update($wpdb->prefix.'mb_class_staff_invites', ['status'=>'replaced'], ['staff_access_id'=>$staff_id,'status'=>'pending']);
        $token = wp_generate_password(48, false, false);
        $invite_expires = gmdate('Y-m-d H:i:s', time()+self::INVITE_LIFETIME);
        $invite_saved=$wpdb->insert($wpdb->prefix.'mb_class_staff_invites', ['staff_access_id'=>$staff_id,'token_hash'=>hash('sha256',$token),'invited_email'=>$email,'status'=>'pending','expires_at'=>$invite_expires,'created_by'=>$actor,'created_at'=>$now]);
        if(!$invite_saved) self::redirect_notice('save_failed');
        $class = $wpdb->get_row($wpdb->prepare("SELECT name,section_name FROM {$wpdb->prefix}mb_classes WHERE id=%d", $class_id), ARRAY_A);
        $role_label = ['co_teacher'=>'Co-Teacher','substitute'=>'Substitute Teacher','class_aide'=>'Class Aide'][$role];
        $subject = 'You are invited to assist in a MathBinder classroom';
        $message = sprintf("%s has invited you to assist with %s%s in MathBinder as a %s. Your classroom access has already been prepared.\n\nAccept your secure invitation:\n%s\n\nThis single-use link expires in 7 days. You will use your own login; the primary teacher's password and account are never shared.", wp_get_current_user()->display_name, $class['name'], $class['section_name']?' · '.$class['section_name']:'', $role_label, self::invitation_url($token));
        $sent = wp_mail($email, $subject, $message);
        MathBinder_Audit_Log::record('invite_class_staff','class_staff',$staff_id,['class_id'=>$class_id,'email'=>$email,'role'=>$role,'access_level'=>$level,'permissions'=>$permissions,'email_sent'=>(bool)$sent],'class',$class_id);
        self::redirect_notice($sent ? 'invited' : 'email_failed');
    }

    public static function handle_update() {
        if (!is_user_logged_in()) wp_die('Teacher access required.', 'Teacher access required', ['response'=>403]);
        check_admin_referer('mb_update_class_staff', 'mb_staff_update_nonce');
        global $wpdb;
        $staff_id = absint($_POST['staff_id'] ?? 0);
        $record = $wpdb->get_row($wpdb->prepare("SELECT * FROM {$wpdb->prefix}mb_class_staff_access WHERE id=%d", $staff_id), ARRAY_A);
        if (!$record || !self::primary_can_manage(get_current_user_id(), $record['class_id'])) wp_die('Only the primary teacher or an administrator may change staff access.', 'Staff management unavailable', ['response'=>403]);
        $status = sanitize_key(wp_unslash($_POST['staff_status'] ?? 'active'));
        $level = sanitize_key(wp_unslash($_POST['access_level'] ?? $record['access_level']));
        if (!in_array($status,['active','suspended','removed'],true) || !in_array($level,['full','custom'],true)) self::redirect_notice('invalid');
        $permissions = ($level==='full') ? self::full_permissions() : self::normalize_permissions($_POST['permissions'] ?? []);
        if (!$permissions && $status !== 'removed') self::redirect_notice('permissions_required');
        $starts=self::parsed_date($_POST['starts_on']??''); $expires=self::parsed_date($_POST['expires_on']??'',true);
        if ($starts && $expires && $expires < $starts) self::redirect_notice('invalid_dates');
        $wpdb->update($wpdb->prefix.'mb_class_staff_access',['access_level'=>$level,'permissions_json'=>wp_json_encode($permissions),'starts_at'=>$starts,'expires_at'=>$expires,'status'=>$status,'updated_at'=>current_time('mysql',true)],['id'=>$staff_id]);
        MathBinder_Audit_Log::record('update_class_staff','class_staff',$staff_id,['class_id'=>(int)$record['class_id'],'status'=>$status,'access_level'=>$level,'permissions'=>$permissions],'class',(int)$record['class_id']);
        self::redirect_notice('updated');
    }

    public static function handle_remove() {
        if (!is_user_logged_in()) wp_die('Teacher access required.', 'Teacher access required', ['response'=>403]);
        check_admin_referer('mb_remove_class_staff', 'mb_remove_staff_nonce');
        global $wpdb;
        $staff_id = absint($_POST['staff_id'] ?? 0);
        $record = $wpdb->get_row($wpdb->prepare("SELECT * FROM {$wpdb->prefix}mb_class_staff_access WHERE id=%d", $staff_id), ARRAY_A);
        if (!$record || !self::primary_can_manage(get_current_user_id(), $record['class_id'])) wp_die('Only the primary teacher or an administrator may remove classroom staff.', 'Staff management unavailable', ['response'=>403]);
        if ($record['status'] === 'removed') self::redirect_notice('remove_missing');
        $now = current_time('mysql', true);
        $updated = $wpdb->update($wpdb->prefix.'mb_class_staff_access', ['status'=>'removed','updated_at'=>$now], ['id'=>$staff_id], ['%s','%s'], ['%d']);
        if ($updated === false) self::redirect_notice('remove_failed');
        $wpdb->update($wpdb->prefix.'mb_class_staff_invites', ['status'=>'revoked'], ['staff_access_id'=>$staff_id,'status'=>'pending']);
        MathBinder_Audit_Log::record('remove_class_staff','class_staff',$staff_id,['class_id'=>(int)$record['class_id'],'user_id'=>(int)$record['user_id'],'account_preserved'=>true],'class',(int)$record['class_id']);
        self::redirect_notice('removed');
    }

    private static function invite_from_token($token) {
        global $wpdb;
        if (!$token) return null;
        return $wpdb->get_row($wpdb->prepare("SELECT i.*,s.class_id,s.account_email,s.display_name,s.staff_role,s.permissions_json,c.name AS class_name,c.section_name FROM {$wpdb->prefix}mb_class_staff_invites i JOIN {$wpdb->prefix}mb_class_staff_access s ON s.id=i.staff_access_id JOIN {$wpdb->prefix}mb_classes c ON c.id=s.class_id WHERE i.token_hash=%s AND i.status='pending' AND i.expires_at>=%s", hash('sha256',$token), current_time('mysql',true)), ARRAY_A);
    }

    public static function handle_accept() {
        check_admin_referer('mb_accept_class_staff', 'mb_accept_staff_nonce');
        global $wpdb;
        $token = sanitize_text_field(wp_unslash($_POST['staff_invitation'] ?? '')); $invite=self::invite_from_token($token);
        if (!$invite) wp_die('This invitation is invalid, expired, or has already been used.', 'Invitation unavailable', ['response'=>400]);
        $user = wp_get_current_user();
        if (!$user->exists()) {
            $password=(string)($_POST['account_password']??''); $name=sanitize_text_field(wp_unslash($_POST['account_name']??$invite['display_name']));
            if (strlen($password)<12 || !$name) wp_die('Enter your name and a password of at least 12 characters.', 'Account information required', ['response'=>400]);
            if (get_user_by('email',$invite['account_email'])) wp_die('An account already uses this email. Log in first, then reopen the invitation.', 'Log in required', ['response'=>409]);
            $login=sanitize_user(strstr($invite['account_email'],'@',true),true); if(!$login)$login='mathbinder-staff'; $base=$login; $n=1; while(username_exists($login))$login=$base.$n++;
            $user_id=wp_create_user($login,$password,$invite['account_email']);
            if(is_wp_error($user_id))wp_die(esc_html($user_id->get_error_message()),'Account could not be created',['response'=>400]);
            wp_update_user(['ID'=>$user_id,'display_name'=>$name,'first_name'=>$name,'role'=>'mb_class_staff']);
            wp_set_current_user($user_id); wp_set_auth_cookie($user_id,true); $user=get_userdata($user_id);
        }
        if (strtolower($user->user_email)!==strtolower($invite['account_email'])) wp_die('This invitation was sent to a different email address.', 'Invitation email mismatch', ['response'=>403]);
        $now=current_time('mysql',true);
        $wpdb->update($wpdb->prefix.'mb_class_staff_access',['user_id'=>(int)$user->ID,'status'=>'active','accepted_at'=>$now,'updated_at'=>$now],['id'=>(int)$invite['staff_access_id']]);
        $wpdb->update($wpdb->prefix.'mb_class_staff_invites',['status'=>'accepted','accepted_at'=>$now],['id'=>(int)$invite['id']]);
        if (!in_array('mb_teacher',(array)$user->roles,true) && !in_array('mb_school_admin',(array)$user->roles,true) && !in_array('administrator',(array)$user->roles,true)) $user->add_role('mb_class_staff');
        MathBinder_Identity_Service::assign_role((int)$user->ID,'teacher','class',(int)$invite['class_id'],'active','staff_invitation');
        MathBinder_Audit_Log::record('accept_class_staff_invitation','class_staff',(int)$invite['staff_access_id'],['class_id'=>(int)$invite['class_id']],'class',(int)$invite['class_id']);
        wp_safe_redirect(add_query_arg('staff_joined','1',home_url('/'.MathBinder_Teacher_Dashboard::PAGE_SLUG.'/'))); exit;
    }

    public static function invitation_shortcode() {
        $token=sanitize_text_field(wp_unslash($_GET['staff_invitation']??'')); $invite=self::invite_from_token($token);
        if(!$invite)return '<main class="mb-staff-invite"><section><h1>Invitation unavailable</h1><p>This invitation is invalid, expired, or has already been used. Ask the primary teacher to send a new invitation.</p></section></main>';
        $role=['co_teacher'=>'Co-Teacher','substitute'=>'Substitute Teacher','class_aide'=>'Class Aide'][$invite['staff_role']]??'Classroom Staff';
        if(is_user_logged_in() && strtolower(wp_get_current_user()->user_email)!==strtolower($invite['account_email']))return '<main class="mb-staff-invite"><section><h1>Different account signed in</h1><p>This invitation belongs to '.esc_html($invite['account_email']).'. Log out, then reopen the invitation with the correct account.</p><a href="'.esc_url(wp_logout_url(self::invitation_url($token))).'">Log out</a></section></main>';
        ob_start(); ?><main class="mb-staff-invite"><section><span>MathBinder classroom invitation</span><h1>Join <?php echo esc_html($invite['class_name']); ?></h1><p><?php echo esc_html(trim($invite['section_name'].' · '.$role,' ·')); ?></p><p>Your classroom permissions are already prepared. You will use your own secure identity; you will not enter the primary teacher's personal account.</p>
        <?php if(!is_user_logged_in() && get_user_by('email',$invite['account_email'])): ?><a class="mb-staff-primary" href="<?php echo esc_url(MathBinder_Frontend_Auth::login_url(self::invitation_url($token))); ?>">Log in to accept</a>
        <?php else: ?><form method="post" action="<?php echo esc_url(admin_url('admin-post.php')); ?>"><input type="hidden" name="action" value="mb_accept_class_staff"><input type="hidden" name="staff_invitation" value="<?php echo esc_attr($token); ?>"><?php wp_nonce_field('mb_accept_class_staff','mb_accept_staff_nonce'); ?><?php if(!is_user_logged_in()): ?><label>Your name<input type="text" name="account_name" value="<?php echo esc_attr($invite['display_name']); ?>" required></label><label>Create a password<input type="password" name="account_password" minlength="12" autocomplete="new-password" required><small>Use at least 12 characters.</small></label><?php endif; ?><button class="mb-staff-primary" type="submit">Accept Invitation</button></form><?php endif; ?></section></main><?php return ob_get_clean();
    }

    public static function dashboard_section(array $classes, $notice='') {
        $owned=array_values(array_filter($classes,function($c){return self::primary_can_manage(get_current_user_id(),$c['id']);}));
        if(!$owned)return '';
        $staff=array_values(array_filter(self::staff_for_classes(wp_list_pluck($owned,'id')),function($person){return $person['status']!=='removed';})); $messages=['invited'=>'The classroom staff invitation was sent.','email_failed'=>'Access was saved, but WordPress could not send the invitation email. Check site email delivery, then resend.','updated'=>'The staff member’s access was updated.','removed'=>'The staff member was removed from this class. Their account and access to other classes were preserved.','remove_missing'=>'That active staff access could not be found.','remove_failed'=>'The staff member could not be removed. Please try again.','invalid'=>'Please check the staff information and try again.','permissions_required'=>'Select at least one permission for custom access.','invalid_dates'=>'The expiration date must be on or after the start date.','save_failed'=>'The staff invitation could not be saved.','session_expired'=>'Your secure form session expired. You are still signed in; reopen the form and try again.','unexpected_error'=>'MathBinder could not finish the invitation. You are still signed in, and no classroom permissions were changed.'];
        ob_start(); ?><section id="class-staff" class="mb-teacher-panel mb-class-staff-panel"><div class="mb-teacher-heading"><div><small>Delegated classroom access</small><h2>Class Staff</h2><p>Invite co-teachers, substitutes, and aides with their own secure login and only the classroom permissions you choose.</p></div></div><?php if(isset($messages[$notice])):?><div class="mb-teacher-review-notice <?php echo in_array($notice,['invited','updated','removed'],true)?'is-success':'is-error'; ?>" role="status"><?php echo esc_html($messages[$notice]); ?></div><?php endif; ?>
        <details class="mb-staff-editor" <?php echo in_array($notice,['invalid','permissions_required','invalid_dates','save_failed'],true)?'open':''; ?>><summary>Invite Classroom Staff</summary><form method="post" action="<?php echo esc_url(admin_url('admin-post.php')); ?>"><input type="hidden" name="action" value="mb_invite_class_staff"><?php wp_nonce_field('mb_invite_class_staff','mb_staff_nonce'); ?><div class="mb-class-form-grid"><label>Class<select name="class_id" required><?php foreach($owned as $c):?><option value="<?php echo absint($c['id']); ?>"><?php echo esc_html($c['name'].($c['section_name']?' · '.$c['section_name']:'')); ?></option><?php endforeach;?></select></label><label>Name<input name="staff_name" maxlength="190" required></label><label>Email address<input type="email" name="staff_email" required></label><label>Role<select name="staff_role"><option value="co_teacher">Co-Teacher</option><option value="substitute">Substitute Teacher</option><option value="class_aide" selected>Class Aide</option></select></label><label>Access level<select name="access_level"><option value="custom">Custom Access</option><option value="full">Full Classroom Access</option></select></label><label>Start date (optional)<input type="date" name="starts_on"></label><label>Expiration date (optional)<input type="date" name="expires_on"></label></div><fieldset><legend>Custom aide permissions</legend><p>Full access and teacher roles automatically receive all classroom permissions below. Billing, ownership, and staff management are always excluded.</p><div class="mb-staff-permissions"><?php foreach(self::$permissions as $key=>$label):?><label><input type="checkbox" name="permissions[]" value="<?php echo esc_attr($key); ?>"> <?php echo esc_html($label); ?></label><?php endforeach;?></div></fieldset><button class="mb-teacher-primary" type="submit">Send Secure Invitation</button></form></details>
        <?php if($staff):?><div class="mb-staff-list"><?php foreach($staff as $person):$perms=self::normalize_permissions(json_decode((string)$person['permissions_json'],true));?><article><div><small><?php echo esc_html($person['class_name'].($person['section_name']?' · '.$person['section_name']:'')); ?></small><h3><?php echo esc_html($person['user_display_name']?:$person['display_name']); ?></h3><p><?php echo esc_html($person['account_email']); ?> · <?php echo esc_html(ucwords(str_replace('_',' ',$person['staff_role']))); ?></p><span class="mb-staff-status is-<?php echo esc_attr($person['status']); ?>"><?php echo esc_html(ucwords($person['status'])); ?></span></div><div class="mb-staff-actions"><details><summary>Manage access</summary><form method="post" action="<?php echo esc_url(admin_url('admin-post.php')); ?>"><input type="hidden" name="action" value="mb_update_class_staff"><input type="hidden" name="staff_id" value="<?php echo absint($person['id']); ?>"><?php wp_nonce_field('mb_update_class_staff','mb_staff_update_nonce'); ?><label>Status<select name="staff_status"><?php foreach(['active','suspended'] as $s):?><option value="<?php echo esc_attr($s); ?>" <?php selected($person['status'],$s); ?>><?php echo esc_html(ucwords($s)); ?></option><?php endforeach;?></select></label><label>Access level<select name="access_level"><option value="custom" <?php selected($person['access_level'],'custom'); ?>>Custom</option><option value="full" <?php selected($person['access_level'],'full'); ?>>Full Classroom Access</option></select></label><label>Start date<input type="date" name="starts_on" value="<?php echo esc_attr($person['starts_at']?substr($person['starts_at'],0,10):''); ?>"></label><label>Expiration date<input type="date" name="expires_on" value="<?php echo esc_attr($person['expires_at']?substr($person['expires_at'],0,10):''); ?>"></label><div class="mb-staff-permissions"><?php foreach(self::$permissions as $key=>$label):?><label><input type="checkbox" name="permissions[]" value="<?php echo esc_attr($key); ?>" <?php checked(in_array($key,$perms,true)); ?>> <?php echo esc_html($label); ?></label><?php endforeach;?></div><button type="submit">Save Access</button></form></details><form class="mb-remove-staff-form" method="post" action="<?php echo esc_url(admin_url('admin-post.php')); ?>" onsubmit="return confirm('Remove this staff member from this class? Their account and other class access will be preserved.');"><input type="hidden" name="action" value="mb_remove_class_staff"><input type="hidden" name="staff_id" value="<?php echo absint($person['id']); ?>"><?php wp_nonce_field('mb_remove_class_staff','mb_remove_staff_nonce'); ?><button type="submit">Remove Staff</button></form></div></article><?php endforeach;?></div><?php endif;?></section><?php return ob_get_clean();
    }
}
