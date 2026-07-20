<?php
/**
 * Immutable value object for normalized apply-stage outcomes.
 */

defined('ABSPATH') || exit;

class MathBinder_Apply_Result {
    /** @var string */
    private $run_id;

    /** @var string */
    private $lesson_slug;

    /** @var string */
    private $field;

    /** @var string */
    private $requested_action;

    /** @var string */
    private $outcome;

    /** @var string */
    private $reason;

    /** @var int */
    private $object_id;

    /** @var bool */
    private $dry_run;

    /** @var string */
    private $error_code;

    /** @var string */
    private $error_message;

    /**
     * @param mixed $run_id
     * @param mixed $lesson_slug
     * @param mixed $field
     * @param mixed $requested_action
     * @param mixed $outcome
     * @param mixed $reason
     * @param mixed $object_id
     * @param mixed $dry_run
     * @param mixed $error_code
     * @param mixed $error_message
     */
    public function __construct(
        $run_id,
        $lesson_slug,
        $field,
        $requested_action,
        $outcome,
        $reason,
        $object_id = 0,
        $dry_run = false,
        $error_code = '',
        $error_message = ''
    ) {
        $this->run_id = $this->require_trimmed_non_empty_string($run_id, 'run_id');
        $this->lesson_slug = $this->require_trimmed_non_empty_string($lesson_slug, 'lesson_slug');
        $this->field = $this->require_trimmed_non_empty_string($field, 'field');
        $this->requested_action = $this->require_trimmed_non_empty_string($requested_action, 'requested_action');
        $this->outcome = $this->require_allowed_outcome($outcome);
        $this->reason = $this->require_string($reason, 'reason');
        $this->object_id = $this->require_non_negative_int($object_id, 'object_id');
        $this->dry_run = $this->require_bool($dry_run, 'dry_run');
        $this->error_code = $this->require_trimmed_string($error_code, 'error_code');
        $this->error_message = $this->require_trimmed_string($error_message, 'error_message');
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
    public function get_lesson_slug() {
        return $this->lesson_slug;
    }

    /**
     * @return string
     */
    public function get_field() {
        return $this->field;
    }

    /**
     * @return string
     */
    public function get_requested_action() {
        return $this->requested_action;
    }

    /**
     * @return string
     */
    public function get_outcome() {
        return $this->outcome;
    }

    /**
     * @return string
     */
    public function get_reason() {
        return $this->reason;
    }

    /**
     * @return int
     */
    public function get_object_id() {
        return $this->object_id;
    }

    /**
     * @return bool
     */
    public function get_dry_run() {
        return $this->dry_run;
    }

    /**
     * @return string
     */
    public function get_error_code() {
        return $this->error_code;
    }

    /**
     * @return string
     */
    public function get_error_message() {
        return $this->error_message;
    }

    /**
     * Return true for successful normalized outcomes.
     *
     * @return bool
     */
    public function is_success() {
        return in_array($this->outcome, array('skipped', 'dry_run', 'applied'), true);
    }

    /**
     * @return array<string, mixed>
     */
    public function to_array() {
        return array(
            'run_id' => $this->run_id,
            'lesson_slug' => $this->lesson_slug,
            'field' => $this->field,
            'requested_action' => $this->requested_action,
            'outcome' => $this->outcome,
            'reason' => $this->reason,
            'object_id' => $this->object_id,
            'dry_run' => $this->dry_run,
            'error_code' => $this->error_code,
            'error_message' => $this->error_message,
        );
    }

    /**
     * @return string|false
     */
    public function to_json() {
        return json_encode($this->to_array());
    }

    /**
     * @param mixed $value
     * @param string $argument_name
     * @return string
     */
    private function require_trimmed_non_empty_string($value, $argument_name) {
        if (!is_string($value)) {
            throw new InvalidArgumentException($argument_name . ' must be a non-empty string.');
        }

        $value = trim($value);
        if ($value === '') {
            throw new InvalidArgumentException($argument_name . ' must be a non-empty string.');
        }

        return $value;
    }

    /**
     * @param mixed $value
     * @param string $argument_name
     * @return string
     */
    private function require_trimmed_string($value, $argument_name) {
        if (!is_string($value)) {
            throw new InvalidArgumentException($argument_name . ' must be a string.');
        }

        return trim($value);
    }

    /**
     * @param mixed $value
     * @param string $argument_name
     * @return string
     */
    private function require_allowed_outcome($value) {
        if (!is_string($value)) {
            throw new InvalidArgumentException('outcome must be one of: skipped, dry_run, applied, failed, unsupported.');
        }

        $value = trim($value);
        $allowed = array('skipped', 'dry_run', 'applied', 'failed', 'unsupported');
        if (!in_array($value, $allowed, true)) {
            throw new InvalidArgumentException('outcome must be one of: skipped, dry_run, applied, failed, unsupported.');
        }

        return $value;
    }

    /**
     * @param mixed $value
     * @param string $argument_name
     * @return string
     */
    private function require_string($value, $argument_name) {
        if (!is_string($value)) {
            throw new InvalidArgumentException($argument_name . ' must be a string.');
        }

        return $value;
    }

    /**
     * @param mixed $value
     * @param string $argument_name
     * @return int
     */
    private function require_non_negative_int($value, $argument_name) {
        if (!is_int($value) || $value < 0) {
            throw new InvalidArgumentException($argument_name . ' must be an integer greater than or equal to 0.');
        }

        return $value;
    }

    /**
     * @param mixed $value
     * @param string $argument_name
     * @return bool
     */
    private function require_bool($value, $argument_name) {
        if (!is_bool($value)) {
            throw new InvalidArgumentException($argument_name . ' must be a boolean.');
        }

        return $value;
    }
}
