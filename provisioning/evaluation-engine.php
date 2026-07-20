<?php
/**
 * Evaluation engine scaffold for provisioning action comparison.
 */

defined('ABSPATH') || exit;

class MathBinder_Evaluation_Engine {
    /**
     * Evaluate actions using read-only page existence checks where supported.
     *
     * @param MathBinder_Provisioning_Action[] $actions
     * @param MathBinder_Lesson_Provisioning_Context $context
     * @param MathBinder_WordPress_Reader|null $reader
     * @param MathBinder_WordPress_Writer|null $writer
     * @return MathBinder_Provisioning_Action[]
     */
    public static function evaluate_actions(array $actions, MathBinder_Lesson_Provisioning_Context $context, $reader, $writer) {
        $evaluated_actions = array();

        foreach ($actions as $action) {
            if (!$action instanceof MathBinder_Provisioning_Action) {
                continue;
            }

            if (!($reader instanceof MathBinder_WordPress_Reader)) {
                $evaluated_actions[] = $action;
                continue;
            }

            if (!self::is_page_existence_probe($action)) {
                $evaluated_actions[] = $action;
                continue;
            }

            $desired_slug = $action->get_desired_value();
            if (!is_string($desired_slug)) {
                $evaluated_actions[] = $action;
                continue;
            }

            $slug = trim($desired_slug);
            if ($slug === '') {
                $evaluated_actions[] = $action;
                continue;
            }

            $state = $reader->find_post_by_slug($slug, 'page');

            if ($state->get_exists()) {
                $evaluated_actions[] = new MathBinder_Provisioning_Action(
                    $action->get_run_id(),
                    $action->get_lesson_slug(),
                    $action->get_field(),
                    $action->get_policy(),
                    'skip',
                    'page already exists',
                    $action->get_value_source(),
                    $action->get_desired_value()
                );
                continue;
            }

            $evaluated_actions[] = new MathBinder_Provisioning_Action(
                $action->get_run_id(),
                $action->get_lesson_slug(),
                $action->get_field(),
                $action->get_policy(),
                'pending_apply',
                'page is missing',
                $action->get_value_source(),
                $action->get_desired_value()
            );
        }

        return $evaluated_actions;
    }

    /**
     * @param MathBinder_Provisioning_Action $action
     * @return bool
     */
    private static function is_page_existence_probe(MathBinder_Provisioning_Action $action) {
        if ($action->get_action() !== 'evaluate') {
            return false;
        }

        if ($action->get_policy() !== MathBinder_Lesson_Write_Policy::MISSING_ONLY) {
            return false;
        }

        return $action->get_field() === 'slug';
    }
}
