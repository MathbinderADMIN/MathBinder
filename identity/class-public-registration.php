<?php
if (!defined('ABSPATH')) exit;

/** Controlled public registration for MathBinder's permanent identities. */
final class MathBinder_Public_Registration {
    const SIGNUP_SLUG = 'sign-up';
    const ORG_SLUG = 'school-district-request';

    public static function register() {
        add_shortcode('mathbinder_signup', [__CLASS__, 'signup_shortcode']);
        add_shortcode('mathbinder_organization_request', [__CLASS__, 'organization_shortcode']);
        add_action('admin_post_nopriv_mb_public_register', [__CLASS__, 'handle_register']);
        add_action('admin_post_nopriv_mb_organization_request', [__CLASS__, 'handle_organization_request']);
        add_action('template_redirect', [__CLASS__, 'redirect_class_enrollment_link'], 2);
        add_action('admin_menu', [__CLASS__, 'admin_menu']);
        add_action('admin_post_mb_review_organization_request', [__CLASS__, 'handle_request_review']);
    }

    public static function ensure_pages() {
        self::ensure_page(self::SIGNUP_SLUG, 'Sign Up', '[mathbinder_signup]');
        self::ensure_page(self::ORG_SLUG, 'School or District Access', '[mathbinder_organization_request]');
    }

    private static function ensure_page($slug, $title, $shortcode) {
        $page = get_page_by_path($slug, OBJECT, 'page');
        $data = ['post_type'=>'page','post_status'=>'publish','post_title'=>$title,'post_name'=>$slug,'post_content'=>$shortcode];
        if ($page) $data['ID'] = $page->ID;
        return wp_insert_post($data);
    }

    public static function signup_url($args = []) { return add_query_arg($args, home_url('/'.self::SIGNUP_SLUG.'/')); }

    public static function redirect_class_enrollment_link() {
        if (!is_page('student-dashboard') || empty($_GET['class_code'])) return;
        wp_safe_redirect(self::signup_url(['account_type'=>'student','class_code'=>strtoupper(sanitize_text_field(wp_unslash($_GET['class_code']))) ]));
        exit;
    }

    private static function class_by_code($code) {
        global $wpdb;
        return $wpdb->get_row($wpdb->prepare("SELECT c.*,u.display_name teacher_name FROM {$wpdb->prefix}mb_classes c LEFT JOIN {$wpdb->users} u ON u.ID=c.teacher_user_id WHERE c.class_code=%s AND c.status='active'", strtoupper(sanitize_text_field($code))), ARRAY_A);
    }

