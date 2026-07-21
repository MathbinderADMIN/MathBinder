<?php
/**
 * Developer-only admin diagnostics for provisioning dry-run verification.
 */

defined('ABSPATH') || exit;

class MathBinder_Developer_Diagnostics {
    const PAGE_TITLE = 'MathBinder Diagnostics';
    const MENU_TITLE = 'MathBinder Diagnostics';
    const CAPABILITY = 'manage_options';
    const PAGE_SLUG = 'mathbinder-diagnostics';
    const NONCE_ACTION = 'mathbinder_diagnostics_run_place_value_dry_run';
    const NONCE_FIELD = 'mathbinder_diagnostics_nonce';
    const LIVE_NONCE_ACTION = 'mathbinder_diagnostics_run_single_live_test';
    const LIVE_NONCE_FIELD = 'mathbinder_diagnostics_live_nonce';
    const ACTION_FIELD = 'mathbinder_diagnostics_action';
    const RUN_ACTION = 'mathbinder_run_place_value_dry_run';
    const RUN_SINGLE_LIVE_TEST_ACTION = 'mathbinder_run_single_live_test';
    const LIVE_CONFIRM_FIELD = 'mathbinder_single_live_test_confirm';
    const LIVE_TEST_LESSON_SLUG = 'sprint-10-live-create-test';

    /** @var array<string, mixed>|null */
    private $results = null;

    /** @var string */
    private $error_message = '';

    /** @var array<string, mixed>|null */
    private $live_test_results = null;

    /** @var string */
    private $live_test_error_message = '';

    public function __construct() {
        add_action('admin_menu', array($this, 'register_menu_page'));
    }

    /**
     * @return void
     */
    public function register_menu_page() {
        $hook_suffix = add_management_page(
            self::PAGE_TITLE,
            self::MENU_TITLE,
            self::CAPABILITY,
            self::PAGE_SLUG,
            array($this, 'render_page')
        );

        if (is_string($hook_suffix) && $hook_suffix !== '') {
            add_action('load-' . $hook_suffix, array($this, 'handle_submission'));
        }
    }

    /**
     * @return void
     */
    public function handle_submission() {
        if (!current_user_can(self::CAPABILITY)) {
            wp_die(esc_html__('You do not have permission to access this page.', 'mathbinder-core'));
        }

        if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
            return;
        }

        $submitted_action = isset($_POST[self::ACTION_FIELD])
            ? sanitize_text_field(wp_unslash($_POST[self::ACTION_FIELD]))
            : '';

        if ($submitted_action === self::RUN_ACTION) {
            $nonce = isset($_POST[self::NONCE_FIELD])
                ? sanitize_text_field(wp_unslash($_POST[self::NONCE_FIELD]))
                : '';

            if (!wp_verify_nonce($nonce, self::NONCE_ACTION)) {
                $this->error_message = 'Nonce verification failed.';
                return;
            }

            $this->run_place_value_dry_run();
            return;
        }

        if ($submitted_action === self::RUN_SINGLE_LIVE_TEST_ACTION) {
            $live_nonce = isset($_POST[self::LIVE_NONCE_FIELD])
                ? sanitize_text_field(wp_unslash($_POST[self::LIVE_NONCE_FIELD]))
                : '';

            if (!wp_verify_nonce($live_nonce, self::LIVE_NONCE_ACTION)) {
                $this->live_test_error_message = 'Nonce verification failed.';
                return;
            }

            $confirmed = isset($_POST[self::LIVE_CONFIRM_FIELD])
                ? sanitize_text_field(wp_unslash($_POST[self::LIVE_CONFIRM_FIELD]))
                : '';

            if ($confirmed !== '1') {
                $this->live_test_error_message = 'Confirmation checkbox required.';
                return;
            }

            $this->run_single_live_test();
            return;
        }

