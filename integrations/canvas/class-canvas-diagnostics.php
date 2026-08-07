<?php
if (!defined('ABSPATH')) exit;

/** Administrator-only, non-mutating Canvas readiness and preview console. */
final class MathBinder_Canvas_Diagnostics {
    const HISTORY_OPTION = 'mathbinder_canvas_diagnostic_history_v1';
    const PREVIEW_TRANSIENT = 'mathbinder_canvas_preview_';

    public static function register() {
        add_action('admin_post_mathbinder_canvas_run_diagnostics', [__CLASS__, 'handle_diagnostics']);
        add_action('admin_post_mathbinder_canvas_preview_launch', [__CLASS__, 'handle_launch_preview']);
        add_action('admin_post_mathbinder_canvas_preview_roster', [__CLASS__, 'handle_roster_preview']);
        add_action('admin_post_mathbinder_canvas_preview_services', [__CLASS__, 'handle_service_previews']);
        add_action('admin_post_mathbinder_canvas_clear_diagnostics', [__CLASS__, 'handle_clear']);
    }

    public static function local_tests() {
        $s = MathBinder_Canvas_Settings::get();
        $config = MathBinder_Canvas_Transport::config();
        $jwk = json_decode((string)($s['public_jwk'] ?? ''), true);
        $private_ok = !empty($s['private_key']) && strpos((string)$s['private_key'], '-----BEGIN PRIVATE KEY-----') !== false;
        $tests = [
            self::test('wordpress_https', 'MathBinder site uses HTTPS', is_ssl(), is_ssl() ? 'HTTPS detected.' : 'HTTPS is required before an LTI deployment can be authorized.'),
            self::test('openssl', 'OpenSSL signing and verification available', function_exists('openssl_sign') && function_exists('openssl_verify'), 'Required for RS256 launch verification and service authorization.'),
            self::test('configuration', 'Required deployment fields complete', MathBinder_Canvas_Settings::is_complete($s), 'No secret values are included in this report.'),
            self::test('mode', 'Operating mode is fail-closed', in_array(($s['operating_mode'] ?? 'disabled'), ['disabled','sandbox'], true), 'Production mode is locked in Core 30.28.0.'),
            self::test('validation', 'Configuration has been locally validated', !empty($s['validated_at']), !empty($s['validated_at']) ? 'Validated at ' . $s['validated_at'] . ' UTC.' : 'Run local validation after saving settings.'),
            self::test('private_key', 'MathBinder private key format recognized', $private_ok, 'The private key itself is never shown.'),
            self::test('public_jwk', 'Public JWK is an RSA signing key', is_array($jwk) && ($jwk['kty'] ?? '') === 'RSA' && !empty($jwk['kid']) && !empty($jwk['n']) && !empty($jwk['e']), 'Canvas uses the matching public key to verify MathBinder messages.'),
            self::test('registration', 'Canvas registration document is complete', !empty($config['oidc_initiation_url']) && !empty($config['target_link_uri']) && !empty($config['public_jwk_url']) && count((array)($config['scopes'] ?? [])) >= 3, 'OIDC, launch, JWKS, AGS, and NRPS declarations checked.'),
            self::test('adapter', 'Authenticated LTI adapter installed', MathBinder_Canvas_Transport::adapter_ready(), 'Adapter availability does not by itself enable transmission.'),
            self::test('transport_gate', 'Transport activation agrees with mode', self::transport_gate_consistent($s), 'Disabled mode always blocks transport; Sandbox requires validation and explicit authorization.'),
        ];
        return $tests;
    }

    public static function summary(array $tests) {
        $passed = count(array_filter($tests, function($test){ return !empty($test['passed']); }));
        return ['passed'=>$passed, 'total'=>count($tests), 'ready'=>$passed === count($tests)];
    }

    public static function history() {
        $history = get_option(self::HISTORY_OPTION, []);
        return is_array($history) ? $history : [];
    }

    public static function preview() {
        $value = get_transient(self::PREVIEW_TRANSIENT . get_current_user_id());
        delete_transient(self::PREVIEW_TRANSIENT . get_current_user_id());
        return is_array($value) ? $value : [];
    }

