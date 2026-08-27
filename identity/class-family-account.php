<?php
if (!defined('ABSPATH')) exit;

/** Parent-only child roster and Family Premium status. */
final class MathBinder_Family_Account {
    public static function register() {
        add_action('admin_post_mb_add_family_child', [__CLASS__, 'handle_add_child']);
        add_action('admin_post_mb_update_family_child', [__CLASS__, 'handle_update_child']);
        add_action('admin_post_mb_reset_family_child_password', [__CLASS__, 'handle_reset_password']);
        add_action('admin_post_mb_toggle_family_child_access', [__CLASS__, 'handle_toggle_access']);
    }

    public static function is_parent($user_id = 0) {
        $user = get_user_by('id', $user_id ?: get_current_user_id());
        return $user && in_array('mb_parent', (array) $user->roles, true);
    }

    public static function children($parent_id) {
        return get_users([
            'meta_key' => 'mb_parent_user_id',
            'meta_value' => absint($parent_id),
            'orderby' => 'display_name',
            'order' => 'ASC',
        ]);
    }

    public static function render_dashboard() {
        if (!is_user_logged_in()) {
            return '<section class="mb-dashboard-gate"><h1>Log in to manage your family</h1><a class="mb-button mb-button-primary" href="' . esc_url(MathBinder_Frontend_Auth::login_url(home_url('/parents/'))) . '">Log In</a></section>';
        }
        if (!self::is_parent()) return '';

        $parent_id = get_current_user_id();
        $parent = wp_get_current_user();
        $children = self::children($parent_id);
        $seat_count = max(1, min(20, absint(get_user_meta($parent_id, 'mb_family_child_count', true))));
        $status = sanitize_key((string) get_user_meta($parent_id, 'mb_family_subscription_status', true));
        $trial_end = absint(get_user_meta($parent_id, 'mb_family_trial_ends_at', true));
        $active_statuses = ['trialing', 'active', 'checkout_complete', 'test_active'];
        $premium_active = in_array($status, $active_statuses, true);
        $status_labels = [
            'trialing' => 'Free trial', 'active' => 'Active', 'checkout_complete' => 'Checkout confirmed',
            'test_active' => 'Administrator test account',
            'checkout_pending' => 'Checkout pending', 'past_due' => 'Payment needs attention',
            'canceled' => 'Canceled', 'unpaid' => 'Unpaid', 'incomplete' => 'Incomplete',
        ];
        $notice = sanitize_key(wp_unslash($_GET['mb_family_notice'] ?? ''));
        $error = sanitize_key(wp_unslash($_GET['mb_family_error'] ?? ''));
        $notices = [
            'child_added' => 'Child account created successfully.',
            'child_updated' => 'Child account details updated.',
            'password_reset' => 'Child password updated successfully.',
            'access_paused' => 'Family Premium access paused. The student account and all saved work were preserved.',
            'access_restored' => 'Family Premium access restored for this child.',
        ];
        $errors = [
            'security' => 'The form expired. Please try again.', 'permission' => 'Only a Parent account can manage children.',
            'limit' => 'All Family Premium child spots are already assigned.', 'required' => 'Enter the child’s name, username, grade band, and password.',
            'username' => 'That username is unavailable. Choose another.', 'password' => 'Use at least 10 characters, including a letter and a number.',
            'create' => 'The child account could not be created. Please try again.',
            'child' => 'That child account is not connected to this Parent account.',
        ];

        ob_start(); ?>
        <main class="mb-family-dashboard">
            <header class="mb-family-hero">
                <div><p class="mb-family-eyebrow">Family Account</p><h1>Welcome, <?php echo esc_html($parent->first_name ?: $parent->display_name); ?></h1><p>Manage your Family Premium plan and each child’s MathBinder login.</p></div>
                <a class="mb-family-secondary" href="<?php echo esc_url(home_url('/mathbinder-account/')); ?>">Account &amp; Workspaces</a>
            </header>
            <?php if (isset($notices[$notice])): ?><div class="mb-family-message mb-family-success" role="status"><?php echo esc_html($notices[$notice]); ?></div><?php endif; ?>
            <?php if (isset($errors[$error])): ?><div class="mb-family-message mb-family-error" role="alert"><?php echo esc_html($errors[$error]); ?></div><?php endif; ?>
            <section class="mb-family-summary" aria-label="Family subscription summary">
                <article><span>Plan</span><strong>Family Premium</strong></article>
                <article><span>Status</span><strong><?php echo esc_html($status_labels[$status] ?? 'Not connected'); ?></strong><?php if ($trial_end > time()): ?><small>Trial ends <?php echo esc_html(wp_date('F j, Y', $trial_end)); ?></small><?php endif; ?></article>
                <article><span>Child spots</span><strong><?php echo esc_html(count($children) . ' of ' . $seat_count); ?></strong><small><?php echo $premium_active ? 'Premium access available' : 'Access activates after Stripe confirmation'; ?></small></article>
            </section>
            <section class="mb-family-panel">
                <div class="mb-family-panel-heading"><div><p class="mb-family-eyebrow">Children</p><h2>Your child accounts</h2></div><span><?php echo esc_html(max(0, $seat_count - count($children))); ?> spot(s) available</span></div>
                <?php if (!$children): ?><p class="mb-family-empty">No child accounts have been added yet. Create the first child login below.</p><?php endif; ?>
                <div class="mb-family-child-grid">
                    <?php foreach ($children as $child): ?>
                    <?php $family_access = get_user_meta($child->ID, 'mb_family_access_paused', true) !== 'yes'; ?>
                    <article class="mb-family-child-card">
                        <div class="mb-family-avatar" aria-hidden="true"><?php echo esc_html(strtoupper(substr($child->display_name, 0, 1))); ?></div>
                        <div class="mb-family-child-details"><h3><?php echo esc_html($child->display_name); ?></h3><p>Username: <strong><?php echo esc_html($child->user_login); ?></strong></p><p><?php echo esc_html(get_user_meta($child->ID, 'mb_grade_band', true) ?: 'Grade not set'); ?> · <?php echo $family_access && $premium_active ? 'Premium' : 'Family Premium paused'; ?></p></div>
                        <details class="mb-family-manage"><summary>Manage account</summary>
                            <form method="post" action="<?php echo esc_url(admin_url('admin-post.php')); ?>">
                                <input type="hidden" name="action" value="mb_update_family_child"><input type="hidden" name="child_id" value="<?php echo esc_attr($child->ID); ?>"><?php wp_nonce_field('mb_manage_family_child_' . $child->ID); ?>
                                <label>Grade band<select name="grade_band" required><?php foreach (['K–2','3–5','6–8','9–12'] as $band): ?><option <?php selected(get_user_meta($child->ID, 'mb_grade_band', true), $band); ?>><?php echo esc_html($band); ?></option><?php endforeach; ?></select></label>
                                <button class="mb-family-secondary" type="submit">Update Grade Band</button>
                            </form>
                            <form method="post" action="<?php echo esc_url(admin_url('admin-post.php')); ?>">
                                <input type="hidden" name="action" value="mb_reset_family_child_password"><input type="hidden" name="child_id" value="<?php echo esc_attr($child->ID); ?>"><?php wp_nonce_field('mb_manage_family_child_' . $child->ID); ?>
                                <label>New password<input name="password" type="password" minlength="10" autocomplete="new-password" required></label><label>Confirm password<input name="confirm_password" type="password" minlength="10" autocomplete="new-password" required></label>
                                <button class="mb-family-secondary" type="submit">Reset Password</button>
                            </form>
                            <form method="post" action="<?php echo esc_url(admin_url('admin-post.php')); ?>">
                                <input type="hidden" name="action" value="mb_toggle_family_child_access"><input type="hidden" name="child_id" value="<?php echo esc_attr($child->ID); ?>"><?php wp_nonce_field('mb_manage_family_child_' . $child->ID); ?>
                                <button class="mb-family-access-toggle" type="submit"><?php echo $family_access ? 'Pause Family Premium' : 'Restore Family Premium'; ?></button>
                                <small>Pausing access never deletes the student account, notes, progress, or saved work.</small>
                            </form>
                        </details>
                    </article>
                    <?php endforeach; ?>
                </div>
            </section>
            <?php if (count($children) < $seat_count): ?>
            <section class="mb-family-panel mb-family-add-child">
                <p class="mb-family-eyebrow">Add a child</p><h2>Create a student login</h2>
                <p>Create a unique username and password you can give your child. MathBinder does not require the child’s email address.</p>
                <form method="post" action="<?php echo esc_url(admin_url('admin-post.php')); ?>">
                    <input type="hidden" name="action" value="mb_add_family_child"><?php wp_nonce_field('mb_add_family_child'); ?>
                    <div class="mb-family-form-grid">
                        <div><label for="mb-child-first">First name</label><input id="mb-child-first" name="first_name" type="text" required></div>
                        <div><label for="mb-child-last">Last name</label><input id="mb-child-last" name="last_name" type="text" required></div>
                        <div><label for="mb-child-username">Username</label><input id="mb-child-username" name="username" type="text" autocomplete="off" autocapitalize="none" required></div>
                        <div><label for="mb-child-grade">Grade band</label><select id="mb-child-grade" name="grade_band" required><option value="">Choose one</option><option>K–2</option><option>3–5</option><option>6–8</option><option>9–12</option></select></div>
                        <div><label for="mb-child-password">Password</label><input id="mb-child-password" name="password" type="password" minlength="10" autocomplete="new-password" required></div>
                        <div><label for="mb-child-confirm">Confirm password</label><input id="mb-child-confirm" name="confirm_password" type="password" minlength="10" autocomplete="new-password" required></div>
                    </div>
                    <button class="mb-family-primary" type="submit">Create Child Account</button>
                </form>
            </section>
            <?php endif; ?>
        </main>
        <?php return ob_get_clean();
    }

