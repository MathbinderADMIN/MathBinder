<?php
if (!defined('ABSPATH')) exit;

/**
 * Public-facing authentication for MathBinder accounts.
 *
 * Account creation remains administrator-controlled. WordPress continues to
 * own passwords, authentication cookies, reset keys, and logout nonces.
 */
final class MathBinder_Frontend_Auth {
    const SHORTCODE = 'mathbinder_login';
    const PAGE_SLUG = 'login';

    public static function register() {
        add_shortcode(self::SHORTCODE, [__CLASS__, 'shortcode']);
        add_action('admin_post_nopriv_mb_frontend_login', [__CLASS__, 'handle_login']);
        add_action('admin_post_mb_frontend_login', [__CLASS__, 'handle_login']);
        add_filter('wp_nav_menu_items', [__CLASS__, 'navigation_item'], 100, 2);
        add_action('wp_enqueue_scripts', [__CLASS__, 'enqueue_assets']);
        add_action('wp_head', [__CLASS__, 'compact_navigation_bootstrap'], 99);
        add_action('login_enqueue_scripts', [__CLASS__, 'login_branding']);
        add_filter('login_headerurl', [__CLASS__, 'login_header_url']);
        add_filter('login_headertext', [__CLASS__, 'login_header_text']);
        add_filter('register_url', [__CLASS__, 'disable_public_registration_link']);
        add_filter('pre_option_users_can_register', [__CLASS__, 'public_registration_disabled']);
        add_filter('login_redirect', [__CLASS__, 'native_login_redirect'], 20, 3);
        add_action('admin_init', [__CLASS__, 'restrict_student_admin'], 1);
        add_filter('show_admin_bar', [__CLASS__, 'student_admin_bar']);
    }

    public static function ensure_page() {
        $page = get_page_by_path(self::PAGE_SLUG, OBJECT, 'page');
        $data = [
            'post_type' => 'page',
            'post_status' => 'publish',
            'post_title' => 'Log In',
            'post_name' => self::PAGE_SLUG,
            'post_content' => '[' . self::SHORTCODE . ']'
        ];

        if ($page) {
            $data['ID'] = $page->ID;
            wp_update_post($data);
            update_post_meta($page->ID, '_mb_managed_login_page', '1');
            return (int) $page->ID;
        }

        $page_id = wp_insert_post($data);
        if ($page_id && !is_wp_error($page_id)) {
            update_post_meta($page_id, '_mb_managed_login_page', '1');
            return (int) $page_id;
        }
        return 0;
    }

    public static function login_url($redirect_to = '') {
        $page = get_page_by_path(self::PAGE_SLUG, OBJECT, 'page');
        $url = $page ? get_permalink($page->ID) : home_url('/' . self::PAGE_SLUG . '/');
        if ($redirect_to) $url = add_query_arg('redirect_to', $redirect_to, $url);
        return $url;
    }

    public static function enqueue_assets() {
        wp_enqueue_style(
            'mathbinder-frontend-auth',
            self::plugin_url('assets/frontend-auth.css'),
            [],
            defined('MathBinder_Core::VERSION') ? MathBinder_Core::VERSION : '30.0.9'
        );
    }

    /**
     * Mark the exact menu receiving MathBinder's account controls.
     *
     * The active site header does not expose a stable theme-specific menu
     * selector, so the previous :has() rule never reached the controlling
     * navigation element. This small bootstrap adds stable MathBinder classes
     * to the rendered menu and its containers before applying the compact
     * desktop layout.
     */
    public static function compact_navigation_bootstrap() {
        if (is_admin()) return;
        ?>
        <style id="mathbinder-compact-navigation">
        @media (min-width:1001px){
            .mb-compact-header{overflow:visible!important}
            .mb-compact-nav{width:100%!important;max-width:none!important;overflow:visible!important}
            ul.mb-compact-menu{display:flex!important;align-items:center!important;justify-content:flex-start!important;flex-wrap:nowrap!important;gap:4px!important;width:auto!important;max-width:100%!important;margin:0!important;padding:0!important;overflow:visible!important}
            ul.mb-compact-menu>li{flex:0 0 auto!important;width:auto!important;min-width:0!important;margin:0!important;padding:0!important}
            ul.mb-compact-menu>li>a{display:flex!important;align-items:center!important;justify-content:center!important;width:auto!important;min-width:0!important;min-height:34px!important;margin:0!important;padding:7px 7px!important;font-size:13px!important;line-height:1.1!important;letter-spacing:0!important;white-space:nowrap!important}
            ul.mb-compact-menu>.mb-auth-menu-item>a,
            ul.mb-compact-menu>.mb-signup-menu-item>a{min-height:34px!important;padding:6px 11px!important;font-size:12px!important}
        }
        </style>
        <script id="mathbinder-compact-navigation-bootstrap">
        document.addEventListener('DOMContentLoaded',function(){
            document.querySelectorAll('li.mb-auth-menu-item').forEach(function(item){
                var menu=item.parentElement;
                if(!menu)return;
                menu.classList.add('mb-compact-menu');
                var nav=menu.closest('nav');
                if(nav)nav.classList.add('mb-compact-nav');
                var header=menu.closest('header');
                if(header)header.classList.add('mb-compact-header');
            });
        });
        </script>
        <?php
    }

