<?php
/*
Plugin Name: MathBinder Content Pack 001.4 - Number Operations Core Content
Description: Populates missing Number Operations Binder Page core instructional fields without overwriting teacher edits.
Version: 0.1.4
*/

if (!defined('ABSPATH')) {
    exit;
}

register_activation_hook(__FILE__, 'mbcp0014_install');
add_action('admin_notices', 'mbcp0014_admin_notice');

function mbcp0014_install() {
    if (!mbcp0014_core_is_active()) {
        mbcp0014_set_notice('error', 'MathBinder Core is not active, so MathBinder Content Pack 001.4 could not update Number Operations.');
        return;
    }

    $page = get_page_by_path('number-operations', OBJECT, 'mb_binder_page');
    if (!$page || !isset($page->ID) || get_post_type($page->ID) !== 'mb_binder_page') {
        mbcp0014_set_notice('error', 'The Number Operations Binder Page (slug: number-operations) could not be found, so no content was changed.');
        return;
    }

    $fields = [
        '_mb_subtitle' => 'Build fluency with addition, subtraction, multiplication, and division using efficient strategies and clear reasoning.',
        '_mb_essential_question' => 'How can operation choice, estimation, and inverse checks help us solve real-world problems accurately?',
        '_mb_difficulty' => 'beginner',
        '_mb_estimated_time' => '25-35 minutes',
        '_mb_prerequisites' => 'Place value and basic multiplication facts',
        '_mb_introduction' => '<p>Number operations describe how quantities are combined, compared, grouped, and shared. Strong operation sense means choosing the right operation, calculating accurately, and checking whether an answer is reasonable.</p><p>In this lesson, you will solve problems with addition, subtraction, multiplication, and division, then explain why your methods work.</p>',
        '_mb_learning_targets' => "Choose the correct operation based on context clues and quantity relationships.\nUse efficient strategies to add, subtract, multiply, and divide whole numbers.\nEstimate before solving and check whether an answer is reasonable.\nInterpret remainders in context and explain what they mean.",
        '_mb_vocabulary' => "Sum - result of addition\nDifference - result of subtraction\nProduct - result of multiplication\nQuotient - result of division\nRemainder - amount left after making equal groups\nInverse operations - operations that undo each other",
        '_mb_worked_examples' => "Find 284 + 167 | Add ones: 4 + 7 = 11. | Regroup 1 ten and add tens: 8 + 6 + 1 = 15 tens. | Regroup 1 hundred and add hundreds: 2 + 1 + 1 = 4. | 284 + 167 = 451.\nFind 900 - 468 | Regroup so subtraction is possible in each place. | Ones: 10 - 8 = 2. | Tens: 9 - 6 = 3, Hundreds: 8 - 4 = 4. | 900 - 468 = 432.\nFind 17 x 24 using the distributive property | Rewrite 24 as 20 + 4. | Compute 17 x 20 = 340 and 17 x 4 = 68. | Add partial products. | 17 x 24 = 408.\nInterpret 53 / 6 | Find the largest multiple of 6 less than 53. | 6 x 8 = 48. | Subtract to find leftover: 53 - 48 = 5. | 53 / 6 = 8 R5, so 8 full groups and 5 left over.",
        '_mb_common_questions' => "How do I know which operation to use? | Decide whether the situation combines, compares, groups, or shares quantities.\nWhy estimate first? | Estimation predicts answer size and helps catch unreasonable results.\nWhat should I do with a remainder? | Use context to decide whether to keep it, round up, or describe leftovers clearly.",
        '_mb_practice_warmup' => "Compute 46 + 38. | 84 | Add ones first. | Regroup one ten if needed. | 6 + 8 = 14, write 4 and regroup 1 ten; 4 + 3 + 1 = 8, so 84.\nCompute 72 - 29. | 43 | Regroup from the tens place. | Subtract ones, then tens. | 12 - 9 = 3 and 6 - 2 = 4, so 43.",
        '_mb_guided_practice' => "Compute 458 - 179. | 279 | Start with ones and regroup carefully. | Rewrite one ten as 10 ones when needed. | 458 - 179 = 279.\nCompute 24 x 6. | 144 | Break 24 into 20 and 4. | Multiply each part and add. | (20 x 6) + (4 x 6) = 120 + 24 = 144.\nCompute 35 / 4. | 8 R3 | Find the largest multiple of 4 less than 35. | Subtract to find the remainder. | 4 x 8 = 32 and 35 - 32 = 3, so 8 R3.",
        '_mb_independent_practice' => "Compute 627 + 185. | 812 | Add by place value. | Estimate to check reasonableness. | 627 + 185 = 812.\nCompute 900 - 468. | 432 | Regroup across place values. | Check by adding back. | 900 - 468 = 432.\nCompute 42 / 5. | 8 R2 | Use multiplication facts for 5. | Subtract to find leftover. | 5 x 8 = 40 with 2 left, so 8 R2.",
        '_mb_challenge_practice' => "A class collected 287 cans on Monday, 354 on Tuesday, and 198 on Wednesday. How many cans were collected in all, and about how many is that when estimated?\nA bakery packs 196 muffins into boxes of 12. How many full boxes can be filled, and how many muffins remain?",
        '_mb_real_world_practice' => "A store sold 248 notebooks in the morning and 179 in the afternoon. How many notebooks were sold in total?\nA coach has 53 players and makes teams of 6. How many full teams can be made, and how many players are left over?",
        '_mb_learn_checks' => "Which operation is best for finding a total amount? | Addition ; Subtraction ; Multiplication ; Division | A\nWhat is the best estimate for 398 x 21? | 800 ; 8,000 ; 80,000 ; 400 | B\nWhat is 36 / 6? | 6 ; 30 ; 216 ; 42 | A\nWhich equation checks 84 - 29 = 55? | 55 - 29 = 84 ; 55 + 29 = 84 ; 84 + 29 = 55 ; 29 - 55 = 84 | B",
        '_mb_common_mistakes' => "Choosing an operation from one keyword only | Read the full context and identify how quantities are related.\nForgetting to regroup in addition or subtraction | Rename place values carefully and record each regrouping step.\nIgnoring the meaning of a remainder | Explain what is left over and decide how to report it for the context.\nSkipping estimation | Estimate first to see if your exact answer is in a reasonable range.",
        '_mb_parent_summary' => 'Number operations help students solve everyday problems by combining, comparing, grouping, and sharing amounts. In this lesson, students choose operations carefully, estimate first, solve accurately, and check with inverse operations.',
        '_mb_teacher_objectives' => "Students will select and justify the correct operation for contextual problems.\nStudents will solve multi-digit addition, subtraction, multiplication, and division problems accurately.\nStudents will estimate to evaluate reasonableness before and after solving.\nStudents will interpret remainders and communicate what they mean in context.",
        '_mb_mastery_questions' => "What is the best estimate for 398 x 21? | 800 ; 8,000 ; 80,000 ; 4,000 | B\nWhich operation checks division? | Addition ; Subtraction ; Multiplication ; Exponents | C\nWhat is 17 x 24? | 348 ; 388 ; 408 ; 428 | C\nWhat is a remainder? | The amount left after equal groups are formed ; The answer to multiplication ; A rounding error ; A decimal point | A\nWhy estimate first? | To avoid solving ; To predict answer size and catch unreasonable results ; To change the operation ; To eliminate units | B",
        '_mb_standards' => "CCSS.MATH.CONTENT.5.NBT.B.5 - Fluently multiply multi-digit whole numbers using the standard algorithm.\nCCSS.MATH.CONTENT.5.NBT.B.6 - Find whole-number quotients of whole numbers with up to four-digit dividends and two-digit divisors.\nCCSS.MATH.CONTENT.6.NS.B.2 - Fluently divide multi-digit numbers using the standard algorithm.\nStandards for Mathematical Practice: MP1, MP2, MP3, MP6.",
    ];

    $updated_count = 0;
    foreach ($fields as $meta_key => $meta_value) {
        if (mbcp0014_update_missing_meta($page->ID, $meta_key, $meta_value)) {
            $updated_count++;
        }
    }

    update_option('mathbinder_content_pack_001_4_version', '0.1.4');

    if ($updated_count > 0) {
        mbcp0014_set_notice('success', 'MathBinder Content Pack 001.4 populated missing Number Operations fields (' . intval($updated_count) . ' fields updated).');
        return;
    }

    mbcp0014_set_notice('success', 'MathBinder Content Pack 001.4 found no missing Number Operations fields to update. Existing content was preserved.');
}

