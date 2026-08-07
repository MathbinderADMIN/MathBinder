<?php
if (!defined('ABSPATH')) exit;

/** Defines the Canvas LTI 1.3 contract without performing network requests. */
final class MathBinder_Canvas_Protocol {
    const VERSION = '2.0';

    public static function services() {
        return [
            'oidc_login' => ['label'=>'OIDC login initiation', 'state'=>'endpoint_ready'],
            'resource_launch' => ['label'=>'LTI resource-link launch', 'state'=>'endpoint_ready'],
            'deep_linking' => ['label'=>'Deep Linking 2.0', 'state'=>'endpoint_ready'],
            'ags_line_items' => ['label'=>'Assignment and Grade Services line items', 'state'=>'endpoint_ready'],
            'ags_scores' => ['label'=>'Assignment and Grade Services scores', 'state'=>'endpoint_ready'],
            'speedgrader_evidence' => ['label'=>'SpeedGrader evidence handoff', 'state'=>'endpoint_ready'],
            'nrps_roster' => ['label'=>'Names and Role Provisioning Services', 'state'=>'endpoint_ready'],
            'course_mapping' => ['label'=>'Canvas course to MathBinder class mapping', 'state'=>'endpoint_ready'],
            'sync_diagnostics' => ['label'=>'Retries, reconciliation, and diagnostics', 'state'=>'endpoint_ready'],
        ];
    }

    public static function score_contract() {
        return [
            'mathbinder_grade_is_authoritative'=>true,
            'autograde_supported'=>true,
            'teacher_override_supported'=>true,
            'dimensions'=>['reasoning','revision','improvement','correctness'],
            'inadequate_attempts_do_not_count'=>true,
            'adequate_distinct_attempts_before_explanation'=>2,
        ];
    }

    public static function evidence_contract() {
        return [
            'original_student_work_preserved'=>true,
            'teacher_feedback_preserved'=>true,
            'revision_history_preserved'=>true,
            'speedgrader_handoff'=>'secure_link',
            'personally_identifying_data_minimized'=>true,
        ];
    }

    public static function readiness() {
        $settings = MathBinder_Canvas_Settings::get();
        $configured = MathBinder_Canvas_Settings::is_complete($settings);
        $validated = !empty($settings['validated_at']);
        $gate = !empty($settings['sandbox_enabled']);
        $adapter = (bool) apply_filters('mathbinder_canvas_adapter_ready', false);
        return [
            'protocol_version'=>self::VERSION,
            'configuration_complete'=>$configured,
            'locally_validated'=>$validated,
            'activation_gate_enabled'=>$gate,
            'adapter_installed'=>$adapter,
            'live_transport_enabled'=>$configured && $validated && $gate && $adapter,
        ];
    }
}
