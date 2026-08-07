<?php
if (!defined('ABSPATH')) exit;

final class MathBinder_Identity_Service {
    const ROLES = ['student', 'parent', 'teacher', 'administrator'];

    public static function register() {
        add_action('user_register', [__CLASS__, 'provision_profile']);
        add_action('wp_login', [__CLASS__, 'on_login'], 10, 2);
    }

    public static function install() {
        $users = get_users(['fields' => 'ID']);
        foreach ($users as $user_id) self::provision_profile($user_id);
        self::seed_administrator_assignments();
    }

    public static function on_login($login, $user) {
        if ($user instanceof WP_User) self::provision_profile($user->ID);
    }

    public static function provision_profile($user_id) {
        global $wpdb;
        $user_id = absint($user_id);
        if (!$user_id || !get_user_by('id', $user_id)) return false;
        $table = $wpdb->prefix . 'mb_identity_profiles';
        $exists = $wpdb->get_var($wpdb->prepare("SELECT id FROM {$table} WHERE user_id = %d", $user_id));
        if ($exists) return (int) $exists;
        $now = current_time('mysql', true);
        $wpdb->insert($table, [
            'user_id' => $user_id,
            'permanent_key' => wp_generate_uuid4(),
            'account_status' => 'active',
            'minor_status' => 'unknown',
            'created_at' => $now,
            'updated_at' => $now,
        ], ['%d', '%s', '%s', '%s', '%s', '%s']);
        return (int) $wpdb->insert_id;
    }

    private static function seed_administrator_assignments() {
        foreach (get_users(['role' => 'administrator', 'fields' => 'ID']) as $user_id) {
            self::assign_role($user_id, 'administrator', 'site', 0, 'active', 'wordpress');
        }
    }

    public static function permanent_key($user_id) {
        global $wpdb;
        self::provision_profile($user_id);
        return (string) $wpdb->get_var($wpdb->prepare(
            "SELECT permanent_key FROM {$wpdb->prefix}mb_identity_profiles WHERE user_id = %d",
            absint($user_id)
        ));
    }

    public static function assignments($user_id, $status = 'active') {
        global $wpdb;
        $sql = "SELECT * FROM {$wpdb->prefix}mb_role_assignments WHERE user_id = %d";
        $args = [absint($user_id)];
        if ($status !== '') { $sql .= ' AND status = %s'; $args[] = sanitize_key($status); }
        $sql .= ' ORDER BY role_key, scope_type, scope_id';
        return $wpdb->get_results($wpdb->prepare($sql, $args), ARRAY_A) ?: [];
    }

    public static function assign_role($user_id, $role_key, $scope_type = 'site', $scope_id = 0, $status = 'active', $source = 'administrator') {
        global $wpdb;
        $user_id = absint($user_id);
        $role_key = sanitize_key($role_key);
        if (!$user_id || !in_array($role_key, self::ROLES, true)) return new WP_Error('invalid_role', 'The role assignment is invalid.');
        self::provision_profile($user_id);
        $now = current_time('mysql', true);
        $table = $wpdb->prefix . 'mb_role_assignments';
        $existing = $wpdb->get_var($wpdb->prepare(
            "SELECT id FROM {$table} WHERE user_id=%d AND role_key=%s AND scope_type=%s AND scope_id=%d",
            $user_id, $role_key, sanitize_key($scope_type), absint($scope_id)
        ));
        $data = ['status' => sanitize_key($status), 'source' => sanitize_key($source), 'approved_by' => get_current_user_id(), 'updated_at' => $now];
        if ($existing) {
            $wpdb->update($table, $data, ['id' => $existing], ['%s','%s','%d','%s'], ['%d']);
            $assignment_id = (int) $existing;
        } else {
            $data += ['user_id'=>$user_id, 'role_key'=>$role_key, 'scope_type'=>sanitize_key($scope_type), 'scope_id'=>absint($scope_id), 'created_at'=>$now];
            $wpdb->insert($table, $data, ['%s','%s','%d','%s','%d','%s','%s','%d','%s','%s']);
            $assignment_id = (int) $wpdb->insert_id;
        }
        MathBinder_Audit_Log::record('assign_role', 'identity', $user_id, ['assignment_id'=>$assignment_id, 'role'=>$role_key, 'status'=>$status]);
        return $assignment_id;
    }

