<?php
if (!defined('ABSPATH')) exit;

/** Public parent registration and Stripe-hosted Family Premium checkout. */
final class MathBinder_Family_Checkout {
    const SHORTCODE = 'mathbinder_family_signup';
    const PAGE_SLUG = 'sign-up';

    public static function register() {
        add_shortcode(self::SHORTCODE, [__CLASS__, 'shortcode']);
        add_action('admin_post_nopriv_mb_family_signup', [__CLASS__, 'handle_signup']);
        add_action('admin_post_mb_family_signup', [__CLASS__, 'handle_signup']);
        add_action('rest_api_init', [__CLASS__, 'rest_routes']);
    }

    public static function ensure_page() {
        $page = get_page_by_path(self::PAGE_SLUG, OBJECT, 'page');
        $data = ['post_type'=>'page','post_status'=>'publish','post_title'=>'Family Sign Up','post_name'=>self::PAGE_SLUG,'post_content'=>'[' . self::SHORTCODE . ']'];
        if ($page) $data['ID'] = $page->ID;
        $id = $page ? wp_update_post($data) : wp_insert_post($data);
        if ($id && !is_wp_error($id)) update_post_meta($id, '_mb_managed_signup_page', '1');
        return is_wp_error($id) ? 0 : (int) $id;
    }

    public static function signup_url(array $args = []) {
        $page = get_page_by_path(self::PAGE_SLUG, OBJECT, 'page');
        $url = $page ? get_permalink($page->ID) : home_url('/' . self::PAGE_SLUG . '/');
        return $args ? add_query_arg($args, $url) : $url;
    }

    public static function shortcode() {
        $state = sanitize_key(wp_unslash($_GET['mb_checkout'] ?? ''));
        if ($state === 'success') return self::status_card('Checkout received', 'Stripe is confirming your sandbox subscription. We also sent an email verification link. After confirmation, you can add your children from your Parent area.', true);
        if ($state === 'cancelled') return self::status_card('Checkout was cancelled', 'Your family account is saved, but no subscription was started. You can return here and try checkout again.', false);
        $error = sanitize_key(wp_unslash($_GET['mb_signup_error'] ?? ''));
        $messages = [
            'required'=>'Complete every required field.', 'email'=>'Enter a valid email address.', 'exists'=>'An account already uses that email. Log in instead.',
            'password'=>'Use at least 10 characters, including a letter and a number.', 'match'=>'The passwords do not match.', 'children'=>'Choose between 1 and 20 children.',
            'terms'=>'You must accept the subscription terms.', 'security'=>'The signup page expired. Please try again.', 'stripe'=>'We created your account but could not open Stripe Checkout. Log in and contact MathBinder support.'
        ];
        ob_start(); ?>
        <main class="mb-login-page"><section class="mb-login-card mb-signup-card" aria-labelledby="mb-signup-title">
            <?php echo self::brand_mark(); ?>
            <p class="mb-login-eyebrow">Family Premium</p><h1 id="mb-signup-title">Start your 14-day free trial</h1>
            <p class="mb-login-intro">The first child is $14.99 per month and each additional child is $4.99 per month. You will enter card details securely on Stripe.</p>
            <?php if (isset($messages[$error])): ?><div class="mb-login-error" role="alert"><?php echo esc_html($messages[$error]); ?></div><?php endif; ?>
            <form class="mb-login-form" method="post" action="<?php echo esc_url(admin_url('admin-post.php')); ?>">
                <input type="hidden" name="action" value="mb_family_signup"><?php wp_nonce_field('mb_family_signup', 'mb_signup_nonce'); ?>
                <div class="mb-signup-grid">
                    <div><label for="mb-first-name">Parent first name</label><input id="mb-first-name" name="first_name" type="text" autocomplete="given-name" required></div>
                    <div><label for="mb-last-name">Parent last name</label><input id="mb-last-name" name="last_name" type="text" autocomplete="family-name" required></div>
                    <div class="mb-full"><label for="mb-signup-email">Email address</label><input id="mb-signup-email" name="email" type="email" autocomplete="email" required></div>
                    <div><label for="mb-signup-password">Password</label><input id="mb-signup-password" name="password" type="password" minlength="10" autocomplete="new-password" required></div>
                    <div><label for="mb-signup-confirm">Confirm password</label><input id="mb-signup-confirm" name="confirm_password" type="password" minlength="10" autocomplete="new-password" required></div>
                    <div class="mb-full"><label for="mb-child-count">Number of children</label><input id="mb-child-count" name="child_count" type="number" min="1" max="20" value="1" inputmode="numeric" required></div>
                </div>
                <div class="mb-price-preview" aria-live="polite">After the 14-day trial: <strong id="mb-family-total">$14.99/month</strong></div>
                <label class="mb-signup-consent"><input type="checkbox" name="terms" value="1" required><span>I agree to recurring monthly billing after the free trial until I cancel, and I confirm that I am the parent or legal guardian for the children I add.</span></label>
                <button class="mb-login-submit" type="submit">Continue to Secure Checkout</button>
                <p class="mb-checkout-note">Sandbox mode is active. No real card will be charged during testing.</p>
            </form>
            <p class="mb-login-help">Already have an account? <a href="<?php echo esc_url(MathBinder_Frontend_Auth::login_url()); ?>">Log in</a>.</p>
        </section></main>
        <script>(function(){var n=document.getElementById('mb-child-count'),o=document.getElementById('mb-family-total');if(!n||!o)return;function u(){var q=Math.max(1,Math.min(20,parseInt(n.value||'1',10)));o.textContent='$'+(14.99+(q-1)*4.99).toFixed(2)+'/month';}n.addEventListener('input',u);u();}());</script>
        <?php return ob_get_clean();
    }

