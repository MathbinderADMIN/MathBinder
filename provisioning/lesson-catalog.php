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
                    'planning_probe_managed' => 'managed probe value',
                    'planning_probe_unmanaged' => 'unmanaged probe value',
                ),
                // Temporary planning probe policy mapping.
                // Remove this probe policy before production provisioning.
                'write_policies' => array(
                    'planning_probe_managed' => MathBinder_Lesson_Write_Policy::MISSING_ONLY,
                ),
                'operations' => array(),
            ),
        );
    }
}