    public static function handle_add_child() {
        if (!is_user_logged_in() || !self::is_parent()) self::redirect_error('permission');
        check_admin_referer('mb_add_family_child');
        $parent_id = get_current_user_id();
        $seat_count = max(1, min(20, absint(get_user_meta($parent_id, 'mb_family_child_count', true))));
        if (count(self::children($parent_id)) >= $seat_count) self::redirect_error('limit');

        $first = sanitize_text_field(wp_unslash($_POST['first_name'] ?? ''));
        $last = sanitize_text_field(wp_unslash($_POST['last_name'] ?? ''));
        $username = sanitize_user(wp_unslash($_POST['username'] ?? ''), true);
        $grade = sanitize_text_field(wp_unslash($_POST['grade_band'] ?? ''));
        $password = (string) wp_unslash($_POST['password'] ?? '');
        $confirm = (string) wp_unslash($_POST['confirm_password'] ?? '');
        if ($first === '' || $last === '' || $username === '' || $grade === '' || $password === '') self::redirect_error('required');
        if (username_exists($username)) self::redirect_error('username');
        if (strlen($password) < 10 || !preg_match('/[A-Za-z]/', $password) || !preg_match('/\d/', $password) || !hash_equals($password, $confirm)) self::redirect_error('password');
        $child_id = wp_insert_user(['user_login'=>$username, 'user_pass'=>$password, 'first_name'=>$first, 'last_name'=>$last, 'display_name'=>trim($first . ' ' . $last), 'role'=>'mb_student']);
        if (is_wp_error($child_id)) self::redirect_error('create');
        update_user_meta($child_id, 'mb_parent_user_id', $parent_id);
        update_user_meta($child_id, 'mb_grade_band', $grade);
        update_user_meta($child_id, 'mb_family_premium_source', 'parent');
        delete_user_meta($child_id, 'mb_family_access_paused');
        MathBinder_Identity_Service::assign_role($child_id, 'student', 'family', $parent_id, 'active', 'parent');
        MathBinder_Verification_Service::authorize_minor($child_id, $parent_id, wp_get_current_user()->user_email, 'parent');
        MathBinder_Audit_Log::record('family_child_created', 'identity', $child_id, ['parent_user_id'=>$parent_id]);
        wp_safe_redirect(add_query_arg('mb_family_notice', 'child_added', home_url('/parents/'))); exit;
    }