    public static function signup_shortcode() {
        $type = isset($_GET['account_type']) ? sanitize_key(wp_unslash($_GET['account_type'])) : '';
        $code = isset($_GET['class_code']) ? strtoupper(sanitize_text_field(wp_unslash($_GET['class_code']))) : '';
        if (is_user_logged_in() && $code) {
            $share=self::signup_url(['account_type'=>'student','class_code'=>$code]);
            return '<main class="mb-login-page"><section class="mb-login-card mb-signup-card"><p class="mb-login-eyebrow">Student enrollment</p><h1>Share this classroom link</h1><p>Students use this link to create their own account and password for your class.</p><label class="mb-share-link">Enrollment link<input type="url" readonly value="'.esc_attr($share).'" onclick="this.select()"></label><button class="mb-login-submit" type="button" onclick="navigator.clipboard.writeText(this.previousElementSibling.querySelector(\'input\').value);this.textContent=\'Copied\';">Copy Enrollment Link</button><p class="mb-login-help">Class code: <strong>'.esc_html($code).'</strong></p><a href="'.esc_url(MathBinder_Frontend_Auth::role_destination(wp_get_current_user())).'">Return to your dashboard</a></section></main>';
        }
        if (is_user_logged_in()) return '<main class="mb-login-page"><section class="mb-login-card"><h1>You already have an account.</h1><a class="mb-login-submit" href="'.esc_url(MathBinder_Frontend_Auth::role_destination(wp_get_current_user())).'">Continue</a></section></main>';
        if ($code) $type = 'student';
        $error = isset($_GET['signup_error']) ? sanitize_key(wp_unslash($_GET['signup_error'])) : '';
        $messages = ['required'=>'Complete every required field.','email'=>'Enter a valid email address.','duplicate'=>'An account already uses that email address. Log in or reset the password instead.','existing_invited'=>'Your invitation matches an existing MathBinder account. Log in with that account, or reset its password. Your class enrollment will activate automatically after you sign in.','password'=>'Use a password containing at least 10 characters.','class'=>'That classroom code is invalid or unavailable.','save'=>'MathBinder could not create the account. Please try again.'];
        ob_start(); ?>
        <main class="mb-login-page"><section class="mb-login-card mb-signup-card">
          <p class="mb-login-eyebrow">Find it. Learn it. Master it.</p><h1>Create your MathBinder account</h1>
          <?php if(isset($messages[$error])):?><div class="mb-login-error" role="alert"><?php echo esc_html($messages[$error]);?></div><?php if($error==='existing_invited'):?><div class="mb-login-actions"><a class="mb-login-submit" href="<?php echo esc_url(MathBinder_Frontend_Auth::login_url(home_url('/student-dashboard/')));?>">Log In</a><a class="mb-login-secondary" href="<?php echo esc_url(wp_lostpassword_url(MathBinder_Frontend_Auth::login_url()));?>">Reset Password</a></div><?php endif;endif;?>
          <?php if(!$type):?><div class="mb-account-paths">
            <a href="<?php echo esc_url(self::signup_url(['account_type'=>'student']));?>"><strong>Student</strong><span>Join with a classroom code</span></a>
            <a href="<?php echo esc_url(class_exists('MathBinder_Family_Checkout') ? MathBinder_Family_Checkout::signup_url() : self::signup_url(['account_type'=>'parent']));?>"><strong>Parent/Guardian</strong><span>Create a family account</span></a>
            <a href="<?php echo esc_url(self::signup_url(['account_type'=>'teacher']));?>"><strong>Teacher</strong><span>Create an independent teacher workspace</span></a>
            <a href="<?php echo esc_url(home_url('/'.self::ORG_SLUG.'/'));?>"><strong>School or District</strong><span>Request controlled organization setup</span></a>
          </div><?php elseif($type==='parent'): wp_safe_redirect(class_exists('MathBinder_Family_Checkout') ? MathBinder_Family_Checkout::signup_url() : self::signup_url()); exit;
          elseif(in_array($type,['student','teacher'],true)):?>
          <form class="mb-login-form" method="post" action="<?php echo esc_url(admin_url('admin-post.php'));?>">
            <input type="hidden" name="action" value="mb_public_register"><input type="hidden" name="account_type" value="<?php echo esc_attr($type);?>"><?php wp_nonce_field('mb_public_register','mb_register_nonce');?>
            <label>First name<input type="text" name="first_name" maxlength="80" required></label><label>Last name<input type="text" name="last_name" maxlength="80" required></label>
            <label>Email address<input type="email" name="email" required autocomplete="email"></label>
            <?php if($type==='student'):?><label>Classroom code<input type="text" name="class_code" maxlength="24" required value="<?php echo esc_attr($code);?>" autocapitalize="characters"></label><?php endif;?>
            <label>Create password<input type="password" name="password" minlength="10" required autocomplete="new-password"></label>
            <button class="mb-login-submit" type="submit">Create <?php echo $type==='student'?'Student':'Teacher';?> Account</button>
          </form><p class="mb-login-help"><a href="<?php echo esc_url(self::signup_url());?>">Choose a different account type</a></p><?php endif;?>
        </section></main><?php return ob_get_clean();
    }

