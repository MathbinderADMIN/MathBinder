<?php
if (!defined('ABSPATH')) exit;

/** Administrator-only Canvas LTI 1.3 sandbox configuration. */
final class MathBinder_Canvas_Settings {
    const OPTION = 'mathbinder_canvas_settings_v1';
    const PAGE_SLUG = 'mathbinder-canvas-settings';
    const NONCE_ACTION = 'mathbinder_save_canvas_settings';

    public static function register() {
        add_action('admin_menu', [__CLASS__, 'menu']);
        add_action('admin_post_mathbinder_save_canvas_settings', [__CLASS__, 'save']);
        add_action('admin_post_mathbinder_validate_canvas_settings', [__CLASS__, 'validate']);
    }

    public static function menu() {
        add_options_page('MathBinder Canvas', 'MathBinder Canvas', MathBinder_Capabilities::MANAGE_INTEGRATIONS, self::PAGE_SLUG, [__CLASS__, 'render']);
    }

    public static function get() {
        $saved = get_option(self::OPTION, []);
        if (!is_array($saved)) $saved = [];
        $saved = wp_parse_args($saved, [
            'environment' => 'sandbox', 'canvas_url' => '', 'client_id' => '', 'deployment_id' => '',
            'canvas_jwks_url' => '', 'canvas_auth_url' => '', 'canvas_token_url' => '',
            'private_key' => '', 'public_jwk' => '', 'validated_at' => '', 'sandbox_enabled' => false,
            'operating_mode' => 'disabled',
        ]);
        $saved['private_key'] = self::unseal((string)$saved['private_key']);
        return $saved;
    }

    public static function is_complete($settings = null) {
        $settings = is_array($settings) ? $settings : self::get();
        foreach (['canvas_url','client_id','deployment_id','canvas_jwks_url','canvas_auth_url','canvas_token_url','private_key','public_jwk'] as $key) {
            if (trim((string)($settings[$key] ?? '')) === '') return false;
        }
        return true;
    }

    public static function save() {
        self::authorize();
        check_admin_referer(self::NONCE_ACTION, 'mathbinder_canvas_nonce');
        $current = self::get();
        $settings = $current;
        $errors = [];
        foreach (['canvas_url','canvas_jwks_url','canvas_auth_url','canvas_token_url'] as $key) {
            $value = esc_url_raw(trim((string)wp_unslash($_POST[$key] ?? '')));
            if ($value !== '' && stripos($value, 'https://') !== 0) $errors[] = self::label($key) . ' must use HTTPS.';
            $settings[$key] = $value;
        }
        foreach (['client_id','deployment_id'] as $key) $settings[$key] = sanitize_text_field(wp_unslash($_POST[$key] ?? ''));
        $new_private = trim((string)wp_unslash($_POST['private_key'] ?? ''));
        $new_jwk = trim((string)wp_unslash($_POST['public_jwk'] ?? ''));
        if (!empty($_POST['clear_private_key'])) $settings['private_key'] = '';
        if (!empty($_POST['clear_public_jwk'])) $settings['public_jwk'] = '';
        if ($new_private !== '') {
            if (!function_exists('openssl_encrypt')) $errors[] = 'This server must provide OpenSSL before a Canvas private key can be stored.';
            elseif (strpos($new_private, '-----BEGIN PRIVATE KEY-----') === false) $errors[] = 'The private key must be a PEM private key.';
            else $settings['private_key'] = $new_private;
        }
        if ($new_jwk !== '') {
            $decoded = json_decode($new_jwk, true);
            if (!is_array($decoded) || empty($decoded['kty'])) $errors[] = 'The public JWK must be valid JSON containing kty.';
            else $settings['public_jwk'] = wp_json_encode($decoded);
        }
        $requested_mode = sanitize_key((string)wp_unslash($_POST['operating_mode'] ?? 'disabled'));
        if (!in_array($requested_mode, ['disabled','sandbox'], true)) $errors[] = 'Live mode is locked until an authorized production pilot is approved.';
        $settings['operating_mode'] = in_array($requested_mode, ['disabled','sandbox'], true) ? $requested_mode : 'disabled';
        $settings['environment'] = $settings['operating_mode'] === 'sandbox' ? 'sandbox' : 'disabled';
        $settings['validated_at'] = '';
        $settings['sandbox_enabled'] = false;
        if ($errors) self::redirect_errors($errors);
        $stored = $settings;
        $stored['private_key'] = self::seal((string)$settings['private_key']);
        update_option(self::OPTION, $stored, false);
        MathBinder_Audit_Log::record('update', 'canvas_configuration', 0, ['environment'=>'sandbox','complete'=>self::is_complete($settings)]);
        wp_safe_redirect(add_query_arg('canvas_saved', '1', self::page_url())); exit;
    }

