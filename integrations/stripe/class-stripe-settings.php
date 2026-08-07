<?php
if (!defined('ABSPATH')) exit;

/**
 * Administrator-only Stripe configuration.
 *
 * Secret credentials are stored only in WordPress options and are never sent
 * to a front-end page. Existing secrets are never rendered back into HTML.
 */
final class MathBinder_Stripe_Settings {
    const OPTION = 'mathbinder_stripe_settings';
    const PAGE_SLUG = 'mathbinder-stripe-settings';
    const NONCE_ACTION = 'mathbinder_save_stripe_settings';
    const DEFAULT_PUBLISHABLE_KEY = 'pk_test_51U06FfP0D0LACAxr5jinj2gQt5uoE4qQ6ft66OHwt1ILN3oesL8bSesbPkd54Fnq39AoTqlkXXJd8bF9kFSEmU2s00oElpzHzf';
    const DEFAULT_PRICE_ID = 'price_1U078bP0D0LACAxr9bxi8ho3';

    public static function register() {
        add_action('admin_menu', [__CLASS__, 'menu']);
        add_action('admin_post_mathbinder_save_stripe_settings', [__CLASS__, 'save']);
    }

    public static function menu() {
        add_options_page(
            'MathBinder Stripe',
            'MathBinder Stripe',
            MathBinder_Capabilities::MANAGE_INTEGRATIONS,
            self::PAGE_SLUG,
            [__CLASS__, 'render']
        );
    }

    public static function get() {
        $saved = get_option(self::OPTION, []);
        if (!is_array($saved)) $saved = [];
        return wp_parse_args($saved, [
            'mode' => 'test',
            'publishable_key' => self::DEFAULT_PUBLISHABLE_KEY,
            'secret_key' => '',
            'price_id' => self::DEFAULT_PRICE_ID,
            'webhook_secret' => '',
        ]);
    }

    public static function save() {
        if (!current_user_can(MathBinder_Capabilities::MANAGE_INTEGRATIONS)) {
            wp_die('You do not have permission to manage payment settings.', 403);
        }
        check_admin_referer(self::NONCE_ACTION, 'mathbinder_stripe_nonce');

        $current = self::get();
        $publishable = trim(sanitize_text_field(wp_unslash($_POST['publishable_key'] ?? '')));
        $price_id = trim(sanitize_text_field(wp_unslash($_POST['price_id'] ?? '')));
        $new_secret = trim((string) wp_unslash($_POST['secret_key'] ?? ''));
        $new_webhook_secret = trim((string) wp_unslash($_POST['webhook_secret'] ?? ''));
        $errors = [];

        if (strpos($publishable, 'pk_test_') !== 0) {
            $errors[] = 'Use the Stripe sandbox publishable key beginning with pk_test_.';
        }
        if (strpos($price_id, 'price_') !== 0) {
            $errors[] = 'The Family Premium Price ID must begin with price_.';
        }
        if ($new_secret !== '' && strpos($new_secret, 'sk_test_') !== 0) {
            $errors[] = 'Use the Stripe sandbox secret key beginning with sk_test_.';
        }
        if ($new_webhook_secret !== '' && strpos($new_webhook_secret, 'whsec_') !== 0) {
            $errors[] = 'The Stripe webhook signing secret must begin with whsec_.';
        }

        if ($errors) {
            set_transient('mathbinder_stripe_errors_' . get_current_user_id(), $errors, 60);
            wp_safe_redirect(self::page_url());
            exit;
        }

        $secret = !empty($_POST['clear_secret']) ? '' : (string) $current['secret_key'];
        if ($new_secret !== '') $secret = $new_secret;
        $webhook_secret = !empty($_POST['clear_webhook_secret']) ? '' : (string) $current['webhook_secret'];
        if ($new_webhook_secret !== '') $webhook_secret = $new_webhook_secret;

        update_option(self::OPTION, [
            'mode' => 'test',
            'publishable_key' => $publishable,
            'secret_key' => $secret,
            'price_id' => $price_id,
            'webhook_secret' => $webhook_secret,
        ], false);

        wp_safe_redirect(add_query_arg('mathbinder_stripe_saved', '1', self::page_url()));
        exit;
    }

