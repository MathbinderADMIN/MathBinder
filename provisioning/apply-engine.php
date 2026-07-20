<?php
/**
 * Apply engine scaffold for provisioning actions.
 */

defined('ABSPATH') || exit;

class MathBinder_Apply_Engine {
    /** @var MathBinder_WordPress_Writer */
    private $writer;

    /**
     * @param mixed $writer
     */
    public function __construct($writer) {
        if (!($writer instanceof MathBinder_WordPress_Writer)) {
            throw new InvalidArgumentException('$writer must be an instance of MathBinder_WordPress_Writer.');
        }

        $this->writer = $writer;
    }

    /**
     * Validate and return provisioning actions unchanged.
     *
     * @param array $actions
     * @param MathBinder_Lesson_Provisioning_Context $context
     * @return MathBinder_Provisioning_Action[]
     */
    public function apply(array $actions, MathBinder_Lesson_Provisioning_Context $context) {
        $validated_actions = array();

        foreach ($actions as $index => $action) {
            if (!($action instanceof MathBinder_Provisioning_Action)) {
                throw new InvalidArgumentException('Action at index ' . $index . ' must be an instance of MathBinder_Provisioning_Action.');
            }

            $validated_actions[] = $action;
        }

        return $validated_actions;
    }
}