    public static function active_workspace($user_id) {
        $assignments = self::assignments($user_id);
        if (!$assignments) return null;
        $selected = absint(get_user_meta($user_id, 'mb_active_role_assignment', true));
        foreach ($assignments as $assignment) if ((int) $assignment['id'] === $selected) return $assignment;
        return $assignments[0];
    }

    public static function select_workspace($user_id, $assignment_id) {
        foreach (self::assignments($user_id) as $assignment) {
            if ((int) $assignment['id'] !== absint($assignment_id)) continue;
            update_user_meta($user_id, 'mb_active_role_assignment', (int) $assignment['id']);
            MathBinder_Audit_Log::record('switch_workspace', 'identity', $user_id, ['assignment_id'=>(int)$assignment['id']]);
            return true;
        }
        return new WP_Error('workspace_denied', 'That workspace is not assigned to this account.');
    }

    public static function duplicate_candidates($email, $exclude_user_id = 0) {
        $email = sanitize_email($email);
        if (!$email) return [];
        $user = get_user_by('email', $email);
        if (!$user || (int) $user->ID === absint($exclude_user_id)) return [];
        return [['user_id'=>(int)$user->ID, 'display_name'=>$user->display_name, 'masked_email'=>self::mask_email($user->user_email)]];
    }

    public static function link_external_identity($user_id, $provider, $issuer, $subject) {
        global $wpdb;
        $user_id = absint($user_id);
        if (!$user_id || !get_user_by('id', $user_id)) return new WP_Error('invalid_user', 'The MathBinder account does not exist.');
        $provider = sanitize_key($provider); $issuer = esc_url_raw($issuer); $subject = sanitize_text_field($subject);
        if (!$provider || !$subject) return new WP_Error('invalid_external_identity', 'The external identity is incomplete.');
        $table = $wpdb->prefix . 'mb_external_identities';
        $owner = $wpdb->get_var($wpdb->prepare("SELECT user_id FROM {$table} WHERE provider=%s AND issuer=%s AND subject=%s", $provider, $issuer, $subject));
        if ($owner && (int)$owner !== $user_id) return new WP_Error('duplicate_external_identity', 'That external identity is already connected to another MathBinder account.');
        if ($owner) return true;
        $now = current_time('mysql', true);
        $result = $wpdb->insert($table, ['user_id'=>$user_id,'provider'=>$provider,'issuer'=>$issuer,'subject'=>$subject,'status'=>'active','created_at'=>$now,'updated_at'=>$now], ['%d','%s','%s','%s','%s','%s','%s']);
        if ($result) MathBinder_Audit_Log::record('link_external_identity', 'identity', $user_id, ['provider'=>$provider]);
        return (bool)$result;
    }

    private static function mask_email($email) {
        $parts = explode('@', $email, 2);
        if (count($parts) !== 2) return '';
        return substr($parts[0], 0, 1) . '***@' . $parts[1];
    }

    public static function request_transfer($user_id, $from_type, $from_id, $to_type, $to_id, array $context = []) {
        global $wpdb;
        $now = current_time('mysql', true);
        $wpdb->insert($wpdb->prefix . 'mb_identity_transfers', [
            'user_id'=>absint($user_id), 'from_scope_type'=>sanitize_key($from_type), 'from_scope_id'=>absint($from_id),
            'to_scope_type'=>sanitize_key($to_type), 'to_scope_id'=>absint($to_id), 'status'=>'pending',
            'preserve_personal_records'=>1, 'initiated_by'=>get_current_user_id(), 'context_json'=>wp_json_encode($context), 'created_at'=>$now,
        ], ['%d','%s','%d','%s','%d','%s','%d','%d','%s','%s']);
        $id = (int) $wpdb->insert_id;
        MathBinder_Audit_Log::record('request_transfer', 'identity_transfer', $id, ['user_id'=>absint($user_id)]);
        return $id;
    }
}