    public static function handle_signup() {
        if (is_user_logged_in()) wp_logout();
        if (!isset($_POST['mb_signup_nonce']) || !wp_verify_nonce(sanitize_text_field(wp_unslash($_POST['mb_signup_nonce'])), 'mb_family_signup')) self::fail('security');
        $first = sanitize_text_field(wp_unslash($_POST['first_name'] ?? ''));
        $last = sanitize_text_field(wp_unslash($_POST['last_name'] ?? ''));
        $email = sanitize_email(wp_unslash($_POST['email'] ?? ''));
        $password = (string) wp_unslash($_POST['password'] ?? '');
        $confirm = (string) wp_unslash($_POST['confirm_password'] ?? '');
        $children = absint($_POST['child_count'] ?? 0);
        if ($first === '' || $last === '' || $email === '' || $password === '') self::fail('required');
        if (!is_email($email)) self::fail('email');
        if (email_exists($email)) self::fail('exists');
        if (strlen($password) < 10 || !preg_match('/[A-Za-z]/', $password) || !preg_match('/\d/', $password)) self::fail('password');
        if (!hash_equals($password, $confirm)) self::fail('match');
        if ($children < 1 || $children > 20) self::fail('children');
        if (empty($_POST['terms'])) self::fail('terms');
        $base = sanitize_user(strstr($email, '@', true), true);
        if ($base === '') $base = 'family';
        $login = $base; $suffix = 2;
        while (username_exists($login)) $login = $base . $suffix++;
        $user_id = wp_insert_user(['user_login'=>$login,'user_email'=>$email,'user_pass'=>$password,'first_name'=>$first,'last_name'=>$last,'display_name'=>trim($first.' '.$last),'role'=>'mb_parent']);
        if (is_wp_error($user_id)) self::fail('required');
        MathBinder_Identity_Service::assign_role($user_id, 'parent', 'site', 0, 'active', 'self_signup');
        update_user_meta($user_id, 'mb_family_child_count', $children);
        update_user_meta($user_id, 'mb_family_subscription_status', 'checkout_pending');
        wp_set_current_user($user_id); wp_set_auth_cookie($user_id, true, is_ssl());
        $session = self::create_checkout_session($user_id, $email, $children);
        if (is_wp_error($session) || empty($session['url'])) self::fail('stripe');
        wp_redirect(esc_url_raw($session['url'])); exit;
    }

    private static function create_checkout_session($user_id, $email, $children) {
        $settings = MathBinder_Stripe_Settings::get();
        if (empty($settings['secret_key']) || empty($settings['price_id'])) return new WP_Error('stripe_config', 'Stripe is not configured.');
        $body = [
            'mode'=>'subscription','customer_email'=>$email,'client_reference_id'=>(string)$user_id,
            'line_items'=>[['price'=>$settings['price_id'],'quantity'=>(int)$children]],
            'subscription_data'=>['trial_period_days'=>14,'metadata'=>['mathbinder_user_id'=>(string)$user_id,'mathbinder_child_count'=>(string)$children]],
            'metadata'=>['mathbinder_user_id'=>(string)$user_id,'mathbinder_child_count'=>(string)$children],
            'success_url'=>self::signup_url(['mb_checkout'=>'success','session_id'=>'{CHECKOUT_SESSION_ID}']),
            'cancel_url'=>self::signup_url(['mb_checkout'=>'cancelled']),
        ];
        $response = wp_remote_post('https://api.stripe.com/v1/checkout/sessions', ['timeout'=>20,'headers'=>['Authorization'=>'Bearer '.$settings['secret_key'],'Content-Type'=>'application/x-www-form-urlencoded'],'body'=>http_build_query($body)]);
        if (is_wp_error($response)) return $response;
        $data = json_decode(wp_remote_retrieve_body($response), true);
        $code = wp_remote_retrieve_response_code($response);
        if ($code < 200 || $code >= 300) return new WP_Error('stripe_api', 'Stripe Checkout could not be created.');
        return is_array($data) ? $data : new WP_Error('stripe_response', 'Stripe returned an invalid response.');
    }

