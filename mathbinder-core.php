<?php
/**
 * Plugin Name: MathBinder Core
 * Description: Structured Binder Pages with a Quick Add builder, automatic At a Glance details, embedded videos, resource cards, common questions, downloads, and topic navigation.
 * Version: 30.51.5
 * Author: MathBinder
 * Text Domain: mathbinder-core
 */
if (!defined('ABSPATH')) exit;

require_once __DIR__ . '/provisioning/lesson-catalog.php';
require_once __DIR__ . '/provisioning/k-calculus-catalog.php';
require_once __DIR__ . '/provisioning/lesson-write-policy.php';
require_once __DIR__ . '/provisioning/lesson-provisioning-context.php';
require_once __DIR__ . '/provisioning/lesson-provisioning-result.php';
require_once __DIR__ . '/provisioning/lesson-operation-ledger.php';
require_once __DIR__ . '/provisioning/provisioning-action.php';
require_once __DIR__ . '/provisioning/wordpress-state.php';
require_once __DIR__ . '/provisioning/planning-engine.php';
require_once __DIR__ . '/provisioning/evaluation-engine.php';
require_once __DIR__ . '/provisioning/adapters/wordpress-reader.php';
require_once __DIR__ . '/provisioning/adapters/wordpress-writer.php';
require_once __DIR__ . '/provisioning/lesson-provisioner.php';
require_once __DIR__ . '/foundation/class-migrations.php';
require_once __DIR__ . '/foundation/class-capabilities.php';
require_once __DIR__ . '/foundation/class-student-access.php';
require_once __DIR__ . '/foundation/class-audit-log.php';
require_once __DIR__ . '/foundation/class-rest-controller.php';
require_once __DIR__ . '/foundation/class-student-dashboard.php';
require_once __DIR__ . '/foundation/class-teacher-dashboard.php';
require_once __DIR__ . '/foundation/class-math-notes.php';
require_once __DIR__ . '/foundation/class-external-practice.php';
require_once __DIR__ . '/foundation/class-class-staff.php';
require_once __DIR__ . '/identity/class-identity-service.php';
require_once __DIR__ . '/identity/class-verification-service.php';
require_once __DIR__ . '/identity/class-account-workspace.php';
require_once __DIR__ . '/identity/class-family-account.php';
require_once __DIR__ . '/identity/class-frontend-auth.php';
require_once __DIR__ . '/identity/class-public-registration.php';
require_once __DIR__ . '/identity/class-identity-admin.php';
require_once __DIR__ . '/organization/class-organization-migrations.php';
require_once __DIR__ . '/organization/class-organization-service.php';
require_once __DIR__ . '/organization/class-organization-admin.php';
require_once __DIR__ . '/integrations/stripe/class-stripe-settings.php';
require_once __DIR__ . '/integrations/stripe/class-family-checkout.php';
require_once __DIR__ . '/integrations/canvas/class-canvas-adapter.php';
require_once __DIR__ . '/integrations/canvas/class-null-canvas-adapter.php';
require_once __DIR__ . '/integrations/canvas/class-canvas-crypto.php';
require_once __DIR__ . '/integrations/canvas/class-canvas-repository.php';
require_once __DIR__ . '/integrations/canvas/class-lti-canvas-adapter.php';
require_once __DIR__ . '/integrations/canvas/class-canvas-settings.php';
require_once __DIR__ . '/integrations/canvas/class-canvas-protocol.php';
require_once __DIR__ . '/integrations/canvas/class-canvas-transport.php';
require_once __DIR__ . '/integrations/canvas/class-canvas-diagnostics.php';
require_once __DIR__ . '/integrations/canvas/class-canvas-deep-linking.php';
require_once __DIR__ . '/integrations/canvas/class-canvas-integration.php';
require_once __DIR__ . '/integrations/canvas/class-canvas-submission.php';
require_once __DIR__ . '/foundation/bootstrap.php';

final class MathBinder_Core {
    const CPT = 'mb_binder_page';
    const TAX = 'mb_binder_section';
    const NONCE = 'mb_binder_page_nonce';
    const QUICK_NONCE = 'mb_quick_add_nonce';
    const VERSION = '30.51.5';

    private static $runtime_instance_sequence = 0;
    private static $runtime_diag_panel_rendered_state = false;
    private $runtime_diag_data = [];
    private $runtime_diag_panel_rendered = false;
    private $runtime_instance_marker = '';
    private $constructor_instance_marker = '';

    public function __construct() {
        $this->runtime_diag_panel_rendered =& self::$runtime_diag_panel_rendered_state;
        self::$runtime_instance_sequence++;
        $this->runtime_instance_marker = 'runtime-instance-' . strval(self::$runtime_instance_sequence);
        $this->constructor_instance_marker = $this->runtime_instance_marker;
        $this->runtime_diag_data['constructor_instance_marker'] = $this->runtime_instance_marker;
        $this->runtime_diag_data['last_mathbinder_trace_point'] = 'constructor';

        add_action('init', [$this, 'register_content_types']);
        add_action('add_meta_boxes', [$this, 'add_meta_boxes']);
        add_action('save_post_' . self::CPT, [$this, 'save_meta']);
        add_action('wp_enqueue_scripts', [$this, 'enqueue_frontend_assets']);
        add_action('admin_enqueue_scripts', [$this, 'enqueue_admin_assets']);
        add_filter('template_include', [$this, 'trace_template_filter_998'], 998);
        add_filter('template_include', [$this, 'load_single_template'], 999);
        add_filter('template_include', [$this, 'trace_template_filter_1000'], 1000);
        add_filter('template_include', [$this, 'trace_template_filter_1001'], 1001);
        add_filter('template_include', [$this, 'capture_runtime_template_diagnostic'], PHP_INT_MAX);
        add_shortcode('mathbinder_topics', [$this, 'topics_shortcode']);
        add_shortcode('mathbinder_section', [$this, 'section_shortcode']);
        add_shortcode('mathbinder_home', [$this, 'homepage_shortcode']);
        add_shortcode('mathbinder_progress', [$this, 'progress_shortcode']);
        add_shortcode('mathbinder_collection', [$this, 'collection_shortcode']);
        add_shortcode('mathbinder_evidence_folder', [$this, 'evidence_folder_shortcode']);
        add_shortcode('mathbinder_parents', [$this, 'parents_shortcode']);
        add_shortcode('mathbinder_teachers', [$this, 'teachers_shortcode']);
        add_shortcode('mathbinder_about', [$this, 'about_shortcode']);
        add_shortcode('mathbinder_contact', [$this, 'contact_shortcode']);
        add_shortcode('mathbinder_getting_started', [$this, 'getting_started_shortcode']);
        add_shortcode('mathbinder_privacy', [$this, 'privacy_shortcode']);
        add_shortcode('mathbinder_terms', [$this, 'terms_shortcode']);
        add_shortcode('mathbinder_premium_access', [$this, 'premium_access_shortcode']);
        add_shortcode('mathbinder_assignment_helper', [$this, 'assignment_helper_shortcode']);
        add_shortcode('mathbinder_interactive_notebook', [$this, 'interactive_notebook_shortcode']);
        add_filter('manage_' . self::CPT . '_posts_columns', [$this, 'columns']);
        add_action('manage_' . self::CPT . '_posts_custom_column', [$this, 'column_content'], 10, 2);
        add_action('admin_menu', [$this, 'add_quick_add_page']);
        add_action('admin_post_mb_quick_add', [$this, 'handle_quick_add']);
        add_action('admin_post_mb_lesson_builder_create', [$this, 'handle_lesson_builder_create']);
        add_action('admin_post_mb_gold_certify', [$this, 'handle_gold_certify']);
        add_action('admin_post_mb_clone_lesson', [$this, 'handle_clone_lesson']);
        add_action('admin_post_mb_update_lesson_status', [$this, 'handle_update_lesson_status']);
        add_action('admin_post_mb_bulk_generate_lessons', [$this, 'handle_bulk_generate_lessons']);
        add_action('admin_post_mb_contact_submit', [$this, 'handle_contact_submit']);
        add_action('admin_post_nopriv_mb_contact_submit', [$this, 'handle_contact_submit']);
        add_action('wp_ajax_mb_assignment_feedback', [$this, 'ajax_assignment_feedback']);
        add_action('wp_ajax_nopriv_mb_assignment_feedback', [$this, 'ajax_assignment_feedback']);
        add_action('admin_notices', [$this, 'admin_notice']);
        add_action('admin_head', [$this, 'hide_lesson_builder_notices']);
        add_action('admin_init', [$this, 'maybe_upgrade']);
        add_action('admin_init', [$this, 'maybe_repair_fractions_challenge'], 20);
        add_action('admin_init', [$this, 'maybe_repair_operations_lessons'], 25);
        add_action('admin_init', [$this, 'maybe_repair_number_base_ten_lessons'], 30);
        add_action('admin_init', [$this, 'maybe_repair_fraction_lessons'], 35);
        add_action('admin_init', [$this, 'maybe_repair_measurement_data_lessons'], 38);
        add_action('admin_init', [$this, 'maybe_repair_geometry_lessons'], 39);
        add_action('admin_init', [$this, 'maybe_repair_sections_7_11_lessons'], 39);
        add_action('admin_init', [$this, 'maybe_publish_complete_curriculum'], 39);
        add_action('admin_init', [$this, 'maybe_repair_all_lesson_videos'], 40);
        add_action('wp', [$this, 'capture_pagelayer_timing_wp_priority_1'], 1);
        add_action('wp', [$this, 'capture_pagelayer_timing_wp_priority_10'], 10);
        add_action('wp', [$this, 'capture_pagelayer_timing_wp_priority_999'], 999);
        add_action('wp', [$this, 'capture_pagelayer_timing_wp_php_int_max'], PHP_INT_MAX);
        add_action('wp', [$this, 'capture_runtime_query_diagnostic'], PHP_INT_MAX);
        add_action('template_redirect', [$this, 'redirect_legacy_section_archive'], 1);
        add_action('template_redirect', [$this, 'capture_pagelayer_timing_template_redirect_priority_10'], 10);
        add_action('template_redirect', [$this, 'capture_pagelayer_timing_template_redirect_priority_999'], 999);
        add_action('template_redirect', [$this, 'capture_pagelayer_timing_template_redirect_php_int_max'], PHP_INT_MAX);
        add_action('wp_footer', [$this, 'render_runtime_diagnostic_panel_footer'], PHP_INT_MAX);
        add_action('wp_footer', [$this, 'render_official_site_footer'], 20);
        add_action('shutdown', [$this, 'render_runtime_diagnostic_panel_shutdown'], PHP_INT_MAX);
        add_filter('body_class', [$this, 'body_classes']);
        add_action('wp_ajax_mb_topic_search', [$this, 'ajax_topic_search']);
        add_action('wp_ajax_nopriv_mb_topic_search', [$this, 'ajax_topic_search']);
        register_activation_hook(__FILE__, [$this, 'activate']);
        register_deactivation_hook(__FILE__, 'flush_rewrite_rules');
    }

    public function register_content_types() {
        register_post_type(self::CPT, [
            'labels' => [
                'name' => 'Binder Pages',
                'singular_name' => 'Binder Page',
                'add_new' => 'Add New',
                'add_new_item' => 'Add New Binder Page',
                'edit_item' => 'Edit Binder Page',
                'new_item' => 'New Binder Page',
                'view_item' => 'View Binder Page',
                'search_items' => 'Search Binder Pages',
                'not_found' => 'No Binder Pages found',
                'menu_name' => 'Binder Pages'
            ],
            'public' => true,
            'show_in_rest' => false,
            'menu_icon' => 'dashicons-book-alt',
            'supports' => ['title', 'excerpt', 'thumbnail', 'revisions', 'page-attributes'],
            'has_archive' => true,
            'rewrite' => ['slug' => 'binder-pages'],
            'menu_position' => 20
        ]);

        register_taxonomy(self::TAX, self::CPT, [
            'labels' => [
                'name' => 'Binder Sections',
                'singular_name' => 'Binder Section',
                'menu_name' => 'Binder Sections'
            ],
            'public' => true,
            'show_in_rest' => false,
            'hierarchical' => true,
            'rewrite' => ['slug' => 'binder-section'],
            'show_admin_column' => true
        ]);
    }

    public function add_quick_add_page() {
        add_submenu_page(
            'edit.php?post_type=' . self::CPT,
            'Quick Add Binder Page',
            'Quick Add',
            'edit_posts',
            'mb-quick-add',
            [$this, 'render_quick_add_page']
        );
        add_submenu_page(
            'edit.php?post_type=' . self::CPT,
            'Lesson Builder',
            'Lesson Builder',
            'edit_posts',
            'mb-lesson-builder',
            [$this, 'render_lesson_builder_page']
        );
        add_submenu_page(
            'edit.php?post_type=' . self::CPT,
            'Bulk Lesson Generator',
            'Bulk Lesson Generator',
            'edit_posts',
            'mb-bulk-lesson-generator',
            [$this, 'render_bulk_lesson_generator_page']
        );
        add_submenu_page(
            'edit.php?post_type=' . self::CPT,
            'AI Assignment Tutor',
            'AI Tutor Setup',
            'manage_options',
            'mb-ai-helper',
            [$this, 'render_ai_helper_setup_page']
        );
    }

    public function render_ai_helper_setup_page() {
        if (!current_user_can('manage_options')) return;
        $configured = defined('MATHBINDER_OPENAI_API_KEY') && trim((string) MATHBINDER_OPENAI_API_KEY) !== '';
        ?>
        <div class="wrap">
            <h1>AI Assignment Tutor</h1>
            <div class="notice <?php echo $configured ? 'notice-success' : 'notice-warning'; ?> inline">
                <p><strong><?php echo $configured ? 'Secure AI connection configured.' : 'Secure AI connection not configured.'; ?></strong></p>
            </div>
            <p>The API key is never stored in a page, browser script, shortcode, or student upload. Add it as the server-side constant <code>MATHBINDER_OPENAI_API_KEY</code> in <code>wp-config.php</code>.</p>
            <p>The helper accepts one PDF, JPG, PNG, or WEBP up to 8 MB, requests hint-first feedback, and does not save the uploaded file in the WordPress Media Library.</p>
        </div>
        <?php
    }

    public function render_quick_add_page() {
        if (!current_user_can('edit_posts')) return;
        $terms = get_terms(['taxonomy' => self::TAX, 'hide_empty' => false]);
        ?>
        <div class="wrap mb-quick-add-wrap">
            <h1>Quick Add Binder Page</h1>
            <p>Create the page structure automatically. Known topics, beginning with Place Value, receive preset instructional content that you may edit.</p>
            <form method="post" action="<?php echo esc_url(admin_url('admin-post.php')); ?>" class="mb-quick-add-form">
                <input type="hidden" name="action" value="mb_quick_add">
                <?php wp_nonce_field(self::QUICK_NONCE, self::QUICK_NONCE); ?>
                <table class="form-table">
                    <tr>
                        <th><label for="mb_topic_title">Topic</label></th>
                        <td><input required class="regular-text" id="mb_topic_title" name="mb_topic_title" type="text" placeholder="Place Value"></td>
                    </tr>
                    <tr>
                        <th><label for="mb_section_id">Binder Section</label></th>
                        <td>
                            <select required id="mb_section_id" name="mb_section_id">
                                <option value="">Choose a section</option>
                                <?php foreach ($terms as $term): ?>
                                    <option value="<?php echo intval($term->term_id); ?>"><?php echo esc_html($term->name); ?></option>
                                <?php endforeach; ?>
                            </select>
                        </td>
                    </tr>
                    <tr>
                        <th><label for="mb_menu_order">Topic Order</label></th>
                        <td><input id="mb_menu_order" name="mb_menu_order" type="number" min="0" value="0"><p class="description">Used for Previous and Next topic navigation.</p></td>
                    </tr>
                </table>
                <?php submit_button('Create Binder Page'); ?>
            </form>
        </div>
        <?php
    }

    public function handle_quick_add() {
        if (!current_user_can('edit_posts')) wp_die('You do not have permission to do this.');
        check_admin_referer(self::QUICK_NONCE, self::QUICK_NONCE);

        $title = isset($_POST['mb_topic_title']) ? sanitize_text_field(wp_unslash($_POST['mb_topic_title'])) : '';
        $section_id = isset($_POST['mb_section_id']) ? absint($_POST['mb_section_id']) : 0;
        $menu_order = isset($_POST['mb_menu_order']) ? intval($_POST['mb_menu_order']) : 0;

        if (!$title || !$section_id) wp_die('Topic and Binder Section are required.');

        $post_id = wp_insert_post([
            'post_type' => self::CPT,
            'post_status' => 'draft',
            'post_title' => $title,
            'post_name' => sanitize_title($title),
            'menu_order' => $menu_order
        ], true);

        if (is_wp_error($post_id)) wp_die(esc_html($post_id->get_error_message()));

        wp_set_object_terms($post_id, [$section_id], self::TAX);
        $preset = $this->topic_preset($title);
        foreach ($preset as $key => $value) {
            update_post_meta($post_id, '_mb_' . $key, $value);
        }

        wp_safe_redirect(admin_url('post.php?post=' . intval($post_id) . '&action=edit&mb_created=1'));
        exit;
    }


    public function lesson_builder_required_fields() {
        return [
            'subtitle'=>'Short Description','essential_question'=>'Essential Question','learning_targets'=>'Learning Targets',
            'vocabulary'=>'Vocabulary','worked_examples'=>'Worked Examples','common_mistakes'=>'Common Misconceptions',
            'real_life'=>'Real-Life Math','videos'=>'Watch It Video','video_chapters'=>'Video Chapters',
            'practice_warmup'=>'Practice Warm-Up','guided_practice'=>'Guided Practice','independent_practice'=>'Independent Practice',
            'challenge_practice'=>'Challenge Practice','master_it'=>'Success Criteria','mastery_questions'=>'Mastery Questions',
            'parent_summary'=>'Parent Summary','parent_conversation'=>'Parent Conversation Starters',
            'parent_five_minute'=>'Parent Five-Minute Review','teacher_objectives'=>'Teacher Objectives',
            'teacher_pacing'=>'Teacher Pacing','teacher_misconceptions'=>'Teacher Misconceptions',
            'teacher_differentiation'=>'Teacher Differentiation','teacher_formative'=>'Formative Assessment','standards'=>'Standards'
        ];
    }

    public function lesson_builder_placeholders() {
        return [
            'subtitle'=>'Add a one-sentence student-friendly lesson description.',
            'essential_question'=>'What important mathematical idea will students answer by the end of this lesson?',
            'learning_targets'=>"I can identify...\nI can explain...\nI can apply...",
            'vocabulary'=>"Term — Student-friendly definition\nTerm — Student-friendly definition",
            'worked_examples'=>'Example title | Step 1 | Step 2 | Step 3',
            'common_mistakes'=>'Common mistake | Correction and explanation',
            'real_life'=>'Add a meaningful real-world application.',
            'videos'=>'Lesson Video | https://',
            'video_chapters'=>"0:00 | Introduction\n1:30 | Key Idea\n3:00 | Worked Example",
            'practice_warmup'=>'Optional directions || Complete question | Answer | Hint 1 | Hint 2 | Worked solution',
            'guided_practice'=>'Optional directions || Complete question | Answer | Hint 1 | Hint 2 | Worked solution',
            'independent_practice'=>'Optional directions || Complete question | Answer | Optional hint | Optional second hint | Solution',
            'challenge_practice'=>'Add one open-response challenge.',
            'master_it'=>"I can explain...\nI can solve...\nI can apply...",
            'mastery_questions'=>'Question | option A ; option B ; option C ; option D | correct letter',
            'parent_summary'=>'Explain the lesson in plain language for families.',
            'parent_conversation'=>"Ask...\nAsk...\nAsk...",
            'parent_five_minute'=>'Describe a five-minute family review routine.',
            'teacher_objectives'=>"Students will...\nStudents will...",
            'teacher_pacing'=>"Launch | 5 minutes | Guidance\nLearn It | 15 minutes | Guidance\nPractice It | 20 minutes | Guidance",
            'teacher_misconceptions'=>'Misconception | Instructional response',
            'teacher_differentiation'=>"Below Level | Support\nOn Level | Support\nAdvanced | Extension",
            'teacher_formative'=>'Formative checkpoint',
            'standards'=>'Standard code — Description'
        ];
    }


    public function hide_lesson_builder_notices() {
        $screen = function_exists('get_current_screen') ? get_current_screen() : null;
        if (!$screen || $screen->id !== self::CPT . '_page_mb-lesson-builder') return;
        echo '<style>
            body.post-type-' . esc_attr(self::CPT) . ' .wrap > .notice:not(.mb-builder-own-notice),
            body.post-type-' . esc_attr(self::CPT) . ' .wrap > .updated:not(.mb-builder-own-notice),
            body.post-type-' . esc_attr(self::CPT) . ' .wrap > .error:not(.mb-builder-own-notice) {
                display:none!important;
            }
        </style>';
    }

    public function lesson_builder_groups() {
        return [
            'Foundation' => [
                'subtitle'=>'Short Description',
                'essential_question'=>'Essential Question',
                'learning_targets'=>'Learning Targets',
                'vocabulary'=>'Vocabulary'
            ],
            'Learn It' => [
                'worked_examples'=>'Worked Examples',
                'common_mistakes'=>'Common Misconceptions',
                'real_life'=>'Real-Life Math'
            ],
            'Watch It' => [
                'videos'=>'Watch It Video',
                'video_chapters'=>'Video Chapters'
            ],
            'Practice It' => [
                'practice_warmup'=>'Practice Warm-Up',
                'guided_practice'=>'Guided Practice',
                'independent_practice'=>'Independent Practice',
                'challenge_practice'=>'Challenge Practice'
            ],
            'Binder & Mastery' => [
                'master_it'=>'Success Criteria',
                'mastery_questions'=>'Mastery Questions'
            ],
            'Family Support' => [
                'parent_summary'=>'Parent Summary',
                'parent_conversation'=>'Parent Conversation Starters',
                'parent_five_minute'=>'Parent Five-Minute Review'
            ],
            'Teacher Support' => [
                'teacher_objectives'=>'Teacher Objectives',
                'teacher_pacing'=>'Teacher Pacing',
                'teacher_misconceptions'=>'Teacher Misconceptions',
                'teacher_differentiation'=>'Teacher Differentiation',
                'teacher_formative'=>'Formative Assessment',
                'standards'=>'Standards'
            ]
        ];
    }

    public function lesson_field_is_complete($post_id, $key) {
        $value = trim((string)get_post_meta($post_id, '_mb_' . $key, true));
        if ($value === '') return false;

        // Reject the builder's actual instructional placeholders, not valid
        // lesson content that happens to begin with words such as "Explain".
        $placeholders = $this->lesson_builder_placeholders();
        if (isset($placeholders[$key]) && $value === trim((string) $placeholders[$key])) return false;
        if (preg_match('#https://\s*$#', $value)) return false;
        return true;
    }

    public function lesson_completion_data($post_id) {
        $required = $this->lesson_builder_required_fields();
        $complete = [];
        $missing = [];

        foreach ($required as $key => $label) {
            if ($this->lesson_field_is_complete($post_id, $key)) {
                $complete[$key] = $label;
            } else {
                $missing[$key] = $label;
            }
        }

        $total = count($required);
        $count = count($complete);
        return [
            'total'=>$total,
            'complete_count'=>$count,
            'percent'=>$total ? round(($count/$total)*100) : 0,
            'complete'=>$complete,
            'missing'=>$missing
        ];
    }

    public function curriculum_dashboard_data($lessons) {
        $stats = [
            'total'=>count($lessons),'published'=>0,'gold'=>0,
            'review'=>0,'development'=>0,'draft'=>0,'average_completion'=>0
        ];
        $sum = 0;

        foreach ($lessons as $lesson) {
            $status = get_post_meta($lesson->ID, '_mb_lesson_status', true);
            if (!$status) $status = $lesson->post_status === 'publish' ? 'published' : 'draft';

            if ($status === 'published') $stats['published']++;
            elseif ($status === 'gold-certified') $stats['gold']++;
            elseif ($status === 'review') $stats['review']++;
            elseif ($status === 'development') $stats['development']++;
            else $stats['draft']++;

            $sum += $this->lesson_completion_data($lesson->ID)['percent'];
        }

        if ($stats['total']) $stats['average_completion'] = round($sum/$stats['total']);
        return $stats;
    }

    public function render_lesson_builder_page() {
        if (!current_user_can('edit_posts')) return;

        $terms = get_terms(['taxonomy'=>self::TAX,'hide_empty'=>false]);
        $all_lessons = get_posts([
            'post_type'=>self::CPT,
            'post_status'=>['draft','publish','pending','private'],
            'posts_per_page'=>-1,
            'orderby'=>'modified',
            'order'=>'DESC'
        ]);
        $recent = $all_lessons;
        $required = $this->lesson_builder_required_fields();
        $groups = $this->lesson_builder_groups();
        $dashboard = $this->curriculum_dashboard_data($all_lessons);
        $status_labels = [
            'draft'=>'Draft','development'=>'In Development',
            'review'=>'Review','gold-certified'=>'Gold Certified',
            'published'=>'Published'
        ];
        ?>
        <div class="wrap mb-builder-wrap">
            <div class="mb-builder-hero mb-builder-hero-pro">
                <div class="mb-builder-hero-copy">
                    <span>PHASE 3 RELEASE CANDIDATE</span>
                    <h1>MathBinder Curriculum Production</h1>
                    <p>Build, certify, manage, and scale standards-aligned lessons from one locked instructional framework.</p>
                    <div class="mb-builder-hero-points">
                        <span>✓ Framework locked</span>
                        <span>✓ Certification active</span>
                        <span>✓ Production analytics enabled</span>
                        <span>✓ Clone-and-customize workflow</span>
                    </div>
                </div>
                <div class="mb-builder-version mb-builder-production-badge">
                    <strong>GOLD</strong>
                    <small>Production Mode</small>
                    <em>Framework v1.0 Locked</em>
                </div>
            </div>

            <?php if(isset($_GET['mb_cloned'])): ?>
                <div class="notice notice-success mb-builder-own-notice"><p><strong>Lesson cloned successfully.</strong></p></div>
            <?php endif; ?>
            <?php if(isset($_GET['mb_status_updated'])): ?>
                <div class="notice notice-success mb-builder-own-notice"><p><strong>Lesson status updated.</strong></p></div>
            <?php endif; ?>
            <?php if(isset($_GET['mb_certification'])): ?>
                <div class="notice notice-success mb-builder-own-notice"><p><strong>Gold Certification completed.</strong></p></div>
            <?php endif; ?>
            <?php if(isset($_GET['mb_workflow_error'])): ?>
                <div class="notice notice-error mb-builder-own-notice"><p><strong><?php echo esc_html(sanitize_text_field(wp_unslash($_GET['mb_workflow_error']))); ?></strong></p></div>
            <?php endif; ?>

            <section class="mb-production-dashboard">
                <div class="mb-production-heading">
                    <div><span>CURRICULUM OVERVIEW</span><h2>Production Dashboard</h2></div>
                    <strong><?php echo intval($dashboard['average_completion']); ?>% average completion</strong>
                </div>
                <div class="mb-production-stats">
                    <article><small>Total Lessons</small><strong><?php echo intval($dashboard['total']); ?></strong><span>Across MathBinder</span></article>
                    <article><small>Published</small><strong><?php echo intval($dashboard['published']); ?></strong><span>Available to learners</span></article>
                    <article><small>Gold Certified</small><strong><?php echo intval($dashboard['gold']); ?></strong><span>Ready for release</span></article>
                    <article><small>In Review</small><strong><?php echo intval($dashboard['review']); ?></strong><span>Awaiting approval</span></article>
                    <article><small>In Development</small><strong><?php echo intval($dashboard['development']); ?></strong><span>Content in progress</span></article>
                    <article><small>Draft</small><strong><?php echo intval($dashboard['draft']); ?></strong><span>Not yet developed</span></article>
                </div>
            </section>

            <div class="mb-builder-grid">
                <section class="mb-builder-panel">
                    <div class="mb-builder-panel-heading"><span>1</span><div><small>Create</small><h2>New Gold-Template Lesson</h2></div></div>
                    <form method="post" action="<?php echo esc_url(admin_url('admin-post.php')); ?>" class="mb-builder-form">
                        <input type="hidden" name="action" value="mb_lesson_builder_create"><?php wp_nonce_field('mb_lesson_builder_create','mb_builder_nonce'); ?>
                        <p><label><strong>Lesson Title</strong><br><input class="regular-text" required name="lesson_title" type="text"></label></p>
                        <div class="mb-builder-form-grid">
                            <p><label><strong>Binder Section</strong><br><select required name="section_id"><option value="">Choose a section</option><?php foreach($terms as $term): ?><option value="<?php echo intval($term->term_id); ?>"><?php echo esc_html($term->name); ?></option><?php endforeach; ?></select></label></p>
                            <p><label><strong>Difficulty</strong><br><select name="difficulty"><option value="beginner">Beginner</option><option value="intermediate">Intermediate</option><option value="advanced">Advanced</option></select></label></p>
                            <p><label><strong>Estimated Time</strong><br><input name="estimated_time" value="45–60 minutes"></label></p>
                            <p><label><strong>Prerequisites</strong><br><input name="prerequisites" value="None"></label></p>
                        </div>
                        <p><label><input type="checkbox" name="placeholders" value="1" checked> Add authoring guidance to required fields</label></p>
                        <?php submit_button('Create Lesson'); ?>
                    </form>
                </section>

                <section class="mb-builder-panel">
                    <div class="mb-builder-panel-heading"><span>2</span><div><small>Duplicate</small><h2>Clone Existing Lesson</h2></div></div>
                    <form method="post" action="<?php echo esc_url(admin_url('admin-post.php')); ?>" class="mb-builder-form">
                        <input type="hidden" name="action" value="mb_clone_lesson"><?php wp_nonce_field('mb_clone_lesson','mb_clone_nonce'); ?>
                        <p><label><strong>Source Lesson</strong><br><select required name="source_post_id"><option value="">Choose a lesson</option><?php foreach($all_lessons as $lesson): ?><option value="<?php echo intval($lesson->ID); ?>"><?php echo esc_html($lesson->post_title); ?></option><?php endforeach; ?></select></label></p>
                        <p><label><strong>New Lesson Title</strong><br><input class="regular-text" required name="new_title" type="text"></label></p>
                        <p><label><input type="checkbox" name="copy_section" value="1" checked> Keep the same Binder Section</label></p>
                        <?php submit_button('Clone as Draft','secondary'); ?>
                    </form>
                </section>
            </div>

            <section class="mb-builder-panel">
                <div class="mb-builder-panel-heading"><span>3</span><div><small>Locked Blueprint</small><h2>MathBinder Lesson Sequence</h2></div></div>
                <div class="mb-builder-sequence mb-builder-sequence-wide">
                    <div class="mb-sequence-step"><span>1</span><strong>Find It</strong></div><b>→</b>
                    <div class="mb-sequence-step"><span>2</span><strong>Learn It</strong></div><b>→</b>
                    <div class="mb-sequence-step"><span>3</span><strong>Watch It</strong></div><b>→</b>
                    <div class="mb-sequence-step"><span>4</span><strong>Practice It</strong></div><b>→</b>
                    <div class="mb-sequence-step"><span>5</span><strong>Add to Binder</strong></div><b>→</b>
                    <div class="mb-sequence-step"><span>6</span><strong>Math Journal</strong></div><b>→</b>
                    <div class="mb-sequence-step"><span>7</span><strong>Mastery Check</strong></div><b>→</b>
                    <div class="mb-sequence-step"><span>8</span><strong>Parent Help</strong></div><b>→</b>
                    <div class="mb-sequence-step"><span>9</span><strong>Teacher Notes</strong></div>
                </div>
                <div class="mb-builder-lock"><strong>The framework is frozen for curriculum production.</strong><p>Future lessons use the same architecture, interaction patterns, visual system, and quality requirements.</p></div>
            </section>

            <section class="mb-builder-panel">
                <div class="mb-builder-panel-heading"><span>4</span><div><small>Gold Standard</small><h2>Grouped Certification Framework</h2></div></div>
                <div class="mb-certification-groups">
                    <?php foreach($groups as $group_name=>$fields): ?>
                        <article>
                            <div class="mb-cert-group-heading"><span><?php echo esc_html($group_name); ?></span><strong><?php echo intval(count($fields)); ?> checks</strong></div>
                            <ul><?php foreach($fields as $label): ?><li><span>○</span><?php echo esc_html($label); ?></li><?php endforeach; ?></ul>
                        </article>
                    <?php endforeach; ?>
                </div>
            </section>

            <section class="mb-builder-panel">
                <div class="mb-builder-panel-heading"><span>5</span><div><small>Curriculum Management</small><h2>Production Status &amp; Action Reports</h2></div></div>
                <p class="description"><strong>Required workflow:</strong> Draft → In Development → Review → Gold Certified → Published. Certification must pass before a lesson can be published.</p>
                <div class="mb-builder-recent-list">
                    <?php foreach($recent as $lesson):
                        $completion=$this->lesson_completion_data($lesson->ID);
                        $lesson_status=get_post_meta($lesson->ID,'_mb_lesson_status',true);
                        if(!$lesson_status)$lesson_status=$lesson->post_status==='publish'?'published':'draft';
                        $cert=get_post_meta($lesson->ID,'_mb_gold_certification',true)?:'not-run';
                        $missing_preview=array_slice(array_values($completion['missing']),0,3);
                    ?>
                        <article class="mb-builder-lesson-row mb-production-row">
                            <div class="mb-builder-lesson-main">
                                <h3><?php echo esc_html($lesson->post_title); ?></h3>
                                <div class="mb-builder-status-line">
                                    <span class="mb-status-pill mb-status-<?php echo esc_attr($lesson_status); ?>"><?php echo esc_html($status_labels[$lesson_status]??'Draft'); ?></span>
                                    <span class="mb-cert-pill mb-cert-<?php echo esc_attr($cert); ?>"><?php echo esc_html($cert==='gold-ready'?'Gold Ready':($cert==='needs-revision'?'Needs Revision':'Certification Not Run')); ?></span>
                                </div>
                            </div>
                            <div class="mb-builder-cert-status"><strong><?php echo intval($completion['percent']); ?>%</strong><div><span style="width:<?php echo intval($completion['percent']); ?>%"></span></div><small><?php echo intval($completion['complete_count']); ?> / <?php echo intval($completion['total']); ?> complete</small></div>
                            <div class="mb-missing-report">
                                <?php if(!$completion['missing']): ?><strong>✓ All required content complete</strong>
                                <?php else: ?><strong>Needs:</strong><ul><?php foreach($missing_preview as $label): ?><li>□ <?php echo esc_html($label); ?></li><?php endforeach; ?><?php if(count($completion['missing'])>3): ?><li>+ <?php echo intval(count($completion['missing'])-3); ?> more</li><?php endif; ?></ul><?php endif; ?>
                            </div>
                            <form method="post" action="<?php echo esc_url(admin_url('admin-post.php')); ?>" class="mb-builder-status-form">
                                <input type="hidden" name="action" value="mb_update_lesson_status"><input type="hidden" name="post_id" value="<?php echo intval($lesson->ID); ?>"><?php wp_nonce_field('mb_update_lesson_status_'.$lesson->ID,'mb_status_nonce'); ?>
                                <select name="lesson_status"><?php foreach($status_labels as $k=>$v): ?><option value="<?php echo esc_attr($k); ?>" <?php selected($lesson_status,$k); ?>><?php echo esc_html($v); ?></option><?php endforeach; ?></select>
                                <button class="button">Update</button>
                            </form>
                            <div class="mb-builder-recent-actions">
                                <a class="button" href="<?php echo esc_url(get_edit_post_link($lesson->ID)); ?>">Edit</a>
                                <a class="button" target="_blank" rel="noopener" href="<?php echo esc_url(get_preview_post_link($lesson->ID)); ?>">Preview</a>
                                <form method="post" action="<?php echo esc_url(admin_url('admin-post.php')); ?>"><input type="hidden" name="action" value="mb_gold_certify"><input type="hidden" name="post_id" value="<?php echo intval($lesson->ID); ?>"><?php wp_nonce_field('mb_gold_certify_'.$lesson->ID,'mb_cert_nonce'); ?><button class="button button-primary">Certify</button></form>
                            </div>
                        </article>
                    <?php endforeach; ?>
                </div>
            </section>

            <section class="mb-phase-closeout">
                <div><span>PHASE 3 COMPLETE</span><h2>MathBinder Lesson Framework 1.0</h2><p>The lesson architecture, curriculum-production workflow, cloning system, certification standard, and management dashboard are ready for Phase 4 content production.</p></div>
                <strong>Release Candidate 1</strong>
            </section>
        </div>
        <?php
    }

    public function handle_clone_lesson() {
        if(!current_user_can('edit_posts')) wp_die('Permission denied.');
        check_admin_referer('mb_clone_lesson','mb_clone_nonce');
        $source_id=absint($_POST['source_post_id']??0);$title=sanitize_text_field(wp_unslash($_POST['new_title']??''));$copy_section=!empty($_POST['copy_section']);
        $source=get_post($source_id);if(!$source||$source->post_type!==self::CPT||!$title) wp_die('Choose a valid source lesson and enter a title.');
        $id=wp_insert_post(['post_type'=>self::CPT,'post_status'=>'draft','post_title'=>$title,'post_name'=>sanitize_title($title),'post_content'=>$source->post_content,'post_excerpt'=>$source->post_excerpt,'menu_order'=>$source->menu_order+1],true);
        if(is_wp_error($id)) wp_die(esc_html($id->get_error_message()));
        foreach(get_post_meta($source_id) as $key=>$values){if(in_array($key,['_edit_lock','_edit_last','_mb_gold_certification','_mb_gold_certification_missing','_mb_gold_certification_date'],true))continue;foreach($values as $value)add_post_meta($id,$key,maybe_unserialize($value));}
        update_post_meta($id,'_mb_template_version','1.0');update_post_meta($id,'_mb_gold_certification','not-run');update_post_meta($id,'_mb_lesson_status','draft');
        if($copy_section){$terms=wp_get_object_terms($source_id,self::TAX,['fields'=>'ids']);if(!is_wp_error($terms)&&$terms)wp_set_object_terms($id,$terms,self::TAX);}
        wp_safe_redirect(admin_url('post.php?post='.intval($id).'&action=edit&mb_cloned=1'));exit;
    }

    public function handle_update_lesson_status() {
        $id=absint($_POST['post_id']??0);if(!$id||!current_user_can('edit_post',$id))wp_die('Permission denied.');
        check_admin_referer('mb_update_lesson_status_'.$id,'mb_status_nonce');
        $allowed=['draft','development','review','gold-certified','published'];$status=sanitize_key($_POST['lesson_status']??'draft');if(!in_array($status,$allowed,true))$status='draft';
        $certification=get_post_meta($id,'_mb_gold_certification',true);
        if(in_array($status,['gold-certified','published'],true)&&$certification!=='gold-ready'){
            $message=rawurlencode('Run Gold Certification and resolve every required item before advancing this lesson.');
            wp_safe_redirect(admin_url('edit.php?post_type='.self::CPT.'&page=mb-lesson-builder&mb_workflow_error='.$message));exit;
        }
        update_post_meta($id,'_mb_lesson_status',$status);
        update_post_meta($id,'_mb_lesson_status_changed_at',current_time('mysql'));
        update_post_meta($id,'_mb_lesson_status_changed_by',get_current_user_id());
        if($status==='published')wp_update_post(['ID'=>$id,'post_status'=>'publish']);elseif(get_post_status($id)==='publish')wp_update_post(['ID'=>$id,'post_status'=>'draft']);
        if($status==='gold-certified')update_post_meta($id,'_mb_gold_certification','gold-ready');
        wp_safe_redirect(admin_url('edit.php?post_type='.self::CPT.'&page=mb-lesson-builder&mb_status_updated=1'));exit;
    }

    public function render_bulk_lesson_generator_page() {
        if(!current_user_can('edit_posts')) return;
        $terms=get_terms(['taxonomy'=>self::TAX,'hide_empty'=>false]);
        $recovery=get_transient('mb_bulk_lesson_recovery_'.get_current_user_id());
        if(!is_array($recovery))$recovery=[];
        delete_transient('mb_bulk_lesson_recovery_'.get_current_user_id());
        $created=isset($_GET['created'])?absint($_GET['created']):0;
        $skipped=isset($_GET['skipped'])?absint($_GET['skipped']):0;
        $failed=isset($_GET['failed'])?absint($_GET['failed']):0;
        ?>
        <div class="wrap mb-builder-wrap">
            <h1>Bulk Lesson Generator</h1>
            <p>Create multiple Binder Pages safely. Enter one unique lesson title per line.</p>
            <?php if(isset($_GET['mb_bulk_result'])): ?><div class="notice notice-success mb-builder-own-notice"><p><strong><?php echo esc_html($created); ?> created, <?php echo esc_html($skipped); ?> skipped, <?php echo esc_html($failed); ?> failed.</strong></p></div><?php endif; ?>
            <?php if(!empty($recovery['error'])): ?><div class="notice notice-error mb-builder-own-notice"><p><strong><?php echo esc_html($recovery['error']); ?></strong></p></div><?php endif; ?>
            <section class="mb-builder-panel">
                <form method="post" action="<?php echo esc_url(admin_url('admin-post.php')); ?>" class="mb-builder-form">
                    <input type="hidden" name="action" value="mb_bulk_generate_lessons"><?php wp_nonce_field('mb_bulk_generate_lessons','mb_bulk_nonce'); ?>
                    <p><label><strong>Lesson Titles</strong><br><textarea name="lesson_titles" rows="14" required placeholder="Adding Whole Numbers&#10;Subtracting Whole Numbers&#10;Multiplying Whole Numbers"><?php echo esc_textarea($recovery['lesson_titles']??''); ?></textarea></label></p>
                    <div class="mb-builder-form-grid">
                        <p><label><strong>Binder Section</strong><br><select required name="section_id"><option value="">Choose a section</option><?php foreach($terms as $term): ?><option value="<?php echo intval($term->term_id); ?>" <?php selected(absint($recovery['section_id']??0),$term->term_id); ?>><?php echo esc_html($term->name); ?></option><?php endforeach; ?></select></label></p>
                        <p><label><strong>WordPress Status</strong><br><select name="post_status"><?php foreach(['draft'=>'Draft','pending'=>'Pending Review','publish'=>'Published'] as $key=>$label): ?><option value="<?php echo esc_attr($key); ?>" <?php selected($recovery['post_status']??'draft',$key); ?>><?php echo esc_html($label); ?></option><?php endforeach; ?></select></label></p>
                    </div>
                    <p><label><input type="checkbox" name="placeholders" value="1" <?php checked(!isset($recovery['placeholders'])||!empty($recovery['placeholders'])); ?>> Add Gold-template authoring guidance</label></p>
                    <?php submit_button('Generate Lessons'); ?>
                </form>
            </section>
        </div><?php
    }

    public function handle_bulk_generate_lessons() {
        if(!current_user_can('edit_posts'))wp_die('Permission denied.');
        check_admin_referer('mb_bulk_generate_lessons','mb_bulk_nonce');
        $raw=wp_unslash($_POST['lesson_titles']??'');
        $section=absint($_POST['section_id']??0);
        $post_status=sanitize_key($_POST['post_status']??'draft');
        $allowed=['draft','pending','publish'];
        $placeholders=!empty($_POST['placeholders']);
        $recovery=['lesson_titles'=>$raw,'section_id'=>$section,'post_status'=>$post_status,'placeholders'=>$placeholders];
        $term=$section?get_term($section,self::TAX):null;
        if(!$raw||!$term||is_wp_error($term)||!in_array($post_status,$allowed,true)){
            $recovery['error']='Enter at least one title, choose a valid Binder Section, and choose a valid status.';
            set_transient('mb_bulk_lesson_recovery_'.get_current_user_id(),$recovery,10*MINUTE_IN_SECONDS);
            wp_safe_redirect(admin_url('edit.php?post_type='.self::CPT.'&page=mb-bulk-lesson-generator'));exit;
        }
        $lines=preg_split('/\r\n|\r|\n/',(string)$raw);
        $titles=[];$seen=[];
        foreach($lines as $line){$title=sanitize_text_field(trim($line));if($title==='')continue;$key=function_exists('mb_strtolower')?mb_strtolower($title,'UTF-8'):strtolower($title);if(isset($seen[$key]))continue;$seen[$key]=true;$titles[]=$title;}
        $existing=get_posts(['post_type'=>self::CPT,'post_status'=>'any','posts_per_page'=>-1,'fields'=>'ids']);
        $existing_titles=[];foreach($existing as $existing_id){$existing_title=get_the_title($existing_id);$key=function_exists('mb_strtolower')?mb_strtolower($existing_title,'UTF-8'):strtolower($existing_title);$existing_titles[$key]=true;}
        $created=0;$skipped=0;$failed=0;
        foreach($titles as $title){
            $key=function_exists('mb_strtolower')?mb_strtolower($title,'UTF-8'):strtolower($title);
            if(isset($existing_titles[$key])){$skipped++;continue;}
            $id=wp_insert_post(['post_type'=>self::CPT,'post_status'=>$post_status,'post_title'=>$title,'post_name'=>sanitize_title($title)],true);
            if(is_wp_error($id)){$failed++;continue;}
            $assigned=wp_set_object_terms($id,[$section],self::TAX);
            if(is_wp_error($assigned)){wp_delete_post($id,true);$failed++;continue;}
            update_post_meta($id,'_mb_template_version','1.0');
            update_post_meta($id,'_mb_gold_certification','not-run');
            update_post_meta($id,'_mb_lesson_status',$post_status==='publish'?'published':($post_status==='pending'?'review':'draft'));
            update_post_meta($id,'_mb_completion_status','not_started');
            if($placeholders)foreach($this->lesson_builder_placeholders() as $meta_key=>$value)update_post_meta($id,'_mb_'.$meta_key,$value);
            $existing_titles[$key]=true;$created++;
        }
        wp_safe_redirect(admin_url('edit.php?post_type='.self::CPT.'&page=mb-bulk-lesson-generator&mb_bulk_result=1&created='.$created.'&skipped='.$skipped.'&failed='.$failed));exit;
    }

    public function handle_lesson_builder_create() {
        if(!current_user_can('edit_posts')) wp_die('Permission denied.');
        check_admin_referer('mb_lesson_builder_create','mb_builder_nonce');
        $title=sanitize_text_field(wp_unslash($_POST['lesson_title']??''));$section=absint($_POST['section_id']??0);
        if(!$title||!$section) wp_die('Lesson title and section are required.');
        $id=wp_insert_post(['post_type'=>self::CPT,'post_status'=>'draft','post_title'=>$title,'post_name'=>sanitize_title($title)],true);
        if(is_wp_error($id)) wp_die(esc_html($id->get_error_message()));
        wp_set_object_terms($id,[$section],self::TAX);
        update_post_meta($id,'_mb_difficulty',sanitize_text_field(wp_unslash($_POST['difficulty']??'beginner')));
        update_post_meta($id,'_mb_estimated_time',sanitize_text_field(wp_unslash($_POST['estimated_time']??'45–60 minutes')));
        update_post_meta($id,'_mb_prerequisites',sanitize_text_field(wp_unslash($_POST['prerequisites']??'None')));update_post_meta($id,'_mb_template_version','1.0');update_post_meta($id,'_mb_gold_certification','not-run');update_post_meta($id,'_mb_lesson_status','draft');
        if(!empty($_POST['placeholders'])) foreach($this->lesson_builder_placeholders() as $key=>$value) update_post_meta($id,'_mb_'.$key,$value);
        wp_safe_redirect(admin_url('post.php?post='.intval($id).'&action=edit&mb_created=1'));exit;
    }

    public function handle_gold_certify() {
        $id=absint($_POST['post_id']??0);
        if(!$id||!current_user_can('edit_post',$id))wp_die('Permission denied.');
        check_admin_referer('mb_gold_certify_'.$id,'mb_cert_nonce');

        $completion=$this->lesson_completion_data($id);
        $missing=$completion['missing'];
        $status=$missing?'needs-revision':'gold-ready';

        update_post_meta($id,'_mb_gold_certification',$status);
        update_post_meta($id,'_mb_gold_certification_missing',$missing);
        update_post_meta($id,'_mb_gold_certification_date',current_time('mysql'));
        update_post_meta($id,'_mb_gold_certification_percent',$completion['percent']);

        if(!$missing) update_post_meta($id,'_mb_lesson_status','gold-certified');

        wp_safe_redirect(admin_url('edit.php?post_type='.self::CPT.'&page=mb-lesson-builder&mb_certification='.$status));
        exit;
    }

    public function add_meta_boxes() {
        add_meta_box('mb_overview', '1. Overview and At a Glance', [$this, 'render_overview_box'], self::CPT, 'normal', 'high');
        add_meta_box('mb_teach', '2. Teach It', [$this, 'render_teach_box'], self::CPT, 'normal', 'high');
        add_meta_box('mb_resources', '3. Watch and Practice', [$this, 'render_resources_box'], self::CPT, 'normal', 'default');
        add_meta_box('mb_downloads', '4. Binder Page Downloads', [$this, 'render_downloads_box'], self::CPT, 'normal', 'default');
        add_meta_box('mb_support', '5. Parent Help, Mastery Check, and Related Topics', [$this, 'render_support_box'], self::CPT, 'normal', 'default');
        add_meta_box('mb_teacher', '6. Teacher Notes and Instructional Support', [$this, 'render_teacher_box'], self::CPT, 'normal', 'default');
        add_meta_box('mb_checklist', 'Publishing Checklist', [$this, 'render_checklist_box'], self::CPT, 'side', 'high');
        add_meta_box('mb_gold_certification', 'Gold Certification', [$this, 'render_gold_certification_box'], self::CPT, 'side', 'high');
    }

    private function field($key, $default = '') {
        global $post;
        $value = get_post_meta($post->ID, '_mb_' . $key, true);
        return $value !== '' ? $value : $default;
    }

    private function textarea($name, $label, $value, $help = '', $rows = 5) {
        echo '<div class="mb-admin-field"><label for="mb_' . esc_attr($name) . '"><strong>' . esc_html($label) . '</strong></label>';
        echo '<textarea id="mb_' . esc_attr($name) . '" name="mb_' . esc_attr($name) . '" rows="' . intval($rows) . '">' . esc_textarea($value) . '</textarea>';
        if ($help) echo '<p class="description">' . esc_html($help) . '</p>';
        echo '</div>';
    }

    private function input($name, $label, $value, $type = 'text', $help = '') {
        echo '<div class="mb-admin-field"><label for="mb_' . esc_attr($name) . '"><strong>' . esc_html($label) . '</strong></label>';
        echo '<input id="mb_' . esc_attr($name) . '" type="' . esc_attr($type) . '" name="mb_' . esc_attr($name) . '" value="' . esc_attr($value) . '">';
        if ($help) echo '<p class="description">' . esc_html($help) . '</p>';
        echo '</div>';
    }

    private function select($name, $label, $value, $options, $help = '') {
        echo '<div class="mb-admin-field"><label for="mb_' . esc_attr($name) . '"><strong>' . esc_html($label) . '</strong></label>';
        echo '<select id="mb_' . esc_attr($name) . '" name="mb_' . esc_attr($name) . '">';
        foreach ($options as $key => $label_text) {
            echo '<option value="' . esc_attr($key) . '" ' . selected($value, $key, false) . '>' . esc_html($label_text) . '</option>';
        }
        echo '</select>';
        if ($help) echo '<p class="description">' . esc_html($help) . '</p>';
        echo '</div>';
    }

    private function media_input($name, $label, $value) {
        echo '<div class="mb-admin-field mb-media-field"><label for="mb_' . esc_attr($name) . '"><strong>' . esc_html($label) . '</strong></label><div class="mb-media-row">';
        echo '<input id="mb_' . esc_attr($name) . '" type="url" name="mb_' . esc_attr($name) . '" value="' . esc_attr($value) . '" placeholder="https://">';
        echo '<button type="button" class="button mb-media-button" data-target="mb_' . esc_attr($name) . '">Choose from Media Library</button></div></div>';
    }

    public function render_overview_box() {
        wp_nonce_field(self::NONCE, self::NONCE);
        echo '<p class="mb-admin-intro">The public page layout is generated automatically. Quick Add supplies presets for recognized topics.</p>';
        $this->input('subtitle', 'Short Description', $this->field('subtitle'), 'text', 'One sentence shown below the topic title.');
        $this->input('essential_question', 'Essential Question', $this->field('essential_question'));
        echo '<div class="mb-admin-grid mb-admin-grid-3">';
        $this->select('difficulty', 'Difficulty', $this->field('difficulty', 'beginner'), [
            'beginner' => 'Beginner',
            'intermediate' => 'Intermediate',
            'advanced' => 'Advanced'
        ]);
        $this->input('estimated_time', 'Estimated Time', $this->field('estimated_time', '15–20 minutes'));
        $this->input('prerequisites', 'Prerequisites', $this->field('prerequisites', 'None'));
        echo '</div>';
    }

    public function render_teach_box() {
        $this->textarea('introduction', 'Teach It', $this->field('introduction'), 'Clear, student-friendly instruction. Basic formatting is allowed.', 7);
        echo '<div class="mb-admin-grid">';
        $this->textarea('learning_targets', 'Learning Targets', $this->field('learning_targets'), 'One target per line.', 6);
        $this->textarea('vocabulary', 'Vocabulary', $this->field('vocabulary'), 'One term and definition per line. Example: Digit — any numeral from 0 to 9', 6);
        echo '</div>';
        $this->textarea('worked_examples', 'Worked Examples', $this->field('worked_examples'), 'One example per line. Simple HTML is allowed.', 7);
        echo '<div class="mb-admin-grid">';
        $this->textarea('common_questions', 'Common Questions', $this->field('common_questions'), 'One question and answer per line using Question | Answer. Presets are supplied when available.', 7);
        $this->textarea('common_mistakes', 'Common Mistakes', $this->field('common_mistakes'), 'One mistake per line.', 6);
        echo '</div>';
        $this->textarea('real_life', 'Real-Life Math', $this->field('real_life'), 'Short real-world connection.', 5);
    }

    public function render_resources_box() {
        echo '<p class="mb-admin-intro">Enter one resource per line as <code>Title | URL</code>. YouTube links are embedded automatically. Put Mr. J resources first.</p>';
        $this->textarea('videos', 'Videos', $this->field('videos'), 'Example: Place Value with Mr. J | https://youtube.com/...', 6);
        echo '<div class="mb-admin-grid">';
        $this->textarea('ixl', 'IXL Resources', $this->field('ixl'), 'Title | URL', 6);
        $this->textarea('khan', 'Khan Academy Resources', $this->field('khan'), 'Title | URL', 6);
        $this->textarea('delta', 'DeltaMath Resources', $this->field('delta'), 'Title | URL. A student-login notice is shown automatically.', 6);
        $this->textarea('desmos', 'Desmos Resources', $this->field('desmos'), 'Optional. Title | URL', 6);
        $this->textarea('other_resources', 'Other Resources', $this->field('other_resources'), 'Title | URL', 6);
        echo '</div>';
    }

    public function render_downloads_box() {
        echo '<p class="mb-admin-intro">Upload a file to WordPress or paste a shared-document URL.</p>';
        $this->media_input('printable_pdf', 'Printable PDF', $this->field('printable_pdf'));
        $this->media_input('interactive_version', 'Interactive / Fillable Version', $this->field('interactive_version'));
        $this->media_input('answer_key', 'Answer Key', $this->field('answer_key'));
    }

    public function render_support_box() {
        $this->textarea('parent_summary', 'Parent Lesson Summary', $this->field('parent_summary'), 'Explain the lesson in plain language for families.', 6);
        $this->textarea('parent_conversation', 'Parent Conversation Starters', $this->field('parent_conversation'), 'One prompt per line.', 6);
        $this->textarea('parent_mistakes', 'Common Mistakes for Families to Watch For', $this->field('parent_mistakes'), 'One mistake and correction per line using Mistake | Better guidance.', 7);
        $this->textarea('parent_five_minute', 'Five-Minute Review', $this->field('parent_five_minute'), 'A short review routine families can complete together.', 6);
        $this->textarea('parent_activity', 'At-Home Activity', $this->field('parent_activity'), 'A practical activity using common household materials.', 6);
        $this->textarea('parent_help', 'Additional Parent Help', $this->field('parent_help'), 'One tip per line.', 6);
        $this->textarea('master_it', 'Master It / Success Criteria', $this->field('master_it'), 'One “I can” statement per line.', 6);
        $this->textarea('mastery_questions', 'Interactive Mastery Questions', $this->field('mastery_questions'), 'Reveal answer: Question | Answer. Multiple choice: Question | option A ; option B ; option C ; option D | correct letter.', 9);
        $this->textarea('related_topics', 'Related Binder Pages', $this->field('related_topics'), 'One exact Binder Page title per line. Existing pages become links automatically.', 5);
    }

    public function render_teacher_box() {
        echo '<p class="mb-admin-intro">Build a complete instructional support guide for this Binder Page.</p>';
        $this->textarea('teacher_objectives', 'Teacher Objectives', $this->field('teacher_objectives'), 'One measurable objective per line.', 6);
        $this->textarea('teacher_pacing', 'Suggested Pacing', $this->field('teacher_pacing'), 'Use Stage | Minutes | Guidance, one stage per line.', 7);
        $this->textarea('teacher_materials', 'Materials and Manipulatives', $this->field('teacher_materials'), 'One item per line.', 6);
        $this->textarea('teacher_misconceptions', 'Misconceptions and Interventions', $this->field('teacher_misconceptions'), 'Use Misconception | Intervention, one pair per line.', 8);
        $this->textarea('teacher_differentiation', 'Differentiation', $this->field('teacher_differentiation'), 'Use Level | Support, one pair per line.', 8);
        $this->textarea('teacher_small_group', 'Small-Group Instruction', $this->field('teacher_small_group'), 'One strategy per line.', 7);
        $this->textarea('teacher_formative', 'Formative Assessment', $this->field('teacher_formative'), 'One checkpoint per line.', 7);
        $this->textarea('teacher_connections', 'Cross-Curricular Connections', $this->field('teacher_connections'), 'Use Subject | Connection, one pair per line.', 6);
        $this->textarea('teacher_extensions', 'Extensions and Enrichment', $this->field('teacher_extensions'), 'One idea per line.', 6);
        $this->textarea('teacher_notes', 'Additional Teacher Notes', $this->field('teacher_notes'), 'Teaching moves, reminders, or implementation notes.', 7);
        $this->textarea('standards', 'Standards / Alignment', $this->field('standards'), 'One standard per line.', 6);
    }


    public function render_gold_certification_box() {
        global $post;$status=get_post_meta($post->ID,'_mb_gold_certification',true)?:'not-run';$missing=get_post_meta($post->ID,'_mb_gold_certification_missing',true);if(!is_array($missing))$missing=[];
        $labels=['not-run'=>'Not Yet Run','needs-revision'=>'Needs Revision','gold-ready'=>'Gold Ready'];
        echo '<div class="mb-gold-cert-box"><strong>'.esc_html($labels[$status]??'Not Yet Run').'</strong><p>Template Version: '.esc_html(get_post_meta($post->ID,'_mb_template_version',true)?:'Legacy').'</p>';
        if($missing)echo '<p>'.esc_html(count($missing)).' required items need attention.</p>';
        echo '<p><a class="button button-primary" href="'.esc_url(admin_url('edit.php?post_type='.self::CPT.'&page=mb-lesson-builder')).'">Open Lesson Builder</a></p></div>';
    }

    public function render_checklist_box() {
        $checks = [
            'Short description' => $this->field('subtitle'),
            'Essential question' => $this->field('essential_question'),
            'Teach It' => $this->field('introduction'),
            'Learning targets' => $this->field('learning_targets'),
            'Vocabulary' => $this->field('vocabulary'),
            'Common questions' => $this->field('common_questions'),
            'Video' => $this->field('videos'),
            'IXL' => $this->field('ixl'),
            'Khan Academy' => $this->field('khan'),
            'DeltaMath' => $this->field('delta'),
            'Parent summary' => $this->field('parent_summary'),
            'Parent conversation starters' => $this->field('parent_conversation'),
            'Parent five-minute review' => $this->field('parent_five_minute'),
            'Parent activity' => $this->field('parent_activity'),
            'Mastery criteria' => $this->field('master_it'),
            'Mastery questions' => $this->field('mastery_questions'),
            'Teacher notes' => $this->field('teacher_notes')
        ];
        echo '<ul class="mb-checklist">';
        foreach ($checks as $label => $value) {
            echo '<li class="' . ($value ? 'is-complete' : 'is-missing') . '">' . ($value ? '✓' : '○') . ' ' . esc_html($label) . '</li>';
        }
        echo '</ul><p class="description">Save the draft to refresh this checklist.</p>';
    }

    public function save_meta($post_id) {
        if (!isset($_POST[self::NONCE]) || !wp_verify_nonce(sanitize_text_field(wp_unslash($_POST[self::NONCE])), self::NONCE)) return;
        if (defined('DOING_AUTOSAVE') && DOING_AUTOSAVE) return;
        if (!current_user_can('edit_post', $post_id)) return;

        $textarea = ['introduction', 'learning_targets', 'vocabulary', 'worked_examples', 'common_questions', 'common_mistakes', 'real_life', 'videos', 'ixl', 'khan', 'delta', 'desmos', 'other_resources', 'parent_summary', 'parent_conversation', 'parent_mistakes', 'parent_five_minute', 'parent_activity', 'parent_help', 'master_it', 'mastery_questions', 'teacher_objectives', 'teacher_pacing', 'teacher_materials', 'teacher_misconceptions', 'teacher_differentiation', 'teacher_small_group', 'teacher_formative', 'teacher_connections', 'teacher_extensions', 'teacher_notes', 'standards', 'learn_checks', 'did_you_know', 'video_chapters', 'watch_vocabulary', 'pause_prompts', 'video_transcript', 'practice_warmup', 'guided_practice', 'independent_practice', 'challenge_practice', 'real_world_practice', 'related_topics'];
        $text = ['subtitle', 'essential_question', 'difficulty', 'estimated_time', 'prerequisites'];
        $urls = ['printable_pdf', 'interactive_version', 'answer_key'];

        foreach ($textarea as $key) {
            if (isset($_POST['mb_' . $key])) update_post_meta($post_id, '_mb_' . $key, wp_kses_post(wp_unslash($_POST['mb_' . $key])));
        }
        foreach ($text as $key) {
            if (isset($_POST['mb_' . $key])) update_post_meta($post_id, '_mb_' . $key, sanitize_text_field(wp_unslash($_POST['mb_' . $key])));
        }
        foreach ($urls as $key) {
            if (isset($_POST['mb_' . $key])) update_post_meta($post_id, '_mb_' . $key, esc_url_raw(wp_unslash($_POST['mb_' . $key])));
        }
    }

    public function enqueue_frontend_assets() {
        if (is_singular(self::CPT) || is_post_type_archive(self::CPT) || is_tax(self::TAX) || is_page()) {
            wp_enqueue_style('mathbinder-core', plugin_dir_url(__FILE__) . 'mathbinder.css', [], self::VERSION);
            wp_enqueue_script('mathbinder-front', plugin_dir_url(__FILE__) . 'mathbinder-front.js', [], self::VERSION, true);
            wp_localize_script('mathbinder-front', 'MathBinderSearch', [
                'ajaxUrl' => admin_url('admin-ajax.php'),
                'nonce' => wp_create_nonce('mb_topic_search_nonce'),
                'assignmentNonce' => wp_create_nonce('mb_assignment_feedback_nonce'),
                'activityUrl' => rest_url('mathbinder/v1/student/activity'),
                'serverActivity' => is_user_logged_in() ? get_user_meta(get_current_user_id(), 'mb_student_activity_v1', true) : null,
                'restNonce' => wp_create_nonce('wp_rest')
            ]);
        }
        wp_localize_script('mathbinder-front', 'mathbinderFooterData', [
            'logo' => plugin_dir_url(__FILE__) . 'Assests/Logos/mathbinder-logo.svg',
            'home' => home_url('/'),
            'binderTopics' => home_url('/binder-topics/'),
            'parents' => $this->public_page_url('parents'),
            'teachers' => $this->public_page_url('teachers'),
            'about' => $this->public_page_url('about'),
            'contact' => $this->public_page_url('contact'),
            'myBinder' => home_url('/my-mathbinder/'),
            'yourBinder' => home_url('/your-binder/'),
            'year' => date('Y')
        ]);

    }

    /**
     * Render the MathBinder footer in the page HTML.
     *
     * The front-end script still normalizes older theme footers, but the
     * official footer must not depend on JavaScript in order to appear.
     */
    public function render_official_site_footer() {
        if (is_admin() || wp_doing_ajax()) return;
        ?>
        <footer id="mb-official-site-footer" class="mb-official-site-footer" aria-label="MathBinder site footer">
            <div class="mb-official-footer-main">
                <div class="mb-official-footer-brand">
                    <a href="<?php echo esc_url(home_url('/')); ?>">
                        <img src="<?php echo esc_url(plugin_dir_url(__FILE__) . 'Assests/Logos/mathbinder-logo.svg'); ?>" alt="MathBinder">
                    </a>
                    <p>Digital Student Binder</p>
                    <span>Find It. Learn It. Master It.</span>
                </div>
                <div class="mb-official-footer-links">
                    <h2>Explore</h2>
                    <a href="<?php echo esc_url(home_url('/binder-topics/')); ?>">Binder Topics</a>
                    <a href="<?php echo esc_url($this->public_page_url('parents')); ?>">Parents</a>
                    <a href="<?php echo esc_url($this->public_page_url('teachers')); ?>">Teachers</a>
                    <a href="<?php echo esc_url($this->public_page_url('about')); ?>">About</a>
                    <a href="<?php echo esc_url($this->public_page_url('contact')); ?>">Contact</a>
                </div>
                <div class="mb-official-footer-purpose">
                    <h2>Our Purpose</h2>
                    <p>Make trustworthy math help easier to find, easier to understand, and easier to use.</p>
                    <nav class="mb-official-footer-legal" aria-label="Help and legal links">
                        <a href="<?php echo esc_url($this->public_page_url('getting_started')); ?>">Getting Started</a>
                        <a href="<?php echo esc_url($this->public_page_url('premium_access')); ?>">Premium Access</a>
                        <a href="<?php echo esc_url($this->public_page_url('privacy')); ?>">Privacy</a>
                        <a href="<?php echo esc_url($this->public_page_url('terms')); ?>">Terms</a>
                    </nav>
                </div>
            </div>
            <div class="mb-official-footer-bottom">
                <span>&copy; <?php echo esc_html(wp_date('Y')); ?> MathBinder</span>
                <span>Digital Student Binder</span>
            </div>
        </footer>
        <?php
    }

    public function enqueue_admin_assets($hook) {
        $screen = get_current_screen();
        if (!$screen) return;
        if ($screen->post_type === self::CPT || (isset($_GET['page']) && $_GET['page'] === 'mb-quick-add')) {
            wp_enqueue_media();
            wp_enqueue_style('mathbinder-admin', plugin_dir_url(__FILE__) . 'mathbinder-admin.css', [], self::VERSION);
            wp_enqueue_script('mathbinder-admin', plugin_dir_url(__FILE__) . 'mathbinder-admin.js', ['jquery'], self::VERSION, true);
        }
    }

    public function load_single_template($template) {
        $incoming_template = $template;
        $incoming_basename = (is_string($incoming_template) && $incoming_template !== '') ? wp_basename($incoming_template) : '';
        $diag_allowed = $this->runtime_diagnostic_allowed();
        if ($diag_allowed) {
            $this->capture_pagelayer_timing_checkpoint('template_include_999_entry', 'ti_999_in');
        }
        $is_singular_cpt = is_singular(self::CPT);
        $is_tax_self_tax = is_tax(self::TAX);
        $taxonomy_template = plugin_dir_path(__FILE__) . 'taxonomy-mb_binder_section.php';
        $taxonomy_exists = false;

        $selected_template = $incoming_template;
        $return_source = 'incoming';

        if ($is_singular_cpt) {
            $selected_template = plugin_dir_path(__FILE__) . 'single-mb_binder_page.php';
            $return_source = 'plugin-single';
        }

        if ($diag_allowed) {
            $this->capture_pagelayer_timing_checkpoint('template_include_999_exit', 'ti_999_out');
            $this->set_last_mathbinder_trace_point('priority_999');
            $load_callback_count = intval($this->runtime_diag_data['load_single_template_callback_count'] ?? 0) + 1;
            $return_basename = (is_string($selected_template) && $selected_template !== '') ? wp_basename($selected_template) : '';

            $this->runtime_diag_data['priority_999_executed'] = true;
            $this->runtime_diag_data['priority_999_callback_count'] = $load_callback_count;
            $this->runtime_diag_data['priority_999_incoming_basename'] = $incoming_basename;
            $this->runtime_diag_data['priority_999_return_basename'] = $return_basename;
            $this->runtime_diag_data['priority_999_return_source'] = $return_source;
            $this->runtime_diag_data['template_after_999_basename'] = $return_basename;
            $this->runtime_diag_data['filter_chain_reached_999'] = true;

            $this->runtime_diag_data['load_single_template_executed'] = true;
            $this->runtime_diag_data['load_single_template_callback_count'] = $load_callback_count;
            $this->runtime_diag_data['load_single_template_incoming_basename'] = $incoming_basename;
            $this->runtime_diag_data['load_single_template_is_singular_cpt'] = $is_singular_cpt;
            $this->runtime_diag_data['load_single_template_is_tax_self_tax'] = $is_tax_self_tax;
            $this->runtime_diag_data['load_single_template_file_exists'] = $taxonomy_exists;
            $this->runtime_diag_data['load_single_template_return_basename'] = $return_basename;
            $this->runtime_diag_data['load_single_template_return_source'] = $return_source;
            $this->runtime_diag_data['load_callback_instance_marker'] = $this->runtime_instance_marker;
        }

        return $selected_template;
    }

    public function trace_template_filter_998($template) {
        if (!$this->runtime_diagnostic_allowed()) {
            return $template;
        }

        $this->capture_pagelayer_timing_checkpoint('template_include_998_entry', 'ti_998');

        $this->set_last_mathbinder_trace_point('priority_998');

        $incoming_basename = (is_string($template) && $template !== '') ? wp_basename($template) : '';
        $count = intval($this->runtime_diag_data['priority_998_callback_count'] ?? 0) + 1;

        $this->runtime_diag_data['priority_998_executed'] = true;
        $this->runtime_diag_data['priority_998_callback_count'] = $count;
        $this->runtime_diag_data['priority_998_incoming_basename'] = $incoming_basename;
        $this->runtime_diag_data['template_after_998_basename'] = $incoming_basename;
        $this->runtime_diag_data['filter_chain_reached_998'] = true;
        $this->runtime_diag_data['priority_998_instance_marker'] = $this->runtime_instance_marker;

        return $template;
    }

    public function trace_template_filter_1000($template) {
        if (!$this->runtime_diagnostic_allowed()) {
            return $template;
        }

        $this->capture_pagelayer_timing_checkpoint('template_include_1000_entry', 'ti_1000');

        $this->set_last_mathbinder_trace_point('priority_1000');

        $incoming_basename = (is_string($template) && $template !== '') ? wp_basename($template) : '';
        $count = intval($this->runtime_diag_data['priority_1000_callback_count'] ?? 0) + 1;

        $this->runtime_diag_data['priority_1000_executed'] = true;
        $this->runtime_diag_data['priority_1000_callback_count'] = $count;
        $this->runtime_diag_data['priority_1000_incoming_basename'] = $incoming_basename;
        $this->runtime_diag_data['template_at_1000_basename'] = $incoming_basename;
        $this->runtime_diag_data['filter_chain_reached_1000'] = true;
        $this->runtime_diag_data['priority_1000_instance_marker'] = $this->runtime_instance_marker;

        return $template;
    }

    public function trace_template_filter_1001($template) {
        if (!$this->runtime_diagnostic_allowed()) {
            return $template;
        }

        $this->capture_pagelayer_timing_checkpoint('template_include_1001_entry', 'ti_1001');

        $this->set_last_mathbinder_trace_point('priority_1001');

        $incoming_basename = (is_string($template) && $template !== '') ? wp_basename($template) : '';
        $count = intval($this->runtime_diag_data['priority_1001_callback_count'] ?? 0) + 1;

        $this->runtime_diag_data['priority_1001_executed'] = true;
        $this->runtime_diag_data['priority_1001_callback_count'] = $count;
        $this->runtime_diag_data['priority_1001_incoming_basename'] = $incoming_basename;
        $this->runtime_diag_data['template_at_1001_basename'] = $incoming_basename;
        $this->runtime_diag_data['filter_chain_reached_1001'] = true;
        $this->runtime_diag_data['priority_1001_instance_marker'] = $this->runtime_instance_marker;

        return $template;
    }

    private function bool_string($value) {
        return $value ? 'true' : 'false';
    }

    private function set_last_mathbinder_trace_point($point) {
        if (!is_string($point) || $point === '') {
            return;
        }
        $this->runtime_diag_data['last_mathbinder_trace_point'] = $point;
    }

    private function safe_callback_label($callback) {
        if (is_string($callback) && $callback !== '') {
            return 'function:' . $callback;
        }

        if (is_array($callback) && count($callback) >= 2) {
            $target = $callback[0];
            $method = is_string($callback[1]) ? $callback[1] : '';
            if (is_string($target) && $target !== '' && $method !== '') {
                return 'static:' . $target . '::' . $method;
            }
            if (is_object($target) && $method !== '') {
                return 'object:' . get_class($target) . '::' . $method;
            }
            return 'unknown';
        }

        if ($callback instanceof Closure) {
            return 'closure';
        }

        if (is_object($callback)) {
            if (is_callable([$callback, '__invoke'])) {
                return 'invokable:' . get_class($callback);
            }
            return 'unknown';
        }

        return 'unknown';
    }

    private function read_priority_1000_template_registry_snapshot() {
        $result = [
            'captured' => false,
            'count' => 0,
            'order' => '',
            'mathbinder_position' => '',
        ];

        if (!isset($GLOBALS['wp_filter']) || !is_array($GLOBALS['wp_filter']) || !isset($GLOBALS['wp_filter']['template_include'])) {
            return $result;
        }

        $hook = $GLOBALS['wp_filter']['template_include'];
        if (!is_object($hook) || !isset($hook->callbacks) || !is_array($hook->callbacks) || !isset($hook->callbacks[1000]) || !is_array($hook->callbacks[1000])) {
            return $result;
        }

        $labels = [];
        $positions = [];
        $position = 0;

        foreach ($hook->callbacks[1000] as $entry) {
            if (!is_array($entry) || !array_key_exists('function', $entry)) {
                continue;
            }
            $position++;
            $label = $this->safe_callback_label($entry['function']);
            $labels[] = $position . '=' . $label;
            if ($label === 'object:MathBinder_Core::trace_template_filter_1000') {
                $positions[] = strval($position);
            }
        }

        $result['captured'] = true;
        $result['count'] = $position;
        $result['order'] = implode(' | ', $labels);
        $result['mathbinder_position'] = implode(',', $positions);
        return $result;
    }

    private function capture_priority_1000_registry_before_filter() {
        if (!$this->runtime_diagnostic_allowed()) {
            return;
        }
        $snapshot = $this->read_priority_1000_template_registry_snapshot();
        $this->runtime_diag_data['priority_1000_registry_captured_before_filter'] = $snapshot['captured'];
        $this->runtime_diag_data['priority_1000_callback_count_before_filter'] = intval($snapshot['count']);
        $this->runtime_diag_data['priority_1000_callback_order_before_filter'] = (string) $snapshot['order'];
        $this->runtime_diag_data['priority_1000_mathbinder_position_before_filter'] = (string) $snapshot['mathbinder_position'];
    }

    private function capture_priority_1000_registry_at_render() {
        if (!$this->runtime_diagnostic_allowed()) {
            return;
        }
        $snapshot = $this->read_priority_1000_template_registry_snapshot();
        $this->runtime_diag_data['priority_1000_registry_captured_at_render'] = $snapshot['captured'];
        $this->runtime_diag_data['priority_1000_callback_count_at_render'] = intval($snapshot['count']);
        $this->runtime_diag_data['priority_1000_callback_order_at_render'] = (string) $snapshot['order'];
        $this->runtime_diag_data['priority_1000_mathbinder_position_at_render'] = (string) $snapshot['mathbinder_position'];

        $before_count = intval($this->runtime_diag_data['priority_1000_callback_count_before_filter'] ?? 0);
        $before_order = (string) ($this->runtime_diag_data['priority_1000_callback_order_before_filter'] ?? '');
        $this->runtime_diag_data['priority_1000_registry_changed_by_render'] = (
            $before_count !== intval($snapshot['count']) ||
            $before_order !== (string) $snapshot['order']
        );
    }

    private function sanitize_shutdown_error_message($message) {
        if (!is_string($message) || $message === '') {
            return '';
        }

        $safe = preg_replace('/[A-Za-z]:\\\\[^\s"\']+/', '[path]', $message);
        $safe = preg_replace('/\\\\\\\\[^\s"\']+/', '[path]', $safe);
        $safe = preg_replace('#/(?:[^\s"\']+/)+[^\s"\']+#', '[path]', $safe);
        if (!is_string($safe)) {
            return '';
        }

        if (preg_match('/[A-Za-z]:\\\\[^\s"\']+/', $safe) || preg_match('/\\\\\\\\[^\s"\']+/', $safe) || preg_match('#/(?:[^\s"\']+/)+[^\s"\']+#', $safe)) {
            return '';
        }

        if (strlen($safe) > 240) {
            $safe = substr($safe, 0, 240);
        }

        return $safe;
    }

    private function capture_shutdown_error_diagnostic() {
        if (!$this->runtime_diagnostic_allowed()) {
            return;
        }

        $error = error_get_last();
        $fatal_types = [E_ERROR, E_PARSE, E_CORE_ERROR, E_COMPILE_ERROR, E_USER_ERROR];
        $is_fatal = is_array($error) && isset($error['type']) && in_array(intval($error['type']), $fatal_types, true);

        $this->runtime_diag_data['shutdown_error_present'] = $is_fatal;
        $this->runtime_diag_data['shutdown_error_type'] = $is_fatal ? strval(intval($error['type'])) : '';

        $safe_message = '';
        if ($is_fatal && isset($error['message']) && is_string($error['message'])) {
            $safe_message = $this->sanitize_shutdown_error_message($error['message']);
        }
        $this->runtime_diag_data['shutdown_error_message_safe'] = $safe_message;
    }

    private function normalize_request_path() {
        $request_uri = isset($_SERVER['REQUEST_URI']) ? (string) wp_unslash($_SERVER['REQUEST_URI']) : '';
        $raw_path = (string) wp_parse_url($request_uri, PHP_URL_PATH);
        $path = preg_replace('#/+#', '/', $raw_path);
        if (!is_string($path) || $path === '') {
            return '/';
        }
        $path = '/' . trim($path, '/');
        if ($path !== '/') {
            $path .= '/';
        }
        return strtolower($path);
    }

    private function runtime_request_path_match($normalized_path) {
        return in_array($normalized_path, ['/binder-section/the-number-system/', '/wp/binder-section/the-number-system/'], true);
    }

    private function runtime_diagnostic_context() {
        $path = $this->normalize_request_path();
        $cap = is_user_logged_in() && current_user_can('manage_options');
        $param = isset($_GET['mb_runtime_diagnostic']) && sanitize_text_field(wp_unslash($_GET['mb_runtime_diagnostic'])) === '1';

        return [
            'request_path' => $path,
            'request_path_match' => $this->runtime_request_path_match($path),
            'admin_capability_check' => $cap,
            'diagnostic_parameter_present' => $param,
        ];
    }

    private function runtime_diagnostic_allowed() {
        $ctx = $this->runtime_diagnostic_context();
        return $ctx['request_path_match'] && $ctx['admin_capability_check'] && $ctx['diagnostic_parameter_present'];
    }

    private function read_pagelayer_template_include_priority_state() {
        $priority = has_filter('template_include', 'pagelayer_template_include');
        $present = ($priority !== false);
        $exact_priority_1000 = ($priority === 1000);
        $count_at_1000 = 0;

        if (isset($GLOBALS['wp_filter']) && is_array($GLOBALS['wp_filter']) && isset($GLOBALS['wp_filter']['template_include'])) {
            $hook = $GLOBALS['wp_filter']['template_include'];
            if (is_object($hook) && isset($hook->callbacks) && is_array($hook->callbacks) && isset($hook->callbacks[1000]) && is_array($hook->callbacks[1000])) {
                foreach ($hook->callbacks[1000] as $entry) {
                    if (is_array($entry) && array_key_exists('function', $entry)) {
                        $count_at_1000++;
                    }
                }
            }
        }

        return [
            'present' => $present,
            'exact_priority_1000' => $exact_priority_1000,
            'count_at_1000' => $count_at_1000,
        ];
    }

    private function capture_pagelayer_timing_checkpoint($checkpoint_key, $compact_label) {
        if (!$this->runtime_diagnostic_allowed()) {
            return;
        }

        $state = $this->read_pagelayer_template_include_priority_state();
        $sequence = intval($this->runtime_diag_data['pagelayer_timing_sequence_counter'] ?? 0) + 1;
        $this->runtime_diag_data['pagelayer_timing_sequence_counter'] = $sequence;

        if (!isset($this->runtime_diag_data['pagelayer_timing']) || !is_array($this->runtime_diag_data['pagelayer_timing'])) {
            $this->runtime_diag_data['pagelayer_timing'] = [];
        }

        $this->runtime_diag_data['pagelayer_timing'][$checkpoint_key] = [
            'executed' => true,
            'present' => !empty($state['present']),
            'exact_priority_1000' => !empty($state['exact_priority_1000']),
            'count_at_1000' => intval($state['count_at_1000'] ?? 0),
            'sequence' => $sequence,
            'instance_marker' => $this->runtime_instance_marker,
        ];

        if (!isset($this->runtime_diag_data['pagelayer_timing_checkpoint_order']) || !is_array($this->runtime_diag_data['pagelayer_timing_checkpoint_order'])) {
            $this->runtime_diag_data['pagelayer_timing_checkpoint_order'] = [];
        }
        $this->runtime_diag_data['pagelayer_timing_checkpoint_order'][] = strval($sequence) . '=' . $compact_label;

        if (!isset($this->runtime_diag_data['pagelayer_first_observed_sequence']) && !empty($state['exact_priority_1000'])) {
            $this->runtime_diag_data['pagelayer_first_observed_checkpoint'] = $compact_label;
            $this->runtime_diag_data['pagelayer_first_observed_sequence'] = strval($sequence);
        }
    }

    private function pagelayer_timing_row_values($checkpoint_key) {
        $checkpoints = isset($this->runtime_diag_data['pagelayer_timing']) && is_array($this->runtime_diag_data['pagelayer_timing'])
            ? $this->runtime_diag_data['pagelayer_timing']
            : [];
        $entry = isset($checkpoints[$checkpoint_key]) && is_array($checkpoints[$checkpoint_key])
            ? $checkpoints[$checkpoint_key]
            : [];

        return [
            'executed' => !empty($entry['executed']),
            'present' => !empty($entry['present']),
            'exact_priority_1000' => !empty($entry['exact_priority_1000']),
            'count_at_1000' => intval($entry['count_at_1000'] ?? 0),
            'sequence' => strval($entry['sequence'] ?? ''),
            'instance_marker' => (string) ($entry['instance_marker'] ?? ''),
        ];
    }

    private function pagelayer_timing_instance_consistent() {
        $checkpoints = isset($this->runtime_diag_data['pagelayer_timing']) && is_array($this->runtime_diag_data['pagelayer_timing'])
            ? $this->runtime_diag_data['pagelayer_timing']
            : [];
        if (empty($checkpoints)) {
            return false;
        }

        $expected = $this->constructor_instance_marker;
        foreach ($checkpoints as $entry) {
            if (!is_array($entry) || empty($entry['executed'])) {
                continue;
            }
            if (!isset($entry['instance_marker']) || (string) $entry['instance_marker'] !== $expected) {
                return false;
            }
        }
        return true;
    }

    public function capture_pagelayer_timing_wp_priority_1() {
        $this->capture_pagelayer_timing_checkpoint('wp_priority_1', 'wp_1');
    }

    public function capture_pagelayer_timing_wp_priority_10() {
        $this->capture_pagelayer_timing_checkpoint('wp_priority_10', 'wp_10');
    }

    public function capture_pagelayer_timing_wp_priority_999() {
        $this->capture_pagelayer_timing_checkpoint('wp_priority_999', 'wp_999');
    }

    public function capture_pagelayer_timing_wp_php_int_max() {
        $this->capture_pagelayer_timing_checkpoint('wp_php_int_max', 'wp_max');
    }

    public function capture_pagelayer_timing_template_redirect_priority_10() {
        $this->capture_pagelayer_timing_checkpoint('template_redirect_priority_10', 'tr_10');
    }

    public function capture_pagelayer_timing_template_redirect_priority_999() {
        $this->capture_pagelayer_timing_checkpoint('template_redirect_priority_999', 'tr_999');
    }

    public function capture_pagelayer_timing_template_redirect_php_int_max() {
        $this->capture_pagelayer_timing_checkpoint('template_redirect_php_int_max', 'tr_max');
    }

    public function apply_pagelayer_taxonomy_compatibility() {
        if (!$this->runtime_diagnostic_allowed()) {
            return;
        }

        $this->capture_pagelayer_timing_checkpoint('template_redirect_priority_1', 'tr_1');
        $state = $this->read_pagelayer_template_include_priority_state();

        $this->runtime_diag_data['pagelayer_callback_present_before_compatibility'] = !empty($state['exact_priority_1000']);
        $this->runtime_diag_data['pagelayer_callback_removal_attempted'] = false;
        $this->runtime_diag_data['pagelayer_callback_removal_succeeded'] = false;
        $this->runtime_diag_data['pagelayer_callback_present_after_compatibility'] = !empty($state['exact_priority_1000']);
    }

    private function safe_query_var_value($value) {
        if (is_null($value)) {
            return '';
        }
        if (is_scalar($value)) {
            return (string) $value;
        }
        if (is_array($value)) {
            return '[array]';
        }
        if (is_object($value)) {
            return '[object]';
        }
        return '';
    }

    private function path_within_directory($path, $directory) {
        if (!is_string($path) || $path === '' || !is_string($directory) || $directory === '') {
            return false;
        }
        $normalized_path = wp_normalize_path($path);
        $normalized_dir = trailingslashit(wp_normalize_path($directory));
        return strpos($normalized_path, $normalized_dir) === 0;
    }

    public function capture_runtime_template_diagnostic($template) {
        if (!$this->runtime_diagnostic_allowed()) {
            return $template;
        }

        $this->capture_pagelayer_timing_checkpoint('template_include_php_int_max_entry', 'ti_max');

        $this->set_last_mathbinder_trace_point('php_int_max');

        $template_path = is_string($template) ? $template : '';
        $basename = $template_path !== '' ? wp_basename($template_path) : '';
        $capture_callback_count = intval($this->runtime_diag_data['runtime_capture_callback_count'] ?? 0) + 1;

        $this->runtime_diag_data['php_int_max_executed'] = true;
        $this->runtime_diag_data['php_int_max_callback_count'] = $capture_callback_count;
        $this->runtime_diag_data['php_int_max_incoming_basename'] = $basename;
        $this->runtime_diag_data['php_int_max_instance_marker'] = $this->runtime_instance_marker;
        $this->runtime_diag_data['filter_chain_reached_php_int_max'] = true;
        $this->runtime_diag_data['template_at_php_int_max_basename'] = $basename;

        $this->runtime_diag_data['runtime_capture_executed'] = true;
        $this->runtime_diag_data['runtime_capture_callback_count'] = $capture_callback_count;
        $this->runtime_diag_data['runtime_capture_incoming_basename'] = $basename;
        $this->runtime_diag_data['runtime_capture_final_basename'] = $basename;
        $this->runtime_diag_data['capture_callback_instance_marker'] = $this->runtime_instance_marker;

        $this->runtime_diag_data['final_template_captured'] = $template_path !== '';
        $this->runtime_diag_data['final_template_basename'] = $basename;
        $this->runtime_diag_data['final_template_in_child_theme'] = $this->path_within_directory($template_path, get_stylesheet_directory());
        $this->runtime_diag_data['final_template_in_parent_theme'] = $this->path_within_directory($template_path, get_template_directory());
        $this->runtime_diag_data['final_template_in_mathbinder_plugin'] = $this->path_within_directory($template_path, plugin_dir_path(__FILE__));
        $this->runtime_diag_data['custom_taxonomy_template_selected'] = ($basename === 'taxonomy-mb_binder_section.php');

        return $template;
    }

    public function capture_runtime_query_diagnostic() {
        if (!$this->runtime_diagnostic_allowed()) {
            return;
        }

        $this->capture_priority_1000_registry_before_filter();
        $this->runtime_diag_data['priority_1000_before_filter_capture_stage'] = 'wp@PHP_INT_MAX';

        $ctx = $this->runtime_diagnostic_context();

        $is_main_query = 'n/a';
        if (isset($GLOBALS['wp_query']) && $GLOBALS['wp_query'] instanceof WP_Query) {
            $is_main_query = $this->bool_string($GLOBALS['wp_query']->is_main_query());
        }

        $this->runtime_diag_data['request_path_match'] = $ctx['request_path_match'];
        $this->runtime_diag_data['admin_capability_check'] = $ctx['admin_capability_check'];
        $this->runtime_diag_data['diagnostic_parameter_present'] = $ctx['diagnostic_parameter_present'];
        $this->runtime_diag_data['request_path'] = $ctx['request_path'];
        $this->runtime_diag_data['is_admin'] = is_admin();
        $this->runtime_diag_data['is_front_page'] = is_front_page();
        $this->runtime_diag_data['is_home'] = is_home();
        $this->runtime_diag_data['is_page'] = is_page();
        $this->runtime_diag_data['is_single'] = is_single();
        $this->runtime_diag_data['is_singular'] = is_singular();
        $this->runtime_diag_data['is_archive'] = is_archive();
        $this->runtime_diag_data['is_tax'] = is_tax();
        $this->runtime_diag_data['is_tax_self_tax'] = is_tax(self::TAX);
        $this->runtime_diag_data['is_category'] = is_category();
        $this->runtime_diag_data['is_tag'] = is_tag();
        $this->runtime_diag_data['is_post_type_archive'] = is_post_type_archive();
        $this->runtime_diag_data['is_404'] = is_404();
        $this->runtime_diag_data['is_search'] = is_search();
        $this->runtime_diag_data['is_main_query'] = $is_main_query;
        $this->runtime_diag_data['self_tax'] = self::TAX;
        $this->runtime_diag_data['query_var_taxonomy'] = $this->safe_query_var_value(get_query_var('taxonomy'));
        $this->runtime_diag_data['query_var_term'] = $this->safe_query_var_value(get_query_var('term'));
        $this->runtime_diag_data['query_var_self_tax'] = $this->safe_query_var_value(get_query_var(self::TAX));
        $this->runtime_diag_data['query_var_post_type'] = $this->safe_query_var_value(get_query_var('post_type'));
        $this->runtime_diag_data['query_var_name'] = $this->safe_query_var_value(get_query_var('name'));
        $this->runtime_diag_data['query_var_pagename'] = $this->safe_query_var_value(get_query_var('pagename'));

        $queried = get_queried_object();
        $this->runtime_diag_data['queried_object_class'] = is_object($queried) ? get_class($queried) : '';
        $this->runtime_diag_data['queried_term_id'] = (is_object($queried) && isset($queried->term_id)) ? (string) intval($queried->term_id) : '';
        $this->runtime_diag_data['queried_taxonomy'] = (is_object($queried) && isset($queried->taxonomy)) ? (string) $queried->taxonomy : '';
        $this->runtime_diag_data['queried_slug'] = (is_object($queried) && isset($queried->slug)) ? (string) $queried->slug : '';
        $this->runtime_diag_data['queried_name'] = (is_object($queried) && isset($queried->name)) ? (string) $queried->name : '';
        $this->runtime_diag_data['queried_post_id'] = (is_object($queried) && isset($queried->ID)) ? (string) intval($queried->ID) : '';
        $this->runtime_diag_data['queried_post_type'] = (is_object($queried) && isset($queried->post_type)) ? (string) $queried->post_type : '';
        $this->runtime_diag_data['queried_post_name'] = (is_object($queried) && isset($queried->post_name)) ? (string) $queried->post_name : '';
    }

    private function runtime_diag_rows($render_method) {
        $this->capture_pagelayer_timing_checkpoint('panel_render', 'panel_render');
        $this->capture_priority_1000_registry_at_render();

        $wp_1 = $this->pagelayer_timing_row_values('wp_priority_1');
        $wp_10 = $this->pagelayer_timing_row_values('wp_priority_10');
        $wp_999 = $this->pagelayer_timing_row_values('wp_priority_999');
        $wp_max = $this->pagelayer_timing_row_values('wp_php_int_max');
        $tr_1 = $this->pagelayer_timing_row_values('template_redirect_priority_1');
        $tr_10 = $this->pagelayer_timing_row_values('template_redirect_priority_10');
        $tr_999 = $this->pagelayer_timing_row_values('template_redirect_priority_999');
        $tr_max = $this->pagelayer_timing_row_values('template_redirect_php_int_max');
        $ti_998 = $this->pagelayer_timing_row_values('template_include_998_entry');
        $ti_999_entry = $this->pagelayer_timing_row_values('template_include_999_entry');
        $ti_999_exit = $this->pagelayer_timing_row_values('template_include_999_exit');
        $ti_1000 = $this->pagelayer_timing_row_values('template_include_1000_entry');
        $ti_1001 = $this->pagelayer_timing_row_values('template_include_1001_entry');
        $ti_max = $this->pagelayer_timing_row_values('template_include_php_int_max_entry');
        $panel_render = $this->pagelayer_timing_row_values('panel_render');
        $shutdown = $this->pagelayer_timing_row_values('shutdown');
        $checkpoint_order = isset($this->runtime_diag_data['pagelayer_timing_checkpoint_order']) && is_array($this->runtime_diag_data['pagelayer_timing_checkpoint_order'])
            ? implode(' | ', $this->runtime_diag_data['pagelayer_timing_checkpoint_order'])
            : '';

        $load_998_priority = has_filter('template_include', [$this, 'trace_template_filter_998']);
        $load_999_priority = has_filter('template_include', [$this, 'load_single_template']);
        $load_1000_priority = has_filter('template_include', [$this, 'trace_template_filter_1000']);
        $load_1001_priority = has_filter('template_include', [$this, 'trace_template_filter_1001']);
        $capture_filter_priority = has_filter('template_include', [$this, 'capture_runtime_template_diagnostic']);

        $load_998_registered = ($load_998_priority !== false);
        $load_999_registered = ($load_999_priority !== false);
        $load_1000_registered = ($load_1000_priority !== false);
        $load_1001_registered = ($load_1001_priority !== false);
        $capture_filter_registered = ($capture_filter_priority !== false);

        $load_998_priority_text = $load_998_registered ? strval(intval($load_998_priority)) : '';
        $load_999_priority_text = $load_999_registered ? strval(intval($load_999_priority)) : '';
        $load_1000_priority_text = $load_1000_registered ? strval(intval($load_1000_priority)) : '';
        $load_1001_priority_text = $load_1001_registered ? strval(intval($load_1001_priority)) : '';
        $capture_filter_priority_text = '';
        if ($capture_filter_registered) {
            $capture_filter_priority_text = intval($capture_filter_priority) === PHP_INT_MAX ? 'PHP_INT_MAX' : strval(intval($capture_filter_priority));
        }

        $constructor_marker = $this->constructor_instance_marker;
        $priority_998_marker = (string) ($this->runtime_diag_data['priority_998_instance_marker'] ?? '');
        $priority_999_marker = (string) ($this->runtime_diag_data['load_callback_instance_marker'] ?? '');
        $priority_1000_marker = (string) ($this->runtime_diag_data['priority_1000_instance_marker'] ?? '');
        $priority_1001_marker = (string) ($this->runtime_diag_data['priority_1001_instance_marker'] ?? '');
        $php_int_max_marker = (string) ($this->runtime_diag_data['php_int_max_instance_marker'] ?? '');
        $load_marker = (string) ($this->runtime_diag_data['load_callback_instance_marker'] ?? '');
        $capture_marker = (string) ($this->runtime_diag_data['capture_callback_instance_marker'] ?? '');
        $panel_marker = (string) ($this->runtime_diag_data['panel_instance_marker'] ?? '');

        $callback_markers = [
            $priority_998_marker,
            $priority_999_marker,
            $priority_1000_marker,
            $priority_1001_marker,
            $php_int_max_marker,
        ];
        $available_callback_markers = array_values(array_filter($callback_markers, function($marker) {
            return $marker !== '';
        }));
        $markers_match = false;
        if ($constructor_marker !== '' && $panel_marker !== '' && !empty($available_callback_markers)) {
            $markers_match = ($constructor_marker === $panel_marker);
            if ($markers_match) {
                foreach ($available_callback_markers as $available_marker) {
                    if ($available_marker !== $constructor_marker) {
                        $markers_match = false;
                        break;
                    }
                }
            }
        }

        return [
            'REQUEST_PATH_MATCH' => $this->bool_string(!empty($this->runtime_diag_data['request_path_match'])),
            'ADMIN_CAPABILITY_CHECK' => $this->bool_string(!empty($this->runtime_diag_data['admin_capability_check'])),
            'DIAGNOSTIC_PARAMETER_PRESENT' => $this->bool_string(!empty($this->runtime_diag_data['diagnostic_parameter_present'])),
            'PRIORITY_998_EXECUTED' => $this->bool_string(!empty($this->runtime_diag_data['priority_998_executed'])),
            'PRIORITY_998_CALLBACK_COUNT' => strval(intval($this->runtime_diag_data['priority_998_callback_count'] ?? 0)),
            'PRIORITY_998_INCOMING_BASENAME' => (string) ($this->runtime_diag_data['priority_998_incoming_basename'] ?? ''),
            'PRIORITY_999_EXECUTED' => $this->bool_string(!empty($this->runtime_diag_data['priority_999_executed'])),
            'PRIORITY_999_CALLBACK_COUNT' => strval(intval($this->runtime_diag_data['priority_999_callback_count'] ?? 0)),
            'PRIORITY_999_INCOMING_BASENAME' => (string) ($this->runtime_diag_data['priority_999_incoming_basename'] ?? ''),
            'PRIORITY_999_RETURN_BASENAME' => (string) ($this->runtime_diag_data['priority_999_return_basename'] ?? ''),
            'PRIORITY_999_RETURN_SOURCE' => (string) ($this->runtime_diag_data['priority_999_return_source'] ?? ''),
            'PRIORITY_1000_EXECUTED' => $this->bool_string(!empty($this->runtime_diag_data['priority_1000_executed'])),
            'PRIORITY_1000_CALLBACK_COUNT' => strval(intval($this->runtime_diag_data['priority_1000_callback_count'] ?? 0)),
            'PRIORITY_1000_INCOMING_BASENAME' => (string) ($this->runtime_diag_data['priority_1000_incoming_basename'] ?? ''),
            'PRIORITY_1000_CALLBACK_COUNT_BEFORE_FILTER' => strval(intval($this->runtime_diag_data['priority_1000_callback_count_before_filter'] ?? 0)),
            'PRIORITY_1000_BEFORE_FILTER_CAPTURE_STAGE' => (string) ($this->runtime_diag_data['priority_1000_before_filter_capture_stage'] ?? ''),
            'PRIORITY_1000_CALLBACK_ORDER_BEFORE_FILTER' => (string) ($this->runtime_diag_data['priority_1000_callback_order_before_filter'] ?? ''),
            'PRIORITY_1000_MATHBINDER_POSITION_BEFORE_FILTER' => (string) ($this->runtime_diag_data['priority_1000_mathbinder_position_before_filter'] ?? ''),
            'PRIORITY_1000_REGISTRY_CAPTURED_BEFORE_FILTER' => $this->bool_string(!empty($this->runtime_diag_data['priority_1000_registry_captured_before_filter'])),
            'PRIORITY_1001_EXECUTED' => $this->bool_string(!empty($this->runtime_diag_data['priority_1001_executed'])),
            'PRIORITY_1001_CALLBACK_COUNT' => strval(intval($this->runtime_diag_data['priority_1001_callback_count'] ?? 0)),
            'PRIORITY_1001_INCOMING_BASENAME' => (string) ($this->runtime_diag_data['priority_1001_incoming_basename'] ?? ''),
            'PRIORITY_1000_CALLBACK_COUNT_AT_RENDER' => strval(intval($this->runtime_diag_data['priority_1000_callback_count_at_render'] ?? 0)),
            'PRIORITY_1000_CALLBACK_ORDER_AT_RENDER' => (string) ($this->runtime_diag_data['priority_1000_callback_order_at_render'] ?? ''),
            'PRIORITY_1000_MATHBINDER_POSITION_AT_RENDER' => (string) ($this->runtime_diag_data['priority_1000_mathbinder_position_at_render'] ?? ''),
            'PRIORITY_1000_REGISTRY_CAPTURED_AT_RENDER' => $this->bool_string(!empty($this->runtime_diag_data['priority_1000_registry_captured_at_render'])),
            'PRIORITY_1000_REGISTRY_CHANGED_BY_RENDER' => $this->bool_string(!empty($this->runtime_diag_data['priority_1000_registry_changed_by_render'])),
            'PAGELAYER_CALLBACK_PRESENT_BEFORE_COMPATIBILITY' => $this->bool_string(!empty($this->runtime_diag_data['pagelayer_callback_present_before_compatibility'])),
            'PAGELAYER_CALLBACK_REMOVAL_ATTEMPTED' => $this->bool_string(!empty($this->runtime_diag_data['pagelayer_callback_removal_attempted'])),
            'PAGELAYER_CALLBACK_REMOVAL_SUCCEEDED' => $this->bool_string(!empty($this->runtime_diag_data['pagelayer_callback_removal_succeeded'])),
            'PAGELAYER_CALLBACK_PRESENT_AFTER_COMPATIBILITY' => $this->bool_string(!empty($this->runtime_diag_data['pagelayer_callback_present_after_compatibility'])),
            'PAGELAYER_TIMING_WP_PRIORITY_1_EXECUTED' => $this->bool_string(!empty($wp_1['executed'])),
            'PAGELAYER_TIMING_WP_PRIORITY_1_PRESENT' => $this->bool_string(!empty($wp_1['present'])),
            'PAGELAYER_TIMING_WP_PRIORITY_1_EXACT_PRIORITY_1000' => $this->bool_string(!empty($wp_1['exact_priority_1000'])),
            'PAGELAYER_TIMING_WP_PRIORITY_1_COUNT_AT_1000' => strval(intval($wp_1['count_at_1000'])),

            'PAGELAYER_TIMING_WP_PRIORITY_10_EXECUTED' => $this->bool_string(!empty($wp_10['executed'])),
            'PAGELAYER_TIMING_WP_PRIORITY_10_PRESENT' => $this->bool_string(!empty($wp_10['present'])),
            'PAGELAYER_TIMING_WP_PRIORITY_10_EXACT_PRIORITY_1000' => $this->bool_string(!empty($wp_10['exact_priority_1000'])),
            'PAGELAYER_TIMING_WP_PRIORITY_10_COUNT_AT_1000' => strval(intval($wp_10['count_at_1000'])),

            'PAGELAYER_TIMING_WP_PRIORITY_999_EXECUTED' => $this->bool_string(!empty($wp_999['executed'])),
            'PAGELAYER_TIMING_WP_PRIORITY_999_PRESENT' => $this->bool_string(!empty($wp_999['present'])),
            'PAGELAYER_TIMING_WP_PRIORITY_999_EXACT_PRIORITY_1000' => $this->bool_string(!empty($wp_999['exact_priority_1000'])),
            'PAGELAYER_TIMING_WP_PRIORITY_999_COUNT_AT_1000' => strval(intval($wp_999['count_at_1000'])),

            'PAGELAYER_TIMING_WP_PHP_INT_MAX_EXECUTED' => $this->bool_string(!empty($wp_max['executed'])),
            'PAGELAYER_TIMING_WP_PHP_INT_MAX_PRESENT' => $this->bool_string(!empty($wp_max['present'])),
            'PAGELAYER_TIMING_WP_PHP_INT_MAX_EXACT_PRIORITY_1000' => $this->bool_string(!empty($wp_max['exact_priority_1000'])),
            'PAGELAYER_TIMING_WP_PHP_INT_MAX_COUNT_AT_1000' => strval(intval($wp_max['count_at_1000'])),

            'PAGELAYER_TIMING_TEMPLATE_REDIRECT_PRIORITY_1_EXECUTED' => $this->bool_string(!empty($tr_1['executed'])),
            'PAGELAYER_TIMING_TEMPLATE_REDIRECT_PRIORITY_1_PRESENT' => $this->bool_string(!empty($tr_1['present'])),
            'PAGELAYER_TIMING_TEMPLATE_REDIRECT_PRIORITY_1_EXACT_PRIORITY_1000' => $this->bool_string(!empty($tr_1['exact_priority_1000'])),
            'PAGELAYER_TIMING_TEMPLATE_REDIRECT_PRIORITY_1_COUNT_AT_1000' => strval(intval($tr_1['count_at_1000'])),

            'PAGELAYER_TIMING_TEMPLATE_REDIRECT_PRIORITY_10_EXECUTED' => $this->bool_string(!empty($tr_10['executed'])),
            'PAGELAYER_TIMING_TEMPLATE_REDIRECT_PRIORITY_10_PRESENT' => $this->bool_string(!empty($tr_10['present'])),
            'PAGELAYER_TIMING_TEMPLATE_REDIRECT_PRIORITY_10_EXACT_PRIORITY_1000' => $this->bool_string(!empty($tr_10['exact_priority_1000'])),
            'PAGELAYER_TIMING_TEMPLATE_REDIRECT_PRIORITY_10_COUNT_AT_1000' => strval(intval($tr_10['count_at_1000'])),

            'PAGELAYER_TIMING_TEMPLATE_REDIRECT_PRIORITY_999_EXECUTED' => $this->bool_string(!empty($tr_999['executed'])),
            'PAGELAYER_TIMING_TEMPLATE_REDIRECT_PRIORITY_999_PRESENT' => $this->bool_string(!empty($tr_999['present'])),
            'PAGELAYER_TIMING_TEMPLATE_REDIRECT_PRIORITY_999_EXACT_PRIORITY_1000' => $this->bool_string(!empty($tr_999['exact_priority_1000'])),
            'PAGELAYER_TIMING_TEMPLATE_REDIRECT_PRIORITY_999_COUNT_AT_1000' => strval(intval($tr_999['count_at_1000'])),

            'PAGELAYER_TIMING_TEMPLATE_REDIRECT_PHP_INT_MAX_EXECUTED' => $this->bool_string(!empty($tr_max['executed'])),
            'PAGELAYER_TIMING_TEMPLATE_REDIRECT_PHP_INT_MAX_PRESENT' => $this->bool_string(!empty($tr_max['present'])),
            'PAGELAYER_TIMING_TEMPLATE_REDIRECT_PHP_INT_MAX_EXACT_PRIORITY_1000' => $this->bool_string(!empty($tr_max['exact_priority_1000'])),
            'PAGELAYER_TIMING_TEMPLATE_REDIRECT_PHP_INT_MAX_COUNT_AT_1000' => strval(intval($tr_max['count_at_1000'])),

            'PAGELAYER_TIMING_TEMPLATE_INCLUDE_998_ENTRY_EXECUTED' => $this->bool_string(!empty($ti_998['executed'])),
            'PAGELAYER_TIMING_TEMPLATE_INCLUDE_998_ENTRY_PRESENT' => $this->bool_string(!empty($ti_998['present'])),
            'PAGELAYER_TIMING_TEMPLATE_INCLUDE_998_ENTRY_EXACT_PRIORITY_1000' => $this->bool_string(!empty($ti_998['exact_priority_1000'])),
            'PAGELAYER_TIMING_TEMPLATE_INCLUDE_998_ENTRY_COUNT_AT_1000' => strval(intval($ti_998['count_at_1000'])),

            'PAGELAYER_TIMING_TEMPLATE_INCLUDE_999_ENTRY_EXECUTED' => $this->bool_string(!empty($ti_999_entry['executed'])),
            'PAGELAYER_TIMING_TEMPLATE_INCLUDE_999_ENTRY_PRESENT' => $this->bool_string(!empty($ti_999_entry['present'])),
            'PAGELAYER_TIMING_TEMPLATE_INCLUDE_999_ENTRY_EXACT_PRIORITY_1000' => $this->bool_string(!empty($ti_999_entry['exact_priority_1000'])),
            'PAGELAYER_TIMING_TEMPLATE_INCLUDE_999_ENTRY_COUNT_AT_1000' => strval(intval($ti_999_entry['count_at_1000'])),

            'PAGELAYER_TIMING_TEMPLATE_INCLUDE_999_EXIT_EXECUTED' => $this->bool_string(!empty($ti_999_exit['executed'])),
            'PAGELAYER_TIMING_TEMPLATE_INCLUDE_999_EXIT_PRESENT' => $this->bool_string(!empty($ti_999_exit['present'])),
            'PAGELAYER_TIMING_TEMPLATE_INCLUDE_999_EXIT_EXACT_PRIORITY_1000' => $this->bool_string(!empty($ti_999_exit['exact_priority_1000'])),
            'PAGELAYER_TIMING_TEMPLATE_INCLUDE_999_EXIT_COUNT_AT_1000' => strval(intval($ti_999_exit['count_at_1000'])),

            'PAGELAYER_TIMING_TEMPLATE_INCLUDE_1000_ENTRY_EXECUTED' => $this->bool_string(!empty($ti_1000['executed'])),
            'PAGELAYER_TIMING_TEMPLATE_INCLUDE_1000_ENTRY_PRESENT' => $this->bool_string(!empty($ti_1000['present'])),
            'PAGELAYER_TIMING_TEMPLATE_INCLUDE_1000_ENTRY_EXACT_PRIORITY_1000' => $this->bool_string(!empty($ti_1000['exact_priority_1000'])),
            'PAGELAYER_TIMING_TEMPLATE_INCLUDE_1000_ENTRY_COUNT_AT_1000' => strval(intval($ti_1000['count_at_1000'])),

            'PAGELAYER_TIMING_TEMPLATE_INCLUDE_1001_ENTRY_EXECUTED' => $this->bool_string(!empty($ti_1001['executed'])),
            'PAGELAYER_TIMING_TEMPLATE_INCLUDE_1001_ENTRY_PRESENT' => $this->bool_string(!empty($ti_1001['present'])),
            'PAGELAYER_TIMING_TEMPLATE_INCLUDE_1001_ENTRY_EXACT_PRIORITY_1000' => $this->bool_string(!empty($ti_1001['exact_priority_1000'])),
            'PAGELAYER_TIMING_TEMPLATE_INCLUDE_1001_ENTRY_COUNT_AT_1000' => strval(intval($ti_1001['count_at_1000'])),

            'PAGELAYER_TIMING_TEMPLATE_INCLUDE_PHP_INT_MAX_ENTRY_EXECUTED' => $this->bool_string(!empty($ti_max['executed'])),
            'PAGELAYER_TIMING_TEMPLATE_INCLUDE_PHP_INT_MAX_ENTRY_PRESENT' => $this->bool_string(!empty($ti_max['present'])),
            'PAGELAYER_TIMING_TEMPLATE_INCLUDE_PHP_INT_MAX_ENTRY_EXACT_PRIORITY_1000' => $this->bool_string(!empty($ti_max['exact_priority_1000'])),
            'PAGELAYER_TIMING_TEMPLATE_INCLUDE_PHP_INT_MAX_ENTRY_COUNT_AT_1000' => strval(intval($ti_max['count_at_1000'])),

            'PAGELAYER_TIMING_PANEL_RENDER_EXECUTED' => $this->bool_string(!empty($panel_render['executed'])),
            'PAGELAYER_TIMING_PANEL_RENDER_PRESENT' => $this->bool_string(!empty($panel_render['present'])),
            'PAGELAYER_TIMING_PANEL_RENDER_EXACT_PRIORITY_1000' => $this->bool_string(!empty($panel_render['exact_priority_1000'])),
            'PAGELAYER_TIMING_PANEL_RENDER_COUNT_AT_1000' => strval(intval($panel_render['count_at_1000'])),

            'PAGELAYER_TIMING_SHUTDOWN_EXECUTED' => $this->bool_string(!empty($shutdown['executed'])),
            'PAGELAYER_TIMING_SHUTDOWN_PRESENT' => $this->bool_string(!empty($shutdown['present'])),
            'PAGELAYER_TIMING_SHUTDOWN_EXACT_PRIORITY_1000' => $this->bool_string(!empty($shutdown['exact_priority_1000'])),
            'PAGELAYER_TIMING_SHUTDOWN_COUNT_AT_1000' => strval(intval($shutdown['count_at_1000'])),

            'PAGELAYER_FIRST_OBSERVED_CHECKPOINT' => (string) ($this->runtime_diag_data['pagelayer_first_observed_checkpoint'] ?? ''),
            'PAGELAYER_FIRST_OBSERVED_SEQUENCE' => (string) ($this->runtime_diag_data['pagelayer_first_observed_sequence'] ?? ''),
            'PAGELAYER_TIMING_CHECKPOINT_ORDER' => $checkpoint_order,
            'PAGELAYER_TIMING_INSTANCE_CONSISTENT' => $this->bool_string($this->pagelayer_timing_instance_consistent()),
            'PHP_INT_MAX_EXECUTED' => $this->bool_string(!empty($this->runtime_diag_data['php_int_max_executed'])),
            'PHP_INT_MAX_CALLBACK_COUNT' => strval(intval($this->runtime_diag_data['php_int_max_callback_count'] ?? 0)),
            'PHP_INT_MAX_INCOMING_BASENAME' => (string) ($this->runtime_diag_data['php_int_max_incoming_basename'] ?? ''),
            'FILTER_CHAIN_REACHED_998' => $this->bool_string(!empty($this->runtime_diag_data['filter_chain_reached_998'])),
            'FILTER_CHAIN_REACHED_999' => $this->bool_string(!empty($this->runtime_diag_data['filter_chain_reached_999'])),
            'FILTER_CHAIN_REACHED_1000' => $this->bool_string(!empty($this->runtime_diag_data['filter_chain_reached_1000'])),
            'FILTER_CHAIN_REACHED_1001' => $this->bool_string(!empty($this->runtime_diag_data['filter_chain_reached_1001'])),
            'FILTER_CHAIN_REACHED_PHP_INT_MAX' => $this->bool_string(!empty($this->runtime_diag_data['filter_chain_reached_php_int_max'])),
            'TEMPLATE_AFTER_998_BASENAME' => (string) ($this->runtime_diag_data['template_after_998_basename'] ?? ''),
            'TEMPLATE_AFTER_999_BASENAME' => (string) ($this->runtime_diag_data['template_after_999_basename'] ?? ''),
            'TEMPLATE_AT_1000_BASENAME' => (string) ($this->runtime_diag_data['template_at_1000_basename'] ?? ''),
            'TEMPLATE_AT_1001_BASENAME' => (string) ($this->runtime_diag_data['template_at_1001_basename'] ?? ''),
            'TEMPLATE_AT_PHP_INT_MAX_BASENAME' => (string) ($this->runtime_diag_data['template_at_php_int_max_basename'] ?? ''),
            'CONSTRUCTOR_INSTANCE_MARKER' => $constructor_marker,
            'PRIORITY_998_INSTANCE_MARKER' => $priority_998_marker,
            'PRIORITY_999_INSTANCE_MARKER' => $priority_999_marker,
            'PRIORITY_1000_INSTANCE_MARKER' => $priority_1000_marker,
            'PRIORITY_1001_INSTANCE_MARKER' => $priority_1001_marker,
            'PHP_INT_MAX_INSTANCE_MARKER' => $php_int_max_marker,
            'PANEL_INSTANCE_MARKER' => $panel_marker,
            'ALL_AVAILABLE_INSTANCE_MARKERS_MATCH' => $this->bool_string($markers_match),
            'DIAGNOSTIC_STORAGE_SCOPE' => 'per-instance',
            'PANEL_RENDER_LATCH_SCOPE' => 'class-wide',
            'CURRENT_INSTANCE_MARKER' => $this->runtime_instance_marker,
            'SHUTDOWN_ERROR_PRESENT' => $this->bool_string(!empty($this->runtime_diag_data['shutdown_error_present'])),
            'SHUTDOWN_ERROR_TYPE' => (string) ($this->runtime_diag_data['shutdown_error_type'] ?? ''),
            'SHUTDOWN_ERROR_MESSAGE_SAFE' => (string) ($this->runtime_diag_data['shutdown_error_message_safe'] ?? ''),
            'LAST_MATHBINDER_TRACE_POINT' => (string) ($this->runtime_diag_data['last_mathbinder_trace_point'] ?? ''),
            'PRIORITY_998_REGISTERED_AT_RENDER' => $this->bool_string($load_998_registered),
            'PRIORITY_998_REGISTERED_PRIORITY' => $load_998_priority_text,
            'PRIORITY_999_REGISTERED_AT_RENDER' => $this->bool_string($load_999_registered),
            'PRIORITY_999_REGISTERED_PRIORITY' => $load_999_priority_text,
            'PRIORITY_1000_REGISTERED_AT_RENDER' => $this->bool_string($load_1000_registered),
            'PRIORITY_1000_REGISTERED_PRIORITY' => $load_1000_priority_text,
            'PRIORITY_1001_REGISTERED_AT_RENDER' => $this->bool_string($load_1001_registered),
            'PRIORITY_1001_REGISTERED_PRIORITY' => $load_1001_priority_text,
            'PHP_INT_MAX_REGISTERED_AT_RENDER' => $this->bool_string($capture_filter_registered),
            'PHP_INT_MAX_REGISTERED_PRIORITY' => $capture_filter_priority_text,
            'LOAD_FILTER_REGISTERED_AT_RENDER' => $this->bool_string($load_999_registered),
            'CAPTURE_FILTER_REGISTERED_AT_RENDER' => $this->bool_string($capture_filter_registered),
            'LOAD_FILTER_PRIORITY_AT_RENDER' => $load_999_priority_text,
            'CAPTURE_FILTER_PRIORITY_AT_RENDER' => $capture_filter_priority_text,
            'LOAD_SINGLE_TEMPLATE_EXECUTED' => $this->bool_string(!empty($this->runtime_diag_data['load_single_template_executed'])),
            'LOAD_SINGLE_TEMPLATE_CALLBACK_COUNT' => strval(intval($this->runtime_diag_data['load_single_template_callback_count'] ?? 0)),
            'LOAD_SINGLE_TEMPLATE_INCOMING_BASENAME' => (string) ($this->runtime_diag_data['load_single_template_incoming_basename'] ?? ''),
            'LOAD_SINGLE_TEMPLATE_IS_SINGULAR_CPT' => $this->bool_string(!empty($this->runtime_diag_data['load_single_template_is_singular_cpt'])),
            'LOAD_SINGLE_TEMPLATE_IS_TAX_SELF_TAX' => $this->bool_string(!empty($this->runtime_diag_data['load_single_template_is_tax_self_tax'])),
            'LOAD_SINGLE_TEMPLATE_FILE_EXISTS' => $this->bool_string(!empty($this->runtime_diag_data['load_single_template_file_exists'])),
            'LOAD_SINGLE_TEMPLATE_RETURN_BASENAME' => (string) ($this->runtime_diag_data['load_single_template_return_basename'] ?? ''),
            'LOAD_SINGLE_TEMPLATE_RETURN_SOURCE' => (string) ($this->runtime_diag_data['load_single_template_return_source'] ?? ''),
            'RUNTIME_CAPTURE_EXECUTED' => $this->bool_string(!empty($this->runtime_diag_data['runtime_capture_executed'])),
            'RUNTIME_CAPTURE_CALLBACK_COUNT' => strval(intval($this->runtime_diag_data['runtime_capture_callback_count'] ?? 0)),
            'RUNTIME_CAPTURE_INCOMING_BASENAME' => (string) ($this->runtime_diag_data['runtime_capture_incoming_basename'] ?? ''),
            'RUNTIME_CAPTURE_FINAL_BASENAME' => (string) ($this->runtime_diag_data['runtime_capture_final_basename'] ?? ''),
            'FINAL_TEMPLATE_CAPTURED' => $this->bool_string(!empty($this->runtime_diag_data['final_template_captured'])),
            'FINAL_TEMPLATE_BASENAME' => (string) ($this->runtime_diag_data['final_template_basename'] ?? ''),
            'FINAL_TEMPLATE_IN_CHILD_THEME' => $this->bool_string(!empty($this->runtime_diag_data['final_template_in_child_theme'])),
            'FINAL_TEMPLATE_IN_PARENT_THEME' => $this->bool_string(!empty($this->runtime_diag_data['final_template_in_parent_theme'])),
            'FINAL_TEMPLATE_IN_MATHBINDER_PLUGIN' => $this->bool_string(!empty($this->runtime_diag_data['final_template_in_mathbinder_plugin'])),
            'CUSTOM_TAXONOMY_TEMPLATE_SELECTED' => $this->bool_string(!empty($this->runtime_diag_data['custom_taxonomy_template_selected'])),
            'LOAD_CALLBACK_INSTANCE_MARKER' => $load_marker,
            'CAPTURE_CALLBACK_INSTANCE_MARKER' => $capture_marker,
            'INSTANCE_MARKERS_MATCH' => $this->bool_string($markers_match),
            'PANEL_RENDER_METHOD' => $render_method,
            'ACTIVE_PLUGIN_VERSION' => self::VERSION,
            'REQUEST_PATH' => (string) ($this->runtime_diag_data['request_path'] ?? ''),
            'is_admin()' => $this->bool_string(!empty($this->runtime_diag_data['is_admin'])),
            'is_front_page()' => $this->bool_string(!empty($this->runtime_diag_data['is_front_page'])),
            'is_home()' => $this->bool_string(!empty($this->runtime_diag_data['is_home'])),
            'is_page()' => $this->bool_string(!empty($this->runtime_diag_data['is_page'])),
            'is_single()' => $this->bool_string(!empty($this->runtime_diag_data['is_single'])),
            'is_singular()' => $this->bool_string(!empty($this->runtime_diag_data['is_singular'])),
            'is_archive()' => $this->bool_string(!empty($this->runtime_diag_data['is_archive'])),
            'is_tax()' => $this->bool_string(!empty($this->runtime_diag_data['is_tax'])),
            'is_tax(self::TAX)' => $this->bool_string(!empty($this->runtime_diag_data['is_tax_self_tax'])),
            'is_category()' => $this->bool_string(!empty($this->runtime_diag_data['is_category'])),
            'is_tag()' => $this->bool_string(!empty($this->runtime_diag_data['is_tag'])),
            'is_post_type_archive()' => $this->bool_string(!empty($this->runtime_diag_data['is_post_type_archive'])),
            'is_404()' => $this->bool_string(!empty($this->runtime_diag_data['is_404'])),
            'is_search()' => $this->bool_string(!empty($this->runtime_diag_data['is_search'])),
            'is_main_query()' => (string) ($this->runtime_diag_data['is_main_query'] ?? 'n/a'),
            'self::TAX' => (string) ($this->runtime_diag_data['self_tax'] ?? ''),
            'get_query_var("taxonomy")' => (string) ($this->runtime_diag_data['query_var_taxonomy'] ?? ''),
            'get_query_var("term")' => (string) ($this->runtime_diag_data['query_var_term'] ?? ''),
            'get_query_var(self::TAX)' => (string) ($this->runtime_diag_data['query_var_self_tax'] ?? ''),
            'get_query_var("post_type")' => (string) ($this->runtime_diag_data['query_var_post_type'] ?? ''),
            'get_query_var("name")' => (string) ($this->runtime_diag_data['query_var_name'] ?? ''),
            'get_query_var("pagename")' => (string) ($this->runtime_diag_data['query_var_pagename'] ?? ''),
            'queried_object class' => (string) ($this->runtime_diag_data['queried_object_class'] ?? ''),
            'queried_object term_id' => (string) ($this->runtime_diag_data['queried_term_id'] ?? ''),
            'queried_object taxonomy' => (string) ($this->runtime_diag_data['queried_taxonomy'] ?? ''),
            'queried_object slug' => (string) ($this->runtime_diag_data['queried_slug'] ?? ''),
            'queried_object name' => (string) ($this->runtime_diag_data['queried_name'] ?? ''),
            'queried_object post ID' => (string) ($this->runtime_diag_data['queried_post_id'] ?? ''),
            'queried_object post type' => (string) ($this->runtime_diag_data['queried_post_type'] ?? ''),
            'queried_object post name' => (string) ($this->runtime_diag_data['queried_post_name'] ?? ''),
        ];
    }

    private function maybe_render_runtime_diagnostic_panel($render_method) {
        if ($this->runtime_diag_panel_rendered || !$this->runtime_diagnostic_allowed()) {
            return;
        }
        $this->set_last_mathbinder_trace_point('panel_renderer');
        $this->runtime_diag_data['panel_instance_marker'] = $this->runtime_instance_marker;
        $rows = $this->runtime_diag_rows($render_method);
        $this->runtime_diag_panel_rendered = true;

        echo '<div style="position:fixed;left:12px;right:12px;bottom:12px;z-index:2147483647;max-width:980px;max-height:46vh;margin:0 auto;padding:12px;border:2px solid #2b2b2b;background:#fffef5;color:#111;box-shadow:0 8px 24px rgba(0,0,0,.25);overflow:auto;font:13px/1.4 -apple-system,BlinkMacSystemFont,Segoe UI,Arial,sans-serif;">';
        echo '<div style="font-weight:700;font-size:16px;margin-bottom:8px;">' . esc_html('MathBinder Runtime Diagnostic ' . self::VERSION) . '</div>';
        foreach ($rows as $label => $value) {
            echo '<div style="display:flex;gap:8px;border-top:1px solid #d9d6c7;padding:4px 0;">';
            echo '<div style="min-width:290px;font-weight:600;">' . esc_html($label) . '</div>';
            echo '<div style="word-break:break-word;">' . esc_html((string) $value) . '</div>';
            echo '</div>';
        }
        echo '</div>';
    }

    public function render_runtime_diagnostic_panel_footer() {
        $this->maybe_render_runtime_diagnostic_panel('wp_footer');
    }

    public function render_runtime_diagnostic_panel_shutdown() {
        if ($this->runtime_diagnostic_allowed()) {
            $this->set_last_mathbinder_trace_point('shutdown');
            $this->capture_pagelayer_timing_checkpoint('shutdown', 'shutdown');
            $this->capture_shutdown_error_diagnostic();
        }
        $this->maybe_render_runtime_diagnostic_panel('shutdown');
    }

    public function lines($text) {
        $lines = preg_split('/\r\n|\r|\n/', (string) $text);
        return array_values(array_filter(array_map('trim', $lines), function($line) { return $line !== ''; }));
    }

    public function render_list($text, $class = '') {
        $items = $this->lines($text);
        if (!$items) return '';
        $html = '<ul class="mb-list ' . esc_attr($class) . '">';
        foreach ($items as $item) $html .= '<li>' . wp_kses_post($item) . '</li>';
        return $html . '</ul>';
    }

    public function parse_resources($text) {
        $resources = [];
        foreach ($this->lines($text) as $line) {
            $parts = array_map('trim', explode('|', $line, 2));
            $title = $parts[0] ?? '';
            $url = isset($parts[1]) ? esc_url_raw($parts[1]) : '';
            if ($title || $url) $resources[] = ['title' => $title ?: $url, 'url' => $url];
        }
        return $resources;
    }

    public function youtube_embed_url($url) {
        $url = html_entity_decode(trim((string) $url), ENT_QUOTES, 'UTF-8');
        if ($url === '') return '';

        $video_id = '';

        if (preg_match('/^[A-Za-z0-9_-]{11}$/', $url)) {
            $video_id = $url;
        } else {
            $parts = wp_parse_url($url);
            $host = strtolower((string) ($parts['host'] ?? ''));
            $host = preg_replace('/^(?:www\.|m\.)/', '', $host);
            $path = trim((string) ($parts['path'] ?? ''), '/');

            if ($host === 'youtu.be') {
                $video_id = explode('/', $path)[0] ?? '';
            } elseif (in_array($host, ['youtube.com', 'youtube-nocookie.com'], true)) {
                if ($path === 'watch') {
                    parse_str((string) ($parts['query'] ?? ''), $query);
                    $video_id = (string) ($query['v'] ?? '');
                } elseif (preg_match('~^(?:embed|shorts|live)/([A-Za-z0-9_-]{11})(?:/|$)~', $path, $matches)) {
                    $video_id = $matches[1];
                }
            }
        }

        if (!preg_match('/^[A-Za-z0-9_-]{11}$/', $video_id)) return '';

        return 'https://www.youtube-nocookie.com/embed/' . rawurlencode($video_id) . '?rel=0';
    }

    public function render_resource_cards($text, $provider = 'Resource', $note = '') {
        $resources = $this->parse_resources($text);
        if (!$resources) return '';
        $html = '<div class="mb-resource-grid">';
        foreach ($resources as $resource) {
            $html .= '<article class="mb-resource-card"><span class="mb-provider">' . esc_html($provider) . '</span>';
            $html .= '<h3>' . esc_html($resource['title']) . '</h3>';
            if ($note) $html .= '<p class="mb-resource-note">' . esc_html($note) . '</p>';
            if ($resource['url']) $html .= '<a class="mb-resource-link" href="' . esc_url($resource['url']) . '" target="_blank" rel="noopener">Open resource <span aria-hidden="true">→</span></a>';
            $html .= '</article>';
        }
        $html .= '
';
        return $html . '</div>';
    }

    public function render_videos($text) {
        $resources = $this->parse_resources($text);
        if (!$resources) return '';
        $html = '<div class="mb-video-grid">';
        foreach ($resources as $resource) {
            $embed = $resource['url'] ? wp_oembed_get($resource['url'], ['width' => 900]) : '';
            $html .= '<article class="mb-video-card"><h3>' . esc_html($resource['title']) . '</h3>';
            if ($embed) {
                $html .= '<div class="mb-video-embed">' . $embed . '</div>';
            } elseif ($resource['url']) {
                $html .= '<a class="mb-resource-link" href="' . esc_url($resource['url']) . '" target="_blank" rel="noopener">Watch video <span aria-hidden="true">→</span></a>';
            }
            $html .= '</article>';
        }
        return $html . '</div>';
    }

    public function render_common_questions($text) {
        $items = $this->lines($text);
        if (!$items) return '';
        $html = '<div class="mb-faq">';
        foreach ($items as $item) {
            $parts = array_map('trim', explode('|', $item, 2));
            $question = $parts[0] ?? '';
            $answer = $parts[1] ?? '';
            $html .= '<details><summary>' . esc_html($question) . '</summary>';
            if ($answer) $html .= '<p>' . wp_kses_post($answer) . '</p>';
            $html .= '</details>';
        }
        return $html . '</div>';
    }

    public function render_interactive_vocabulary($text) {
        $items = $this->lines($text);
        if (!$items) return '';

        $html = '<div class="mb-vocab-grid">';
        foreach ($items as $index => $item) {
            $parts = array_map('trim', preg_split('/\s+[—-]\s+/', $item, 2));
            $term = $parts[0] ?? '';
            $definition = $parts[1] ?? '';
            if (!$term) continue;

            $html .= '<article class="mb-vocab-card">';
            $html .= '<button type="button" class="mb-vocab-toggle" aria-expanded="false">';
            $html .= '<span class="mb-vocab-index">' . esc_html($index + 1) . '</span>';
            $html .= '<span>' . esc_html($term) . '</span>';
            $html .= '<span class="mb-vocab-plus" aria-hidden="true">+</span>';
            $html .= '</button>';
            $html .= '<div class="mb-vocab-definition" hidden>';
            $html .= '<p>' . esc_html($definition ?: 'Add a student-friendly definition in the Binder Page editor.') . '</p>';
            $note_text = $term . ($definition ? ' — ' . $definition : '');
            $html .= '<button type="button" class="mb-vocab-add-note" data-mb-vocabulary-text="' . esc_attr($note_text) . '">Add to My Notes</button>';
            $html .= '</div>';
            $html .= '</article>';
        }
        return $html . '</div>';
    }

    public function render_step_examples($text) {
        $items = $this->lines($text);
        if (!$items) return '';

        $html = '<div class="mb-step-example-list">';
        foreach ($items as $index => $item) {
            $parts = array_map('trim', explode('|', $item));
            $title = $parts[0] ?? ('Example ' . ($index + 1));
            $steps = array_slice($parts, 1);

            if (!$steps) {
                $steps = preg_split('/(?<=[.!?])\s+/', $item);
                $title = 'Worked Example ' . ($index + 1);
            }

            $html .= '<article class="mb-step-example">';
            $html .= '<div class="mb-step-example-heading">';
            $html .= '<span>Example ' . esc_html($index + 1) . '</span>';
            $html .= '<h4>' . esc_html($title) . '</h4>';
            $html .= '</div>';
            $html .= '<div class="mb-step-example-steps">';
            foreach ($steps as $step_index => $step) {
                if (!$step) continue;
                $html .= '<div class="mb-example-step' . ($step_index === 0 ? ' is-visible' : '') . '" ' . ($step_index === 0 ? '' : 'hidden') . '>';
                $html .= '<span>' . esc_html($step_index + 1) . '</span>';
                $html .= '<p>' . esc_html($step) . '</p>';
                $html .= '</div>';
            }
            $html .= '</div>';
            if (count($steps) > 1) {
                $html .= '<button type="button" class="mb-show-next-step">Show Next Step</button>';
            }
            $html .= '</article>';
        }
        return $html . '</div>';
    }

    public function render_misconception_cards($text) {
        $items = $this->lines($text);
        if (!$items) return '';

        $html = '<div class="mb-misconception-grid">';
        foreach ($items as $item) {
            $parts = array_map('trim', explode('|', $item, 2));
            $mistake = $parts[0] ?? '';
            $correction = $parts[1] ?? 'Review the example and compare the digit’s place with its value.';

            $html .= '<article class="mb-misconception-card">';
            $html .= '<div class="mb-misconception-wrong"><span aria-hidden="true">×</span><p>' . esc_html($mistake) . '</p></div>';
            $html .= '<div class="mb-misconception-correct"><span aria-hidden="true">✓</span><p>' . esc_html($correction) . '</p></div>';
            $html .= '</article>';
        }
        return $html . '</div>';
    }

    public function render_learn_checks($text) {
        $items = $this->lines($text);
        if (!$items) return '';

        $html = '<div class="mb-learn-checks">';
        foreach ($items as $index => $item) {
            $parts = array_map('trim', explode('|', $item));
            $question = $parts[0] ?? '';
            $options = isset($parts[1]) ? array_values(array_filter(array_map('trim', explode(';', $parts[1])))) : [];
            $answer = strtoupper($parts[2] ?? '');

            if (!$question || !$options || !$answer) continue;

            $html .= '<article class="mb-learn-check" data-correct="' . esc_attr($answer) . '">';
            $html .= '<span class="mb-check-label">Quick Check ' . esc_html($index + 1) . '</span>';
            $html .= '<h4>' . esc_html($question) . '</h4>';
            $html .= '<div class="mb-learn-check-options">';
            foreach ($options as $option_index => $option) {
                $letter = chr(65 + $option_index);
                $html .= '<button type="button" data-choice="' . esc_attr($letter) . '"><span>' . esc_html($letter) . '</span>' . esc_html($option) . '</button>';
            }
            $html .= '</div>';
            $html .= '<div class="mb-learn-check-feedback" aria-live="polite"></div>';
            $html .= '</article>';
        }
        return $html . '</div>';
    }

    public function render_video_chapters($text) {
        $items = $this->lines($text);
        if (!$items) return '';

        $html = '<div class="mb-video-chapters">';
        foreach ($items as $index => $item) {
            $parts = array_map('trim', explode('|', $item, 2));
            $time = $parts[0] ?? '';
            $label = $parts[1] ?? ('Chapter ' . ($index + 1));
            if (!$time) continue;

            $seconds = 0;
            $segments = array_map('intval', explode(':', $time));
            if (count($segments) === 2) {
                $seconds = ($segments[0] * 60) + $segments[1];
            } elseif (count($segments) === 3) {
                $seconds = ($segments[0] * 3600) + ($segments[1] * 60) + $segments[2];
            }

            $html .= '<button type="button" class="mb-video-chapter" data-video-time="' . esc_attr($seconds) . '">';
            $html .= '<span>' . esc_html($time) . '</span>';
            $html .= '<strong>' . esc_html($label) . '</strong>';
            $html .= '</button>';
        }
        return $html . '</div>';
    }

    public function render_watch_vocab($text) {
        $items = $this->lines($text);
        if (!$items) return '';

        $html = '<div class="mb-watch-vocab">';
        foreach ($items as $item) {
            $parts = array_map('trim', preg_split('/\s+[—-]\s+/', $item, 2));
            $term = $parts[0] ?? '';
            $definition = $parts[1] ?? '';
            if (!$term) continue;

            $html .= '<article>';
            $html .= '<strong>' . esc_html($term) . '</strong>';
            if ($definition) $html .= '<p>' . esc_html($definition) . '</p>';
            $html .= '</article>';
        }
        return $html . '</div>';
    }

    public function render_pause_prompts($text) {
        $items = $this->lines($text);
        if (!$items) return '';

        $html = '<div class="mb-pause-prompts">';
        foreach ($items as $index => $item) {
            $html .= '<article class="mb-pause-prompt">';
            $html .= '<span>Pause &amp; Think ' . esc_html($index + 1) . '</span>';
            $html .= '<p>' . esc_html($item) . '</p>';
            $html .= '<button type="button" class="mb-pause-reveal">Show Reflection Prompt</button>';
            $html .= '<div class="mb-pause-response" hidden><textarea rows="3" placeholder="Write your thinking here…"></textarea></div>';
            $html .= '</article>';
        }
        return $html . '</div>';
    }

    public function render_practice_items($text, $mode = 'guided') {
        $items = $this->lines($text);
        if (!$items) return '';
        $lesson_title = function_exists('get_the_title') ? (string) get_the_title() : '';

        $html = '<div class="mb-practice-items" data-practice-mode="' . esc_attr($mode) . '">';
        foreach ($items as $index => $item) {
            // Protect the optional directions separator, then accept authored
            // fields with or without spaces around their single pipe delimiter.
            $directions_token = '__MB_DIRECTIONS_SEPARATOR__';
            $protected_item = str_replace('||', $directions_token, $item);
            $parts = array_map('trim', explode('|', $protected_item));
            $parts = array_map(function($part) use ($directions_token) {
                return str_replace($directions_token, '||', $part);
            }, $parts);
            $question_block = $parts[0] ?? '';
            $answer = $parts[1] ?? '';
            $hint1 = $parts[2] ?? '';
            $hint2 = $parts[3] ?? '';
            $solution = $parts[4] ?? '';
            if (!$question_block) continue;

            $question_parts = array_map('trim', explode('||', $question_block, 2));
            if (count($question_parts) === 2) {
                $directions = $question_parts[0];
                $question = $question_parts[1];
            } else {
                $question = $question_block;
                $directions_by_mode = [
                    'warm-up' => 'Solve the problem. Show your thinking, then enter only your final answer.',
                    'guided' => 'Use the lesson method. Show each step, then enter only your final answer.',
                    'independent' => 'Solve independently. Show your work, check it, then enter only your final answer.',
                ];
                $directions = $directions_by_mode[$mode] ?? 'Complete the problem. Show your work, then enter only your final answer.';
            }
            if (!$question || !$answer) continue;

            $fallback_hint = 'Identify what is known, what must be found, and the lesson rule or model that connects them. Do not calculate the final answer yet.';
            $question_lower = strtolower($question);
            if (strpos($question_lower, 'distance') !== false || strpos($question_lower, 'coordinate') !== false) {
                $fallback_hint = 'Find the horizontal and vertical changes first. Use those changes as the legs of a right triangle; do not simplify to the final distance yet.';
            } elseif (strpos($question_lower, 'right triangle') !== false || strpos($question_lower, 'hypotenuse') !== false || strpos($question_lower, 'diagonal') !== false) {
                $fallback_hint = 'Label the two legs and the hypotenuse before substituting into a²+b²=c². Stop before calculating the final length.';
            }
            foreach (['hint1', 'hint2'] as $hint_key) {
                $hint_value = $$hint_key;
                $answer_pattern = '/(^|[^[:alnum:]])' . preg_quote(trim($answer), '/') . '([^[:alnum:]]|$)/iu';
                if (!$hint_value || strpos($hint_value, '=') !== false || preg_match($answer_pattern, $hint_value)) {
                    $$hint_key = $fallback_hint;
                }
            }

            $normalized_answer = strtolower(preg_replace('/\s+/', ' ', str_replace(',', '', trim($answer))));
            $answer_hash = hash('sha256', $normalized_answer);

            $html .= '<article class="mb-practice-problem" data-answer-hash="' . esc_attr($answer_hash) . '">';
            $html .= '<span class="mb-practice-problem-label">' . esc_html(ucfirst($mode)) . ' ' . esc_html($index + 1) . '</span>';
            $html .= '<p class="mb-practice-directions"><strong>Directions:</strong> ' . esc_html($directions) . '</p>';
            $html .= '<h4>' . esc_html($question) . '</h4>';
            $html .= $this->render_safe_practice_problem_visual($question, $answer, $mode, $lesson_title);
            if ($mode === 'guided') {
                $html .= '<div class="mb-guided-steps" aria-label="Guided problem steps">';
                $html .= '<button type="button" class="mb-guided-step" data-guided-step="1" aria-expanded="false">Start with Step 1</button>';
                $html .= '<div class="mb-guided-step-content" data-guided-content="1" hidden><strong>Step 1:</strong> ' . esc_html($hint1) . '<label>Write your Step 1 work<input type="text" class="mb-guided-work" autocomplete="off"></label></div>';
                $html .= '<button type="button" class="mb-guided-step" data-guided-step="2" aria-expanded="false" hidden>Continue to Step 2</button>';
                $html .= '<div class="mb-guided-step-content" data-guided-content="2" hidden><strong>Step 2:</strong> ' . esc_html($hint2) . '<label>Write your Step 2 work<input type="text" class="mb-guided-work" autocomplete="off"></label></div>';
                $html .= '</div>';
            }
            $html .= $this->render_practice_response_workspace($index + 1, $this->practice_calculator_allowed());
            $html .= '<div class="mb-practice-actions">';
            if ($mode !== 'guided' && $hint1) $html .= '<button type="button" class="mb-practice-hint" data-hint="' . esc_attr($hint1) . '">Hint</button>';
            if ($mode !== 'guided' && $hint2) $html .= '<button type="button" class="mb-practice-hint" data-hint="' . esc_attr($hint2) . '">Another Hint</button>';
            $html .= '<button type="button" class="mb-practice-submit">Check Answer</button>';
            if ($solution) $html .= '<button type="button" class="mb-practice-solution" data-solution="' . esc_attr($solution) . '">Show Solution</button>';
            $html .= '</div>';
            $html .= '<div class="mb-practice-feedback" aria-live="polite"></div>';
            $html .= '</article>';
        }
        return $html . '</div>';
    }

    private function practice_calculator_allowed() {
        $post_id = function_exists('get_the_ID') ? (int) get_the_ID() : 0;
        $title = function_exists('get_the_title') ? strtolower((string) get_the_title($post_id)) : '';
        $standards = $post_id ? strtolower((string) get_post_meta($post_id, '_mb_standards', true)) : '';
        $text = $title . ' ' . $standards;
        if (preg_match('/\b(?:algebra\s*i{1,2}|geometry|precalculus|calculus|high[ -]?school)\b/i', $text)) return true;
        if (preg_match('/(?:ccss\s*)?(?:grade\s*)?([7-9]|1[0-2])\b/i', $text, $match)) return (int) $match[1] >= 7;
        if (preg_match('/\b([7-9])\.[a-z]{1,4}\b/i', $standards, $match)) return (int) $match[1] >= 7;
        return false;
    }

    private function render_practice_response_workspace($problem_number, $calculator_enabled = false) {
        $symbols = ['+', '−', '×', '÷', '=', '≠', '<', '>', '≤', '≥', '±', '≈', 'π', '√', '²', '³', '°', '∠', '%', '½', '¼', '¾'];
        $emojis = ['😀', '🙂', '🤔', '💡', '⭐', '✅', '❓', '👍', '🎯', '🧠'];
        $html = '<div class="mb-practice-response-workspace" data-practice-workspace>';
        $html .= '<p class="mb-practice-workspace-label">Choose how you want to work or answer.</p>';
        $html .= '<div class="mb-response-tools" data-practice-tools>';
        $html .= '<button type="button" class="is-active" data-practice-mode="type">Type</button>';
        $html .= '<button type="button" data-practice-mode="draw">Draw / Show Work</button>';
        $html .= '<button type="button" data-practice-mode="symbols">Math Symbols</button>';
        $html .= '<button type="button" data-practice-mode="emoji">Emoji</button>';
        if ($calculator_enabled) $html .= '<button type="button" data-practice-mode="calculator">Desmos Calculator</button>';
        $html .= '<div class="mb-symbol-palette" data-practice-symbol-palette hidden>';
        foreach ($symbols as $symbol) $html .= '<button type="button" data-practice-insert="' . esc_attr($symbol) . '">' . esc_html($symbol) . '</button>';
        $html .= '</div><div class="mb-emoji-palette" data-practice-emoji-palette hidden>';
        foreach ($emojis as $emoji) $html .= '<button type="button" data-practice-insert="' . esc_attr($emoji) . '">' . esc_html($emoji) . '</button>';
        $html .= '</div></div>';
        $html .= '<div class="mb-drawing-panel" data-practice-drawing-panel hidden><div class="mb-drawing-controls">'
            . '<label>Color <input type="color" value="#293241" data-practice-draw-color></label>'
            . '<label>Line <input type="range" min="1" max="14" value="3" data-practice-draw-size></label>'
            . '<button type="button" data-practice-draw-eraser>Eraser</button>'
            . '<button type="button" data-practice-draw-undo>Undo</button>'
            . '<button type="button" data-practice-draw-clear>Clear</button></div>'
            . '<canvas data-practice-canvas width="1000" height="300" aria-label="Drawing area for problem ' . esc_attr($problem_number) . '"></canvas></div>';
        if ($calculator_enabled) {
            $html .= '<div class="mb-calculator-panel mb-practice-calculator-panel" data-practice-calculator-panel hidden>'
                . '<div class="mb-practice-calculator-links"><strong>Desmos tools:</strong> '
                . '<a href="https://www.desmos.com/scientific" target="_blank" rel="noopener">Scientific</a>'
                . '<a href="https://www.desmos.com/calculator" target="_blank" rel="noopener">Graphing</a></div>'
                . '<iframe title="Desmos Scientific Calculator" loading="lazy" src="https://www.desmos.com/scientific"></iframe></div>';
        }
        $html .= '<input type="text" class="mb-practice-answer" aria-label="Answer for problem ' . esc_attr($problem_number) . '" placeholder="Type your final answer">';
        return $html . '</div>';
    }

    public function render_learn_visual($title, $subtitle = '', $essential_question = '') {
        $context = strtolower(trim((string) $title . ' ' . (string) $subtitle . ' ' . (string) $essential_question));
        $visual = '';

        if (preg_match('/sort and classify/', $context)) {
            $visual = $this->render_measurement_visual('classify', [], 'Sort by an attribute', 'Objects can be grouped by shape, size, color, or another feature.');
        } elseif (preg_match('/time, mass and volume|mass and volume/', $context)) {
            $visual = $this->render_measurement_visual('mass-volume', [], 'Measure mass and liquid volume', 'Choose a tool and unit that match what is being measured.');
        } elseif (preg_match('/time to|clock/', $context)) {
            $visual = $this->render_measurement_visual('clock', [], 'Read the clock', 'The short hand shows the hour. The long hand shows the minutes.');
        } elseif (preg_match('/money/', $context)) {
            $visual = $this->render_measurement_visual('money', [], 'Know the coins', 'Use each coin value to find the total amount.');
        } elseif (preg_match('/picture and bar graphs|scaled graphs/', $context)) {
            $visual = $this->render_measurement_visual('bar-graph', [], 'Read the data display', 'Compare bar heights and use the scale to answer questions.');
        } elseif (preg_match('/represent and interpret data/', $context)) {
            $visual = $this->render_measurement_visual('line-plot', [], 'Read a line plot', 'Each X represents one measurement at that value.');
        } elseif (preg_match('/area and perimeter/', $context)) {
            $visual = $this->render_measurement_visual('area-perimeter', [], 'See area and perimeter', 'Area covers the inside. Perimeter measures the distance around.');
        } elseif (preg_match('/measurement conversions|convert measurement/', $context)) {
            $visual = $this->render_measurement_visual('conversion', [], 'Connect equivalent units', 'Convert by using the relationship between the two units.');
        } elseif (preg_match('/\bvolume\b/', $context)) {
            $visual = $this->render_measurement_visual('volume', [], 'See cubic units', 'Volume counts the unit cubes that fill a three-dimensional space.');
        } elseif (preg_match('/measure|length|measurement/', $context)) {
            $visual = $this->render_measurement_visual('length', [], 'Measure from zero', 'Line up the endpoints and count equal-size units without gaps or overlaps.');
        } elseif (preg_match('/fractions as numbers/', $context)) {
            $visual = $this->render_fraction_bar_visual([[3,4]], 'See 3/4 as a number', 'Four equal parts make the whole. Three parts are counted.');
        } elseif (preg_match('/equivalent fractions and simplest form/', $context)) {
            $visual = $this->render_fraction_bar_visual([[6,8],[3,4]], 'See the same amount two ways', 'The shaded lengths are equal, so 6/8 = 3/4.');
        } elseif (preg_match('/equivalent fractions/', $context)) {
            $visual = $this->render_fraction_bar_visual([[1,2],[2,4]], 'See the same amount two ways', 'Both bars show one-half of the same-sized whole.');
        } elseif (preg_match('/compare fractions/', $context)) {
            $visual = $this->render_fraction_bar_visual([[3,8],[5,8]], 'Compare equal-size parts', 'Both bars use eighths, so compare how many eighths are shaded.');
        } elseif (preg_match('/add and subtract fractions with unlike/', $context)) {
            $visual = $this->render_fraction_bar_visual([[2,3],[1,4]], 'Rename with a common unit', 'Equivalent twelfths let us combine equal-size parts.');
        } elseif (preg_match('/add and subtract fractions/', $context)) {
            $visual = $this->render_fraction_bar_visual([[2,8],[5,8]], 'Combine like fractional units', 'Both amounts use eighths, so the pieces are the same size.');
        } elseif (preg_match('/multiply fractions by whole/', $context)) {
            $visual = $this->render_fraction_bar_visual([[2,5],[2,5],[2,5]], 'See three equal groups of 2/5', 'Repeated groups show 3 × 2/5.');
        } elseif (preg_match('/multiply fractions/', $context)) {
            $visual = $this->render_fraction_bar_visual([[2,3],[3,4]], 'Picture a fraction of a fraction', 'Use overlapping parts to see the product.');
        } elseif (preg_match('/divide unit fractions/', $context)) {
            $visual = $this->render_fraction_bar_visual([[1,4],[1,4],[1,4],[1,4]], 'Count unit-fraction pieces', 'Four one-fourth pieces fit in one whole.');
        } elseif (preg_match('/decimal notation and comparison/', $context)) {
            $visual = $this->render_fraction_bar_visual([[37,100]], 'Connect hundredths to decimals', 'Thirty-seven hundredths is written 0.37.');
        } elseif (preg_match('/teen numbers/', $context)) {
            $visual = $this->render_early_math_visual('count', [14], 'See one ten and four ones', 'A full group of ten and 4 more ones make 14.', false, 'block');
        } elseif (preg_match('/compare (?:two|three)-digit/', $context)) {
            $visual = $this->render_shared_concept_visual('place-value', 'Compare from the greatest place', 'Compare hundreds first, then tens, then ones.', 'A place-value chart used to compare two numbers');
        } elseif (preg_match('/rounding/', $context)) {
            $visual = $this->render_shared_concept_visual('number-line', 'Find the nearest benchmark', 'Place the number between two benchmarks and use the halfway point.', 'A number line used to round a number to its nearest benchmark');
        } elseif (preg_match('/multi-digit multiplication/', $context)) {
            $visual = $this->render_operations_visual('array', [3, 4], 'Break apart an area model', 'Partial products show how place-value parts combine.', 'block');
        } elseif (preg_match('/division with remainders|multi-digit multiplication and division/', $context)) {
            $visual = $this->render_operations_visual('share', [12, 3], 'Use equal groups and partial quotients', 'Account for the whole amount, then interpret anything left.', 'block');
        } elseif (preg_match('/decimal operations/', $context)) {
            $visual = $this->render_place_value_chart_visual('6.18', 'Line up equal decimal places', 'The completed chart shows 6 ones, 1 tenth, and 8 hundredths.');
        } elseif (preg_match('/place value to 1,000/', $context)) {
            $visual = $this->render_place_value_chart_visual('4326', 'Read a four-digit number by place value', 'The completed model shows 4 thousands, 3 hundreds, 2 tens, and 6 ones.');
        } elseif (preg_match('/counting and place value to 120/', $context)) {
            $visual = $this->render_place_value_chart_visual('47', 'Build a number with tens and ones', 'The completed model shows 4 tens and 7 ones, so the number is 47.');
        } elseif (preg_match('/place value with decimals/', $context)) {
            $visual = $this->render_place_value_chart_visual('4.372', 'Read each digit by its place', 'The completed chart shows how the value of each digit depends on its position.');
        } elseif (preg_match('/place value and rounding/', $context)) {
            $visual = $this->render_place_value_chart_visual('347', 'Read the number before rounding', 'The completed chart shows 3 hundreds, 4 tens, and 7 ones.');
        } elseif (preg_match('/add within 100/', $context)) {
            $visual = $this->render_place_value_chart_visual('47', 'Keep tens and ones aligned', 'The completed chart models 4 tens and 7 ones.');
        } elseif (preg_match('/add and subtract within 1,000/', $context)) {
            $visual = $this->render_place_value_chart_visual('625', 'Regroup by place value', 'The completed chart shows the sum: 6 hundreds, 2 tens, and 5 ones.');
        } elseif (preg_match('/multi-digit addition and subtraction/', $context)) {
            $visual = $this->render_place_value_chart_visual('43545', 'Keep equal places aligned', 'The completed model shows the value of every digit in 43,545.');
        }

        if ($visual !== '') {
            return '<div class="mb-learn-concept-model"><span class="mb-model-label">Visual Model</span>' . $visual . '</div>';
        }

        // Route Geometry lessons to a concept-specific model before the broad
        // arithmetic keyword fallbacks below can claim words such as "area",
        // "equal", or "number" from the lesson description.
        if (preg_match('/circle|radius|diameter|circumference/', $context)) {
            $visual = $this->render_shared_concept_visual('circle', 'See the parts of a circle', 'Use the center, radius, and diameter to organize the given information.', 'A circle with its center, radius, and diameter marked');
        } elseif (preg_match('/coordinate plane|ordered pair|scale drawing/', $context)) {
            $visual = $this->render_shared_concept_visual('graph', 'See position and scale', 'Use coordinates or a scale factor to connect the diagram to its measurements.', 'A coordinate plane with labeled points and a geometric relationship');
        } elseif (preg_match('/transform|translation|reflection|rotation|dilation|congruen|similar/', $context)) {
            $visual = $this->render_shared_concept_visual('transformation', 'See how the figure changes', 'Track each point as the figure moves, turns, flips, or changes size.', 'A figure and its transformed image on a coordinate grid');
        } elseif (preg_match('/shape|geometry|geometric|triangle|quadrilateral|polygon|angle|symmetr|parallel|perpendicular|two-dimensional|three-dimensional|surface area|pythagorean|distance/', $context)) {
            $visual = $this->render_shared_concept_visual('geometry', 'Read the geometric figure', 'Use the labels and markings to identify what is known and what must be found.', 'A labeled geometric figure with sides, height, angle, and an unknown measurement');
        }

        if ($visual !== '') {
            return '<div class="mb-learn-concept-model"><span class="mb-model-label">Visual Model</span>' . $visual . '</div>';
        }

        if (preg_match('/place value|base ten|decimal place/', $context)) {
            return '<div class="mb-place-value-model" aria-label="Place value model"><span class="mb-model-label">Visual Model</span><div class="mb-place-value-number"><span data-place="thousands">4<small>Thousands</small></span><span data-place="hundreds">3<small>Hundreds</small></span><span data-place="tens">2<small>Tens</small></span><span data-place="ones">7<small>Ones</small></span></div><p>4,327 = 4,000 + 300 + 20 + 7</p></div>';
        }
        if (preg_match('/compose|decompose|part.*whole|take.*apart/', $context)) {
            $visual = $this->render_apple_basket_visual(7, 5, 'See the parts and the whole', 'Seven apples can be separated into two parts.');
        } elseif (preg_match('/subtraction|subtract|take away|difference|remain/', $context)) {
            $visual = $this->render_operations_visual('separate', [8, 3], 'See a group change', 'Start with the whole group, then show the part that leaves.', 'object');
        } elseif (preg_match('/addition|add|join|altogether|sum/', $context)) {
            $visual = $this->render_operations_visual('join', [4, 3], 'See two groups join', 'Put the two parts together to find the whole.', 'object');
        } elseif (preg_match('/division|divide|equal shares|share equally/', $context)) {
            $visual = $this->render_operations_visual('share', [12, 3], 'See equal shares', 'Each group receives the same number of objects.', 'object');
        } elseif (preg_match('/multiplication|multiply|equal groups|arrays?|factors?/', $context)) {
            $visual = $this->render_operations_visual('array', [3, 4], 'See equal rows and columns', 'An array organizes equal groups so they are easier to count.', 'object');
        } elseif (preg_match('/unknown|missing number|equations?/', $context)) {
            $visual = $this->render_operations_visual('unknown', [], 'See what is unknown', 'The two sides of an equation must have the same value.');
        } elseif (preg_match('/patterns?|sequence|rule/', $context)) {
            $visual = $this->render_operations_visual('pattern', [3, 7, 11, 15], 'See the pattern grow', 'Use the same rule to move from one term to the next.');
        } elseif (preg_match('/numerical expression|order of operations|parentheses/', $context)) {
            $visual = $this->render_shared_concept_visual('equation', 'See how an expression is organized', 'Grouping symbols show which operation to complete first.', 'A balanced mathematical model for an expression');
        } else {
            $visual = $this->render_shared_concept_visual('organizer', 'See the lesson idea', 'Connect what you know to a model, rule, and solution.', 'A visual organizer for the lesson concept');
        }

        return '<div class="mb-learn-concept-model"><span class="mb-model-label">Visual Model</span>' . $visual . '</div>';
    }

    private function render_fraction_bar_visual($fractions, $heading, $caption) {
        if (!is_array($fractions) || !$fractions) $fractions = [[1,2]];
        $bars = '';
        $y = 28;
        foreach (array_slice($fractions, 0, 4) as $fraction) {
            $numerator = max(0, (int) ($fraction[0] ?? 0));
            $denominator = max(1, min(100, (int) ($fraction[1] ?? 1)));
            $visible_parts = min($denominator, 20);
            $width = 360 / $visible_parts;
            for ($i = 0; $i < $visible_parts; $i++) {
                $filled = $denominator <= 20 ? $i < $numerator : $i < (int) round(($numerator / $denominator) * $visible_parts);
                $bars .= '<rect x="' . esc_attr(45 + ($i * $width)) . '" y="' . esc_attr($y) . '" width="' . esc_attr($width) . '" height="42" fill="' . ($filled ? '#c6ecea' : '#ffffff') . '" stroke="#087f83" stroke-width="2"/>';
            }
            $bars .= '<text x="225" y="' . esc_attr($y + 67) . '" text-anchor="middle" class="mb-example-svg-measure">' . esc_html($numerator . '/' . $denominator) . '</text>';
            $y += 86;
        }
        $height = max(125, $y + 5);
        return '<figure class="mb-practice-problem-visual mb-fraction-visual"><div><strong>' . esc_html($heading) . '</strong><span>' . esc_html($caption) . '</span></div>'
            . '<svg viewBox="0 0 450 ' . esc_attr($height) . '" role="img" aria-label="Fraction bar model showing ' . esc_attr(implode(', ', array_map(function($f){ return ((int) $f[0]) . '/' . ((int) $f[1]); }, $fractions))) . '">' . $bars . '</svg></figure>';
    }

    private function render_measurement_visual($kind, $values = [], $heading = '', $caption = '') {
        $svg = '';
        $aria = $heading;
        if ($kind === 'length') {
            $ticks = '';
            for ($i = 0; $i <= 10; $i++) $ticks .= '<line x1="' . (55 + $i * 34) . '" y1="92" x2="' . (55 + $i * 34) . '" y2="' . ($i % 5 ? 116 : 126) . '"/><text x="' . (55 + $i * 34) . '" y="148" text-anchor="middle">' . $i . '</text>';
            $svg = '<line x1="55" y1="92" x2="395" y2="92"/>' . $ticks . '<line class="accent" x1="89" y1="58" x2="327" y2="58"/><circle class="accent-fill" cx="89" cy="58" r="6"/><circle class="accent-fill" cx="327" cy="58" r="6"/><text x="208" y="43" text-anchor="middle">7 equal units</text>';
        } elseif ($kind === 'classify') {
            $svg = '<rect x="28" y="24" width="185" height="135" rx="14"/><rect x="237" y="24" width="185" height="135" rx="14"/><text x="120" y="48" text-anchor="middle">Shapes with curves</text><text x="330" y="48" text-anchor="middle">Shapes with straight sides</text><circle class="soft-fill" cx="85" cy="95" r="25"/><circle class="accent-fill" cx="150" cy="105" r="18"/><polygon class="soft-fill" points="285,125 315,70 345,125"/><rect class="accent-fill" x="362" y="76" width="42" height="42"/>';
        } elseif ($kind === 'clock') {
            $svg = '<circle cx="225" cy="91" r="73"/><text x="225" y="35" text-anchor="middle">12</text><text x="282" y="97">3</text><text x="225" y="158" text-anchor="middle">6</text><text x="162" y="97">9</text><line class="accent" x1="225" y1="91" x2="225" y2="43"/><line class="accent" x1="225" y1="91" x2="268" y2="91"/><circle class="accent-fill" cx="225" cy="91" r="6"/><text x="225" y="185" text-anchor="middle">3:00</text>';
        } elseif ($kind === 'money') {
            $svg = '<circle class="soft-fill" cx="82" cy="90" r="48"/><circle class="accent-fill" cx="190" cy="90" r="40"/><circle class="soft-fill" cx="290" cy="90" r="34"/><circle class="accent-fill" cx="380" cy="90" r="30"/><text x="82" y="98" text-anchor="middle">25¢</text><text x="190" y="98" text-anchor="middle">10¢</text><text x="290" y="98" text-anchor="middle">5¢</text><text x="380" y="98" text-anchor="middle">1¢</text>';
        } elseif ($kind === 'bar-graph') {
            $svg = '<line x1="60" y1="25" x2="60" y2="160"/><line x1="60" y1="160" x2="415" y2="160"/><rect class="soft-fill" x="105" y="85" width="65" height="75"/><rect class="accent-fill" x="210" y="45" width="65" height="115"/><rect class="soft-fill" x="315" y="105" width="65" height="55"/><text x="137" y="184" text-anchor="middle">A</text><text x="242" y="184" text-anchor="middle">B</text><text x="347" y="184" text-anchor="middle">C</text>';
        } elseif ($kind === 'line-plot') {
            $svg = '<line x1="50" y1="140" x2="410" y2="140"/>';
            for ($i=0;$i<6;$i++) $svg .= '<line x1="' . (70+$i*64) . '" y1="132" x2="' . (70+$i*64) . '" y2="148"/><text x="' . (70+$i*64) . '" y="170" text-anchor="middle">' . ($i+1) . '</text>';
            $svg .= '<text class="accent-text" x="130" y="120" text-anchor="middle">X</text><text class="accent-text" x="194" y="120" text-anchor="middle">X</text><text class="accent-text" x="194" y="94" text-anchor="middle">X</text><text class="accent-text" x="258" y="120" text-anchor="middle">X</text><text class="accent-text" x="322" y="120" text-anchor="middle">X</text><text class="accent-text" x="322" y="94" text-anchor="middle">X</text>';
        } elseif ($kind === 'mass-volume') {
            $svg = '<line x1="125" y1="55" x2="325" y2="55"/><line x1="225" y1="55" x2="225" y2="150"/><polygon class="soft-fill" points="75,95 175,95 155,145 95,145"/><polygon class="accent-fill" points="275,95 375,95 355,145 295,145"/><rect x="345" y="35" width="65" height="135" rx="8"/><path class="soft-fill" d="M347 105 H408 V168 H347 Z"/><text x="377" y="92" text-anchor="middle">mL</text>';
        } elseif ($kind === 'area-perimeter') {
            for ($r=0;$r<3;$r++) for ($c=0;$c<5;$c++) $svg .= '<rect class="' . (($r+$c)%2 ? 'soft-fill' : 'accent-fill') . '" x="' . (105+$c*50) . '" y="' . (25+$r*45) . '" width="50" height="45"/>';
            $svg .= '<text x="230" y="181" text-anchor="middle">5 units</text><text x="78" y="98" text-anchor="middle" transform="rotate(-90 78 98)">3 units</text>';
        } elseif ($kind === 'conversion') {
            $svg = '<rect class="soft-fill" x="40" y="50" width="140" height="90" rx="15"/><rect class="accent-fill" x="270" y="50" width="140" height="90" rx="15"/><text x="110" y="88" text-anchor="middle">1 meter</text><text x="110" y="116" text-anchor="middle">1 m</text><text x="340" y="88" text-anchor="middle">100 centimeters</text><text x="340" y="116" text-anchor="middle">100 cm</text><path class="accent" d="M188 95 H255 M238 78 L255 95 L238 112"/>';
        } elseif ($kind === 'volume') {
            $svg = '<polygon class="soft-fill" points="105,72 285,72 350,35 170,35"/><polygon class="accent-fill" points="285,72 350,35 350,130 285,167"/><rect x="105" y="72" width="180" height="95"/><line x1="105" y1="72" x2="170" y2="35"/><line x1="105" y1="167" x2="170" y2="130"/><line x1="170" y1="35" x2="170" y2="130"/><line x1="170" y1="130" x2="350" y2="130"/><text x="195" y="190" text-anchor="middle">length</text><text x="338" y="178">width</text><text x="82" y="122" text-anchor="middle" transform="rotate(-90 82 122)">height</text>';
        }
        return '<figure class="mb-practice-problem-visual mb-measurement-visual"><div><strong>' . esc_html($heading) . '</strong><span>' . esc_html($caption) . '</span></div><svg viewBox="0 0 450 205" role="img" aria-label="' . esc_attr($aria) . '"><g fill="none" stroke="#087f83" stroke-width="3" stroke-linecap="round" stroke-linejoin="round">' . $svg . '</g></svg></figure>';
    }

    private function render_safe_practice_problem_visual($question, $answer, $mode = 'guided', $lesson_title = '') {
        $visual = $this->render_practice_problem_visual($question, $mode, $lesson_title);
        $answer_text = strtolower(preg_replace('/[^\p{L}\p{N}.:+\-\/]+/u', '', html_entity_decode(wp_strip_all_tags((string) $answer), ENT_QUOTES, 'UTF-8')));
        $visual_text = strtolower(preg_replace('/[^\p{L}\p{N}.:+\-\/]+/u', '', html_entity_decode(wp_strip_all_tags((string) $visual), ENT_QUOTES, 'UTF-8')));
        if ($answer_text !== '' && strpos($visual_text, $answer_text) !== false) {
            return $this->render_shared_concept_visual('organizer', 'Plan your solution', 'Record what you know, choose a model or rule, and solve without revealing the final answer.', 'A blank problem-solving organizer with spaces for known information, a model or rule, and the unknown');
        }
        return $visual;
    }

    private function render_practice_problem_visual($question, $mode = 'guided', $lesson_title = '') {
        $plain_question = str_replace(['−', '–'], '-', (string) $question);
        $question_lower = strtolower($plain_question);
        $context = strtolower($plain_question . ' ' . (string) $lesson_title);
        $prompt = $mode === 'guided'
            ? 'Use the picture as you work through each step.'
            : 'Use the picture to organize what you know before you solve.';

        if (preg_match('/there (?:are|is)\s+(\d{1,2})\s+apples?.*put\s+(\d{1,2})\s+in\s+one\s+basket/i', $plain_question, $basket_match)) {
            return $this->render_apple_basket_visual((int) $basket_match[1], (int) $basket_match[2], 'Picture the apples and baskets', $prompt);
        }

        if (preg_match('/mass|grams?|kilograms?|liters?|milliliters?|liquid/', $context)) return $this->render_measurement_visual('mass-volume', [], 'Picture the measurement', $prompt);
        if (preg_match('/cubic|unit cubes?|rectangular prism|\bvolume\b/', $context)) return $this->render_measurement_visual('volume', [], 'Picture the solid', $prompt);
        if (preg_match('/area|perimeter/', $context)) return $this->render_measurement_visual('area-perimeter', [], 'Picture the area and perimeter', $prompt);
        if (preg_match('/money|coins?|quarters?|dimes?|nickels?|pennies|cents?/', $context)) return $this->render_measurement_visual('money', [], 'Picture the coins', $prompt);
        if (preg_match('/time|clock|hour|half-hour|minutes?/', $context)) return $this->render_measurement_visual('clock', [], 'Picture the time', $prompt);
        if (preg_match('/line plot/', $context)) return $this->render_measurement_visual('line-plot', [], 'Picture the line plot', $prompt);
        if (preg_match('/bar graph|picture graph|scaled graph|chart|data|votes?|survey/', $context)) return $this->render_measurement_visual('bar-graph', [], 'Picture the data', $prompt);
        if (preg_match('/convert|conversion|meters?|centimeters?|yards?|feet|inches?/', $context)) return $this->render_measurement_visual('conversion', [], 'Picture equivalent units', $prompt);
        if (preg_match('/measure|length|long|tall|ruler|ribbon|pencil/', $context)) return $this->render_measurement_visual('length', [], 'Picture the length', $prompt);
        if (preg_match('/sort|classify|category|attribute/', $context)) return $this->render_measurement_visual('classify', [], 'Picture the groups', $prompt);

        if (preg_match_all('/\b(\d+)\s*\/\s*(\d+)\b/', $plain_question, $fraction_matches, PREG_SET_ORDER)) {
            $fractions = [];
            foreach (array_slice($fraction_matches, 0, 3) as $match) {
                $fractions[] = [(int) $match[1], max(1, (int) $match[2])];
            }
            return $this->render_fraction_bar_visual($fractions, 'Picture the fractional parts', $prompt);
        }

        // Student practice uses unfinished charts. Completed models remain in
        // instructional examples, never in student response areas.
        if (preg_match('/show\s+(\d{1,6}(?:\.\d+)?)\s+as\s+/i', $plain_question, $place_number)) {
            return $this->render_practice_place_value_visual($place_number[1], $prompt);
        }
        if (preg_match('/build\s+(\d{1,6}(?:\.\d+)?)/i', $plain_question, $place_number)) {
            return $this->render_practice_place_value_visual($place_number[1], $prompt);
        }
        if (preg_match('/write\s+(\d{1,6}(?:\.\d+)?)\s+in\s+(?:expanded|word|standard)\s+form/i', $plain_question, $place_number)) {
            return $this->render_practice_place_value_visual($place_number[1], $prompt);
        }
        if (preg_match('/(\d+)\s+hundreds?\s*,?\s*(\d+)\s+tens?\s*,?\s*(?:and\s+)?(\d+)\s+ones?/i', $plain_question, $place_parts)) {
            return $this->render_practice_place_value_visual('', $prompt);
        }
        if (preg_match('/(\d+)\s+tens?\s+(?:and\s+)?(\d+)\s+ones?/i', $plain_question, $place_parts)) {
            return $this->render_practice_place_value_visual('', $prompt, ['tens', 'ones']);
        }

        if (preg_match('/equal groups|rows? of|columns? of|array|groups? (?:with|of)|multiply|multiplication|factor pairs?/', $context)) {
            preg_match_all('/\b\d{1,2}\b/', $plain_question, $numbers);
            return $this->render_operations_visual('array', array_map('intval', array_slice($numbers[0], 0, 2)), 'Picture the equal groups', $prompt, $this->detect_early_math_object($plain_question));
        }
        if (preg_match('/share|shared|divide|division|each (?:child|student|table|group)|quotient/', $context)) {
            preg_match_all('/\b\d{1,2}\b/', $plain_question, $numbers);
            return $this->render_operations_visual('share', array_map('intval', array_slice($numbers[0], 0, 2)), 'Picture the equal shares', $prompt, $this->detect_early_math_object($plain_question));
        }
        if (preg_match('/add|addition|altogether|in all|more join|gets? \d+ more|sum|plus/', $context)) {
            preg_match_all('/\b\d{1,2}\b/', $plain_question, $numbers);
            return $this->render_operations_visual('join', array_map('intval', array_slice($numbers[0], 0, 2)), 'Picture the groups joining', $prompt, $this->detect_early_math_object($plain_question));
        }
        if (preg_match('/subtract|subtraction|take away|fly away|swim away|remain|are left|difference|fewer/', $context)) {
            preg_match_all('/\b\d{1,2}\b/', $plain_question, $numbers);
            return $this->render_operations_visual('separate', array_map('intval', array_slice($numbers[0], 0, 2)), 'Picture what changes', $prompt, $this->detect_early_math_object($plain_question));
        }
        if (preg_match('/unknown|missing (?:number|addend|factor)|□|\bbox\b/', $context)) {
            return $this->render_operations_visual('unknown', [], 'Picture the unknown', $prompt);
        }
        if (preg_match('/pattern|sequence|rule|next three terms/', $context)) {
            preg_match_all('/\b\d{1,3}\b/', $plain_question, $numbers);
            return $this->render_operations_visual('pattern', array_map('intval', array_slice($numbers[0], 0, 5)), 'Picture the pattern', $prompt);
        }

        if (preg_match('/compare|greater than|less than|equal to|which (?:number|group)/', $context)) {
            preg_match_all('/\b(?:[0-9]|1[0-9]|20)\b/', $plain_question, $numbers);
            $values = array_values(array_map('intval', array_slice($numbers[0], 0, 2)));
            return $this->render_early_math_visual('compare', $values ?: [4, 6], 'Picture both groups', $prompt, false, $this->detect_early_math_object($plain_question));
        }
        if (preg_match('/count forward|comes (?:next|after)|missing number|count by tens|counting to 100/', $context)) {
            preg_match_all('/\b\d{1,3}\b/', $plain_question, $numbers);
            $values = array_values(array_map('intval', array_slice($numbers[0], 0, 5)));
            return $this->render_early_math_visual('sequence', $values, 'Picture the counting pattern', $prompt, false);
        }
        if (preg_match('/count|how many|group of|counting to 20/', $context)) {
            $count = 8;
            if (preg_match('/(?:count|group of|how many)[^0-9]*(\d{1,2})/i', $plain_question, $count_match)) $count = (int) $count_match[1];
            return $this->render_early_math_visual('count', [$count], 'Picture what you are counting', $prompt, false, $this->detect_early_math_object($plain_question));
        }

        if (preg_match('/between\s+\((-?\d+)\s*,\s*(-?\d+)\)\s+and\s+\((-?\d+)\s*,\s*(-?\d+)\)/i', $plain_question, $points)) {
            $x1 = (int) $points[1]; $y1 = (int) $points[2]; $x2 = (int) $points[3]; $y2 = (int) $points[4];
            $dx = abs($x2 - $x1); $dy = abs($y2 - $y1);
            $aria = 'Coordinate distance diagram from point ' . $x1 . ', ' . $y1 . ' to point ' . $x2 . ', ' . $y2 . ', showing a horizontal change of ' . $dx . ' and a vertical change of ' . $dy;
            return '<figure class="mb-practice-problem-visual">'
                . '<div><strong>Picture the distance</strong><span>' . esc_html($prompt) . '</span></div>'
                . '<svg viewBox="0 0 440 225" role="img" aria-label="' . esc_attr($aria) . '">'
                . '<defs><pattern id="mb-grid-' . esc_attr(md5($question)) . '" width="25" height="25" patternUnits="userSpaceOnUse"><path d="M25 0H0V25" fill="none" stroke="#dbe8ea" stroke-width="1"/></pattern></defs>'
                . '<rect x="20" y="15" width="400" height="185" rx="10" fill="url(#mb-grid-' . esc_attr(md5($question)) . ')" stroke="#b9d9d8"/>'
                . '<path d="M85 170 H350 V48" fill="none" stroke="#6d28d9" stroke-width="4" stroke-dasharray="8 6"/>'
                . '<path d="M85 170 L350 48" fill="none" stroke="#087f83" stroke-width="5"/>'
                . '<circle cx="85" cy="170" r="7" fill="#6d28d9"/><circle cx="350" cy="48" r="7" fill="#6d28d9"/>'
                . '<text x="85" y="195" text-anchor="middle" class="mb-example-svg-measure">A (' . esc_html($x1) . ',' . esc_html($y1) . ')</text>'
                . '<text x="350" y="31" text-anchor="middle" class="mb-example-svg-measure">B (' . esc_html($x2) . ',' . esc_html($y2) . ')</text>'
                . '<text x="218" y="191" text-anchor="middle" class="mb-example-svg-label">horizontal change = ' . esc_html($dx) . '</text>'
                . '<text x="379" y="109" text-anchor="middle" transform="rotate(-90 379 109)" class="mb-example-svg-label">vertical change = ' . esc_html($dy) . '</text>'
                . '<rect x="184" y="80" width="142" height="34" rx="17" class="mb-example-svg-label-bg"/>'
                . '<text x="255" y="103" text-anchor="middle" class="mb-example-svg-unknown">distance = ?</text>'
                . '</svg></figure>';
        }

        if (preg_match('/rectangle\s+is\s+(\d+)\s+units\s+wide\s+and\s+(\d+)\s+units\s+long/i', $plain_question, $rectangle)) {
            return '<figure class="mb-practice-problem-visual">'
                . '<div><strong>Picture the rectangle</strong><span>' . esc_html($prompt) . '</span></div>'
                . '<svg viewBox="0 0 440 225" role="img" aria-label="A rectangle ' . esc_attr($rectangle[1]) . ' units wide and ' . esc_attr($rectangle[2]) . ' units long with an unknown diagonal">'
                . '<rect x="70" y="42" width="300" height="140" rx="3" fill="#eef9f8" stroke="#087f83" stroke-width="5"/>'
                . '<path d="M70 182 L370 42" stroke="#6d28d9" stroke-width="5"/>'
                . '<text x="220" y="212" text-anchor="middle" class="mb-example-svg-measure">' . esc_html($rectangle[2]) . ' units long</text>'
                . '<text x="45" y="112" text-anchor="middle" transform="rotate(-90 45 112)" class="mb-example-svg-measure">' . esc_html($rectangle[1]) . ' units wide</text>'
                . '<rect x="225" y="68" width="116" height="34" rx="17" class="mb-example-svg-label-bg"/>'
                . '<text x="283" y="92" text-anchor="middle" class="mb-example-svg-unknown">diagonal = ?</text>'
                . '</svg></figure>';
        }

        if (preg_match('/side lengths\s+(\d+)\s*,\s*(\d+)\s*,\s*and\s*(\d+)/i', $plain_question, $sides)) {
            return '<figure class="mb-practice-problem-visual">'
                . '<div><strong>Picture the three sides</strong><span>Use the longest side as the possible hypotenuse. Then test the Pythagorean Theorem.</span></div>'
                . '<svg viewBox="0 0 440 225" role="img" aria-label="A triangle with side lengths ' . esc_attr($sides[1]) . ', ' . esc_attr($sides[2]) . ', and ' . esc_attr($sides[3]) . ', asking whether it is a right triangle">'
                . '<polygon points="75,185 75,52 375,185" fill="#eef9f8" stroke="#087f83" stroke-width="5" stroke-linejoin="round"/>'
                . '<path d="M75 158 H102 V185" fill="none" stroke="#6d28d9" stroke-width="4" stroke-dasharray="5 4"/>'
                . '<text x="47" y="119" text-anchor="middle" transform="rotate(-90 47 119)" class="mb-example-svg-measure">' . esc_html($sides[1]) . '</text><text x="225" y="214" text-anchor="middle" class="mb-example-svg-measure">' . esc_html($sides[2]) . '</text><text x="243" y="105" text-anchor="middle" transform="rotate(24 243 105)" class="mb-example-svg-measure">' . esc_html($sides[3]) . '</text>'
                . '<rect x="125" y="12" width="190" height="34" rx="17" class="mb-example-svg-label-bg"/><text x="220" y="36" text-anchor="middle" class="mb-example-svg-unknown">Does a²+b²=c²?</text>'
                . '</svg></figure>';
        }

        if (preg_match('/hypotenuse\s+of\s+(\d+)\s+units\s+and\s+one\s+leg\s+of\s+(\d+)/i', $plain_question, $missing_leg)) {
            return $this->render_right_triangle_problem_visual($missing_leg[2] . ' units', 'b = ?', $missing_leg[1] . ' units', $prompt, 'A right triangle with a hypotenuse of ' . $missing_leg[1] . ' units, one leg of ' . $missing_leg[2] . ' units, and the other leg unknown');
        }

        if (preg_match('/legs?\s+(?:of\s+)?(\d+)\s+units?\s+and\s+(\d+)\s+units?/i', $plain_question, $legs)) {
            return $this->render_right_triangle_problem_visual($legs[1] . ' units', $legs[2] . ' units', 'c = ?', $prompt, 'A right triangle with legs of ' . $legs[1] . ' units and ' . $legs[2] . ' units and an unknown hypotenuse');
        }

        if (strpos($question_lower, 'right triangle') !== false || strpos($question_lower, 'hypotenuse') !== false || strpos($question_lower, 'diagonal') !== false) {
            return $this->render_right_triangle_problem_visual('leg a', 'leg b', 'c = ?', $prompt, 'A right triangle with two legs and an unknown hypotenuse');
        }

        if (preg_match('/place value|decimal place|digit|powers? of ten|expanded form|standard form|word form/', $context)) {
            if (preg_match('/\b\d{1,6}(?:\.\d+)?\b/', $plain_question, $place_number)) {
                return $this->render_practice_place_value_visual($place_number[0], $prompt);
            }
            return $this->render_practice_place_value_visual('', $prompt);
        }
        if (preg_match('/fraction|ratio|rate|proportion|percent/', $context)) {
            return $this->render_shared_concept_visual('fraction-ratio', 'Picture the parts and the whole', $prompt, 'A bar model divided into equal parts with some parts shaded');
        }
        if (preg_match('/probability|chance|spinner|coin|die|dice|outcome|sample space/', $context)) {
            return $this->render_shared_concept_visual('probability', 'Picture the possible outcomes', $prompt, 'A four-section spinner showing equally sized possible outcomes');
        }
        if (preg_match('/mean|median|mode|range|data|statistic|distribution|scatter|box plot|dot plot|histogram/', $context)) {
            return $this->render_shared_concept_visual('data', 'Picture the data', $prompt, 'A dot plot showing values arranged along a number line');
        }
        if (preg_match('/function|slope|graph|coordinate|linear|quadratic|exponential/', $context)) {
            return $this->render_shared_concept_visual('graph', 'Picture the relationship', $prompt, 'A coordinate plane with points following an increasing line');
        }
        if (preg_match('/inequal|integer|absolute value|number line|positive|negative/', $context)) {
            return $this->render_shared_concept_visual('number-line', 'Picture the number line', $prompt, 'A number line showing negative numbers, zero, and positive numbers');
        }
        if (preg_match('/equation|expression|variable|order of operations|combine like|distributive|algebra/', $context)) {
            return $this->render_shared_concept_visual('equation', 'Picture both sides of the equation', $prompt, 'A balanced scale representing two equal expressions');
        }
        if (preg_match('/volume|prism|pyramid|cylinder|cone|sphere|three-dimensional|3d/', $context)) {
            return $this->render_shared_concept_visual('volume', 'Picture the solid', $prompt, 'A three-dimensional rectangular prism with length, width, and height marked');
        }
        if (preg_match('/circle|radius|diameter|circumference/', $context)) {
            return $this->render_shared_concept_visual('circle', 'Picture the circle', $prompt, 'A circle with its center, radius, and diameter marked');
        }
        if (preg_match('/transform|rotation|reflection|translation|dilation|congruen|similar/', $context)) {
            return $this->render_shared_concept_visual('transformation', 'Picture the change', $prompt, 'A shape and its translated image on a coordinate grid');
        }
        if (preg_match('/angle|triangle|polygon|geometry|area|perimeter|surface area/', $context)) {
            return $this->render_shared_concept_visual('geometry', 'Picture the figure', $prompt, 'A geometric figure with side lengths, an angle, and an unknown measurement marked');
        }
        if (preg_match('/complex number|imaginary|number system|rational|irrational/', $context)) {
            return $this->render_shared_concept_visual('number-system', 'Picture the number system', $prompt, 'Nested groups showing how natural, whole, integer, rational, real, and complex numbers are related');
        }
        return $this->render_shared_concept_visual('organizer', 'Picture your plan', $prompt, 'A visual organizer moving from what is known to the rule or model and then to what must be found');
    }

    private function place_value_explanation($number) {
        $normalized = preg_replace('/[^0-9.]/', '', (string) $number);
        if ($normalized === '' || substr_count($normalized, '.') > 1) {
            $normalized = '326';
        }
        $parts = explode('.', $normalized, 2);
        $integer = ltrim($parts[0], '0');
        $integer = $integer === '' ? '0' : $integer;
        $decimal = isset($parts[1]) ? $parts[1] : '';
        $integer_names = ['ones', 'tens', 'hundreds', 'thousands', 'ten-thousands', 'hundred-thousands'];
        $decimal_names = ['tenths', 'hundredths', 'thousandths'];
        $descriptions = [];
        $reversed = strrev($integer);
        for ($i = strlen($reversed) - 1; $i >= 0; $i--) {
            if (isset($integer_names[$i])) {
                $descriptions[] = $reversed[$i] . ' ' . $integer_names[$i];
            }
        }
        for ($i = 0; $i < strlen($decimal) && $i < count($decimal_names); $i++) {
            $descriptions[] = $decimal[$i] . ' ' . $decimal_names[$i];
        }
        return 'The completed model shows ' . implode(', ', $descriptions) . '. Together, the digits make ' . $normalized . '.';
    }

    private function render_practice_place_value_visual($given_number = '', $prompt = '', $labels = ['hundreds', 'tens', 'ones']) {
        $labels = array_values(array_filter(array_map('sanitize_text_field', (array) $labels)));
        if (!$labels) $labels = ['hundreds', 'tens', 'ones'];
        $given_number = preg_replace('/[^0-9.]/', '', (string) $given_number);
        $heading = $given_number !== '' ? 'Organize ' . $given_number . ' by place value' : 'Build the number by place value';
        $cells = '';
        foreach ($labels as $label) {
            $cells .= '<label class="mb-place-value-entry"><strong>' . esc_html($label) . '</strong><input type="text" inputmode="decimal" maxlength="3" autocomplete="off" aria-label="Enter the ' . esc_attr($label) . ' value"></label>';
        }
        return '<figure class="mb-practice-problem-visual mb-practice-visual-place-value"><div><strong>' . esc_html($heading) . '</strong><span>' . esc_html($prompt ?: 'Fill in the chart, then solve.') . '</span></div><div class="mb-place-value-entry-grid" style="--mb-place-columns:' . esc_attr(count($labels)) . '" role="group" aria-label="Writable place-value chart">' . $cells . '</div></figure>';
    }

    private function render_place_value_chart_visual($number, $heading, $prompt) {
        $normalized = preg_replace('/[^0-9.]/', '', (string) $number);
        if ($normalized === '' || substr_count($normalized, '.') > 1) {
            $normalized = '326';
        }
        $parts = explode('.', $normalized, 2);
        $integer = ltrim($parts[0], '0');
        $integer = $integer === '' ? '0' : $integer;
        $decimal = isset($parts[1]) ? substr($parts[1], 0, 3) : '';
        $integer_names = ['ones', 'tens', 'hundreds', 'thousands', 'ten-thousands', 'hundred-thousands'];
        $decimal_names = ['tenths', 'hundredths', 'thousandths'];
        $columns = [];
        $integer_length = min(strlen($integer), count($integer_names));
        for ($i = $integer_length - 1; $i >= 0; $i--) {
            $columns[] = ['label' => $integer_names[$i], 'digit' => $integer[$integer_length - 1 - $i]];
        }
        for ($i = 0; $i < strlen($decimal) && $i < count($decimal_names); $i++) {
            $columns[] = ['label' => $decimal_names[$i], 'digit' => $decimal[$i]];
        }
        $count = max(1, count($columns));
        $chart_width = min(390, max(180, $count * 88));
        $start_x = (440 - $chart_width) / 2;
        $column_width = $chart_width / $count;
        $svg = '<rect x="' . esc_attr($start_x) . '" y="38" width="' . esc_attr($chart_width) . '" height="126" rx="10" fill="#fff" stroke="#087f83" stroke-width="3"/>';
        $svg .= '<path d="M' . esc_attr($start_x) . ' 88H' . esc_attr($start_x + $chart_width) . '" stroke="#b9d9d8" stroke-width="2"/>';
        foreach ($columns as $index => $column) {
            $x = $start_x + ($index * $column_width);
            $center = $x + ($column_width / 2);
            if ($index > 0) {
                $svg .= '<path d="M' . esc_attr($x) . ' 38V164" stroke="#b9d9d8" stroke-width="2"/>';
            }
            $svg .= '<text x="' . esc_attr($center) . '" y="69" text-anchor="middle" class="mb-example-svg-label">' . esc_html($column['label']) . '</text>';
            $svg .= '<text x="' . esc_attr($center) . '" y="134" text-anchor="middle" class="mb-example-svg-unknown">' . esc_html($column['digit']) . '</text>';
        }
        $expanded = [];
        $reversed = strrev($integer);
        for ($i = strlen($reversed) - 1; $i >= 0; $i--) {
            $value = ((int) $reversed[$i]) * pow(10, $i);
            if ($value > 0) {
                $expanded[] = number_format($value, 0, '.', ',');
            }
        }
        for ($i = 0; $i < strlen($decimal); $i++) {
            if ((int) $decimal[$i] > 0) {
                $expanded[] = rtrim(rtrim(number_format(((int) $decimal[$i]) / pow(10, $i + 1), $i + 1, '.', ''), '0'), '.');
            }
        }
        $display_number = $decimal === '' ? number_format((int) $integer, 0, '.', ',') : $integer . '.' . $decimal;
        $summary = $display_number . (count($expanded) > 1 ? ' = ' . implode(' + ', $expanded) : '');
        $svg .= '<text x="220" y="205" text-anchor="middle" class="mb-example-svg-measure">' . esc_html($summary) . '</text>';
        return '<figure class="mb-practice-problem-visual mb-practice-visual-place-value"><div><strong>' . esc_html($heading) . '</strong><span>' . esc_html($prompt) . '</span></div><svg viewBox="0 0 440 225" role="img" aria-label="A completed place-value chart for ' . esc_attr($normalized) . '">' . $svg . '</svg></figure>';
    }

    private function render_apple_basket_visual($total, $first_part, $heading, $prompt) {
        $total = max(1, min(12, (int) $total));
        $first_part = max(0, min($total, (int) $first_part));
        $svg = '<path d="M35 142 Q92 117 149 142 L137 201 Q92 218 47 201 Z" fill="#f6dfae" stroke="#a86516" stroke-width="4"/><path d="M53 143 Q92 94 131 143" fill="none" stroke="#a86516" stroke-width="4"/>';
        $svg .= '<path d="M282 142 Q339 117 396 142 L384 201 Q339 218 294 201 Z" fill="#f6dfae" stroke="#a86516" stroke-width="4"/><path d="M300 143 Q339 94 378 143" fill="none" stroke="#a86516" stroke-width="4"/>';
        for ($i = 0; $i < $total; $i++) {
            if ($i < $first_part) {
                $cx = 68 + (($i % 3) * 25); $cy = 151 + (intdiv($i, 3) * 25);
            } else {
                $remaining_index = $i - $first_part;
                $cx = 280 + (($remaining_index % 5) * 29); $cy = 67 + (intdiv($remaining_index, 5) * 34);
            }
            $svg .= $this->render_early_math_marker('apple', $cx, $cy, '#ef5b5b', '#a52b2b');
        }
        $svg .= '<text x="92" y="225" text-anchor="middle" class="mb-example-svg-label">Basket 1 has ' . esc_html($first_part) . '</text><text x="339" y="225" text-anchor="middle" class="mb-example-svg-label">Move the apples left into Basket 2</text>';
        return '<figure class="mb-practice-problem-visual mb-practice-visual-baskets"><div><strong>' . esc_html($heading) . '</strong><span>' . esc_html($prompt) . '</span></div><svg viewBox="0 0 440 240" role="img" aria-label="' . esc_attr($total . ' apples, with ' . $first_part . ' apples in the first basket and the apples left ready to move into the second basket') . '">' . $svg . '</svg></figure>';
    }

    private function render_operations_visual($type, $values, $heading, $prompt, $object = 'object') {
        $a = isset($values[0]) ? max(1, min(12, (int) $values[0])) : 4;
        $b = isset($values[1]) ? max(1, min(12, (int) $values[1])) : 3;
        $svg = '';
        $dot = function($x, $y, $color) use ($object) {
            if ($object !== 'object') {
                $marker = $this->render_early_math_marker($object, $x, $y, $color, '#17394a');
                return '<g transform="translate(' . esc_attr($x) . ' ' . esc_attr($y) . ') scale(.55) translate(' . esc_attr(-$x) . ' ' . esc_attr(-$y) . ')">' . $marker . '</g>';
            }
            return '<circle cx="' . esc_attr($x) . '" cy="' . esc_attr($y) . '" r="10" fill="' . esc_attr($color) . '" stroke="#17394a" stroke-width="2"/>';
        };
        if ($type === 'array') {
            $rows = min(5, $a); $cols = min(6, $b);
            for ($r = 0; $r < $rows; $r++) for ($c = 0; $c < $cols; $c++) $svg .= $dot(145 + $c * 42, 48 + $r * 34, ($r % 2 ? '#e9ddff' : '#ccefed'));
            $svg .= '<text x="220" y="215" text-anchor="middle" class="mb-example-svg-label">' . esc_html($rows) . ' equal rows • ' . esc_html($cols) . ' in each row</text>';
        } elseif ($type === 'share') {
            $groups = min(5, max(2, $b));
            for ($g = 0; $g < $groups; $g++) {
                $x = 38 + $g * 80;
                $svg .= '<rect x="' . esc_attr($x) . '" y="58" width="66" height="104" rx="14" fill="#f8f4ff" stroke="#6d28d9" stroke-width="3"/>';
                for ($n = 0; $n < min(6, (int) ceil($a / $groups)); $n++) $svg .= $dot($x + 20 + ($n % 2) * 27, 85 + intdiv($n, 2) * 27, '#ccefed');
            }
            $svg .= '<text x="220" y="205" text-anchor="middle" class="mb-example-svg-label">share the same amount with each group</text>';
        } elseif ($type === 'join' || $type === 'separate') {
            $left = min(8, $a); $right = min(8, $b);
            for ($i = 0; $i < $left; $i++) $svg .= $dot(55 + ($i % 4) * 33, 65 + intdiv($i, 4) * 36, '#ccefed');
            for ($i = 0; $i < $right; $i++) $svg .= $dot(285 + ($i % 4) * 33, 65 + intdiv($i, 4) * 36, '#e9ddff');
            $symbol = $type === 'join' ? '+' : '−';
            $svg .= '<circle cx="220" cy="83" r="30" fill="#fff" stroke="#6d28d9" stroke-width="3"/><text x="220" y="94" text-anchor="middle" class="mb-example-svg-unknown">' . esc_html($symbol) . '</text>';
            $svg .= '<path d="M70 160H370" stroke="#087f83" stroke-width="4"/><path d="M370 160L350 148M370 160L350 172" stroke="#087f83" stroke-width="4"/><text x="220" y="201" text-anchor="middle" class="mb-example-svg-label">show what joins or leaves, then find the new amount</text>';
        } elseif ($type === 'pattern') {
            $sequence = $values ?: [2, 5, 8, 11];
            foreach (array_slice($sequence, 0, 5) as $i => $number) {
                $x = 28 + $i * 83;
                $svg .= '<rect x="' . esc_attr($x) . '" y="70" width="64" height="58" rx="12" fill="' . ($i % 2 ? '#f8f4ff' : '#eef9f8') . '" stroke="' . ($i % 2 ? '#6d28d9' : '#087f83') . '" stroke-width="3"/><text x="' . esc_attr($x + 32) . '" y="108" text-anchor="middle" class="mb-example-svg-measure">' . esc_html($number) . '</text>';
            }
            $svg .= '<path d="M52 150H390" stroke="#17394a" stroke-width="3" stroke-dasharray="8 7"/><text x="220" y="194" text-anchor="middle" class="mb-example-svg-label">use the same rule from one term to the next</text>';
        } else {
            $svg = '<path d="M220 45V165M120 165H320M155 165L105 205M285 165L335 205" stroke="#17394a" stroke-width="5"/><path d="M65 105H180M260 105H375" stroke="#087f83" stroke-width="5"/><rect x="92" y="65" width="62" height="40" rx="8" fill="#e9ddff" stroke="#6d28d9" stroke-width="3"/><text x="123" y="93" text-anchor="middle" class="mb-example-svg-unknown">?</text><circle cx="295" cy="85" r="18" fill="#ccefed"/><circle cx="342" cy="85" r="18" fill="#ccefed"/><text x="220" y="28" text-anchor="middle" class="mb-example-svg-label">both sides must have the same value</text>';
        }
        return '<figure class="mb-practice-problem-visual mb-practice-visual-operations"><div><strong>' . esc_html($heading) . '</strong><span>' . esc_html($prompt) . '</span></div><svg viewBox="0 0 440 225" role="img" aria-label="A visual model for the operations and algebraic thinking problem">' . $svg . '</svg></figure>';
    }

    private function render_right_triangle_problem_visual($leg_a, $leg_b, $hypotenuse, $prompt, $aria) {
        return '<figure class="mb-practice-problem-visual">'
            . '<div><strong>Picture the triangle</strong><span>' . esc_html($prompt) . '</span></div>'
            . '<svg viewBox="0 0 440 225" role="img" aria-label="' . esc_attr($aria) . '">'
            . '<polygon points="78,185 78,48 375,185" fill="#eef9f8" stroke="#087f83" stroke-width="5" stroke-linejoin="round"/>'
            . '<path d="M78 157 H106 V185" fill="none" stroke="#6d28d9" stroke-width="4"/>'
            . '<text x="47" y="117" text-anchor="middle" transform="rotate(-90 47 117)" class="mb-example-svg-measure">' . esc_html($leg_a) . '</text>'
            . '<text x="225" y="214" text-anchor="middle" class="mb-example-svg-measure">' . esc_html($leg_b) . '</text>'
            . '<rect x="220" y="78" width="112" height="32" rx="16" class="mb-example-svg-label-bg"/><text x="276" y="101" text-anchor="middle" class="mb-example-svg-unknown">' . esc_html($hypotenuse) . '</text>'
            . '<text x="112" y="171" class="mb-example-svg-label">right angle</text>'
            . '</svg></figure>';
    }

    private function render_shared_concept_visual($type, $heading, $prompt, $aria) {
        $svg = '';
        if ($type === 'place-value') {
            $svg = '<rect x="25" y="48" width="390" height="120" rx="8" fill="#fff" stroke="#087f83" stroke-width="3"/>'
                . '<path d="M103 48V168M181 48V168M259 48V168M337 48V168M25 91H415" stroke="#b9d9d8" stroke-width="2"/>'
                . '<text x="45" y="76" class="mb-example-svg-label">hundreds</text><text x="127" y="76" class="mb-example-svg-label">tens</text><text x="207" y="76" class="mb-example-svg-label">ones</text><text x="273" y="76" class="mb-example-svg-label">tenths</text><text x="345" y="76" class="mb-example-svg-label">hundredths</text>'
                . '<text x="61" y="137" text-anchor="middle" class="mb-example-svg-unknown">3</text><text x="139" y="137" text-anchor="middle" class="mb-example-svg-unknown">2</text><text x="217" y="137" text-anchor="middle" class="mb-example-svg-unknown">6</text><text x="295" y="137" text-anchor="middle" class="mb-example-svg-unknown">4</text><text x="373" y="137" text-anchor="middle" class="mb-example-svg-unknown">5</text>';
        } elseif ($type === 'fraction-ratio') {
            $svg = '<rect x="45" y="72" width="350" height="76" rx="5" fill="#fff" stroke="#087f83" stroke-width="4"/>'
                . '<rect x="45" y="72" width="210" height="76" fill="#ccefed"/>'
                . '<path d="M115 72V148M185 72V148M255 72V148M325 72V148" stroke="#087f83" stroke-width="3"/>'
                . '<text x="105" y="45" class="mb-example-svg-unknown">equal parts</text><text x="117" y="184" class="mb-example-svg-label">compare the shaded part with the whole</text>';
        } elseif ($type === 'probability') {
            $svg = '<circle cx="220" cy="110" r="78" fill="#fff" stroke="#087f83" stroke-width="4"/><path d="M220 32V188M142 110H298" stroke="#087f83" stroke-width="4"/>'
                . '<path d="M220 110L278 58" stroke="#6d28d9" stroke-width="6"/><circle cx="220" cy="110" r="8" fill="#6d28d9"/>'
                . '<text x="177" y="78" class="mb-example-svg-label">A</text><text x="251" y="78" class="mb-example-svg-label">B</text><text x="177" y="151" class="mb-example-svg-label">C</text><text x="251" y="151" class="mb-example-svg-label">D</text>';
        } elseif ($type === 'data') {
            $svg = '<path d="M45 170H400" stroke="#17394a" stroke-width="3"/>'
                . '<path d="M80 163V177M140 163V177M200 163V177M260 163V177M320 163V177M380 163V177" stroke="#17394a" stroke-width="3"/>'
                . '<g fill="#6d28d9"><circle cx="80" cy="145" r="8"/><circle cx="140" cy="145" r="8"/><circle cx="140" cy="125" r="8"/><circle cx="200" cy="145" r="8"/><circle cx="200" cy="125" r="8"/><circle cx="200" cy="105" r="8"/><circle cx="260" cy="145" r="8"/><circle cx="260" cy="125" r="8"/><circle cx="320" cy="145" r="8"/></g>'
                . '<text x="55" y="202" class="mb-example-svg-label">look for center, spread, and patterns</text>';
        } elseif ($type === 'graph') {
            $svg = '<path d="M55 185H405M95 205V25" stroke="#17394a" stroke-width="3"/><path d="M125 165L350 55" stroke="#087f83" stroke-width="5"/>'
                . '<g fill="#6d28d9"><circle cx="145" cy="155" r="7"/><circle cx="205" cy="125" r="7"/><circle cx="265" cy="96" r="7"/><circle cx="325" cy="67" r="7"/></g>'
                . '<text x="367" y="207" class="mb-example-svg-label">x</text><text x="72" y="39" class="mb-example-svg-label">y</text><text x="230" y="45" class="mb-example-svg-unknown">notice the pattern</text>';
        } elseif ($type === 'number-line') {
            $svg = '<path d="M35 112H405M35 112L52 101M35 112L52 123M405 112L388 101M405 112L388 123" stroke="#17394a" stroke-width="4"/>'
                . '<path d="M90 99V125M155 99V125M220 94V130M285 99V125M350 99V125" stroke="#17394a" stroke-width="3"/>'
                . '<text x="77" y="151" class="mb-example-svg-label">−2</text><text x="146" y="151" class="mb-example-svg-label">−1</text><text x="215" y="151" class="mb-example-svg-label">0</text><text x="280" y="151" class="mb-example-svg-label">1</text><text x="345" y="151" class="mb-example-svg-label">2</text>'
                . '<circle cx="285" cy="112" r="10" fill="#6d28d9"/><text x="120" y="70" class="mb-example-svg-unknown">left is less • right is greater</text>';
        } elseif ($type === 'equation') {
            $svg = '<path d="M220 55V157M130 157H310M165 157L115 196M275 157L325 196" stroke="#17394a" stroke-width="5"/>'
                . '<path d="M70 110H180M260 110H370" stroke="#087f83" stroke-width="5"/>'
                . '<rect x="95" y="69" width="60" height="40" rx="7" fill="#e9ddff" stroke="#6d28d9" stroke-width="3"/><text x="117" y="96" class="mb-example-svg-unknown">x</text>'
                . '<circle cx="290" cy="90" r="19" fill="#ccefed"/><circle cx="338" cy="90" r="19" fill="#ccefed"/><text x="84" y="35" class="mb-example-svg-label">keep both sides balanced</text>';
        } elseif ($type === 'volume') {
            $svg = '<path d="M95 75H305V185H95ZM305 75L365 40V150L305 185M95 75L155 40H365M95 185L155 150H365M155 40V150" fill="#eef9f8" fill-opacity=".65" stroke="#087f83" stroke-width="4" stroke-linejoin="round"/>'
                . '<text x="220" y="211" text-anchor="middle" class="mb-example-svg-label">length</text><text x="342" y="178" text-anchor="middle" transform="rotate(-30 342 178)" class="mb-example-svg-label">width</text><text x="65" y="130" text-anchor="middle" transform="rotate(-90 65 130)" class="mb-example-svg-label">height</text>';
        } elseif ($type === 'circle') {
            $svg = '<circle cx="220" cy="110" r="82" fill="#eef9f8" stroke="#087f83" stroke-width="4"/><path d="M138 110H302" stroke="#6d28d9" stroke-width="4"/><path d="M220 110L279 53" stroke="#087f83" stroke-width="5"/><circle cx="220" cy="110" r="7" fill="#17394a"/>'
                . '<text x="232" y="79" class="mb-example-svg-label">radius</text><text x="177" y="139" class="mb-example-svg-label">diameter</text><text x="230" y="126" class="mb-example-svg-label">center</text>';
        } elseif ($type === 'transformation') {
            $svg = '<defs><pattern id="mb-transform-grid" width="25" height="25" patternUnits="userSpaceOnUse"><path d="M25 0H0V25" fill="none" stroke="#dbe8ea"/></pattern></defs><rect x="25" y="20" width="390" height="185" fill="url(#mb-transform-grid)"/>'
                . '<polygon points="80,165 135,165 105,95" fill="#ccefed" stroke="#087f83" stroke-width="4"/><polygon points="270,135 325,135 295,65" fill="#e9ddff" stroke="#6d28d9" stroke-width="4"/>'
                . '<path d="M150 120H245" stroke="#17394a" stroke-width="4"/><path d="M245 120L231 111M245 120L231 129" stroke="#17394a" stroke-width="4"/><text x="148" y="105" class="mb-example-svg-label">move, turn, flip, or resize</text>';
        } elseif ($type === 'geometry') {
            $svg = '<polygon points="75,180 165,52 365,180" fill="#eef9f8" stroke="#087f83" stroke-width="5" stroke-linejoin="round"/><path d="M165 52V180" stroke="#6d28d9" stroke-width="4" stroke-dasharray="7 5"/>'
                . '<path d="M84 166A24 24 0 0 1 105 180" fill="none" stroke="#6d28d9" stroke-width="4"/><text x="183" y="116" class="mb-example-svg-label">height</text><text x="205" y="210" class="mb-example-svg-label">base</text><text x="55" y="153" class="mb-example-svg-unknown">?</text>';
        } elseif ($type === 'number-system') {
            $svg = '<ellipse cx="220" cy="112" rx="190" ry="92" fill="#f8f4ff" stroke="#6d28d9" stroke-width="3"/><ellipse cx="215" cy="115" rx="145" ry="68" fill="#eef9f8" stroke="#087f83" stroke-width="3"/><ellipse cx="200" cy="118" rx="96" ry="45" fill="#fff" stroke="#17394a" stroke-width="3"/>'
                . '<text x="47" y="57" class="mb-example-svg-label">complex</text><text x="86" y="89" class="mb-example-svg-label">real</text><text x="200" y="119" text-anchor="middle" class="mb-example-svg-label">rational and integers</text><text x="220" y="203" text-anchor="middle" class="mb-example-svg-label">groups fit inside larger groups</text>';
        } else {
            $svg = '<rect x="25" y="70" width="110" height="80" rx="12" fill="#eef9f8" stroke="#087f83" stroke-width="3"/><rect x="165" y="70" width="110" height="80" rx="12" fill="#f8f4ff" stroke="#6d28d9" stroke-width="3"/><rect x="305" y="70" width="110" height="80" rx="12" fill="#eef9f8" stroke="#087f83" stroke-width="3"/>'
                . '<text x="48" y="104" class="mb-example-svg-label">What do I</text><text x="68" y="124" class="mb-example-svg-label">know?</text><text x="186" y="104" class="mb-example-svg-label">Which rule</text><text x="198" y="124" class="mb-example-svg-label">fits?</text><text x="324" y="104" class="mb-example-svg-label">What must I</text><text x="344" y="124" class="mb-example-svg-label">find?</text>'
                . '<path d="M136 110H161M276 110H301" stroke="#17394a" stroke-width="4"/>';
        }
        return '<figure class="mb-practice-problem-visual mb-practice-visual-' . esc_attr($type) . '"><div><strong>' . esc_html($heading) . '</strong><span>' . esc_html($prompt) . '</span></div><svg viewBox="0 0 440 225" role="img" aria-label="' . esc_attr($aria) . '">' . $svg . '</svg></figure>';
    }

    public function render_practice_worked_example($text) {
        $items = $this->lines($text);
        if (!$items) return '';

        $parts = array_values(array_filter(array_map('trim', explode('|', $items[0])), function($part) {
            return $part !== '';
        }));
        if (!$parts) return '';

        $example_title = array_shift($parts);
        $html = '<section class="mb-practice-worked-example" aria-labelledby="mb-practice-example-title">';
        $html .= '<div class="mb-practice-example-heading"><span>Before You Try</span><h3 id="mb-practice-example-title">Study a Worked Example</h3><p>Look at the words and math in each step. Notice what you know, which rule is used, and how one step leads to the next.</p><div class="mb-practice-example-note"><strong>This example shows the answer so you can learn the steps.</strong><span>Study how the solution works. Then use the same ideas to try a new problem on your own.</span></div></div>';
        $html .= '<div class="mb-practice-example-body"><span class="mb-example-problem-label">Example Problem</span><strong>' . esc_html($example_title) . '</strong>';
        $html .= $this->render_practice_example_visual($example_title);
        if ($parts) {
            $html .= '<ol>';
            foreach ($parts as $step_index => $step) {
                $step_parts = array_map('trim', preg_split('/\s+[—-]\s+/', $step, 2));
                $step_label = count($step_parts) > 1 ? $step_parts[0] : 'Step ' . ($step_index + 1);
                $step_body = count($step_parts) > 1 ? $step_parts[1] : $step_parts[0];
                $html .= '<li><span>' . esc_html($step_label) . '</span><p>' . esc_html($step_body) . '</p></li>';
            }
            $html .= '</ol>';
        }
        $html .= '<div class="mb-example-transfer"><strong>Try these steps next:</strong><span>Find what you know, choose the rule that fits, and show your work one step at a time. Then enter your final answer.</span></div>';
        $html .= '</div></section>';
        return $html;
    }

    private function render_practice_example_visual($example_title) {
        $title = strtolower((string) $example_title);
        if (preg_match('/count a group of\s+(\d{1,2})/', $title, $count_match)) {
            return $this->render_early_math_visual('count', [(int) $count_match[1]], 'Picture the group', 'Point to each object once as you count.', true, $this->detect_early_math_object($example_title));
        }
        if (preg_match('/count forward|count by tens|counting to 100|missing number/', $title)) {
            preg_match_all('/\b\d{1,3}\b/', $title, $numbers);
            return $this->render_early_math_visual('sequence', array_map('intval', $numbers[0]), 'Picture the counting pattern', 'Read the numbers in order and notice what changes.', true);
        }
        if (preg_match('/compare|greater|less|equal/', $title)) {
            preg_match_all('/\b(?:[0-9]|1[0-9]|20)\b/', $title, $numbers);
            return $this->render_early_math_visual('compare', array_map('intval', array_slice($numbers[0], 0, 2)), 'Picture both groups', 'Count each group, then compare the amounts.', true, $this->detect_early_math_object($example_title));
        }
        if (preg_match('/rows? of|groups? of|array|multiply|factor|×/', $title)) {
            preg_match_all('/\b\d{1,2}\b/', $title, $numbers);
            return $this->render_operations_visual('array', array_map('intval', array_slice($numbers[0], 0, 2)), 'Picture the equal groups', 'Each row or group has the same amount.', $this->detect_early_math_object($example_title));
        }
        if (preg_match('/share|divide|division|÷/', $title)) {
            preg_match_all('/\b\d{1,2}\b/', $title, $numbers);
            return $this->render_operations_visual('share', array_map('intval', array_slice($numbers[0], 0, 2)), 'Picture the equal shares', 'Give the same amount to every group.', $this->detect_early_math_object($example_title));
        }
        if (preg_match('/add|join|altogether|make ten|switch the addends|\+/', $title)) {
            preg_match_all('/\b\d{1,2}\b/', $title, $numbers);
            return $this->render_operations_visual('join', array_map('intval', array_slice($numbers[0], 0, 2)), 'Picture the groups joining', 'Show both parts before finding the whole.', $this->detect_early_math_object($example_title));
        }
        if (preg_match('/subtract|take .*away|remain|−/', $title)) {
            preg_match_all('/\b\d{1,2}\b/', $title, $numbers);
            return $this->render_operations_visual('separate', array_map('intval', array_slice($numbers[0], 0, 2)), 'Picture what changes', 'Show the starting amount and the part that leaves.', $this->detect_early_math_object($example_title));
        }
        if (preg_match('/unknown|missing/', $title)) return $this->render_operations_visual('unknown', [], 'Picture the unknown', 'Keep both sides of the equation equal.');
        if (preg_match('/pattern|rule add|rule multiply/', $title)) {
            preg_match_all('/\b\d{1,3}\b/', $title, $numbers);
            return $this->render_operations_visual('pattern', array_map('intval', array_slice($numbers[0], 0, 5)), 'Picture the pattern', 'Apply the same rule to each new term.');
        }
        if (preg_match('/circle|radius|diameter|circumference/', $title)) {
            return $this->render_shared_concept_visual('circle', 'Picture the circle', 'Match the radius, diameter, circumference, or area in the model to the question.', 'A circle with its center, radius, and diameter marked');
        }
        if (preg_match('/coordinate|ordered pair|scale drawing|map uses/', $title)) {
            return $this->render_shared_concept_visual('graph', 'Picture the location or scale', 'Use the axes, coordinates, or scale factor shown by the diagram.', 'A coordinate plane showing a geometric relationship');
        }
        if (preg_match('/translate|reflect|rotate|dilate|transformation|congruen|similar/', $title)) {
            return $this->render_shared_concept_visual('transformation', 'Picture the transformation', 'Follow each point as the figure moves, turns, flips, or changes size.', 'A figure and its transformed image on a coordinate grid');
        }
        if (preg_match('/prism|cylinder|cone|sphere|\bvolume\b/', $title)) {
            return $this->render_shared_concept_visual('volume', 'Picture the solid', 'Use the labeled dimensions to connect the solid to its volume formula.', 'A three-dimensional solid with dimensions marked');
        }
        if (preg_match('/shape|square|rectangle|triangle|quadrilateral|polygon|angle|symmetr|parallel|area|surface|geometry/', $title)) {
            return $this->render_shared_concept_visual('geometry', 'Picture the figure', 'Read every label and marking before choosing a property or formula.', 'A geometric figure with side lengths, an angle, and an unknown measurement marked');
        }
        if (strpos($title, 'right triangle') === false && strpos($title, 'hypotenuse') === false && strpos($title, 'pythagorean') === false) {
            return '';
        }

        return '<figure class="mb-practice-example-visual">'
            . '<div class="mb-example-visual-heading"><strong>Picture the problem</strong><span>The two legs meet to make the right angle. The hypotenuse is the slanted side across from it.</span></div>'
            . '<svg viewBox="0 0 440 270" role="img" aria-label="A right triangle with legs of 6 units and 8 units and an unknown hypotenuse labeled c">'
            . '<polygon points="82,218 82,58 362,218" fill="#eef9f8" stroke="#087f83" stroke-width="5" stroke-linejoin="round" />'
            . '<path d="M82 190 H110 V218" fill="none" stroke="#6d28d9" stroke-width="4" />'
            . '<text x="49" y="138" text-anchor="middle" transform="rotate(-90 49 138)" class="mb-example-svg-measure">6 units</text>'
            . '<text x="222" y="250" text-anchor="middle" class="mb-example-svg-measure">8 units</text>'
            . '<rect x="218" y="91" width="142" height="55" rx="18" class="mb-example-svg-label-bg"/>'
            . '<text x="289" y="116" text-anchor="middle" class="mb-example-svg-unknown">c = ?</text>'
            . '<text x="289" y="136" text-anchor="middle" class="mb-example-svg-label">hypotenuse</text>'
            . '<text x="116" y="205" class="mb-example-svg-label">right angle</text>'
            . '</svg>'
            . '<figcaption>We know both leg lengths. We are finding <strong>c</strong>, the hypotenuse.</figcaption>'
            . '</figure>';
    }

    private function detect_early_math_object($text) {
        $text = strtolower((string) $text);
        foreach (['apple', 'star', 'dot', 'block', 'cracker', 'counter', 'circle', 'button', 'bird', 'balloon', 'crayon', 'pencil', 'duck', 'car', 'sticker', 'marker'] as $object) {
            if (preg_match('/\b' . preg_quote($object, '/') . 's?\b/', $text)) return $object;
        }
        return 'object';
    }

    private function render_early_math_marker($object, $cx, $cy, $fill, $stroke, $number = '') {
        $shape = '';
        if ($object === 'apple') {
            $shape = '<path d="M' . esc_attr($cx) . ' ' . esc_attr($cy - 17) . ' C' . esc_attr($cx - 20) . ' ' . esc_attr($cy - 25) . ' ' . esc_attr($cx - 25) . ' ' . esc_attr($cy + 4) . ' ' . esc_attr($cx - 10) . ' ' . esc_attr($cy + 19) . ' C' . esc_attr($cx - 3) . ' ' . esc_attr($cy + 25) . ' ' . esc_attr($cx + 3) . ' ' . esc_attr($cy + 25) . ' ' . esc_attr($cx + 10) . ' ' . esc_attr($cy + 19) . ' C' . esc_attr($cx + 25) . ' ' . esc_attr($cy + 4) . ' ' . esc_attr($cx + 20) . ' ' . esc_attr($cy - 25) . ' ' . esc_attr($cx) . ' ' . esc_attr($cy - 17) . ' Z" fill="' . esc_attr($fill) . '" stroke="' . esc_attr($stroke) . '" stroke-width="3"/><path d="M' . esc_attr($cx) . ' ' . esc_attr($cy - 17) . ' Q' . esc_attr($cx + 2) . ' ' . esc_attr($cy - 31) . ' ' . esc_attr($cx + 10) . ' ' . esc_attr($cy - 34) . '" fill="none" stroke="#6b4b2a" stroke-width="3"/><path d="M' . esc_attr($cx + 5) . ' ' . esc_attr($cy - 27) . ' Q' . esc_attr($cx + 18) . ' ' . esc_attr($cy - 36) . ' ' . esc_attr($cx + 23) . ' ' . esc_attr($cy - 24) . ' Q' . esc_attr($cx + 14) . ' ' . esc_attr($cy - 19) . ' ' . esc_attr($cx + 5) . ' ' . esc_attr($cy - 27) . '" fill="#4e9b55" stroke="#2f6e37" stroke-width="2"/>';
        } elseif ($object === 'star') {
            $points = [];
            for ($i = 0; $i < 10; $i++) {
                $angle = deg2rad(-90 + ($i * 36));
                $radius = ($i % 2 === 0) ? 22 : 10;
                $points[] = round($cx + cos($angle) * $radius, 1) . ',' . round($cy + sin($angle) * $radius, 1);
            }
            $shape = '<polygon points="' . esc_attr(implode(' ', $points)) . '" fill="' . esc_attr($fill) . '" stroke="' . esc_attr($stroke) . '" stroke-width="3" stroke-linejoin="round"/>';
        } elseif ($object === 'block') {
            $shape = '<polygon points="' . esc_attr(($cx - 18) . ',' . ($cy - 12) . ' ' . ($cx - 7) . ',' . ($cy - 23) . ' ' . ($cx + 23) . ',' . ($cy - 23) . ' ' . ($cx + 12) . ',' . ($cy - 12)) . '" fill="#f4fbfa" stroke="' . esc_attr($stroke) . '" stroke-width="3" stroke-linejoin="round"/>'
                . '<polygon points="' . esc_attr(($cx + 12) . ',' . ($cy - 12) . ' ' . ($cx + 23) . ',' . ($cy - 23) . ' ' . ($cx + 23) . ',' . ($cy + 8) . ' ' . ($cx + 12) . ',' . ($cy + 19)) . '" fill="#a9d7d5" stroke="' . esc_attr($stroke) . '" stroke-width="3" stroke-linejoin="round"/>'
                . '<polygon points="' . esc_attr(($cx - 18) . ',' . ($cy - 12) . ' ' . ($cx + 12) . ',' . ($cy - 12) . ' ' . ($cx + 12) . ',' . ($cy + 19) . ' ' . ($cx - 18) . ',' . ($cy + 19)) . '" fill="' . esc_attr($fill) . '" stroke="' . esc_attr($stroke) . '" stroke-width="3" stroke-linejoin="round"/>';
        } elseif ($object === 'cracker') {
            $shape = '<circle cx="' . esc_attr($cx) . '" cy="' . esc_attr($cy) . '" r="20" fill="#f7d58b" stroke="#a86516" stroke-width="3"/><circle cx="' . esc_attr($cx - 7) . '" cy="' . esc_attr($cy - 5) . '" r="2" fill="#a86516"/><circle cx="' . esc_attr($cx + 7) . '" cy="' . esc_attr($cy - 5) . '" r="2" fill="#a86516"/><circle cx="' . esc_attr($cx) . '" cy="' . esc_attr($cy + 7) . '" r="2" fill="#a86516"/>';
        } elseif ($object === 'button') {
            $shape = '<circle cx="' . esc_attr($cx) . '" cy="' . esc_attr($cy) . '" r="20" fill="' . esc_attr($fill) . '" stroke="' . esc_attr($stroke) . '" stroke-width="3"/><circle cx="' . esc_attr($cx - 6) . '" cy="' . esc_attr($cy) . '" r="2.5" fill="#fff"/><circle cx="' . esc_attr($cx + 6) . '" cy="' . esc_attr($cy) . '" r="2.5" fill="#fff"/>';
        } elseif (in_array($object, ['bird', 'balloon', 'crayon', 'pencil', 'duck', 'car', 'sticker', 'marker'], true)) {
            $icons = ['bird'=>'🐦', 'balloon'=>'🎈', 'crayon'=>'🖍️', 'pencil'=>'✏️', 'duck'=>'🦆', 'car'=>'🚗', 'sticker'=>'⭐', 'marker'=>'🖊️'];
            $shape = '<text x="' . esc_attr($cx) . '" y="' . esc_attr($cy + 12) . '" text-anchor="middle" font-size="38" aria-hidden="true">' . esc_html($icons[$object]) . '</text>';
        } else {
            $shape = '<circle cx="' . esc_attr($cx) . '" cy="' . esc_attr($cy) . '" r="20" fill="' . esc_attr($fill) . '" stroke="' . esc_attr($stroke) . '" stroke-width="3"/>';
        }
        if ($number !== '') $shape .= '<text x="' . esc_attr($cx) . '" y="' . esc_attr($cy + 6) . '" text-anchor="middle" class="mb-example-svg-measure">' . esc_html($number) . '</text>';
        return $shape;
    }

    private function render_early_math_visual($type, $values, $heading, $prompt, $example = false, $object = 'object') {
        $figure_class = $example ? 'mb-practice-example-visual mb-practice-visual-early-math' : 'mb-practice-problem-visual mb-practice-visual-early-math';
        $svg = '';
        $caption = '';
        $aria = '';

        if ($type === 'compare') {
            $left = isset($values[0]) ? max(0, min(10, (int) $values[0])) : 4;
            $right = isset($values[1]) ? max(0, min(10, (int) $values[1])) : 6;
            $plural = $object === 'object' ? 'objects' : $object . 's';
            $aria = 'Two groups of ' . $plural . ', one with ' . $left . ' and one with ' . $right . ', ready to compare';
            $svg .= '<rect x="20" y="35" width="175" height="160" rx="14" fill="#eef9f8" stroke="#087f83" stroke-width="3"/><rect x="245" y="35" width="175" height="160" rx="14" fill="#f8f4ff" stroke="#6d28d9" stroke-width="3"/>';
            foreach ([[$left, 20, '#087f83'], [$right, 245, '#6d28d9']] as $group) {
                for ($i = 0; $i < $group[0]; $i++) {
                    $cx = $group[1] + 38 + (($i % 4) * 34); $cy = 77 + (intdiv($i, 4) * 36);
                    $svg .= $this->render_early_math_marker($object, $cx, $cy, $group[2], $group[2]);
                }
            }
            $svg .= '<text x="108" y="181" text-anchor="middle" class="mb-example-svg-measure">' . esc_html($left . ' ' . $plural) . '</text><text x="332" y="181" text-anchor="middle" class="mb-example-svg-measure">' . esc_html($right . ' ' . $plural) . '</text><text x="220" y="125" text-anchor="middle" class="mb-example-svg-unknown">?</text>';
            $caption = 'Count each group. Then decide whether the first amount is greater than, less than, or equal to the second.';
        } elseif ($type === 'sequence') {
            $sequence = array_values(array_filter(array_map('intval', (array) $values), function($value) { return $value >= 0; }));
            if (count($sequence) < 2) $sequence = [10, 20, 30, 40, 50, 60, 70, 80, 90, 100];
            $sequence = array_slice($sequence, 0, 10);
            $aria = 'A number sequence arranged from left to right to show a counting pattern';
            foreach ($sequence as $i => $number) {
                $col = $i % 5; $row = intdiv($i, 5); $x = 22 + ($col * 83); $y = 52 + ($row * 82);
                $svg .= '<rect x="' . esc_attr($x) . '" y="' . esc_attr($y) . '" width="66" height="54" rx="12" fill="' . ($row ? '#f8f4ff' : '#eef9f8') . '" stroke="' . ($row ? '#6d28d9' : '#087f83') . '" stroke-width="3"/><text x="' . esc_attr($x + 33) . '" y="' . esc_attr($y + 36) . '" text-anchor="middle" class="mb-example-svg-measure">' . esc_html($number) . '</text>';
            }
            $caption = 'Say the numbers in order. Look for the amount that is added each time.';
        } else {
            $count = isset($values[0]) ? max(0, min(20, (int) $values[0])) : 8;
            $plural = $object === 'object' ? 'objects' : $object . 's';
            $aria = 'A group of ' . $count . ' ' . $plural . ' arranged in rows for one-to-one counting';
            if ($count === 0) {
                $svg = '<rect x="55" y="45" width="330" height="125" rx="18" fill="#fff" stroke="#087f83" stroke-width="3" stroke-dasharray="9 7"/><text x="220" y="105" text-anchor="middle" class="mb-example-svg-unknown">0 objects</text><text x="220" y="138" text-anchor="middle" class="mb-example-svg-label">zero means none</text>';
            } else {
                for ($i = 0; $i < $count; $i++) {
                    $col = $i % 5; $row = intdiv($i, 5); $cx = 78 + ($col * 72); $cy = 52 + ($row * 48);
                    $n = $i + 1;
                    $svg .= $this->render_early_math_marker($object, $cx, $cy, ($row % 2 ? '#e9ddff' : '#ccefed'), ($row % 2 ? '#6d28d9' : '#087f83'), $n);
                }
            }
            $caption = 'Touch or point to each object one time. The last number you say tells how many objects are in the group.';
        }

        return '<figure class="' . esc_attr($figure_class) . '"><div class="mb-example-visual-heading"><strong>' . esc_html($heading) . '</strong><span>' . esc_html($prompt) . '</span></div><svg viewBox="0 0 440 225" role="img" aria-label="' . esc_attr($aria) . '">' . $svg . '</svg>' . ($example ? '<figcaption>' . esc_html($caption) . '</figcaption>' : '') . '</figure>';
    }

    public function section_toggle($id, $label, $open = false) {
        return '<button type="button" class="mb-section-toggle" aria-expanded="' . ($open ? 'true' : 'false') . '" aria-controls="' . esc_attr($id) . '-content"><span>' . esc_html($label) . '</span><span class="mb-toggle-icon" aria-hidden="true">⌄</span></button>';
    }

    public function get_section_pages($post_id, $status = 'publish') {
        $terms = wp_get_post_terms($post_id, self::TAX, ['fields' => 'ids']);
        if (!$terms) return [];

        return get_posts([
            'post_type' => self::CPT,
            'post_status' => $status,
            'posts_per_page' => -1,
            'tax_query' => [[
                'taxonomy' => self::TAX,
                'field' => 'term_id',
                'terms' => $terms[0]
            ]],
            /*
             * Content packs create lessons in their approved teaching sequence.
             * Many of those lessons intentionally share the default menu_order,
             * so ID is the stable tie-breaker. Alphabetical title order makes
             * the progress counter and lesson navigation pedagogically wrong.
             */
            'orderby' => ['menu_order' => 'ASC', 'ID' => 'ASC']
        ]);
    }

    public function render_support_cards($text, $type = 'parent') {
        $items = $this->lines($text);
        if (!$items) return '';

        $parent_labels = ['Conversation Starter', 'At-Home Activity', 'Common Misconception', 'Extra Challenge'];
        $teacher_labels = ['Instruction', 'Objective', 'Assessment', 'Discussion', 'Extension'];
        $labels = $type === 'teacher' ? $teacher_labels : $parent_labels;

        $html = '<div class="mb-support-card-grid mb-support-' . esc_attr($type) . '">';
        foreach ($items as $index => $item) {
            $parts = array_map('trim', explode('|', $item, 2));
            $label = count($parts) > 1 ? $parts[0] : $labels[$index % count($labels)];
            $body = count($parts) > 1 ? $parts[1] : $parts[0];

            $html .= '<article class="mb-support-card">';
            $html .= '<span class="mb-support-icon" aria-hidden="true"></span>';
            $html .= '<h3>' . esc_html($label) . '</h3>';
            $html .= '<p>' . wp_kses_post($body) . '</p>';
            $html .= '</article>';
        }
        return $html . '</div>';
    }

    public function render_mastery_questions($text) {
        $items = $this->lines($text);
        if (!$items) return '';

        $html = '<div class="mb-mastery-assessment">';
        foreach ($items as $index => $item) {
            $parts = array_map('trim', explode('|', $item));
            $question = $parts[0] ?? '';
            if (!$question) continue;

            $number = $index + 1;
            $html .= '<article class="mb-mastery-item" data-mastery-index="' . esc_attr($index) . '">';
            $html .= '<div class="mb-mastery-item-heading">';
            $html .= '<span class="mb-mastery-number">' . esc_html($number) . '</span>';
            $html .= '<div><small>Question ' . esc_html($number) . '</small><h3>' . esc_html($question) . '</h3></div>';
            $html .= '</div>';

            if (count($parts) >= 3 && strpos($parts[1], ';') !== false) {
                $options = array_values(array_filter(array_map('trim', explode(';', $parts[1]))));
                $correct = strtoupper($parts[2]);

                $html .= '<div class="mb-mastery-choice-grid" role="radiogroup" aria-label="' . esc_attr($question) . '">';
                foreach ($options as $choice_index => $option) {
                    $letter = chr(65 + $choice_index);
                    $html .= '<button type="button" class="mb-mastery-choice" data-choice="' . esc_attr($letter) . '" data-correct="' . esc_attr($correct) . '">';
                    $html .= '<span>' . esc_html($letter) . '</span><strong>' . esc_html($option) . '</strong>';
                    $html .= '</button>';
                }
                $html .= '</div>';
            } else {
                $answer = $parts[1] ?? '';
                $html .= '<textarea class="mb-mastery-response" rows="4" data-expected="' . esc_attr($answer) . '" placeholder="Explain your reasoning…"></textarea>';
                $html .= '<button type="button" class="mb-self-check-response">Review My Response</button>';
                if ($answer) {
                    $html .= '<div class="mb-constructed-guide" hidden><strong>Look for:</strong> ' . wp_kses_post($answer) . '</div>';
                }
            }

            $html .= '<div class="mb-mastery-item-status" aria-live="polite"></div>';
            $html .= '</article>';
        }
        return $html . '</div>';
    }

    public function get_adjacent_topic($post_id, $direction = 'next') {
        $pages = $this->get_section_pages($post_id, 'publish');
        if (!$pages) return null;

        foreach ($pages as $index => $page) {
            if (intval($page->ID) !== intval($post_id)) continue;

            $target_index = $direction === 'previous' ? $index - 1 : $index + 1;
            return isset($pages[$target_index]) ? $pages[$target_index] : null;
        }

        return null;
    }


    public function homepage_shortcode() {
        if (!is_user_logged_in()) {
            return $this->public_homepage();
        }

        $sections = get_terms([
            'taxonomy' => self::TAX,
            'hide_empty' => false,
            'orderby' => 'meta_value_num',
            'meta_key' => 'mb_number'
        ]);

        $place_value = get_page_by_path('place-value', OBJECT, self::CPT);
        $binder_topics = get_page_by_path('binder-topics');
        $binder_topics_url = $binder_topics ? get_permalink($binder_topics) : home_url('/binder-topics/');
        $homepage_binder_sections = [
            ['Counting & Cardinality', 'Kindergarten', 'domain-counting-cardinality'],
            ['Operations & Algebraic Thinking', 'Grades K–5', 'domain-operations-algebraic-thinking'],
            ['Number & Operations in Base Ten', 'Grades K–5', 'domain-number-base-ten'],
            ['Number & Operations—Fractions', 'Grades 3–5', 'domain-number-fractions'],
            ['Measurement & Data', 'Grades K–5', 'domain-measurement-data'],
            ['Geometry', 'Grades K–8', 'domain-geometry'],
            ['Ratios & Proportional Relationships', 'Grades 6–7', 'domain-ratios-proportional'],
            ['The Number System', 'Grades 6–8', 'domain-number-system'],
            ['Expressions & Equations', 'Grades 6–8', 'domain-expressions-equations'],
            ['Functions', 'Grade 8', 'domain-functions'],
            ['Statistics & Probability', 'Grades 6–8', 'domain-statistics-probability'],
        ];
        $homepage_high_school_sections = [
            ['Algebra I', 'High School', 'course-algebra-1'],
            ['Geometry', 'High School', 'course-geometry'],
            ['Algebra II', 'High School', 'course-algebra-2'],
            ['Precalculus', 'High School / Advanced', 'course-precalculus'],
            ['Calculus', 'Advanced / College Preparation', 'course-calculus'],
        ];

        $recent_pages = get_posts([
            'post_type' => self::CPT,
            'post_status' => 'publish',
            'posts_per_page' => 4,
            'orderby' => 'modified',
            'order' => 'DESC'
        ]);

        $featured = $place_value;
        if (!$featured && $recent_pages) $featured = $recent_pages[0];

        ob_start();
        ?>
        <div class="mb-home mb-home-v8">
            <section class="mb-v8-hero">
                <div class="mb-v8-copy mb-reveal">
                    <div class="mb-v8-logo">
                        <img src="<?php echo esc_url(plugin_dir_url(__FILE__) . 'Assests/Logos/mathbinder-logo.svg'); ?>" alt="MathBinder">
                    </div>

                    <h1><span>Find It.</span><span>Learn It.</span><span>Master It.</span></h1>
                    <p class="mb-v8-subtitle">Your all-in-one math resource for every learner.</p>

                    <div class="mb-v8-search-card">
                        <h2>Search MathBinder</h2>
                        <p>Find any math topic instantly.</p>
                        <form class="mb-home-search mb-home-search-v8" role="search" method="get" action="<?php echo esc_url(home_url('/')); ?>" autocomplete="off">
                            <label class="screen-reader-text" for="mb-home-search-field">Search MathBinder</label>
                            <input id="mb-home-search-field" type="search" name="s" placeholder="Type a topic, such as place value or fractions" aria-autocomplete="list" aria-controls="mb-search-results" required>
                            <input type="hidden" name="post_type" value="<?php echo esc_attr(self::CPT); ?>">
                            <button type="submit">Search</button>
                            <div id="mb-search-results" class="mb-search-results" role="listbox" aria-label="Topic suggestions"></div>
                        </form>

                        <div class="mb-home-actions">
                            <a class="mb-home-primary" href="<?php echo esc_url($binder_topics_url); ?>">Browse Binder Topics</a>
                            <?php if ($featured): ?>
                                <a class="mb-home-secondary" href="<?php echo esc_url(get_permalink($featured)); ?>">Open <?php echo esc_html($featured->post_title); ?></a>
                            <?php endif; ?>
                        </div>
                    </div>
                </div>

                <div class="mb-v8-art mb-reveal" aria-label="MathBinder digital binder illustration">
                    <img class="mb-v8-scene" src="<?php echo esc_url(plugin_dir_url(__FILE__) . 'Assests/mathbinder-binder-scene-v94.png'); ?>" alt="Teal MathBinder with section tabs and an open Place Value Binder Page">
                </div>
            </section>

            <section id="binder-sections" class="mb-home-section mb-home-binder-section mb-reveal">
                <div class="mb-home-section-heading">
                    <div>
                        <span class="mb-home-kicker">Explore the Binder</span>
                        <h2>Binder Sections</h2>
                    </div>
                    <p>Choose a standards domain or course, open its notebook page, and select the lesson you need.</p>
                </div>

                <div class="mb-curriculum-section-heading mb-home-domain-heading"><span>Kindergarten–Grade 8</span><h3>Binder Sections by Standards Domain</h3></div>
                <div class="mb-section-card-grid mb-section-card-grid-v8">
                    <?php foreach ($homepage_binder_sections as $index => $section): ?>
                        <a class="mb-section-card mb-section-card-<?php echo esc_attr(($index % 4) + 1); ?>" href="<?php echo esc_url(add_query_arg('mb_section', $section[2], $binder_topics_url)); ?>">
                            <span class="mb-section-number"><?php echo esc_html(str_pad($index + 1, 2, '0', STR_PAD_LEFT)); ?></span>
                            <h3><?php echo esc_html($section[0]); ?></h3>
                            <p><?php echo esc_html($section[1]); ?></p>
                            <span class="mb-card-arrow">Open notebook page →</span>
                        </a>
                    <?php endforeach; ?>
                </div>

                <div class="mb-curriculum-section-heading mb-curriculum-high-school-heading mb-home-domain-heading"><span>High School and Advanced Mathematics</span><h3>Binder Sections by Course</h3></div>
                <div class="mb-section-card-grid mb-section-card-grid-v8 mb-home-high-school-grid">
                    <?php foreach ($homepage_high_school_sections as $index => $section): ?>
                        <a class="mb-section-card mb-section-card-<?php echo esc_attr(($index % 4) + 1); ?>" href="<?php echo esc_url(add_query_arg('mb_section', $section[2], $binder_topics_url)); ?>">
                            <span class="mb-section-number"><?php echo esc_html(str_pad($index + 12, 2, '0', STR_PAD_LEFT)); ?></span>
                            <h3><?php echo esc_html($section[0]); ?></h3>
                            <p><?php echo esc_html($section[1]); ?></p>
                            <span class="mb-card-arrow">Open notebook page →</span>
                        </a>
                    <?php endforeach; ?>
                </div>
            </section>

            <?php if ($featured):
                $featured_summary = get_post_meta($featured->ID, '_mb_subtitle', true);
                $featured_terms = wp_get_post_terms($featured->ID, self::TAX, ['fields' => 'names']);
            ?>
                <section class="mb-v8-content-row mb-reveal">
                    <article class="mb-home-featured-page mb-v8-featured">
                        <div class="mb-featured-label">Featured Binder Page</div>
                        <div class="mb-featured-content">
                            <div>
                                <span class="mb-home-kicker"><?php echo esc_html($featured_terms ? $featured_terms[0] : 'Binder Page'); ?></span>
                                <h2><?php echo esc_html($featured->post_title); ?></h2>
                                <p><?php echo esc_html($featured_summary ?: 'Open this Binder Page to learn, watch, practice, download resources, and check your understanding.'); ?></p>
                                <a class="mb-home-primary" href="<?php echo esc_url(get_permalink($featured)); ?>">Open Binder Page</a>
                            </div>
                        </div>
                    </article>

                    <section class="mb-v8-recent">
                        <div class="mb-v8-recent-heading">
                            <h2>Recently Added Binder Pages</h2>
                            <a href="<?php echo esc_url($binder_topics_url); ?>">View all pages →</a>
                        </div>
                        <div class="mb-recent-grid mb-recent-grid-v8">
                            <?php foreach ($recent_pages as $recent):
                                $summary = get_post_meta($recent->ID, '_mb_subtitle', true);
                            ?>
                                <a class="mb-recent-card" href="<?php echo esc_url(get_permalink($recent)); ?>">
                                    <h3><?php echo esc_html($recent->post_title); ?></h3>
                                    <?php if ($summary): ?><p><?php echo esc_html($summary); ?></p><?php endif; ?>
                                    <strong>View page →</strong>
                                </a>
                            <?php endforeach; ?>
                        </div>
                    </section>
                </section>
            <?php endif; ?>

            <section class="mb-home-help-grid mb-home-help-grid-v8 mb-reveal">
                <article class="mb-audience-card mb-audience-students">
                    <span class="mb-help-icon" aria-hidden="true">✓</span>
                    <h2>For Students</h2>
                    <p>Build confidence, practice skills, and master math one topic at a time.</p>
                    <ul>
                        <li>Clear step-by-step lessons</li>
                        <li>Videos and interactive practice</li>
                        <li>Mastery checks</li>
                    </ul>
                    <a href="<?php echo esc_url($binder_topics ? get_permalink($binder_topics) : home_url('/binder-topics/')); ?>">Learn more →</a>
                </article>

                <article class="mb-audience-card mb-audience-parents">
                    <span class="mb-help-icon" aria-hidden="true">♥</span>
                    <h2>For Parents</h2>
                    <p>Understand the math and support learning without having to become the teacher.</p>
                    <ul>
                        <li>Parent guides and tips</li>
                        <li>Common misconceptions</li>
                        <li>Conversation starters</li>
                    </ul>
                    <a href="<?php echo esc_url($this->public_page_url('parents')); ?>">Learn more →</a>
                </article>

                <article class="mb-audience-card mb-audience-teachers">
                    <span class="mb-help-icon" aria-hidden="true">★</span>
                    <h2>For Teachers</h2>
                    <p>Save time with organized resources for teaching, intervention, and practice.</p>
                    <ul>
                        <li>Printable pages and keys</li>
                        <li>Curated practice resources</li>
                        <li>Consistent topic organization</li>
                    </ul>
                    <a href="<?php echo esc_url($this->public_page_url('teachers')); ?>">Learn more →</a>
                </article>
            </section>

            <footer class="mb-home-footer mb-reveal">
                <div class="mb-home-footer-main">
                    <div class="mb-bottom-brand">
                        <img src="<?php echo esc_url(plugin_dir_url(__FILE__) . 'Assests/Logos/mathbinder-logo.svg'); ?>" alt="MathBinder">
                        <p>Digital Student Binder</p>
                        <span>Find It. Learn It. Master It.</span>
                    </div>
                    <div class="mb-bottom-links">
                        <h2>Explore</h2>
                        <a href="<?php echo esc_url($binder_topics ? get_permalink($binder_topics) : home_url('/binder-topics/')); ?>">Binder Topics</a>
                        <a href="<?php echo esc_url($this->public_page_url('parents')); ?>">Parents</a>
                        <a href="<?php echo esc_url($this->public_page_url('teachers')); ?>">Teachers</a>
                        <a href="<?php echo esc_url($this->public_page_url('contact')); ?>">Contact</a>
                    </div>
                    <div class="mb-bottom-mission">
                        <h2>Our Purpose</h2>
                        <p>Make trustworthy math help easier to find, easier to understand, and easier to use.</p>
                    </div>
                </div>
                <div class="mb-home-footer-bottom">
                    <span>&copy; <?php echo esc_html(date('Y')); ?> MathBinder</span>
                    <span>Digital Student Binder</span>
                </div>
            </footer>
        </div>
        <?php
        return ob_get_clean();
    }

    private function public_homepage() {
        $login_url = class_exists('MathBinder_Frontend_Auth')
            ? MathBinder_Frontend_Auth::login_url()
            : home_url('/login/');
        $signup_url = class_exists('MathBinder_Family_Checkout')
            ? MathBinder_Family_Checkout::signup_url()
            : home_url('/sign-up/');

        ob_start();
        ?>
        <main class="mb-public-home">
            <section class="mb-public-home-hero">
                <div class="mb-public-home-copy">
                    <img class="mb-public-home-logo" src="<?php echo esc_url(plugin_dir_url(__FILE__) . 'Assests/Logos/mathbinder-logo.svg'); ?>" alt="MathBinder">
                    <p class="mb-public-home-kicker">Digital Student Binder</p>
                    <h1>Math help that stays organized.</h1>
                    <p class="mb-public-home-lead">MathBinder brings clear lessons, helpful videos, practice resources, notes, and progress tools together in one easy-to-use learning space.</p>
                    <div class="mb-public-home-actions">
                        <a class="mb-public-home-signup" href="<?php echo esc_url($signup_url); ?>">Start Your 14-Day Free Trial</a>
                        <a class="mb-public-home-login" href="<?php echo esc_url($login_url); ?>">Log In</a>
                    </div>
                    <p class="mb-public-home-price">Family Premium is $14.99 per month for the first child and $4.99 per month for each additional child.</p>
                </div>
                <div class="mb-public-home-demo">
                    <video class="mb-public-home-video" controls playsinline preload="metadata" aria-label="See how MathBinder works">
                        <source src="<?php echo esc_url(content_url('/uploads/2026/08/mathbinder-promo.mp4')); ?>" type="video/mp4">
                        Your browser does not support the video player.
                    </video>
                </div>
            </section>

            <section class="mb-public-home-benefits" aria-labelledby="mb-benefits-title">
                <div class="mb-public-home-heading">
                    <p class="mb-public-home-kicker">Find It. Learn It. Master It.</p>
                    <h2 id="mb-benefits-title">Everything students need to keep moving forward</h2>
                </div>
                <div class="mb-public-home-benefit-grid">
                    <article><span aria-hidden="true">1</span><h3>Find the right topic</h3><p>Students can quickly reach the math help they need without sorting through unrelated material.</p></article>
                    <article><span aria-hidden="true">2</span><h3>Learn in more than one way</h3><p>Explanations, examples, videos, and practice work together to make difficult ideas clearer.</p></article>
                    <article><span aria-hidden="true">3</span><h3>Build lasting mastery</h3><p>Notes and progress tools help students remember what they learned and see what comes next.</p></article>
                </div>
            </section>

            <section class="mb-public-home-audiences">
                <div><p class="mb-public-home-kicker">For Families</p><h2>Support math learning with confidence.</h2><p>Parents receive one organized place to manage their children and support learning without having to search across multiple websites.</p></div>
                <div class="mb-public-home-checklist">
                    <p>Clear, student-friendly instruction</p>
                    <p>Organized videos and practice resources</p>
                    <p>Digital notes and progress support</p>
                    <p>One parent account for multiple children</p>
                </div>
            </section>

            <section class="mb-public-home-cta">
                <div><p class="mb-public-home-kicker">Ready to begin?</p><h2>Give your child a better place to learn math.</h2><p>Try Family Premium free for 14 days. Cancel anytime.</p></div>
                <a href="<?php echo esc_url($signup_url); ?>">Sign Up</a>
            </section>
        </main>
        <?php
        return ob_get_clean();
    }

    public function progress_shortcode() {
        $terms = get_terms([
            'taxonomy' => self::TAX,
            'hide_empty' => false,
            'orderby' => 'meta_value_num',
            'meta_key' => 'mb_number'
        ]);

        if (is_wp_error($terms)) {
            return '<p>Progress information is temporarily unavailable.</p>';
        }

        $all_pages = get_posts([
            'post_type' => self::CPT,
            'post_status' => 'publish',
            'posts_per_page' => -1,
            'orderby' => ['menu_order' => 'ASC', 'title' => 'ASC']
        ]);

        ob_start();
        ?>
        <div class="mb-progress-dashboard">
            <header class="mb-dashboard-hero">
                <div>
                    <span class="mb-dashboard-kicker">My MathBinder</span>
                    <h1>Your learning progress</h1>
                    <p>Resume your last lesson, track completed Binder Pages, save favorites, and celebrate each milestone.</p>
                </div>
                <img src="<?php echo esc_url(plugin_dir_url(__FILE__) . 'Assests/Logos/mathbinder-logo.svg'); ?>" alt="MathBinder">
            </header>

            <section class="mb-dashboard-metrics" aria-label="Progress summary">
                <article><span>Completed</span><strong data-mb-total-completed>0</strong><small>Binder Pages</small></article>
                <article><span>Available</span><strong><?php echo esc_html(count($all_pages)); ?></strong><small>Published Pages</small></article>
                <article><span>Overall Progress</span><strong data-mb-overall-percent>0%</strong><small>Across MathBinder</small></article>
                <article><span>Favorites</span><strong data-mb-favorite-count>0</strong><small>Saved Pages</small></article>
            </section>

            <section class="mb-resume-panel" data-mb-resume hidden>
                <div>
                    <span class="mb-dashboard-kicker">Continue Learning</span>
                    <h2 data-mb-resume-title>Resume your last Binder Page</h2>
                    <p data-mb-resume-section></p>
                </div>
                <a data-mb-resume-link href="#">Resume Lesson →</a>
            </section>

            <section class="mb-dashboard-section">
                <div class="mb-dashboard-heading">
                    <div>
                        <span class="mb-dashboard-kicker">Milestones</span>
                        <h2>Your MathBinder badges</h2>
                    </div>
                    <p>Badges activate as your overall completion grows.</p>
                </div>
                <div class="mb-milestone-grid">
                    <article data-milestone="25"><span>25%</span><strong>Getting Started</strong><small>Complete one quarter</small></article>
                    <article data-milestone="50"><span>50%</span><strong>Halfway There</strong><small>Complete half</small></article>
                    <article data-milestone="75"><span>75%</span><strong>Math Momentum</strong><small>Complete three quarters</small></article>
                    <article data-milestone="100"><span>100%</span><strong>Binder Master</strong><small>Complete every page</small></article>
                </div>
            </section>

            <section class="mb-dashboard-section mb-favorites-panel" data-mb-favorites-panel hidden>
                <div class="mb-dashboard-heading">
                    <div>
                        <span class="mb-dashboard-kicker">Saved for Later</span>
                        <h2>Favorite Binder Pages</h2>
                    </div>
                </div>
                <div class="mb-dashboard-lesson-grid" data-mb-favorites-list></div>
            </section>

            <section class="mb-dashboard-section">
                <div class="mb-dashboard-heading">
                    <div>
                        <span class="mb-dashboard-kicker">Binder Progress</span>
                        <h2>Progress by section</h2>
                    </div>
                    <p>Your progress is stored privately in this browser.</p>
                </div>

                <div class="mb-section-progress-grid">
                    <?php foreach ($terms as $term):
                        $pages = get_posts([
                            'post_type' => self::CPT,
                            'post_status' => 'publish',
                            'posts_per_page' => -1,
                            'orderby' => ['menu_order' => 'ASC', 'title' => 'ASC'],
                            'tax_query' => [[
                                'taxonomy' => self::TAX,
                                'field' => 'term_id',
                                'terms' => $term->term_id
                            ]]
                        ]);
                    ?>
                        <article class="mb-dashboard-section-card"
                                 data-mb-section-card
                                 data-section="<?php echo esc_attr($term->slug); ?>"
                                 data-total="<?php echo esc_attr(count($pages)); ?>">
                            <div class="mb-dashboard-section-title">
                                <div>
                                    <span>Binder Section</span>
                                    <h3><?php echo esc_html($term->name); ?></h3>
                                </div>
                                <strong data-mb-section-count>0 / <?php echo esc_html(count($pages)); ?></strong>
                            </div>
                            <div class="mb-dashboard-progress-track">
                                <span data-mb-section-fill style="width:0%"></span>
                            </div>
                            <div class="mb-dashboard-lesson-grid">
                                <?php foreach ($pages as $page): ?>
                                    <a class="mb-dashboard-lesson"
                                       href="<?php echo esc_url(get_permalink($page)); ?>"
                                       data-post-id="<?php echo esc_attr($page->ID); ?>"
                                       data-title="<?php echo esc_attr($page->post_title); ?>"
                                       data-url="<?php echo esc_url(get_permalink($page)); ?>"
                                       data-section-title="<?php echo esc_attr($term->name); ?>"
                                       data-section-slug="<?php echo esc_attr($term->slug); ?>">
                                        <span class="mb-lesson-state" aria-hidden="true">○</span>
                                        <span><?php echo esc_html($page->post_title); ?></span>
                                    </a>
                                <?php endforeach; ?>
                                <?php if (!$pages): ?>
                                    <span class="mb-dashboard-empty">Binder Pages coming soon.</span>
                                <?php endif; ?>
                            </div>
                        </article>
                    <?php endforeach; ?>
                </div>
            </section>
        </div>
        <?php
        return ob_get_clean();
    }

    public function collection_shortcode() {
        $terms = get_terms([
            'taxonomy' => self::TAX,
            'hide_empty' => false,
            'orderby' => 'meta_value_num',
            'meta_key' => 'mb_number'
        ]);

        if (is_wp_error($terms)) {
            return '<p>Your Binder is temporarily unavailable.</p>';
        }

        ob_start();
        ?>
        <div class="mb-collection-dashboard mb-my-mathbinder" data-my-mathbinder>
            <header class="mb-my-mathbinder-hero">
                <div>
                    <span class="mb-collection-kicker">Find it. Learn it. Master it.</span>
                    <h1>My MathBinder</h1>
                    <p>Everything you add from a lesson is organized here so you can reopen it, keep working, print it, or remove it.</p>
                    <div class="mb-collection-hero-actions">
                        <?php if (is_user_logged_in() && MathBinder_Capabilities::can_view_student_dashboard()) : ?>
                            <a class="mb-student-dashboard-link" href="<?php echo esc_url(home_url('/student-dashboard/')); ?>">Student Dashboard</a>
                        <?php endif; ?>
                        <a href="<?php echo esc_url(home_url('/binder-topics/')); ?>">Browse Binder Topics</a>
                    </div>
                </div>
                <div class="mb-my-mathbinder-brand">
                    <img src="<?php echo esc_url(plugin_dir_url(__FILE__) . 'Assests/Logos/mathbinder-logo.svg'); ?>" alt="MathBinder">
                    <div><strong data-mb-collected-total>0</strong><span>saved items</span></div>
                </div>
            </header>

            <section class="mb-my-binder-controls" id="my-math-notes" aria-label="Search and filter saved items">
                <label><span>Search my saved items</span><input type="search" data-my-binder-search placeholder="Search by title or math section"></label>
                <div class="mb-my-binder-filters" role="group" aria-label="Filter saved items">
                    <button type="button" class="is-active" data-binder-filter="all">All Saved Items</button>
                    <button type="button" data-binder-filter="lesson">Lessons</button>
                    <button type="button" data-binder-filter="notebook">Interactive Notebook</button>
                    <button type="button" data-binder-filter="journal">Math Journal</button>
                    <button type="button" data-binder-filter="notes">My Math Notes</button>
                    <button type="button" data-binder-filter="reference">Reference Pages</button>
                    <button type="button" data-binder-filter="practice">Practice</button>
                </div>
            </section>

            <section class="mb-my-binder-recent">
                <div class="mb-dashboard-heading">
                    <div><span class="mb-collection-kicker">Continue working</span><h2>Recently Added</h2></div>
                </div>
                <div class="mb-my-binder-card-grid" data-my-binder-recent></div>
            </section>

            <section class="mb-my-binder-sections">
                <div class="mb-dashboard-heading">
                    <div><span class="mb-collection-kicker">My saved collection</span><h2>Browse by math section</h2></div>
                    <p>Saved privately in this browser.</p>
                </div>
                <div data-my-binder-sections></div>
                <div class="mb-my-binder-empty" data-my-binder-empty>
                    <div aria-hidden="true">＋</div>
                    <h2>Your binder is empty</h2>
                    <p>Open a lesson and select <strong>Add to My Binder</strong> on a lesson, notebook page, or Math Journal entry you want to keep.</p>
                    <a href="<?php echo esc_url(home_url('/binder-topics/')); ?>">Browse Binder Topics</a>
                </div>
            </section>
        </div>
        <?php
        return ob_get_clean();
    }

    public function evidence_folder_shortcode() {
        if (!is_user_logged_in()) {
            return '<section class="mb-dashboard-gate"><h1>Your Evidence Folder is waiting</h1><p>Log in to view the lessons you have completed.</p><a class="mb-button mb-button-primary" href="' . esc_url(MathBinder_Frontend_Auth::login_url(get_permalink())) . '">Log In</a></section>';
        }

        $teacher_reviews = get_user_meta(get_current_user_id(), 'mb_teacher_evidence_reviews_v1', true);
        if (!is_array($teacher_reviews)) $teacher_reviews = [];
        $terms = get_terms([
            'taxonomy' => self::TAX,
            'hide_empty' => false,
            'orderby' => 'meta_value_num',
            'meta_key' => 'mb_number',
        ]);

        ob_start();
        ?>
        <div class="mb-collection-dashboard mb-evidence-folder" data-mb-evidence-folder>
            <header class="mb-my-mathbinder-hero">
                <div>
                    <span class="mb-collection-kicker">Your completed learning</span>
                    <h1>Evidence Folder</h1>
                    <p>Lessons you mark complete are collected here as evidence of your learning.</p>
                    <div class="mb-collection-hero-actions">
                        <a href="<?php echo esc_url(home_url('/student-dashboard/')); ?>">Student Dashboard</a>
                        <a href="<?php echo esc_url(home_url('/binder-topics/')); ?>">Explore the Binder</a>
                    </div>
                </div>
                <div class="mb-my-mathbinder-brand">
                    <img src="<?php echo esc_url(plugin_dir_url(__FILE__) . 'Assests/Logos/mathbinder-logo.svg'); ?>" alt="MathBinder">
                    <div><strong data-mb-evidence-total>0</strong><span>completed lessons</span></div>
                </div>
            </header>
            <section class="mb-my-binder-sections">
                <div class="mb-dashboard-heading"><div><span class="mb-collection-kicker">Evidence of learning</span><h2>Completed Lessons</h2></div></div>
                <div class="mb-my-binder-card-grid" data-mb-evidence-items>
                <?php if (!is_wp_error($terms)) : foreach ($terms as $term) :
                    $pages = get_posts(['post_type' => self::CPT, 'post_status' => 'publish', 'numberposts' => -1, 'tax_query' => [['taxonomy' => self::TAX, 'field' => 'term_id', 'terms' => $term->term_id]]]);
                    foreach ($pages as $page) : $teacher_review = $teacher_reviews[(string)$page->ID] ?? []; ?>
                        <article class="mb-my-binder-card" data-evidence-lesson data-section-slug="<?php echo esc_attr($term->slug); ?>" data-post-id="<?php echo esc_attr($page->ID); ?>" hidden>
                            <span class="mb-my-binder-type">Completed Lesson</span>
                            <h3><?php echo esc_html($page->post_title); ?></h3>
                            <p><?php echo esc_html($term->name); ?></p>
                            <?php if ($teacher_review): ?><div class="mb-student-teacher-review"><strong><?php echo esc_html($teacher_review['decision']==='mastered' ? 'Teacher marked this lesson mastered' : ($teacher_review['decision']==='revision_requested' ? 'Teacher requested a revision' : 'Teacher feedback')); ?></strong><span><?php echo esc_html($teacher_review['teacher_name'] ?? 'Your teacher'); ?></span><?php if (!empty($teacher_review['feedback'])): ?><p><?php echo esc_html($teacher_review['feedback']); ?></p><?php endif; ?></div><?php endif; ?>
                            <div class="mb-my-binder-actions"><a href="<?php echo esc_url(get_permalink($page)); ?>">Review Lesson</a></div>
                        </article>
                    <?php endforeach; endforeach; endif; ?>
                </div>
                <div class="mb-my-binder-empty" data-mb-evidence-empty>
                    <div aria-hidden="true">✓</div><h2>No completed lessons yet</h2>
                    <p>When you mark a lesson complete, it will appear here as evidence of your learning.</p>
                    <a href="<?php echo esc_url(home_url('/binder-topics/')); ?>">Choose a Math Topic</a>
                </div>
            </section>
            <?php echo MathBinder_External_Practice::render_student_section(get_current_user_id(), $teacher_reviews); ?>
        </div>
        <?php
        return ob_get_clean();
    }

    private function section_page_url($term) {
        if (!$term || is_wp_error($term) || empty($term->slug)) {
            return home_url('/binder-topics/');
        }

        $page = get_page_by_path('binder-topics/' . $term->slug, OBJECT, 'page');
        if ($page) {
            return get_permalink($page);
        }

        return home_url('/binder-topics/');
    }

    public function section_shortcode($atts = []) {
        $atts = shortcode_atts(['slug' => ''], $atts, 'mathbinder_section');
        $slug = sanitize_title($atts['slug']);

        if ($slug === '') {
            $page_id = get_queried_object_id();
            $slug = sanitize_title((string) get_post_meta($page_id, '_mb_section_slug', true));
        }

        $term = $slug ? get_term_by('slug', $slug, self::TAX) : false;
        if (!$term || is_wp_error($term)) {
            return '<div class="mb-empty-section"><h2>Binder Section unavailable.</h2><p>Please return to Binder Topics and choose a section.</p></div>';
        }

        $topic_map = MathBinder_Lesson_Catalog::get_section_topic_map();
        $planned = isset($topic_map[$term->slug]) ? $topic_map[$term->slug] : [
            'description' => '',
            'topics' => []
        ];
        $planned_topics = [];

        foreach (($planned['topics'] ?? []) as $topic) {
            $title = is_array($topic) ? ($topic['title'] ?? '') : $topic;
            $title = trim((string) $title);
            if ($title !== '') {
                $planned_topics[] = $title;
            }
        }

        if (!$planned_topics) {
            foreach (['primary_topics', 'nested_topics'] as $group) {
                foreach (($planned[$group] ?? []) as $topic) {
                    $title = is_array($topic) ? ($topic['title'] ?? '') : $topic;
                    $title = trim((string) $title);
                    if ($title !== '') {
                        $planned_topics[] = $title;
                    }
                }
            }
        }

        $published_pages = get_posts([
            'post_type' => self::CPT,
            'post_status' => 'publish',
            'posts_per_page' => -1,
            'tax_query' => [[
                'taxonomy' => self::TAX,
                'field' => 'term_id',
                'terms' => $term->term_id
            ]],
            'orderby' => ['menu_order' => 'ASC', 'title' => 'ASC']
        ]);

        $published_by_title = [];
        foreach ($published_pages as $page) {
            $published_by_title[strtolower(trim($page->post_title))] = $page;
        }

        ob_start();
        ?>
        <main class="mb-page-wrap mb-section-archive mb-section-page">
            <nav class="mb-breadcrumbs" aria-label="Breadcrumb">
                <a href="<?php echo esc_url(home_url('/')); ?>">Home</a><span>›</span>
                <a href="<?php echo esc_url(home_url('/binder-topics/')); ?>">Binder Topics</a><span>›</span>
                <span aria-current="page"><?php echo esc_html($term->name); ?></span>
            </nav>

            <header class="mb-chapter-header">
                <span class="mb-chapter-label">Binder Section</span>
                <h1><?php echo esc_html($term->name); ?></h1>
                <p><?php echo esc_html(!empty($planned['description'])
                    ? $planned['description']
                    : 'Open a Binder Page to find instruction, videos, practice, downloads, parent help, and a mastery check.'); ?></p>
            </header>

            <div class="mb-chapter-page-grid">
                <?php if ($planned_topics): ?>
                    <?php foreach ($planned_topics as $index => $topic):
                        $page = $published_by_title[strtolower(trim($topic))] ?? null;
                        $is_published = $page && $page->post_status === 'publish';
                    ?>
                        <?php if ($is_published): ?>
                            <a class="mb-chapter-page-card is-published" href="<?php echo esc_url(get_permalink($page)); ?>">
                                <span class="mb-page-number"><?php echo esc_html(str_pad($index + 1, 2, '0', STR_PAD_LEFT)); ?></span>
                                <div>
                                    <span class="mb-topic-status">Available Now</span>
                                    <h2><?php echo esc_html($topic); ?></h2>
                                    <?php $summary = get_post_meta($page->ID, '_mb_subtitle', true); ?>
                                    <?php if ($summary): ?><p><?php echo esc_html($summary); ?></p><?php endif; ?>
                                    <div class="mb-topic-features">
                                        <span>Teach It</span><span>Watch It</span><span>Practice It</span><span>Master It</span>
                                    </div>
                                </div>
                                <span class="mb-card-arrow">Open →</span>
                            </a>
                        <?php else: ?>
                            <article class="mb-chapter-page-card is-coming">
                                <span class="mb-page-number"><?php echo esc_html(str_pad($index + 1, 2, '0', STR_PAD_LEFT)); ?></span>
                                <div>
                                    <span class="mb-topic-status">Coming Soon</span>
                                    <h2><?php echo esc_html($topic); ?></h2>
                                    <p>This Binder Page is part of the planned section sequence.</p>
                                </div>
                            </article>
                        <?php endif; ?>
                    <?php endforeach; ?>
                <?php elseif ($published_pages): ?>
                    <?php foreach ($published_pages as $index => $page): ?>
                        <a class="mb-chapter-page-card is-published" href="<?php echo esc_url(get_permalink($page)); ?>">
                            <span class="mb-page-number"><?php echo esc_html(str_pad($index + 1, 2, '0', STR_PAD_LEFT)); ?></span>
                            <div>
                                <span class="mb-topic-status">Available Now</span>
                                <h2><?php echo esc_html($page->post_title); ?></h2>
                            </div>
                            <span class="mb-card-arrow">Open →</span>
                        </a>
                    <?php endforeach; ?>
                <?php else: ?>
                    <div class="mb-empty-section">
                        <h2>Binder Pages are coming soon.</h2>
                        <p>This section is ready for content.</p>
                    </div>
                <?php endif; ?>
            </div>
        </main>
        <?php
        return ob_get_clean();
    }

    public function topics_shortcode() {
        $topic_map = MathBinder_Lesson_Catalog::get_section_topic_map();
        $terms = get_terms([
            'taxonomy' => self::TAX,
            'hide_empty' => false,
            'orderby' => 'meta_value_num',
            'meta_key' => 'mb_number'
        ]);

        if (is_wp_error($terms) || !$terms) {
            return '<p>No Binder Sections are available yet.</p>';
        }

        $selected_catalog_section = isset($_GET['mb_section']) ? sanitize_key(wp_unslash($_GET['mb_section'])) : '';

        ob_start();
        ?>
        <div class="mb-topics-notebook">
            <?php if (!$selected_catalog_section): ?>
            <header class="mb-topics-notebook-hero">
                <span class="mb-topics-kicker">Standards-Based Math Library</span>
                <h1>Browse Binder Topics by Domain</h1>
                <p>Find a mathematical domain, see the grades it covers, and open the lessons available in MathBinder.</p>

                <form class="mb-topics-notebook-search" role="search" method="get" action="<?php echo esc_url(home_url('/')); ?>">
                    <input type="search" name="s" placeholder="Search for a math topic" required>
                    <input type="hidden" name="post_type" value="<?php echo esc_attr(self::CPT); ?>">
                    <button type="submit">Search MathBinder</button>
                </form>
            </header>
            <?php endif; ?>

            <?php echo $this->render_k_calculus_catalog($selected_catalog_section); ?>

            <?php if (!$selected_catalog_section): ?>
            <details class="mb-legacy-collections">
                <summary><span><strong>Published MathBinder lesson collections</strong><small>Open the original eight collections and their available Binder Pages.</small></span><b><?php echo esc_html(count($terms)); ?> collections</b></summary>
            <main class="mb-notebook-page-grid">
                <?php foreach ($terms as $index => $term):
                    $number = intval(get_term_meta($term->term_id, 'mb_number', true));
                    $section_map = isset($topic_map[$term->slug]) ? $topic_map[$term->slug] : [
                        'description' => '',
                        'inventory_status' => 'not_started',
                        'topics' => [],
                        'primary_topics' => [],
                        'nested_topics' => []
                    ];
                    $published = get_posts([
                        'post_type' => self::CPT,
                        'post_status' => 'publish',
                        'posts_per_page' => -1,
                        'fields' => 'ids',
                        'tax_query' => [[
                            'taxonomy' => self::TAX,
                            'field' => 'term_id',
                            'terms' => $term->term_id
                        ]]
                    ]);

                    /*
                     * Sections added after the original PDF catalog may have
                     * published lesson pages without duplicate static catalog
                     * entries. Use those live pages as the card hierarchy so
                     * the overview never hides existing lessons or reports a
                     * misleading zero count.
                     */
                    if (empty($section_map['primary_topics']) && !empty($published)) {
                        $section_map['primary_topics'] = array_map(static function ($post_id) {
                            return [
                                'slug' => get_post_field('post_name', $post_id),
                                'title' => get_the_title($post_id),
                            ];
                        }, $published);
                    }
                ?>
                    <article class="mb-notebook-page mb-notebook-page-<?php echo esc_attr($number); ?>">
                        <span class="mb-notebook-tab">
                            <span><?php echo esc_html($number); ?></span>
                            <?php echo esc_html($term->name); ?>
                        </span>

                        <div class="mb-notebook-holes" aria-hidden="true">
                            <i></i><i></i><i></i>
                        </div>

                        <div class="mb-notebook-content">
                            <div class="mb-notebook-title-row">
                                <div>
                                    <span class="mb-notebook-label">Binder Section <?php echo esc_html($number); ?></span>
                                    <h2><?php echo esc_html($term->name); ?></h2>
                                </div>
                            </div>

                            <div class="mb-notebook-rule"></div>

                            <p class="mb-notebook-intro"><?php echo esc_html(!empty($section_map['description']) ? $section_map['description'] : 'Open this section to explore the verified PDF hierarchy.'); ?></p>

                            <?php if (!empty($section_map['primary_topics'])): ?>
                                <div class="mb-notebook-topic-group">
                                    <strong>Primary Lessons</strong>
                                    <ul class="mb-notebook-topic-list">
                                        <?php foreach ($section_map['primary_topics'] as $topic): ?>
                                            <li><?php echo esc_html($topic['title']); ?></li>
                                        <?php endforeach; ?>
                                    </ul>
                                </div>
                            <?php endif; ?>

                            <?php if (!empty($section_map['nested_topics'])): ?>
                                <div class="mb-notebook-topic-group">
                                    <strong>Nested Subsections</strong>
                                    <ul class="mb-notebook-topic-list">
                                        <?php foreach ($section_map['nested_topics'] as $topic): ?>
                                            <li><?php echo esc_html($topic['title']); ?></li>
                                        <?php endforeach; ?>
                                    </ul>
                                </div>
                            <?php endif; ?>

                            <div class="mb-notebook-status">
                                <span><?php echo esc_html(count($published)); ?> available now</span>
                                <span><?php echo esc_html(count($section_map['primary_topics'])); ?> primary lessons</span>
                                <span><?php echo esc_html(count($section_map['nested_topics'])); ?> nested subsections</span>
                            </div>

                            <a class="mb-notebook-open" href="<?php echo esc_url($this->section_page_url($term)); ?>">
                                Open Section →
                            </a>
                        </div>
                    </article>
                <?php endforeach; ?>
            </main>
            </details>

            <section class="mb-notebook-help">
                <div>
                    <span class="mb-topics-kicker">Not sure where to begin?</span>
                    <h2>Start with Place Value.</h2>
                    <p>Place Value is the first page in The Number System and the foundation for understanding whole numbers and decimals.</p>
                </div>
                <?php $place_value = get_page_by_path('place-value', OBJECT, self::CPT); ?>
                <?php if ($place_value): ?>
                    <a href="<?php echo esc_url(get_permalink($place_value)); ?>">Open Place Value →</a>
                <?php endif; ?>
            </section>
            <?php endif; ?>
</div>
        <?php
        return ob_get_clean();
    }

    private function render_k_calculus_catalog($selected_section = '') {
        $lessons = MathBinder_K_Calculus_Catalog::lessons();
        $domain_definitions = [
            'counting-cardinality' => ['label'=>'Counting & Cardinality','band'=>'Kindergarten','code'=>'K.CC','url'=>'https://www.thecorestandards.org/Math/Content/CC/'],
            'operations-algebraic-thinking' => ['label'=>'Operations & Algebraic Thinking','band'=>'Grades K–5','code'=>'K–5.OA','url'=>'https://www.thecorestandards.org/Math/Content/OA/'],
            'number-base-ten' => ['label'=>'Number & Operations in Base Ten','band'=>'Grades K–5','code'=>'K–5.NBT','url'=>'https://www.thecorestandards.org/Math/Content/NBT/'],
            'number-fractions' => ['label'=>'Number & Operations—Fractions','band'=>'Grades 3–5','code'=>'3–5.NF','url'=>'https://www.thecorestandards.org/Math/Content/NF/'],
            'measurement-data' => ['label'=>'Measurement & Data','band'=>'Grades K–5','code'=>'K–5.MD','url'=>'https://www.thecorestandards.org/Math/Content/MD/'],
            'geometry' => ['label'=>'Geometry','band'=>'Grades K–8','code'=>'K–8.G','url'=>'https://www.thecorestandards.org/Math/Content/G/'],
            'ratios-proportional' => ['label'=>'Ratios & Proportional Relationships','band'=>'Grades 6–7','code'=>'6–7.RP','url'=>'https://www.thecorestandards.org/Math/Content/RP/'],
            'number-system' => ['label'=>'The Number System','band'=>'Grades 6–8','code'=>'6–8.NS','url'=>'https://www.thecorestandards.org/Math/Content/NS/'],
            'expressions-equations' => ['label'=>'Expressions & Equations','band'=>'Grades 6–8','code'=>'6–8.EE','url'=>'https://www.thecorestandards.org/Math/Content/EE/'],
            'functions' => ['label'=>'Functions','band'=>'Grade 8','code'=>'8.F','url'=>'https://www.thecorestandards.org/Math/Content/F/'],
            'statistics-probability' => ['label'=>'Statistics & Probability','band'=>'Grades 6–8','code'=>'6–8.SP','url'=>'https://www.thecorestandards.org/Math/Content/SP/'],
        ];
        $domain_aliases = [
            'Counting & Cardinality'=>'counting-cardinality','Operations & Algebraic Thinking'=>'operations-algebraic-thinking',
            'Number & Operations in Base Ten'=>'number-base-ten','Fractions'=>'number-fractions','Measurement & Data'=>'measurement-data',
            'Geometry'=>'geometry','Ratios & Proportional Relationships'=>'ratios-proportional','The Number System'=>'number-system',
            'Expressions & Equations'=>'expressions-equations','Functions'=>'functions','Statistics & Probability'=>'statistics-probability',
        ];
        $course_definitions = [
            'algebra-1'=>['label'=>'Algebra I','band'=>'High School','code'=>'HS Algebra & Functions'],
            'geometry'=>['label'=>'Geometry','band'=>'High School','code'=>'HS Geometry'],
            'algebra-2'=>['label'=>'Algebra II','band'=>'High School','code'=>'HS Algebra, Functions & Statistics'],
            'precalculus'=>['label'=>'Precalculus','band'=>'High School / Advanced','code'=>'Advanced preparation'],
            'calculus'=>['label'=>'Calculus','band'=>'Advanced / College Preparation','code'=>'Calculus AB & BC'],
        ];
        $course_aliases = ['algebra-1'=>'algebra-1','geometry'=>'geometry','algebra-2'=>'algebra-2','statistics'=>'algebra-2','precalculus'=>'precalculus','calculus-ab'=>'calculus','calculus-bc'=>'calculus'];
        $grade_band_keys = ['k'=>'k-2','1'=>'k-2','2'=>'k-2','3'=>'3-5','4'=>'3-5','5'=>'3-5','6'=>'6-8','7'=>'6-8','8'=>'6-8'];
        $domain_groups = array_fill_keys(array_keys($domain_definitions), []);
        $course_groups = array_fill_keys(array_keys($course_definitions), []);
        foreach ($lessons as $lesson) {
            if (isset($grade_band_keys[$lesson['pathway']])) {
                $domain_key = $domain_aliases[$lesson['domain']] ?? '';
                if ($domain_key && isset($domain_groups[$domain_key])) {
                    $lesson['grade_band_key'] = $grade_band_keys[$lesson['pathway']];
                    $domain_groups[$domain_key][] = $lesson;
                }
            } else {
                $course_key = $course_aliases[$lesson['pathway']] ?? '';
                if ($course_key && isset($course_groups[$course_key])) {
                    $lesson['grade_band_key'] = 'high-school';
                    $lesson['pathway_label'] = $course_definitions[$course_key]['label'];
                    $course_groups[$course_key][] = $lesson;
                }
            }
        }

        /*
         * Keep the original MathBinder catalog visible inside the newer
         * standards-domain workflow. These are navigation aliases only: the
         * canonical legacy slugs and their published posts are never renamed,
         * copied, or deleted.
         */
        $legacy_section_targets = [
            'ratios-proportional-relationships' => [
                ['type'=>'domain','key'=>'ratios-proportional','group'=>'Ratios & Proportional Relationships'],
                ['type'=>'course','key'=>'algebra-1','group'=>'Proportional Relationships'],
            ],
            'algebraic-expressions' => [
                ['type'=>'domain','key'=>'expressions-equations','group'=>'Algebraic Expressions'],
                ['type'=>'course','key'=>'algebra-1','group'=>'Expressions'],
            ],
            'solving-graphing-equations' => [
                ['type'=>'domain','key'=>'expressions-equations','group'=>'Linear Equations & Inequalities'],
                ['type'=>'course','key'=>'algebra-1','group'=>'Linear Equations & Inequalities'],
            ],
            'solving-graphing-inequalities' => [
                ['type'=>'domain','key'=>'expressions-equations','group'=>'Linear Equations & Inequalities'],
                ['type'=>'course','key'=>'algebra-1','group'=>'Linear Equations & Inequalities'],
            ],
            'triangles-transformations' => [
                ['type'=>'domain','key'=>'geometry','group'=>'Triangles & Transformations'],
                ['type'=>'course','key'=>'geometry','group'=>'Triangles & Transformations'],
            ],
            'volume-area' => [
                ['type'=>'domain','key'=>'measurement-data','group'=>'Area & Volume'],
                ['type'=>'domain','key'=>'geometry','group'=>'Area & Volume'],
                ['type'=>'course','key'=>'geometry','group'=>'Area & Volume'],
            ],
            'probability-statistics' => [
                ['type'=>'domain','key'=>'statistics-probability','group'=>'Probability & Statistics'],
                ['type'=>'course','key'=>'algebra-2','group'=>'Probability & Statistics'],
            ],
        ];
        $add_legacy_lesson = static function (&$target_group, $entry, $placement_group, $pathway_label, $domain_label, $grade_band_key) {
            $entry_slug = $entry['slug'] ?? '';
            $entry_title = strtolower(trim($entry['title'] ?? ''));
            foreach ($target_group as $existing) {
                $existing_slug = $existing['canonical_slug'] ?? ($existing['slug'] ?? '');
                if (($entry_slug && $entry_slug === $existing_slug) || ($entry_title && $entry_title === strtolower(trim($existing['title'] ?? '')))) {
                    return;
                }
            }
            $target_group[] = [
                'title' => $entry['title'],
                'slug' => $entry_slug,
                'canonical_slug' => $entry_slug,
                'domain' => $domain_label,
                'pathway_label' => $pathway_label,
                'grade_band_key' => $grade_band_key,
                'placement_group' => $placement_group,
                'is_legacy' => true,
                'sequence' => $entry['sequence'] ?? 999,
            ];
        };
        foreach (MathBinder_Lesson_Catalog::get_entries() as $legacy_entry) {
            $section_slug = $legacy_entry['section'] ?? '';
            $legacy_slug = $legacy_entry['slug'] ?? '';

            /*
             * The original Number System section spans several standards
             * domains. Route each canonical lesson to the notebook where a
             * teacher or student would now expect to find it.
             */
            if ($section_slug === 'the-number-system') {
                if ($legacy_slug === 'place-value') {
                    $definition = $domain_definitions['number-base-ten'];
                    $add_legacy_lesson($domain_groups['number-base-ten'], $legacy_entry, 'Original Place Value Lessons', $definition['band'], $definition['label'], '3-5');
                } elseif (strpos($legacy_slug, 'number-operations') === 0) {
                    foreach (['operations-algebraic-thinking', 'number-base-ten'] as $domain_key) {
                        $definition = $domain_definitions[$domain_key];
                        $add_legacy_lesson($domain_groups[$domain_key], $legacy_entry, 'Original Number Operations Lessons', $definition['band'], $definition['label'], '3-5');
                    }
                } elseif ($legacy_slug === 'fractions-decimals') {
                    $definition = $domain_definitions['number-fractions'];
                    $add_legacy_lesson($domain_groups['number-fractions'], $legacy_entry, 'Original Fractions & Decimals Lessons', $definition['band'], $definition['label'], '3-5');
                } elseif ($legacy_slug === 'order-of-operations-pemdas') {
                    $definition = $domain_definitions['expressions-equations'];
                    $add_legacy_lesson($domain_groups['expressions-equations'], $legacy_entry, 'Original Expressions Lessons', $definition['band'], $definition['label'], '6-8');
                    $definition = $course_definitions['algebra-1'];
                    $add_legacy_lesson($course_groups['algebra-1'], $legacy_entry, 'Expressions', $definition['label'], $definition['label'], 'high-school');
                } elseif (strpos($legacy_slug, 'real-complex-number-systems') === 0) {
                    $definition = $domain_definitions['number-system'];
                    $add_legacy_lesson($domain_groups['number-system'], $legacy_entry, 'Real & Complex Number Systems', $definition['band'], $definition['label'], '6-8');
                    $definition = $course_definitions['algebra-2'];
                    $add_legacy_lesson($course_groups['algebra-2'], $legacy_entry, 'Real & Complex Number Systems', $definition['label'], $definition['label'], 'high-school');
                }
            }
            foreach ($legacy_section_targets[$section_slug] ?? [] as $target) {
                if ($target['type'] === 'domain' && isset($domain_groups[$target['key']])) {
                    $definition = $domain_definitions[$target['key']];
                    $add_legacy_lesson($domain_groups[$target['key']], $legacy_entry, $target['group'], $definition['band'], $definition['label'], $target['key'] === 'measurement-data' ? '3-5' : '6-8');
                } elseif ($target['type'] === 'course' && isset($course_groups[$target['key']])) {
                    $definition = $course_definitions[$target['key']];
                    $add_legacy_lesson($course_groups[$target['key']], $legacy_entry, $target['group'], $definition['label'], $definition['label'], 'high-school');
                }
            }
            if (($legacy_entry['slug'] ?? '') === 'graphing-linear-equations') {
                $definition = $domain_definitions['functions'];
                $add_legacy_lesson($domain_groups['functions'], $legacy_entry, 'Linear Relationships', $definition['band'], $definition['label'], '6-8');
            }
        }
        $published_posts = get_posts(['post_type'=>self::CPT,'post_status'=>'publish','posts_per_page'=>-1]);
        $published_by_title = [];
        $published_by_slug = [];
        foreach ($published_posts as $published_post) {
            $published_by_title[strtolower(trim($published_post->post_title))] = $published_post;
            $published_by_slug[$published_post->post_name] = $published_post;
        }

        $catalog_url = get_permalink();
        if ($selected_section) {
            $selected_is_domain = strpos($selected_section, 'domain-') === 0;
            $selected_is_course = strpos($selected_section, 'course-') === 0;
            $selected_key = $selected_is_domain ? substr($selected_section, 7) : ($selected_is_course ? substr($selected_section, 7) : '');
            $selected_definition = $selected_is_domain ? ($domain_definitions[$selected_key] ?? null) : ($selected_is_course ? ($course_definitions[$selected_key] ?? null) : null);
            $selected_lessons = $selected_is_domain ? ($domain_groups[$selected_key] ?? []) : ($selected_is_course ? ($course_groups[$selected_key] ?? []) : []);

            if ($selected_definition) {
                ob_start(); ?>
                <section class="mb-section-notebook-view">
                    <a class="mb-section-notebook-back" href="<?php echo esc_url($catalog_url); ?>">← Back to all Binder Sections</a>
                    <article class="mb-section-notebook-sheet">
                        <span class="mb-section-notebook-tab"><?php echo esc_html($selected_definition['band']); ?></span>
                        <div class="mb-section-notebook-holes" aria-hidden="true"><i></i><i></i><i></i><i></i></div>
                        <div class="mb-section-notebook-body">
                            <span class="mb-topics-kicker"><?php echo esc_html($selected_is_domain ? 'Standards Domain' : 'Course Pathway'); ?></span>
                            <h1><?php echo esc_html($selected_definition['label']); ?></h1>
                            <p class="mb-section-notebook-meta"><?php echo esc_html($selected_definition['band'].' · '.$selected_definition['code']); ?><?php if ($selected_is_domain): ?> · <a href="<?php echo esc_url($selected_definition['url']); ?>" target="_blank" rel="noopener">View standards</a><?php endif; ?></p>
                            <div class="mb-section-notebook-rule"></div>
                            <h2>Subtopics</h2>
                            <p class="mb-section-notebook-instructions">Choose a subtopic to open its MathBinder lesson.</p>
                            <div class="mb-section-subtopic-links">
                                <?php $last_placement_group = null; foreach ($selected_lessons as $lesson):
                                    $placement_group = $lesson['placement_group'] ?? '';
                                    if ($placement_group && $placement_group !== $last_placement_group): ?>
                                        <div class="mb-section-subtopic-group"><?php echo esc_html($placement_group); ?></div>
                                    <?php endif;
                                    $last_placement_group = $placement_group ?: $last_placement_group;
                                    $canonical_slug = $lesson['canonical_slug'] ?? ($lesson['slug'] ?? '');
                                    $published = ($canonical_slug && isset($published_by_slug[$canonical_slug])) ? $published_by_slug[$canonical_slug] : ($published_by_title[strtolower(trim($lesson['title']))] ?? null); ?>
                                    <?php if ($published): ?>
                                        <a class="mb-section-subtopic-link is-available" href="<?php echo esc_url(get_permalink($published)); ?>"><span><strong><?php echo esc_html($lesson['title']); ?></strong><small><?php echo esc_html($selected_is_domain ? $lesson['pathway_label'] : $lesson['domain']); ?></small></span><b>Open lesson →</b></a>
                                    <?php else: ?>
                                        <div class="mb-section-subtopic-link is-planned"><span><strong><?php echo esc_html($lesson['title']); ?></strong><small><?php echo esc_html($selected_is_domain ? $lesson['pathway_label'] : $lesson['domain']); ?></small></span><b>Lesson coming soon</b></div>
                                    <?php endif; ?>
                                <?php endforeach; ?>
                            </div>
                        </div>
                    </article>
                </section>
                <?php return ob_get_clean();
            }
        }

        ob_start(); ?>
        <section class="mb-curriculum-catalog" data-mb-curriculum-catalog>
            <header class="mb-curriculum-catalog-heading">
                <div><span class="mb-topics-kicker">Common Core Mathematics · Core <?php echo esc_html(self::VERSION); ?></span><h2>Standards Domains and Course Pathways</h2><p>Choose a Binder Section to open its notebook page and lesson subtopics.</p></div>
            </header>
            <div class="mb-curriculum-filters">
                <label>Find a topic<input type="search" data-mb-catalog-search placeholder="Example: fractions, equations, derivatives"></label>
                <label>Grade band<select data-mb-catalog-grade><option value="">All grade bands</option><option value="k-2">Grades K–2</option><option value="3-5">Grades 3–5</option><option value="6-8">Grades 6–8</option><option value="high-school">High School and Advanced</option></select></label>
                <label>Domain or course<select data-mb-catalog-group><option value="">All domains and courses</option><?php foreach ($domain_definitions as $key=>$domain): ?><option value="<?php echo esc_attr('domain-'.$key); ?>"><?php echo esc_html($domain['label']); ?></option><?php endforeach; ?><?php foreach ($course_definitions as $key=>$course): ?><option value="<?php echo esc_attr('course-'.$key); ?>"><?php echo esc_html($course['label']); ?></option><?php endforeach; ?></select></label>
            </div>
            <div class="mb-curriculum-section-heading"><span>Kindergarten–Grade 8</span><h3>Binder Sections by Standards Domain</h3><p>Every domain is visible at once. Select a card to jump to its notebook page and subtopics.</p></div>
            <nav class="mb-domain-card-grid" aria-label="Standards domain Binder Sections">
                <?php $card_number=1; foreach ($domain_definitions as $domain_key=>$domain): ?>
                    <a class="mb-domain-card mb-domain-card-<?php echo esc_attr((($card_number-1)%4)+1); ?>" data-mb-catalog-card data-grade-bands="<?php echo esc_attr(implode(' ',array_values(array_unique(array_column($domain_groups[$domain_key],'grade_band_key'))))); ?>" data-group="<?php echo esc_attr('domain-'.$domain_key); ?>" data-search="<?php echo esc_attr(strtolower($domain['label'].' '.$domain['band'].' '.implode(' ',array_column($domain_groups[$domain_key],'title')))); ?>" href="<?php echo esc_url(add_query_arg('mb_section','domain-'.$domain_key,$catalog_url)); ?>">
                        <span><?php echo esc_html(str_pad($card_number,2,'0',STR_PAD_LEFT)); ?></span><h3><?php echo esc_html($domain['label']); ?></h3><p><?php echo esc_html($domain['band']); ?></p><strong>Open notebook page →</strong>
                    </a>
                <?php $card_number++; endforeach; ?>
            </nav>
            <div class="mb-curriculum-section-heading mb-curriculum-high-school-heading"><span>High School and Advanced Mathematics</span><h3>Binder Sections by Course</h3><p>The high-school sequence is grouped into familiar course names.</p></div>
            <nav class="mb-domain-card-grid mb-course-card-grid" aria-label="High school course Binder Sections">
                <?php foreach ($course_definitions as $course_key=>$course): ?>
                    <a class="mb-domain-card mb-domain-card-<?php echo esc_attr((($card_number-1)%4)+1); ?>" data-mb-catalog-card data-grade-bands="high-school" data-group="<?php echo esc_attr('course-'.$course_key); ?>" data-search="<?php echo esc_attr(strtolower($course['label'].' '.$course['band'].' '.implode(' ',array_column($course_groups[$course_key],'title')))); ?>" href="<?php echo esc_url(add_query_arg('mb_section','course-'.$course_key,$catalog_url)); ?>">
                        <span><?php echo esc_html(str_pad($card_number,2,'0',STR_PAD_LEFT)); ?></span><h3><?php echo esc_html($course['label']); ?></h3><p><?php echo esc_html($course['band']); ?></p><strong>Open notebook page →</strong>
                    </a>
                <?php $card_number++; endforeach; ?>
            </nav>

            <div class="mb-curriculum-legend"><strong>How availability works</strong><p>“Available now” opens a published MathBinder lesson. “Planned” means the topic belongs in the standards-aligned map but its full instruction, examples, visual practice, accessibility, and mastery checks are still being developed and reviewed.</p></div>
        </section>
        <?php return ob_get_clean();
    }

    public function ajax_topic_search() {
        check_ajax_referer('mb_topic_search_nonce', 'nonce');
        $query = isset($_GET['q']) ? sanitize_text_field(wp_unslash($_GET['q'])) : '';
        if (mb_strlen($query) < 2) wp_send_json_success([]);

        $posts = get_posts([
            'post_type' => self::CPT,
            'post_status' => 'publish',
            'posts_per_page' => 8,
            's' => $query,
            'orderby' => 'relevance'
        ]);

        $results = [];
        foreach ($posts as $post) {
            $terms = wp_get_post_terms($post->ID, self::TAX, ['fields' => 'names']);
            $results[] = [
                'title' => $post->post_title,
                'url' => get_permalink($post),
                'section' => $terms ? $terms[0] : 'Binder Page',
                'summary' => get_post_meta($post->ID, '_mb_subtitle', true)
            ];
        }
        wp_send_json_success($results);
    }

    private function section_topic_map() {
        return MathBinder_Lesson_Catalog::get_section_topic_map();
    }

    public function columns($columns) {
        $new = [];
        foreach ($columns as $key => $label) {
            $new[$key] = $label;
            if ($key === 'title') $new['mb_completion'] = 'Completion';
        }
        return $new;
    }

    public function column_content($column, $post_id) {
        if ($column !== 'mb_completion') return;
        $completion = $this->lesson_completion_data($post_id);
        echo '<strong>' . esc_html($completion['percent']) . '%</strong>';
        echo '<br><small>' . esc_html($completion['complete_count']) . ' / ' . esc_html($completion['total']) . ' complete</small>';
        if ($completion['missing']) {
            echo '<details><summary>' . esc_html(count($completion['missing'])) . ' missing items</summary><ul>';
            foreach ($completion['missing'] as $label) {
                echo '<li>' . esc_html($label) . '</li>';
            }
            echo '</ul></details>';
        }
    }

    private function complete_number_system_lessons() {
        $content_file = __DIR__ . '/content/number-system-completion.php';
        if (!is_readable($content_file)) return;

        $lessons = require $content_file;
        if (!is_array($lessons)) return;

        foreach ($lessons as $slug => $fields) {
            $lesson = get_page_by_path($slug, OBJECT, self::CPT);
            if (!$lesson || !is_array($fields)) continue;

            foreach ($fields as $key => $value) {
                if (!array_key_exists($key, $this->lesson_builder_required_fields())) continue;
                if ($this->lesson_field_is_complete($lesson->ID, $key)) continue;
                update_post_meta($lesson->ID, '_mb_' . $key, $value);
            }

            $completion = $this->lesson_completion_data($lesson->ID);
            update_post_meta($lesson->ID, '_mb_gold_certification_percent', $completion['percent']);
            update_post_meta($lesson->ID, '_mb_gold_certification_missing', $completion['missing']);
            if (!$completion['missing']) {
                update_post_meta($lesson->ID, '_mb_lesson_status', 'gold-certified');
                update_post_meta($lesson->ID, '_mb_gold_certification', 'gold-ready');
            }
        }

        // Repair the one Fractions & Decimals field that could remain
        // incomplete after the 30.13.0 bulk-content upgrade.
        $fractions = $this->find_fractions_decimals_lesson();
        if ($fractions && !$this->lesson_field_is_complete($fractions->ID, 'challenge_practice')) {
            update_post_meta(
                $fractions->ID,
                '_mb_challenge_practice',
                "Explain why a fraction in simplest form has a terminating decimal only when its denominator has no prime factors other than 2 and 5.\nCreate three different fractions between 0.4 and 0.5 and justify their placement."
            );

            $completion = $this->lesson_completion_data($fractions->ID);
            update_post_meta($fractions->ID, '_mb_gold_certification_percent', $completion['percent']);
            update_post_meta($fractions->ID, '_mb_gold_certification_missing', $completion['missing']);
            if (!$completion['missing']) {
                update_post_meta($fractions->ID, '_mb_lesson_status', 'gold-certified');
                update_post_meta($fractions->ID, '_mb_gold_certification', 'gold-ready');
            }
        }
    }

    private function complete_ratio_lessons() {
        $content_file = __DIR__ . '/content/ratios-proportional-completion.php';
        if (!is_readable($content_file)) return;
        $lessons = require $content_file;
        if (!is_array($lessons)) return;

        foreach ($lessons as $slug => $fields) {
            $lesson = get_page_by_path($slug, OBJECT, self::CPT);
            if (!$lesson || !is_array($fields)) continue;
            foreach ($fields as $key => $value) {
                if (!array_key_exists($key, $this->lesson_builder_required_fields())) continue;
                if ($this->lesson_field_is_complete($lesson->ID, $key)) continue;
                update_post_meta($lesson->ID, '_mb_' . $key, $value);
            }
            $completion = $this->lesson_completion_data($lesson->ID);
            update_post_meta($lesson->ID, '_mb_gold_certification_percent', $completion['percent']);
            update_post_meta($lesson->ID, '_mb_gold_certification_missing', $completion['missing']);
            if (!$completion['missing']) {
                update_post_meta($lesson->ID, '_mb_lesson_status', 'gold-certified');
                update_post_meta($lesson->ID, '_mb_gold_certification', 'gold-ready');
            }
        }
    }

    private function complete_algebraic_expression_lessons() {
        $content_file = __DIR__ . '/content/algebraic-expressions-completion.php';
        if (!is_readable($content_file)) return;
        $lessons = require $content_file;
        if (!is_array($lessons)) return;

        foreach ($lessons as $slug => $fields) {
            $lesson = get_page_by_path($slug, OBJECT, self::CPT);
            if (!$lesson || !is_array($fields)) continue;
            foreach ($fields as $key => $value) {
                if (!array_key_exists($key, $this->lesson_builder_required_fields())) continue;
                if ($this->lesson_field_is_complete($lesson->ID, $key)) continue;
                update_post_meta($lesson->ID, '_mb_' . $key, $value);
            }
            $completion = $this->lesson_completion_data($lesson->ID);
            update_post_meta($lesson->ID, '_mb_gold_certification_percent', $completion['percent']);
            update_post_meta($lesson->ID, '_mb_gold_certification_missing', $completion['missing']);
            if (!$completion['missing']) {
                update_post_meta($lesson->ID, '_mb_lesson_status', 'gold-certified');
                update_post_meta($lesson->ID, '_mb_gold_certification', 'gold-ready');
            }
        }
    }

    private function find_lesson_by_slug_or_title($slug, $title) {
        $lesson = get_page_by_path($slug, OBJECT, self::CPT);
        if ($lesson) return $lesson;

        $candidates = get_posts([
            'post_type' => self::CPT,
            'post_status' => ['publish', 'draft', 'pending', 'private', 'future'],
            'posts_per_page' => -1,
            'orderby' => 'ID',
            'order' => 'ASC'
        ]);
        foreach ($candidates as $candidate) {
            if (strcasecmp(trim((string) $candidate->post_title), trim((string) $title)) === 0) return $candidate;
        }
        return null;
    }

    private function complete_solving_graphing_equations_lessons() {
        $content_file = __DIR__ . '/content/solving-graphing-equations-completion.php';
        if (!is_readable($content_file)) return;
        $lessons = require $content_file;
        if (!is_array($lessons)) return;

        foreach ($lessons as $slug => $fields) {
            if (!is_array($fields)) continue;
            $title = isset($fields['title']) ? $fields['title'] : ucwords(str_replace('-', ' ', $slug));
            $lesson = $this->find_lesson_by_slug_or_title($slug, $title);
            if (!$lesson) continue;
            foreach ($fields as $key => $value) {
                if ($key === 'title') continue;
                if (!array_key_exists($key, $this->lesson_builder_required_fields())) continue;
                if ($this->lesson_field_is_complete($lesson->ID, $key)) continue;
                update_post_meta($lesson->ID, '_mb_' . $key, $value);
            }
            $completion = $this->lesson_completion_data($lesson->ID);
            update_post_meta($lesson->ID, '_mb_gold_certification_percent', $completion['percent']);
            update_post_meta($lesson->ID, '_mb_gold_certification_missing', $completion['missing']);
            if (!$completion['missing']) {
                update_post_meta($lesson->ID, '_mb_lesson_status', 'gold-certified');
                update_post_meta($lesson->ID, '_mb_gold_certification', 'gold-ready');
            }
        }
    }

    private function completion_content_key_for_geometry_title($title) {
        $title = strtolower(trim((string) $title));
        if (preg_match('/right.?triangle.*trig|trigonometr/', $title)) return 'right-triangle-trigonometry';
        if (strpos($title, 'construct') !== false) return 'geometric-constructions';
        if (strpos($title, 'congruen') !== false || strpos($title, 'rigid') !== false) return 'congruence-and-rigid-motions';
        if (strpos($title, 'similar') !== false || strpos($title, 'dilat') !== false) return 'similarity-and-dilations';
        if (strpos($title, 'pythag') !== false || strpos($title, 'distance formula') !== false) return 'pythagorean-theorem-and-distance-formula';
        if (strpos($title, 'coordinate') !== false || strpos($title, 'proof') !== false) return 'coordinate-geometry-and-proof';
        if (strpos($title, 'angle') !== false || strpos($title, 'triangle theorem') !== false || strpos($title, 'triangle relationship') !== false) return 'triangle-angle-relationships';
        if (strpos($title, 'transform') !== false) return 'transformations';
        return '';
    }

    private function apply_completion_fields_to_lesson($lesson, $fields) {
        if (!$lesson || !is_array($fields)) return;
        foreach ($fields as $key => $value) {
            if ($key === 'title') continue;
            if (!array_key_exists($key, $this->lesson_builder_required_fields())) continue;
            // Curated video sources are release data. Refresh them on repair so
            // every lesson uses the current in-page player configuration.
            if ($key !== 'videos' && $this->lesson_field_is_complete($lesson->ID, $key)) continue;
            update_post_meta($lesson->ID, '_mb_' . $key, $value);
        }
        $completion = $this->lesson_completion_data($lesson->ID);
        update_post_meta($lesson->ID, '_mb_gold_certification_percent', $completion['percent']);
        update_post_meta($lesson->ID, '_mb_gold_certification_missing', $completion['missing']);
        if (!$completion['missing']) {
            update_post_meta($lesson->ID, '_mb_lesson_status', 'gold-certified');
            update_post_meta($lesson->ID, '_mb_gold_certification', 'gold-ready');
        }
    }

    /**
     * Refresh curated video metadata across every completion curriculum file.
     *
     * Earlier releases repaired only the most recently shipped domain, which
     * allowed stale course-page links to survive in older lessons. This repair
     * is deliberately global and touches only the video field; teacher-authored
     * lesson copy and other customizations remain unchanged.
     */
    public function maybe_repair_all_lesson_videos() {
        $repair_version = get_option('mathbinder_all_lesson_videos_version', '0');
        if (version_compare($repair_version, '30.42.2', '>=')) return;

        $this->register_content_types();
        $content_files = glob(__DIR__ . '/content/*-completion.php');
        if (!$content_files) return;

        $updated = 0;
        foreach ($content_files as $content_file) {
            if (!is_readable($content_file)) continue;
            $lessons = require $content_file;
            if (!is_array($lessons)) continue;

            foreach ($lessons as $slug => $fields) {
                if (!is_array($fields) || empty($fields['videos'])) continue;
                $title = isset($fields['title']) ? $fields['title'] : ucwords(str_replace('-', ' ', $slug));
                $lesson = $this->find_lesson_by_slug_or_title($slug, $title);
                if (!$lesson) continue;
                update_post_meta($lesson->ID, '_mb_videos', $fields['videos']);
                $updated++;
            }
        }

        update_option('mathbinder_all_lesson_videos_version', '30.42.2');
        update_option('mathbinder_all_lesson_videos_updated', $updated);
    }

    private function complete_counting_cardinality_lessons() {
        $content_file = __DIR__ . '/content/counting-cardinality-completion.php';
        if (!is_readable($content_file)) return;
        $lessons = require $content_file;
        if (!is_array($lessons)) return;

        $term = get_term_by('slug', 'counting-cardinality', self::TAX);
        if (!$term) {
            $created = wp_insert_term('Counting & Cardinality', self::TAX, [
                'slug' => 'counting-cardinality',
                'description' => 'Kindergarten counting, cardinality, and number comparison lessons.',
            ]);
            if (!is_wp_error($created)) {
                $term = get_term((int) $created['term_id'], self::TAX);
                update_term_meta((int) $created['term_id'], '_mb_section_number', '01');
            }
        }

        foreach ($lessons as $slug => $fields) {
            if (!is_array($fields)) continue;
            $title = isset($fields['title']) ? $fields['title'] : ucwords(str_replace('-', ' ', $slug));
            $lesson = $this->find_lesson_by_slug_or_title($slug, $title);

            if (!$lesson) {
                $lesson_id = wp_insert_post([
                    'post_type' => self::CPT,
                    'post_status' => 'draft',
                    'post_title' => $title,
                    'post_name' => $slug,
                    'post_content' => '',
                    'menu_order' => 100,
                ]);
                if (!$lesson_id || is_wp_error($lesson_id)) continue;
                $lesson = get_post($lesson_id);
            }

            if ($term && !is_wp_error($term)) {
                wp_set_object_terms($lesson->ID, [(int) $term->term_id], self::TAX);
            }

            $this->apply_completion_fields_to_lesson($lesson, $fields);
            $completion = $this->lesson_completion_data($lesson->ID);
            if (!$completion['missing'] && $lesson->post_status !== 'publish') {
                wp_update_post([
                    'ID' => $lesson->ID,
                    'post_status' => 'publish',
                ]);
            }
        }
    }

    public function maybe_repair_operations_lessons() {
        $repair_version = get_option('mathbinder_operations_lessons_version', '0');
        if (version_compare($repair_version, '30.40.2', '>=')) return;

        $this->register_content_types();
        $this->ensure_sections();
        $this->complete_operations_algebraic_thinking_lessons();

        if ($this->operations_algebraic_thinking_lessons_ready()) {
            update_option('mathbinder_operations_lessons_version', '30.40.2');
            flush_rewrite_rules(false);
        }
    }

    private function operations_algebraic_thinking_lessons_ready() {
        $content_file = __DIR__ . '/content/operations-algebraic-thinking-completion.php';
        if (!is_readable($content_file)) return false;
        $lessons = require $content_file;
        if (!is_array($lessons) || !$lessons) return false;

        foreach ($lessons as $slug => $fields) {
            $title = isset($fields['title']) ? $fields['title'] : ucwords(str_replace('-', ' ', $slug));
            $lesson = $this->find_lesson_by_slug_or_title($slug, $title);
            if (!$lesson || $lesson->post_status !== 'publish') return false;
        }
        return true;
    }

    private function complete_operations_algebraic_thinking_lessons() {
        $content_file = __DIR__ . '/content/operations-algebraic-thinking-completion.php';
        if (!is_readable($content_file)) return;
        $lessons = require $content_file;
        if (!is_array($lessons)) return;

        $term = get_term_by('slug', 'operations-algebraic-thinking', self::TAX);
        if (!$term) {
            $created = wp_insert_term('Operations & Algebraic Thinking', self::TAX, [
                'slug' => 'operations-algebraic-thinking',
                'description' => 'Kindergarten through Grade 5 operations, algebraic thinking, and pattern lessons.',
            ]);
            if (!is_wp_error($created)) {
                $term = get_term((int) $created['term_id'], self::TAX);
                update_term_meta((int) $created['term_id'], '_mb_section_number', '02');
            }
        }

        foreach ($lessons as $slug => $fields) {
            if (!is_array($fields)) continue;
            $title = isset($fields['title']) ? $fields['title'] : ucwords(str_replace('-', ' ', $slug));
            $lesson = $this->find_lesson_by_slug_or_title($slug, $title);
            if (!$lesson) {
                $lesson_id = wp_insert_post([
                    'post_type' => self::CPT,
                    'post_status' => 'draft',
                    'post_title' => $title,
                    'post_name' => $slug,
                    'post_content' => '',
                    'menu_order' => 200,
                ]);
                if (!$lesson_id || is_wp_error($lesson_id)) continue;
                $lesson = get_post($lesson_id);
            }
            if ($term && !is_wp_error($term)) {
                wp_set_object_terms($lesson->ID, [(int) $term->term_id], self::TAX);
            }
            $this->apply_completion_fields_to_lesson($lesson, $fields);
            $completion = $this->lesson_completion_data($lesson->ID);
            if (!$completion['missing'] && $lesson->post_status !== 'publish') {
                wp_update_post(['ID' => $lesson->ID, 'post_status' => 'publish']);
            }
        }
    }

    public function maybe_repair_number_base_ten_lessons() {
        $repair_version = get_option('mathbinder_number_base_ten_lessons_version', '0');
        if (version_compare($repair_version, '30.41.0', '>=')) return;

        $this->register_content_types();
        $this->ensure_sections();
        $this->complete_number_base_ten_lessons();

        if ($this->number_base_ten_lessons_ready()) {
            update_option('mathbinder_number_base_ten_lessons_version', '30.41.0');
            flush_rewrite_rules(false);
        }
    }

    private function number_base_ten_lessons_ready() {
        $content_file = __DIR__ . '/content/number-base-ten-completion.php';
        if (!is_readable($content_file)) return false;
        $lessons = require $content_file;
        if (!is_array($lessons) || count($lessons) !== 15) return false;

        foreach ($lessons as $slug => $fields) {
            $title = isset($fields['title']) ? $fields['title'] : ucwords(str_replace('-', ' ', $slug));
            $lesson = $this->find_lesson_by_slug_or_title($slug, $title);
            if (!$lesson || $lesson->post_status !== 'publish') return false;
        }
        return true;
    }

    private function complete_number_base_ten_lessons() {
        $content_file = __DIR__ . '/content/number-base-ten-completion.php';
        if (!is_readable($content_file)) return;
        $lessons = require $content_file;
        if (!is_array($lessons)) return;

        $term = get_term_by('slug', 'number-base-ten', self::TAX);
        if (!$term) {
            $created = wp_insert_term('Number & Operations in Base Ten', self::TAX, [
                'slug' => 'number-base-ten',
                'description' => 'Kindergarten through Grade 5 place value, base-ten arithmetic, rounding, and decimal lessons.',
            ]);
            if (!is_wp_error($created)) {
                $term = get_term((int) $created['term_id'], self::TAX);
                update_term_meta((int) $created['term_id'], '_mb_section_number', '03');
            }
        }

        foreach ($lessons as $slug => $fields) {
            if (!is_array($fields)) continue;
            $title = isset($fields['title']) ? $fields['title'] : ucwords(str_replace('-', ' ', $slug));
            $lesson = $this->find_lesson_by_slug_or_title($slug, $title);
            if (!$lesson) {
                $lesson_id = wp_insert_post([
                    'post_type' => self::CPT,
                    'post_status' => 'draft',
                    'post_title' => $title,
                    'post_name' => $slug,
                    'post_content' => '',
                    'menu_order' => 300,
                ]);
                if (!$lesson_id || is_wp_error($lesson_id)) continue;
                $lesson = get_post($lesson_id);
            }
            if ($term && !is_wp_error($term)) {
                wp_set_object_terms($lesson->ID, [(int) $term->term_id], self::TAX);
            }
            $this->apply_completion_fields_to_lesson($lesson, $fields);
            $completion = $this->lesson_completion_data($lesson->ID);
            if (!$completion['missing'] && $lesson->post_status !== 'publish') {
                wp_update_post(['ID' => $lesson->ID, 'post_status' => 'publish']);
            }
        }
    }

    public function maybe_repair_fraction_lessons() {
        $repair_version = get_option('mathbinder_fraction_lessons_version', '0');
        if (version_compare($repair_version, '30.42.2', '>=')) return;
        $this->register_content_types();
        $this->ensure_sections();
        $this->complete_fraction_lessons();
        if ($this->fraction_lessons_ready()) {
            update_option('mathbinder_fraction_lessons_version', '30.42.2');
            flush_rewrite_rules(false);
        }
    }

    private function fraction_lessons_ready() {
        $content_file = __DIR__ . '/content/fractions-completion.php';
        if (!is_readable($content_file)) return false;
        $lessons = require $content_file;
        if (!is_array($lessons) || count($lessons) !== 10) return false;
        foreach ($lessons as $slug => $fields) {
            $title = isset($fields['title']) ? $fields['title'] : ucwords(str_replace('-', ' ', $slug));
            $lesson = $this->find_lesson_by_slug_or_title($slug, $title);
            if (!$lesson || $lesson->post_status !== 'publish') return false;
        }
        return true;
    }

    private function complete_fraction_lessons() {
        $content_file = __DIR__ . '/content/fractions-completion.php';
        if (!is_readable($content_file)) return;
        $lessons = require $content_file;
        if (!is_array($lessons)) return;
        $term = get_term_by('slug', 'number-fractions', self::TAX);
        if (!$term) {
            $created = wp_insert_term('Number & Operations—Fractions', self::TAX, [
                'slug' => 'number-fractions',
                'description' => 'Grades 3 through 5 fraction concepts, equivalence, comparison, operations, and decimal connections.',
            ]);
            if (!is_wp_error($created)) {
                $term = get_term((int) $created['term_id'], self::TAX);
                update_term_meta((int) $created['term_id'], '_mb_section_number', '04');
            }
        }
        foreach ($lessons as $slug => $fields) {
            if (!is_array($fields)) continue;
            $title = isset($fields['title']) ? $fields['title'] : ucwords(str_replace('-', ' ', $slug));
            $lesson = $this->find_lesson_by_slug_or_title($slug, $title);
            if (!$lesson) {
                $lesson_id = wp_insert_post([
                    'post_type' => self::CPT, 'post_status' => 'draft', 'post_title' => $title,
                    'post_name' => $slug, 'post_content' => '', 'menu_order' => 400,
                ]);
                if (!$lesson_id || is_wp_error($lesson_id)) continue;
                $lesson = get_post($lesson_id);
            }
            if ($term && !is_wp_error($term)) wp_set_object_terms($lesson->ID, [(int) $term->term_id], self::TAX);
            $this->apply_completion_fields_to_lesson($lesson, $fields);
            // Video URLs are curated release data, not teacher-authored lesson text.
            // Force this field during the repair so older course-page links cannot
            // survive an upgrade and trigger the external-tab fallback.
            if (!empty($fields['videos'])) {
                update_post_meta($lesson->ID, '_mb_videos', $fields['videos']);
            }
            $completion = $this->lesson_completion_data($lesson->ID);
            if (!$completion['missing'] && $lesson->post_status !== 'publish') wp_update_post(['ID'=>$lesson->ID, 'post_status'=>'publish']);
        }
    }

    public function maybe_repair_measurement_data_lessons() {
        $repair_version = get_option('mathbinder_measurement_data_lessons_version', '0');
        if (version_compare($repair_version, '30.43.1', '>=') && $this->measurement_data_lessons_ready()) return;
        $this->register_content_types();
        $this->ensure_sections();
        $this->complete_measurement_data_lessons();
        if ($this->measurement_data_lessons_ready()) {
            update_option('mathbinder_measurement_data_lessons_version', '30.43.1');
            flush_rewrite_rules(false);
        }
    }

    private function measurement_data_lessons_ready() {
        $content_file = __DIR__ . '/content/measurement-data-completion.php';
        if (!is_readable($content_file)) return false;
        $lessons = require $content_file;
        if (!is_array($lessons) || count($lessons) !== 17) return false;
        foreach ($lessons as $slug => $fields) {
            $title = isset($fields['title']) ? $fields['title'] : ucwords(str_replace('-', ' ', $slug));
            $lesson = $this->find_lesson_by_slug_or_title($slug, $title);
            if (!$lesson || $lesson->post_status !== 'publish') return false;
            $completion = $this->lesson_completion_data($lesson->ID);
            if (!empty($completion['missing'])) return false;
        }
        return true;
    }

    private function complete_measurement_data_lessons() {
        $content_file = __DIR__ . '/content/measurement-data-completion.php';
        if (!is_readable($content_file)) return;
        $lessons = require $content_file;
        if (!is_array($lessons)) return;
        $term = get_term_by('slug', 'measurement-data', self::TAX);
        if (!$term) {
            $created = wp_insert_term('Measurement & Data', self::TAX, [
                'slug' => 'measurement-data',
                'description' => 'Kindergarten through Grade 5 measurement, time, money, data, area, perimeter, conversions, and volume.',
            ]);
            if (!is_wp_error($created)) {
                $term = get_term((int) $created['term_id'], self::TAX);
                update_term_meta((int) $created['term_id'], '_mb_section_number', '05');
            }
        } elseif (!is_wp_error($term)) {
            update_term_meta((int) $term->term_id, '_mb_section_number', '05');
        }
        foreach ($lessons as $slug => $fields) {
            if (!is_array($fields)) continue;
            $title = isset($fields['title']) ? $fields['title'] : ucwords(str_replace('-', ' ', $slug));
            $lesson = $this->find_lesson_by_slug_or_title($slug, $title);
            if (!$lesson) {
                $lesson_id = wp_insert_post([
                    'post_type' => self::CPT, 'post_status' => 'draft', 'post_title' => $title,
                    'post_name' => $slug, 'post_content' => '', 'menu_order' => 500,
                ]);
                if (!$lesson_id || is_wp_error($lesson_id)) continue;
                $lesson = get_post($lesson_id);
            }
            if ($term && !is_wp_error($term)) wp_set_object_terms($lesson->ID, [(int) $term->term_id], self::TAX);
            $this->apply_completion_fields_to_lesson($lesson, $fields);
            if (!empty($fields['videos'])) update_post_meta($lesson->ID, '_mb_videos', $fields['videos']);
            $completion = $this->lesson_completion_data($lesson->ID);
            if (!$completion['missing'] && $lesson->post_status !== 'publish') wp_update_post(['ID' => $lesson->ID, 'post_status' => 'publish']);
        }
    }

    public function maybe_repair_geometry_lessons() {
        $repair_version = get_option('mathbinder_geometry_lessons_version', '0');
        if (version_compare($repair_version, '30.44.0', '>=') && $this->geometry_lessons_ready()) return;
        $this->register_content_types();
        $this->ensure_sections();
        $this->complete_geometry_lessons();
        if ($this->geometry_lessons_ready()) {
            update_option('mathbinder_geometry_lessons_version', '30.44.0');
            flush_rewrite_rules(false);
        }
    }

    private function geometry_lessons_ready() {
        $content_file = __DIR__ . '/content/geometry-completion.php';
        if (!is_readable($content_file)) return false;
        $lessons = require $content_file;
        if (!is_array($lessons) || count($lessons) !== 21) return false;
        foreach ($lessons as $slug => $fields) {
            $lesson = get_page_by_path($slug, OBJECT, self::CPT);
            if (!$lesson || $lesson->post_status !== 'publish') return false;
            $completion = $this->lesson_completion_data($lesson->ID);
            if (!empty($completion['missing'])) return false;
        }
        return true;
    }

    private function complete_geometry_lessons() {
        $content_file = __DIR__ . '/content/geometry-completion.php';
        if (!is_readable($content_file)) return;
        $lessons = require $content_file;
        if (!is_array($lessons)) return;
        $term = get_term_by('slug', 'geometry', self::TAX);
        if (!$term) {
            $created = wp_insert_term('Geometry', self::TAX, [
                'slug' => 'geometry',
                'description' => 'Kindergarten through Grade 8 shapes, coordinate geometry, measurement geometry, transformations, similarity, and the Pythagorean Theorem.',
            ]);
            if (!is_wp_error($created)) $term = get_term((int) $created['term_id'], self::TAX);
        }
        if ($term && !is_wp_error($term)) update_term_meta((int) $term->term_id, '_mb_section_number', '06');

        foreach ($lessons as $slug => $fields) {
            if (!is_array($fields)) continue;
            $title = isset($fields['title']) ? $fields['title'] : ucwords(str_replace('-', ' ', $slug));
            // Use the release slug so older lessons with a similar title remain intact.
            $lesson = get_page_by_path($slug, OBJECT, self::CPT);
            if (!$lesson) {
                $lesson_id = wp_insert_post([
                    'post_type' => self::CPT, 'post_status' => 'draft', 'post_title' => $title,
                    'post_name' => $slug, 'post_content' => '', 'menu_order' => 600,
                ]);
                if (!$lesson_id || is_wp_error($lesson_id)) continue;
                $lesson = get_post($lesson_id);
            }
            if ($term && !is_wp_error($term)) wp_set_object_terms($lesson->ID, [(int) $term->term_id], self::TAX, true);
            $this->apply_completion_fields_to_lesson($lesson, $fields);
            if (!empty($fields['videos'])) update_post_meta($lesson->ID, '_mb_videos', $fields['videos']);
            $completion = $this->lesson_completion_data($lesson->ID);
            if (!$completion['missing'] && $lesson->post_status !== 'publish') wp_update_post(['ID' => $lesson->ID, 'post_status' => 'publish']);
        }
    }

    public function maybe_repair_sections_7_11_lessons() {
        $repair_version = get_option('mathbinder_sections_7_11_lessons_version', '0');
        if (version_compare($repair_version, '30.45.0', '>=') && $this->sections_7_11_lessons_ready()) return;
        $this->register_content_types();
        $this->complete_sections_7_11_lessons();
        if ($this->sections_7_11_lessons_ready()) {
            update_option('mathbinder_sections_7_11_lessons_version', '30.45.0');
            flush_rewrite_rules(false);
        }
    }

    private function sections_7_11_lessons_ready() {
        $content_file = __DIR__ . '/content/sections-7-11-completion.php';
        if (!is_readable($content_file)) return false;
        $lessons = require $content_file;
        if (!is_array($lessons) || count($lessons) !== 30) return false;
        foreach ($lessons as $slug => $fields) {
            $lesson = get_page_by_path($slug, OBJECT, self::CPT);
            if (!$lesson || $lesson->post_status !== 'publish') return false;
            $completion = $this->lesson_completion_data($lesson->ID);
            if (!empty($completion['missing'])) return false;
        }
        return true;
    }

    private function complete_sections_7_11_lessons() {
        $content_file = __DIR__ . '/content/sections-7-11-completion.php';
        if (!is_readable($content_file)) return;
        $lessons = require $content_file;
        if (!is_array($lessons)) return;
        $domains = [
            'ratios-proportional' => ['Ratios & Proportional Relationships','07'],
            'number-system' => ['The Number System','08'],
            'expressions-equations' => ['Expressions & Equations','09'],
            'functions' => ['Functions','10'],
            'statistics-probability' => ['Statistics & Probability','11'],
        ];
        $terms = [];
        foreach ($domains as $slug => $definition) {
            $term = get_term_by('slug', $slug, self::TAX);
            if (!$term) {
                $created = wp_insert_term($definition[0], self::TAX, ['slug'=>$slug]);
                if (!is_wp_error($created)) $term = get_term((int)$created['term_id'], self::TAX);
            }
            if ($term && !is_wp_error($term)) {
                update_term_meta((int)$term->term_id, '_mb_section_number', $definition[1]);
                update_term_meta((int)$term->term_id, 'mb_number', (int)$definition[1]);
                $terms[$slug] = $term;
            }
        }
        $order = 700;
        foreach ($lessons as $slug => $fields) {
            if (!is_array($fields)) continue;
            $title = $fields['title'] ?? ucwords(str_replace('-', ' ', $slug));
            $lesson = get_page_by_path($slug, OBJECT, self::CPT);
            if (!$lesson) {
                $lesson_id = wp_insert_post(['post_type'=>self::CPT,'post_status'=>'draft','post_title'=>$title,'post_name'=>$slug,'post_content'=>'','menu_order'=>$order++]);
                if (!$lesson_id || is_wp_error($lesson_id)) continue;
                $lesson = get_post($lesson_id);
            }
            $domain_slug = $fields['_mb_domain_slug'] ?? '';
            if ($domain_slug && isset($terms[$domain_slug])) wp_set_object_terms($lesson->ID, [(int)$terms[$domain_slug]->term_id], self::TAX, true);
            foreach (['_mb_domain_title','_mb_section_number','_mb_grade'] as $meta_key) if (isset($fields[$meta_key])) update_post_meta($lesson->ID, $meta_key, $fields[$meta_key]);
            $this->apply_completion_fields_to_lesson($lesson, $fields);
            if (!empty($fields['videos'])) update_post_meta($lesson->ID, '_mb_videos', $fields['videos']);
            $completion = $this->lesson_completion_data($lesson->ID);
            if (!$completion['missing'] && $lesson->post_status !== 'publish') wp_update_post(['ID'=>$lesson->ID,'post_status'=>'publish']);
        }
    }

    public function maybe_publish_complete_curriculum() {
        $installed = get_option('mathbinder_complete_curriculum_version', '0');
        if (version_compare($installed, '30.46.0', '>=') && $this->complete_curriculum_ready()) return;
        $this->register_content_types();
        $this->publish_missing_middle_school_lessons();
        $this->publish_high_school_lessons();
        $this->complete_sections_7_11_lessons();
        if ($this->complete_curriculum_ready()) {
            update_option('mathbinder_complete_curriculum_version', '30.46.0');
            flush_rewrite_rules(false);
        }
    }

    private function complete_curriculum_ready() {
        foreach (['missing-middle-school-completion.php'=>10,'high-school-completion.php'=>86] as $file=>$expected) {
            $path=__DIR__.'/content/'.$file; if(!is_readable($path)) return false;
            $items=require $path; if(!is_array($items)||count($items)!==$expected) return false;
            foreach($items as $slug=>$fields){
                $lesson=get_page_by_path($slug,OBJECT,self::CPT);
                if(!$lesson||$lesson->post_status!=='publish'||!empty($this->lesson_completion_data($lesson->ID)['missing'])) return false;
            }
        }
        return $this->sections_7_11_lessons_ready();
    }

    private function ensure_curriculum_term($slug,$title,$number) {
        $term=get_term_by('slug',$slug,self::TAX);
        if(!$term){$created=wp_insert_term($title,self::TAX,['slug'=>$slug]);if(!is_wp_error($created))$term=get_term((int)$created['term_id'],self::TAX);}
        if($term&&!is_wp_error($term)){update_term_meta((int)$term->term_id,'_mb_section_number',str_pad((string)$number,2,'0',STR_PAD_LEFT));update_term_meta((int)$term->term_id,'mb_number',(int)$number);}
        return ($term&&!is_wp_error($term))?$term:null;
    }

    private function publish_completion_records($records,$terms,$order_start) {
        $order=$order_start;
        foreach($records as $slug=>$fields){
            if(!is_array($fields))continue;
            $lesson=get_page_by_path($slug,OBJECT,self::CPT);
            if(!$lesson){$id=wp_insert_post(['post_type'=>self::CPT,'post_status'=>'draft','post_title'=>$fields['title']??ucwords(str_replace('-',' ',$slug)),'post_name'=>$slug,'post_content'=>'','menu_order'=>$order++]);if(!$id||is_wp_error($id))continue;$lesson=get_post($id);}
            $term_key=$fields['_mb_domain_slug']??($fields['_mb_course_slug']??'');
            if($term_key&&isset($terms[$term_key]))wp_set_object_terms($lesson->ID,[(int)$terms[$term_key]->term_id],self::TAX,true);
            foreach(['_mb_domain_slug','_mb_course_slug','_mb_course_title','_mb_domain_title','_mb_sequence'] as $meta)if(isset($fields[$meta]))update_post_meta($lesson->ID,$meta,$fields[$meta]);
            $this->apply_completion_fields_to_lesson($lesson,$fields);
            if(!empty($fields['videos']))update_post_meta($lesson->ID,'_mb_videos',$fields['videos']);
            if(empty($this->lesson_completion_data($lesson->ID)['missing'])&&$lesson->post_status!=='publish')wp_update_post(['ID'=>$lesson->ID,'post_status'=>'publish']);
        }
    }

    private function publish_missing_middle_school_lessons() {
        $path=__DIR__.'/content/missing-middle-school-completion.php';if(!is_readable($path))return;$records=require $path;if(!is_array($records))return;
        $terms=['ratios-proportional'=>$this->ensure_curriculum_term('ratios-proportional','Ratios & Proportional Relationships',7),'number-system'=>$this->ensure_curriculum_term('number-system','The Number System',8)];
        $this->publish_completion_records($records,$terms,760);
    }

    private function publish_high_school_lessons() {
        $path=__DIR__.'/content/high-school-completion.php';if(!is_readable($path))return;$records=require $path;if(!is_array($records))return;
        $terms=[
            'algebra-1'=>$this->ensure_curriculum_term('course-algebra-1','Algebra I',12),
            'geometry'=>$this->ensure_curriculum_term('course-geometry','Geometry — High School',13),
            'algebra-2'=>$this->ensure_curriculum_term('course-algebra-2','Algebra II',14),
            'statistics'=>$this->ensure_curriculum_term('course-algebra-2','Algebra II',14),
            'precalculus'=>$this->ensure_curriculum_term('course-precalculus','Precalculus',15),
            'calculus-ab'=>$this->ensure_curriculum_term('course-calculus','Calculus',16),
            'calculus-bc'=>$this->ensure_curriculum_term('course-calculus','Calculus',16),
        ];
        $this->publish_completion_records($records,$terms,1200);
    }

    private function complete_inequalities_triangles_transformations_lessons() {
        $content_file = __DIR__ . '/content/inequalities-triangles-transformations-completion.php';
        if (!is_readable($content_file)) return;
        $lessons = require $content_file;
        if (!is_array($lessons)) return;

        $completed_ids = [];
        foreach ($lessons as $slug => $fields) {
            if (!is_array($fields)) continue;
            $title = isset($fields['title']) ? $fields['title'] : ucwords(str_replace('-', ' ', $slug));
            $lesson = $this->find_lesson_by_slug_or_title($slug, $title);
            if (!$lesson) continue;
            $this->apply_completion_fields_to_lesson($lesson, $fields);
            $completed_ids[(int) $lesson->ID] = true;
        }

        $section_lessons = get_posts([
            'post_type' => self::CPT,
            'post_status' => ['publish', 'draft', 'pending', 'private', 'future'],
            'posts_per_page' => -1,
            'tax_query' => [[
                'taxonomy' => self::TAX,
                'field' => 'slug',
                'terms' => ['triangles-transformations'],
            ]],
        ]);
        foreach ($section_lessons as $lesson) {
            if (isset($completed_ids[(int) $lesson->ID])) continue;
            $key = $this->completion_content_key_for_geometry_title($lesson->post_title);
            if ($key === '' || !isset($lessons[$key])) continue;
            $this->apply_completion_fields_to_lesson($lesson, $lessons[$key]);
        }
    }

    private function migrate_pythagorean_practice_30375() {
        $content_file = __DIR__ . '/content/inequalities-triangles-transformations-completion.php';
        if (!is_readable($content_file)) return;
        $lessons = require $content_file;
        $fields = $lessons['pythagorean-theorem-and-distance-formula'] ?? [];
        if (!is_array($fields)) return;

        $lesson = $this->find_lesson_by_slug_or_title(
            'pythagorean-theorem-and-distance-formula',
            'Pythagorean Theorem and Distance Formula'
        );
        $targets = $lesson ? [$lesson] : [];
        $all_lessons = get_posts([
            'post_type' => self::CPT,
            'post_status' => ['publish', 'draft', 'pending', 'private', 'future'],
            'posts_per_page' => -1,
        ]);
        foreach ($all_lessons as $candidate) {
            $title = strtolower((string) $candidate->post_title);
            if (strpos($title, 'pythag') !== false || strpos($title, 'distance formula') !== false) {
                $targets[(int) $candidate->ID] = $candidate;
            }
        }
        foreach ($targets as $target) {
            foreach (['worked_examples', 'practice_warmup', 'guided_practice', 'independent_practice'] as $key) {
                if (isset($fields[$key])) update_post_meta($target->ID, '_mb_' . $key, $fields[$key]);
            }
        }
    }

    private function completion_content_key_for_measurement_title($title) {
        $title = strtolower(trim((string) $title));
        if (strpos($title, 'cross-section') !== false || strpos($title, 'solid of revolution') !== false || strpos($title, 'solids of revolution') !== false) return 'cross-sections-and-solids-of-revolution';
        if (strpos($title, 'composite') !== false) return 'composite-figures';
        if (strpos($title, 'surface') !== false || strpos($title, 'net') !== false) return 'surface-area';
        if (preg_match('/pyramid|cone|sphere/', $title)) return 'volume-of-pyramids-cones-and-spheres';
        if (preg_match('/prism|cylinder/', $title) && strpos($title, 'volume') !== false) return 'volume-of-prisms-and-cylinders';
        if (strpos($title, 'circle') !== false) return 'circles';
        if (strpos($title, 'scale') !== false || strpos($title, 'dimension') !== false || strpos($title, 'measurement') !== false) return 'scale-drawings-and-dimensional-measurement';
        if (strpos($title, 'polygon') !== false) return 'area-of-polygons';
        if (strpos($title, 'area') !== false || strpos($title, 'perimeter') !== false) return 'area-and-perimeter';
        return '';
    }

    private function completion_content_key_for_statistics_title($title) {
        $title = strtolower(trim((string) $title));
        if (strpos($title, 'regression') !== false || strpos($title, 'correlation') !== false || strpos($title, 'causation') !== false) return 'regression-correlation-and-causation';
        if (strpos($title, 'scatter') !== false || strpos($title, 'association') !== false || strpos($title, 'line of fit') !== false) return 'scatter-plots-and-association';
        if (strpos($title, 'two-way') !== false || strpos($title, 'categorical') !== false || strpos($title, 'relative frequenc') !== false) return 'two-way-tables-and-categorical-data';
        if (strpos($title, 'compound') !== false || strpos($title, 'independent') !== false || strpos($title, 'dependent') !== false) return 'compound-probability';
        if (strpos($title, 'simulation') !== false || strpos($title, 'model') !== false) return 'probability-models-and-simulation';
        if (strpos($title, 'sample') !== false || strpos($title, 'population') !== false || strpos($title, 'bias') !== false) return 'populations-and-samples';
        if (strpos($title, 'compare') !== false || strpos($title, 'distribution') !== false) return 'comparing-data-distributions';
        if (strpos($title, 'center') !== false || strpos($title, 'variability') !== false || strpos($title, 'mean') !== false || strpos($title, 'median') !== false) return 'measures-of-center-and-variability';
        if (strpos($title, 'experimental') !== false || strpos($title, 'theoretical') !== false) return 'theoretical-and-experimental-probability';
        if (strpos($title, 'probability') !== false) return 'theoretical-and-experimental-probability';
        if (strpos($title, 'statistic') !== false || strpos($title, 'data') !== false || strpos($title, 'display') !== false) return 'statistical-questions-and-data-displays';
        return '';
    }

    private function complete_volume_area_probability_statistics_lessons() {
        $content_file = __DIR__ . '/content/volume-area-probability-statistics-completion.php';
        if (!is_readable($content_file)) return;
        $lessons = require $content_file;
        if (!is_array($lessons)) return;

        $geometry = $this->find_lesson_by_slug_or_title('geometric-figures-and-relationships', 'Geometric Figures and Relationships');
        if ($geometry && isset($lessons['geometric-figures-and-relationships'])) {
            $this->apply_completion_fields_to_lesson($geometry, $lessons['geometric-figures-and-relationships']);
        }

        $section_maps = [
            'volume-area' => 'completion_content_key_for_measurement_title',
            'probability-statistics' => 'completion_content_key_for_statistics_title',
        ];
        foreach ($section_maps as $section_slug => $resolver) {
            $section_lessons = get_posts([
                'post_type' => self::CPT,
                'post_status' => ['publish', 'draft', 'pending', 'private', 'future'],
                'posts_per_page' => -1,
                'orderby' => 'menu_order title',
                'order' => 'ASC',
                'tax_query' => [[
                    'taxonomy' => self::TAX,
                    'field' => 'slug',
                    'terms' => [$section_slug],
                ]],
            ]);
            foreach ($section_lessons as $lesson) {
                $key = $this->{$resolver}($lesson->post_title);
                if ($key === '' || !isset($lessons[$key])) continue;
                $this->apply_completion_fields_to_lesson($lesson, $lessons[$key]);
            }
        }

        foreach ($lessons as $slug => $fields) {
            if ($slug === 'geometric-figures-and-relationships' || !is_array($fields)) continue;
            $title = isset($fields['title']) ? $fields['title'] : ucwords(str_replace('-', ' ', $slug));
            $lesson = $this->find_lesson_by_slug_or_title($slug, $title);
            if ($lesson) $this->apply_completion_fields_to_lesson($lesson, $fields);
        }
    }

    private function find_fractions_decimals_lesson() {
        $lesson = get_page_by_path('fractions-decimals', OBJECT, self::CPT);
        if ($lesson) return $lesson;

        $candidates = get_posts([
            'post_type' => self::CPT,
            'post_status' => ['publish', 'draft', 'pending', 'private', 'future'],
            'posts_per_page' => -1,
            'orderby' => 'ID',
            'order' => 'ASC'
        ]);

        foreach ($candidates as $candidate) {
            if (strcasecmp(trim((string) $candidate->post_title), 'Fractions & Decimals') === 0) {
                return $candidate;
            }
        }

        return null;
    }

    public function maybe_repair_fractions_challenge() {
        if (!current_user_can('manage_options')) return;

        $fractions = $this->find_fractions_decimals_lesson();
        if (!$fractions) return;

        if (!$this->lesson_field_is_complete($fractions->ID, 'challenge_practice')) {
            update_post_meta(
                $fractions->ID,
                '_mb_challenge_practice',
                "Explain why a fraction in simplest form has a terminating decimal only when its denominator has no prime factors other than 2 and 5.\nCreate three different fractions between 0.4 and 0.5 and justify their placement."
            );
        }

        if (!$this->lesson_field_is_complete($fractions->ID, 'challenge_practice')) return;

        $completion = $this->lesson_completion_data($fractions->ID);
        update_post_meta($fractions->ID, '_mb_gold_certification_percent', $completion['percent']);
        update_post_meta($fractions->ID, '_mb_gold_certification_missing', $completion['missing']);
        if (!$completion['missing']) {
            update_post_meta($fractions->ID, '_mb_lesson_status', 'gold-certified');
            update_post_meta($fractions->ID, '_mb_gold_certification', 'gold-ready');
        }
        update_option('mathbinder_fractions_challenge_repair', self::VERSION);
    }

    private function ensure_sections() {
        $sections = [
            1 => ['The Number System', 'the-number-system'],
            2 => ['Ratios & Proportional Relationships', 'ratios-proportional-relationships'],
            3 => ['Algebraic Expressions', 'algebraic-expressions'],
            4 => ['Solving & Graphing Equations', 'solving-graphing-equations'],
            5 => ['Solving & Graphing Inequalities', 'solving-graphing-inequalities'],
            6 => ['Triangles & Transformations', 'triangles-transformations'],
            7 => ['Volume & Area', 'volume-area'],
            8 => ['Probability & Statistics', 'probability-statistics']
        ];
        foreach ($sections as $number => $data) {
            $term = term_exists($data[0], self::TAX);
            if (!$term) $term = wp_insert_term($data[0], self::TAX, ['slug' => $data[1]]);
            if (!is_wp_error($term)) {
                $term_id = is_array($term) ? $term['term_id'] : intval($term);
                update_term_meta($term_id, 'mb_number', $number);
            }
        }
    }

    private function ensure_section_pages() {
        $parent = get_page_by_path('binder-topics', OBJECT, 'page');
        if (!$parent) {
            return;
        }

        $terms = get_terms([
            'taxonomy' => self::TAX,
            'hide_empty' => false,
            'orderby' => 'meta_value_num',
            'meta_key' => 'mb_number'
        ]);
        if (is_wp_error($terms)) {
            return;
        }

        foreach ($terms as $term) {
            $path = 'binder-topics/' . $term->slug;
            $page = get_page_by_path($path, OBJECT, 'page');
            $content = '[mathbinder_section slug="' . $term->slug . '"]';
            $page_data = [
                'post_type' => 'page',
                'post_status' => 'publish',
                'post_title' => $term->name,
                'post_name' => $term->slug,
                'post_parent' => $parent->ID,
                'post_content' => $content,
                'menu_order' => intval(get_term_meta($term->term_id, 'mb_number', true))
            ];

            if ($page) {
                if (get_post_meta($page->ID, '_mb_managed_section_page', true) === '1') {
                    $page_data['ID'] = $page->ID;
                    wp_update_post($page_data);
                    update_post_meta($page->ID, '_mb_section_slug', $term->slug);
                }
                continue;
            }

            $page_id = wp_insert_post($page_data);
            if ($page_id && !is_wp_error($page_id)) {
                update_post_meta($page_id, '_mb_managed_section_page', '1');
                update_post_meta($page_id, '_mb_section_slug', $term->slug);
            }
        }
    }

    public function redirect_legacy_section_archive() {
        if (!is_tax(self::TAX)) {
            return;
        }

        $term = get_queried_object();
        if (!$term || is_wp_error($term) || empty($term->slug)) {
            return;
        }

        wp_safe_redirect($this->section_page_url($term), 301);
        exit;
    }

    private function topic_preset($title) {
        $key = strtolower(trim($title));

        $presets = [
            'place value' => [
                'subtitle' => 'Understand how the position of a digit determines its value in whole numbers and decimals.',
                'essential_question' => 'How does the value of a digit change depending on its position in a number?',
                'difficulty' => 'beginner',
                'estimated_time' => '15–20 minutes',
                'prerequisites' => 'Recognizing digits 0–9 and reading whole numbers',
                'introduction' => '<p>Place value is the concept that the position, or place, of a digit in a number determines its value. Each position is ten times the value of the position to its right and one-tenth the value of the position to its left.</p><p>This Binder Page covers money and decimals, powers of ten, and comparing decimals.</p>',
                'learning_targets' => "Identify the value of a digit in a whole number or decimal.\nRead and write numbers in standard, expanded, and word form.\nCompare decimals using place value.\nExplain how powers of ten affect a digit’s value.",
                'vocabulary' => "Digit — any numeral from 0 to 9\nPlace — the location of a digit in a number\nValue — how much a digit is worth based on its place\nPlaceholder — a zero used to hold a place in a number",
                'worked_examples' => "Money and decimals: In $5.27, the 2 is in the tenths place and has a value of $0.20.\nPowers of ten: In 3,400,000, the 3 is in the millions place and has a value of 3,000,000.\nComparing decimals: Compare digits from left to right, adding placeholder zeros when helpful.",
                'common_questions' => "Why does the same digit have different values? | A digit’s value depends on its position in the number.\nWhy is zero important? | Zero can act as a placeholder so every other digit remains in the correct place.",
                'common_mistakes' => "Assuming a digit has the same value no matter where it appears.\nThinking that a longer decimal is always greater.\nConfusing tenths with hundredths.",
                'real_life' => 'Place value is used when reading money, measuring distances, comparing data, and writing very large or very small numbers.',
                'videos' => 'Place Value with Mr. J Playlist | https://www.youtube.com/watch?v=NOx6jlVSSpU&list=PLiT3pCvK_cfXYBJqLpwGYa3AV1w0RN5H-',
                'ixl' => "Convert Between Place Values | https://www.ixl.com/math/grade-4/convert-between-place-values\nPlace Value Lesson | https://www.ixl.com/math/lessons/place-value",
                'khan' => "Place Value Unit | https://www.khanacademy.org/math/cc-fourth-grade-math/imp-place-value-and-rounding-2\nDecimal Place Value | https://www.khanacademy.org/math/cc-fifth-grade-math/imp-place-value-and-decimals",
                'delta' => '',
                'desmos' => '',
                'parent_help' => "Ask your child to name both the place and the value of a digit.\nUse money to connect tenths and hundredths to dimes and pennies.\nWhen comparing decimals, line up decimal points and add placeholder zeros.",
                'master_it' => "I can identify the value of any digit.\nI can write a number in standard, expanded, and word form.\nI can compare two decimals and explain my reasoning.",
                'related_topics' => "Number Operations\nFractions & Decimals\nOrder of Operations (PEMDAS)\nScientific Notation"
            ],
            'number operations' => [
                'subtitle' => 'Use properties and efficient strategies to add, subtract, multiply, and divide rational numbers.',
                'essential_question' => 'How can number properties and operation strategies help us solve problems accurately?',
                'difficulty' => 'beginner',
                'estimated_time' => '20–25 minutes',
                'prerequisites' => 'Place value and basic multiplication facts',
                'introduction' => '<p>Number operations describe how quantities are combined, separated, grouped, and shared. Strong operation sense includes choosing an efficient method and checking whether an answer is reasonable.</p>',
                'learning_targets' => "Choose the correct operation for a situation.\nUse properties to calculate efficiently.\nEstimate to check whether an answer is reasonable.\nExplain the meaning of a remainder.",
                'vocabulary' => "Sum — the result of addition\nDifference — the result of subtraction\nProduct — the result of multiplication\nQuotient — the result of division\nInverse operations — operations that undo each other",
                'worked_examples' => "Estimate first: 398 × 21 is close to 400 × 20 = 8,000.\nUse the distributive property: 17 × 24 = 17 × 20 + 17 × 4.\nCheck division with multiplication.",
                'common_questions' => "How do I know which operation to use? | Identify what is happening to the quantities and what the question asks you to find.\nWhy should I estimate first? | Estimation helps you detect unreasonable answers.",
                'common_mistakes' => "Choosing an operation based only on a keyword.\nForgetting to regroup.\nIgnoring the meaning of a remainder.",
                'real_life' => 'Operations are used in budgeting, shopping, recipes, travel, measurement, and comparing quantities.',
                'videos' => '',
                'ixl' => '',
                'khan' => '',
                'delta' => '',
                'desmos' => '',
                'parent_help' => "Ask your child to estimate before calculating.\nHave your child explain why an operation makes sense.\nUse receipts, recipes, and travel distances for practice.",
                'master_it' => "I can choose an appropriate operation.\nI can calculate accurately.\nI can estimate and check my answer.\nI can explain what my answer means.",
                'related_topics' => "Place Value\nFractions & Decimals\nOrder of Operations (PEMDAS)"
            ],
            'fractions & decimals' => [
                'subtitle' => 'Connect fractions and decimals as different representations of the same quantity.',
                'essential_question' => 'How can the same value be represented as a fraction and as a decimal?',
                'difficulty' => 'intermediate',
                'estimated_time' => '20–25 minutes',
                'prerequisites' => 'Place value, multiplication, and division',
                'introduction' => '<p>Fractions and decimals describe parts of a whole. Converting between them helps learners compare values, solve problems, and choose the most useful representation.</p>',
                'learning_targets' => "Convert common fractions to decimals.\nConvert terminating decimals to fractions.\nCompare fractions and decimals.\nUse visual models to explain equivalence.",
                'vocabulary' => "Numerator — the number of selected parts\nDenominator — the total number of equal parts\nEquivalent — having the same value\nTerminating decimal — a decimal that ends",
                'worked_examples' => "3/4 = 3 ÷ 4 = 0.75.\n0.6 = 6/10 = 3/5.\nCompare by converting both numbers to decimals or equivalent fractions.",
                'common_questions' => "Why does division convert a fraction to a decimal? | A fraction bar represents division.\nCan every fraction become a terminating decimal? | No. Some fractions produce repeating decimals.",
                'common_mistakes' => "Comparing only numerators or denominators.\nWriting 0.5 as 5/100.\nForgetting to simplify a fraction.",
                'real_life' => 'Fractions and decimals appear in money, measurements, recipes, probability, and data.',
                'videos' => '',
                'ixl' => '',
                'khan' => '',
                'delta' => '',
                'desmos' => '',
                'parent_help' => "Connect quarters to 0.25 and halves to 0.5.\nUse measuring cups and money.\nAsk your child to draw a model before converting.",
                'master_it' => "I can convert between fractions and decimals.\nI can compare values in different forms.\nI can explain equivalence with a model.",
                'related_topics' => "Place Value\nNumber Operations\nProbability & Statistics"
            ],
            'order of operations (pemdas)' => [
                'subtitle' => 'Use an agreed sequence to evaluate numerical expressions accurately.',
                'essential_question' => 'Why does the order in which we perform operations matter?',
                'difficulty' => 'intermediate',
                'estimated_time' => '15–20 minutes',
                'prerequisites' => 'Whole-number operations and exponents',
                'introduction' => '<p>The order of operations ensures that everyone evaluates the same expression in the same way. Multiplication and division are completed from left to right, as are addition and subtraction.</p>',
                'learning_targets' => "Evaluate expressions with grouping symbols.\nEvaluate exponents.\nComplete multiplication and division from left to right.\nComplete addition and subtraction from left to right.",
                'vocabulary' => "Expression — numbers and operations without an equals sign\nGrouping symbols — parentheses, brackets, or fraction bars\nExponent — a number that shows repeated multiplication",
                'worked_examples' => "18 ÷ 3 × 2 = 6 × 2 = 12.\n4 + 3² × 2 = 4 + 9 × 2 = 22.\nEvaluate inside grouping symbols first.",
                'common_questions' => "Does multiplication always come before division? | No. Complete them from left to right.\nDoes addition always come before subtraction? | No. Complete them from left to right.",
                'common_mistakes' => "Reading PEMDAS as six separate priority levels.\nAdding before multiplying.\nMultiplying the base and exponent.",
                'real_life' => 'Order of operations is used in formulas, spreadsheets, calculators, coding, and scientific calculations.',
                'videos' => '',
                'ixl' => '',
                'khan' => '',
                'delta' => '',
                'desmos' => '',
                'parent_help' => "Have your child mark grouping symbols and exponents first.\nEncourage one step per line.\nAsk why two operations share the same priority.",
                'master_it' => "I can evaluate an expression in the correct order.\nI can explain left-to-right rules.\nI can find and correct an order-of-operations error.",
                'related_topics' => "Number Operations\nAlgebraic Expressions\nExponents"
            ],
            'real & complex number systems' => [
                'subtitle' => 'Classify numbers and understand how number sets fit inside one another.',
                'essential_question' => 'How are different kinds of numbers related?',
                'difficulty' => 'advanced',
                'estimated_time' => '20–25 minutes',
                'prerequisites' => 'Integers, fractions, decimals, and square roots',
                'introduction' => '<p>The number system organizes numbers into nested sets. Natural numbers are contained within whole numbers, integers, rational numbers, real numbers, and eventually complex numbers.</p>',
                'learning_targets' => "Classify numbers into appropriate sets.\nDistinguish rational and irrational numbers.\nExplain the nested structure of the real number system.\nIdentify the real and imaginary parts of a complex number.",
                'vocabulary' => "Natural numbers — counting numbers\nIntegers — positive and negative whole numbers and zero\nRational numbers — numbers expressible as a fraction\nIrrational numbers — nonterminating, nonrepeating decimals\nComplex number — a number in the form a + bi",
                'worked_examples' => "−4 is an integer, rational number, and real number.\n√2 is irrational and real.\n3 + 2i is complex with real part 3 and imaginary part 2i.",
                'common_questions' => "Can a number belong to more than one set? | Yes. Number sets are nested.\nIs every decimal rational? | No. Nonterminating, nonrepeating decimals are irrational.",
                'common_mistakes' => "Listing only the smallest number set.\nAssuming every square root is irrational.\nConfusing i with a variable.",
                'real_life' => 'Number classifications support algebra, geometry, engineering, electronics, and advanced mathematics.',
                'videos' => '',
                'ixl' => '',
                'khan' => '',
                'delta' => '',
                'desmos' => '',
                'parent_help' => "Use a nesting diagram for the number sets.\nAsk your child to justify every classification.\nCompare terminating, repeating, and nonrepeating decimals.",
                'master_it' => "I can classify a number in every applicable set.\nI can distinguish rational and irrational numbers.\nI can identify parts of a complex number.",
                'related_topics' => "Fractions & Decimals\nSquare Roots\nAlgebraic Expressions"
            ]
        ];

        if (isset($presets[$key])) {
            return $presets[$key];
        }

        return [
            'subtitle' => '',
            'essential_question' => '',
            'difficulty' => 'beginner',
            'estimated_time' => '15–20 minutes',
            'prerequisites' => 'None',
            'introduction' => '',
            'learning_targets' => '',
            'vocabulary' => '',
            'worked_examples' => '',
            'common_questions' => '',
            'common_mistakes' => '',
            'real_life' => '',
            'videos' => '',
            'ixl' => '',
            'khan' => '',
            'delta' => '',
            'desmos' => '',
            'parent_help' => '',
            'master_it' => '',
            'related_topics' => ''
        ];
    }

    private function migrate_place_value($post_id) {
        $preset = $this->topic_preset('Place Value');
        foreach ($preset as $key => $value) {
            if (!get_post_meta($post_id, '_mb_' . $key, true)) update_post_meta($post_id, '_mb_' . $key, $value);
        }
    }

    private function public_page_definitions() {
        return [
            'parents' => [
                'title' => 'Parent Resources',
                'shortcode' => '[mathbinder_parents]',
                'slugs' => ['parents', 'parent-resources']
            ],
            'teachers' => [
                'title' => 'Teacher Resources',
                'shortcode' => '[mathbinder_teachers]',
                // The original PopularFX navigation uses /courses/.
                'slugs' => ['courses', 'teachers', 'teacher-resources']
            ],
            'about' => [
                'title' => 'About Us',
                'shortcode' => '[mathbinder_about]',
                // The original PopularFX navigation uses /about/.
                'slugs' => ['about', 'about-us']
            ],
            'contact' => [
                'title' => 'Contact Us',
                'shortcode' => '[mathbinder_contact]',
                'slugs' => ['contact', 'contact-us']
            ],
            'assignment_helper' => [
                'title' => 'AI Assignment Tutor',
                'shortcode' => '[mathbinder_assignment_helper]',
                'slugs' => ['assignment-helper', 'ai-assignment-helper']
            ],
            'my_binder' => [
                'title' => 'My MathBinder',
                'shortcode' => '[mathbinder_collection]',
                'slugs' => ['your-binder']
            ],
            'evidence_folder' => [
                'title' => 'Evidence Folder',
                'shortcode' => '[mathbinder_evidence_folder]',
                'slugs' => ['evidence-folder']
            ],
            'getting_started' => [
                'title' => 'Getting Started',
                'shortcode' => '[mathbinder_getting_started]',
                'slugs' => ['getting-started']
            ],
            'privacy' => [
                'title' => 'Privacy Policy',
                'shortcode' => '[mathbinder_privacy]',
                'slugs' => ['privacy-policy']
            ],
            'terms' => [
                'title' => 'Terms of Use',
                'shortcode' => '[mathbinder_terms]',
                'slugs' => ['terms-of-use']
            ],
            'premium_access' => [
                'title' => 'Premium Access',
                'shortcode' => '[mathbinder_premium_access]',
                'slugs' => ['premium-access']
            ],
        ];
    }

    private function public_page_for_key($key) {
        $definitions = $this->public_page_definitions();
        if (!isset($definitions[$key])) return null;
        foreach ($definitions[$key]['slugs'] as $slug) {
            $page = get_page_by_path($slug, OBJECT, 'page');
            if ($page) return $page;
        }
        return null;
    }

    private function public_page_url($key) {
        $page = $this->public_page_for_key($key);
        if ($page) return get_permalink($page->ID);
        $definitions = $this->public_page_definitions();
        $slug = isset($definitions[$key]) ? $definitions[$key]['slugs'][0] : '';
        return home_url('/' . trim($slug, '/') . '/');
    }

    private function ensure_public_pages() {
        $pages = $this->public_page_definitions();
        $resolved = [];

        foreach ($pages as $key => $details) {
            $page = $this->public_page_for_key($key);
            $slug = $page ? $page->post_name : $details['slugs'][0];
            $data = [
                'post_type' => 'page',
                'post_status' => 'publish',
                'post_title' => $details['title'],
                'post_name' => $slug,
                'post_content' => $details['shortcode']
            ];

            if ($page) {
                $data['ID'] = $page->ID;
                wp_update_post($data);
                update_post_meta($page->ID, '_mb_managed_public_page', '1');
                $resolved[$key] = $page->ID;
            } else {
                $page_id = wp_insert_post($data);
                if ($page_id && !is_wp_error($page_id)) {
                    update_post_meta($page_id, '_mb_managed_public_page', '1');
                    $resolved[$key] = $page_id;
                }
            }
        }

        $this->retire_standalone_interactive_notebook();
        $this->repair_public_page_menu_items($resolved);
    }

    private function retire_standalone_interactive_notebook() {
        $notebook_pages = [];
        foreach (['interactive-notebook', 'my-interactive-notebook'] as $slug) {
            $page = get_page_by_path($slug, OBJECT, 'page');
            if ($page) $notebook_pages[intval($page->ID)] = $page;
        }

        foreach ($notebook_pages as $page) {
            if (get_post_meta($page->ID, '_mb_managed_public_page', true) === '1') {
                wp_update_post([
                    'ID' => $page->ID,
                    'post_status' => 'draft'
                ]);
            }
        }

        foreach (wp_get_nav_menus() as $menu) {
            foreach ((array) wp_get_nav_menu_items($menu->term_id) as $item) {
                $title = strtolower(trim(wp_strip_all_tags($item->title)));
                $object_id = intval($item->object_id);
                if (in_array($title, ['interactive notebook', 'my interactive notebook'], true)
                    || isset($notebook_pages[$object_id])) {
                    wp_delete_post($item->ID, true);
                }
            }
        }
    }

    private function repair_public_page_menu_items($resolved) {
        if (!$resolved) return;
        $labels = [
            'parents' => ['parent', 'parents', 'parent resources'],
            'teachers' => ['teacher', 'teachers', 'teacher resources'],
            'about' => ['about', 'about us'],
            'contact' => ['contact', 'contact us'],
            'assignment_helper' => ['ai assignment tutor', 'assignment tutor', 'ai assignment helper', 'assignment helper'],
            'my_binder' => ['my mathbinder', 'mathbinder', 'your binder'],
        ];

        foreach (wp_get_nav_menus() as $menu) {
            $items = wp_get_nav_menu_items($menu->term_id);
            if (!$items) continue;
            $binder_item = null;
            $parents_item = null;
            $helper_item = null;
            $my_binder_item = null;

            foreach ($items as $item) {
                $title = strtolower(trim(wp_strip_all_tags($item->title)));
                if (in_array($title, ['binder topics', 'binder sections'], true)) {
                    $binder_item = $item;
                }
                if (in_array($title, $labels['parents'], true)) {
                    $parents_item = $item;
                }
                if (in_array($title, $labels['assignment_helper'], true)) {
                    $helper_item = $item;
                }
                if (in_array($title, $labels['my_binder'], true)) {
                    $my_binder_item = $item;
                }

                foreach ($labels as $key => $accepted) {
                    if (!isset($resolved[$key]) || !in_array($title, $accepted, true)) continue;
                    wp_update_nav_menu_item($menu->term_id, $item->ID, [
                        'menu-item-title' => $key === 'assignment_helper' ? 'AI Assignment Tutor' : ($key === 'my_binder' ? 'My MathBinder' : $item->title),
                        'menu-item-object-id' => $resolved[$key],
                        'menu-item-object' => 'page',
                        'menu-item-type' => 'post_type',
                        'menu-item-status' => 'publish',
                        'menu-item-parent-id' => $item->menu_item_parent,
                        'menu-item-position' => $item->menu_order,
                        'menu-item-target' => $item->target,
                        'menu-item-classes' => implode(' ', array_filter((array) $item->classes)),
                        'menu-item-xfn' => $item->xfn,
                        'menu-item-description' => $item->description
                    ]);
                    break;
                }
            }

            // Keep the student destinations together in the regular header menu:
            // Binder Sections → My MathBinder → AI Assignment Tutor.
            if (isset($resolved['assignment_helper'], $resolved['my_binder']) && $binder_item && $parents_item) {
                $my_binder_item_id = $my_binder_item ? $my_binder_item->ID : 0;
                $my_binder_item_id = wp_update_nav_menu_item($menu->term_id, $my_binder_item_id, [
                    'menu-item-title' => 'My MathBinder',
                    'menu-item-object-id' => $resolved['my_binder'],
                    'menu-item-object' => 'page',
                    'menu-item-type' => 'post_type',
                    'menu-item-status' => 'publish',
                    'menu-item-position' => intval($binder_item->menu_order) + 1
                ]);
                $helper_item_id = $helper_item ? $helper_item->ID : 0;
                $helper_item_id = wp_update_nav_menu_item($menu->term_id, $helper_item_id, [
                    'menu-item-title' => 'AI Assignment Tutor',
                    'menu-item-object-id' => $resolved['assignment_helper'],
                    'menu-item-object' => 'page',
                    'menu-item-type' => 'post_type',
                    'menu-item-status' => 'publish',
                    'menu-item-position' => intval($binder_item->menu_order) + 2
                ]);

                // WordPress may resolve a single requested position after an
                // existing neighboring item instead of before it. Rebuild the
                // current top-level sequence explicitly, keeping every other
                // item in its existing relative order.
                if ($my_binder_item_id && !is_wp_error($my_binder_item_id) && $helper_item_id && !is_wp_error($helper_item_id)) {
                    $ordered_items = wp_get_nav_menu_items($menu->term_id, [
                        'orderby' => 'menu_order',
                        'order' => 'ASC'
                    ]);
                    $top_level_ids = [];
                    $my_binder_id = intval($my_binder_item_id);
                    $helper_id = intval($helper_item_id);
                    $binder_id = intval($binder_item->ID);

                    foreach ((array) $ordered_items as $ordered_item) {
                        if (intval($ordered_item->menu_item_parent) !== 0) continue;
                        $item_id = intval($ordered_item->ID);
                        if ($item_id !== $my_binder_id && $item_id !== $helper_id) $top_level_ids[] = $item_id;
                    }

                    $binder_index = array_search($binder_id, $top_level_ids, true);
                    if ($binder_index !== false) {
                        array_splice($top_level_ids, $binder_index + 1, 0, [$my_binder_id, $helper_id]);
                        foreach ($top_level_ids as $index => $item_id) {
                            wp_update_post([
                                'ID' => $item_id,
                                'menu_order' => $index + 1
                            ]);
                        }
                        clean_term_cache($menu->term_id, 'nav_menu');
                    }
                }
            }

        }
    }

    private function public_page_header($eyebrow, $title, $description) {
        $logo_url = plugin_dir_url(__FILE__) . 'Assests/Icons/mathbinder-icon.svg';

        return '<header class="mb-public-hero"><div class="mb-public-hero-mark"><img src="' .
            esc_url($logo_url) . '" alt="MathBinder logo"></div><div><p class="mb-public-eyebrow">' .
            esc_html($eyebrow) . '</p><h1>' . esc_html($title) . '</h1><p>' .
            esc_html($description) . '</p></div></header>';
    }

    public function parents_shortcode() {
        if (class_exists('MathBinder_Family_Account') && MathBinder_Family_Account::is_parent()) {
            return MathBinder_Family_Account::render_dashboard();
        }
        ob_start(); ?>
        <main class="mb-public-page mb-parents-page">
            <?php echo $this->public_page_header('Support at home', 'Parent Resources', 'Simple, practical ways to help your student build confidence, explain their thinking, and keep moving forward in math.'); ?>
            <section class="mb-public-intro">
                <div><span>Start here</span><h2>You do not have to reteach the whole lesson.</h2><p>MathBinder gives families short explanations, worked examples, videos, practice choices, and conversation starters. Begin with the student’s current lesson and use one small support at a time.</p></div>
                <a class="mb-public-button" href="<?php echo esc_url(home_url('/binder-topics/')); ?>">Find a math topic</a>
            </section>
            <section class="mb-public-grid mb-public-grid-three">
                <article><div class="mb-public-icon">1</div><h3>Ask what they notice</h3><p>Before correcting the work, ask your student to explain what the problem is asking and which step feels confusing.</p></article>
                <article><div class="mb-public-icon">2</div><h3>Use the lesson tabs</h3><p>Move through Teach It, Watch It, and Practice It. The Parent Help tab provides lesson-specific prompts and a five-minute review.</p></article>
                <article><div class="mb-public-icon">3</div><h3>Celebrate the revision</h3><p>Focus on the thinking that improved—not only the final answer. Productive struggle helps mathematical confidence grow.</p></article>
            </section>
            <section class="mb-public-band">
                <div><p class="mb-public-eyebrow">A helpful routine</p><h2>Try the 5–10–5 approach</h2></div>
                <ol><li><strong>5 minutes:</strong> Read the lesson overview and identify the goal.</li><li><strong>10 minutes:</strong> Watch one video or work through one example together.</li><li><strong>5 minutes:</strong> Let the student try one problem independently and explain the strategy.</li></ol>
            </section>
            <section class="mb-public-split">
                <div><p class="mb-public-eyebrow">When your student is stuck</p><h2>Questions that support thinking</h2><ul><li>What do you already know?</li><li>Can you draw, model, or estimate it?</li><li>Where did your answer stop making sense?</li><li>Which example in the lesson looks most similar?</li><li>How could you check your answer another way?</li></ul></div>
                <aside><h3>Remember</h3><p>It is okay to pause. A short break, a simpler example, or returning to a prerequisite topic can be more productive than pushing through frustration.</p><a href="<?php echo esc_url($this->public_page_url('contact')); ?>">Contact MathBinder support</a></aside>
            </section>
        </main>
        <?php return ob_get_clean();
    }

    public function teachers_shortcode() {
        ob_start(); ?>
        <main class="mb-public-page mb-teachers-page">
            <?php echo $this->public_page_header('Plan • Teach • Respond', 'Teacher Resources', 'Use MathBinder as a flexible instructional companion for whole-group teaching, small-group intervention, independent practice, and family support.'); ?>
            <section class="mb-public-intro">
                <div><span>Built for flexible instruction</span><h2>Choose the support your students need.</h2><p>Each lesson combines concise teaching, visual examples, carefully selected videos, practice pathways, common misconceptions, and teacher-facing instructional notes.</p></div>
                <a class="mb-public-button" href="<?php echo esc_url(home_url('/binder-topics/')); ?>">Browse lesson sections</a>
            </section>
            <section class="mb-public-grid mb-public-grid-four">
                <article><div class="mb-public-icon">A</div><h3>Launch</h3><p>Use the essential question and At a Glance information to frame the learning goal.</p></article>
                <article><div class="mb-public-icon">B</div><h3>Model</h3><p>Select a worked example or Watch It video for a focused mini-lesson.</p></article>
                <article><div class="mb-public-icon">C</div><h3>Practice</h3><p>Assign a targeted resource or use the practice progression for gradual release.</p></article>
                <article><div class="mb-public-icon">D</div><h3>Respond</h3><p>Use misconceptions, formative checks, and differentiation notes to plan the next step.</p></article>
            </section>
            <section class="mb-public-band">
                <div><p class="mb-public-eyebrow">Inside every complete lesson</p><h2>Instructional tools in one place</h2></div>
                <ul class="mb-public-checks"><li>Learning targets and vocabulary</li><li>Worked examples and common mistakes</li><li>Videos and external practice resources</li><li>Parent help and home-review prompts</li><li>Teacher objectives and pacing</li><li>Differentiation and formative assessment</li></ul>
            </section>
            <section class="mb-public-split">
                <div><p class="mb-public-eyebrow">Suggested uses</p><h2>Make MathBinder fit your setting</h2><ul><li>Link one lesson inside your LMS.</li><li>Use a video before a small-group conference.</li><li>Send the Parent Help section after a family meeting.</li><li>Use common mistakes to plan an exit ticket.</li><li>Let students revisit prerequisite lessons independently.</li></ul></div>
                <aside><h3>Teacher principle</h3><p>MathBinder is designed to supplement professional instruction and curriculum—not replace teacher judgment. Select, adapt, and sequence resources for the learners in front of you.</p><a href="<?php echo esc_url($this->public_page_url('contact')); ?>">Share feedback or request a topic</a></aside>
            </section>
        </main>
        <?php return ob_get_clean();
    }

    public function about_shortcode() {
        ob_start(); ?>
        <main class="mb-public-page mb-about-page">
            <?php echo $this->public_page_header('Find it. Learn it. Master it.', 'About MathBinder', 'A student-friendly digital math binder created to make trustworthy help easier to find, understand, and use.'); ?>
            <section class="mb-public-story">
                <div><p class="mb-public-eyebrow">Why MathBinder exists</p><h2>Math help should feel organized—not overwhelming.</h2><p>Students and families often search across many websites, videos, and assignments before finding an explanation that makes sense. MathBinder brings those supports together by topic, so learners can focus on the mathematics instead of searching for it.</p><p>The site begins with middle-school mathematics and is designed to grow into a K–12 resource while preserving the same clear, consistent lesson experience.</p></div>
                <div class="mb-public-values"><article><strong>Find it.</strong><p>Search by math topic instead of guessing a grade level or course name.</p></article><article><strong>Learn it.</strong><p>Choose explanations, examples, and videos that match how you learn.</p></article><article><strong>Master it.</strong><p>Practice, reflect, revise, and build confidence over time.</p></article></div>
            </section>
            <section class="mb-public-band mb-public-mission">
                <div><p class="mb-public-eyebrow">Our mission</p><h2>Make high-quality math support accessible, understandable, and empowering.</h2></div>
                <p>MathBinder supports student agency, mathematical reasoning, and productive struggle. It gives teachers and families practical tools while keeping the learner’s thinking at the center.</p>
            </section>
            <section class="mb-public-grid mb-public-grid-three">
                <article><div class="mb-public-icon">✓</div><h3>Student centered</h3><p>Pages use predictable sections, plain language, and multiple ways to engage with a concept.</p></article>
                <article><div class="mb-public-icon">∞</div><h3>Built to grow</h3><p>The binder structure supports new lessons, grade bands, and tools without becoming harder to navigate.</p></article>
                <article><div class="mb-public-icon">★</div><h3>Educator guided</h3><p>Content is shaped by classroom experience, instructional practice, and a commitment to useful resources.</p></article>
            </section>
            <div class="mb-public-cta"><div><h2>Ready to explore?</h2><p>Open the binder and choose the topic you need today.</p></div><a class="mb-public-button" href="<?php echo esc_url(home_url('/binder-topics/')); ?>">Explore the Binder</a></div>
        </main>
        <?php return ob_get_clean();
    }

    public function getting_started_shortcode() {
        ob_start(); ?>
        <main class="mb-public-page mb-launch-page">
            <?php echo $this->public_page_header('Welcome to MathBinder', 'Getting Started', 'Choose your role for a clear path from account setup to confident learning.'); ?>
            <section class="mb-public-grid mb-public-grid-four">
                <article><div class="mb-public-icon">S</div><h3>Students</h3><ol><li>Log in with your permanent MathBinder account.</li><li>Open Student Dashboard and join a class with the teacher’s class code.</li><li>Open Assigned Learning or Continue Learning.</li><li>Use Teach It, Watch It, Practice It, My Math Notes, and the mastery check.</li><li>Track progress, badges, saved work, and binder decorations.</li></ol><a href="<?php echo esc_url(home_url('/student-dashboard/')); ?>">Open Student Dashboard</a></article>
                <article><div class="mb-public-icon">P</div><h3>Parents &amp; caregivers</h3><ol><li>Create and verify the adult account.</li><li>Add each child and approve access when using a family account.</li><li>Manage child logins and available premium spots.</li><li>Use Parent Help inside lessons to support learning.</li><li>Contact support without sharing passwords or student records.</li></ol><a href="<?php echo esc_url($this->public_page_url('parents')); ?>">Open Parent Resources</a></article>
                <article><div class="mb-public-icon">T</div><h3>Teachers</h3><ol><li>Log in to the Teacher Dashboard.</li><li>Confirm the correct organization, term, and class.</li><li>Share the class code with authorized students.</li><li>Build, preview, publish, and assign a Mastery Path.</li><li>Review progress, evidence, mastery, and exported reports.</li></ol><a href="<?php echo esc_url(home_url('/teacher-dashboard/')); ?>">Open Teacher Dashboard</a></article>
                <article><div class="mb-public-icon">A</div><h3>Administrators</h3><ol><li>Create the organization and active term.</li><li>Create classes and assign teachers.</li><li>Review memberships and enrollment.</li><li>Allocate, activate, or revoke premium seats.</li><li>Monitor license usage, roles, and audit history.</li></ol><a href="<?php echo esc_url(admin_url('admin.php?page=mathbinder-organizations')); ?>">Open Organization Tools</a></article>
            </section>
            <section class="mb-public-band"><div><p class="mb-public-eyebrow">Student authorization</p><h2>Two safe ways for a minor to begin</h2></div><div><p><strong>Family path:</strong> A verified parent or caregiver creates or approves the child account.</p><p><strong>School path:</strong> An authorized school organization enrolls the student under its approved process, without requiring a duplicate family account.</p><p>A learner keeps one MathBinder identity as roles, classes, schools, licenses, and future connections change.</p></div></section>
            <section class="mb-public-split"><div><p class="mb-public-eyebrow">Need help?</p><h2>Account and access recovery</h2><p>Do not create a second account when a student changes classes, schools, or plans. Use password recovery first, then contact support for duplicate-account or school-parent connection help.</p></div><aside><h3>Protect student information</h3><p>Never send passwords, grades, student records, or private school documents through the contact form.</p><a href="<?php echo esc_url($this->public_page_url('contact')); ?>">Contact MathBinder support</a></aside></section>
        </main>
        <?php return ob_get_clean();
    }

    public function privacy_shortcode() {
        ob_start(); ?>
        <main class="mb-public-page mb-launch-page mb-policy-page">
            <?php echo $this->public_page_header('Effective August 5, 2026', 'Privacy Policy', 'How MathBinder handles account, learning, organization, and support information.'); ?>
            <section class="mb-public-band"><div><h2>Privacy at a glance</h2></div><div><p>MathBinder collects only information needed to provide accounts, classes, learning activities, progress, support, licensing, and security. Access is limited by role and organization. MathBinder does not ask users to place passwords, grades, or student records in public forms.</p><p><strong>Important:</strong> This policy must be reviewed by MathBinder’s owner and qualified counsel before public commercial or school launch.</p></div></section>
            <section class="mb-public-grid mb-public-grid-two">
                <article><h2>Information we process</h2><ul><li>Account and verification details, such as name, email, role, and login status.</li><li>Parent-child and school-authorized enrollment relationships.</li><li>Organization, term, class, membership, invitation, license, and seat records.</li><li>Assignments, lesson completion, notes, saved items, evidence, mastery attempts, scores, and badges.</li><li>Support messages, security events, audit history, and essential technical logs.</li><li>Subscription status and payment-provider references; MathBinder should not store full payment-card numbers.</li></ul></article>
                <article><h2>How information is used</h2><ul><li>Provide and secure the service.</li><li>Connect authorized learners with families, teachers, classes, and assignments.</li><li>Save progress across devices and display authorized reports.</li><li>Manage premium access, licenses, seats, trials, and account recovery.</li><li>Respond to support requests and maintain audit records.</li><li>Improve accessibility, reliability, and learning workflows.</li></ul></article>
                <article><h2>Visibility and sharing</h2><p>Students see their own learning information. Verified parents see connected children as authorized. Teachers and administrators see only learners and records within their assigned classes or organization scope. Service providers receive only information needed to operate their function and must be governed by appropriate agreements. MathBinder does not sell student personal information.</p></article>
                <article><h2>Retention and account changes</h2><p>MathBinder preserves learning work when a class, license, subscription, school relationship, or future Canvas connection ends, unless deletion is requested or law requires another result. Retention periods should match educational, contractual, security, and legal needs. Cancellation should stop paid coverage without silently erasing student work.</p></article>
                <article><h2>Choices and requests</h2><p>Authorized users may request access, correction, export, connection review, or deletion through the Contact page. MathBinder must verify identity and authority before changing a minor’s or organization’s records. Some security, transaction, or audit records may be retained when legally required.</p></article>
                <article><h2>Children and school use</h2><p>A minor may be authorized by a verified parent/caregiver or through an approved school process. Schools remain responsible for their notices, permissions, and legal basis for student use. MathBinder should use privacy-protective defaults and avoid public student reporting.</p></article>
            </section>
            <div class="mb-public-cta"><div><h2>Privacy question or request?</h2><p>Use the support form and select the closest subject. Do not include passwords or unnecessary student records.</p></div><a class="mb-public-button" href="<?php echo esc_url($this->public_page_url('contact')); ?>">Contact MathBinder</a></div>
        </main>
        <?php return ob_get_clean();
    }

    public function terms_shortcode() {
        ob_start(); ?>
        <main class="mb-public-page mb-launch-page mb-policy-page">
            <?php echo $this->public_page_header('Effective August 5, 2026', 'Terms of Use', 'Rules for using MathBinder accounts, lessons, classes, and premium features.'); ?>
            <section class="mb-public-band"><div><h2>Agreement and review</h2></div><div><p>By using MathBinder, users agree to use the service lawfully, protect account access, respect educational content and other users, and follow the rules below. Parents, schools, and organizations are responsible for authorizing minors and managing their assigned users.</p><p><strong>Important:</strong> These terms are a launch draft and must be reviewed by MathBinder’s owner and qualified counsel before public commercial or school launch.</p></div></section>
            <section class="mb-public-grid mb-public-grid-two">
                <article><h2>Accounts and authorization</h2><p>Provide accurate information, keep credentials private, and use one permanent identity rather than creating duplicates. A parent/caregiver or authorized school must approve a minor’s access. Teachers and administrators may use only records within their authorized scope.</p></article>
                <article><h2>Acceptable use</h2><p>Do not disrupt the service, bypass access controls, impersonate another person, upload harmful code, expose student information, misuse AI or assessment tools, or copy and redistribute protected materials beyond allowed educational use.</p></article>
                <article><h2>Learning and assessment</h2><p>MathBinder supports instruction and practice but does not replace professional educational judgment. Teachers and schools remain responsible for assignments, grades, accommodations, placement, and high-stakes decisions. Automated or AI feedback must not independently determine grades or mastery.</p></article>
                <article><h2>Premium access and billing</h2><p>Paid plans, trials, licenses, and seats provide the features shown at purchase or allocation. Coverage may have priority rules when family and organization access overlap. Payment failure may trigger a stated grace period. Cancellation ends future paid coverage but should not erase saved student work.</p></article>
                <article><h2>Content and external services</h2><p>MathBinder owns or licenses its original site content. Linked or embedded resources remain subject to their owners’ terms. Users retain ownership of their original notes and submitted work while granting MathBinder the limited permission needed to store and display that content for the service.</p></article>
                <article><h2>Availability and changes</h2><p>Features may be updated for safety, law, accessibility, or reliability. MathBinder may suspend accounts or access that threaten users, data, or the service. Material term changes should be posted with a new effective date and any notice required by law or contract.</p></article>
            </section>
            <div class="mb-public-cta"><div><h2>Questions about these terms?</h2><p>Contact MathBinder before using the service if you do not understand an account, school, or premium-access requirement.</p></div><a class="mb-public-button" href="<?php echo esc_url($this->public_page_url('contact')); ?>">Contact MathBinder</a></div>
        </main>
        <?php return ob_get_clean();
    }

    public function premium_access_shortcode() {
        ob_start(); ?>
        <main class="mb-public-page mb-launch-page">
            <?php echo $this->public_page_header('Plans • Licenses • Seats', 'Premium Access', 'Understand how family and organization access is assigned without losing student work.'); ?>
            <section class="mb-public-grid mb-public-grid-three">
                <article><div class="mb-public-icon">F</div><h3>Family Premium</h3><p>A verified parent or caregiver manages the plan and available child spots. Child access can be paused or restored without creating a new learner identity.</p></article>
                <article><div class="mb-public-icon">O</div><h3>Organization access</h3><p>A school or organization manages its license, terms, classes, teachers, memberships, and premium-seat allocations. Seats may be assigned by account email before a matching WordPress user exists.</p></article>
                <article><div class="mb-public-icon">1</div><h3>One student identity</h3><p>When family and organization coverage overlap, MathBinder applies the appropriate active coverage while preserving one account, learning history, notes, mastery, and badges.</p></article>
            </section>
            <section class="mb-public-band"><div><p class="mb-public-eyebrow">Coverage lifecycle</p><h2>What happens when access changes?</h2></div><ol><li><strong>Trial or plan begins:</strong> Eligible premium features activate after confirmation.</li><li><strong>A seat is allocated:</strong> The allocation is reviewable and auditable by the organization.</li><li><strong>Coverage overlaps:</strong> Active family or organization coverage is resolved without duplicating the student.</li><li><strong>Payment fails or a seat is revoked:</strong> Any configured grace or status rules apply.</li><li><strong>Coverage ends:</strong> Premium features may pause, but saved student work is preserved according to the Privacy Policy.</li></ol></section>
            <section class="mb-public-split"><div><h2>Before purchasing or assigning access</h2><ul><li>Review the current plan, price, trial, renewal, cancellation, and seat limits shown at checkout or in the license dashboard.</li><li>Use the account email that should own or receive access.</li><li>Confirm the correct organization and term before allocating school seats.</li><li>Do not create a second learner account to solve an access problem.</li></ul></div><aside><h3>Billing or seat help</h3><p>Contact support for duplicate accounts, incorrect coverage, failed linking, cancellations, or seat-allocation questions.</p><a href="<?php echo esc_url($this->public_page_url('contact')); ?>">Contact MathBinder support</a></aside></section>
        </main>
        <?php return ob_get_clean();
    }

    public function contact_shortcode() {
        $status = isset($_GET['mb_contact']) ? sanitize_key(wp_unslash($_GET['mb_contact'])) : '';
        ob_start(); ?>
        <main class="mb-public-page mb-contact-page">
            <?php echo $this->public_page_header('We would love to hear from you', 'Contact MathBinder', 'Ask a question, report a problem, suggest a lesson, or share how MathBinder is helping your student or class.'); ?>
            <?php if ($status === 'success') : ?><div class="mb-contact-message mb-contact-success" role="status"><strong>Message sent.</strong> Thank you for contacting MathBinder. We will respond as soon as possible.</div><?php endif; ?>
            <?php if ($status === 'error') : ?><div class="mb-contact-message mb-contact-error" role="alert"><strong>Your message could not be sent.</strong> Please check the required fields and try again.</div><?php endif; ?>
            <section class="mb-contact-layout">
                <div class="mb-contact-info">
                    <p class="mb-public-eyebrow">How can we help?</p><h2>Send us a message.</h2>
                    <div class="mb-contact-reasons"><article><span>?</span><div><h3>Questions</h3><p>Get help finding or using a MathBinder resource.</p></div></article><article><span>＋</span><div><h3>Topic requests</h3><p>Suggest a concept you would like added to the binder.</p></div></article><article><span>!</span><div><h3>Corrections</h3><p>Tell us about a broken link, unclear explanation, or technical issue.</p></div></article></div>
                    <p class="mb-contact-note">Please do not include student records, passwords, grades, or other sensitive personal information.</p>
                </div>
                <form class="mb-contact-form" action="<?php echo esc_url(admin_url('admin-post.php')); ?>" method="post">
                    <input type="hidden" name="action" value="mb_contact_submit">
                    <?php wp_nonce_field('mb_contact_submit', 'mb_contact_nonce'); ?>
                    <div class="mb-contact-hp" aria-hidden="true"><label>Leave this field empty<input type="text" name="website" tabindex="-1" autocomplete="off"></label></div>
                    <label>Your name <span>*</span><input type="text" name="mb_name" required maxlength="100" autocomplete="name"></label>
                    <label>Email address <span>*</span><input type="email" name="mb_email" required maxlength="190" autocomplete="email"></label>
                    <label>I am a <select name="mb_role"><option value="student">Student</option><option value="parent">Parent or caregiver</option><option value="teacher">Teacher or educator</option><option value="other">Other</option></select></label>
                    <label>Subject <span>*</span><select name="mb_subject" required><option value="General question">General question</option><option value="Account or access">Account or access</option><option value="Billing or premium seat">Billing or premium seat</option><option value="Privacy request">Privacy request</option><option value="Topic request">Topic request</option><option value="Broken link or correction">Broken link or correction</option><option value="Technical problem">Technical problem</option><option value="Feedback">Feedback</option></select></label>
                    <label>Message <span>*</span><textarea name="mb_message" required maxlength="4000" rows="7"></textarea></label>
                    <button class="mb-public-button" type="submit">Send message</button>
                </form>
            </section>
        </main>
        <?php return ob_get_clean();
    }

    private function place_value_notebook_activities() {
        return [
            [
                'id' => 'place-value-targets',
                'type' => 'I Can + Reflection',
                'title' => 'Place Value Learning Targets',
                'description' => 'Check each goal as it becomes comfortable, then reflect on what helped.',
                'prompt' => 'I can name the place and value of a digit.|I can read and write whole numbers in standard, word, and expanded form.|I can compare whole numbers using <, >, and =.|I can explain how a digit’s value changes when it moves one place.',
                'fields' => 'One goal I feel confident about|One goal I want to practice|A strategy that helped me'
            ],
            [
                'id' => 'place-value-vocabulary',
                'type' => 'Vocabulary Foldable',
                'title' => 'Place Value Vocabulary Flaps',
                'description' => 'Define, illustrate, and use four important words. The print version includes cut and fold guides.',
                'prompt' => 'digit|place|value|expanded form',
                'fields' => 'My example|A picture or model|In my own words'
            ],
            [
                'id' => 'place-value-chart',
                'type' => 'Foldable Notes',
                'title' => 'Build a Place Value Chart',
                'description' => 'Organize a number by period, place, digit, and value.',
                'prompt' => 'Millions|Hundred Thousands|Ten Thousands|Thousands|Hundreds|Tens|Ones',
                'fields' => 'Number I am modeling|Expanded form|Number in words'
            ],
            [
                'id' => 'place-value-worked-example',
                'type' => 'Worked Example',
                'title' => 'I Do • We Do • You Do',
                'description' => 'Study one model, complete one guided example, and explain one independently.',
                'prompt' => 'I Do: 483,216 = 400,000 + 80,000 + 3,000 + 200 + 10 + 6|We Do: Write 705,094 in expanded form.|You Do: Choose a six-digit number and show three forms.',
                'fields' => 'My guided work|My independent example|How I checked my answer'
            ],
            [
                'id' => 'place-value-anchor-chart',
                'type' => 'Anchor Chart',
                'title' => 'Place Value Quick Reference',
                'description' => 'Create a personal reminder page for reading, writing, comparing, and checking numbers.',
                'prompt' => 'Name the place before stating the value.|Read numbers in groups of three digits.|Compare from the greatest place first.|Use zero as a placeholder when a place has no amount.',
                'fields' => 'A reminder that helps me|A common mistake to avoid|My own example'
            ],
            [
                'id' => 'place-value-exit-ticket',
                'type' => 'Exit Ticket',
                'title' => 'Place Value Check-In',
                'description' => 'Show what you know and choose the next step that would help.',
                'prompt' => 'In 684,231, what is the value of the digit 8?|Write 900,000 + 20,000 + 7 in standard form.|Which is greater: 507,120 or 507,102? Explain.',
                'fields' => 'My answers|The part I understand best|My next step'
            ]
        ];
    }

    public function render_lesson_notebook_tools($post_id, $lesson_title, $section_title = '') {
        $context = strtolower(wp_strip_all_tags($lesson_title . ' ' . $section_title));
        $activities = [];

        if (strpos($context, 'place value') !== false) {
            $activities = $this->place_value_notebook_activities();
        }

        /**
         * Content Packs can supply lesson-specific interactive notebook pages.
         * Each activity uses id, type, title, description, prompt, and fields.
         */
        $activities = apply_filters(
            'mathbinder_lesson_notebook_activities',
            $activities,
            intval($post_id),
            $lesson_title,
            $section_title
        );

        if (!$activities || !is_array($activities)) return '';

        ob_start(); ?>
        <section id="interactive-notebook-pages" class="mb-binder-subsection mb-lesson-notebook-tools" data-mb-notebook
            data-mb-notebook-lesson="<?php echo esc_attr(intval($post_id)); ?>"
            data-mb-lesson-title="<?php echo esc_attr($lesson_title); ?>"
            data-mb-section-title="<?php echo esc_attr($section_title); ?>"
            data-mb-lesson-url="<?php echo esc_url(get_permalink($post_id)); ?>"
            data-mb-binder-url="<?php echo esc_url(home_url('/your-binder/')); ?>">
            <div class="mb-binder-subheading mb-lesson-notebook-heading">
                <div>
                    <span>Interactive Notebook</span>
                    <h3>Choose what to add to your binder</h3>
                    <p>Add a page to My MathBinder to type, draw, insert math symbols or emojis, and print your work.</p>
                </div>
                <strong><span data-mb-binder-count>0</span> saved</strong>
            </div>

            <div class="mb-notebook-grid">
                <?php foreach ($activities as $activity) : ?>
                    <article class="mb-notebook-card" data-notebook-id="<?php echo esc_attr($activity['id']); ?>"
                        data-notebook-title="<?php echo esc_attr($activity['title']); ?>"
                        data-notebook-type="<?php echo esc_attr($activity['type']); ?>">
                        <span class="mb-notebook-type"><?php echo esc_html($activity['type']); ?></span>
                        <h3><?php echo esc_html($activity['title']); ?></h3>
                        <p><?php echo esc_html($activity['description']); ?></p>
                        <div class="mb-notebook-preview">
                            <?php foreach (explode('|', $activity['prompt']) as $line) : ?>
                                <div><?php echo esc_html($line); ?></div>
                            <?php endforeach; ?>
                        </div>
                        <script type="application/json" class="mb-notebook-data"><?php echo wp_json_encode($activity); ?></script>
                        <div class="mb-notebook-actions">
                            <button type="button" class="mb-button mb-add-notebook">Add to My Binder</button>
                            <button type="button" class="mb-button-secondary mb-print-notebook">Print Blank Copy</button>
                        </div>
                    </article>
                <?php endforeach; ?>
            </div>

            <div class="mb-lesson-notebook-saved">
                <div class="mb-my-binder-heading">
                    <div><p class="mb-public-kicker">Saved on this device</p><h3>My pages from this lesson</h3></div>
                </div>
                <p class="mb-notebook-device-note">Your typed entries save in this browser. They do not yet follow you to another device.</p>
                <div data-my-binder-list></div>
                <div class="mb-notebook-empty" data-my-binder-empty>
                    <strong>No interactive pages added yet.</strong>
                    <p>Choose <em>Add to My Binder</em> on any page above.</p>
                </div>
            </div>
            <div class="mb-binder-toast" data-mb-binder-toast hidden aria-live="polite"></div>
        </section>
        <?php
        return ob_get_clean();
    }

    public function interactive_notebook_shortcode() {
        $activities = $this->place_value_notebook_activities();

        ob_start(); ?>
        <main class="mb-public-page mb-notebook-page" data-mb-notebook>
            <?php echo $this->public_page_header('Create • Save • Print', 'Interactive Notebook', 'Build a personal math reference online or print original MathBinder pages for a physical binder.'); ?>
            <section class="mb-notebook-intro">
                <div>
                    <p class="mb-public-kicker">Place Value starter collection</p>
                    <h2>Choose the notes that help you learn</h2>
                    <p>Notebook pages are learning tools, not another assignment. Add any page to <strong>My Binder</strong>, type and save your thinking, or print a blank copy to complete by hand.</p>
                </div>
                <div class="mb-notebook-count" aria-live="polite"><strong data-mb-binder-count>0</strong><span>pages in My Binder</span></div>
            </section>

            <nav class="mb-notebook-tabs" aria-label="Interactive notebook views">
                <button type="button" class="is-active" data-notebook-tab="library">Notebook Library</button>
                <button type="button" data-notebook-tab="binder">My Binder <span data-mb-binder-badge>0</span></button>
            </nav>

            <section class="mb-notebook-library" data-notebook-panel="library">
                <div class="mb-notebook-grid">
                    <?php foreach ($activities as $activity) : ?>
                        <article class="mb-notebook-card" data-notebook-id="<?php echo esc_attr($activity['id']); ?>"
                            data-notebook-title="<?php echo esc_attr($activity['title']); ?>"
                            data-notebook-type="<?php echo esc_attr($activity['type']); ?>">
                            <span class="mb-notebook-type"><?php echo esc_html($activity['type']); ?></span>
                            <h3><?php echo esc_html($activity['title']); ?></h3>
                            <p><?php echo esc_html($activity['description']); ?></p>
                            <div class="mb-notebook-preview">
                                <?php foreach (explode('|', $activity['prompt']) as $line) : ?>
                                    <div><?php echo esc_html($line); ?></div>
                                <?php endforeach; ?>
                            </div>
                            <script type="application/json" class="mb-notebook-data"><?php echo wp_json_encode($activity); ?></script>
                            <div class="mb-notebook-actions">
                                <button type="button" class="mb-button mb-add-notebook">Add to My Binder</button>
                                <button type="button" class="mb-button-secondary mb-print-notebook">Print It</button>
                            </div>
                        </article>
                    <?php endforeach; ?>
                </div>
            </section>

            <section class="mb-my-binder" data-notebook-panel="binder" hidden>
                <div class="mb-my-binder-heading">
                    <div><p class="mb-public-kicker">Saved on this device</p><h2>My Binder</h2></div>
                    <button type="button" class="mb-button-secondary" data-print-all-notebook>Print My Binder</button>
                </div>
                <p class="mb-notebook-device-note">Your entries save automatically in this browser. They do not yet follow you to another device.</p>
                <div data-my-binder-list></div>
                <div class="mb-notebook-empty" data-my-binder-empty>
                    <strong>Your binder is ready for its first page.</strong>
                    <p>Return to the Notebook Library and choose <em>Add to My Binder</em>.</p>
                </div>
            </section>
        </main>
        <?php
        return ob_get_clean();
    }

    public function assignment_helper_shortcode() {
        $configured = defined('MATHBINDER_OPENAI_API_KEY') && trim((string) MATHBINDER_OPENAI_API_KEY) !== '';
        $authorized = is_user_logged_in() && MathBinder_Student_Access::has_full_access();
        ob_start(); ?>
        <main class="mb-public-page mb-assignment-page">
            <?php echo $this->public_page_header('Upload • Reflect • Revise', 'AI Assignment Tutor', 'Get a helpful next step without having the answer simply given to you.'); ?>
            <section class="mb-assignment-safety">
                <strong>Protect your privacy.</strong>
                <span>Remove your name, school, student ID, grade report, email address, and any other personal information before uploading.</span>
            </section>
            <?php if (!$authorized): ?>
                <section class="mb-assignment-unavailable">
                    <div class="mb-assignment-lock" aria-hidden="true">🔒</div>
                    <div><p class="mb-public-eyebrow">Premium student access</p><h2>Join an eligible class or use an active plan.</h2><p>Your free MathBinder access still includes Watch It videos and links to IXL, Khan Academy, and DeltaMath. Full learning tools activate through an eligible classroom, family, school, district, or individual plan.</p></div>
                </section>
            <?php elseif (!$configured): ?>
                <section class="mb-assignment-unavailable">
                    <div class="mb-assignment-lock" aria-hidden="true">🔒</div>
                    <div><p class="mb-public-eyebrow">Teacher setup required</p><h2>The helper is almost ready.</h2><p>The secure AI connection has not been enabled yet. No file can be uploaded or sent while the connection is off.</p></div>
                </section>
            <?php else: ?>
                <form id="mb-assignment-form" class="mb-assignment-form" enctype="multipart/form-data">
                    <section class="mb-assignment-step">
                        <div class="mb-assignment-step-number">1</div>
                        <div><h2>Upload your work</h2><p>Choose one clear screenshot or PDF showing both the problem and your attempt.</p>
                            <label class="mb-assignment-drop" for="mb-assignment-file">
                                <strong>Choose a PDF or image</strong><span>PDF, JPG, PNG, or WEBP • 8 MB maximum</span>
                                <input required id="mb-assignment-file" name="assignment_file" type="file" accept=".pdf,.jpg,.jpeg,.png,.webp,application/pdf,image/jpeg,image/png,image/webp">
                            </label>
                        </div>
                    </section>
                    <section class="mb-assignment-step">
                        <div class="mb-assignment-step-number">2</div>
                        <div><h2>Tell us about your attempt</h2><p>Explaining what you tried helps the tutor give a useful hint.</p>
                            <label for="mb-assignment-attempt">What strategy did you try, and where did you get stuck?</label>
                            <textarea required minlength="12" maxlength="1200" id="mb-assignment-attempt" name="attempt" rows="5"></textarea>
                            <label class="mb-assignment-consent"><input required type="checkbox" name="privacy_confirmed" value="1"> I removed personal information and understand this file will be sent to an AI service for feedback.</label>
                        </div>
                    </section>
                    <button class="mb-public-button mb-assignment-submit" type="submit">Help me find my next step</button>
                    <p class="mb-assignment-status" id="mb-assignment-status" role="status" aria-live="polite"></p>
                </form>
                <section class="mb-assignment-result" id="mb-assignment-result" hidden>
                    <p class="mb-public-eyebrow">Your coaching feedback</p>
                    <div class="mb-assignment-result-grid">
                        <article><span>1</span><h3>What I notice</h3><p data-field="notice"></p></article>
                        <article><span>2</span><h3>First likely mistake</h3><p data-field="mistake"></p></article>
                        <article><span>3</span><h3>Guiding question</h3><p data-field="question"></p></article>
                        <article><span>4</span><h3>Your next step</h3><p data-field="next_step"></p></article>
                    </div>
                    <p class="mb-assignment-encouragement" data-field="encouragement"></p>
                </section>
            <?php endif; ?>
            <section class="mb-assignment-how">
                <h2>How the helper supports learning</h2>
                <div><article><strong>It studies your attempt.</strong><p>Feedback begins with your mathematical thinking—not just the final answer.</p></article><article><strong>It gives one useful hint.</strong><p>The first response focuses on the earliest likely error or missing idea.</p></article><article><strong>You stay in charge.</strong><p>You revise, check your reasoning, and build toward the solution.</p></article></div>
            </section>
        </main>
        <?php return ob_get_clean();
    }

    private function assignment_feedback_schema() {
        return [
            'type' => 'object',
            'additionalProperties' => false,
            'properties' => [
                'notice' => ['type' => 'string'],
                'mistake' => ['type' => 'string'],
                'question' => ['type' => 'string'],
                'next_step' => ['type' => 'string'],
                'encouragement' => ['type' => 'string']
            ],
            'required' => ['notice', 'mistake', 'question', 'next_step', 'encouragement']
        ];
    }

    public function ajax_assignment_feedback() {
        check_ajax_referer('mb_assignment_feedback_nonce', 'nonce');
        if (!is_user_logged_in() || !MathBinder_Student_Access::has_full_access()) {
            wp_send_json_error(['message' => 'Full student access is required for the AI Assignment Tutor.'], 403);
        }
        if (!defined('MATHBINDER_OPENAI_API_KEY') || trim((string) MATHBINDER_OPENAI_API_KEY) === '') {
            wp_send_json_error(['message' => 'The secure AI connection is not configured.'], 503);
        }

        $attempt = isset($_POST['attempt']) ? sanitize_textarea_field(wp_unslash($_POST['attempt'])) : '';
        if (strlen($attempt) < 12 || empty($_POST['privacy_confirmed'])) {
            wp_send_json_error(['message' => 'Describe your attempt and confirm the privacy notice.'], 400);
        }
        if (!isset($_FILES['assignment_file']) || !is_array($_FILES['assignment_file'])) {
            wp_send_json_error(['message' => 'Choose one PDF or image.'], 400);
        }

        $file = $_FILES['assignment_file'];
        if (!empty($file['error']) || intval($file['size']) < 1 || intval($file['size']) > 8 * MB_IN_BYTES) {
            wp_send_json_error(['message' => 'The file could not be read or is larger than 8 MB.'], 400);
        }
        $allowed = ['image/jpeg', 'image/png', 'image/webp', 'application/pdf'];
        $checked = wp_check_filetype_and_ext($file['tmp_name'], $file['name']);
        $mime = isset($checked['type']) ? $checked['type'] : '';
        if (!in_array($mime, $allowed, true)) {
            wp_send_json_error(['message' => 'Use a PDF, JPG, PNG, or WEBP file.'], 400);
        }

        $rate_key = 'mb_ai_' . hash('sha256', (string) ($_SERVER['REMOTE_ADDR'] ?? 'unknown'));
        $uses = intval(get_transient($rate_key));
        if ($uses >= 6) wp_send_json_error(['message' => 'Please wait before requesting more feedback.'], 429);
        set_transient($rate_key, $uses + 1, HOUR_IN_SECONDS);

        $bytes = file_get_contents($file['tmp_name']);
        if ($bytes === false) wp_send_json_error(['message' => 'The uploaded file could not be read.'], 400);
        $data_url = 'data:' . $mime . ';base64,' . base64_encode($bytes);
        $content_type = $mime === 'application/pdf' ? 'input_file' : 'input_image';
        $file_input = $mime === 'application/pdf'
            ? ['type' => $content_type, 'filename' => sanitize_file_name($file['name']), 'file_data' => $data_url, 'detail' => 'high']
            : ['type' => $content_type, 'image_url' => $data_url, 'detail' => 'high'];

        $instructions = 'You are the MathBinder AI Assignment Tutor, a patient K-12 math coach. Analyze the problem and the student work. Focus on the earliest likely mathematical mistake or missing idea. Do not reveal the final answer, complete the assignment, or provide a full solution. Give one concise guiding question and one small next step. If the work is unreadable or not mathematics, say so honestly. Use clear, student-friendly language. When you use a formal mathematical term that may be unfamiliar to a K-12 student, immediately follow it with a short, commonly used explanation in parentheses, such as "addend (a number being added)" or "ones column (the place for ones digits)." Keep the formal term so the student learns correct vocabulary. Define a term only on its first use in the response, do not define ordinary words, and do not overload sentences with parentheses. Never infer identity, ability, diagnosis, or grade from the upload.';
        $payload = [
            'model' => 'gpt-5.6-luna',
            'store' => false,
            'safety_identifier' => hash('sha256', wp_salt('auth') . (string) ($_SERVER['REMOTE_ADDR'] ?? 'unknown')),
            'instructions' => $instructions,
            'input' => [[
                'role' => 'user',
                'content' => [
                    $file_input,
                    ['type' => 'input_text', 'text' => "The student says they tried:\n" . $attempt]
                ]
            ]],
            'text' => ['format' => [
                'type' => 'json_schema',
                'name' => 'mathbinder_assignment_feedback',
                'strict' => true,
                'schema' => $this->assignment_feedback_schema()
            ]]
        ];

        $response = wp_remote_post('https://api.openai.com/v1/responses', [
            'timeout' => 75,
            'headers' => [
                'Authorization' => 'Bearer ' . trim((string) MATHBINDER_OPENAI_API_KEY),
                'Content-Type' => 'application/json'
            ],
            'body' => wp_json_encode($payload)
        ]);
        if (is_wp_error($response)) wp_send_json_error(['message' => 'The helper could not connect. Please try again.'], 502);
        $body = json_decode(wp_remote_retrieve_body($response), true);
        if (wp_remote_retrieve_response_code($response) >= 300) {
            wp_send_json_error(['message' => 'The AI service could not complete this request.'], 502);
        }
        $text = '';
        foreach (($body['output'] ?? []) as $output) {
            foreach (($output['content'] ?? []) as $item) {
                if (($item['type'] ?? '') === 'output_text') $text .= (string) ($item['text'] ?? '');
            }
        }
        $feedback = json_decode($text, true);
        if (!is_array($feedback)) wp_send_json_error(['message' => 'The feedback could not be displayed. Please try again.'], 502);
        wp_send_json_success(['feedback' => $feedback]);
    }

    public function handle_contact_submit() {
        $return_url = $this->public_page_url('contact');
        if (!isset($_POST['mb_contact_nonce']) || !wp_verify_nonce(sanitize_text_field(wp_unslash($_POST['mb_contact_nonce'])), 'mb_contact_submit')) {
            wp_safe_redirect(add_query_arg('mb_contact', 'error', $return_url)); exit;
        }
        if (!empty($_POST['website'])) {
            wp_safe_redirect(add_query_arg('mb_contact', 'success', $return_url)); exit;
        }

        $name = isset($_POST['mb_name']) ? sanitize_text_field(wp_unslash($_POST['mb_name'])) : '';
        $email = isset($_POST['mb_email']) ? sanitize_email(wp_unslash($_POST['mb_email'])) : '';
        $role = isset($_POST['mb_role']) ? sanitize_text_field(wp_unslash($_POST['mb_role'])) : '';
        $subject = isset($_POST['mb_subject']) ? sanitize_text_field(wp_unslash($_POST['mb_subject'])) : '';
        $message = isset($_POST['mb_message']) ? sanitize_textarea_field(wp_unslash($_POST['mb_message'])) : '';

        if ($name === '' || !is_email($email) || $subject === '' || $message === '') {
            wp_safe_redirect(add_query_arg('mb_contact', 'error', $return_url)); exit;
        }

        $recipient = sanitize_email(get_option('admin_email'));
        $mail_subject = '[MathBinder] ' . $subject;
        $mail_body = "Name: {$name}\nEmail: {$email}\nRole: {$role}\n\nMessage:\n{$message}";
        $headers = ['Reply-To: ' . $name . ' <' . $email . '>'];
        $sent = wp_mail($recipient, $mail_subject, $mail_body, $headers);
        wp_safe_redirect(add_query_arg('mb_contact', $sent ? 'success' : 'error', $return_url));
        exit;
    }



    public function body_classes($classes) {
        if (is_page('binder-topics')) {
            $classes[] = 'mb-binder-topics-page';
        }
        if (is_page() && get_post_meta(get_queried_object_id(), '_mb_managed_section_page', true) === '1') {
            $classes[] = 'mb-binder-section-page';
        }
        if (is_page('my-mathbinder')) {
            $classes[] = 'mb-progress-dashboard-page';
        }
        if (is_page('your-binder')) {
            $classes[] = 'mb-binder-collection-page';
        }
        if (is_page('evidence-folder')) {
            $classes[] = 'mb-binder-collection-page';
            $classes[] = 'mb-evidence-folder-page';
        }
        if (is_page() && get_post_meta(get_queried_object_id(), '_mb_managed_public_page', true) === '1') {
            $classes[] = 'mb-public-content-page';
        }
        if (is_page(['assignment-helper', 'ai-assignment-helper'])) {
            $classes[] = 'mb-assignment-helper-page';
        }
        if (is_page(['interactive-notebook', 'my-interactive-notebook'])) {
            $classes[] = 'mb-interactive-notebook-page';
        }
        return $classes;
    }

    public function maybe_upgrade() {
        $installed = get_option('mathbinder_core_version', '0');
        if (version_compare($installed, self::VERSION, '>=')) return;

        $this->register_content_types();
        $this->ensure_sections();

        $index = get_page_by_path('binder-topics', OBJECT, 'page');
        if ($index) {
            wp_update_post([
                'ID' => $index->ID,
                'post_title' => 'Binder Topics',
                'post_name' => 'binder-topics',
                'post_content' => '[mathbinder_topics]',
                'post_status' => 'publish'
            ]);
        } else {
            wp_insert_post([
                'post_type' => 'page',
                'post_status' => 'publish',
                'post_title' => 'Binder Topics',
                'post_name' => 'binder-topics',
                'post_content' => '[mathbinder_topics]'
            ]);
        }

        $this->ensure_section_pages();
        $this->ensure_public_pages();

        $progress_page = get_page_by_path('my-mathbinder', OBJECT, 'page');
        if ($progress_page) {
            wp_update_post([
                'ID' => $progress_page->ID,
                'post_title' => 'My MathBinder',
                'post_name' => 'my-mathbinder',
                'post_content' => '[mathbinder_progress]',
                'post_status' => 'publish'
            ]);
        } else {
            wp_insert_post([
                'post_type' => 'page',
                'post_status' => 'publish',
                'post_title' => 'My MathBinder',
                'post_name' => 'my-mathbinder',
                'post_content' => '[mathbinder_progress]'
            ]);
        }


        $collection_page = get_page_by_path('your-binder', OBJECT, 'page');
        if ($collection_page) {
            wp_update_post([
                'ID' => $collection_page->ID,
                'post_title' => 'My MathBinder',
                'post_name' => 'your-binder',
                'post_content' => '[mathbinder_collection]',
                'post_status' => 'publish'
            ]);
        } else {
            wp_insert_post([
                'post_type' => 'page',
                'post_status' => 'publish',
                'post_title' => 'My MathBinder',
                'post_name' => 'your-binder',
                'post_content' => '[mathbinder_collection]'
            ]);
        }

        $evidence_page = get_page_by_path('evidence-folder', OBJECT, 'page');
        if ($evidence_page) {
            wp_update_post([
                'ID' => $evidence_page->ID,
                'post_title' => 'Evidence Folder',
                'post_name' => 'evidence-folder',
                'post_content' => '[mathbinder_evidence_folder]',
                'post_status' => 'publish'
            ]);
        } else {
            wp_insert_post([
                'post_type' => 'page',
                'post_status' => 'publish',
                'post_title' => 'Evidence Folder',
                'post_name' => 'evidence-folder',
                'post_content' => '[mathbinder_evidence_folder]'
            ]);
        }

        flush_rewrite_rules(false);

        $number_system_topics = [
            'Number Operations',
            'Fractions & Decimals',
            'Order of Operations (PEMDAS)',
            'Real & Complex Number Systems'
        ];

        $number_system_term = get_term_by('slug', 'the-number-system', self::TAX);

        foreach ($number_system_topics as $offset => $topic_title) {
            $slug = sanitize_title($topic_title);
            $topic = get_page_by_path($slug, OBJECT, self::CPT);

            if (!$topic) {
                $topic_id = wp_insert_post([
                    'post_type' => self::CPT,
                    'post_status' => 'draft',
                    'post_title' => $topic_title,
                    'post_name' => $slug,
                    'menu_order' => $offset + 2
                ]);

                if ($topic_id && !is_wp_error($topic_id)) {
                    foreach ($this->topic_preset($topic_title) as $meta_key => $meta_value) {
                        update_post_meta($topic_id, '_mb_' . $meta_key, $meta_value);
                    }

                    if ($number_system_term) {
                        wp_set_object_terms($topic_id, [$number_system_term->term_id], self::TAX);
                    }
                }
            }
        }


        $lesson_support = [
            'place-value' => [
                'questions' => "In 42.68, what is the value of the 6? | 0.6, or six tenths.\nWhich is greater: 0.7 or 0.65? | 0.7 is greater because 0.70 is greater than 0.65.\nWrite 5,204 in expanded form. | 5,000 + 200 + 4.\nWhat happens to a digit’s value when it moves one place left? | Its value becomes 10 times greater.\nWhat place is the 3 in 0.034? | The hundredths place.",
                'teacher' => "Begin with a place-value chart and connect each move to a power of ten.\nUse money and metric measurement to connect decimals to familiar contexts.\nAsk students to state both the place and the value of a digit.\nFor small groups, use digit cards and physically shift them across a place-value mat.\nExtension: Connect scientific notation to repeated shifts in place value.",
                'standards' => "CCSS 5.NBT.A.1 — Recognize place-value relationships.\nCCSS 5.NBT.A.3 — Read, write, and compare decimals.\nSupports middle-school prerequisite review and intervention."
            ],
            'number-operations' => [
                'questions' => "Estimate 398 × 21. | About 8,000.\nWhat inverse operation checks division? | Multiplication.\nEvaluate 17 × 24 using the distributive property. | 17 × 20 + 17 × 4 = 340 + 68 = 408.\nWhat does a remainder mean? | It is the amount left after equal groups are formed; its meaning depends on the context.\nWhy estimate before calculating? | To predict the size of the answer and catch unreasonable results.",
                'teacher' => "Require an estimate before exact computation.\nAsk students to explain why an operation matches the situation instead of relying only on keywords.\nUse error analysis to compare efficient and inefficient methods.\nFor intervention, use arrays, area models, and number lines.\nExtension: Compare multiple algorithms and defend the most efficient choice.",
                'standards' => "CCSS 6.NS.B.2–3 — Fluently divide multi-digit numbers and compute with decimals.\nCCSS MP1 — Make sense of problems and persevere.\nCCSS MP6 — Attend to precision."
            ],
            'fractions-decimals' => [
                'questions' => "Convert 3/4 to a decimal. | 0.75.\nWrite 0.6 as a fraction in simplest form. | 3/5.\nWhich is greater: 2/3 or 0.6? | 2/3, because it is approximately 0.667.\nWhy does division convert a fraction to a decimal? | The fraction bar represents division.\nIs 1/3 a terminating decimal? | No. It is the repeating decimal 0.333…",
                'teacher' => "Use fraction strips, hundred grids, and number lines before relying on procedures.\nConnect benchmark fractions to familiar decimals.\nHave students justify comparisons using a common representation.\nFor small groups, sort fraction-decimal equivalence cards.\nExtension: Investigate which denominators produce terminating decimals.",
                'standards' => "CCSS 6.NS.C.6–8 — Understand rational numbers and represent them on number lines.\nCCSS 7.NS.A.2d — Convert rational numbers to decimals.\nCCSS MP4 — Model with mathematics."
            ],
            'order-of-operations-pemdas' => [
                'questions' => "Evaluate 18 ÷ 3 × 2. | 12, because division and multiplication are completed from left to right.\nEvaluate 4 + 3² × 2. | 22.\nWhat is completed first in 5(8 − 3)? | The expression inside the parentheses.\nDoes multiplication always come before division? | No. They have equal priority and are completed from left to right.\nEvaluate 24 − 6 ÷ 2. | 21.",
                'teacher' => "Teach operation pairs rather than treating PEMDAS as six separate levels.\nHave students write one transformation per line.\nUse incorrect worked examples for error analysis.\nFor intervention, color-code grouping symbols, exponents, operation pairs, and final addition/subtraction.\nExtension: Write two different expressions with the same value.",
                'standards' => "CCSS 6.EE.A.1–2 — Write and evaluate numerical expressions involving whole-number exponents.\nCCSS MP7 — Look for and make use of structure."
            ],
            'real-complex-number-systems' => [
                'questions' => "Classify −4 in every applicable set. | Integer, rational number, real number, and complex number.\nIs √2 rational or irrational? | Irrational.\nIs 0 a whole number? | Yes.\nWhat are the real and imaginary parts of 3 + 2i? | Real part 3; imaginary part 2i.\nCan one number belong to several sets? | Yes, because the number sets are nested.",
                'teacher' => "Use a nested-set diagram and require students to classify each number in every applicable set.\nContrast terminating, repeating, and nonrepeating decimals.\nInclude perfect and nonperfect square roots.\nFor small groups, sort number cards into nested hoops.\nExtension: Introduce complex numbers as solutions to equations such as x² = −1.",
                'standards' => "CCSS 8.NS.A.1–2 — Distinguish rational and irrational numbers.\nHigh-school extension: HSN-RN.A and HSN-CN.A.\nCCSS MP3 — Construct viable arguments."
            ]
        ];

        foreach ($lesson_support as $slug => $support) {
            $lesson = get_page_by_path($slug, OBJECT, self::CPT);
            if (!$lesson) continue;
            if (!get_post_meta($lesson->ID, '_mb_mastery_questions', true)) {
                update_post_meta($lesson->ID, '_mb_mastery_questions', $support['questions']);
            }
            if (!get_post_meta($lesson->ID, '_mb_teacher_notes', true)) {
                update_post_meta($lesson->ID, '_mb_teacher_notes', $support['teacher']);
            }
            if (!get_post_meta($lesson->ID, '_mb_standards', true)) {
                update_post_meta($lesson->ID, '_mb_standards', $support['standards']);
            }
        }


        $mcq_sets = [
            'place-value' => "In 42.68, what is the value of the 6? | 6 ones ; 6 tenths ; 6 hundredths ; 60 | B\nWhich is greater? | 0.7 ; 0.65 ; They are equal ; Not enough information | A\nWhich is the expanded form of 5,204? | 5,000 + 200 + 4 ; 500 + 20 + 4 ; 5,000 + 20 + 4 ; 5 + 2 + 4 | A\nWhat happens when a digit moves one place left? | It becomes 10 times smaller ; It becomes 10 times greater ; It stays the same ; It becomes zero | B\nWhat place is the 3 in 0.034? | Tenths ; Hundredths ; Thousandths ; Ones | B",
            'number-operations' => "What is the best estimate for 398 × 21? | 800 ; 8,000 ; 80,000 ; 4,000 | B\nWhich operation checks division? | Addition ; Subtraction ; Multiplication ; Exponents | C\nWhat is 17 × 24? | 348 ; 388 ; 408 ; 428 | C\nWhat is a remainder? | The amount left after equal groups are formed ; The answer to multiplication ; A rounding error ; A decimal point | A\nWhy estimate first? | To avoid solving ; To predict the size of the answer ; To change the operation ; To eliminate units | B",
            'fractions-decimals' => "What decimal is equal to 3/4? | 0.25 ; 0.5 ; 0.75 ; 0.8 | C\nWhat fraction equals 0.6 in simplest form? | 6/100 ; 6/10 ; 3/5 ; 2/3 | C\nWhich is greater? | 2/3 ; 0.6 ; They are equal ; Neither | A\nWhy does division convert a fraction to a decimal? | The fraction bar represents division ; Decimals are always larger ; Fractions have no value ; Division removes the denominator | A\nWhich fraction produces a repeating decimal? | 1/2 ; 1/4 ; 1/5 ; 1/3 | D",
            'order-of-operations-pemdas' => "Evaluate 18 ÷ 3 × 2. | 3 ; 6 ; 12 ; 36 | C\nEvaluate 4 + 3² × 2. | 14 ; 18 ; 22 ; 26 | C\nWhat should be completed first in 5(8 − 3)? | Multiplication ; Subtraction inside parentheses ; Addition ; Exponents | B\nDoes multiplication always come before division? | Yes ; No, they are completed left to right ; Only with decimals ; Only with fractions | B\nEvaluate 24 − 6 ÷ 2. | 9 ; 18 ; 21 ; 27 | C",
            'real-complex-number-systems' => "Which sets contain −4? | Natural only ; Integer, rational, real, and complex ; Irrational only ; Whole only | B\nHow is √2 classified? | Rational ; Irrational ; Integer ; Natural | B\nIs 0 a whole number? | Yes ; No ; Only sometimes ; It is irrational | A\nWhat is the real part of 3 + 2i? | 2 ; i ; 3 ; 5 | C\nCan a number belong to more than one number set? | Yes ; No ; Only positive numbers ; Only zero | A"
        ];

        foreach ($mcq_sets as $slug => $questions) {
            $lesson = get_page_by_path($slug, OBJECT, self::CPT);
            if (!$lesson) continue;

            $current = get_post_meta($lesson->ID, '_mb_mastery_questions', true);
            if (!$current || strpos($current, ';') === false) {
                update_post_meta($lesson->ID, '_mb_mastery_questions', $questions);
            }
        }


        $place_value = get_page_by_path('place-value', OBJECT, self::CPT);
        if ($place_value) {
            update_post_meta($place_value->ID, '_mb_worked_examples',
                "Find the value of 7 in 57,214 | Locate the digit 7. | The 7 is in the thousands place. | Its value is 7,000.\nCompare 0.56 and 0.506 | Line up the decimal points. | Write 0.56 as 0.560. | Compare digit by digit: 0.560 is greater than 0.506.\nWrite 4,302 in expanded form | Identify each digit’s place. | Multiply each digit by its place value. | 4,302 = 4,000 + 300 + 2."
            );
            update_post_meta($place_value->ID, '_mb_common_mistakes',
                "The digit 6 in 4.63 has a value of 6. | The 6 is in the tenths place, so its value is 0.6.\n0.45 is greater than 0.5 because 45 is greater than 5. | Compare decimals by place value: 0.45 is less than 0.50.\nZero never matters in a number. | Zero can be a placeholder that keeps every other digit in the correct place."
            );
            update_post_meta($place_value->ID, '_mb_learn_checks',
                "What is the value of the 8 in 18,452? | 8 ; 80 ; 800 ; 8,000 | D\nWhich number is greater? | 0.609 ; 0.69 ; They are equal ; Not enough information | B\nWhich is the expanded form of 3,407? | 3,000 + 400 + 7 ; 300 + 40 + 7 ; 3,000 + 40 + 7 ; 3 + 4 + 7 | A"
            );
            update_post_meta($place_value->ID, '_mb_did_you_know',
                "The base-ten place-value system developed over many centuries. Its use of zero as both a number and a placeholder made efficient calculation possible."
            );
        }


        $place_value = get_page_by_path('place-value', OBJECT, self::CPT);
        if ($place_value) {
            update_post_meta($place_value->ID, '_mb_video_chapters',
                "0:00 | Lesson Introduction\n0:45 | Place and Value\n2:10 | Standard, Expanded, and Word Form\n4:30 | Comparing Decimals\n6:20 | Try It Yourself"
            );
            update_post_meta($place_value->ID, '_mb_watch_vocabulary',
                "Digit — Any numeral from 0 through 9.\nPlace — The location of a digit in a number.\nValue — How much a digit is worth because of its place.\nExpanded Form — A number written as the sum of each digit’s value."
            );
            update_post_meta($place_value->ID, '_mb_pause_prompts',
                "Pause the video and explain the difference between a digit and its value.\nWrite 6,304 in expanded form before the video reveals the answer.\nCompare 0.7 and 0.65. Explain which place you compare first."
            );
            update_post_meta($place_value->ID, '_mb_video_transcript',
                "Use this space for the full Place Value video transcript or a teacher-written summary. Students can read along, review key ideas, and print the transcript for their binder."
            );
        }


        $place_value = get_page_by_path('place-value', OBJECT, self::CPT);
        if ($place_value) {
            update_post_meta($place_value->ID, '_mb_practice_warmup',
                "In 4,327, which digit is in the hundreds place? | 3 | Look at the third digit from the right. | Use the order ones, tens, hundreds. | The digit 3 is in the hundreds place."
            );
            update_post_meta($place_value->ID, '_mb_guided_practice',
                "What is the value of 6 in 26,415? | 6000 | Identify the place of 6. | The 6 is in the thousands place. | 6 × 1,000 = 6,000.\nWrite 5,204 in expanded form. | 5000 + 200 + 4 | Break the number apart by place. | The zero means there are no tens. | 5,204 = 5,000 + 200 + 4.\nWhich is greater: 0.7 or 0.65? | 0.7 | Write 0.7 as 0.70. | Compare the hundredths after matching decimal places. | 0.70 is greater than 0.65."
            );
            update_post_meta($place_value->ID, '_mb_independent_practice',
                "What is the value of 8 in 18,452? | 8000 | Think about the thousands place. | | 8 × 1,000 = 8,000.\nWrite 7,030 in expanded form. | 7000 + 30 | Include only nonzero place values. | | 7,030 = 7,000 + 30.\nWhich is greater: 0.506 or 0.56? | 0.56 | Add a placeholder zero. | | 0.560 is greater than 0.506."
            );
            update_post_meta($place_value->ID, '_mb_challenge_practice',
                "Create a six-digit number in which the largest digit is in the thousands place. Explain how you know.\nCan two different digits have the same value in one number? Explain your reasoning."
            );
            update_post_meta($place_value->ID, '_mb_real_world_practice',
                "A stadium seats 42,518 people. What value does the digit 2 represent?\nA store sold $5,204 worth of supplies. Write the amount in expanded form."
            );
        }


        $place_value = get_page_by_path('place-value', OBJECT, self::CPT);
        if ($place_value) {
            update_post_meta($place_value->ID, '_mb_parent_summary',
                "Place value tells us how much a digit is worth because of where it appears in a number. In this lesson, students identify places, write numbers in standard and expanded form, and compare whole numbers and decimals."
            );
            update_post_meta($place_value->ID, '_mb_parent_conversation',
                "Ask: What is the difference between a digit and its value?\nAsk your child to explain why the zero matters in 5,204.\nSay a number aloud and ask your child to write it in expanded form."
            );
            update_post_meta($place_value->ID, '_mb_parent_mistakes',
                "Treating the digit as the value | Ask, “What place is the digit in?” before naming its value.\nComparing decimals by the number of digits | Line up decimal points and add placeholder zeros.\nLeaving out zero placeholders | Show how zero keeps every other digit in the correct place."
            );
            update_post_meta($place_value->ID, '_mb_parent_five_minute',
                "Choose any number from a receipt, sports score, house number, or population figure. Ask your child to name each digit’s place and value, then write the number in expanded form. End by having your child explain one step aloud."
            );
            update_post_meta($place_value->ID, '_mb_parent_activity',
                "Use four index cards labeled Thousands, Hundreds, Tens, and Ones. Write one digit on each card. Rearrange the cards to make new numbers, then ask how the value of each digit changes when it moves."
            );
        }


        $place_value = get_page_by_path('place-value', OBJECT, self::CPT);
        if ($place_value) {
            update_post_meta($place_value->ID, '_mb_teacher_objectives',
                "Students will identify the place and value of digits in whole numbers and decimals.\nStudents will write numbers in standard, expanded, and word form.\nStudents will compare whole numbers and decimals using place-value reasoning.\nStudents will explain how moving a digit one place changes its value by a factor of ten."
            );
            update_post_meta($place_value->ID, '_mb_teacher_pacing',
                "Launch | 5 minutes | Use a familiar number such as a school enrollment or sports score.\nLearn It | 12–15 minutes | Model place and value with a chart and think-aloud.\nWatch It | 8–10 minutes | Pause at chapter markers for student explanation.\nPractice It | 20–25 minutes | Move from guided to independent practice.\nJournal and Mastery | 10–15 minutes | Require explanation before the mastery check."
            );
            update_post_meta($place_value->ID, '_mb_teacher_materials',
                "Place-value chart\nDigit cards or index cards\nBase-ten blocks or virtual manipulatives\nDry-erase boards and markers\nReceipts, population data, or real-world number examples"
            );
            update_post_meta($place_value->ID, '_mb_teacher_misconceptions',
                "Students name the digit instead of its value | Ask, “What place is the digit in?” and have students multiply the digit by the place value.\nStudents compare decimals by counting digits | Line up decimal points and add placeholder zeros before comparing.\nStudents omit zero placeholders | Use a place-value chart to show that zero preserves the position of other digits."
            );
            update_post_meta($place_value->ID, '_mb_teacher_differentiation',
                "Below Level | Limit the number of places, use color-coded charts, and provide sentence frames.\nOn Level | Use the full lesson flow with verbal justification and mixed examples.\nAdvanced | Include larger numbers, decimals to thousandths, and error analysis.\nMultilingual Learners | Pair gestures and visuals with key place-value vocabulary."
            );
            update_post_meta($place_value->ID, '_mb_teacher_small_group',
                "Sort digit cards onto a place-value mat and have students read each number aloud.\nUse one number in standard, expanded, word, and visual forms.\nUse error analysis so students identify and correct a fictional student’s mistake."
            );
            update_post_meta($place_value->ID, '_mb_teacher_formative',
                "Listen for correct use of digit, place, and value during partner explanations.\nUse a one-problem exit ticket requiring both an answer and a reason.\nReview Practice It hint use to identify students needing support.\nCompare journal confidence with Mastery Check results."
            );
            update_post_meta($place_value->ID, '_mb_teacher_connections',
                "Science | Read and compare measurements or population data.\nSocial Studies | Analyze city, state, or country population figures.\nFinancial Literacy | Connect decimals to dollars and cents.\nTechnology | Use a spreadsheet to sort numbers from least to greatest."
            );
            update_post_meta($place_value->ID, '_mb_teacher_extensions',
                "Have students design a place-value riddle for a classmate.\nInvestigate how place value works in another number base.\nCreate a real-world infographic using large numbers and decimals."
            );
            update_post_meta($place_value->ID, '_mb_teacher_notes',
                "Emphasize explanation over speed. Require students to name the place before stating the value. Use consistent visual language across Learn It, Practice It, and Mastery Check."
            );
            update_post_meta($place_value->ID, '_mb_standards',
                "CCSS.MATH.CONTENT.5.NBT.A.1 — Recognize that a digit in one place represents 10 times as much as it represents in the place to its right.\nCCSS.MATH.CONTENT.5.NBT.A.3 — Read, write, and compare decimals to thousandths.\nStandards for Mathematical Practice: SMP 2, SMP 3, SMP 6, and SMP 7."
            );
        }

        $this->complete_number_system_lessons();
        $this->complete_ratio_lessons();
        $this->complete_algebraic_expression_lessons();
        $this->complete_solving_graphing_equations_lessons();
        $this->complete_inequalities_triangles_transformations_lessons();
        $this->complete_volume_area_probability_statistics_lessons();
        $this->complete_counting_cardinality_lessons();
        $this->complete_operations_algebraic_thinking_lessons();
        $this->complete_number_base_ten_lessons();
        $this->migrate_pythagorean_practice_30375();

        update_option('mathbinder_core_version', self::VERSION);
        set_transient('mb_activation_notice_v10', 1, 120);
    }

    public function activate() {
        $this->register_content_types();
        $this->ensure_sections();
        flush_rewrite_rules();

        $existing = get_page_by_path('place-value', OBJECT, self::CPT);
        if ($existing) {
            $post_id = $existing->ID;
            $this->migrate_place_value($post_id);
        } else {
            $post_id = wp_insert_post([
                'post_type' => self::CPT,
                'post_status' => 'draft',
                'post_title' => 'Place Value',
                'post_name' => 'place-value',
                'menu_order' => 1
            ]);
            if ($post_id && !is_wp_error($post_id)) {
                foreach ($this->topic_preset('Place Value') as $key => $value) update_post_meta($post_id, '_mb_' . $key, $value);
            }
        }

        $term = get_term_by('slug', 'the-number-system', self::TAX);
        if ($term && $post_id) wp_set_object_terms($post_id, [$term->term_id], self::TAX);

        $index = get_page_by_path('binder-topics');
        if (!$index) {
            wp_insert_post([
                'post_type' => 'page',
                'post_status' => 'draft',
                'post_title' => 'Binder Topics',
                'post_name' => 'binder-topics',
                'post_content' => '[mathbinder_topics]'
            ]);
        }

        $home = get_page_by_path('home');
        if (!$home) {
            $home_id = wp_insert_post([
                'post_type' => 'page',
                'post_status' => 'draft',
                'post_title' => 'Home',
                'post_name' => 'home',
                'post_content' => '[mathbinder_home]'
            ]);
        } else {
            $home_id = $home->ID;
            $content = trim((string) $home->post_content);
            if ($content === '' || strpos($content, '[mathbinder_home]') === false) {
                update_post_meta($home_id, '_mb_homepage_shortcode_ready', '1');
            }
        }


        $index = get_page_by_path('binder-topics');
        if ($index) {
            wp_update_post([
                'ID' => $index->ID,
                'post_content' => '[mathbinder_topics]',
                'post_status' => 'publish'
            ]);
        } else {
            wp_insert_post([
                'post_type' => 'page',
                'post_status' => 'publish',
                'post_title' => 'Binder Topics',
                'post_name' => 'binder-topics',
                'post_content' => '[mathbinder_topics]'
            ]);
        }

        $this->ensure_section_pages();
        $this->ensure_public_pages();
        $this->maybe_repair_operations_lessons();
        $this->maybe_repair_number_base_ten_lessons();
        $this->maybe_repair_fraction_lessons();
        $this->maybe_repair_measurement_data_lessons();
        $this->maybe_repair_geometry_lessons();
        $this->maybe_repair_sections_7_11_lessons();
        $this->maybe_publish_complete_curriculum();

        update_option('mathbinder_core_version', self::VERSION);
        set_transient('mb_activation_notice_v3', 1, 60);
    }

    public function admin_notice() {
        if (get_transient('mb_activation_notice_v10')) {
            delete_transient('mb_activation_notice_v10');
            $page = get_page_by_path('binder-topics', OBJECT, 'page');
            $edit_link = $page ? get_edit_post_link($page->ID) : admin_url('edit.php?post_type=page');
            echo '<div class="notice notice-success is-dismissible"><p><strong>MathBinder Core 25.1 is active.</strong> The Binder Topics page has been created and published. <a href="' . esc_url($edit_link) . '">Edit Binder Topics</a></p></div>';
        }
        if (isset($_GET['mb_created']) && $_GET['mb_created'] === '1') {
            echo '<div class="notice notice-success is-dismissible"><p><strong>Binder Page created.</strong> Review the preset content, add resources, and publish when ready.</p></div>';
        }
        if (get_transient('mb_activation_notice_v3')) {
            delete_transient('mb_activation_notice_v3');
            echo '<div class="notice notice-success is-dismissible"><p><strong>MathBinder Core 25.1 is active.</strong> The final homepage now uses a revised clean crop that preserves the full Number System tab and fills the artwork area without a white edge.</p></div>';
        }
    }
}
new MathBinder_Core();
