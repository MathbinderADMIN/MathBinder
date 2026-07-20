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
    const ACTION_FIELD = 'mathbinder_diagnostics_action';
    const RUN_ACTION = 'mathbinder_run_place_value_dry_run';

    /** @var array<string, mixed>|null */
    private $results = null;

    /** @var string */
    private $error_message = '';

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

        if ($submitted_action !== self::RUN_ACTION) {
            $this->error_message = 'Invalid diagnostics action.';
            return;
        }

        $nonce = isset($_POST[self::NONCE_FIELD])
            ? sanitize_text_field(wp_unslash($_POST[self::NONCE_FIELD]))
            : '';

        if (!wp_verify_nonce($nonce, self::NONCE_ACTION)) {
            $this->error_message = 'Nonce verification failed.';
            return;
        }

        $this->run_place_value_dry_run();
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
        </div>
        <?php
    }
}