    public static function handle_diagnostics() {
        self::authorize('mathbinder_canvas_run_diagnostics');
        $tests = self::local_tests();
        self::record('local_readiness', self::summary($tests), $tests);
        self::redirect(['canvas_test'=>'diagnostics']);
    }

    public static function handle_launch_preview() {
        self::authorize('mathbinder_canvas_preview_launch');
        $s = MathBinder_Canvas_Settings::get();
        $claims = [
            'iss'=>rtrim((string)$s['canvas_url'], '/'),
            'aud'=>(string)$s['client_id'],
            'sub'=>'preview-user-' . substr(hash('sha256', (string)get_current_user_id()), 0, 10),
            'https://purl.imsglobal.org/spec/lti/claim/deployment_id'=>(string)$s['deployment_id'],
            'https://purl.imsglobal.org/spec/lti/claim/message_type'=>'LtiResourceLinkRequest',
            'https://purl.imsglobal.org/spec/lti/claim/version'=>'1.3.0',
            'https://purl.imsglobal.org/spec/lti/claim/roles'=>['http://purl.imsglobal.org/vocab/lis/v2/membership#Instructor'],
            'https://purl.imsglobal.org/spec/lti/claim/context'=>['id'=>'preview-course','label'=>'PREVIEW','title'=>'MathBinder Canvas Preview'],
        ];
        $checks = [
            self::test('issuer', 'Issuer matches saved Canvas instance', $claims['iss'] !== '' && $claims['iss'] === rtrim((string)$s['canvas_url'], '/'), 'Synthetic claim only.'),
            self::test('audience', 'Audience matches client ID', $claims['aud'] !== '' && $claims['aud'] === (string)$s['client_id'], 'Synthetic claim only.'),
            self::test('deployment', 'Deployment claim matches', $claims['https://purl.imsglobal.org/spec/lti/claim/deployment_id'] !== '' && $claims['https://purl.imsglobal.org/spec/lti/claim/deployment_id'] === (string)$s['deployment_id'], 'Synthetic claim only.'),
            self::test('message', 'Resource-link message supported', true, 'LtiResourceLinkRequest'),
            self::test('mapping', 'Course mapping would require review', true, 'Preview course remains pending_review; no mapping was saved.'),
        ];
        self::store_preview('LTI launch simulation', 'No JWT was accepted and no Canvas request or MathBinder record was created.', $checks);
        self::record('launch_simulation', self::summary($checks), $checks);
        self::redirect(['canvas_test'=>'launch']);
    }

    public static function handle_roster_preview() {
        self::authorize('mathbinder_canvas_preview_roster');
        $raw = trim((string)wp_unslash($_POST['roster_json'] ?? ''));
        $data = json_decode($raw, true);
        $members = is_array($data) && isset($data['members']) && is_array($data['members']) ? $data['members'] : (is_array($data) && array_values($data) === $data ? $data : []);
        $rows = [];
        foreach (array_slice($members, 0, 100) as $member) {
            if (!is_array($member)) continue;
            $external = sanitize_text_field((string)($member['user_id'] ?? $member['id'] ?? ''));
            $email = sanitize_email((string)($member['email'] ?? ''));
            $user = $email !== '' ? get_user_by('email', $email) : false;
            $rows[] = [
                'external_id_hash'=>$external === '' ? '' : substr(hash('sha256', $external), 0, 12),
                'name'=>sanitize_text_field((string)($member['name'] ?? 'Unnamed member')),
                'role'=>sanitize_text_field(implode(', ', (array)($member['roles'] ?? []))),
                'match'=>$user ? 'Possible email match — review required' : 'No automatic match',
                'mathbinder_user_id'=>$user ? (int)$user->ID : 0,
            ];
        }
        $checks = [
            self::test('json', 'Roster JSON is readable', is_array($data), is_array($data) ? 'Valid JSON supplied.' : 'Paste a JSON array or an NRPS object containing members.'),
            self::test('members', 'Roster contains previewable members', count($rows) > 0, count($rows) . ' member(s) previewed; maximum 100.'),
            self::test('no_write', 'No account changes performed', true, 'Matches are suggestions only; no user, role, enrollment, or mapping was changed.'),
        ];
        self::store_preview('Roster synchronization preview', 'External IDs are hashed in the report. Every suggested match requires human review.', $checks, ['rows'=>$rows]);
        self::record('roster_preview', self::summary($checks), $checks);
        self::redirect(['canvas_test'=>'roster']);
    }

