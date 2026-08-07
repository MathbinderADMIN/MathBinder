<?php
if (!defined('ABSPATH')) exit;

final class MathBinder_Identity_Admin {
    public static function register() {
        add_action('admin_menu', [__CLASS__, 'menu']);
        add_action('admin_post_mb_assign_identity_role', [__CLASS__, 'handle_assign']);
        add_action('admin_post_mb_request_identity_transfer', [__CLASS__, 'handle_transfer']);
        add_action('admin_post_mb_create_test_parent', [__CLASS__, 'handle_create_test_parent']);
        add_action('admin_post_mb_create_test_teacher', [__CLASS__, 'handle_create_test_teacher']);
    }
    public static function menu() {
        add_users_page('MathBinder Identities', 'MathBinder Identities', MathBinder_Capabilities::MANAGE_IDENTITIES, 'mathbinder-identities', [__CLASS__, 'render']);
    }
    private static function authorize($action) {
        if (!current_user_can(MathBinder_Capabilities::MANAGE_IDENTITIES)) wp_die('Not authorized.', 403);
        check_admin_referer($action);
    }
    public static function handle_assign() {
        self::authorize('mb_assign_identity_role');
        $user = get_user_by('email', sanitize_email(wp_unslash($_POST['email'] ?? '')));
        if (!$user) wp_safe_redirect(add_query_arg('mb_identity_error', 'user', wp_get_referer()));
        else {
            MathBinder_Identity_Service::assign_role($user->ID, sanitize_key($_POST['role_key'] ?? ''), 'site', 0);
            wp_safe_redirect(add_query_arg('mb_identity_updated', '1', wp_get_referer()));
        }
        exit;
    }
    public static function handle_transfer() {
        self::authorize('mb_request_identity_transfer');
        $user = get_user_by('email', sanitize_email(wp_unslash($_POST['email'] ?? '')));
        if ($user) MathBinder_Identity_Service::request_transfer($user->ID, 'organization', absint($_POST['from_id'] ?? 0), 'organization', absint($_POST['to_id'] ?? 0));
        wp_safe_redirect(add_query_arg($user ? 'mb_transfer_requested' : 'mb_identity_error', $user ? '1' : 'user', wp_get_referer())); exit;
    }
    public static function handle_create_test_parent() {
        self::authorize('mb_create_test_parent');
        $email = sanitize_email(wp_unslash($_POST['email'] ?? ''));
        $username = sanitize_user(wp_unslash($_POST['username'] ?? ''), true);
        $first = sanitize_text_field(wp_unslash($_POST['first_name'] ?? ''));
        $last = sanitize_text_field(wp_unslash($_POST['last_name'] ?? ''));
        $password = (string) wp_unslash($_POST['password'] ?? '');
        $child_count = max(1, min(20, absint($_POST['child_count'] ?? 1)));
        $redirect = wp_get_referer() ?: admin_url('users.php?page=mathbinder-identities');
        if (!$email || !is_email($email) || !$username || !$first || !$last) {
            wp_safe_redirect(add_query_arg('mb_test_parent_error', 'required', $redirect)); exit;
        }
        if (email_exists($email) || username_exists($username)) {
            wp_safe_redirect(add_query_arg('mb_test_parent_error', 'exists', $redirect)); exit;
        }
        if (strlen($password) < 10 || !preg_match('/[A-Za-z]/', $password) || !preg_match('/\d/', $password)) {
            wp_safe_redirect(add_query_arg('mb_test_parent_error', 'password', $redirect)); exit;
        }
        $user_id = wp_insert_user([
            'user_login' => $username, 'user_email' => $email, 'user_pass' => $password,
            'first_name' => $first, 'last_name' => $last,
            'display_name' => trim($first . ' ' . $last), 'role' => 'mb_parent',
        ]);
        if (is_wp_error($user_id)) {
            wp_safe_redirect(add_query_arg('mb_test_parent_error', 'create', $redirect)); exit;
        }
        update_user_meta($user_id, 'mb_family_child_count', $child_count);
        update_user_meta($user_id, 'mb_family_subscription_status', 'test_active');
        update_user_meta($user_id, 'mb_family_account_mode', 'administrator_test');
        update_user_meta($user_id, 'mb_family_premium_source', 'administrator_test');
        MathBinder_Identity_Service::assign_role($user_id, 'parent', 'site', 0, 'active', 'administrator_test');
        MathBinder_Audit_Log::record('test_parent_created', 'identity', $user_id, ['child_count'=>$child_count, 'created_by'=>get_current_user_id()]);
        wp_safe_redirect(add_query_arg('mb_test_parent_created', '1', $redirect)); exit;
    }
    public static function handle_create_test_teacher() {
        self::authorize('mb_create_test_teacher');
        $email = sanitize_email(wp_unslash($_POST['email'] ?? ''));
        $username = sanitize_user(wp_unslash($_POST['username'] ?? ''), true);
        $first = sanitize_text_field(wp_unslash($_POST['first_name'] ?? ''));
        $last = sanitize_text_field(wp_unslash($_POST['last_name'] ?? ''));
        $password = (string) wp_unslash($_POST['password'] ?? '');
        $redirect = wp_get_referer() ?: admin_url('users.php?page=mathbinder-identities');
        if (!$email || !is_email($email) || !$username || !$first || !$last) {
            wp_safe_redirect(add_query_arg('mb_test_teacher_error', 'required', $redirect)); exit;
        }
        if (email_exists($email) || username_exists($username)) {
            wp_safe_redirect(add_query_arg('mb_test_teacher_error', 'exists', $redirect)); exit;
        }
        if (strlen($password) < 10 || !preg_match('/[A-Za-z]/', $password) || !preg_match('/\d/', $password)) {
            wp_safe_redirect(add_query_arg('mb_test_teacher_error', 'password', $redirect)); exit;
        }
        $user_id = wp_insert_user([
            'user_login' => $username, 'user_email' => $email, 'user_pass' => $password,
            'first_name' => $first, 'last_name' => $last,
            'display_name' => trim($first . ' ' . $last), 'role' => 'mb_teacher',
        ]);
        if (is_wp_error($user_id)) {
            wp_safe_redirect(add_query_arg('mb_test_teacher_error', 'create', $redirect)); exit;
        }
        update_user_meta($user_id, 'mb_teacher_account_mode', 'administrator_test');
        MathBinder_Identity_Service::assign_role($user_id, 'teacher', 'site', 0, 'active', 'administrator_test');
        MathBinder_Audit_Log::record('test_teacher_created', 'identity', $user_id, ['created_by'=>get_current_user_id()]);
        wp_safe_redirect(add_query_arg('mb_test_teacher_created', '1', $redirect)); exit;
    }
    public static function render() {
        if (!current_user_can(MathBinder_Capabilities::MANAGE_IDENTITIES)) return;
        $test_error = sanitize_key(wp_unslash($_GET['mb_test_parent_error'] ?? ''));
        $test_errors = ['required'=>'Complete every test Parent field.', 'exists'=>'That email or username is already in use.', 'password'=>'Use at least 10 characters, including a letter and a number.', 'create'=>'The test Parent account could not be created.'];
        $teacher_error = sanitize_key(wp_unslash($_GET['mb_test_teacher_error'] ?? ''));
        $teacher_errors = ['required'=>'Complete every test Teacher field.', 'exists'=>'That email or username is already in use.', 'password'=>'Use at least 10 characters, including a letter and a number.', 'create'=>'The test Teacher account could not be created.'];
        ?>
        <div class="wrap"><h1>MathBinder Identities</h1><p>Assign another workspace to an existing account. Do not create a duplicate user for a new role or school.</p>
        <?php if (!empty($_GET['mb_test_parent_created'])): ?><div class="notice notice-success is-dismissible"><p>Administrator test Parent account created successfully. No Stripe customer or subscription was created.</p></div><?php endif; ?>
        <?php if (isset($test_errors[$test_error])): ?><div class="notice notice-error"><p><?php echo esc_html($test_errors[$test_error]); ?></p></div><?php endif; ?>
        <?php if (!empty($_GET['mb_test_teacher_created'])): ?><div class="notice notice-success is-dismissible"><p>Administrator test Teacher account created successfully. No billing record was created.</p></div><?php endif; ?>
        <?php if (isset($teacher_errors[$teacher_error])): ?><div class="notice notice-error"><p><?php echo esc_html($teacher_errors[$teacher_error]); ?></p></div><?php endif; ?>
        <div class="card"><h2>Create test Parent account</h2><p>Creates a dashboard-review account only. It does not start checkout, a trial, or billing.</p><form method="post" action="<?php echo esc_url(admin_url('admin-post.php')); ?>"><?php wp_nonce_field('mb_create_test_parent'); ?><input type="hidden" name="action" value="mb_create_test_parent"><p><label>First name<br><input required type="text" name="first_name" class="regular-text"></label></p><p><label>Last name<br><input required type="text" name="last_name" class="regular-text"></label></p><p><label>Email<br><input required type="email" name="email" class="regular-text"></label></p><p><label>Username<br><input required type="text" name="username" class="regular-text" autocomplete="off"></label></p><p><label>Password<br><input required type="password" name="password" class="regular-text" minlength="10" autocomplete="new-password"></label><br><span class="description">At least 10 characters, including a letter and a number.</span></p><p><label>Test child spots<br><input required type="number" min="1" max="20" value="1" name="child_count"></label></p><?php submit_button('Create Test Parent Account'); ?></form></div>
        <div class="card"><h2>Create test Teacher account</h2><p>Creates a Teacher Dashboard review account only. It does not create a subscription or billing record.</p><form method="post" action="<?php echo esc_url(admin_url('admin-post.php')); ?>"><?php wp_nonce_field('mb_create_test_teacher'); ?><input type="hidden" name="action" value="mb_create_test_teacher"><p><label>First name<br><input required type="text" name="first_name" class="regular-text"></label></p><p><label>Last name<br><input required type="text" name="last_name" class="regular-text"></label></p><p><label>Email<br><input required type="email" name="email" class="regular-text"></label></p><p><label>Username<br><input required type="text" name="username" class="regular-text" autocomplete="off"></label></p><p><label>Password<br><input required type="password" name="password" class="regular-text" minlength="10" autocomplete="new-password"></label><br><span class="description">At least 10 characters, including a letter and a number.</span></p><?php submit_button('Create Test Teacher Account'); ?></form></div>
        <div class="card"><h2>Assign role</h2><form method="post" action="<?php echo esc_url(admin_url('admin-post.php')); ?>"><?php wp_nonce_field('mb_assign_identity_role'); ?><input type="hidden" name="action" value="mb_assign_identity_role"><p><label>Email<br><input required type="email" name="email" class="regular-text"></label></p><p><label>Role<br><select name="role_key"><option value="student">Student</option><option value="parent">Parent</option><option value="teacher">Teacher</option><option value="administrator">Administrator</option></select></label></p><?php submit_button('Assign workspace'); ?></form></div>
        <div class="card"><h2>Request transfer</h2><p>This records continuity before organization access changes. Phase 3 will supply organization names.</p><form method="post" action="<?php echo esc_url(admin_url('admin-post.php')); ?>"><?php wp_nonce_field('mb_request_identity_transfer'); ?><input type="hidden" name="action" value="mb_request_identity_transfer"><p><label>Student email<br><input required type="email" name="email" class="regular-text"></label></p><p><label>From organization ID<br><input type="number" min="0" name="from_id"></label></p><p><label>To organization ID<br><input type="number" min="0" name="to_id"></label></p><?php submit_button('Record transfer request', 'secondary'); ?></form></div></div>
        <?php
    }
}
