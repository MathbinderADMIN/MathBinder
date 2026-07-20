<?php
/**
 * Operation ledger for tracking lesson-level provisioning operation execution.
 *
 * Future responsibility:
 * - Record operation completion per lesson post.
 * - Support idempotent upgrades by skipping completed operations.
 */

defined('ABSPATH') || exit;

class MathBinder_Lesson_Operation_Ledger {
    /**
     * Determine whether an operation has already completed for a lesson post.
     *
     * @param int $post_id
     * @param string $operation_id
     * @return bool
     */
    public function has_completed($post_id, $operation_id) {
        return false;
    }

    /**
     * Mark an operation as completed for a lesson post.
     *
     * @param int $post_id
     * @param string $operation_id
     * @return bool
     */
    public function mark_completed($post_id, $operation_id) {
        return false;
    }
}