    public static function validate() {
        self::authorize();
        check_admin_referer('mathbinder_validate_canvas_settings', 'mathbinder_canvas_validate_nonce');
        $settings = self::get();
        $errors = [];
        if (!self::is_complete($settings)) $errors[] = 'Complete all Canvas sandbox fields before validation.';
        foreach (['canvas_url','canvas_jwks_url','canvas_auth_url','canvas_token_url'] as $key) {
            if ($settings[$key] !== '' && stripos($settings[$key], 'https://') !== 0) $errors[] = self::label($key) . ' must use HTTPS.';
        }
        if ($errors) self::redirect_errors($errors);
        $settings['validated_at'] = current_time('mysql', true);
        $settings['sandbox_enabled'] = ($settings['operating_mode'] ?? 'disabled') === 'sandbox' && !empty($_POST['enable_sandbox']);
        $stored = $settings;
        $stored['private_key'] = self::seal((string)$settings['private_key']);
        update_option(self::OPTION, $stored, false);
        MathBinder_Audit_Log::record('validate', 'canvas_configuration', 0, ['environment'=>'sandbox','sandbox_enabled'=>$settings['sandbox_enabled']]);
        wp_safe_redirect(add_query_arg('canvas_validated', $settings['sandbox_enabled'] ? 'enabled' : 'ready', self::page_url())); exit;
    }

