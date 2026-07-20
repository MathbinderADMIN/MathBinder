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
     * Convert evaluated provisioning actions into normalized apply results.
     *
     * @param array $actions
     * @param MathBinder_Lesson_Provisioning_Context $context
     * @return MathBinder_Apply_Result[]
     */
    public function apply(array $actions, MathBinder_Lesson_Provisioning_Context $context) {
        $apply_results = array();

        foreach ($actions as $index => $action) {
            if (!($action instanceof MathBinder_Provisioning_Action)) {
                throw new InvalidArgumentException('Action at index ' . $index . ' must be an instance of MathBinder_Provisioning_Action.');
            }

            $apply_results[] = $this->normalize_action($action, $context);
        }

        return $apply_results;
    }

    /**
     * @param MathBinder_Provisioning_Action $action
     * @param MathBinder_Lesson_Provisioning_Context $context
     * @return MathBinder_Apply_Result
     */
    protected function normalize_action(MathBinder_Provisioning_Action $action, MathBinder_Lesson_Provisioning_Context $context) {
        $requested_action = $action->get_action();
        $dry_run = $context->is_dry_run();

        if ($requested_action === 'skip') {
            return new MathBinder_Apply_Result(
                $action->get_run_id(),
                $action->get_lesson_slug(),
                $action->get_field(),
                'skip',
                'skipped',
                $action->get_reason(),
                0,
                $dry_run,
                '',
                ''
            );
        }

        if ($requested_action === 'pending_apply' && $dry_run) {
            return new MathBinder_Apply_Result(
                $action->get_run_id(),
                $action->get_lesson_slug(),
                $action->get_field(),
                'pending_apply',
                'dry_run',
                'action would be applied in dry-run mode',
                0,
                true,
                '',
                ''
            );
        }

        if ($requested_action === 'pending_apply') {
            return new MathBinder_Apply_Result(
                $action->get_run_id(),
                $action->get_lesson_slug(),
                $action->get_field(),
                'pending_apply',
                'unsupported',
                'live apply is not implemented in this step',
                0,
                false,
                'live_apply_not_implemented',
                ''
            );
        }

        return new MathBinder_Apply_Result(
            $action->get_run_id(),
            $action->get_lesson_slug(),
            $action->get_field(),
            $requested_action,
            'unsupported',
            'unsupported action type',
            0,
            $dry_run,
            'unsupported_action',
            ''
        );
    }
}
