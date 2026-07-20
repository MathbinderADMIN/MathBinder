<?php
/**
 * Write policy constants for lesson provisioning field ownership behavior.
 *
 * Future responsibility:
 * - Define the allowed write strategies used by provisioning operations.
 * - Standardize policy values across manifests and execution logic.
 */

defined('ABSPATH') || exit;

class MathBinder_Lesson_Write_Policy {
    const MISSING_ONLY = 'missing_only';
    const SEED_ONCE = 'seed_once';
    const MANAGED_REPLACE = 'managed_replace';
    const APPEND_UNIQUE = 'append_unique';
    const NEVER_MANAGE = 'never_manage';
}
