<?php
/**
 * Result object for lesson provisioning diagnostics and outcomes.
 *
 * Future responsibility:
 * - Collect created, updated, skipped, conflict, and error records.
 * - Provide structured access to provisioning outcomes without side effects.
 */

defined('ABSPATH') || exit;

class MathBinder_Lesson_Provisioning_Result {
    /** @var string */
    protected $run_id = '';

    /** @var string */
    protected $run_mode = 'dry_run';

    /** @var int */
    protected $manifest_source_version = 0;

    /** @var array */
    protected $discovered = array();

    /** @var array */
    protected $created = array();

    /** @var array */
    protected $updated = array();

    /** @var array */
    protected $skipped = array();

    /** @var array */
    protected $conflicts = array();

    /** @var array */
    protected $errors = array();

    /** @var MathBinder_Provisioning_Action[] */
    protected $planned_actions = array();

    /** @var MathBinder_Provisioning_Action[] */
    protected $skipped_actions = array();

    /**
     * Store immutable run metadata from provisioning context.
     *
     * @param MathBinder_Lesson_Provisioning_Context $context
     * @return void
     */
    public function set_context(MathBinder_Lesson_Provisioning_Context $context) {
        $this->run_id = $context->get_run_id();
        $this->run_mode = $context->is_dry_run() ? 'dry_run' : 'write';
        $this->manifest_source_version = $context->get_manifest_source_version();
    }

    /**
     * @return string
     */
    public function get_run_id() {
        return $this->run_id;
    }

    /**
     * @return string
     */
    public function get_run_mode() {
        return $this->run_mode;
    }

    /**
     * @return int
     */
    public function get_manifest_source_version() {
        return $this->manifest_source_version;
    }

    /**
     * Record a manifest that was read by the provisioner.
     *
     * @param string $slug
     * @param array $manifest
     * @return void
     */
    public function add_discovered($slug, array $manifest) {
        $this->discovered[] = array(
            'slug' => (string) $slug,
            'manifest' => $manifest,
        );
    }

    /**
     * @return array
     */
    public function get_discovered() {
        return $this->discovered;
    }

    /**
     * Record a structured manifest validation error.
     *
     * @param string $catalog_key
     * @param string $field
     * @param string $reason
     * @param string $received_type
     * @return void
     */
    public function add_validation_error($catalog_key, $field, $reason, $received_type) {
        $this->errors[] = array(
            'catalog_key' => (string) $catalog_key,
            'field' => (string) $field,
            'reason' => (string) $reason,
            'received_type' => (string) $received_type,
        );
    }

    /**
     * Record a planned provisioning action (planning phase only).
     *
     * @param MathBinder_Provisioning_Action $action
     * @return void
     */
    public function add_planned_action(MathBinder_Provisioning_Action $action) {
        $this->planned_actions[] = $action;
    }

    /**
     * Record a skipped planning action.
     *
     * @param MathBinder_Provisioning_Action $action
     * @return void
     */
    public function add_skipped_action(MathBinder_Provisioning_Action $action) {
        $this->skipped_actions[] = $action;
    }

    /**
     * @return array
     */
    public function get_created() {
        return $this->created;
    }

    /**
     * @return array
     */
    public function get_updated() {
        return $this->updated;
    }

    /**
     * @return array
     */
    public function get_skipped() {
        return $this->skipped;
    }

    /**
     * @return array
     */
    public function get_conflicts() {
        return $this->conflicts;
    }

    /**
     * @return array
     */
    public function get_errors() {
        return $this->errors;
    }

    /**
     * @return MathBinder_Provisioning_Action[]
     */
    public function get_planned_actions() {
        return $this->planned_actions;
    }

    /**
     * @return MathBinder_Provisioning_Action[]
     */
    public function get_skipped_actions() {
        return $this->skipped_actions;
    }
}