function mbcp0014_update_missing_meta($post_id, $meta_key, $meta_value) {
    if (!mbcp0014_meta_is_missing($post_id, $meta_key)) {
        return false;
    }

    if ($meta_key === '_mb_introduction') {
        return (bool) update_post_meta($post_id, $meta_key, wp_kses_post($meta_value));
    }

    return (bool) update_post_meta($post_id, $meta_key, sanitize_textarea_field($meta_value));
}

function mbcp0014_meta_is_missing($post_id, $meta_key) {
    if (!metadata_exists('post', $post_id, $meta_key)) {
        return true;
    }

    $current = get_post_meta($post_id, $meta_key, true);
    if (!is_string($current)) {
        return false;
    }

    return trim($current) === '';
}

function mbcp0014_admin_notice() {
    if (!is_admin()) {
        return;
    }

    $notice = get_transient('mbcp0014_activation_notice');
    if (empty($notice['message'])) {
        return;
    }

    $type = isset($notice['type']) ? sanitize_key($notice['type']) : 'success';
    $message = wp_kses_post($notice['message']);

    echo '<div class="notice notice-' . esc_attr($type) . ' is-dismissible"><p>' . $message . '</p></div>';
    delete_transient('mbcp0014_activation_notice');
}

function mbcp0014_set_notice($type, $message) {
    set_transient('mbcp0014_activation_notice', [
        'type' => sanitize_key($type),
        'message' => wp_kses_post($message),
    ], 60);
}

function mbcp0014_core_is_active() {
    if (class_exists('MathBinder_Core')) {
        return true;
    }

    if (function_exists('is_plugin_active')) {
        return is_plugin_active('mathbinder-core/mathbinder-core.php');
    }

    return false;
}
