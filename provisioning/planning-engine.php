<?php
/**
 * Planning engine for manifest default field actions.
 */

defined('ABSPATH') || exit;

require_once __DIR__ . '/provisioning-action.php';

class MathBinder_Planning_Engine {
    /**
     * Build planned and skipped provisioning actions from a validated manifest.
     *
     * @param MathBinder_Lesson_Provisioning_Context $context
     * @param array $manifest
     * @return array{planned_actions: MathBinder_Provisioning_Action[], skipped_actions: MathBinder_Provisioning_Action[]}
     */
    public static function build_actions(MathBinder_Lesson_Provisioning_Context $context, array $manifest) {
        $run_id = $context->get_run_id();
        $lesson_slug = isset($manifest['slug']) && is_string($manifest['slug']) ? $manifest['slug'] : '';
        $defaults = isset($manifest['defaults']) && is_array($manifest['defaults']) ? $manifest['defaults'] : array();
        $write_policies = isset($manifest['write_policies']) && is_array($manifest['write_policies']) ? $manifest['write_policies'] : array();

        $planned_actions = array();
        $skipped_actions = array();

        foreach ($defaults as $field => $value) {
            $field_name = is_string($field) ? trim($field) : '';
            $policy = self::resolve_valid_policy_for_field($write_policies, $field_name);

            if ($policy !== '') {
                $planned_actions[] = new MathBinder_Provisioning_Action(
                    $run_id,
                    $lesson_slug,
                    $field_name,
                    $policy,
                    'evaluate',
                    'pending WordPress state evaluation',
                    'manifest_default',
                    $value
                );
                continue;
            }

            $skipped_actions[] = new MathBinder_Provisioning_Action(
                $run_id,
                $lesson_slug,
                $field_name,
                '',
                'skip',
                'no valid write policy',
                'manifest_default',
                $value
            );
        }

        return array(
            'planned_actions' => $planned_actions,
            'skipped_actions' => $skipped_actions,
        );
    }

    /**
     * Resolve a valid supported policy for a defaults field.
     *
     * @param array $write_policies
     * @param string $field_name
     * @return string
     */
    private static function resolve_valid_policy_for_field(array $write_policies, $field_name) {
        if ($field_name === '' || !array_key_exists($field_name, $write_policies)) {
            return '';
        }

        $policy = $write_policies[$field_name];
        if (!is_string($policy)) {
            return '';
        }

        return in_array($policy, self::supported_write_policies(), true) ? $policy : '';
    }

    /**
     * Return supported write policy values.
     *
     * @return array<int, string>
     */
    private static function supported_write_policies() {
        return array(
            MathBinder_Lesson_Write_Policy::MISSING_ONLY,
            MathBinder_Lesson_Write_Policy::SEED_ONCE,
            MathBinder_Lesson_Write_Policy::MANAGED_REPLACE,
            MathBinder_Lesson_Write_Policy::APPEND_UNIQUE,
            MathBinder_Lesson_Write_Policy::NEVER_MANAGE,
        );
    }
}