    public static function handle_register() {
        if (!isset($_POST['mb_register_nonce']) || !wp_verify_nonce(sanitize_text_field(wp_unslash($_POST['mb_register_nonce'])),'mb_public_register')) self::fail('required');
        $type=sanitize_key($_POST['account_type']??''); $first=sanitize_text_field(wp_unslash($_POST['first_name']??'')); $last=sanitize_text_field(wp_unslash($_POST['last_name']??'')); $email=sanitize_email(wp_unslash($_POST['email']??'')); $password=(string)wp_unslash($_POST['password']??''); $code=strtoupper(sanitize_text_field(wp_unslash($_POST['class_code']??'')));
        if(!in_array($type,['student','teacher'],true)||$first===''||$last==='') self::fail('required',$type,$code);
        if(!is_email($email)) self::fail('email',$type,$code);
        $class = $type==='student' ? self::class_by_code($code) : null; if($type==='student'&&!$class) self::fail('class',$type,$code);
        if(email_exists($email)) {
            if($type==='student' && $class){
                global $wpdb; $now=current_time('mysql',true);
                $pending=$wpdb->get_var($wpdb->prepare("SELECT id FROM {$wpdb->prefix}mb_student_invitations WHERE class_id=%d AND invited_email=%s",$class['id'],strtolower($email)));
                if(!$pending)$wpdb->insert($wpdb->prefix.'mb_student_invitations',['class_id'=>$class['id'],'invited_email'=>strtolower($email),'status'=>'invited','invited_by'=>0,'created_at'=>$now,'updated_at'=>$now,'accepted_at'=>null]);
            }
            self::fail($type==='student'?'existing_invited':'duplicate',$type,$code);
        }
        if(strlen($password)<10) self::fail('password',$type,$code);
        $base=sanitize_user(strtok($email,'@'),true); $login=$base?:'mathbinder'; $i=1; while(username_exists($login)) $login=$base.$i++;
        $uid=wp_create_user($login,$password,$email); if(is_wp_error($uid)) self::fail('save',$type,$code);
        wp_update_user(['ID'=>$uid,'first_name'=>$first,'last_name'=>$last,'display_name'=>trim($first.' '.$last),'role'=>$type==='student'?'mb_student':'mb_teacher']);
        update_user_meta($uid,'mb_account_activation_status','active'); update_user_meta($uid,'mb_first_login_completed_at',current_time('mysql',true));
        if($type==='student') { global $wpdb; $now=current_time('mysql',true); $existing=$wpdb->get_row($wpdb->prepare("SELECT id FROM {$wpdb->prefix}mb_enrollments WHERE class_id=%d AND (user_id=%d OR invited_email=%s) AND role_key='student' ORDER BY id DESC LIMIT 1",$class['id'],$uid,$email),ARRAY_A); if($existing)$wpdb->update($wpdb->prefix.'mb_enrollments',['user_id'=>$uid,'status'=>'active','source'=>'student_code','updated_at'=>$now],['id'=>$existing['id']]); else $wpdb->insert($wpdb->prefix.'mb_enrollments',['class_id'=>$class['id'],'user_id'=>$uid,'invited_email'=>$email,'role_key'=>'student','status'=>'active','source'=>'student_code','approved_by'=>0,'created_at'=>$now,'updated_at'=>$now]); $wpdb->update($wpdb->prefix.'mb_student_invitations',['status'=>'accepted','accepted_at'=>$now,'updated_at'=>$now],['class_id'=>$class['id'],'invited_email'=>strtolower($email)],['%s','%s','%s'],['%d','%s']); MathBinder_Identity_Service::assign_role($uid,'student','class',$class['id']); MathBinder_Verification_Service::authorize_minor($uid,0,'','school',$class['organization_id']); }
        else { wp_set_current_user($uid); MathBinder_Organization_Service::create_organization(trim($first.' '.$last).' Classroom','independent_teacher'); }
        wp_set_current_user($uid); wp_set_auth_cookie($uid,true,is_ssl()); wp_safe_redirect(MathBinder_Frontend_Auth::role_destination(get_userdata($uid))); exit;
    }

    private static function fail($error,$type='',$code='') { wp_safe_redirect(self::signup_url(array_filter(['signup_error'=>$error,'account_type'=>$type,'class_code'=>$code]))); exit; }

    public static function organization_shortcode() {
        $sent=isset($_GET['request_sent']); ob_start();?><main class="mb-login-page"><section class="mb-login-card mb-signup-card"><p class="mb-login-eyebrow">Controlled organization onboarding</p><h1>School or district access</h1><?php if($sent):?><div class="mb-registration-success"><strong>Request received.</strong><p>MathBinder will review the organization before creating accounts, licenses, or workspaces.</p></div><?php else:?><p>Submit an official contact request. This form does not automatically create an organization.</p><form class="mb-login-form" method="post" action="<?php echo esc_url(admin_url('admin-post.php'));?>"><input type="hidden" name="action" value="mb_organization_request"><?php wp_nonce_field('mb_organization_request','mb_org_nonce');?><label>Organization type<select name="organization_type"><option value="school">School</option><option value="district">District</option></select></label><label>School or district name<input type="text" name="organization_name" required maxlength="190"></label><label>Contact name<input type="text" name="contact_name" required maxlength="190"></label><label>Official work email<input type="email" name="contact_email" required></label><label>Website<input type="url" name="website"></label><label>Estimated teachers<input type="number" name="teacher_count" min="1"></label><label>Estimated students<input type="number" name="student_count" min="1"></label><label>Tell us about your requested setup<textarea name="notes" rows="5" maxlength="3000"></textarea></label><button class="mb-login-submit">Submit for Review</button></form><?php endif;?></section></main><?php return ob_get_clean();
    }

