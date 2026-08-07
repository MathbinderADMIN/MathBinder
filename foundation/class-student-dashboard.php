<?php
if (!defined('ABSPATH')) exit;

final class MathBinder_Student_Dashboard {
    const SHORTCODE = 'mathbinder_student_dashboard';
    const PAGE_SLUG = 'student-dashboard';

    public static function register() {
        add_shortcode(self::SHORTCODE, [__CLASS__, 'shortcode']);
        add_filter('body_class', [__CLASS__, 'body_class']);
        add_action('wp_enqueue_scripts', [__CLASS__, 'enqueue_assets'], 30);
    }

    public static function enqueue_assets() {
        // This stylesheet is fully scoped to .mb-student-dashboard-page. Load it
        // on the front end so WordPress draft previews cannot skip it before the
        // shortcode is rendered and the preview post is resolved.
        wp_enqueue_style(
            'mathbinder-student-dashboard',
            plugins_url('assets/student-dashboard.css', __FILE__),
            [],
            MathBinder_Core::VERSION
        );
        wp_enqueue_script(
            'mathbinder-student-dashboard',
            plugins_url('assets/student-dashboard.js', __FILE__),
            [],
            MathBinder_Core::VERSION,
            true
        );
    }

    private static function is_dashboard_request() {
        if (is_page(self::PAGE_SLUG)) return true;
        if (!is_singular('page')) return false;

        $post = get_queried_object();
        return $post instanceof WP_Post && has_shortcode($post->post_content, self::SHORTCODE);
    }

    public static function ensure_page() {
        $page = get_page_by_path(self::PAGE_SLUG, OBJECT, 'page');
        $data = [
            'post_type' => 'page',
            'post_status' => 'publish',
            'post_title' => 'Student Dashboard',
            'post_name' => self::PAGE_SLUG,
            'post_content' => '[' . self::SHORTCODE . ']',
        ];
        if ($page) $data['ID'] = $page->ID;
        wp_insert_post($data);
    }

    public static function body_class($classes) {
        if (is_page(self::PAGE_SLUG)) $classes[] = 'mb-student-dashboard-page';
        return $classes;
    }

    public static function data_for_user($user_id) {
        $user = get_user_by('id', absint($user_id));
        $name = $user ? ($user->display_name ?: $user->user_login) : 'MathBinder Student';
        $activity = get_user_meta(absint($user_id), 'mb_student_activity_v1', true);
        if (!is_array($activity)) $activity = ['version'=>1, 'lastLessonId'=>'', 'lessons'=>[]];
        if (!isset($activity['lessons']) || !is_array($activity['lessons'])) $activity['lessons'] = [];
        $assignments = self::assigned_paths($user_id);
        $preferences = get_user_meta(absint($user_id), 'mb_student_binder_preferences_v1', true);
        if (!is_array($preferences)) $preferences = ['title'=>'My MathBinder','theme'=>'teal','stickers'=>[]];

        return [
            'is_fixture' => false,
            'student_name' => $name,
            'greeting' => self::greeting(),
            'notebook_title' => 'My MathBinder',
            'activity' => $activity,
            'assignments' => $assignments,
            'preferences' => wp_parse_args($preferences, ['title'=>'My MathBinder','theme'=>'teal','stickers'=>[]]),
            'mastery_path' => [
                'title' => 'Your Learning',
                'subtitle' => 'Choose a topic and MathBinder will keep your place.',
                'next_step' => 'Choose your first math topic',
                'next_label' => 'Explore Binder Topics',
            ],
        ];
    }

    private static function student_class_ids($user_id) {
        global $wpdb;
        return array_map('absint', $wpdb->get_col($wpdb->prepare(
            "SELECT class_id FROM {$wpdb->prefix}mb_enrollments WHERE user_id=%d AND role_key='student' AND status='active'",
            absint($user_id)
        )) ?: []);
    }

