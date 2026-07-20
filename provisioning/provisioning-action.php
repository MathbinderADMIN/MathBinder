<?php
/**
 * Immutable value object for provisioning planning diagnostics.
 */

defined('ABSPATH') || exit;

class MathBinder_Provisioning_Action {
    /** @var string */
    private $run_id;

    /** @var string */
    private $lesson_slug;

    /** @var string */
    private $field;

    /** @var string */
    private $policy;

    /** @var string */
    private $action;

    /** @var string */
    private $reason;

    /** @var string */
    private $value_source;

    /** @var mixed */
    private $desired_value;

    /**
     * @param string $run_id
     * @param string $lesson_slug
     * @param string $field
     * @param string $policy
     * @param string $action
     * @param string $reason
     * @param string $value_source
     * @param mixed $desired_value
     */
    public function __construct($run_id, $lesson_slug, $field, $policy, $action, $reason, $value_source, $desired_value = null) {
        $this->run_id = (string) $run_id;
        $this->lesson_slug = (string) $lesson_slug;
        $this->field = (string) $field;
        $this->policy = (string) $policy;
        $this->action = (string) $action;
        $this->reason = (string) $reason;
        $this->value_source = (string) $value_source;
        $this->desired_value = $desired_value;
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
    public function get_policy() {
        return $this->policy;
    }

    /**
     * @return string
     */
    public function get_action() {
        return $this->action;
    }

    /**
     * @return string
     */
    public function get_reason() {
        return $this->reason;
    }

    /**
     * @return string
     */
    public function get_value_source() {
        return $this->value_source;
    }

    /**
     * @return mixed
     */
    public function get_desired_value() {
        return $this->desired_value;
    }

    /**
     * @return array<string, mixed>
     */
    public function to_array() {
        return array(
            'run_id' => $this->run_id,
            'lesson_slug' => $this->lesson_slug,
            'field' => $this->field,
            'policy' => $this->policy,
            'action' => $this->action,
            'reason' => $this->reason,
            'value_source' => $this->value_source,
            'desired_value' => $this->desired_value,
        );
    }

    /**
     * @return string|false
     */
    public function to_json() {
        return json_encode($this->to_array());
    }
}
