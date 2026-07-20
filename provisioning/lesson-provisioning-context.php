<?php
/**
 * Immutable execution context for a single provisioning run.
 *
 * Future responsibility:
 * - Carry execution mode and run metadata through the provisioning pipeline.
 * - Keep side-effect controls explicit before any write behavior is introduced.
 */

defined('ABSPATH') || exit;

class MathBinder_Lesson_Provisioning_Context {
    /** @var bool */
    protected $dry_run;

    /** @var string */
    protected $run_id;

    /** @var int */
    protected $manifest_source_version;

    /** @var bool */
    protected $allow_writes;

    /**
     * @param mixed $dry_run
     */
    public function __construct($dry_run) {
        $this->dry_run = (bool) $dry_run;
        $this->run_id = self::generate_run_id();
        $this->manifest_source_version = 1;
        $this->allow_writes = !$this->dry_run;
    }

    /**
     * @return bool
     */
    public function is_dry_run() {
        return $this->dry_run;
    }

    /**
     * @return string
     */
    public function get_run_id() {
        return $this->run_id;
    }

    /**
     * @return int
     */
    public function get_manifest_source_version() {
        return $this->manifest_source_version;
    }

    /**
     * @return bool
     */
    public function allows_writes() {
        return $this->allow_writes;
    }

    /**
     * @return string
     */
    protected static function generate_run_id() {
        return 'mbprov_' . str_replace('.', '', uniqid('', true));
    }
}