    private static function assigned_paths($user_id) {
        $paths = get_option('mb_teacher_mastery_paths_v1', []);
        if (!is_array($paths)) return [];
        $class_ids = self::student_class_ids($user_id);
        $assigned = [];
        foreach ($paths as $path) {
            if (($path['status'] ?? '') !== 'published') continue;
            $type = (string)($path['target_type'] ?? '');
            $target = absint($path['target_id'] ?? 0);
            if (!(($type === 'student' && $target === absint($user_id)) || ($type === 'class' && in_array($target, $class_ids, true)))) continue;
            $lesson_ids = array_values(array_filter(array_map('absint', (array)($path['lesson_ids'] ?? []))));
            $lessons = [];
            foreach ($lesson_ids as $lesson_id) {
                $lesson = get_post($lesson_id);
                if (!$lesson || $lesson->post_type !== MathBinder_Core::CPT || $lesson->post_status !== 'publish') continue;
                $terms = get_the_terms($lesson_id, MathBinder_Core::TAX);
                $lessons[] = [
                    'id'=>$lesson_id,
                    'title'=>get_the_title($lesson_id),
                    'url'=>get_permalink($lesson_id),
                    'section'=>($terms && !is_wp_error($terms)) ? $terms[0]->name : 'MathBinder',
                ];
            }
            $path['lessons'] = $lessons;
            $assigned[] = $path;
        }
        usort($assigned, function($a, $b) {
            $a_due = (string)($a['due_date'] ?? ''); $b_due = (string)($b['due_date'] ?? '');
            if ($a_due === $b_due) return strcmp((string)($b['updated_at'] ?? ''), (string)($a['updated_at'] ?? ''));
            if ($a_due === '') return 1; if ($b_due === '') return -1; return strcmp($a_due, $b_due);
        });
        return $assigned;
    }

    private static function greeting() {
        $hour = (int) current_time('G');
        if ($hour < 12) return 'Good morning';
        if ($hour < 18) return 'Good afternoon';
        return 'Good evening';
    }

    private static function mastery_lessons() {
        return get_posts([
            'post_type' => MathBinder_Core::CPT,
            'post_status' => 'publish',
            'numberposts' => -1,
            'orderby' => ['menu_order' => 'ASC', 'title' => 'ASC'],
            'order' => 'ASC',
        ]);
    }

