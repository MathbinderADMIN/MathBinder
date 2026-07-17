<?php
/**
 * Plugin Name: MathBinder Core
 * Description: Structured Binder Pages with a Quick Add builder, automatic At a Glance details, embedded videos, resource cards, common questions, downloads, and topic navigation.
 * Version: 27.0.0
 * Author: MathBinder
 * Text Domain: mathbinder-core
 */
if (!defined('ABSPATH')) exit;

final class MathBinder_Core {
    const CPT = 'mb_binder_page';
    const TAX = 'mb_binder_section';
    const NONCE = 'mb_binder_page_nonce';
    const QUICK_NONCE = 'mb_quick_add_nonce';
    const VERSION = '27.0.0';

    public function __construct() {
        add_action('init', [$this, 'register_content_types']);
        add_action('add_meta_boxes', [$this, 'add_meta_boxes']);
        add_action('save_post_' . self::CPT, [$this, 'save_meta']);
        add_action('wp_enqueue_scripts', [$this, 'enqueue_frontend_assets']);
        add_action('admin_enqueue_scripts', [$this, 'enqueue_admin_assets']);
        add_filter('template_include', [$this, 'load_single_template']);
        add_shortcode('mathbinder_topics', [$this, 'topics_shortcode']);
        add_shortcode('mathbinder_home', [$this, 'homepage_shortcode']);
        add_shortcode('mathbinder_progress', [$this, 'progress_shortcode']);
        add_shortcode('mathbinder_collection', [$this, 'collection_shortcode']);
        add_filter('manage_' . self::CPT . '_posts_columns', [$this, 'columns']);
        add_action('manage_' . self::CPT . '_posts_custom_column', [$this, 'column_content'], 10, 2);
        add_action('admin_menu', [$this, 'add_quick_add_page']);
        add_action('admin_post_mb_quick_add', [$this, 'handle_quick_add']);
        add_action('admin_post_mb_lesson_builder_create', [$this, 'handle_lesson_builder_create']);
        add_action('admin_post_mb_gold_certify', [$this, 'handle_gold_certify']);
        add_action('admin_post_mb_clone_lesson', [$this, 'handle_clone_lesson']);
        add_action('admin_post_mb_update_lesson_status', [$this, 'handle_update_lesson_status']);
        add_action('admin_notices', [$this, 'admin_notice']);
        add_action('admin_head', [$this, 'hide_lesson_builder_notices']);
        add_action('admin_init', [$this, 'maybe_upgrade']);
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
            'practice_warmup'=>'Question | Answer | Hint 1 | Hint 2 | Worked solution',
            'guided_practice'=>'Question | Answer | Hint 1 | Hint 2 | Worked solution',
            'independent_practice'=>'Question | Answer | Optional hint | Optional second hint | Solution',
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

        $placeholder_starts = [
            'Add a ', 'Explain ', 'What important ', 'Describe a ',
            'Question |', 'Term —', 'Example title |', 'Ask...',
            'Students will...', 'Misconception |', 'Formative checkpoint',
            'Standard code'
        ];

        foreach ($placeholder_starts as $start) {
            if (strpos($value, $start) === 0) return false;
        }
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
        $recent = array_slice($all_lessons, 0, 15);
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
        update_post_meta($id,'_mb_lesson_status',$status);
        if($status==='published')wp_update_post(['ID'=>$id,'post_status'=>'publish']);elseif(get_post_status($id)==='publish')wp_update_post(['ID'=>$id,'post_status'=>'draft']);
        if($status==='gold-certified')update_post_meta($id,'_mb_gold_certification','gold-ready');
        wp_safe_redirect(admin_url('edit.php?post_type='.self::CPT.'&page=mb-lesson-builder&mb_status_updated=1'));exit;
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
            wp_enqueue_style('mathbinder-core', plugin_dir_url(__FILE__) . 'assets/mathbinder.css', [], self::VERSION);
            wp_enqueue_script('mathbinder-front', plugin_dir_url(__FILE__) . 'assets/mathbinder-front.js', [], self::VERSION, true);
            wp_localize_script('mathbinder-front', 'MathBinderSearch', [
                'ajaxUrl' => admin_url('admin-ajax.php'),
                'nonce' => wp_create_nonce('mb_topic_search_nonce')
            ]);
        }
        wp_localize_script('mathbinder-front', 'mathbinderFooterData', [
            'logo' => plugin_dir_url(__FILE__) . 'assets/mathbinder-logo.svg',
            'home' => home_url('/'),
            'binderTopics' => home_url('/binder-topics/'),
            'parents' => home_url('/parents/'),
            'teachers' => home_url('/teachers/'),
            'about' => home_url('/about/'),
            'contact' => home_url('/contact/'),
            'myBinder' => home_url('/my-mathbinder/'),
            'yourBinder' => home_url('/your-binder/'),
            'year' => date('Y')
        ]);

    }

    public function enqueue_admin_assets($hook) {
        $screen = get_current_screen();
        if (!$screen) return;
        if ($screen->post_type === self::CPT || (isset($_GET['page']) && $_GET['page'] === 'mb-quick-add')) {
            wp_enqueue_media();
            wp_enqueue_style('mathbinder-admin', plugin_dir_url(__FILE__) . 'assets/mathbinder-admin.css', [], self::VERSION);
            wp_enqueue_script('mathbinder-admin', plugin_dir_url(__FILE__) . 'assets/mathbinder-admin.js', ['jquery'], self::VERSION, true);
        }
    }

    public function load_single_template($template) {
        if (is_singular(self::CPT)) return plugin_dir_path(__FILE__) . 'single-mb_binder_page.php';
        if (is_tax(self::TAX)) return plugin_dir_path(__FILE__) . 'taxonomy-mb_binder_section.php';
        return $template;
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

        $html = '<div class="mb-practice-items" data-practice-mode="' . esc_attr($mode) . '">';
        foreach ($items as $index => $item) {
            $parts = array_map('trim', explode('|', $item));
            $question = $parts[0] ?? '';
            $answer = $parts[1] ?? '';
            $hint1 = $parts[2] ?? '';
            $hint2 = $parts[3] ?? '';
            $solution = $parts[4] ?? '';
            if (!$question) continue;

            $html .= '<article class="mb-practice-problem" data-answer="' . esc_attr(strtolower($answer)) . '">';
            $html .= '<span class="mb-practice-problem-label">' . esc_html(ucfirst($mode)) . ' ' . esc_html($index + 1) . '</span>';
            $html .= '<h4>' . esc_html($question) . '</h4>';
            $html .= '<input type="text" class="mb-practice-answer" aria-label="Answer for problem ' . esc_attr($index + 1) . '" placeholder="Type your answer">';
            $html .= '<div class="mb-practice-actions">';
            if ($hint1) $html .= '<button type="button" class="mb-practice-hint" data-hint="' . esc_attr($hint1) . '">Hint</button>';
            if ($hint2) $html .= '<button type="button" class="mb-practice-hint" data-hint="' . esc_attr($hint2) . '">Another Hint</button>';
            $html .= '<button type="button" class="mb-practice-submit">Check Answer</button>';
            if ($solution) $html .= '<button type="button" class="mb-practice-solution" data-solution="' . esc_attr($solution) . '">Show Solution</button>';
            $html .= '</div>';
            $html .= '<div class="mb-practice-feedback" aria-live="polite"></div>';
            $html .= '</article>';
        }
        return $html . '</div>';
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
            'orderby' => ['menu_order' => 'ASC', 'title' => 'ASC']
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
        $terms = wp_get_post_terms($post_id, self::TAX, ['fields' => 'ids']);
        if (!$terms) return null;
        $current_order = intval(get_post_field('menu_order', $post_id));
        $args = [
            'post_type' => self::CPT,
            'post_status' => 'publish',
            'posts_per_page' => 1,
            'tax_query' => [[
                'taxonomy' => self::TAX,
                'field' => 'term_id',
                'terms' => $terms[0]
            ]],
            'post__not_in' => [$post_id],
            'orderby' => ['menu_order' => 'ASC', 'title' => 'ASC']
        ];

        if ($direction === 'previous') {
            $args['meta_query'] = [];
            $args['orderby'] = ['menu_order' => 'DESC', 'title' => 'DESC'];
            $args['date_query'] = [];
            $args['posts_per_page'] = -1;
            $posts = get_posts($args);
            foreach ($posts as $candidate) {
                if (intval($candidate->menu_order) < $current_order) return $candidate;
            }
            return null;
        }

        $posts = get_posts($args);
        foreach ($posts as $candidate) {
            if (intval($candidate->menu_order) > $current_order) return $candidate;
        }
        return null;
    }


    public function homepage_shortcode() {
        $sections = get_terms([
            'taxonomy' => self::TAX,
            'hide_empty' => false,
            'orderby' => 'meta_value_num',
            'meta_key' => 'mb_number'
        ]);

        $place_value = get_page_by_path('place-value', OBJECT, self::CPT);
        $binder_topics = get_page_by_path('binder-topics');

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
                        <img src="<?php echo esc_url(plugin_dir_url(__FILE__) . 'assets/mathbinder-logo.svg'); ?>" alt="MathBinder">
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
                            <a class="mb-home-primary" href="<?php echo esc_url($binder_topics ? get_permalink($binder_topics) : home_url('/binder-topics/')); ?>">Browse Binder Topics</a>
                            <?php if ($featured): ?>
                                <a class="mb-home-secondary" href="<?php echo esc_url(get_permalink($featured)); ?>">Open <?php echo esc_html($featured->post_title); ?></a>
                            <?php endif; ?>
                        </div>
                    </div>
                </div>

                <div class="mb-v8-art mb-reveal" aria-label="MathBinder digital binder illustration">
                    <img class="mb-v8-scene" src="<?php echo esc_url(plugin_dir_url(__FILE__) . 'assets/mathbinder-binder-scene-v94.png'); ?>" alt="Teal MathBinder with section tabs and an open Place Value Binder Page">
                </div>
            </section>

            <section class="mb-home-section mb-home-binder-section mb-reveal">
                <div class="mb-home-section-heading">
                    <div>
                        <span class="mb-home-kicker">Explore the Binder</span>
                        <h2>Binder Sections</h2>
                    </div>
                    <p>Choose a section, then open the topic you need. Every Binder Page keeps explanations, videos, practice, downloads, and support together.</p>
                </div>

                <div class="mb-section-card-grid mb-section-card-grid-v8">
                    <?php if (!is_wp_error($sections) && $sections): ?>
                        <?php foreach ($sections as $term):
                            $number = get_term_meta($term->term_id, 'mb_number', true);
                            $count = intval($term->count);
                            ?>
                            <a class="mb-section-card mb-section-card-<?php echo intval($number ?: 0); ?>" href="<?php echo esc_url(get_term_link($term)); ?>">
                                <span class="mb-section-number"><?php echo esc_html(str_pad($number ?: 0, 2, '0', STR_PAD_LEFT)); ?></span>
                                <h3><?php echo esc_html($term->name); ?></h3>
                                <p><?php echo $count ? esc_html($count . ' published Binder Page' . ($count === 1 ? '' : 's')) : 'Binder Pages coming soon'; ?></p>
                                <span class="mb-card-arrow">Open section →</span>
                            </a>
                        <?php endforeach; ?>
                    <?php endif; ?>
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
                            <a href="<?php echo esc_url($binder_topics ? get_permalink($binder_topics) : home_url('/binder-topics/')); ?>">View all pages →</a>
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
                    <a href="<?php echo esc_url(home_url('/parents/')); ?>">Learn more →</a>
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
                    <a href="<?php echo esc_url(home_url('/teachers/')); ?>">Learn more →</a>
                </article>
            </section>

            <footer class="mb-home-footer mb-reveal">
                <div class="mb-home-footer-main">
                    <div class="mb-bottom-brand">
                        <img src="<?php echo esc_url(plugin_dir_url(__FILE__) . 'assets/mathbinder-logo.svg'); ?>" alt="MathBinder">
                        <p>Digital Student Binder</p>
                        <span>Find It. Learn It. Master It.</span>
                    </div>
                    <div class="mb-bottom-links">
                        <h2>Explore</h2>
                        <a href="<?php echo esc_url($binder_topics ? get_permalink($binder_topics) : home_url('/binder-topics/')); ?>">Binder Topics</a>
                        <a href="<?php echo esc_url(home_url('/parents/')); ?>">Parents</a>
                        <a href="<?php echo esc_url(home_url('/teachers/')); ?>">Teachers</a>
                        <a href="<?php echo esc_url(home_url('/contact/')); ?>">Contact</a>
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
                <img src="<?php echo esc_url(plugin_dir_url(__FILE__) . 'assets/mathbinder-logo.svg'); ?>" alt="MathBinder">
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
        <div class="mb-collection-dashboard">
            <header class="mb-collection-hero">
                <div>
                    <span class="mb-collection-kicker">Your Growing MathBinder</span>
                    <h1>Build a binder you can keep</h1>
                    <p>Collect lesson notes, practice pages, challenge activities, and support resources as you learn.</p>
                    <div class="mb-collection-hero-actions">
                        <a href="<?php echo esc_url(home_url('/binder-topics/')); ?>">Browse Binder Topics</a>
                        <a href="<?php echo esc_url(home_url('/my-mathbinder/')); ?>">View My Progress</a>
                    </div>
                </div>
                <div class="mb-collection-visual" aria-hidden="true">
                    <span class="mb-binder-spine"></span>
                    <div class="mb-binder-cover">
                        <strong>MathBinder</strong>
                        <small>Find It. Learn It. Master It.</small>
                    </div>
                    <div class="mb-binder-tabs">
                        <span>Notes</span><span>Practice</span><span>Challenge</span><span>Support</span>
                    </div>
                </div>
            </header>

            <section class="mb-collection-summary">
                <article>
                    <span>Collected Resources</span>
                    <strong data-mb-collected-total>0</strong>
                    <small>Added to your binder</small>
                </article>
                <article>
                    <span>Lessons Represented</span>
                    <strong data-mb-collected-lessons>0</strong>
                    <small>With at least one resource</small>
                </article>
                <article>
                    <span>Completed Lessons</span>
                    <strong data-mb-collection-completed>0</strong>
                    <small>Across all sections</small>
                </article>
                <article>
                    <span>Binder Progress</span>
                    <strong data-mb-collection-percent>0%</strong>
                    <small>Resources collected</small>
                </article>
            </section>

            <section class="mb-collection-progress-card">
                <div>
                    <span class="mb-collection-kicker">Binder Progress</span>
                    <h2>Your collection is growing</h2>
                    <p>Every downloaded or opened resource is recorded on this device.</p>
                </div>
                <div class="mb-collection-progress-track">
                    <span data-mb-collection-fill style="width:0%"></span>
                </div>
            </section>

            <section class="mb-collection-sections">
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
                    <article class="mb-collection-section"
                             data-collection-section="<?php echo esc_attr($term->slug); ?>">
                        <div class="mb-collection-section-heading">
                            <div>
                                <span>Binder Section</span>
                                <h2><?php echo esc_html($term->name); ?></h2>
                            </div>
                            <strong data-section-resource-count>0 resources</strong>
                        </div>

                        <?php if ($pages): ?>
                            <div class="mb-collection-lesson-grid">
                                <?php foreach ($pages as $page): ?>
                                    <article class="mb-collection-lesson"
                                             data-collection-post="<?php echo esc_attr($page->ID); ?>"
                                             data-section-slug="<?php echo esc_attr($term->slug); ?>"
                                             data-title="<?php echo esc_attr($page->post_title); ?>">
                                        <div class="mb-collection-lesson-heading">
                                            <a href="<?php echo esc_url(get_permalink($page)); ?>"><?php echo esc_html($page->post_title); ?></a>
                                            <span data-lesson-resource-count>0 / 4</span>
                                        </div>
                                        <div class="mb-collection-resource-row">
                                            <span data-resource-type="notes">📄 Notes</span>
                                            <span data-resource-type="practice">✏️ Practice</span>
                                            <span data-resource-type="challenge">🧩 Challenge</span>
                                            <span data-resource-type="support">👨‍🏫 Support</span>
                                        </div>
                                    </article>
                                <?php endforeach; ?>
                            </div>
                        <?php else: ?>
                            <p class="mb-collection-empty">Binder Pages are coming soon for this section.</p>
                        <?php endif; ?>
                    </article>
                <?php endforeach; ?>
            </section>
        </div>
        <?php
        return ob_get_clean();
    }

    public function topics_shortcode() {
        $terms = get_terms([
            'taxonomy' => self::TAX,
            'hide_empty' => false,
            'orderby' => 'meta_value_num',
            'meta_key' => 'mb_number'
        ]);

        if (is_wp_error($terms) || !$terms) {
            return '<p>No Binder Sections are available yet.</p>';
        }

        $previews = [
            'the-number-system' => [
                'Place Value',
                'Number Operations',
                'Fractions & Decimals',
                'Order of Operations',
                'Real & Complex Numbers'
            ],
            'ratios-proportional-relationships' => [
                'Ratios',
                'Rates & Unit Rates',
                'Proportions',
                'Percent',
                'Scale Drawings'
            ],
            'algebraic-expressions' => [
                'Variables & Expressions',
                'Combining Like Terms',
                'Distributive Property',
                'Exponents',
                'Evaluating Expressions'
            ],
            'solving-graphing-equations' => [
                'One-Step Equations',
                'Two-Step Equations',
                'Multi-Step Equations',
                'Slope',
                'Linear Equations'
            ],
            'solving-graphing-inequalities' => [
                'One-Step Inequalities',
                'Two-Step Inequalities',
                'Compound Inequalities',
                'Graphing Inequalities',
                'Word Problems'
            ],
            'triangles-transformations' => [
                'Angle Relationships',
                'Triangles',
                'Pythagorean Theorem',
                'Transformations',
                'Similarity & Congruence'
            ],
            'volume-area' => [
                'Area',
                'Circles',
                'Surface Area',
                'Volume',
                'Composite Figures'
            ],
            'probability-statistics' => [
                'Measures of Center',
                'Data Displays',
                'Scatter Plots',
                'Probability',
                'Compound Events'
            ],
        ];

        $icons = ['▦','x²','△','✎','≷','◇','▱','◆'];

        ob_start();
        ?>
        <div class="mb-topics-notebook">
            <header class="mb-topics-notebook-hero">
                <span class="mb-topics-kicker">MathBinder Table of Contents</span>
                <h1>Open a Binder Section</h1>
                <p>Each section is designed like a page in your digital binder. Choose a section to find organized lessons, videos, practice, downloads, and support.</p>

                <form class="mb-topics-notebook-search" role="search" method="get" action="<?php echo esc_url(home_url('/')); ?>">
                    <input type="search" name="s" placeholder="Search for a math topic" required>
                    <input type="hidden" name="post_type" value="<?php echo esc_attr(self::CPT); ?>">
                    <button type="submit">Search MathBinder</button>
                </form>
            </header>

            <main class="mb-notebook-page-grid">
                <?php foreach ($terms as $index => $term):
                    $number = intval(get_term_meta($term->term_id, 'mb_number', true));
                    $topics = isset($previews[$term->slug]) ? $previews[$term->slug] : [];
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
                                <span class="mb-notebook-icon" aria-hidden="true"><?php echo esc_html($icons[$index] ?? '▦'); ?></span>
                                <div>
                                    <span class="mb-notebook-label">Binder Section <?php echo esc_html($number); ?></span>
                                    <h2><?php echo esc_html($term->name); ?></h2>
                                </div>
                            </div>

                            <div class="mb-notebook-rule"></div>

                            <p class="mb-notebook-intro">Open this section to explore the topic sequence and available Binder Pages.</p>

                            <ul class="mb-notebook-topic-list">
                                <?php foreach ($topics as $topic): ?>
                                    <li><?php echo esc_html($topic); ?></li>
                                <?php endforeach; ?>
                            </ul>

                            <div class="mb-notebook-status">
                                <span><?php echo esc_html(count($published)); ?> available now</span>
                                <span><?php echo esc_html(count($topics)); ?> planned topics</span>
                            </div>

                            <a class="mb-notebook-open" href="<?php echo esc_url(get_term_link($term)); ?>">
                                Open Section →
                            </a>
                        </div>
                    </article>
                <?php endforeach; ?>
            </main>

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
</div>
        <?php
        return ob_get_clean();
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
        $keys = ['subtitle', 'essential_question', 'introduction', 'learning_targets', 'vocabulary', 'worked_examples', 'learn_checks', 'did_you_know', 'common_questions', 'videos', 'video_chapters', 'watch_vocabulary', 'pause_prompts', 'video_transcript', 'ixl', 'khan', 'parent_summary', 'parent_conversation', 'parent_mistakes', 'parent_five_minute', 'parent_activity', 'parent_help', 'master_it', 'mastery_questions', 'teacher_notes', 'standards'];
        $complete = 0;
        foreach ($keys as $key) if (get_post_meta($post_id, '_mb_' . $key, true)) $complete++;
        echo esc_html(round(($complete / count($keys)) * 100)) . '%';
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



    public function body_classes($classes) {
        if (is_page('binder-topics')) {
            $classes[] = 'mb-binder-topics-page';
        }
        if (is_page('my-mathbinder')) {
            $classes[] = 'mb-progress-dashboard-page';
        }
        if (is_page('your-binder')) {
            $classes[] = 'mb-binder-collection-page';
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
                'post_title' => 'Your Binder',
                'post_name' => 'your-binder',
                'post_content' => '[mathbinder_collection]',
                'post_status' => 'publish'
            ]);
        } else {
            wp_insert_post([
                'post_type' => 'page',
                'post_status' => 'publish',
                'post_title' => 'Your Binder',
                'post_name' => 'your-binder',
                'post_content' => '[mathbinder_collection]'
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