    public static function render() {
        if (!current_user_can(MathBinder_Capabilities::MANAGE_INTEGRATIONS)) return;
        $s = self::get(); $complete = self::is_complete($s); $validated = $s['validated_at'] !== ''; $readiness = MathBinder_Canvas_Protocol::readiness();
        $tests = MathBinder_Canvas_Diagnostics::local_tests(); $test_summary = MathBinder_Canvas_Diagnostics::summary($tests);
        $preview = MathBinder_Canvas_Diagnostics::preview(); $history = MathBinder_Canvas_Diagnostics::history();
        $mappings = $complete ? MathBinder_Canvas_Repository::mappings($s) : []; $jobs = $complete ? MathBinder_Canvas_Repository::jobs($s) : [];
        $errors = get_transient('mathbinder_canvas_errors_' . get_current_user_id());
        delete_transient('mathbinder_canvas_errors_' . get_current_user_id());
        ?>
        <div class="wrap"><h1>MathBinder Canvas Sandbox</h1>
            <?php if (isset($_GET['canvas_saved'])): ?><div class="notice notice-success is-dismissible"><p>Canvas sandbox settings saved. No data was sent.</p></div><?php endif; ?>
            <?php if (isset($_GET['canvas_validated'])): ?><div class="notice notice-success is-dismissible"><p><?php echo $_GET['canvas_validated'] === 'enabled' ? 'Sandbox configuration validated and explicitly enabled for the future LTI adapter.' : 'Sandbox configuration validated. Data transfer remains disabled.'; ?></p></div><?php endif; ?>
            <?php if (is_array($errors)): foreach ($errors as $error): ?><div class="notice notice-error"><p><?php echo esc_html($error); ?></p></div><?php endforeach; endif; ?>
            <div class="notice <?php echo $s['sandbox_enabled'] ? 'notice-success' : ($complete ? 'notice-info' : 'notice-warning'); ?> inline"><p><strong><?php echo $s['sandbox_enabled'] ? 'Sandbox activation gate is enabled.' : ($complete ? 'Configuration is complete but data transfer is disabled.' : 'Canvas sandbox is not configured.'); ?></strong></p><p>Operating mode: <strong><?php echo esc_html(ucfirst($s['operating_mode'])); ?></strong>. Live mode remains locked in Core 30.28.0.</p></div>
            <p>Only administrators can view this page. Secret values are never displayed after saving and never appear on a teacher screen.</p>
            <form method="post" action="<?php echo esc_url(admin_url('admin-post.php')); ?>" autocomplete="off">
                <input type="hidden" name="action" value="mathbinder_save_canvas_settings"><?php wp_nonce_field(self::NONCE_ACTION, 'mathbinder_canvas_nonce'); ?>
                <table class="form-table" role="presentation">
                    <tr><th scope="row">Operating mode</th><td><label><input type="radio" name="operating_mode" value="disabled" <?php checked($s['operating_mode'], 'disabled'); ?>> Disabled</label><br><label><input type="radio" name="operating_mode" value="sandbox" <?php checked($s['operating_mode'], 'sandbox'); ?>> Sandbox / Test</label><br><label><input type="radio" value="live" disabled> Live (locked)</label><p class="description">Saving a mode never enables transport. Sandbox also requires local validation and the separate activation checkbox below.</p></td></tr>
                    <?php self::text_row('canvas_url','Canvas base URL',$s['canvas_url'],'https://school.instructure.com'); ?>
                    <?php self::text_row('client_id','LTI client ID',$s['client_id'],'Canvas developer key client ID'); ?>
                    <?php self::text_row('deployment_id','LTI deployment ID',$s['deployment_id'],'Canvas deployment ID'); ?>
                    <?php self::text_row('canvas_jwks_url','Canvas JWKS URL',$s['canvas_jwks_url'],'https://canvas.instructure.com/api/lti/security/jwks'); ?>
                    <?php self::text_row('canvas_auth_url','Canvas authorization URL',$s['canvas_auth_url'],'https://canvas.instructure.com/api/lti/authorize_redirect'); ?>
                    <?php self::text_row('canvas_token_url','Canvas access-token URL',$s['canvas_token_url'],'https://canvas.instructure.com/login/oauth2/token'); ?>
                    <tr><th scope="row"><label for="mb-canvas-private">MathBinder private key</label></th><td><textarea id="mb-canvas-private" name="private_key" class="large-text code" rows="5" placeholder="<?php echo $s['private_key'] ? 'Stored — leave blank to keep it' : 'Paste PEM private key'; ?>" autocomplete="new-password"></textarea><?php if ($s['private_key']): ?><br><label><input type="checkbox" name="clear_private_key" value="1"> Remove stored private key</label><?php endif; ?></td></tr>
                    <tr><th scope="row"><label for="mb-canvas-jwk">MathBinder public JWK</label></th><td><textarea id="mb-canvas-jwk" name="public_jwk" class="large-text code" rows="5" placeholder="<?php echo $s['public_jwk'] ? 'Stored — leave blank to keep it' : 'Paste public JWK JSON'; ?>" autocomplete="off"></textarea><?php if ($s['public_jwk']): ?><br><label><input type="checkbox" name="clear_public_jwk" value="1"> Remove stored public JWK</label><?php endif; ?></td></tr>
                </table><?php submit_button('Save Sandbox Configuration'); ?>
            </form>
            <hr><h2>Validation and activation gate</h2><p>Local validation checks required fields and formats only. It does not contact Canvas or transmit MathBinder data.</p>
            <form method="post" action="<?php echo esc_url(admin_url('admin-post.php')); ?>"><input type="hidden" name="action" value="mathbinder_validate_canvas_settings"><?php wp_nonce_field('mathbinder_validate_canvas_settings','mathbinder_canvas_validate_nonce'); ?><label><input type="checkbox" name="enable_sandbox" value="1" <?php checked($s['sandbox_enabled']); ?>> Explicitly enable the sandbox gate after validation</label><?php submit_button('Validate Saved Configuration','secondary'); ?></form>
            <hr><h2>LTI 1.3 readiness</h2>
            <p><strong>Canvas registration JSON:</strong> <a href="<?php echo esc_url(rest_url('mathbinder/v1/canvas/config')); ?>" target="_blank" rel="noopener"><?php echo esc_html(rest_url('mathbinder/v1/canvas/config')); ?></a></p>
            <p><strong>MathBinder public JWKS:</strong> <a href="<?php echo esc_url(rest_url('mathbinder/v1/canvas/jwks')); ?>" target="_blank" rel="noopener"><?php echo esc_html(rest_url('mathbinder/v1/canvas/jwks')); ?></a></p>
            <p>This release installs fail-closed sandbox endpoints. They remain inactive until configuration, validation, activation, signed-request verification, and authorization gates all pass.</p>
            <table class="widefat striped" style="max-width:900px"><thead><tr><th>Service boundary</th><th>Status</th></tr></thead><tbody>
                <?php foreach (MathBinder_Canvas_Protocol::services() as $service): ?><tr><td><?php echo esc_html($service['label']); ?></td><td><?php echo $service['state'] === 'endpoint_ready' ? '<strong>Sandbox endpoint ready — gated</strong>' : 'Deferred'; ?></td></tr><?php endforeach; ?>
            </tbody></table>
            <h3>Transport safety gates</h3><ul>
                <li>Configuration complete: <strong><?php echo $readiness['configuration_complete'] ? 'Yes' : 'No'; ?></strong></li>
                <li>Locally validated: <strong><?php echo $readiness['locally_validated'] ? 'Yes' : 'No'; ?></strong></li>
                <li>Sandbox gate enabled: <strong><?php echo $readiness['activation_gate_enabled'] ? 'Yes' : 'No'; ?></strong></li>
                <li>Authenticated adapter installed: <strong><?php echo $readiness['adapter_installed'] ? 'Yes' : 'No'; ?></strong></li>
                <li>Live transport enabled: <strong><?php echo $readiness['live_transport_enabled'] ? 'Yes' : 'No'; ?></strong></li>
            </ul>
            <p><strong>Grade and evidence rules:</strong> MathBinder keeps the permanent grade and original student work. The future Canvas adapter may send an autograded result, permit a teacher override, and provide a secure evidence link for SpeedGrader.</p>
            <p><strong>Security reminder:</strong> Never paste the Canvas private key into chat, email, a public page, or a screenshot.</p>
            <?php self::render_testing($tests, $test_summary, $preview, $history, $mappings, $jobs); ?>
        </div><?php
    }