    public static function shortcode() {
        if (!is_user_logged_in()) {
            return '<section class="mb-dashboard-gate"><h1>Your MathBinder is waiting</h1><p>Log in to see your learning path, progress, and Evidence Folder.</p><a class="mb-button mb-button-primary" href="' . esc_url(MathBinder_Frontend_Auth::login_url(get_permalink())) . '">Log In</a></section>';
        }
        if (!MathBinder_Capabilities::can_view_student_dashboard()) {
            return '<section class="mb-dashboard-gate"><h1>Dashboard access is not active</h1><p>Please ask your MathBinder administrator for help.</p></section>';
        }

        $data = self::data_for_user(get_current_user_id());
        $assignments = $data['assignments'];
        $class_ids = self::student_class_ids(get_current_user_id());
        $mastery_lessons = $assignments ? $assignments[0]['lessons'] : [];
        $activity_json = wp_json_encode($data['activity']);
        ob_start();
        ?>
        <div class="mb-student-dashboard-page">
        <div class="mb-dashboard" data-mb-dashboard="student" data-mb-total-pages="<?php echo esc_attr(wp_count_posts(MathBinder_Core::CPT)->publish); ?>">
            <script type="application/json" data-mb-server-activity><?php echo $activity_json ?: '{}'; ?></script>
            <a class="mb-skip-link" href="#mb-dashboard-main">Skip to dashboard content</a>
            <aside class="mb-dashboard-sidebar" aria-label="Student dashboard">
                <a class="mb-dashboard-brand" href="<?php echo esc_url(home_url('/')); ?>">
                    <span class="mb-brand-mark" aria-hidden="true">M</span>
                    <span>MathBinder<small>Find it. Learn it. Master it.</small></span>
                </a>
                <nav class="mb-dashboard-nav" aria-label="Student navigation">
                    <a class="is-active" href="<?php echo esc_url(get_permalink()); ?>" aria-current="page"><span aria-hidden="true">⌂</span> Overview</a>
                    <a href="<?php echo esc_url(home_url('/my-mathbinder/')); ?>"><span aria-hidden="true">▤</span> My Learning</a>
                    <a href="<?php echo esc_url(home_url('/evidence-folder/')); ?>"><span aria-hidden="true">✓</span> Evidence Folder</a>
                    <a href="<?php echo esc_url(add_query_arg('mb_filter', 'notes', home_url('/your-binder/')) . '#my-math-notes'); ?>"><span aria-hidden="true">✎</span> My Math Notes</a>
                    <a href="<?php echo esc_url(home_url('/binder-topics/')); ?>"><span aria-hidden="true">⌕</span> Explore the Binder</a>
                </nav>
                <div class="mb-sidebar-help">
                    <strong>Need a little help?</strong>
                    <p>Use a lesson hint or ask your teacher.</p>
                    <a href="<?php echo esc_url(home_url('/contact/')); ?>">Get help</a>
                </div>
            </aside>

            <main id="mb-dashboard-main" class="mb-dashboard-main">
                <header class="mb-dashboard-header">
                    <div>
                        <p><?php echo esc_html($data['greeting']); ?>,</p>
                        <h1><?php echo esc_html($data['student_name']); ?>!</h1>
                    </div>
                    <div class="mb-dashboard-header-actions">
                        <button class="mb-avatar" type="button" aria-label="Open account menu"><?php echo esc_html(strtoupper(substr($data['student_name'], 0, 1))); ?></button>
                    </div>
                </header>

                <section class="mb-dashboard-welcome" aria-labelledby="mb-welcome-heading">
                    <div>
                        <span class="mb-eyebrow">Your next step</span>
                        <h2 id="mb-welcome-heading" data-mb-student-next-title><?php echo esc_html($data['mastery_path']['next_step']); ?></h2>
                        <p><?php echo esc_html($data['mastery_path']['subtitle']); ?></p>
                        <a class="mb-button mb-button-primary" data-mb-student-next-link href="<?php echo esc_url(home_url('/binder-topics/')); ?>"><?php echo esc_html($data['mastery_path']['next_label']); ?> <span aria-hidden="true">→</span></a>
                    </div>
                    <div class="mb-notebook-card" aria-label="<?php echo esc_attr($data['notebook_title']); ?>">
                        <i></i><i></i><i></i>
                        <span><?php echo esc_html($data['notebook_title']); ?></span>
                        <strong data-mb-student-notebook-title><?php echo esc_html($data['mastery_path']['title']); ?></strong>
                    </div>
                </section>

                <section class="mb-dashboard-grid">
                    <article class="mb-panel mb-progress-panel">
                        <div class="mb-panel-heading">
                            <div><span class="mb-eyebrow">Learning progress</span><h2>Completed lessons</h2></div>
                            <strong data-mb-student-percent>0%</strong>
                        </div>
                        <div class="mb-progress-track" role="progressbar" aria-valuemin="0" aria-valuemax="100" aria-valuenow="0" data-mb-student-progress><span style="width:0%"></span></div>
                        <p><strong data-mb-student-completed>0</strong> lessons completed</p>
                    </article>

                    <article class="mb-panel">
                        <div class="mb-panel-heading"><div><span class="mb-eyebrow">My MathBinder</span><h2>Saved learning</h2></div><a href="<?php echo esc_url(home_url('/my-mathbinder/')); ?>">View all</a></div>
                        <div class="mb-stat-grid">
                            <div><strong data-mb-student-notes>0</strong><span>Notes</span></div>
                            <div><strong data-mb-student-saved>0</strong><span>Saved Items</span></div>
                            <div><strong data-mb-student-favorites>0</strong><span>Favorites</span></div>
                        </div>
                    </article>
                </section>

                <section class="mb-panel mb-assigned-panel" aria-labelledby="mb-assigned-heading">
                    <div class="mb-panel-heading"><div><span class="mb-eyebrow">Teacher assignments</span><h2 id="mb-assigned-heading">Assigned Learning</h2></div><strong data-mb-assignment-count><?php echo count($assignments); ?></strong></div>
                    <?php if (!$assignments): ?>
                        <div class="mb-dashboard-empty"><strong>No assigned paths yet.</strong><p>You can explore any Binder Topic while your teacher prepares your next learning path.</p></div>
                    <?php else: ?><div class="mb-assignment-grid">
                        <?php foreach ($assignments as $path): $lesson_ids = array_map('absint', wp_list_pluck($path['lessons'], 'id')); ?>
                            <article class="mb-assignment-card" data-mb-assignment data-lesson-ids="<?php echo esc_attr(implode(',', $lesson_ids)); ?>">
                                <div class="mb-assignment-card-top"><span><?php echo esc_html($path['teacher_name'] ?: 'Your teacher'); ?></span><?php if (!empty($path['due_date'])): ?><time datetime="<?php echo esc_attr($path['due_date']); ?>">Due <?php echo esc_html(wp_date(get_option('date_format'), strtotime($path['due_date']))); ?></time><?php endif; ?></div>
                                <h3><?php echo esc_html($path['title']); ?></h3><p><?php echo esc_html($path['objectives']); ?></p>
                                <div class="mb-assignment-progress"><span><i data-mb-assignment-fill></i></span><strong data-mb-assignment-progress>0 of <?php echo count($lesson_ids); ?> complete</strong></div>
                                <?php if (!empty($path['lessons'])): ?><a class="mb-button mb-button-primary" data-mb-assignment-link href="<?php echo esc_url($path['lessons'][0]['url']); ?>">Start assignment <span aria-hidden="true">→</span></a><?php endif; ?>
                            </article>
                        <?php endforeach; ?>
                    </div><?php endif; ?>
                </section>

                <section class="mb-panel mb-join-class-panel" aria-labelledby="mb-join-class-heading">
                    <div class="mb-panel-heading"><div><span class="mb-eyebrow">School learning</span><h2 id="mb-join-class-heading">Join a Class</h2></div><strong><?php echo count($class_ids); ?> joined</strong></div>
                    <p class="mb-panel-copy">Enter the class code from your teacher. Assigned learning will appear here automatically.</p>
                    <form data-mb-join-class data-join-url="<?php echo esc_url(rest_url('mathbinder/v1/student/join-class')); ?>" data-rest-nonce="<?php echo esc_attr(wp_create_nonce('wp_rest')); ?>">
                        <label for="mb-class-code">Class code</label>
                        <div class="mb-join-class-controls"><input id="mb-class-code" name="class_code" type="text" maxlength="24" autocomplete="off" autocapitalize="characters" value="<?php echo isset($_GET['class_code']) ? esc_attr(sanitize_text_field(wp_unslash($_GET['class_code']))) : ''; ?>" required><button class="mb-button mb-button-primary" type="submit">Join Class</button></div>
                        <p data-mb-join-status aria-live="polite"></p>
                    </form>
                </section>

                <section class="mb-dashboard-grid mb-achievement-grid">
                    <article class="mb-panel"><div class="mb-panel-heading"><div><span class="mb-eyebrow">Mastery progress</span><h2>Skills Mastered</h2></div><strong data-mb-mastered-count>0</strong></div><p class="mb-panel-copy" data-mb-mastery-average>No mastery checks completed yet.</p></article>
                    <article class="mb-panel"><div class="mb-panel-heading"><div><span class="mb-eyebrow">Achievements</span><h2>MathBinder Badges</h2></div><strong data-mb-badge-count>0</strong></div><div class="mb-badge-preview" data-mb-badge-preview><p>Master a lesson to earn your first badge.</p></div></article>
                </section>

                <section class="mb-panel mb-decorate-panel" aria-labelledby="mb-decorate-heading">
                    <div class="mb-panel-heading"><div><span class="mb-eyebrow">Make it yours</span><h2 id="mb-decorate-heading">Decorate My Binder</h2></div><span class="mb-save-status" data-mb-decoration-status aria-live="polite">Saved</span></div>
                    <div class="mb-decorate-layout">
                        <div class="mb-binder-preview-card" data-mb-binder-preview data-theme="<?php echo esc_attr($data['preferences']['theme']); ?>">
                            <i></i><i></i><i></i><span class="mb-preview-stickers" data-mb-preview-stickers aria-hidden="true"></span><small>MathBinder</small><strong data-mb-preview-title><?php echo esc_html($data['preferences']['title']); ?></strong>
                        </div>
                        <form class="mb-decoration-controls" data-mb-decoration-form data-preferences-url="<?php echo esc_url(rest_url('mathbinder/v1/student/preferences')); ?>" data-rest-nonce="<?php echo esc_attr(wp_create_nonce('wp_rest')); ?>">
                            <label>Binder name<input type="text" name="binder_title" maxlength="40" value="<?php echo esc_attr($data['preferences']['title']); ?>"></label>
                            <fieldset><legend>Choose a binder color</legend><div class="mb-theme-options">
                                <?php foreach (['teal'=>'Teal','purple'=>'Purple','blue'=>'Blue','pink'=>'Pink','green'=>'Green','gold'=>'Gold'] as $key=>$label): ?><label><input type="radio" name="binder_theme" value="<?php echo esc_attr($key); ?>" <?php checked($data['preferences']['theme'], $key); ?>><span class="is-<?php echo esc_attr($key); ?>"></span><?php echo esc_html($label); ?></label><?php endforeach; ?>
                            </div></fieldset>
                            <fieldset><legend>Add stickers</legend><div class="mb-sticker-options">
                                <?php foreach (['⭐'=>'Star','➗'=>'Math','🚀'=>'Rocket','🌈'=>'Rainbow','💡'=>'Idea','🎵'=>'Music','🔬'=>'Science','🏆'=>'Trophy'] as $emoji=>$label): ?><label><input type="checkbox" name="binder_stickers[]" value="<?php echo esc_attr($emoji); ?>" <?php checked(in_array($emoji, (array)$data['preferences']['stickers'], true)); ?>><span aria-hidden="true"><?php echo esc_html($emoji); ?></span><small><?php echo esc_html($label); ?></small></label><?php endforeach; ?>
                            </div></fieldset>
                            <button type="submit" class="mb-button mb-button-primary">Save My Binder</button>
                        </form>
                    </div>
                </section>

                <section class="mb-panel mb-today-panel">
                    <div class="mb-panel-heading"><div><span class="mb-eyebrow">Recent activity</span><h2>Continue Learning</h2></div></div>
                    <div class="mb-today-list" data-mb-student-recent>
                        <div class="mb-dashboard-empty"><strong>No lesson activity yet.</strong><p>Open a Binder Topic to begin. Your most recent lesson and notes will appear here.</p></div>
                    </div>
                </section>

                <section class="mb-panel mb-mastery-path-panel" aria-labelledby="mb-mastery-path-heading">
                    <div class="mb-panel-heading">
                        <div><span class="mb-eyebrow">Step-by-step learning</span><h2 id="mb-mastery-path-heading"><?php echo $assignments ? esc_html($assignments[0]['title']) : 'Your Mastery Path'; ?></h2></div>
                        <span class="mb-path-summary" data-mb-path-summary><?php echo $assignments ? '0 of '.count($mastery_lessons).' completed' : 'No path assigned'; ?></span>
                    </div>
                    <p class="mb-path-intro">Complete each lesson to move your path forward. You can still explore any Binder Topic whenever you need it.</p>
                    <div class="mb-mastery-path" data-mb-mastery-path>
                        <?php if (!$mastery_lessons) : ?>
                            <div class="mb-dashboard-empty"><strong>Your path is being prepared.</strong><p>Use Explore the Binder to begin learning.</p></div>
                        <?php else : foreach ($mastery_lessons as $index => $lesson) :
                        ?>
                            <article class="mb-path-step" data-mb-path-step data-lesson-id="<?php echo esc_attr($lesson['id']); ?>" data-path-index="<?php echo esc_attr($index); ?>">
                                <span class="mb-path-number" aria-hidden="true"><?php echo esc_html($index + 1); ?></span>
                                <div><small><?php echo esc_html($lesson['section']); ?></small><h3><?php echo esc_html($lesson['title']); ?></h3></div>
                                <a href="<?php echo esc_url($lesson['url']); ?>">Open lesson</a>
                                <strong data-mb-path-status>Up Next</strong>
                            </article>
                        <?php endforeach; endif; ?>
                    </div>
                </section>
            </main>
        </div>
        </div>
        <?php
        return ob_get_clean();
    }
}
