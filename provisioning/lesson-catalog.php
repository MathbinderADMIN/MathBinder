<?php
/**
 * Catalog of declarative lesson manifests for generic lesson provisioning.
 *
 * Future responsibility:
 * - Provide the canonical manifest list used by the provisioning system.
 * - Keep lesson configuration data separate from orchestration logic.
 */

defined('ABSPATH') || exit;

class MathBinder_Lesson_Catalog {
    /**
     * Canonical section definitions for the verified PDF hierarchy.
     *
     * Each section record keeps the existing binder section slug, title,
     * inventory status, and descriptive context for downstream consumers.
     *
     * @return array<string, array<string, mixed>>
     */
    protected static function catalog_sections() {
        return array(
            'the-number-system' => array(
                'slug' => 'the-number-system',
                'number' => 1,
                'title' => 'The Number System',
                'inventory_status' => 'in_progress',
                'description' => 'Primary lessons and nested number-sense subsections verified from the PDF.',
            ),
            'ratios-proportional-relationships' => array(
                'slug' => 'ratios-proportional-relationships',
                'number' => 2,
                'title' => 'Ratios & Proportional Relationships',
                'inventory_status' => 'in_progress',
                'description' => 'Primary ratio lessons plus the Constant of Proportionality review and percents applications.',
            ),
            'algebraic-expressions' => array(
                'slug' => 'algebraic-expressions',
                'number' => 3,
                'title' => 'Algebraic Expressions',
                'inventory_status' => 'in_progress',
                'description' => 'Primary algebraic-expression lessons only.',
            ),
            'solving-graphing-equations' => array(
                'slug' => 'solving-graphing-equations',
                'number' => 4,
                'title' => 'Solving & Graphing Equations',
                'inventory_status' => 'in_progress',
                'description' => 'Primary linear-equation lessons only.',
            ),
            'solving-graphing-inequalities' => array(
                'slug' => 'solving-graphing-inequalities',
                'number' => 5,
                'title' => 'Solving & Graphing Inequalities',
                'inventory_status' => 'in_progress',
                'description' => 'Primary linear-inequality lessons only.',
            ),
            'triangles-transformations' => array(
                'slug' => 'triangles-transformations',
                'number' => 6,
                'title' => 'Triangles & Transformations',
                'inventory_status' => 'complete',
                'description' => 'Explore transformations, congruence, similarity, triangle relationships, coordinate geometry, and geometric reasoning.',
            ),
            'volume-area' => array(
                'slug' => 'volume-area',
                'number' => 7,
                'title' => 'Volume & Area',
                'inventory_status' => 'complete',
                'description' => 'Explore area, surface area, volume, circles, composite figures, scale drawings, cross-sections, and three-dimensional measurement.',
            ),
            'probability-statistics' => array(
                'slug' => 'probability-statistics',
                'number' => 8,
                'title' => 'Probability & Statistics',
                'inventory_status' => 'complete',
                'description' => 'Explore data displays, distributions, sampling, probability, association, simulation, regression, correlation, and causation.',
            ),
        );
    }