    public static function render() {
        if (!current_user_can(MathBinder_Capabilities::MANAGE_INTEGRATIONS)) return;
        $settings = self::get();
        $configured = $settings['secret_key'] !== '';
        $webhook_configured = $settings['webhook_secret'] !== '';
        $errors = get_transient('mathbinder_stripe_errors_' . get_current_user_id());
        delete_transient('mathbinder_stripe_errors_' . get_current_user_id());
        ?>
        <div class="wrap">
            <h1>MathBinder Stripe Sandbox</h1>
            <?php if (isset($_GET['mathbinder_stripe_saved'])): ?>
                <div class="notice notice-success is-dismissible"><p>Stripe sandbox settings saved.</p></div>
            <?php endif; ?>
            <?php if (is_array($errors)): foreach ($errors as $error): ?>
                <div class="notice notice-error"><p><?php echo esc_html($error); ?></p></div>
            <?php endforeach; endif; ?>
            <div class="notice <?php echo $configured ? 'notice-success' : 'notice-warning'; ?> inline">
                <p><strong><?php echo $configured ? 'Sandbox secret key is stored.' : 'Sandbox secret key is not stored yet.'; ?></strong></p>
            </div>
            <p>This connection is locked to Stripe sandbox mode. Test payments cannot charge a real card.</p>
            <form method="post" action="<?php echo esc_url(admin_url('admin-post.php')); ?>" autocomplete="off">
                <input type="hidden" name="action" value="mathbinder_save_stripe_settings">
                <?php wp_nonce_field(self::NONCE_ACTION, 'mathbinder_stripe_nonce'); ?>
                <table class="form-table" role="presentation">
                    <tr>
                        <th scope="row"><label for="mb-stripe-mode">Mode</label></th>
                        <td><input id="mb-stripe-mode" class="regular-text" value="Sandbox / Test" disabled><p class="description">Live payments remain disabled during development.</p></td>
                    </tr>
                    <tr>
                        <th scope="row"><label for="mb-stripe-publishable">Publishable key</label></th>
                        <td><input id="mb-stripe-publishable" name="publishable_key" class="large-text code" value="<?php echo esc_attr($settings['publishable_key']); ?>" required spellcheck="false" autocomplete="off"></td>
                    </tr>
                    <tr>
                        <th scope="row"><label for="mb-stripe-secret">Secret key</label></th>
                        <td>
                            <input id="mb-stripe-secret" name="secret_key" class="large-text code" type="password" value="" placeholder="<?php echo $configured ? 'Stored — leave blank to keep it' : 'Paste sk_test_ key here'; ?>" spellcheck="false" autocomplete="new-password">
                            <p class="description">Enter this only inside WordPress. The saved key is never displayed again and is never included in a plugin download.</p>
                            <?php if ($configured): ?><label><input type="checkbox" name="clear_secret" value="1"> Remove the stored secret key</label><?php endif; ?>
                        </td>
                    </tr>
                    <tr>
                        <th scope="row"><label for="mb-stripe-price">Family Premium Price ID</label></th>
                        <td><input id="mb-stripe-price" name="price_id" class="large-text code" value="<?php echo esc_attr($settings['price_id']); ?>" required spellcheck="false" autocomplete="off"><p class="description">Monthly graduated price: first child $14.99; each additional child $4.99.</p></td>
                    </tr>
                    <tr>
                        <th scope="row"><label for="mb-stripe-webhook">Webhook signing secret</label></th>
                        <td>
                            <input id="mb-stripe-webhook" name="webhook_secret" class="large-text code" type="password" value="" placeholder="<?php echo $webhook_configured ? 'Stored — leave blank to keep it' : 'Paste whsec_ signing secret here'; ?>" spellcheck="false" autocomplete="new-password">
                            <p class="description">Webhook endpoint: <code><?php echo esc_html(rest_url('mathbinder/v1/stripe/webhook')); ?></code></p>
                            <?php if ($webhook_configured): ?><label><input type="checkbox" name="clear_webhook_secret" value="1"> Remove the stored webhook signing secret</label><?php endif; ?>
                        </td>
                    </tr>
                </table>
                <?php submit_button('Save Sandbox Settings'); ?>
            </form>
            <p><strong>Security reminder:</strong> Never paste a Stripe secret key into chat, email, a page, or a screenshot.</p>
        </div>
        <?php
    }

    private static function page_url() {
        return admin_url('options-general.php?page=' . self::PAGE_SLUG);
    }
}