    public static function rest_routes() {
        register_rest_route('mathbinder/v1', '/stripe/webhook', ['methods'=>'POST','callback'=>[__CLASS__,'webhook'],'permission_callback'=>'__return_true']);
    }

    public static function webhook(WP_REST_Request $request) {
        $settings = MathBinder_Stripe_Settings::get();
        $secret = (string)($settings['webhook_secret'] ?? '');
        $payload = $request->get_body();
        $signature = (string)$request->get_header('stripe-signature');
        if ($secret === '' || !self::valid_signature($payload, $signature, $secret)) return new WP_REST_Response(['received'=>false], 400);
        $event = json_decode($payload, true);
        if (!is_array($event) || empty($event['type']) || empty($event['data']['object'])) return new WP_REST_Response(['received'=>false], 400);
        $object = $event['data']['object'];
        $type = sanitize_text_field($event['type']);
        $user_id = absint($object['metadata']['mathbinder_user_id'] ?? $object['client_reference_id'] ?? 0);
        if ($user_id && get_user_by('id', $user_id)) {
            if (!empty($object['customer'])) update_user_meta($user_id, 'mb_stripe_customer_id', sanitize_text_field($object['customer']));
            if (!empty($object['subscription'])) update_user_meta($user_id, 'mb_stripe_subscription_id', sanitize_text_field($object['subscription']));
            if (strpos($type, 'customer.subscription.') === 0) {
                update_user_meta($user_id, 'mb_stripe_subscription_id', sanitize_text_field($object['id'] ?? ''));
                update_user_meta($user_id, 'mb_family_subscription_status', sanitize_key($object['status'] ?? 'unknown'));
                if (!empty($object['trial_end'])) update_user_meta($user_id, 'mb_family_trial_ends_at', absint($object['trial_end']));
            } elseif ($type === 'checkout.session.completed') {
                update_user_meta($user_id, 'mb_family_subscription_status', 'checkout_complete');
            } elseif ($type === 'invoice.payment_failed') {
                update_user_meta($user_id, 'mb_family_subscription_status', 'past_due');
            }
            MathBinder_Audit_Log::record('stripe_webhook', 'identity', $user_id, ['event_type'=>$type]);
        }
        return new WP_REST_Response(['received'=>true], 200);
    }

    private static function valid_signature($payload, $header, $secret) {
        $timestamp = 0; $signatures = [];
        foreach (explode(',', $header) as $part) {
            $bits = explode('=', trim($part), 2);
            if (count($bits) !== 2) continue;
            if ($bits[0] === 't') $timestamp = (int)$bits[1];
            if ($bits[0] === 'v1') $signatures[] = $bits[1];
        }
        if (!$timestamp || abs(time() - $timestamp) > 300) return false;
        $expected = hash_hmac('sha256', $timestamp . '.' . $payload, $secret);
        foreach ($signatures as $candidate) if (hash_equals($expected, $candidate)) return true;
        return false;
    }

    private static function fail($code) { wp_safe_redirect(self::signup_url(['mb_signup_error'=>sanitize_key($code)])); exit; }
    private static function brand_mark() {
        $logo = plugin_dir_url(dirname(__DIR__, 2) . '/mathbinder-core.php') . 'Assests/Icons/mathbinder-icon.svg';
        return '<a class="mb-login-brand" href="' . esc_url(home_url('/')) . '"><img src="' . esc_url($logo) . '" alt=""><span>MathBinder</span></a>';
    }
    private static function status_card($title, $message, $success) {
        return '<main class="mb-login-page"><section class="mb-login-card">' . self::brand_mark() . '<p class="mb-login-eyebrow">' . ($success ? 'Family Premium' : 'Checkout') . '</p><h1>' . esc_html($title) . '</h1><p class="mb-login-intro">' . esc_html($message) . '</p><div class="mb-login-actions"><a class="mb-login-submit" href="' . esc_url(home_url('/parents/')) . '">Continue</a></div></section></main>';
    }
}