    /**
     * Canonical lesson entries derived from the verified PDF hierarchy.
     *
     * @return array<string, array<string, mixed>>
     */
    protected static function catalog_entries() {
        return array(
            'place-value' => array(
                'slug' => 'place-value',
                'title' => 'Place Value',
                'section' => 'the-number-system',
                'sequence' => 1,
                'item_type' => 'primary',
                'parent_slug' => null,
                'completion_status' => 'needs_verification',
                'direct_page_recommendation' => 'direct_page',
                'implementation_reference' => 'content/place-value.php',
            ),
            'number-operations' => array(
                'slug' => 'number-operations',
                'title' => 'Number Operations',
                'section' => 'the-number-system',
                'sequence' => 2,
                'item_type' => 'primary',
                'parent_slug' => null,
                'completion_status' => 'partial',
                'direct_page_recommendation' => 'direct_page',
                'implementation_reference' => 'content/number-operations-production.php',
            ),
            'number-operations-addition' => array(
                'slug' => 'number-operations-addition',
                'title' => 'Addition',
                'section' => 'the-number-system',
                'sequence' => 3,
                'item_type' => 'nested',
                'parent_slug' => 'number-operations',
                'completion_status' => 'partial',
                'direct_page_recommendation' => 'both',
                'implementation_reference' => 'content/number-operations-production.php',
            ),
            'number-operations-subtraction' => array(
                'slug' => 'number-operations-subtraction',
                'title' => 'Subtraction',
                'section' => 'the-number-system',
                'sequence' => 4,
                'item_type' => 'nested',
                'parent_slug' => 'number-operations',
                'completion_status' => 'partial',
                'direct_page_recommendation' => 'both',
                'implementation_reference' => 'content/number-operations-production.php',
            ),
            'number-operations-multiplication' => array(
                'slug' => 'number-operations-multiplication',
                'title' => 'Multiplication',
                'section' => 'the-number-system',
                'sequence' => 5,
                'item_type' => 'nested',
                'parent_slug' => 'number-operations',
                'completion_status' => 'partial',
                'direct_page_recommendation' => 'both',
                'implementation_reference' => 'content/number-operations-production.php',
            ),
            'number-operations-division' => array(
                'slug' => 'number-operations-division',
                'title' => 'Division',
                'section' => 'the-number-system',
                'sequence' => 6,
                'item_type' => 'nested',
                'parent_slug' => 'number-operations',
                'completion_status' => 'partial',
                'direct_page_recommendation' => 'both',
                'implementation_reference' => 'content/number-operations-production.php',
            ),
            'fractions-decimals' => array(
                'slug' => 'fractions-decimals',
                'title' => 'Fractions & Decimals',
                'section' => 'the-number-system',
                'sequence' => 7,
                'item_type' => 'primary',
                'parent_slug' => null,
                'completion_status' => 'not_started',
                'direct_page_recommendation' => 'direct_page',
                'implementation_reference' => 'mathbinder-core.php:topic preview',
            ),
            'order-of-operations-pemdas' => array(
                'slug' => 'order-of-operations-pemdas',
                'title' => 'Order of Operations (PEMDAS)',
                'section' => 'the-number-system',
                'sequence' => 8,
                'item_type' => 'primary',
                'parent_slug' => null,
                'completion_status' => 'not_started',
                'direct_page_recommendation' => 'direct_page',
                'implementation_reference' => 'mathbinder-core.php:topic preset',
            ),
            'real-complex-number-systems' => array(
                'slug' => 'real-complex-number-systems',
                'title' => 'The Real & Complex Number Systems',
                'section' => 'the-number-system',
                'sequence' => 9,
                'item_type' => 'primary',
                'parent_slug' => null,
                'completion_status' => 'partial',
                'direct_page_recommendation' => 'direct_page',
                'implementation_reference' => 'mathbinder-core.php:topic preset',
            ),
            'real-complex-number-systems-absolute-value' => array(
                'slug' => 'real-complex-number-systems-absolute-value',
                'title' => 'Absolute Value',
                'section' => 'the-number-system',
                'sequence' => 10,
                'item_type' => 'nested',
                'parent_slug' => 'real-complex-number-systems',
                'completion_status' => 'not_started',
                'direct_page_recommendation' => 'both',
                'implementation_reference' => null,
            ),
            'real-complex-number-systems-integers' => array(
                'slug' => 'real-complex-number-systems-integers',
                'title' => 'Integers',
                'section' => 'the-number-system',
                'sequence' => 11,
                'item_type' => 'nested',
                'parent_slug' => 'real-complex-number-systems',
                'completion_status' => 'not_started',
                'direct_page_recommendation' => 'both',
                'implementation_reference' => null,
            ),
            'real-complex-number-systems-rational-numbers' => array(
                'slug' => 'real-complex-number-systems-rational-numbers',
                'title' => 'Rational Numbers',
                'section' => 'the-number-system',
                'sequence' => 12,
                'item_type' => 'nested',
                'parent_slug' => 'real-complex-number-systems',
                'completion_status' => 'not_started',
                'direct_page_recommendation' => 'both',
                'implementation_reference' => null,
            ),
            'real-complex-number-systems-irrational-numbers' => array(
                'slug' => 'real-complex-number-systems-irrational-numbers',
                'title' => 'Irrational Numbers',
                'section' => 'the-number-system',
                'sequence' => 13,
                'item_type' => 'nested',
                'parent_slug' => 'real-complex-number-systems',
                'completion_status' => 'not_started',
                'direct_page_recommendation' => 'both',
                'implementation_reference' => null,
            ),
            'ratios' => array(
                'slug' => 'ratios',
                'title' => 'Ratios',
                'section' => 'ratios-proportional-relationships',
                'sequence' => 1,
                'item_type' => 'primary',
                'parent_slug' => null,
                'completion_status' => 'not_started',
                'direct_page_recommendation' => 'direct_page',
                'implementation_reference' => 'mathbinder-core.php:planned preview',
            ),
            'rates-unit-rates' => array(
                'slug' => 'rates-unit-rates',
                'title' => 'Rates & Unit Rates',
                'section' => 'ratios-proportional-relationships',
                'sequence' => 2,
                'item_type' => 'primary',
                'parent_slug' => null,
                'completion_status' => 'not_started',
                'direct_page_recommendation' => 'direct_page',
                'implementation_reference' => 'mathbinder-core.php:planned preview',
            ),
            'constant-of-proportionality' => array(
                'slug' => 'constant-of-proportionality',
                'title' => 'Constant of Proportionality',
                'section' => 'ratios-proportional-relationships',
                'sequence' => 3,
                'item_type' => 'primary',
                'parent_slug' => null,
                'completion_status' => 'not_started',
                'direct_page_recommendation' => 'direct_page',
                'implementation_reference' => 'mathbinder-core.php:planned preview',
            ),
            'constant-of-proportionality-review-of-proportions' => array(
                'slug' => 'constant-of-proportionality-review-of-proportions',
                'title' => 'Review of Proportions',
                'section' => 'ratios-proportional-relationships',
                'sequence' => 4,
                'item_type' => 'nested',
                'parent_slug' => 'constant-of-proportionality',
                'completion_status' => 'not_started',
                'direct_page_recommendation' => 'parent_overview',
                'implementation_reference' => null,
            ),
            'percents' => array(
                'slug' => 'percents',
                'title' => 'Percents',
                'section' => 'ratios-proportional-relationships',
                'sequence' => 5,
                'item_type' => 'primary',
                'parent_slug' => null,
                'completion_status' => 'not_started',
                'direct_page_recommendation' => 'direct_page',
                'implementation_reference' => 'mathbinder-core.php:planned preview',
            ),
            'percents-markups' => array(
                'slug' => 'percents-markups',
                'title' => 'Markups',
                'section' => 'ratios-proportional-relationships',
                'sequence' => 6,
                'item_type' => 'nested',
                'parent_slug' => 'percents',
                'completion_status' => 'not_started',
                'direct_page_recommendation' => 'both',
                'implementation_reference' => null,
            ),
            'percents-discounts' => array(
                'slug' => 'percents-discounts',
                'title' => 'Discounts',
                'section' => 'ratios-proportional-relationships',
                'sequence' => 7,
                'item_type' => 'nested',
                'parent_slug' => 'percents',
                'completion_status' => 'not_started',
                'direct_page_recommendation' => 'both',
                'implementation_reference' => null,
            ),
            'percents-taxes' => array(
                'slug' => 'percents-taxes',
                'title' => 'Taxes',
                'section' => 'ratios-proportional-relationships',
                'sequence' => 8,
                'item_type' => 'nested',
                'parent_slug' => 'percents',
                'completion_status' => 'not_started',
                'direct_page_recommendation' => 'both',
                'implementation_reference' => null,
            ),
            'percents-tips' => array(
                'slug' => 'percents-tips',
                'title' => 'Tips',
                'section' => 'ratios-proportional-relationships',
                'sequence' => 9,
                'item_type' => 'nested',
                'parent_slug' => 'percents',
                'completion_status' => 'not_started',
                'direct_page_recommendation' => 'both',
                'implementation_reference' => null,
            ),
            'proportional-relationship-equations' => array(
                'slug' => 'proportional-relationship-equations',
                'title' => 'Proportional Relationship Equations',
                'section' => 'ratios-proportional-relationships',
                'sequence' => 10,
                'item_type' => 'primary',
                'parent_slug' => null,
                'completion_status' => 'not_started',
                'direct_page_recommendation' => 'direct_page',
                'implementation_reference' => 'mathbinder-core.php:planned preview',
            ),
            'properties-of-addition-and-multiplication' => array(
                'slug' => 'properties-of-addition-and-multiplication',
                'title' => 'Properties of Addition and Multiplication',
                'section' => 'algebraic-expressions',
                'sequence' => 1,
                'item_type' => 'primary',
                'parent_slug' => null,
                'completion_status' => 'not_started',
                'direct_page_recommendation' => 'direct_page',
                'implementation_reference' => 'mathbinder-core.php:planned preview',
            ),
            'variables-and-expressions' => array(
                'slug' => 'variables-and-expressions',
                'title' => 'Variables and Expressions',
                'section' => 'algebraic-expressions',
                'sequence' => 2,
                'item_type' => 'primary',
                'parent_slug' => null,
                'completion_status' => 'not_started',
                'direct_page_recommendation' => 'direct_page',
                'implementation_reference' => 'mathbinder-core.php:planned preview',
            ),
            'radicals-and-integer-exponents' => array(
                'slug' => 'radicals-and-integer-exponents',
                'title' => 'Radicals and Integer Exponents',
                'section' => 'algebraic-expressions',
                'sequence' => 3,
                'item_type' => 'primary',
                'parent_slug' => null,
                'completion_status' => 'not_started',
                'direct_page_recommendation' => 'direct_page',
                'implementation_reference' => 'mathbinder-core.php:planned preview',
            ),
            'evaluate-expressions' => array(
                'slug' => 'evaluate-expressions',
                'title' => 'Evaluate Expressions',
                'section' => 'algebraic-expressions',
                'sequence' => 4,
                'item_type' => 'primary',
                'parent_slug' => null,
                'completion_status' => 'not_started',
                'direct_page_recommendation' => 'direct_page',
                'implementation_reference' => 'mathbinder-core.php:planned preview',
            ),
            'equivalent-expressions' => array(
                'slug' => 'equivalent-expressions',
                'title' => 'Equivalent Expressions',
                'section' => 'algebraic-expressions',
                'sequence' => 5,
                'item_type' => 'primary',
                'parent_slug' => null,
                'completion_status' => 'not_started',
                'direct_page_recommendation' => 'direct_page',
                'implementation_reference' => 'mathbinder-core.php:planned preview',
            ),
            'solving-linear-equations' => array(
                'slug' => 'solving-linear-equations',
                'title' => 'Solving Linear Equations',
                'section' => 'solving-graphing-equations',
                'sequence' => 1,
                'item_type' => 'primary',
                'parent_slug' => null,
                'completion_status' => 'needs_verification',
                'direct_page_recommendation' => 'direct_page',
                'implementation_reference' => 'mathbinder-core.php:planned preview',
            ),
            'graphing-linear-equations' => array(
                'slug' => 'graphing-linear-equations',
                'title' => 'Graphing Linear Equations',
                'section' => 'solving-graphing-equations',
                'sequence' => 2,
                'item_type' => 'primary',
                'parent_slug' => null,
                'completion_status' => 'needs_verification',
                'direct_page_recommendation' => 'direct_page',
                'implementation_reference' => 'mathbinder-core.php:planned preview',
            ),
            'solving-linear-inequalities' => array(
                'slug' => 'solving-linear-inequalities',
                'title' => 'Solving Linear Inequalities',
                'section' => 'solving-graphing-inequalities',
                'sequence' => 1,
                'item_type' => 'primary',
                'parent_slug' => null,
                'completion_status' => 'not_started',
                'direct_page_recommendation' => 'direct_page',
                'implementation_reference' => 'mathbinder-core.php:planned preview',
            ),
            'graphing-linear-inequalities' => array(
                'slug' => 'graphing-linear-inequalities',
                'title' => 'Graphing Linear Inequalities',
                'section' => 'solving-graphing-inequalities',
                'sequence' => 2,
                'item_type' => 'primary',
                'parent_slug' => null,
                'completion_status' => 'needs_verification',
                'direct_page_recommendation' => 'direct_page',
                'implementation_reference' => 'mathbinder-core.php:planned preview',
            ),
        );
    }

