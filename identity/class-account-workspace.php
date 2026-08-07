<?php
if (!defined('ABSPATH')) exit;

final class MathBinder_Account_Workspace {
    const SHORTCODE = 'mathbinder_account_workspace';

    public static function register() {
        add_shortcode(self::SHORTCODE, [__CLASS__, 'shortcode']);
        add_action('admin_post_mb_switch_workspace', [__CLASS__, 'handle_switch']);
    }

    public static function ensure_page() {
        $page = get_page_by_path('mathbinder-account', OBJECT, 'page');
        $data = ['post_type'=>'page', 'post_status'=>'publish', 'post_title'=>'MathBinder Account', 'post_name'=>'mathbinder-account', 'post_content'=>'['.self::SHORTCODE.']'];
        if ($page) $data['ID'] = $page->ID;
        wp_insert_post($data);
    }

    public static function handle_switch() {
        if (!is_user_logged_in()) wp_die('Login required.', 403);
        check_admin_referer('mb_switch_workspace');
        $result = MathBinder_Identity_Service::select_workspace(get_current_user_id(), isset($_POST['assignment_id']) ? absint($_POST['assignment_id']) : 0);
        $redirect = wp_validate_redirect(isset($_POST['redirect_to']) ? wp_unslash($_POST['redirect_to']) : '', home_url('/'));
        wp_safe_redirect(add_query_arg(is_wp_error($result) ? 'mb_workspace_error' : 'mb_workspace_changed', '1', $redirect));
        exit;
    }

    public static function shortcode() {
        if (!is_user_logged_in()) return '<section class="mb-dashboard-gate"><h1>Log in to manage your MathBinder account</h1><a class="mb-button mb-button-primary" href="'.esc_url(MathBinder_Frontend_Auth::login_url(get_permalink())).'">Log In</a></section>';
        $user_id = get_current_user_id();
        $user = wp_get_current_user();
        $assignments = MathBinder_Identity_Service::assignments($user_id);
        $active = MathBinder_Identity_Service::active_workspace($user_id);
        ob_start(); ?>
        <section class="mb-account-workspace" style="max-width:900px;margin:32px auto;padding:24px;font-family:Arial,sans-serif">
            <p style="color:#6d28d9;font-weight:700">One account. Every learning connection.</p>
            <h1>Account &amp; Workspaces</h1>
            <p>Your permanent MathBinder identity stays with you when roles, classes, schools, licenses, or Canvas connections change.</p>
            <div style="background:#f5f3ff;border:1px solid #ddd6fe;border-radius:14px;padding:18px;margin:20px 0">
                <strong><?php echo esc_html($user->display_name); ?></strong><br>
                <span><?php echo esc_html($user->user_email); ?></span><br>
                <small>MathBinder ID: <?php echo esc_html(MathBinder_Identity_Service::permanent_key($user_id)); ?></small>
            </div>
            <h2>Your workspaces</h2>
            <?php if (!$assignments): ?><p>No MathBinder role has been assigned yet. Your administrator can add one without creating another account.</p><?php endif; ?>
            <?php foreach ($assignments as $assignment): ?>
                <form method="post" action="<?php echo esc_url(admin_url('admin-post.php')); ?>" style="display:flex;align-items:center;justify-content:space-between;gap:16px;border:1px solid #d1d5db;border-radius:12px;padding:16px;margin:12px 0">
                    <div><strong><?php echo esc_html(ucfirst($assignment['role_key'])); ?></strong><br><small><?php echo esc_html(ucfirst($assignment['scope_type'])); ?> workspace<?php echo $active && (int)$active['id']===(int)$assignment['id'] ? ' · Active' : ''; ?></small></div>
                    <input type="hidden" name="action" value="mb_switch_workspace"><input type="hidden" name="assignment_id" value="<?php echo esc_attr($assignment['id']); ?>"><input type="hidden" name="redirect_to" value="<?php echo esc_url(get_permalink()); ?>">
                    <?php wp_nonce_field('mb_switch_workspace'); ?>
                    <button type="submit" <?php disabled($active && (int)$active['id']===(int)$assignment['id']); ?>><?php echo $active && (int)$active['id']===(int)$assignment['id'] ? 'Current' : 'Switch'; ?></button>
                </form>
            <?php endforeach; ?>
            <p><strong>Email verification:</strong> <?php echo get_user_meta($user_id, 'mb_email_verified_at', true) ? 'Verified' : 'Pending'; ?></p>
        </section><?php
        return ob_get_clean();
    }
}
