<?php
if (!defined('ABSPATH')) exit;

final class MathBinder_Null_Canvas_Adapter implements MathBinder_Canvas_Adapter {
    public function is_configured() { return false; }
    public function launch(array $request) { return new WP_Error('mb_canvas_unavailable', 'Canvas is not configured.'); }
    public function sync_roster($context_id) { return new WP_Error('mb_canvas_unavailable', 'Canvas is not configured.'); }
    public function create_deep_link(array $resource) { return new WP_Error('mb_canvas_unavailable', 'Canvas is not configured.'); }
    public function pass_grade(array $score) { return new WP_Error('mb_canvas_unavailable', 'Canvas is not configured.'); }
    public function create_evidence_handoff(array $evidence) { return new WP_Error('mb_canvas_unavailable', 'Canvas is not configured.'); }
}