    /**
     * Return the canonical catalog as sections plus flat entries.
     *
     * @return array<string, array<string, mixed>>
     */
    public static function get_catalog() {
        return array(
            'sections' => self::catalog_sections(),
            'entries' => self::catalog_entries(),
        );
    }

    /**
     * Return all canonical catalog entries keyed by slug.
     *
     * @return array<string, array<string, mixed>>
     */
    public static function get_entries() {
        return self::catalog_entries();
    }

    /**
     * Return one canonical catalog entry by slug.
     *
     * @param string $slug
     * @return array<string, mixed>|null
     */
    public static function get_entry($slug) {
        $slug = is_string($slug) ? trim($slug) : '';
        if ($slug === '') {
            return null;
        }

        $entries = self::catalog_entries();
        return isset($entries[$slug]) ? $entries[$slug] : null;
    }

    /**
     * Return entries grouped by the existing Binder Section slugs.
     *
     * The legacy `topics` key is retained for compatibility and contains the
     * primary lesson titles only. Nested topics are exposed separately so the
     * hierarchy is preserved for future consumers.
     *
     * @return array<string, array<string, mixed>>
     */
    public static function get_section_topic_map() {
        $sections = self::catalog_sections();
        $entries = self::catalog_entries();
        $map = array();

        foreach ($sections as $section_slug => $section) {
            $primary_topics = array();
            $nested_topics = array();

            foreach ($entries as $entry) {
                if (!isset($entry['section']) || $entry['section'] !== $section_slug) {
                    continue;
                }

                $topic = array(
                    'slug' => $entry['slug'],
                    'title' => $entry['title'],
                    'sequence' => $entry['sequence'],
                    'completion_status' => $entry['completion_status'],
                    'direct_page_recommendation' => $entry['direct_page_recommendation'],
                    'parent_slug' => $entry['parent_slug'],
                    'implementation_reference' => $entry['implementation_reference'],
                );

                if ($entry['item_type'] === 'nested') {
                    $nested_topics[] = $topic;
                    continue;
                }

                $primary_topics[] = $topic;
            }

            usort($primary_topics, array(__CLASS__, 'compare_catalog_topic_sequence'));
            usort($nested_topics, array(__CLASS__, 'compare_catalog_topic_sequence'));

            $titles = array();
            foreach ($primary_topics as $topic) {
                $titles[] = $topic['title'];
            }

            $map[$section_slug] = array(
                'slug' => $section_slug,
                'title' => $section['title'],
                'number' => $section['number'],
                'description' => $section['description'],
                'inventory_status' => $section['inventory_status'],
                'topics' => $titles,
                'primary_topics' => $primary_topics,
                'nested_topics' => $nested_topics,
            );
        }

        return $map;
    }

