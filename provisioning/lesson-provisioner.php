<?php
/**
 * Orchestrator for generic lesson provisioning lifecycle execution.
 *
 * Future responsibility:
 * - Coordinate catalog manifests, write policies, and operation ledger checks.
 * - Execute provisioning in dry-run or write mode and return a structured result.
 */

defined('ABSPATH') || exit;

class MathBinder_Lesson_Provisioner {
    /**
     * Run lesson provisioning orchestration.
     *
     * @param bool $dry_run
     * @return MathBinder_Lesson_Provisioning_Result
     */
    public static function run($dry_run = false) {
        $context = new MathBinder_Lesson_Provisioning_Context($dry_run);
        $result = new MathBinder_Lesson_Provisioning_Result();
        $result->set_context($context);
        $manifests = MathBinder_Lesson_Catalog::get_manifests();

        if (!is_array($manifests)) {
            return $result;
        }

        foreach ($manifests as $catalog_key => $manifest) {
            $validation = self::validate_manifest_entry($context, $catalog_key, $manifest);

            foreach ($validation['errors'] as $validation_error) {
                $result->add_validation_error(
                    $validation_error['catalog_key'],
                    $validation_error['field'],
                    $validation_error['reason'],
                    $validation_error['received_type']
                );
            }

            if (!$validation['is_valid']) {
                continue;
            }

            $result->add_discovered($catalog_key, $manifest);
            self::plan_manifest_actions($context, $result, $manifest);
        }

        return $result;
    }

    /**
     * Validate one manifest catalog entry.
     *
     * @param MathBinder_Lesson_Provisioning_Context $context
     * @param mixed $catalog_key
     * @param mixed $manifest
     * @return array{is_valid:bool,errors:array<int,array<string,string>>}
     */
    protected static function validate_manifest_entry(MathBinder_Lesson_Provisioning_Context $context, $catalog_key, $manifest) {
        $errors = array();

        if (!is_string($catalog_key) || trim($catalog_key) === '') {
            $errors[] = self::validation_error($catalog_key, 'catalog_key', 'must be a non-empty string', $catalog_key);
            return array(
                'is_valid' => false,
                'errors' => $errors,
            );
        }

        if (!is_array($manifest)) {
            $errors[] = self::validation_error($catalog_key, 'manifest', 'must be an array', $manifest);
            return array(
                'is_valid' => false,
                'errors' => $errors,
            );
        }

        $required_keys = array(
            'slug',
            'title',
            'section',
            'order',
            'version',
            'defaults',
            'write_policies',
            'operations',
        );

        foreach ($required_keys as $required_key) {
            if (!array_key_exists($required_key, $manifest)) {
                $errors[] = self::validation_error($catalog_key, $required_key, 'is required', null);
            }
        }

        if (!empty($errors)) {
            return array(
                'is_valid' => false,
                'errors' => $errors,
            );
        }

        if (!is_string($manifest['slug']) || trim($manifest['slug']) === '') {
            $errors[] = self::validation_error($catalog_key, 'slug', 'must be a non-empty string', $manifest['slug']);
        }

        if ($manifest['slug'] !== $catalog_key) {
            $errors[] = self::validation_error($catalog_key, 'slug', 'must match catalog key', $manifest['slug']);
        }

        if (!is_string($manifest['title']) || trim($manifest['title']) === '') {
            $errors[] = self::validation_error($catalog_key, 'title', 'must be a non-empty string', $manifest['title']);
        }

        if (!is_string($manifest['section']) || trim($manifest['section']) === '') {
            $errors[] = self::validation_error($catalog_key, 'section', 'must be a non-empty string', $manifest['section']);
        }

        if (!is_int($manifest['order']) || $manifest['order'] < 0) {
            $errors[] = self::validation_error($catalog_key, 'order', 'must be an integer greater than or equal to 0', $manifest['order']);
        }

        if (!is_int($manifest['version']) || $manifest['version'] < 1) {
            $errors[] = self::validation_error($catalog_key, 'version', 'must be an integer greater than or equal to 1', $manifest['version']);
        }

        if (!is_array($manifest['defaults'])) {
            $errors[] = self::validation_error($catalog_key, 'defaults', 'must be an array', $manifest['defaults']);
        }

        if (!is_array($manifest['write_policies'])) {
            $errors[] = self::validation_error($catalog_key, 'write_policies', 'must be an array', $manifest['write_policies']);
            return array(
                'is_valid' => empty($errors),
                'errors' => $errors,
            );
        }

        if (!is_array($manifest['operations'])) {
            $errors[] = self::validation_error($catalog_key, 'operations', 'must be an array', $manifest['operations']);
        }

        $write_policy_errors = self::validate_write_policies($context, $catalog_key, $manifest['write_policies']);
        foreach ($write_policy_errors as $write_policy_error) {
            $errors[] = $write_policy_error;
        }

        return array(
            'is_valid' => self::has_no_manifest_level_errors($errors),
            'errors' => $errors,
        );
    }

