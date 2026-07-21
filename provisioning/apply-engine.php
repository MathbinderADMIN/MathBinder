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
            if (!$this->is_eligible_live_page_creation_action($action)) {
                return new MathBinder_Apply_Result(
                    $action->get_run_id(),
                    $action->get_lesson_slug(),
                    $action->get_field(),
                    'pending_apply',
                    'unsupported',
                    'live pending_apply action is unsupported for this action shape',
                    0,
                    false,
                    'unsupported_live_action',
                    ''
                );
            }

            $post_slug = trim((string) $action->get_desired_value());
            $post_title = $this->derive_title_from_lesson_slug($post_slug);
            $post_data = array(
                'post_title' => $post_title,
                'post_name' => $post_slug,
                'post_type' => 'mb_binder_page',
                'post_status' => 'draft',
            );

            try {
                $created_post_id = $this->writer->create_post($post_data);
            } catch (Throwable $exception) {
                return new MathBinder_Apply_Result(
                    $action->get_run_id(),
                    $action->get_lesson_slug(),
                    $action->get_field(),
                    'pending_apply',
                    'failed',
                    'page creation failed during live apply',
                    0,
                    false,
                    'page_creation_failed',
                    $exception->getMessage()
                );
            }

            return new MathBinder_Apply_Result(
                $action->get_run_id(),
                $action->get_lesson_slug(),
                $action->get_field(),
                'pending_apply',
                'applied',
                'missing draft page was created',
                $created_post_id,
                false,
                '',
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

    /**
     * @param MathBinder_Provisioning_Action $action
     * @return bool
     */
    protected function is_eligible_live_page_creation_action(MathBinder_Provisioning_Action $action) {
        if ($action->get_action() !== 'pending_apply') {
            return false;
        }

        if ($action->get_field() !== 'slug') {
            return false;
        }

        if ($action->get_policy() !== MathBinder_Lesson_Write_Policy::MISSING_ONLY) {
            return false;
        }

        if (!is_string($action->get_desired_value()) || trim($action->get_desired_value()) === '') {
            return false;
        }

        if (trim($action->get_lesson_slug()) === '') {
            return false;
        }

        return true;
    }

    /**
     * @param string $lesson_slug
     * @return string
     */
    protected function derive_title_from_lesson_slug($lesson_slug) {
        $title = str_replace(array('-', '_'), ' ', (string) $lesson_slug);
        $title = preg_replace('/\s+/', ' ', $title);
        $title = trim((string) $title);

        return ucwords($title);
    }
}
