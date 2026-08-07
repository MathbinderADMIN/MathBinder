<?php
if (!defined('ABSPATH')) exit;

interface MathBinder_Canvas_Adapter {
    public function is_configured();
    public function launch(array $request);
    public function sync_roster($context_id);
    public function create_deep_link(array $resource);
    public function pass_grade(array $score);
    public function create_evidence_handoff(array $evidence);
}