    public static function navigation_item($items, $args) {
        if (is_admin()) return $items;
        // MathBinder's header menu always contains the Binder destination.
        // Limiting the injected item here avoids adding account controls to
        // unrelated footer or utility menus rendered by the theme.
        if (stripos($items, 'Binder Topics') === false && stripos($items, 'Binder Sections') === false) return $items;

        if (is_user_logged_in()) {
            $url = wp_logout_url(home_url('/'));
            $label = 'Log Out';
            $class = 'mb-auth-menu-item mb-auth-menu-item-logout';
        } else {
            $url = self::login_url();
            $label = 'Log In';
            $class = 'mb-auth-menu-item mb-auth-menu-item-login';
        }

        $account_item = '<li class="menu-item ' . esc_attr($class) . '"><a href="' .
            esc_url($url) . '">' . esc_html($label) . '</a></li>';
        if (!is_user_logged_in() && class_exists('MathBinder_Family_Checkout')) {
            $account_item .= '<li class="menu-item mb-signup-menu-item"><a href="' .
                esc_url(MathBinder_Family_Checkout::signup_url()) . '">Sign Up</a></li>';
        }
        return $items . $account_item;
    }

    public static function shortcode() {
        if (is_user_logged_in()) {
            $destination = self::role_destination(wp_get_current_user());
            return '<main class="mb-login-page"><section class="mb-login-card mb-login-card-signed-in">' .
                self::brand_mark() . '<p class="mb-login-eyebrow">Welcome back</p><h1>You are signed in.</h1>' .
                '<p>Continue to your MathBinder area or safely log out of this device.</p>' .
                '<div class="mb-login-actions"><a class="mb-login-submit" href="' . esc_url($destination) . '">Continue</a>' .
                '<a class="mb-login-secondary" href="' . esc_url(wp_logout_url(home_url('/'))) . '">Log Out</a></div>' .
                '</section></main>';
        }

        $error = isset($_GET['mb_login_error']) ? sanitize_key(wp_unslash($_GET['mb_login_error'])) : '';
        $redirect_to = self::validated_redirect(isset($_GET['redirect_to']) ? wp_unslash($_GET['redirect_to']) : '');
        $username = isset($_GET['mb_login_name']) ? sanitize_text_field(wp_unslash($_GET['mb_login_name'])) : '';
        $messages = [
            'required' => 'Enter your username or email address and password.',
            'invalid' => 'We could not sign you in with those details. Please try again.',
            'security' => 'Your sign-in page expired. Please try again.'
        ];

        ob_start(); ?>
        <main class="mb-login-page">
            <section class="mb-login-card" aria-labelledby="mb-login-title">
                <?php echo self::brand_mark(); ?>
                <p class="mb-login-eyebrow">Find it. Learn it. Master it.</p>
                <h1 id="mb-login-title">Log in to MathBinder</h1>
                <p class="mb-login-intro">Use your MathBinder account, or create a Family Premium account for your children.</p>
                <?php if (isset($messages[$error])): ?>
                    <div class="mb-login-error" role="alert"><?php echo esc_html($messages[$error]); ?></div>
                <?php endif; ?>
                <form class="mb-login-form" method="post" action="<?php echo esc_url(admin_url('admin-post.php')); ?>">
                    <input type="hidden" name="action" value="mb_frontend_login">
                    <input type="hidden" name="redirect_to" value="<?php echo esc_attr($redirect_to); ?>">
                    <?php wp_nonce_field('mb_frontend_login', 'mb_login_nonce'); ?>
                    <label for="mb-login-name">Username or email address</label>
                    <input id="mb-login-name" name="log" type="text" value="<?php echo esc_attr($username); ?>" autocomplete="username" autocapitalize="none" required>
                    <label for="mb-login-password">Password</label>
                    <input id="mb-login-password" name="pwd" type="password" autocomplete="current-password" required>
                    <label class="mb-login-remember"><input name="rememberme" type="checkbox" value="forever"> <span>Keep me logged in on this device</span></label>
                    <button class="mb-login-submit" type="submit">Log In</button>
                </form>
                <a class="mb-login-forgot" href="<?php echo esc_url(wp_lostpassword_url(self::login_url())); ?>">Forgot your password?</a>
                <p class="mb-login-help">Don’t have an account? <a href="<?php echo esc_url(class_exists('MathBinder_Family_Checkout') ? MathBinder_Family_Checkout::signup_url() : home_url('/sign-up/')); ?>">Sign up here.</a></p>
                <p class="mb-login-help mb-login-school-note">School-provided accounts are created by a MathBinder administrator.</p>
            </section>
        </main>
        <?php return ob_get_clean();
    }