    /**
     * Validate write policy entries without executing any policy behavior.
     *
     * @param MathBinder_Lesson_Provisioning_Context $context
     * @param string $catalog_key
     * @param array $write_policies
     * @return array<int, array<string, string>>
     */
    protected static function validate_write_policies(MathBinder_Lesson_Provisioning_Context $context, $catalog_key, array $write_policies) {
        $errors = array();
        $supported_policies = self::supported_write_policies();
        $entry_index = 0;

        // Context is intentionally read here so dry-run/write mode is explicit
        // across the validation pipeline before apply behavior exists.
        $context->allows_writes();

        foreach ($write_policies as $field => $policy) {
            $field_label = 'write_policies.' . (is_scalar($field) ? (string) $field : (string) $entry_index);

            if (!is_string($field) || trim($field) === '') {
                $errors[] = self::validation_error($catalog_key, $field_label, 'field name must be a non-empty string', $field);
                $entry_index++;
                continue;
            }

            if (!is_string($policy) || !in_array($policy, $supported_policies, true)) {
                $errors[] = self::validation_error($catalog_key, $field_label, 'policy must be one of the supported write policy constants', $policy);
            }

            $entry_index++;
        }

        return $errors;
    }

    /**
     * Return supported write policy values.
     *
     * @return array<int, string>
     */
    protected static function supported_write_policies() {
        return array(
            MathBinder_Lesson_Write_Policy::MISSING_ONLY,
            MathBinder_Lesson_Write_Policy::SEED_ONCE,
            MathBinder_Lesson_Write_Policy::MANAGED_REPLACE,
            MathBinder_Lesson_Write_Policy::APPEND_UNIQUE,
            MathBinder_Lesson_Write_Policy::NEVER_MANAGE,
        );
    }

    /**
     * Build planning records from manifest defaults and matching valid policies.
     *
     * This stage is intentionally side-effect free.
     *
     * @param MathBinder_Lesson_Provisioning_Context $context
     * @param MathBinder_Lesson_Provisioning_Result $result
     * @param array $manifest
     * @return void
     */
    protected static function plan_manifest_actions(
        MathBinder_Lesson_Provisioning_Context $context,
        MathBinder_Lesson_Provisioning_Result $result,
        array $manifest
    ) {
        $run_id = $context->get_run_id();
        $lesson_slug = isset($manifest['slug']) && is_string($manifest['slug']) ? $manifest['slug'] : '';
        $defaults = isset($manifest['defaults']) && is_array($manifest['defaults']) ? $manifest['defaults'] : array();
        $write_policies = isset($manifest['write_policies']) && is_array($manifest['write_policies']) ? $manifest['write_policies'] : array();

        foreach ($defaults as $field => $value) {
            $field_name = is_string($field) ? trim($field) : '';
            $policy = self::resolve_valid_policy_for_field($write_policies, $field_name);

            if ($policy !== '') {
                $result->add_planned_action(array(
                    'run_id' => $run_id,
                    'lesson_slug' => $lesson_slug,
                    'field' => $field_name,
                    'policy' => $policy,
                    'action' => 'evaluate',
                    'reason' => 'pending WordPress state evaluation',
                    'value_source' => 'manifest_default',
                ));
                continue;
            }

            $result->add_skipped_action(array(
                'run_id' => $run_id,
                'lesson_slug' => $lesson_slug,
                'field' => $field_name,
                'policy' => '',
                'action' => 'skip',
                'reason' => 'no valid write policy',
                'value_source' => 'manifest_default',
            ));
        }
    }

    /**
     * Resolve a valid supported policy for a defaults field.
     *
     * @param array $write_policies
     * @param string $field_name
     * @return string
     */
    protected static function resolve_valid_policy_for_field(array $write_policies, $field_name) {
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
     * Determine whether any blocking manifest-level error exists.
     *
     * Write policy errors are intentionally non-blocking at this stage.
     *
     * @param array<int, array<string, string>> $errors
     * @return bool
     */
    protected static function has_no_manifest_level_errors(array $errors) {
        foreach ($errors as $error) {
            if (!isset($error['field'])) {
                return false;
            }

            if ($error['field'] === 'write_policies') {
                return false;
            }

            if (strpos($error['field'], 'write_policies.') === 0) {
                continue;
            }

            return false;
        }

        return true;
    }

    /**
     * Build a normalized validation error payload.
     *
     * @param mixed $catalog_key
     * @param string $field
     * @param string $reason
     * @param mixed $received
     * @return array<string, string>
     */
    protected static function validation_error($catalog_key, $field, $reason, $received) {
        return array(
            'catalog_key' => is_string($catalog_key) ? $catalog_key : '',
            'field' => $field,
            'reason' => $reason,
            'received_type' => gettype($received),
        );
    }
}