    public static function handle_organization_request() {
        if(!isset($_POST['mb_org_nonce'])||!wp_verify_nonce(sanitize_text_field(wp_unslash($_POST['mb_org_nonce'])),'mb_organization_request')) wp_die('The request expired.');
        $email=sanitize_email(wp_unslash($_POST['contact_email']??'')); if(!is_email($email)) wp_die('Enter a valid official work email.');
        $requests=get_option('mb_organization_requests_v1',[]); if(!is_array($requests))$requests=[]; $requests[]=['id'=>wp_generate_uuid4(),'type'=>sanitize_key($_POST['organization_type']??'school'),'name'=>sanitize_text_field(wp_unslash($_POST['organization_name']??'')),'contact_name'=>sanitize_text_field(wp_unslash($_POST['contact_name']??'')),'email'=>$email,'website'=>esc_url_raw(wp_unslash($_POST['website']??'')),'teacher_count'=>absint($_POST['teacher_count']??0),'student_count'=>absint($_POST['student_count']??0),'notes'=>sanitize_textarea_field(wp_unslash($_POST['notes']??'')),'status'=>'pending_review','submitted_at'=>current_time('mysql',true)]; update_option('mb_organization_requests_v1',$requests,false); wp_mail(get_option('admin_email'),'New MathBinder school or district request','A controlled organization request is awaiting review in MathBinder.'); wp_safe_redirect(add_query_arg('request_sent','1',home_url('/'.self::ORG_SLUG.'/'))); exit;
    }

    public static function admin_menu() {
        add_submenu_page('edit.php?post_type=mb_binder_page','Organization Requests','Organization Requests','manage_options','mb-organization-requests',[__CLASS__,'admin_requests_page']);
    }

    public static function admin_requests_page() {
        if(!current_user_can('manage_options'))wp_die('Administrator access required.');
        $requests=get_option('mb_organization_requests_v1',[]); if(!is_array($requests))$requests=[];
        echo '<div class="wrap"><h1>School and District Requests</h1><p>Requests never create an organization automatically. Review each official contact before creating a school or district workspace.</p>';
        if(!$requests){echo '<p>No requests have been submitted.</p></div>';return;}
        echo '<table class="widefat striped"><thead><tr><th>Organization</th><th>Contact</th><th>Size</th><th>Status</th><th>Review</th></tr></thead><tbody>';
        foreach(array_reverse($requests) as $request){echo '<tr><td><strong>'.esc_html($request['name']).'</strong><br>'.esc_html(ucfirst($request['type'])).'<br><a href="'.esc_url($request['website']).'">'.esc_html($request['website']).'</a></td><td>'.esc_html($request['contact_name']).'<br><a href="mailto:'.esc_attr($request['email']).'">'.esc_html($request['email']).'</a></td><td>'.absint($request['teacher_count']).' teachers<br>'.absint($request['student_count']).' students</td><td>'.esc_html(ucwords(str_replace('_',' ',$request['status']))).'</td><td><form method="post" action="'.esc_url(admin_url('admin-post.php')).'"><input type="hidden" name="action" value="mb_review_organization_request"><input type="hidden" name="request_id" value="'.esc_attr($request['id']).'">';wp_nonce_field('mb_review_organization_request_'.$request['id'],'mb_review_nonce');echo '<button class="button button-primary" name="decision" value="approved" type="submit">Approve for Setup</button> <button class="button" name="decision" value="declined" type="submit">Decline</button></form></td></tr>';}
        echo '</tbody></table></div>';
    }

    public static function handle_request_review() {
        if(!current_user_can('manage_options'))wp_die('Administrator access required.'); $id=sanitize_text_field(wp_unslash($_POST['request_id']??'')); check_admin_referer('mb_review_organization_request_'.$id,'mb_review_nonce'); $decision=sanitize_key($_POST['decision']??''); if(!in_array($decision,['approved','declined'],true))$decision='pending_review';
        $requests=get_option('mb_organization_requests_v1',[]); foreach($requests as &$request)if(hash_equals((string)$request['id'],$id)){$request['status']=$decision;$request['reviewed_at']=current_time('mysql',true);$request['reviewed_by']=get_current_user_id();} unset($request); update_option('mb_organization_requests_v1',$requests,false); wp_safe_redirect(admin_url('edit.php?post_type=mb_binder_page&page=mb-organization-requests'));exit;
    }
}
