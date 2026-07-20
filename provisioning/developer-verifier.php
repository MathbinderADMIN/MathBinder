<?php
/**
 * Developer-only provisioning verifier for manual dry-run diagnostics.
 */

defined('ABSPATH') || exit;

class MathBinder_Developer_Verifier {
    /** @var MathBinder_WordPress_Reader */
    private $reader;

    /** @var MathBinder_WordPress_Writer */
    private $writer;

    /** @var MathBinder_Apply_Engine */
    private $apply_engine;

    /**
     * @param mixed $reader
     * @param mixed $writer
     * @param mixed $apply_engine
     */
    public function __construct($reader, $writer, $apply_engine) {
        if (!($reader instanceof MathBinder_WordPress_Reader)) {
            throw new InvalidArgumentException('$reader must be an instance of MathBinder_WordPress_Reader.');
        }

        if (!($writer instanceof MathBinder_WordPress_Writer)) {
            throw new InvalidArgumentException('$writer must be an instance of MathBinder_WordPress_Writer.');
        }

        if (!($apply_engine instanceof MathBinder_Apply_Engine)) {
            throw new InvalidArgumentException('$apply_engine must be an instance of MathBinder_Apply_Engine.');
        }

        $this->reader = $reader;
        $this->writer = $writer;
        $this->apply_engine = $apply_engine;
    }

    /**
     * Execute a full dry-run verification for Place Value and return scalar diagnostics.
     *
     * @return array<string, mixed>
     */
    public function verify_place_value_dry_run() {
        // Inject explicit dependencies for the static run pipeline.
        new MathBinder_Lesson_Provisioner($this->reader, $this->writer, $this->apply_engine);
        $result = MathBinder_Lesson_Provisioner::run(true);

        $validation_errors = $this->filter_validation_errors_for_lesson($result->get_errors(), 'place-value');
        $planned_actions = $this->filter_actions_for_lesson($result->get_planned_actions(), 'place-value');
        $skipped_actions = $this->filter_actions_for_lesson($result->get_skipped_actions(), 'place-value');
        $apply_results = $this->filter_apply_results_for_lesson($result->get_apply_results(), 'place-value');

        $summary = array(
            'run_id' => $result->get_run_id(),
            'run_mode' => $result->get_run_mode(),
            'manifest_source_version' => $result->get_manifest_source_version(),
            'lesson_slug' => 'place-value',
            'validation_error_count' => count($validation_errors),
            'planned_action_count' => count($planned_actions),
            'skipped_action_count' => count($skipped_actions),
            'apply_result_count' => count($apply_results),
            'apply_outcomes' => $this->count_apply_outcomes($apply_results),
        );

        $safety_assertions = array(
            'is_dry_run_mode' => ($result->get_run_mode() === 'dry_run'),
            'all_apply_results_marked_dry_run' => $this->all_apply_results_marked_dry_run($apply_results),
            'any_live_apply_outcome' => $this->has_live_apply_outcome($apply_results),
            'any_non_zero_object_id' => $this->has_non_zero_object_id($apply_results),
        );

        return array(
            'validation_errors' => $validation_errors,
            'planned_actions' => $planned_actions,
            'skipped_actions' => $skipped_actions,
            'apply_results' => $apply_results,
            'summary' => $summary,
            'safety_assertions' => $safety_assertions,
        );
    }

    /**
     * @param array<int, array<string, mixed>> $errors
     * @param string $lesson_slug
     * @return array<int, array<string, mixed>>
     */
    private function filter_validation_errors_for_lesson(array $errors, $lesson_slug) {
        $filtered = array();

        foreach ($errors as $error) {
            if (!is_array($error)) {
                continue;
            }

            if (!isset($error['catalog_key']) || $error['catalog_key'] !== $lesson_slug) {
                continue;
            }

            $filtered[] = $error;
        }

        return $filtered;
    }

    /**
     * @param MathBinder_Provisioning_Action[] $actions
     * @param string $lesson_slug
     * @return array<int, array<string, mixed>>
     */
    private function filter_actions_for_lesson(array $actions, $lesson_slug) {
        $normalized = array();

        foreach ($actions as $action) {
            if (!($action instanceof MathBinder_Provisioning_Action)) {
                continue;
            }

            if ($action->get_lesson_slug() !== $lesson_slug) {
                continue;
            }

            $normalized[] = $action->to_array();
        }

        return $normalized;
    }

    /**
     * @param MathBinder_Apply_Result[] $apply_results
     * @param string $lesson_slug
     * @return array<int, array<string, mixed>>
     */
    private function filter_apply_results_for_lesson(array $apply_results, $lesson_slug) {
        $normalized = array();

        foreach ($apply_results as $apply_result) {
            if (!($apply_result instanceof MathBinder_Apply_Result)) {
                continue;
            }

            if ($apply_result->get_lesson_slug() !== $lesson_slug) {
                continue;
            }

            $normalized[] = $apply_result->to_array();
        }

        return $normalized;
    }

    /**
     * @param array<int, array<string, mixed>> $apply_results
     * @return array<string, int>
     */
    private function count_apply_outcomes(array $apply_results) {
        $counts = array();

        foreach ($apply_results as $apply_result) {
            if (!is_array($apply_result) || !isset($apply_result['outcome']) || !is_string($apply_result['outcome'])) {
                continue;
            }

            $outcome = $apply_result['outcome'];
            if (!isset($counts[$outcome])) {
                $counts[$outcome] = 0;
            }

            $counts[$outcome]++;
        }

        return $counts;
    }

    /**
     * @param array<int, array<string, mixed>> $apply_results
     * @return bool
     */
    private function all_apply_results_marked_dry_run(array $apply_results) {
        foreach ($apply_results as $apply_result) {
            if (!is_array($apply_result) || !array_key_exists('dry_run', $apply_result)) {
                return false;
            }

            if ($apply_result['dry_run'] !== true) {
                return false;
            }
        }

        return true;
    }

    /**
     * @param array<int, array<string, mixed>> $apply_results
     * @return bool
     */
    private function has_live_apply_outcome(array $apply_results) {
        foreach ($apply_results as $apply_result) {
            if (!is_array($apply_result) || !isset($apply_result['outcome'])) {
                continue;
            }

            if ($apply_result['outcome'] === 'applied') {
                return true;
            }
        }

        return false;
    }

    /**
     * @param array<int, array<string, mixed>> $apply_results
     * @return bool
     */
    private function has_non_zero_object_id(array $apply_results) {
        foreach ($apply_results as $apply_result) {
            if (!is_array($apply_result) || !isset($apply_result['object_id'])) {
                continue;
            }

            if (is_int($apply_result['object_id']) && $apply_result['object_id'] !== 0) {
                return true;
            }
        }

        return false;
    }
}