    private static function render_testing(array $tests, array $summary, array $preview, array $history, array $mappings, array $jobs) { ?>
        <hr id="testing"><h2>Canvas administration and sandbox testing</h2>
        <p>These checks and previews are local. They do not contact Canvas, create accounts, change enrollments, submit grades, or modify Evidence Folders.</p>
        <div class="notice <?php echo $summary['ready'] ? 'notice-success' : 'notice-warning'; ?> inline"><p><strong>Local readiness: <?php echo esc_html($summary['passed'] . ' of ' . $summary['total']); ?> checks passed.</strong></p></div>
        <table class="widefat striped" style="max-width:1000px"><thead><tr><th>Check</th><th>Result</th><th>Detail</th></tr></thead><tbody><?php foreach ($tests as $test): ?><tr><td><?php echo esc_html($test['label']); ?></td><td><strong><?php echo $test['passed'] ? 'Pass' : 'Action needed'; ?></strong></td><td><?php echo esc_html($test['detail']); ?></td></tr><?php endforeach; ?></tbody></table>
        <div style="display:flex;gap:10px;flex-wrap:wrap;margin:16px 0">
            <?php self::test_button('mathbinder_canvas_run_diagnostics','Run and Record Local Diagnostics','primary'); ?>
            <?php self::test_button('mathbinder_canvas_preview_launch','Simulate LTI Launch','secondary'); ?>
            <?php self::test_button('mathbinder_canvas_preview_services','Preview Deep Link, Grade, and Evidence','secondary'); ?>
        </div>
        <?php if ($preview): ?><div class="notice notice-info inline"><h3><?php echo esc_html($preview['title'] ?? 'Preview'); ?></h3><p><?php echo esc_html($preview['notice'] ?? ''); ?></p></div><?php self::test_table((array)($preview['checks'] ?? [])); ?>
            <?php if (!empty($preview['rows'])): ?><h3>Roster match suggestions</h3><table class="widefat striped"><thead><tr><th>Canvas ID hash</th><th>Name</th><th>Role</th><th>Suggested result</th><th>MathBinder user</th></tr></thead><tbody><?php foreach ($preview['rows'] as $row): ?><tr><td><code><?php echo esc_html($row['external_id_hash']); ?></code></td><td><?php echo esc_html($row['name']); ?></td><td><?php echo esc_html($row['role']); ?></td><td><?php echo esc_html($row['match']); ?></td><td><?php echo $row['mathbinder_user_id'] ? esc_html('#'.$row['mathbinder_user_id']) : '—'; ?></td></tr><?php endforeach; ?></tbody></table><?php endif; ?>
        <?php endif; ?>
        <h3>Roster synchronization preview</h3><p>Paste an administrator-provided NRPS sample response or member array. Use test data only; do not paste real student information into an unauthorized environment.</p>
        <form method="post" action="<?php echo esc_url(admin_url('admin-post.php')); ?>"><input type="hidden" name="action" value="mathbinder_canvas_preview_roster"><?php wp_nonce_field('mathbinder_canvas_preview_roster','mathbinder_canvas_test_nonce'); ?><textarea name="roster_json" class="large-text code" rows="6" placeholder='{"members":[{"user_id":"test-1","name":"Test Student","email":"test@example.invalid","roles":["Learner"]}]}'></textarea><?php submit_button('Preview Roster Matches','secondary'); ?></form>
        <h3>Course and identity mapping review</h3>
        <?php if (!$mappings): ?><p>No deployment-scoped mappings are waiting for review.</p><?php else: ?><table class="widefat striped"><thead><tr><th>Type</th><th>External ID hash</th><th>MathBinder target</th><th>Status</th><th>Updated</th></tr></thead><tbody><?php foreach ($mappings as $row): ?><tr><td><?php echo esc_html($row['mapping_type']); ?></td><td><code><?php echo esc_html(substr(hash('sha256',(string)$row['external_id']),0,12)); ?></code></td><td><?php echo esc_html($row['mathbinder_type'].' #'.$row['mathbinder_id']); ?></td><td><?php echo esc_html($row['status']); ?></td><td><?php echo esc_html($row['updated_at']); ?></td></tr><?php endforeach; ?></tbody></table><?php endif; ?>
        <h3>Synchronization queue and errors</h3>
        <?php if (!$jobs): ?><p>No Canvas synchronization jobs have been recorded for this deployment.</p><?php else: ?><table class="widefat striped"><thead><tr><th>ID</th><th>Job</th><th>Direction</th><th>Status</th><th>Attempts</th><th>Sanitized error</th><th>Updated</th></tr></thead><tbody><?php foreach ($jobs as $job): ?><tr><td><?php echo esc_html($job['id']); ?></td><td><?php echo esc_html($job['job_type']); ?></td><td><?php echo esc_html($job['direction']); ?></td><td><?php echo esc_html($job['status']); ?></td><td><?php echo esc_html($job['attempts']); ?></td><td><?php echo esc_html($job['last_error']); ?></td><td><?php echo esc_html($job['updated_at']); ?></td></tr><?php endforeach; ?></tbody></table><?php endif; ?>
        <h3>Diagnostic history</h3>
        <?php if (!$history): ?><p>No diagnostic runs have been recorded.</p><?php else: ?><table class="widefat striped" style="max-width:900px"><thead><tr><th>UTC time</th><th>Test</th><th>Result</th></tr></thead><tbody><?php foreach ($history as $entry): ?><tr><td><?php echo esc_html($entry['time']); ?></td><td><?php echo esc_html(ucwords(str_replace('_',' ',$entry['type']))); ?></td><td><?php echo esc_html(($entry['summary']['passed'] ?? 0).' / '.($entry['summary']['total'] ?? 0).' passed'); ?></td></tr><?php endforeach; ?></tbody></table><?php self::test_button('mathbinder_canvas_clear_diagnostics','Clear Diagnostic History','secondary'); endif; ?>
        <h3>Canvas administrator certification checklist</h3><ol><li>Obtain written authorization and an administrator-controlled Canvas sandbox.</li><li>Register the configuration JSON and confirm the MathBinder JWKS endpoint.</li><li>Enter the sandbox client ID, deployment ID, and Canvas endpoints; keep credentials off teacher screens.</li><li>Run local diagnostics and resolve every action-needed result.</li><li>Enable Sandbox mode and its separate activation gate.</li><li>Verify an RS256-signed instructor launch and keep new mappings pending review.</li><li>Preview roster matches before approving any identity or enrollment change.</li><li>Verify a Mastery Path Deep Link without changing the authoritative MathBinder assignment.</li><li>Pass back only a teacher-approved test score and confirm the audit record.</li><li>Verify the secure Evidence Folder handoff and confirm original student work is preserved.</li><li>Disable the sandbox gate after testing and review sanitized errors and reconciliation records.</li></ol>
    <?php }

