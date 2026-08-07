<?php
if (!defined('ABSPATH')) exit;

final class MathBinder_Capabilities {
    const VIEW_DASHBOARD = 'mb_view_student_dashboard';
    const VIEW_AUDIT = 'mb_view_audit_log';
    const MANAGE_INTEGRATIONS = 'mb_manage_integrations';
    const MANAGE_IDENTITIES = 'mb_manage_identities';
    const SWITCH_WORKSPACES = 'mb_switch_workspaces';
    const MANAGE_ORGANIZATIONS = 'mb_manage_organizations';
    const MANAGE_CLASSES = 'mb_manage_classes';
    const MANAGE_LICENSES = 'mb_manage_licenses';

    public static function register() {
        // Capability names are centralized here for use by Core, REST, and LTI.
    }

    public static function install() {
        $mathbinder_roles = [
            'mb_student' => ['label'=>'MathBinder Student', 'dashboard'=>true],
            'mb_parent' => ['label'=>'MathBinder Parent', 'dashboard'=>false],
            'mb_teacher' => ['label'=>'MathBinder Teacher', 'dashboard'=>true],
            'mb_school_admin' => ['label'=>'MathBinder School Administrator', 'dashboard'=>true],
        ];
        foreach ($mathbinder_roles as $role_name => $definition) {
            add_role($role_name, $definition['label'], ['read'=>true]);
            $role = get_role($role_name);
            if (!$role) continue;
            $role->add_cap('read');
            $role->add_cap(self::SWITCH_WORKSPACES);
            if ($definition['dashboard']) $role->add_cap(self::VIEW_DASHBOARD);
        }
        $subscriber = get_role('subscriber');
        if ($subscriber) $subscriber->add_cap(self::VIEW_DASHBOARD);

        foreach (['administrator', 'editor'] as $role_name) {
            $role = get_role($role_name);
            if (!$role) continue;
            $role->add_cap(self::VIEW_DASHBOARD);
            $role->add_cap(self::VIEW_AUDIT);
            $role->add_cap(self::SWITCH_WORKSPACES);
        }

        $administrator = get_role('administrator');
        if ($administrator) {
            $administrator->add_cap(self::MANAGE_INTEGRATIONS);
            $administrator->add_cap(self::MANAGE_IDENTITIES);
            $administrator->add_cap(self::MANAGE_ORGANIZATIONS);
            $administrator->add_cap(self::MANAGE_CLASSES);
            $administrator->add_cap(self::MANAGE_LICENSES);
        }
        $school_admin = get_role('mb_school_admin');
        if ($school_admin) {
            $school_admin->add_cap(self::MANAGE_ORGANIZATIONS);
            $school_admin->add_cap(self::MANAGE_CLASSES);
            $school_admin->add_cap(self::MANAGE_LICENSES);
        }
        $teacher = get_role('mb_teacher');
        if ($teacher) $teacher->add_cap(self::MANAGE_CLASSES);
    }

    public static function can_view_student_dashboard($user_id = 0) {
        $user = $user_id ? get_user_by('id', $user_id) : wp_get_current_user();
        return $user && $user->exists() && user_can($user, self::VIEW_DASHBOARD);
    }
}