    public static function handle_service_previews() {
        self::authorize('mathbinder_canvas_preview_services');
        $queue = MathBinder_Canvas_Integration::queue();
        $first = $queue ? reset($queue) : [];
        $path_id = sanitize_text_field((string)($first['mathbinder_id'] ?? 'preview-mastery-path'));
        $target = add_query_arg(['mathbinder_path'=>$path_id], home_url('/teacher-dashboard/'));
        $evidence = MathBinder_Canvas_Integration::adapter()->create_evidence_handoff(['student_id'=>get_current_user_id(), 'assignment_id'=>$path_id]);
        $checks = [
            self::test('deep_link', 'Deep Linking item can be assembled', $path_id !== '', 'Target: ' . $target . ' — preview only; no JWT response was sent.'),
            self::test('roster_policy', 'Roster synchronization is preview-first', true, 'No student account, role, enrollment, or work can be changed by this preview.'),
            self::test('grade_unapproved', 'Unapproved grade is blocked', is_wp_error(MathBinder_Canvas_Integration::adapter()->pass_grade(['teacher_approved'=>false])), 'No endpoint was contacted.'),
            self::test('grade_approved_preview', 'Approved grade payload can be previewed safely', true, 'Score 85/100, FullyGraded; no endpoint or Canvas user ID supplied, so nothing was sent.'),
            self::test('evidence', 'Evidence Folder handoff can be generated', !is_wp_error($evidence) && is_string($evidence), is_wp_error($evidence) ? $evidence->get_error_message() : 'A MathBinder URL was generated without opening or transmitting it.'),
        ];
        self::store_preview('Deep Link, roster, grade, and evidence previews', 'All results are local representations. No Canvas API request was made.', $checks);
        self::record('service_previews', self::summary($checks), $checks);
        self::redirect(['canvas_test'=>'services']);
    }

    public static function handle_clear() {
        self::authorize('mathbinder_canvas_clear_diagnostics');
        delete_option(self::HISTORY_OPTION);
        self::redirect(['canvas_test'=>'cleared']);
    }

    private static function test($id, $label, $passed, $detail) {
        return ['id'=>sanitize_key($id), 'label'=>sanitize_text_field($label), 'passed'=>(bool)$passed, 'detail'=>sanitize_text_field($detail)];
    }

    private static function transport_gate_consistent(array $s) {
        $mode = $s['operating_mode'] ?? 'disabled';
        if ($mode === 'disabled') return empty($s['sandbox_enabled']);
        if ($mode !== 'sandbox') return false;
        return empty($s['sandbox_enabled']) || (!empty($s['validated_at']) && MathBinder_Canvas_Settings::is_complete($s));
    }

    private static function record($type, array $summary, array $tests) {
        $history = self::history();
        array_unshift($history, ['type'=>sanitize_key($type), 'time'=>current_time('mysql', true), 'summary'=>$summary, 'tests'=>$tests]);
        update_option(self::HISTORY_OPTION, array_slice($history, 0, 20), false);
        MathBinder_Audit_Log::record('test', 'canvas_diagnostics', 0, ['type'=>sanitize_key($type), 'passed'=>(int)$summary['passed'], 'total'=>(int)$summary['total']]);
    }

    private static function store_preview($title, $notice, array $checks, array $extra = []) {
        set_transient(self::PREVIEW_TRANSIENT . get_current_user_id(), array_merge(['title'=>$title, 'notice'=>$notice, 'checks'=>$checks], $extra), 5 * MINUTE_IN_SECONDS);
    }

    private static function authorize($action) {
        if (!current_user_can(MathBinder_Capabilities::MANAGE_INTEGRATIONS)) wp_die('You do not have permission to test Canvas settings.', 403);
        check_admin_referer($action, 'mathbinder_canvas_test_nonce');
    }

    private static function redirect(array $args) {
        wp_safe_redirect(add_query_arg($args, admin_url('options-general.php?page=' . MathBinder_Canvas_Settings::PAGE_SLUG . '#testing')));
        exit;
    }
}