    /**
     * Return only the primary lesson entries.
     *
     * @return array<string, array<string, mixed>>
     */
    public static function get_primary_entries() {
        $entries = self::catalog_entries();
        $filtered = array();

        foreach ($entries as $slug => $entry) {
            if ($entry['item_type'] === 'primary') {
                $filtered[$slug] = $entry;
            }
        }

        return $filtered;
    }

    /**
     * Return only the nested entries for one parent slug.
     *
     * @param string $parent_slug
     * @return array<string, array<string, mixed>>
     */
    public static function get_nested_entries($parent_slug) {
        $parent_slug = is_string($parent_slug) ? trim($parent_slug) : '';
        if ($parent_slug === '') {
            return array();
        }

        $entries = self::catalog_entries();
        $filtered = array();

        foreach ($entries as $slug => $entry) {
            if ($entry['item_type'] === 'nested' && $entry['parent_slug'] === $parent_slug) {
                $filtered[$slug] = $entry;
            }
        }

        return $filtered;
    }

    /**
     * Compare helper for catalog sequencing.
     *
     * @param array<string, mixed> $left
     * @param array<string, mixed> $right
     * @return int
     */
    protected static function compare_catalog_topic_sequence($left, $right) {
        $left_sequence = isset($left['sequence']) ? intval($left['sequence']) : 0;
        $right_sequence = isset($right['sequence']) ? intval($right['sequence']) : 0;

        if ($left_sequence === $right_sequence) {
            $left_title = isset($left['title']) ? strtolower((string) $left['title']) : '';
            $right_title = isset($right['title']) ? strtolower((string) $right['title']) : '';
            return strcmp($left_title, $right_title);
        }

        return $left_sequence < $right_sequence ? -1 : 1;
    }