    public static function handle_login() {
        if (is_user_logged_in()) {
            wp_safe_redirect(self::role_destination(wp_get_current_user()));
            exit;
        }

        if (!isset($_POST['mb_login_nonce']) ||
            !wp_verify_nonce(sanitize_text_field(wp_unslash($_POST['mb_login_nonce'])), 'mb_frontend_login')) {
            self::redirect_error('security');
        }

        $login = isset($_POST['log']) ? trim((string) wp_unslash($_POST['log'])) : '';
        $password = isset($_POST['pwd']) ? (string) wp_unslash($_POST['pwd']) : '';
        $redirect_to = self::validated_redirect(isset($_POST['redirect_to']) ? wp_unslash($_POST['redirect_to']) : '');
        if ($login === '' || $password === '') self::redirect_error('required', $login, $redirect_to);

        $user = wp_signon([
            'user_login' => $login,
            'user_password' => $password,
            'remember' => !empty($_POST['rememberme'])
        ], is_ssl());

        if (is_wp_error($user)) self::redirect_error('invalid', $login, $redirect_to);

        $destination = $redirect_to ?: self::role_destination($user);
        wp_safe_redirect($destination);
        exit;
    }

    private static function redirect_error($code, $login = '', $redirect_to = '') {
        $args = ['mb_login_error' => sanitize_key($code)];
        if ($login !== '') $args['mb_login_name'] = sanitize_text_field($login);
        if ($redirect_to !== '') $args['redirect_to'] = $redirect_to;
        wp_safe_redirect(add_query_arg($args, self::login_url()));
        exit;
    }

    private static function validated_redirect($url) {
        $url = $url ? (string) $url : '';
        return $url ? wp_validate_redirect($url, '') : '';
    }

    public static function role_destination($user) {
        if (!($user instanceof WP_User)) return home_url('/');
        $roles = (array) $user->roles;
        if (in_array('mb_student', $roles, true)) return self::page_url(['student-dashboard']);
        if (in_array('mb_parent', $roles, true)) return self::page_url(['parents', 'parent-resources']);
        if (in_array('mb_teacher', $roles, true)) return self::page_url(['teacher-dashboard']);
        if (in_array('mb_school_admin', $roles, true)) return home_url('/mathbinder-account/');
        if (in_array('administrator', $roles, true)) return admin_url();
        return self::page_url(['your-binder', 'my-mathbinder']);
    }

    /**
     * Keep student accounts in the public MathBinder learning experience even
     * when they sign in through WordPress's native wp-login.php form.
     */
    public static function native_login_redirect($redirect_to, $requested_redirect_to, $user) {
        if ($user instanceof WP_User && self::is_student($user)) {
            return self::page_url(['student-dashboard']);
        }
        return $redirect_to;
    }

    /**
     * Prevent students from opening the WordPress administration interface.
     * Requests used by front-end forms and background services must continue
     * to reach admin-post.php and admin-ajax.php.
     */
    public static function restrict_student_admin() {
        if (!is_user_logged_in() || !self::is_student(wp_get_current_user())) return;
        if ((function_exists('wp_doing_ajax') && wp_doing_ajax()) ||
            (defined('DOING_CRON') && DOING_CRON) ||
            (defined('WP_CLI') && WP_CLI)) return;

        global $pagenow;
        if (in_array($pagenow, ['admin-post.php', 'admin-ajax.php'], true)) return;

        wp_safe_redirect(self::page_url(['student-dashboard']));
        exit;
    }

    public static function student_admin_bar($show) {
        return self::is_student(wp_get_current_user()) ? false : $show;
    }

    private static function is_student($user) {
        return $user instanceof WP_User && in_array('mb_student', (array) $user->roles, true);
    }

    private static function brand_mark() {
        $logo = self::plugin_url('Assests/Icons/mathbinder-icon.svg');
        return '<a class="mb-login-brand" href="' . esc_url(home_url('/')) . '"><img src="' .
            esc_url($logo) . '" alt=""><span>MathBinder</span></a>';
    }

    public static function login_branding() {
        $logo = self::plugin_url('Assests/Icons/mathbinder-icon.svg');
        echo '<style>body.login{background:#f5f3ff}body.login h1 a{background-image:url(' . esc_url($logo) . ');background-size:72px;width:72px;height:72px}.wp-core-ui .button-primary{background:#6d28d9;border-color:#6d28d9}</style>';
    }

    public static function login_header_url() { return home_url('/'); }
    public static function login_header_text() { return 'MathBinder'; }
    public static function disable_public_registration_link() { return self::login_url(); }
    public static function public_registration_disabled() { return 0; }

    private static function page_url(array $slugs) {
        foreach ($slugs as $slug) {
            $page = get_page_by_path($slug, OBJECT, 'page');
            if ($page && $page->post_status === 'publish') return get_permalink($page->ID);
        }
        return home_url('/' . trim($slugs[0], '/') . '/');
    }

    private static function plugin_url($path = '') {
        return plugin_dir_url(dirname(__DIR__) . '/mathbinder-core.php') . ltrim($path, '/');
    }
}
