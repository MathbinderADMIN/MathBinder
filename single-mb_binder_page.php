<?php
if (!defined('ABSPATH')) exit;
get_header();

$plugin = new MathBinder_Core();

while (have_posts()): the_post();
    $id = get_the_ID();
    $meta = function($key) use ($id) { return get_post_meta($id, '_mb_' . $key, true); };
    $terms = get_the_terms($id, MathBinder_Core::TAX);
    $section = ($terms && !is_wp_error($terms)) ? $terms[0] : null;
    $previous = $plugin->get_adjacent_topic($id, 'previous');
    $next = $plugin->get_adjacent_topic($id, 'next');
?>
<main class="mb-page-wrap">
<nav id="lesson-top" class="mb-breadcrumbs" aria-label="Breadcrumb">
        <a href="<?php echo esc_url(home_url('/')); ?>">Home</a><span>›</span>
        <a href="<?php echo esc_url(home_url('/binder-topics/')); ?>">Binder Topics</a><span>›</span>
        <?php if ($section): ?><span><?php echo esc_html($section->name); ?></span><span>›</span><?php endif; ?>
        <span aria-current="page"><?php the_title(); ?></span>
    </nav>

    <header class="mb-hero">
        <span class="mb-badge">Binder Page</span>
        <h1><?php the_title(); ?></h1>
        <?php if ($meta('subtitle')): ?><p class="mb-lead"><?php echo esc_html($meta('subtitle')); ?></p><?php endif; ?>
        <p class="mb-tagline">Find It. Learn It. Master It.</p>

        <div class="mb-lesson-tools">
            <a class="mb-my-progress-link" href="<?php echo esc_url(home_url('/my-mathbinder/')); ?>">My Progress</a>
            <button type="button"
                    class="mb-favorite-page"
                    data-post-id="<?php echo esc_attr($id); ?>"
                    data-title="<?php echo esc_attr(get_the_title()); ?>"
                    data-url="<?php echo esc_url(get_permalink()); ?>"
                    data-section="<?php echo esc_attr($section ? $section->name : 'Binder Page'); ?>"
                    aria-pressed="false">☆ Save Favorite</button>
            <button type="button" class="mb-print-page">Print Lesson</button>
        </div>

        <div class="mb-hero-badges">
            <span><?php echo esc_html(ucfirst($meta('difficulty') ?: 'Beginner')); ?></span>
            <span><?php echo esc_html($meta('estimated_time') ?: '15–20 minutes'); ?></span>
            <span><?php echo esc_html($section ? $section->name : 'Binder Page'); ?></span>
        </div>

    </header>

    <?php
        $section_pages = $plugin->get_section_pages($id, 'publish');
        $section_total = max(count($section_pages), 1);
        $section_index = 1;
        foreach ($section_pages as $page_index => $section_page) {
            if (intval($section_page->ID) === intval($id)) {
                $section_index = $page_index + 1;
                break;
            }
        }
        $section_key = $section ? $section->slug : 'mathbinder';
    ?>
    <section class="mb-progress-panel" data-section="<?php echo esc_attr($section_key); ?>" data-current-post="<?php echo esc_attr($id); ?>"
             data-page-title="<?php echo esc_attr(get_the_title()); ?>"
             data-page-url="<?php echo esc_url(get_permalink()); ?>"
             data-section-title="<?php echo esc_attr($section ? $section->name : 'Binder Page'); ?>"
             data-total="<?php echo esc_attr($section_total); ?>">
        <div class="mb-progress-copy">
            <span><?php echo esc_html($section ? $section->name : 'Binder Section'); ?></span>
            <strong class="mb-progress-status">Page <?php echo esc_html($section_index); ?> of <?php echo esc_html($section_total); ?></strong>
        </div>
        <div class="mb-progress-track" aria-label="Section progress">
            <span class="mb-progress-fill" style="width:<?php echo esc_attr(($section_index / $section_total) * 100); ?>%"></span>
        </div>
        <button type="button" class="mb-mark-complete">Mark this page complete</button>
    </section>

    <section class="mb-glance mb-inside-cover" aria-label="At a Glance">
        <div class="mb-glance-heading">
            <div><span>Inside Cover</span><h2>At a Glance</h2></div>
            <strong><?php echo esc_html(get_the_title()); ?></strong>
        </div>
        <div class="mb-glance-grid">
            <?php if ($section): ?><div><span>Binder Section</span><strong><?php echo esc_html($section->name); ?></strong></div><?php endif; ?>
            <div><span>Difficulty</span><strong><?php echo esc_html(ucfirst($meta('difficulty') ?: 'Beginner')); ?></strong></div>
            <div><span>Estimated Time</span><strong><?php echo esc_html($meta('estimated_time') ?: '15–20 minutes'); ?></strong></div>
            <div><span>Prerequisites</span><strong><?php echo esc_html($meta('prerequisites') ?: 'None'); ?></strong></div>
        </div>
    </section>

    <?php if ($meta('essential_question')): ?>
        <section class="mb-callout">
            <strong>Essential Question</strong>
            <p><?php echo esc_html($meta('essential_question')); ?></p>
        </section>
    <?php endif; ?>

    <nav class="mb-jump-links mb-sticky-section-nav" aria-label="On this page">
        <a href="#teach" data-section-tab="teach">Learn It</a>
        <a href="#watch" data-section-tab="watch">Watch It</a>
        <a href="#practice" data-section-tab="practice">Practice It</a>
        <a href="#binder-pages" data-section-tab="binder-pages">Add to Your Binder</a>
        <a href="#workbook" data-section-tab="workbook">My Math Journal</a>
        <a href="#master" data-section-tab="master">Mastery Check</a>
        <a href="#parent-help" data-section-tab="parent-help">Parent Help</a>
        <a href="#teacher-notes" data-section-tab="teacher-notes">Teacher Notes</a>
    </nav>

    <section id="teach" class="mb-section mb-gold-learn">
        <?php echo $plugin->section_toggle("teach", "Learn It", true); ?>
        <div id="teach-content" class="mb-collapsible-content" data-open="true">
            <div class="mb-learn-intro-grid<?php echo $meta('introduction') ? '' : ' is-model-only'; ?>">
                <?php if ($meta('introduction')): ?>
                    <div class="mb-learn-intro-copy">
                        <span class="mb-learn-kicker">Build Understanding</span>
                        <div class="mb-editor-content mb-learn-opening"><?php echo wp_kses_post(wpautop($meta('introduction'))); ?></div>
                    </div>
                <?php endif; ?>
                <div class="mb-place-value-model" aria-label="Place value model">
                    <span class="mb-model-label">Visual Model</span>
                    <div class="mb-place-value-number">
                        <span data-place="thousands">4<small>Thousands</small></span>
                        <span data-place="hundreds">3<small>Hundreds</small></span>
                        <span data-place="tens">2<small>Tens</small></span>
                        <span data-place="ones">7<small>Ones</small></span>
                    </div>
                    <p>4,327 = 4,000 + 300 + 20 + 7</p>
                </div>
            </div>

            <?php if ($meta('learning_targets')): ?>
                <section class="mb-learn-block">
                    <div class="mb-learn-block-heading">
                        <span aria-hidden="true">🎯</span>
                        <div><small>Goals</small><h3>Learning Targets</h3></div>
                    </div>
                    <?php echo $plugin->render_list($meta('learning_targets'), 'mb-targets'); ?>
                </section>
            <?php endif; ?>

            <?php if ($meta('vocabulary')): ?>
                <section class="mb-learn-block">
                    <div class="mb-learn-block-heading">
                        <span aria-hidden="true">📚</span>
                        <div><small>Click each word</small><h3>Interactive Vocabulary</h3></div>
                    </div>
                    <?php echo $plugin->render_interactive_vocabulary($meta('vocabulary')); ?>
                </section>
            <?php endif; ?>

            <?php if ($meta('worked_examples')): ?>
                <section class="mb-learn-block">
                    <div class="mb-learn-block-heading">
                        <span aria-hidden="true">✏️</span>
                        <div><small>Reveal one step at a time</small><h3>Worked Examples</h3></div>
                    </div>
                    <?php echo $plugin->render_step_examples($meta('worked_examples')); ?>
                </section>
            <?php endif; ?>

            <?php if ($meta('learn_checks')): ?>
                <section class="mb-learn-block mb-learn-check-block">
                    <div class="mb-learn-block-heading">
                        <span aria-hidden="true">🧠</span>
                        <div><small>Low-stakes practice</small><h3>Check Your Understanding</h3></div>
                    </div>
                    <?php echo $plugin->render_learn_checks($meta('learn_checks')); ?>
                </section>
            <?php endif; ?>

            <?php if ($meta('common_questions')): ?>
                <section class="mb-learn-block">
                    <div class="mb-learn-block-heading">
                        <span aria-hidden="true">💬</span>
                        <div><small>Ask and explain</small><h3>Common Questions</h3></div>
                    </div>
                    <?php echo $plugin->render_common_questions($meta('common_questions')); ?>
                </section>
            <?php endif; ?>

            <?php if ($meta('common_mistakes')): ?>
                <section class="mb-learn-block">
                    <div class="mb-learn-block-heading">
                        <span aria-hidden="true">⚠️</span>
                        <div><small>Notice and correct</small><h3>Common Misconceptions</h3></div>
                    </div>
                    <?php echo $plugin->render_misconception_cards($meta('common_mistakes')); ?>
                </section>
            <?php endif; ?>

            <?php if ($meta('real_life') || $meta('did_you_know')): ?>
                <div class="mb-learn-ending">
                    <?php if ($meta('real_life')): ?>
                        <aside class="mb-learn-callout mb-learn-callout-real">
                            <span class="mb-learn-callout-icon" aria-hidden="true">🌎</span>
                            <div>
                                <span class="mb-learn-callout-kicker">Connect It</span>
                                <h3>Real-Life Math</h3>
                                <p><?php echo wp_kses_post(nl2br($meta('real_life'))); ?></p>
                            </div>
                        </aside>
                    <?php endif; ?>

                    <?php if ($meta('did_you_know')): ?>
                        <aside class="mb-learn-callout mb-learn-callout-fact">
                            <span class="mb-learn-callout-icon" aria-hidden="true">🌟</span>
                            <div>
                                <span class="mb-learn-callout-kicker">Explore More</span>
                                <h3>Did You Know?</h3>
                                <p><?php echo wp_kses_post(nl2br($meta('did_you_know'))); ?></p>
                            </div>
                        </aside>
                    <?php endif; ?>
                </div>
            <?php endif; ?>
        </div>
    </section>

    <section id="watch" class="mb-section mb-gold-watch"
             data-watch-post="<?php echo esc_attr($id); ?>"
             data-watch-title="<?php echo esc_attr(get_the_title()); ?>">
        <?php echo $plugin->section_toggle("watch", "Watch It", false); ?>
        <div id="watch-content" class="mb-collapsible-content">
            <div class="mb-watch-intro">
                <div>
                    <span class="mb-watch-kicker">Learn Through Video</span>
                    <h3>Watch, pause, and explain</h3>
                    <p>Use the chapter markers, key vocabulary, and reflection prompts to stay actively engaged while you watch.</p>
                </div>
                <div class="mb-watch-status">
                    <span data-watch-status>Not Started</span>
                    <button type="button" class="mb-mark-video-complete">Mark Video Complete</button>
                </div>
            </div>

            <?php $videos = $plugin->parse_resources($meta('videos')); ?>
            <?php if ($videos): $featured = $videos[0]; ?>
                <div class="mb-watch-studio">
                    <div class="mb-watch-main">
                        <div class="mb-featured-video" data-featured-video>
                            <?php
                            $url = $featured['url'];
                            $embed = wp_oembed_get($url);
                            echo $embed ? $embed : '<a href="' . esc_url($url) . '" target="_blank" rel="noopener">Open video</a>';
                            ?>
                        </div>

                        <div class="mb-watch-now-playing">
                            <span>Now Playing</span>
                            <h3><?php echo esc_html($featured['title']); ?></h3>
                        </div>

                        <?php if (count($videos) > 1): ?>
                            <div class="mb-video-playlist">
                                <?php foreach ($videos as $index => $video): ?>
                                    <a href="<?php echo esc_url($video['url']); ?>" target="_blank" rel="noopener" class="<?php echo $index === 0 ? 'is-current' : ''; ?>">
                                        <span><?php echo esc_html($index + 1); ?></span>
                                        <strong><?php echo esc_html($video['title']); ?></strong>
                                    </a>
                                <?php endforeach; ?>
                            </div>
                        <?php endif; ?>
                    </div>

                    <aside class="mb-watch-sidebar">
                        <?php if ($meta('video_chapters')): ?>
                            <section class="mb-watch-panel">
                                <span class="mb-watch-panel-kicker">Jump to a Moment</span>
                                <h3>Video Chapters</h3>
                                <?php echo $plugin->render_video_chapters($meta('video_chapters')); ?>
                            </section>
                        <?php endif; ?>

                        <?php if ($meta('watch_vocabulary')): ?>
                            <section class="mb-watch-panel">
                                <span class="mb-watch-panel-kicker">Listen For</span>
                                <h3>Key Vocabulary</h3>
                                <?php echo $plugin->render_watch_vocab($meta('watch_vocabulary')); ?>
                            </section>
                        <?php endif; ?>
                    </aside>
                </div>
            <?php else: ?>
                <div class="mb-watch-empty">
                    <span aria-hidden="true">🎥</span>
                    <h3>Video coming soon</h3>
                    <p>Add a featured video in the Binder Page editor to activate the Watch It studio.</p>
                </div>
            <?php endif; ?>

            <?php if ($meta('pause_prompts')): ?>
                <section class="mb-watch-reflection-section">
                    <div class="mb-watch-section-heading">
                        <span aria-hidden="true">⏸️</span>
                        <div>
                            <small>Active Viewing</small>
                            <h3>Pause &amp; Think</h3>
                        </div>
                    </div>
                    <?php echo $plugin->render_pause_prompts($meta('pause_prompts')); ?>
                </section>
            <?php endif; ?>

            <?php if ($meta('video_transcript')): ?>
                <section class="mb-transcript-section">
                    <button type="button" class="mb-transcript-toggle" aria-expanded="false">
                        <span>
                            <small>Read Along</small>
                            <strong>Video Transcript</strong>
                        </span>
                        <span aria-hidden="true">+</span>
                    </button>
                    <div class="mb-transcript-content" hidden>
                        <div class="mb-editor-content"><?php echo wp_kses_post(wpautop($meta('video_transcript'))); ?></div>
                        <button type="button" class="mb-print-transcript">Print Transcript</button>
                    </div>
                </section>
            <?php endif; ?>

            <div class="mb-watch-next-step">
                <div>
                    <span>Next Step</span>
                    <h3>Ready to practice?</h3>
                    <p>Use Practice It to apply what you just watched.</p>
                </div>
                <a href="#practice" data-go-practice>Go to Practice It →</a>
            </div>
        </div>
    </section>

    <section id="practice" class="mb-section mb-gold-practice"
             data-practice-post="<?php echo esc_attr($id); ?>">
        <?php echo $plugin->section_toggle("practice", "Practice It", false); ?>
        <div id="practice-content" class="mb-collapsible-content">
            <div class="mb-practice-intro">
                <div>
                    <span class="mb-practice-kicker">Build Your Skills</span>
                    <h3>Practice until you can explain it</h3>
                    <p>Move from a quick warm-up to guided practice, independent work, and deeper challenges.</p>
                </div>
                <div class="mb-practice-summary">
                    <strong data-practice-percent>0%</strong>
                    <span data-practice-count>0 of 4 stages complete</span>
                </div>
            </div>

            <div class="mb-practice-roadmap">
                <span data-stage="warmup">Warm-Up</span>
                <span data-stage="guided">Guided</span>
                <span data-stage="independent">Independent</span>
                <span data-stage="challenge">Challenge</span>
            </div>

            <?php if ($meta('practice_warmup')): ?>
                <section class="mb-practice-stage" data-stage-panel="warmup">
                    <div class="mb-practice-stage-heading"><span>1</span><div><small>Quick Start</small><h3>Warm-Up</h3></div></div>
                    <?php echo $plugin->render_practice_items($meta('practice_warmup'), 'warm-up'); ?>
                </section>
            <?php endif; ?>

            <?php if ($meta('guided_practice')): ?>
                <section class="mb-practice-stage" data-stage-panel="guided">
                    <div class="mb-practice-stage-heading"><span>2</span><div><small>We Do</small><h3>Guided Practice</h3></div></div>
                    <?php echo $plugin->render_practice_items($meta('guided_practice'), 'guided'); ?>
                </section>
            <?php endif; ?>

            <?php if ($meta('independent_practice')): ?>
                <section class="mb-practice-stage" data-stage-panel="independent">
                    <div class="mb-practice-stage-heading"><span>3</span><div><small>You Do</small><h3>Independent Practice</h3></div></div>
                    <?php echo $plugin->render_practice_items($meta('independent_practice'), 'independent'); ?>
                </section>
            <?php endif; ?>

            <?php if ($meta('challenge_practice') || $meta('real_world_practice')): ?>
                <section class="mb-practice-stage" data-stage-panel="challenge">
                    <div class="mb-practice-stage-heading"><span>4</span><div><small>Stretch Your Thinking</small><h3>Challenge &amp; Real-World Practice</h3></div></div>
                    <div class="mb-practice-open-grid">
                        <?php foreach ($plugin->lines($meta('challenge_practice')) as $challenge): ?>
                            <article><span>🧩 Challenge</span><p><?php echo esc_html($challenge); ?></p><textarea rows="4" placeholder="Explain your reasoning…"></textarea></article>
                        <?php endforeach; ?>
                        <?php foreach ($plugin->lines($meta('real_world_practice')) as $problem): ?>
                            <article><span>🌎 Real-World Math</span><p><?php echo esc_html($problem); ?></p><textarea rows="4" placeholder="Show your thinking…"></textarea></article>
                        <?php endforeach; ?>
                    </div>
                    <button type="button" class="mb-complete-challenge">Mark Challenge Complete</button>
                </section>
            <?php endif; ?>

            <section class="mb-practice-complete" hidden>
                <span aria-hidden="true">⭐</span>
                <div><small>Practice Complete</small><h3>Nice work!</h3><p>You completed the Practice It learning path.</p></div>
                <a href="#workbook" data-go-journal>Continue to My Math Journal →</a>
            </section>

            <div class="mb-external-practice">
                <?php if ($meta('ixl')): ?><h3>IXL</h3><?php echo $plugin->render_resource_cards($meta('ixl'), 'IXL'); ?><?php endif; ?>
                <?php if ($meta('khan')): ?><h3>Khan Academy</h3><?php echo $plugin->render_resource_cards($meta('khan'), 'Khan Academy'); ?><?php endif; ?>
                <?php if ($meta('delta')): ?><h3>DeltaMath</h3><?php echo $plugin->render_resource_cards($meta('delta'), 'DeltaMath'); ?><?php endif; ?>
                <?php if ($meta('desmos')): ?><h3>Desmos</h3><?php echo $plugin->render_resource_cards($meta('desmos'), 'Desmos'); ?><?php endif; ?>
            </div>
        </div>
    </section>

    <section id="binder-pages"
             class="mb-section mb-binder-resources mb-gold-binder"
             data-resource-post="<?php echo esc_attr($id); ?>"
             data-resource-title="<?php echo esc_attr(get_the_title()); ?>"
             data-resource-url="<?php echo esc_url(get_permalink()); ?>"
             data-resource-section="<?php echo esc_attr($section ? $section->slug : ''); ?>"
             data-resource-section-title="<?php echo esc_attr($section ? $section->name : 'Binder Page'); ?>">
        <?php echo $plugin->section_toggle("binder-pages", "Add to Your Binder", false); ?>
        <div id="binder-pages-content" class="mb-collapsible-content">
            <div class="mb-binder-collection-hero">
                <div>
                    <span class="mb-resource-kicker">BUILD YOUR PERSONAL MATH LIBRARY</span>
                    <h3>Save today’s lesson and grow your MathBinder</h3>
                    <p>Collect lesson resources, revisit recent pages, build review packets, and watch your binder grow over time.</p>
                    <div class="mb-binder-hero-actions">
                        <button type="button" class="mb-add-lesson-to-binder">＋ Add <?php echo esc_html(get_the_title()); ?></button>
                        <a href="<?php echo esc_url(home_url('/your-binder/')); ?>">Open My Binder →</a>
                    </div>
                </div>
                <div class="mb-binder-collection-summary">
                    <span>My Binder</span>
                    <strong data-binder-lesson-count>0</strong>
                    <small>Lessons in My Binder</small>
                    <div class="mb-mini-binder-progress"><span data-binder-progress-fill style="width:0%"></span></div>
                    <em data-binder-progress-label>Start your collection</em>
                </div>
            </div>

            <section class="mb-binder-subsection">
                <div class="mb-binder-subheading">
                    <div><span>Today’s Lesson</span><h3>Printable Resources</h3></div>
                    <strong data-resource-count-label>0 resources collected</strong>
                </div>

                <div class="mb-binder-resource-grid">
                    <article class="mb-binder-resource-card mb-resource-notes" data-resource-card="notes">
                        <div class="mb-resource-icon" aria-hidden="true">📄</div>
                        <div class="mb-resource-copy">
                            <span>Lesson Resource</span>
                            <h3>Printable Lesson Notes</h3>
                            <p>Guided notes that follow this lesson and are ready to print and place in your binder.</p>
                            <ul class="mb-resource-preview"><li>Vocabulary</li><li>Worked examples</li><li>Guided notes</li></ul>
                        </div>
                        <?php if ($meta('printable_pdf')): ?>
                            <a href="<?php echo esc_url($meta('printable_pdf')); ?>" target="_blank" rel="noopener" data-resource-action="notes">Download PDF →</a>
                        <?php else: ?>
                            <span class="mb-resource-status">Coming Soon</span>
                        <?php endif; ?>
                    </article>

                    <article class="mb-binder-resource-card mb-resource-practice" data-resource-card="practice">
                        <div class="mb-resource-icon" aria-hidden="true">✏️</div>
                        <div class="mb-resource-copy">
                            <span>Skill Practice</span>
                            <h3>Practice Pages</h3>
                            <p>Guided and independent practice pages for mastering today’s lesson.</p>
                            <ul class="mb-resource-preview"><li>Guided practice</li><li>Independent practice</li><li>Challenge question</li></ul>
                        </div>
                        <?php if ($meta('interactive_version')): ?>
                            <a href="<?php echo esc_url($meta('interactive_version')); ?>" target="_blank" rel="noopener" data-resource-action="practice">Open Practice →</a>
                        <?php else: ?>
                            <span class="mb-resource-status">Coming Soon</span>
                        <?php endif; ?>
                    </article>

                    <article class="mb-binder-resource-card mb-resource-challenge" data-resource-card="challenge">
                        <div class="mb-resource-icon" aria-hidden="true">🧩</div>
                        <div class="mb-resource-copy">
                            <span>Extension</span>
                            <h3>Challenge Problems</h3>
                            <p>Stretch your thinking with higher-level questions, puzzles, and enrichment activities.</p>
                            <ul class="mb-resource-preview"><li>Extension problems</li><li>Math puzzles</li><li>Enrichment</li></ul>
                        </div>
                        <span class="mb-resource-status">Coming Soon</span>
                    </article>

                    <article class="mb-binder-resource-card mb-resource-support" data-resource-card="support">
                        <div class="mb-resource-icon" aria-hidden="true">👨‍🏫</div>
                        <div class="mb-resource-copy">
                            <span>Adult Support</span>
                            <h3>Teacher &amp; Parent Resources</h3>
                            <p>Answer keys, teaching tips, intervention ideas, extensions, and discussion prompts.</p>
                            <ul class="mb-resource-preview"><li>Answer key</li><li>Teaching tips</li><li>Discussion prompts</li></ul>
                        </div>
                        <?php if ($meta('answer_key')): ?>
                            <a href="<?php echo esc_url($meta('answer_key')); ?>" target="_blank" rel="noopener" data-resource-action="support">View Resources →</a>
                        <?php else: ?>
                            <span class="mb-resource-status">Coming Soon</span>
                        <?php endif; ?>
                    </article>
                </div>
            </section>

            <div class="mb-binder-dashboard-grid">
                <section class="mb-binder-subsection mb-recent-binder-items">
                    <div class="mb-binder-subheading">
                        <div><span>Your Collection</span><h3>Recently Added</h3></div>
                    </div>
                    <div class="mb-recent-binder-list" data-recent-binder-list>
                        <div class="mb-binder-empty"><strong>Your saved lessons will appear here.</strong><span>Build a binder you can use all year.</span></div>
                    </div>
                </section>

                <section class="mb-binder-subsection mb-binder-preview-card">
                    <div class="mb-binder-subheading">
                        <div><span>Binder Preview</span><h3>My MathBinder</h3></div>
                    </div>
                    <div class="mb-binder-preview">
                        <div class="mb-preview-cover">
                            <strong>MathBinder</strong>
                            <small>Find It. Learn It. Master It.</small>
                            <span data-preview-count>0 lessons</span>
                        </div>
                        <div class="mb-preview-tabs">
                            <span>Number System</span><span>Ratios</span><span>Algebra</span><span>Geometry</span>
                        </div>
                    </div>
                    <a class="mb-open-full-binder" href="<?php echo esc_url(home_url('/your-binder/')); ?>">Open Full Binder →</a>
                </section>
            </div>

            <section class="mb-binder-subsection">
                <div class="mb-binder-subheading">
                    <div><span>Study Tools</span><h3>Build a Review Packet</h3></div>
                </div>
                <div class="mb-study-pack-grid">
                    <article>
                        <span aria-hidden="true">📚</span>
                        <h4>My Collected Lessons</h4>
                        <p>Create a printable list of the lessons saved in this browser.</p>
                        <button type="button" data-study-pack="collected">Generate Review List</button>
                    </article>
                    <article>
                        <span aria-hidden="true">⭐</span>
                        <h4>Favorite This Lesson</h4>
                        <p>Save this page for quick access from your MathBinder dashboard.</p>
                        <button type="button" class="mb-binder-favorite-button">Add to Favorites</button>
                    </article>
                    <article>
                        <span aria-hidden="true">🧭</span>
                        <h4>Continue Learning</h4>
                        <p>Move to another published lesson in this Binder Section.</p>
                        <?php
                        $next_page = null;
                        foreach ($section_pages as $candidate) {
                            if (intval($candidate->ID) !== intval($id)) { $next_page = $candidate; break; }
                        }
                        ?>
                        <?php if ($next_page): ?>
                            <a href="<?php echo esc_url(get_permalink($next_page)); ?>">Open <?php echo esc_html($next_page->post_title); ?> →</a>
                        <?php else: ?>
                            <span class="mb-resource-status">More lessons coming soon</span>
                        <?php endif; ?>
                    </article>
                </div>
            </section>

            <section class="mb-binder-subsection">
                <div class="mb-binder-subheading">
                    <div><span>Milestones</span><h3>Your Binder Achievements</h3></div>
                </div>
                <div class="mb-binder-milestones">
                    <article data-milestone="1"><strong>1</strong><span>First Lesson</span></article>
                    <article data-milestone="5"><strong>5</strong><span>Five Lessons</span></article>
                    <article data-milestone="10"><strong>10</strong><span>Growing Binder</span></article>
                    <article data-milestone="25"><strong>25</strong><span>Binder Builder</span></article>
                </div>
            </section>

            <section class="mb-binder-finish mb-binder-finish-with-tip">
                <div class="mb-binder-finish-main">
                    <div>
                        <span>Finish Strong</span>
                        <h3>Learned. Watched. Practiced. Saved.</h3>
                        <p>Now capture the most important idea in My Math Journal.</p>
                    </div>
                    <a href="#workbook" data-binder-go-journal>Go to My Math Journal →</a>
                </div>
                <div class="mb-binder-finish-tip">
                    <span aria-hidden="true">👨‍👩‍👧</span>
                    <div>
                        <strong>Parent Tip</strong>
                        <p>Spend five minutes reviewing one saved lesson together each week. Ask your child to explain one example aloud.</p>
                    </div>
                </div>
            </section>
        </div>
    </section>


    <section id="workbook" class="mb-section mb-workbook-section"
             data-workbook-post="<?php echo esc_attr($id); ?>"
             data-workbook-title="<?php echo esc_attr(get_the_title()); ?>"
             data-workbook-section="<?php echo esc_attr($section ? $section->name : 'Binder Page'); ?>">
        <?php echo $plugin->section_toggle("workbook", "My Math Journal", false); ?>
        <div id="workbook-content" class="mb-collapsible-content">
            <div class="mb-workbook-intro">
                <div>
                    <span class="mb-workbook-kicker">MY PRIVATE MATH SPACE</span>
                    <h3>My Math Journal</h3>
                    <p>Capture your thinking, questions, and reflections. Everything is saved automatically on this device.</p>
                </div>
                <div class="mb-journal-status-wrap">
                    <span class="mb-device-save-note">Saved on this device</span>
                    <span class="mb-workbook-save-state" aria-live="polite">Ready</span>
                </div>
            </div>

            <div class="mb-journal-toolbar">
                <button type="button" class="mb-print-journal">Print My Journal</button>
                <button type="button" class="mb-download-notes">Download Journal</button>
                <button type="button" class="mb-clear-journal">Clear Journal</button>
            </div>

            <div class="mb-journal-layout">
                <article class="mb-journal-confidence">
                    <div class="mb-workbook-card-heading">
                        <div>
                            <span>Confidence Check</span>
                            <h3>How confident do you feel?</h3>
                        </div>
                    </div>
                    <div class="mb-confidence-options" role="radiogroup" aria-label="Confidence level">
                        <button type="button" data-confidence="1">
                            <span class="mb-confidence-emoji" aria-hidden="true">🌱</span>
                            <strong>Still Learning</strong>
                            <small>I need more examples.</small>
                        </button>
                        <button type="button" data-confidence="2">
                            <span class="mb-confidence-emoji" aria-hidden="true">🙂</span>
                            <strong>Getting There</strong>
                            <small>I understand some of it.</small>
                        </button>
                        <button type="button" data-confidence="3">
                            <span class="mb-confidence-emoji" aria-hidden="true">😎</span>
                            <strong>Mostly Confident</strong>
                            <small>I can solve most problems.</small>
                        </button>
                        <button type="button" data-confidence="4">
                            <span class="mb-confidence-emoji" aria-hidden="true">⭐</span>
                            <strong>I Could Teach This</strong>
                            <small>I can explain it clearly.</small>
                        </button>
                    </div>
                    <div class="mb-confidence-message" aria-live="polite"></div>
                    <div class="mb-journal-celebration" aria-live="polite" hidden>
                        <span aria-hidden="true">🌟</span>
                        <strong>Awesome! You’re ready to teach someone else!</strong>
                    </div>
                </article>

                <article class="mb-journal-reflection">
                    <div class="mb-workbook-card-heading">
                        <div>
                            <span>Quick Reflection</span>
                            <h3>Complete the thought</h3>
                        </div>
                    </div>
                    <div class="mb-reflection-grid">
                        <label class="mb-sticky-note mb-sticky-yellow">
                            <span>💡 Big Idea</span>
                            <textarea class="mb-reflection-important" rows="4" placeholder="The most important idea is…"></textarea>
                        </label>
                        <label class="mb-sticky-note mb-sticky-blue">
                            <span>❓ Question</span>
                            <textarea class="mb-reflection-question" rows="4" placeholder="One question I still have is…"></textarea>
                        </label>
                        <label class="mb-sticky-note mb-sticky-green">
                            <span>🌎 Real-World Connection</span>
                            <textarea class="mb-reflection-connection" rows="4" placeholder="A real-world connection is…"></textarea>
                        </label>
                    </div>
                </article>

                <article class="mb-journal-notes-card">
                    <div class="mb-workbook-card-heading">
                        <div>
                            <span>My Math Notes</span>
                            <h3>What do you want to remember?</h3>
                        </div>
                        <strong data-note-count>0 words</strong>
                    </div>
                    <div class="mb-notebook-paper-wrap">
                        <textarea class="mb-student-notes"
                                  rows="12"
                                  placeholder="Write definitions, examples, worked problems, questions, or reminders here…"></textarea>
                    </div>
                </article>
            </div>

            <section class="mb-journal-growth">
                <div class="mb-journal-growth-heading">
                    <div>
                        <span>Learning Record</span>
                        <h3>My Journal History</h3>
                        <p>Revisit reflections saved from other MathBinder lessons on this device.</p>
                    </div>
                    <strong data-journal-entry-count>0 entries</strong>
                </div>
                <div class="mb-journal-history-list" data-journal-history>
                    <div class="mb-journal-history-empty">
                        <strong>Your journal history will grow as you reflect.</strong>
                        <span>Complete this lesson’s journal to begin your learning record.</span>
                    </div>
                </div>
            </section>

            <section class="mb-journal-next-step">
                <div>
                    <span>Next Step</span>
                    <h3>Ready to check your understanding?</h3>
                    <p>Move into Mastery Check when your reflection feels complete.</p>
                </div>
                <a href="#master" data-journal-go-mastery>Go to Mastery Check →</a>
            </section>

            <p class="mb-journal-privacy">
                <strong>Your Math Journal is automatically saved on this device.</strong><br>Clearing your browser data will erase your saved journal entries.
            </p>
        </div>
    </section>

    <section id="master" class="mb-section mb-master mb-gold-mastery"
             data-mastery-post="<?php echo esc_attr($id); ?>"
             data-mastery-title="<?php echo esc_attr(get_the_title()); ?>"
             data-mastery-section="<?php echo esc_attr($section ? $section->slug : 'mathbinder'); ?>">
        <?php echo $plugin->section_toggle("master", "Mastery Check", false); ?>
        <div id="master-content" class="mb-collapsible-content">
            <div class="mb-mastery-intro">
                <div>
                    <span class="mb-mastery-kicker">Show What You Know</span>
                    <h3>Can you do this on your own?</h3>
                    <p>Complete each question without hints. Your result is saved privately on this device.</p>
                </div>
                <div class="mb-mastery-time">
                    <strong>5–8</strong>
                    <span>Minutes</span>
                </div>
            </div>

            <div class="mb-mastery-progress-wrap">
                <div class="mb-mastery-progress-copy">
                    <strong data-mastery-progress-label>0 of 5 answered</strong>
                    <span data-mastery-progress-percent>0%</span>
                </div>
                <div class="mb-mastery-progress-track"><span data-mastery-progress-fill style="width:0%"></span></div>
            </div>

            <?php if ($meta('master_it')): ?>
                <section class="mb-mastery-success-criteria">
                    <span>Success Criteria</span>
                    <h3>Before you begin, I can…</h3>
                    <?php echo $plugin->render_list($meta('master_it'), 'mb-check-yourself'); ?>
                </section>
            <?php endif; ?>

            <?php if ($meta('mastery_questions')): ?>
                <?php echo $plugin->render_mastery_questions($meta('mastery_questions')); ?>
            <?php else: ?>
                <div class="mb-mastery-empty">
                    <span aria-hidden="true">✅</span>
                    <h3>Mastery questions coming soon</h3>
                </div>
            <?php endif; ?>

            <section class="mb-mastery-confidence">
                <div>
                    <span>Confidence Check</span>
                    <h3>How confident do you feel?</h3>
                </div>
                <div class="mb-mastery-confidence-options">
                    <button type="button" data-mastery-confidence="1">1<small>I need more practice</small></button>
                    <button type="button" data-mastery-confidence="2">2<small>I’m getting there</small></button>
                    <button type="button" data-mastery-confidence="3">3<small>I feel confident</small></button>
                    <button type="button" data-mastery-confidence="4">4<small>I could teach this</small></button>
                </div>
            </section>

            <button type="button" class="mb-submit-mastery-check">Submit Mastery Check</button>

            <section class="mb-mastery-results" hidden>
                <div class="mb-mastery-results-hero">
                    <span class="mb-mastery-result-icon" aria-hidden="true">⭐</span>
                    <div>
                        <small data-mastery-result-label>Mastery Result</small>
                        <h3 data-mastery-result-title>Your Results</h3>
                        <p data-mastery-result-message></p>
                    </div>
                    <strong data-mastery-score>0%</strong>
                </div>

                <div class="mb-mastery-result-grid">
                    <article><span>Score</span><strong data-mastery-correct-count>0 of 5</strong></article>
                    <article><span>Confidence</span><strong data-mastery-confidence-result>Not selected</strong></article>
                    <article><span>Attempt</span><strong data-mastery-attempt>1</strong></article>
                    <article><span>Status</span><strong data-mastery-status>In Progress</strong></article>
                </div>

                <div class="mb-mastery-guidance">
                    <div>
                        <span>Recommended Next Step</span>
                        <h3 data-mastery-guidance-title>Review and try again</h3>
                        <p data-mastery-guidance-text></p>
                    </div>
                    <div class="mb-mastery-guidance-actions">
                        <a href="#watch" data-review-section="watch">Review Watch It</a>
                        <a href="#practice" data-review-section="practice">Review Practice It</a>
                        <button type="button" class="mb-retry-mastery">Try Again</button>
                    </div>
                </div>

                <div class="mb-mastery-badge" hidden>
                    <span aria-hidden="true">🏅</span>
                    <div><small>Badge Earned</small><strong><?php echo esc_html(get_the_title()); ?> Master</strong></div>
                </div>

                <?php if ($next): ?>
                    <a class="mb-mastery-next-lesson" href="<?php echo esc_url(get_permalink($next)); ?>">
                        Continue to <?php echo esc_html($next->post_title); ?> →
                    </a>
                <?php endif; ?>
            </section>
        </div>
    </section>

    <section id="parent-help" class="mb-section mb-parent mb-gold-parent"
             data-parent-title="<?php echo esc_attr(get_the_title()); ?>">
        <?php echo $plugin->section_toggle("parent-help", "Parent Help", false); ?>
        <div id="parent-help-content" class="mb-collapsible-content">
            <div class="mb-parent-intro">
                <div>
                    <span class="mb-parent-kicker">Support Without Taking Over</span>
                    <h3>Help your child explain the mathematics</h3>
                    <p>Use these short prompts and activities to support understanding without simply giving the answer.</p>
                </div>
                <button type="button" class="mb-print-parent-guide">Print Parent Guide</button>
            </div>

            <?php if ($meta('parent_summary')): ?>
                <section class="mb-parent-summary">
                    <div class="mb-parent-icon" aria-hidden="true">📘</div>
                    <div>
                        <span>What Your Child Learned</span>
                        <h3><?php echo esc_html(get_the_title()); ?> in Plain Language</h3>
                        <p><?php echo wp_kses_post(nl2br($meta('parent_summary'))); ?></p>
                    </div>
                </section>
            <?php endif; ?>

            <div class="mb-parent-help-grid">
                <?php if ($meta('parent_conversation')): ?>
                    <section class="mb-parent-card mb-parent-conversation">
                        <div class="mb-parent-card-heading"><span aria-hidden="true">💬</span><div><small>Talk About It</small><h3>Conversation Starters</h3></div></div>
                        <?php echo $plugin->render_list($meta('parent_conversation')); ?>
                    </section>
                <?php endif; ?>

                <?php if ($meta('parent_mistakes')): ?>
                    <section class="mb-parent-card mb-parent-mistakes">
                        <div class="mb-parent-card-heading"><span aria-hidden="true">⚠️</span><div><small>Watch For</small><h3>Common Mistakes</h3></div></div>
                        <div class="mb-parent-mistake-list">
                            <?php foreach ($plugin->lines($meta('parent_mistakes')) as $item):
                                $parts = array_map('trim', explode('|', $item, 2)); ?>
                                <article>
                                    <strong><?php echo esc_html($parts[0] ?? 'Common mistake'); ?></strong>
                                    <p><?php echo esc_html($parts[1] ?? 'Ask your child to explain the place and value before trying again.'); ?></p>
                                </article>
                            <?php endforeach; ?>
                        </div>
                    </section>
                <?php endif; ?>

                <?php if ($meta('parent_five_minute')): ?>
                    <section class="mb-parent-card mb-parent-review">
                        <div class="mb-parent-card-heading"><span aria-hidden="true">⏱️</span><div><small>Quick Routine</small><h3>Five-Minute Review</h3></div></div>
                        <p><?php echo wp_kses_post(nl2br($meta('parent_five_minute'))); ?></p>
                        <button type="button" class="mb-start-parent-timer">Start 5-Minute Timer</button>
                        <div class="mb-parent-timer" aria-live="polite">05:00</div>
                    </section>
                <?php endif; ?>

                <?php if ($meta('parent_activity')): ?>
                    <section class="mb-parent-card mb-parent-activity">
                        <div class="mb-parent-card-heading"><span aria-hidden="true">🏠</span><div><small>Try It Together</small><h3>At-Home Activity</h3></div></div>
                        <p><?php echo wp_kses_post(nl2br($meta('parent_activity'))); ?></p>
                    </section>
                <?php endif; ?>
            </div>

            <?php if ($meta('parent_help')): ?>
                <section class="mb-parent-extra">
                    <div class="mb-parent-card-heading"><span aria-hidden="true">💡</span><div><small>More Support</small><h3>Additional Parent Tips</h3></div></div>
                    <?php echo $plugin->render_support_cards($meta('parent_help'), 'parent'); ?>
                </section>
            <?php endif; ?>

            <aside class="mb-parent-boundary">
                <span aria-hidden="true">🤝</span>
                <div>
                    <strong>Helpful Language</strong>
                    <p>Try: “Show me what you know so far,” “What place is that digit in?” or “Which example from the lesson looks similar?” Avoid immediately telling the answer.</p>
                </div>
            </aside>

            <section class="mb-parent-next-step">
                <div>
                    <span>Family Support Complete</span>
                    <h3>Celebrate the explanation, not just the answer</h3>
                    <p>When students explain their reasoning, they strengthen understanding and confidence.</p>
                </div>
                <a href="#teacher-notes" data-parent-go-teacher>View Teacher Notes →</a>
            </section>
        </div>
    </section>


    <?php if (
        $meta('teacher_objectives') || $meta('teacher_pacing') || $meta('teacher_materials') ||
        $meta('teacher_misconceptions') || $meta('teacher_differentiation') ||
        $meta('teacher_small_group') || $meta('teacher_formative') ||
        $meta('teacher_connections') || $meta('teacher_extensions') ||
        $meta('teacher_notes') || $meta('standards')
    ): ?>
        <section id="teacher-notes" class="mb-section mb-teacher mb-gold-teacher">
            <?php echo $plugin->section_toggle("teacher-notes", "Teacher Notes", false); ?>
            <div id="teacher-notes-content" class="mb-collapsible-content">
                <div class="mb-teacher-intro">
                    <div>
                        <span class="mb-teacher-kicker">Instructional Planning Support</span>
                        <h3>Teach with clarity, flexibility, and purpose</h3>
                        <p>Plan instruction, anticipate misconceptions, differentiate support, and monitor learning.</p>
                    </div>
                    <button type="button" class="mb-print-teacher-guide">Print Teacher Guide</button>
                </div>

                <div class="mb-teacher-planning-grid">
                    <?php if ($meta('teacher_objectives')): ?>
                        <section class="mb-teacher-card">
                            <div class="mb-teacher-card-heading"><span>🎯</span><div><small>Planning</small><h3>Objectives</h3></div></div>
                            <?php echo $plugin->render_list($meta('teacher_objectives')); ?>
                        </section>
                    <?php endif; ?>

                    <?php if ($meta('teacher_materials')): ?>
                        <section class="mb-teacher-card">
                            <div class="mb-teacher-card-heading"><span>🧰</span><div><small>Prepare</small><h3>Materials</h3></div></div>
                            <?php echo $plugin->render_list($meta('teacher_materials')); ?>
                        </section>
                    <?php endif; ?>

                    <?php if ($meta('teacher_pacing')): ?>
                        <section class="mb-teacher-card mb-teacher-pacing-card">
                            <div class="mb-teacher-card-heading"><span>⏱️</span><div><small>Suggested Flow</small><h3>Pacing</h3></div></div>
                            <div class="mb-teacher-pacing-list">
                                <?php foreach ($plugin->lines($meta('teacher_pacing')) as $item):
                                    $parts = array_map('trim', explode('|', $item)); ?>
                                    <article>
                                        <strong><?php echo esc_html($parts[0] ?? 'Lesson Stage'); ?></strong>
                                        <span><?php echo esc_html($parts[1] ?? 'Flexible'); ?></span>
                                        <p><?php echo esc_html($parts[2] ?? 'Adjust to student needs.'); ?></p>
                                    </article>
                                <?php endforeach; ?>
                            </div>
                        </section>
                    <?php endif; ?>
                </div>

                <?php if ($meta('teacher_misconceptions')): ?>
                    <section class="mb-teacher-wide-card">
                        <div class="mb-teacher-card-heading"><span>🧠</span><div><small>Anticipate and Respond</small><h3>Misconceptions &amp; Interventions</h3></div></div>
                        <div class="mb-teacher-intervention-list">
                            <?php foreach ($plugin->lines($meta('teacher_misconceptions')) as $item):
                                $parts = array_map('trim', explode('|', $item, 2)); ?>
                                <article>
                                    <div><span>Misconception</span><strong><?php echo esc_html($parts[0] ?? 'Student misconception'); ?></strong></div>
                                    <div><span>Instructional Response</span><p><?php echo esc_html($parts[1] ?? 'Use a visual model and ask the student to explain each step.'); ?></p></div>
                                </article>
                            <?php endforeach; ?>
                        </div>
                    </section>
                <?php endif; ?>

                <?php if ($meta('teacher_differentiation')): ?>
                    <section class="mb-teacher-wide-card">
                        <div class="mb-teacher-card-heading"><span>🌱</span><div><small>Meet Learner Needs</small><h3>Differentiation</h3></div></div>
                        <div class="mb-teacher-differentiation-grid">
                            <?php foreach ($plugin->lines($meta('teacher_differentiation')) as $item):
                                $parts = array_map('trim', explode('|', $item, 2)); ?>
                                <article>
                                    <strong><?php echo esc_html($parts[0] ?? 'Learner Support'); ?></strong>
                                    <p><?php echo esc_html($parts[1] ?? 'Adjust examples and scaffolds.'); ?></p>
                                </article>
                            <?php endforeach; ?>
                        </div>
                    </section>
                <?php endif; ?>

                <div class="mb-teacher-support-grid">
                    <?php if ($meta('teacher_small_group')): ?>
                        <section class="mb-teacher-card">
                            <div class="mb-teacher-card-heading"><span>👥</span><div><small>Targeted Support</small><h3>Small-Group Instruction</h3></div></div>
                            <?php echo $plugin->render_list($meta('teacher_small_group')); ?>
                        </section>
                    <?php endif; ?>

                    <?php if ($meta('teacher_formative')): ?>
                        <section class="mb-teacher-card">
                            <div class="mb-teacher-card-heading"><span>📋</span><div><small>Evidence of Learning</small><h3>Formative Assessment</h3></div></div>
                            <?php echo $plugin->render_list($meta('teacher_formative')); ?>
                        </section>
                    <?php endif; ?>

                    <?php if ($meta('teacher_connections')): ?>
                        <section class="mb-teacher-card">
                            <div class="mb-teacher-card-heading"><span>🔗</span><div><small>Integrate Learning</small><h3>Cross-Curricular Connections</h3></div></div>
                            <div class="mb-teacher-connection-list">
                                <?php foreach ($plugin->lines($meta('teacher_connections')) as $item):
                                    $parts = array_map('trim', explode('|', $item, 2)); ?>
                                    <article><strong><?php echo esc_html($parts[0] ?? 'Connection'); ?></strong><p><?php echo esc_html($parts[1] ?? 'Connect the mathematics to another subject.'); ?></p></article>
                                <?php endforeach; ?>
                            </div>
                        </section>
                    <?php endif; ?>

                    <?php if ($meta('teacher_extensions')): ?>
                        <section class="mb-teacher-card">
                            <div class="mb-teacher-card-heading"><span>🧩</span><div><small>Extend Thinking</small><h3>Enrichment</h3></div></div>
                            <?php echo $plugin->render_list($meta('teacher_extensions')); ?>
                        </section>
                    <?php endif; ?>
                </div>

                <?php if ($meta('standards')): ?>
                    <section class="mb-teacher-standards">
                        <div class="mb-teacher-card-heading"><span>📚</span><div><small>Alignment</small><h3>Standards</h3></div></div>
                        <?php echo $plugin->render_list($meta('standards'), 'mb-standards'); ?>
                    </section>
                <?php endif; ?>

                <?php if ($meta('teacher_notes')): ?>
                    <section class="mb-teacher-notes-callout">
                        <span>💡</span>
                        <div><strong>Teacher Notes</strong><p><?php echo wp_kses_post(nl2br($meta('teacher_notes'))); ?></p></div>
                    </section>
                <?php endif; ?>

                <section class="mb-teacher-finish">
                    <div>
                        <span>Planning Complete</span>
                        <h3>Ready to teach <?php echo esc_html(get_the_title()); ?></h3>
                        <p>Use the lesson, family support, and teacher guide as one connected instructional system.</p>
                    </div>
                    <a href="#lesson-top" data-teacher-back-top>Back to Lesson Top ↑</a>
                </section>
            </div>
        </section>
    <?php endif; ?>

    <?php if ($meta('related_topics')): ?>
        <section class="mb-section">
            <h2>Related Binder Pages</h2>
            <div class="mb-related">
                <?php foreach ($plugin->lines($meta('related_topics')) as $title):
                    $related = get_page_by_title($title, OBJECT, MathBinder_Core::CPT); ?>
                    <?php if ($related): ?>
                        <a class="mb-related-tab" href="<?php echo esc_url(get_permalink($related)); ?>"><?php echo esc_html($title); ?></a>
                    <?php else: ?>
                        <span class="mb-related-tab is-unavailable"><?php echo esc_html($title); ?></span>
                    <?php endif; ?>
                <?php endforeach; ?>
            </div>
        </section>
    <?php endif; ?>


    <section class="mb-lesson-finish">
        <div>
            <span class="mb-finish-kicker">Lesson Complete</span>
            <h2>Great job on <?php the_title(); ?>!</h2>
            <p>You reviewed the lesson, practiced the skill, and completed the mastery check.</p>
        </div>
        <div class="mb-finish-actions">
            <?php if ($next): ?>
                <a class="mb-next-lesson" href="<?php echo esc_url(get_permalink($next)); ?>">Next Lesson: <?php echo esc_html($next->post_title); ?> →</a>
            <?php endif; ?>
            <a class="mb-back-topics" href="<?php echo esc_url(home_url('/binder-topics/')); ?>">Back to Binder Topics</a>
        </div>
    </section>

    <nav class="mb-topic-nav" aria-label="Topic navigation">
        <div>
            <?php if ($previous): ?><span>Previous</span><a href="<?php echo esc_url(get_permalink($previous)); ?>">← <?php echo esc_html($previous->post_title); ?></a><?php endif; ?>
        </div>
        <a class="mb-section-home" href="<?php echo esc_url(home_url('/binder-topics/')); ?>">Binder Topics</a>
        <div class="mb-topic-next">
            <?php if ($next): ?><span>Next</span><a href="<?php echo esc_url(get_permalink($next)); ?>"><?php echo esc_html($next->post_title); ?> →</a><?php endif; ?>
        </div>
    </nav>
</main>
<?php endwhile;
get_footer();
