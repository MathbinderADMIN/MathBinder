<?php
if (!defined('ABSPATH')) exit;

/**
 * Safe Canvas staging layer.
 *
 * MathBinder records remain authoritative. Canvas identifiers are stored only
 * as external mappings after an authenticated LTI adapter is installed.
 */
final class MathBinder_Canvas_Integration {
    const QUEUE_OPTION = 'mb_canvas_assignment_queue_v1';

    public static function register() {
        MathBinder_Canvas_Settings::register();
        MathBinder_Canvas_Diagnostics::register();
        MathBinder_Canvas_Transport::register();
    }

    public static function adapter() {
        return new MathBinder_LTI_Canvas_Adapter();
    }

    public static function status() {
        $settings = MathBinder_Canvas_Settings::get();
        $configured = MathBinder_Canvas_Settings::is_complete($settings);
        $readiness = MathBinder_Canvas_Protocol::readiness();
        $adapter_ready = !empty($readiness['live_transport_enabled']);
        return [
            'adapter_ready' => $adapter_ready,
            'label' => $adapter_ready ? 'Test connection available' : ($configured ? 'Sandbox configured' : 'Not connected'),
            'readiness' => $readiness,
            'detail' => $adapter_ready
                ? 'A Canvas adapter is available for sandbox validation. No credential values are shown here.'
                : ($configured ? 'Canvas settings are stored, but the live LTI adapter and all data transfer remain disabled.' : 'Canvas LTI 1.3 is safely disabled. An administrator must configure the test connection before anything can be sent.'),
        ];
    }

    public static function queue() {
        $queue = get_option(self::QUEUE_OPTION, []);
        return is_array($queue) ? $queue : [];
    }

    public static function for_teacher($teacher_id) {
        return array_values(array_filter(self::queue(), function($item) use ($teacher_id) {
            return (int)($item['teacher_id'] ?? 0) === (int)$teacher_id || user_can($teacher_id, 'manage_options');
        }));
    }

    public static function prepare_assignment(array $path, $teacher_id) {
        $path_id = sanitize_text_field((string)($path['id'] ?? ''));
        if ($path_id === '') return new WP_Error('mb_canvas_missing_path', 'A MathBinder mastery path is required.');

        $queue = self::queue();
        $record_id = 'path:' . $path_id;
        $existing = isset($queue[$record_id]) && is_array($queue[$record_id]) ? $queue[$record_id] : [];
        $queue[$record_id] = [
            'id' => $record_id,
            'mathbinder_type' => 'teacher_mastery_path',
            'mathbinder_id' => $path_id,
            'teacher_id' => absint($teacher_id),
            'title' => sanitize_text_field((string)($path['title'] ?? 'MathBinder Mastery Path')),
            'target_type' => sanitize_key((string)($path['target_type'] ?? '')),
            'target_id' => absint($path['target_id'] ?? 0),
            'due_date' => sanitize_text_field((string)($path['due_date'] ?? '')),
            'points_possible' => 100,
            'status' => 'ready_for_canvas',
            'external_line_item_id' => (string)($existing['external_line_item_id'] ?? ''),
            'created_at' => (string)($existing['created_at'] ?? current_time('mysql', true)),
            'updated_at' => current_time('mysql', true),
        ];
        update_option(self::QUEUE_OPTION, $queue, false);
        return $queue[$record_id];
    }
}