        if ($submitted_action !== '') {
            $this->error_message = 'Invalid diagnostics action.';
        }
    }

    /**
     * @return void
     */
    private function run_place_value_dry_run() {
        try {
            $reader = new MathBinder_WordPress_Reader();
            $writer = new MathBinder_WordPress_Writer();
            $apply_engine = new MathBinder_Apply_Engine($writer);
            $verifier = new MathBinder_Developer_Verifier($reader, $writer, $apply_engine);

            $this->results = $verifier->verify_place_value_dry_run();
        } catch (Throwable $throwable) {
            $this->error_message = 'Diagnostics run failed: ' . $throwable->getMessage();
            $this->results = null;
        }
    }

    /**
     * @return void
     */
    private function run_single_live_test() {
        try {
            $reader = new MathBinder_WordPress_Reader();
            $writer = new MathBinder_WordPress_Writer();
            $apply_engine = new MathBinder_Apply_Engine($writer);
            // Keep verifier dependencies aligned with the existing diagnostics pipeline.
            new MathBinder_Developer_Verifier($reader, $writer, $apply_engine);

            $context = new MathBinder_Lesson_Provisioning_Context(false);
            $manifest = $this->build_single_live_test_manifest();

            $planned = MathBinder_Planning_Engine::build_actions($context, $manifest);
            $planned_actions = MathBinder_Evaluation_Engine::evaluate_actions(
                $planned['planned_actions'],
                $context,
                $reader,
                $writer
            );

            $skipped_actions = MathBinder_Evaluation_Engine::evaluate_actions(
                $planned['skipped_actions'],
                $context,
                $reader,
                $writer
            );

            $ordered_actions = $this->merge_actions_for_single_manifest_slug($planned_actions, $skipped_actions);
            $apply_results = $apply_engine->apply($ordered_actions, $context);

            $planned_action = isset($ordered_actions[0]) && ($ordered_actions[0] instanceof MathBinder_Provisioning_Action)
                ? $ordered_actions[0]->to_array()
                : array();

            $apply_result = isset($apply_results[0]) && ($apply_results[0] instanceof MathBinder_Apply_Result)
                ? $apply_results[0]->to_array()
                : array();

            $this->live_test_results = array(
                'lesson_slug' => self::LIVE_TEST_LESSON_SLUG,
                'dry_run' => $context->is_dry_run(),
                'planned_action' => $planned_action,
                'apply_outcome' => isset($apply_result['outcome']) ? (string) $apply_result['outcome'] : '',
                'object_id' => isset($apply_result['object_id']) && is_int($apply_result['object_id']) ? $apply_result['object_id'] : 0,
                'reason' => isset($apply_result['reason']) ? (string) $apply_result['reason'] : '',
                'apply_result' => $apply_result,
            );

            $this->live_test_error_message = '';
        } catch (Throwable $throwable) {
            $this->live_test_error_message = 'Diagnostics run failed: ' . $throwable->getMessage();
            $this->live_test_results = null;
        }
    }

    /**
     * @return array<string, mixed>
     */
    private function build_single_live_test_manifest() {
        return array(
            'slug' => self::LIVE_TEST_LESSON_SLUG,
            'title' => 'Sprint 10 Live Create Test',
            'section' => 'the-number-system',
            'order' => 0,
            'version' => 1,
            'defaults' => array(
                'slug' => self::LIVE_TEST_LESSON_SLUG,
            ),
            'write_policies' => array(
                'slug' => MathBinder_Lesson_Write_Policy::MISSING_ONLY,
            ),
            'operations' => array(),
        );
    }

    /**
     * @param MathBinder_Provisioning_Action[] $planned_actions
     * @param MathBinder_Provisioning_Action[] $skipped_actions
     * @return MathBinder_Provisioning_Action[]
     */
    private function merge_actions_for_single_manifest_slug(array $planned_actions, array $skipped_actions) {
        $ordered = array();

        foreach ($planned_actions as $action) {
            if ($action instanceof MathBinder_Provisioning_Action && $action->get_field() === 'slug') {
                $ordered[] = $action;
            }
        }

        foreach ($skipped_actions as $action) {
            if ($action instanceof MathBinder_Provisioning_Action && $action->get_field() === 'slug') {
                $ordered[] = $action;
            }
        }

        return $ordered;
    }

    /**
     * @return void
     */
    public function render_page() {
        if (!current_user_can(self::CAPABILITY)) {
            wp_die(esc_html__('You do not have permission to access this page.', 'mathbinder-core'));
        }

        $summary = is_array($this->results) && isset($this->results['summary']) && is_array($this->results['summary'])
            ? $this->results['summary']
            : array();

        $run_id = isset($summary['run_id']) ? (string) $summary['run_id'] : '';
        $lesson_slug = isset($summary['lesson_slug']) ? (string) $summary['lesson_slug'] : '';
        $run_mode = isset($summary['run_mode']) ? (string) $summary['run_mode'] : '';

        ?>
        <div class="wrap">
            <h1><?php echo esc_html(self::PAGE_TITLE); ?></h1>
            <p><strong><?php echo esc_html('Dry-run only. This tool does not create or modify WordPress content.'); ?></strong></p>

            <form method="post" action="<?php echo esc_url(menu_page_url(self::PAGE_SLUG, false)); ?>">
                <?php wp_nonce_field(self::NONCE_ACTION, self::NONCE_FIELD); ?>
                <input type="hidden" name="<?php echo esc_attr(self::ACTION_FIELD); ?>" value="<?php echo esc_attr(self::RUN_ACTION); ?>">
                <?php submit_button('Run Place Value Dry Run'); ?>
            </form>

            <?php if ($this->error_message !== '') : ?>
                <div class="notice notice-error"><p><?php echo esc_html($this->error_message); ?></p></div>
            <?php endif; ?>

            <?php if (is_array($this->results)) : ?>
                <h2><?php echo esc_html('Latest Result'); ?></h2>
                <table class="widefat striped" style="max-width: 900px;">
                    <tbody>
                        <tr>
                            <th scope="row"><?php echo esc_html('run_id'); ?></th>
                            <td><?php echo esc_html($run_id); ?></td>
                        </tr>
                        <tr>
                            <th scope="row"><?php echo esc_html('lesson_slug'); ?></th>
                            <td><?php echo esc_html($lesson_slug); ?></td>
                        </tr>
                        <tr>
                            <th scope="row"><?php echo esc_html('dry_run'); ?></th>
                            <td><?php echo esc_html($run_mode === 'dry_run' ? 'true' : 'false'); ?></td>
                        </tr>
                    </tbody>
                </table>

                <h3><?php echo esc_html('Normalized Results (JSON)'); ?></h3>
                <pre style="max-width: 1100px; overflow: auto; background: #fff; padding: 16px; border: 1px solid #ccd0d4;"><?php echo esc_html((string) wp_json_encode($this->results, JSON_PRETTY_PRINT)); ?></pre>
            <?php endif; ?>

            <hr style="margin: 32px 0;">

            <h2><?php echo esc_html('Controlled Live-Create Test'); ?></h2>
            <p><strong><?php echo esc_html('This will create ONE draft Binder Page for testing. It will never publish content.'); ?></strong></p>

            <form method="post" action="<?php echo esc_url(menu_page_url(self::PAGE_SLUG, false)); ?>">
                <?php wp_nonce_field(self::LIVE_NONCE_ACTION, self::LIVE_NONCE_FIELD); ?>
                <input type="hidden" name="<?php echo esc_attr(self::ACTION_FIELD); ?>" value="<?php echo esc_attr(self::RUN_SINGLE_LIVE_TEST_ACTION); ?>">
                <p>
                    <label>
                        <input type="checkbox" name="<?php echo esc_attr(self::LIVE_CONFIRM_FIELD); ?>" value="1" required>
                        <?php echo esc_html('I confirm this will run one controlled live-create test for a draft Binder Page.'); ?>
                    </label>
                </p>
                <?php submit_button('Run Controlled Live-Create Test', 'secondary'); ?>
            </form>

            <?php if ($this->live_test_error_message !== '') : ?>
                <div class="notice notice-error"><p><?php echo esc_html($this->live_test_error_message); ?></p></div>
            <?php endif; ?>

            <?php if (is_array($this->live_test_results)) : ?>
                <h3><?php echo esc_html('Live Test Result'); ?></h3>
                <table class="widefat striped" style="max-width: 900px;">
                    <tbody>
                        <tr>
                            <th scope="row"><?php echo esc_html('lesson_slug'); ?></th>
                            <td><?php echo esc_html(isset($this->live_test_results['lesson_slug']) ? (string) $this->live_test_results['lesson_slug'] : ''); ?></td>
                        </tr>
                        <tr>
                            <th scope="row"><?php echo esc_html('dry_run'); ?></th>
                            <td><?php echo esc_html(!empty($this->live_test_results['dry_run']) ? 'true' : 'false'); ?></td>
                        </tr>
                        <tr>
                            <th scope="row"><?php echo esc_html('planned_action'); ?></th>
                            <td><?php echo esc_html(isset($this->live_test_results['planned_action']['action']) ? (string) $this->live_test_results['planned_action']['action'] : ''); ?></td>
                        </tr>
                        <tr>
                            <th scope="row"><?php echo esc_html('apply_outcome'); ?></th>
                            <td><?php echo esc_html(isset($this->live_test_results['apply_outcome']) ? (string) $this->live_test_results['apply_outcome'] : ''); ?></td>
                        </tr>
                        <tr>
                            <th scope="row"><?php echo esc_html('object_id'); ?></th>
                            <td><?php echo esc_html((string) (isset($this->live_test_results['object_id']) ? (int) $this->live_test_results['object_id'] : 0)); ?></td>
                        </tr>
                        <tr>
                            <th scope="row"><?php echo esc_html('reason'); ?></th>
                            <td><?php echo esc_html(isset($this->live_test_results['reason']) ? (string) $this->live_test_results['reason'] : ''); ?></td>
                        </tr>
                    </tbody>
                </table>

                <h3><?php echo esc_html('Live Test Result (JSON)'); ?></h3>
                <pre style="max-width: 1100px; overflow: auto; background: #fff; padding: 16px; border: 1px solid #ccd0d4;"><?php echo esc_html((string) wp_json_encode($this->live_test_results, JSON_PRETTY_PRINT)); ?></pre>
            <?php endif; ?>
        </div>
        <?php
    }
}