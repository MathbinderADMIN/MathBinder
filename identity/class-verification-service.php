<?php
if (!defined('ABSPATH')) exit;

final class MathBinder_Verification_Service {
    public static function register() {
        add_action('user_register', [__CLASS__, 'send_email_verification'], 20);
        add_action('admin_post_mb_verify_email', [__CLASS__, 'handle_email_verification']);
        add_action('admin_post_nopriv_mb_verify_email', [__CLASS__, 'handle_email_verification']);
    }

    public static function send_email_verification($user_id) {
        $user = get_user_by('id', absint($user_id));
        if (!$user) return false;
        $token = wp_generate_password(32, false, false);
        update_user_meta($user->ID, 'mb_email_verification_hash', wp_hash_password($token));
        update_user_meta($user->ID, 'mb_email_verification_expires', time() + DAY_IN_SECONDS);
        $url = add_query_arg(['action'=>'mb_verify_email', 'uid'=>$user->ID, 'token'=>$token], admin_url('admin-post.php'));
        return wp_mail($user->user_email, 'Verify your MathBinder email', "Verify your MathBinder email address:\n\n" . esc_url_raw($url) . "\n\nThis link expires in 24 hours.");
    }

    public static function handle_email_verification() {
        $user_id = absint($_GET['uid'] ?? 0);
        $token = sanitize_text_field(wp_unslash($_GET['token'] ?? ''));
        $hash = (string) get_user_meta($user_id, 'mb_email_verification_hash', true);
        $expires = (int) get_user_meta($user_id, 'mb_email_verification_expires', true);
        if (!$user_id || !$token || !$hash || $expires < time() || !wp_check_password($token, $hash)) {
            wp_die('This verification link is invalid or expired.', 'MathBinder email verification', ['response'=>400]);
        }
        update_user_meta($user_id, 'mb_email_verified_at', current_time('mysql', true));
        delete_user_meta($user_id, 'mb_email_verification_hash');
        delete_user_meta($user_id, 'mb_email_verification_expires');
        self::record($user_id, 'email', 'verified', $user_id);
        MathBinder_Audit_Log::record('verify_email', 'identity', $user_id);
        wp_safe_redirect(add_query_arg('mb_email_verified', '1', home_url('/'))); exit;
    }

    public static function record($user_id, $type, $status, $verified_by = 0, $organization_id = 0, array $evidence = []) {
        global $wpdb;
        $table = $wpdb->prefix . 'mb_verifications';
        $now = current_time('mysql', true);
        $existing = $wpdb->get_var($wpdb->prepare("SELECT id FROM {$table} WHERE user_id=%d AND verification_type=%s AND organization_id=%d", absint($user_id), sanitize_key($type), absint($organization_id)));
        $data = ['status'=>sanitize_key($status), 'verified_by'=>absint($verified_by), 'evidence_json'=>wp_json_encode($evidence), 'verified_at'=>$status==='verified'?$now:null, 'updated_at'=>$now];
        if ($existing) return $wpdb->update($table, $data, ['id'=>$existing]);
        $data += ['user_id'=>absint($user_id), 'verification_type'=>sanitize_key($type), 'organization_id'=>absint($organization_id), 'created_at'=>$now];
        return $wpdb->insert($table, $data);
    }

    public static function authorize_minor($student_user_id, $guardian_user_id = 0, $guardian_email = '', $source = 'parent', $organization_id = 0) {
        global $wpdb;
        $now = current_time('mysql', true);
        $status = in_array($source, ['parent','school'], true) ? 'approved' : 'pending';
        $result = $wpdb->insert($wpdb->prefix.'mb_guardian_relationships', [
            'student_user_id'=>absint($student_user_id), 'guardian_user_id'=>absint($guardian_user_id), 'guardian_email'=>sanitize_email($guardian_email),
            'relationship_type'=>'guardian', 'approval_status'=>$status, 'authorization_source'=>sanitize_key($source), 'organization_id'=>absint($organization_id),
            'consented_at'=>$status==='approved'?$now:null, 'created_at'=>$now, 'updated_at'=>$now,
        ], ['%d','%d','%s','%s','%s','%s','%d','%s','%s','%s']);
        if ($result) MathBinder_Audit_Log::record('authorize_minor', 'identity', $student_user_id, ['source'=>$source, 'organization_id'=>absint($organization_id)]);
        return $result;
    }
}