    /**
     * Return all registered lesson manifests.
     *
     * Manifest shape:
     * - Key: lesson slug (string), for example "place-value".
     * - Value: associative array with the following top-level fields:
     *   - slug (string): canonical lesson slug.
     *   - title (string): human-readable lesson title.
     *   - section (string): Binder Section slug used to attach taxonomy.
     *   - order (int): default menu order for topic sequencing.
     *   - version (int): manifest schema/content version for future upgrades.
     *   - defaults (array): default field-value map for lesson provisioning.
     *   - write_policies (array): per-field write policy definitions.
     *   - operations (array): idempotent operation list for upgrades.
     *
     * During Step 2, defaults, write_policies, and operations are intentionally
     * empty to define schema without introducing provisioning behavior.
     *
     * @return array<string, array<string, mixed>>
     */
    public static function get_manifests() {
        return array(
            'place-value' => array(
                'slug' => 'place-value',
                'title' => 'Place Value',
                'section' => 'the-number-system',
                'order' => 1,
                'version' => 1,
                // Temporary planning probes for Sprint 7.
                // Remove these fields before production provisioning.
                'defaults' => array(
                    'slug' => 'place-value',
                    'planning_probe_managed' => 'managed probe value',
                    'planning_probe_unmanaged' => 'unmanaged probe value',
                ),
                // Temporary planning probe policy mapping.
                // Remove this probe policy before production provisioning.
                'write_policies' => array(
                    'slug' => MathBinder_Lesson_Write_Policy::MISSING_ONLY,
                    'planning_probe_managed' => MathBinder_Lesson_Write_Policy::MISSING_ONLY,
                ),
                'operations' => array(),
            ),
        );
    }
}