    private static function managed_child() {
        if (!is_user_logged_in() || !self::is_parent()) self::redirect_error('permission');
        $child_id = absint($_POST['child_id'] ?? 0);
        check_admin_referer('mb_manage_family_child_' . $child_id);
        if (!$child_id || absint(get_user_meta($child_id, 'mb_parent_user_id', true)) !== get_current_user_id()) self::redirect_error('child');
        return $child_id;
    }

    public static function handle_update_child() {
        $child_id = self::managed_child();
        $grade = sanitize_text_field(wp_unslash($_POST['grade_band'] ?? ''));
        if (!in_array($grade, ['K–2','3–5','6–8','9–12'], true)) self::redirect_error('required');
        update_user_meta($child_id, 'mb_grade_band', $grade);
        MathBinder_Audit_Log::record('family_child_updated', 'identity', $child_id, ['parent_user_id'=>get_current_user_id(), 'field'=>'grade_band']);
        self::redirect_notice('child_updated');
    }

    public static function handle_reset_password() {
        $child_id = self::managed_child();
        $password = (string) wp_unslash($_POST['password'] ?? '');
        $confirm = (string) wp_unslash($_POST['confirm_password'] ?? '');
        if (strlen($password) < 10 || !preg_match('/[A-Za-z]/', $password) || !preg_match('/\d/', $password) || !hash_equals($password, $confirm)) self::redirect_error('password');
        wp_set_password($password, $child_id);
        MathBinder_Audit_Log::record('family_child_password_reset', 'identity', $child_id, ['parent_user_id'=>get_current_user_id()]);
        self::redirect_notice('password_reset');
    }

    public static function handle_toggle_access() {
        $child_id = self::managed_child();
        $paused = get_user_meta($child_id, 'mb_family_access_paused', true) === 'yes';
        if ($paused) {
            delete_user_meta($child_id, 'mb_family_access_paused');
            update_user_meta($child_id, 'mb_family_premium_source', 'parent');
            $notice = 'access_restored';
        } else {
            update_user_meta($child_id, 'mb_family_access_paused', 'yes');
            delete_user_meta($child_id, 'mb_family_premium_source');
            $notice = 'access_paused';
        }
        MathBinder_Audit_Log::record('family_child_access_' . ($paused ? 'restored' : 'paused'), 'identity', $child_id, ['parent_user_id'=>get_current_user_id()]);
        self::redirect_notice($notice);
    }

    private static function redirect_notice($code) {
        wp_safe_redirect(add_query_arg('mb_family_notice', sanitize_key($code), home_url('/parents/'))); exit;
    }

    private static function redirect_error($code) {
        wp_safe_redirect(add_query_arg('mb_family_error', sanitize_key($code), home_url('/parents/'))); exit;
    }
}
