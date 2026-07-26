<?php
if (!defined('ABSPATH')) exit;
get_header();

$term = get_queried_object();
$core = new MathBinder_Core();

$published_pages = get_posts([
    'post_type' => MathBinder_Core::CPT,
    'post_status' => 'publish',
    'posts_per_page' => -1,
    'tax_query' => [[
        'taxonomy' => MathBinder_Core::TAX,
        'field' => 'term_id',
        'terms' => $term->term_id
    ]],
    'orderby' => ['menu_order' => 'ASC', 'title' => 'ASC']
]);

$topic_map_reflection = new ReflectionClass($core);
$method = $topic_map_reflection->getMethod('section_topic_map');
$method->setAccessible(true);
$topic_map = $method->invoke($core);
$planned = isset($topic_map[$term->slug]) ? $topic_map[$term->slug] : ['description' => '', 'topics' => []];

$published_by_title = [];
foreach ($published_pages as $page) {
    $published_by_title[strtolower(trim($page->post_title))] = $page;
}
?>
<main class="mb-page-wrap mb-section-archive">
    <div class="mb-template-diagnostic" data-mb-template="taxonomy-mb_binder_section" style="max-width: 920px; margin: 12px auto 16px; padding: 8px 12px; border: 1px solid #d0b96a; background: #fff6cc; color: #2f2a1f; text-align: center; font-size: 14px; line-height: 1.35;">MathBinder section template active — diagnostic 27.0.3</div>
    <nav class="mb-breadcrumbs" aria-label="Breadcrumb">
        <a href="<?php echo esc_url(home_url('/')); ?>">Home</a><span>›</span>
        <a href="<?php echo esc_url(home_url('/binder-topics/')); ?>">Binder Topics</a><span>›</span>
        <span aria-current="page"><?php echo esc_html($term->name); ?></span>
    </nav>

    <header class="mb-chapter-header">
        <span class="mb-chapter-label">Binder Section</span>
        <h1><?php echo esc_html($term->name); ?></h1>
        <?php if (!empty($planned['description'])): ?>
            <p><?php echo esc_html($planned['description']); ?></p>
        <?php else: ?>
            <p>Open a Binder Page to find instruction, videos, practice, downloads, parent help, and a mastery check.</p>
        <?php endif; ?>
    </header>

    <section class="mb-section-progress">
        <div>
            <span>Published Binder Pages</span>
            <strong><?php echo esc_html(count($published_pages)); ?></strong>
        </div>
        <div>
            <span>Planned Topics</span>
            <strong><?php echo esc_html(count($planned['topics'])); ?></strong>
        </div>
        <a href="<?php echo esc_url(home_url('/binder-topics/')); ?>">Back to Binder Topics</a>
    </section>

    <div class="mb-chapter-page-grid">
        <?php if (!empty($planned['topics'])): ?>
            <?php foreach ($planned['topics'] as $index => $topic):
                $key = strtolower(trim($topic));
                $page = $published_by_title[$key] ?? get_page_by_title($topic, OBJECT, MathBinder_Core::CPT);
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
<?php get_footer(); ?>
