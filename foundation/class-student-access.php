<?php
if (!defined('ABSPATH')) exit;

/** Resolve student content coverage without changing or deleting saved work. */
final class MathBinder_Student_Access {
    const FREE = 'free';
    const CLASSROOM = 'classroom';
    const FAMILY = 'family';
    const DIRECT = 'direct';

    public static function register() {
        add_filter('mathbinder_student_has_full_access', [__CLASS__, 'filter_full_access'], 10, 2);
    }

    public static function filter_full_access($allowed, $user_id) {
        return $allowed || self::has_full_access($user_id);
    }

    public static function has_full_access($user_id = 0) {
        $coverage = self::coverage($user_id ?: get_current_user_id());
        return !empty($coverage['full_access']);
    }

    public static function coverage($user_id) {
        $user_id = absint($user_id);
        $user = get_user_by('id', $user_id);
        $base = ['full_access'=>false, 'source'=>self::FREE, 'label'=>'Free student access', 'class_id'=>0, 'organization_id'=>0, 'license_id'=>0];
        if (!$user || !$user->exists()) return $base;
        if (user_can($user, 'manage_options')) return array_merge($base, ['full_access'=>true, 'source'=>'administrator', 'label'=>'Administrator access']);

        $classroom = self::classroom_coverage($user_id);
        if ($classroom) return array_merge($base, $classroom, ['full_access'=>true, 'source'=>self::CLASSROOM]);

        $family = self::family_coverage($user_id);
        if ($family) return array_merge($base, $family, ['full_access'=>true, 'source'=>self::FAMILY]);

        $direct = MathBinder_Organization_Service::coverage_for_user($user_id);
        if ($direct && self::license_window_active($direct)) {
            return array_merge($base, ['full_access'=>true, 'source'=>self::DIRECT, 'label'=>'Direct premium access', 'organization_id'=>absint($direct['organization_id']), 'license_id'=>absint($direct['license_id'])]);
        }
        return $base;
    }

    private static function classroom_coverage($user_id) {
        global $wpdb;
        $rows = $wpdb->get_results($wpdb->prepare(
            "SELECT e.id enrollment_id,e.class_id,e.source,e.approved_by,c.name class_name,c.organization_id,c.term_id,o.name organization_name,l.id license_id,l.status license_status,l.trial_ends_at,l.grace_ends_at,l.renews_at,l.canceled_at
             FROM {$wpdb->prefix}mb_enrollments e
             JOIN {$wpdb->prefix}mb_classes c ON c.id=e.class_id AND c.status='active'
             JOIN {$wpdb->prefix}mb_organizations o ON o.id=c.organization_id AND o.status='active'
             JOIN {$wpdb->prefix}mb_licenses l ON l.organization_id=o.id AND l.status IN ('active','trial','grace')
             LEFT JOIN {$wpdb->prefix}mb_terms t ON t.id=c.term_id
             WHERE e.user_id=%d AND e.role_key='student' AND e.status='active'
               AND (c.term_id=0 OR (t.status='active' AND (t.starts_on IS NULL OR t.starts_on<=UTC_DATE()) AND (t.ends_on IS NULL OR t.ends_on>=UTC_DATE())))
             ORDER BY CASE l.status WHEN 'active' THEN 1 WHEN 'trial' THEN 2 ELSE 3 END,l.id ASC",
            $user_id
        ), ARRAY_A) ?: [];
        foreach ($rows as $row) {
            if (!self::enrollment_authorized($user_id, $row)) continue;
            if (!self::license_window_active($row)) continue;
            return ['label'=>'Full access through '.($row['class_name'] ?: 'your class'), 'class_id'=>absint($row['class_id']), 'organization_id'=>absint($row['organization_id']), 'license_id'=>absint($row['license_id'])];
        }
        return null;
    }

    private static function enrollment_authorized($user_id, array $row) {
        if (absint($row['approved_by'] ?? 0) > 0 || in_array((string)($row['source'] ?? ''), ['administrator','teacher_invitation','school','canvas'], true)) return true;
        global $wpdb;
        return (bool)$wpdb->get_var($wpdb->prepare(
            "SELECT id FROM {$wpdb->prefix}mb_guardian_relationships WHERE student_user_id=%d AND approval_status='approved' LIMIT 1",
            $user_id
        ));
    }

    private static function family_coverage($user_id) {
        $parent_id = absint(get_user_meta($user_id, 'mb_parent_user_id', true));
        if (!$parent_id || get_user_meta($user_id, 'mb_family_access_paused', true) === 'yes') return null;
        $status = sanitize_key((string)get_user_meta($parent_id, 'mb_family_subscription_status', true));
        if (!in_array($status, ['trialing','active','checkout_complete','test_active'], true)) return null;
        $trial_end = absint(get_user_meta($parent_id, 'mb_family_trial_ends_at', true));
        if ($status === 'trialing' && $trial_end && $trial_end < time()) return null;
        return ['label'=>'Family Premium access'];
    }

    public static function license_window_active(array $license) {
        $status = sanitize_key((string)($license['license_status'] ?? $license['status'] ?? ''));
        $now = time();
        if ($status === 'active') return empty($license['canceled_at']) || strtotime((string)$license['canceled_at'].' UTC') > $now;
        if ($status === 'trial') return !empty($license['trial_ends_at']) && strtotime((string)$license['trial_ends_at'].' UTC') >= $now;
        if ($status === 'grace') return !empty($license['grace_ends_at']) && strtotime((string)$license['grace_ends_at'].' UTC') >= $now;
        return false;
    }
}