    private static function test_button($action, $label, $class) { ?><form method="post" action="<?php echo esc_url(admin_url('admin-post.php')); ?>" style="display:inline-block"><input type="hidden" name="action" value="<?php echo esc_attr($action); ?>"><?php wp_nonce_field($action,'mathbinder_canvas_test_nonce'); ?><?php submit_button($label,$class,'submit',false); ?></form><?php }
    private static function test_table(array $checks) { ?><table class="widefat striped" style="max-width:1000px"><thead><tr><th>Preview check</th><th>Result</th><th>Detail</th></tr></thead><tbody><?php foreach ($checks as $check): ?><tr><td><?php echo esc_html($check['label']); ?></td><td><strong><?php echo $check['passed'] ? 'Pass' : 'Action needed'; ?></strong></td><td><?php echo esc_html($check['detail']); ?></td></tr><?php endforeach; ?></tbody></table><?php }

    private static function text_row($key,$label,$value,$placeholder) { ?><tr><th scope="row"><label for="mb-<?php echo esc_attr($key); ?>"><?php echo esc_html($label); ?></label></th><td><input id="mb-<?php echo esc_attr($key); ?>" name="<?php echo esc_attr($key); ?>" class="large-text code" value="<?php echo esc_attr($value); ?>" placeholder="<?php echo esc_attr($placeholder); ?>" spellcheck="false" autocomplete="off"></td></tr><?php }
    private static function label($key) { return ucwords(str_replace('_',' ',$key)); }
    private static function authorize() { if (!current_user_can(MathBinder_Capabilities::MANAGE_INTEGRATIONS)) wp_die('You do not have permission to manage Canvas settings.', 403); }
    private static function seal($value) {
        if ($value === '' || strpos($value, 'mbenc:') === 0) return $value;
        if (!function_exists('openssl_encrypt')) return $value;
        $key = hash('sha256', wp_salt('auth'), true); $iv = random_bytes(12); $tag = '';
        $cipher = openssl_encrypt($value, 'aes-256-gcm', $key, OPENSSL_RAW_DATA, $iv, $tag);
        return $cipher === false ? $value : 'mbenc:' . base64_encode($iv . $tag . $cipher);
    }
    private static function unseal($value) {
        if (strpos($value, 'mbenc:') !== 0 || !function_exists('openssl_decrypt')) return $value;
        $raw = base64_decode(substr($value, 6), true);
        if ($raw === false || strlen($raw) < 29) return '';
        $iv = substr($raw, 0, 12); $tag = substr($raw, 12, 16); $cipher = substr($raw, 28);
        $plain = openssl_decrypt($cipher, 'aes-256-gcm', hash('sha256', wp_salt('auth'), true), OPENSSL_RAW_DATA, $iv, $tag);
        return $plain === false ? '' : $plain;
    }
    private static function redirect_errors($errors) { set_transient('mathbinder_canvas_errors_' . get_current_user_id(), $errors, 60); wp_safe_redirect(self::page_url()); exit; }
    private static function page_url() { return admin_url('options-general.php?page=' . self::PAGE_SLUG); }
}
