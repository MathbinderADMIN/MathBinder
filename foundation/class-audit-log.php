<?php
if (!defined('ABSPATH')) exit;

final class MathBinder_Audit_Log {
    public static function record($action, $object_type, $object_id = '', array $context = [], $scope_type = 'site', $scope_id = 0) {
        global $wpdb;
        return $wpdb->insert(
            $wpdb->prefix . 'mb_audit_events',
            [
                'actor_user_id' => get_current_user_id(),
                'action' => sanitize_key($action),
                'object_type' => sanitize_key($object_type),
                'object_id' => sanitize_text_field((string) $object_id),
                'scope_type' => sanitize_key($scope_type),
                'scope_id' => absint($scope_id),
                'context_json' => wp_json_encode($context),
                'created_at' => current_time('mysql', true),
            ],
            ['%d', '%s', '%s', '%s', '%s', '%d', '%s', '%s']
        );
    }
}
