<?php
/*
Plugin Name: MathBinder Content Pack 001.3 - Number Operations Watch It
Description: Updates the existing Number Operations Binder Page with Watch It video and reflection metadata.
Version: 0.1.3
*/

if (!defined('ABSPATH')) {
    exit;
}

if (!defined('DEFAULT_NUMBER_OPERATIONS_VIDEO_URL')) {
    /**
     * Set this to a verified public Number Operations overview video URL when available.
     * Leaving it empty preserves the existing project convention of avoiding unverified links.
     */
    define('DEFAULT_NUMBER_OPERATIONS_VIDEO_URL', '');
}

register_activation_hook(__FILE__, 'mbcp0013_install');
add_action('admin_notices', 'mbcp0013_admin_notice');

function mbcp0013_install() {
    if (!mbcp0013_core_is_active()) {
        mbcp0013_set_notice('error', 'MathBinder Core is not active, so MathBinder Content Pack 001.3 could not update Number Operations.');
        return;
    }

    $page = get_page_by_title('Number Operations', OBJECT, 'mb_binder_page');
    if (!$page || get_post_status($page->ID) !== 'publish') {
        mbcp0013_set_notice('error', 'A published Number Operations Binder Page could not be found, so no content was changed.');
        return;
    }

    $video_url = trim((string) DEFAULT_NUMBER_OPERATIONS_VIDEO_URL);
    $video_title = 'Number Operations Overview';
    $video_resource = $video_title . ' | ' . $video_url;

    $watch_fields = [
        '_mb_videos' => $video_resource,
        '_mb_video_chapters' => "0:00 | Overview\n2:20 | Choosing the Operation\n4:15 | Estimating and Checking Work",
        '_mb_watch_vocabulary' => "Operation — A mathematical action such as addition, subtraction, multiplication, or division.\nEstimate — A close answer used to judge whether a result is reasonable.\nInverse operation — An operation that undoes another operation.",
        '_mb_pause_prompts' => "Pause and explain why the situation calls for addition, subtraction, multiplication, or division.\nEstimate the answer before you calculate.\nCheck your work by using the inverse operation.",
        '_mb_video_transcript' => "This Watch It section can be used for a teacher-written overview of number operations, an explanation of the featured video, or a student-friendly transcript summary."
    ];

    foreach ($watch_fields as $meta_key => $meta_value) {
        update_post_meta($page->ID, $meta_key, sanitize_textarea_field($meta_value));
    }

    update_option('mathbinder_content_pack_001_3_version', '0.1.3');

    mbcp0013_set_notice('success', 'MathBinder Content Pack 001.3 updated the Watch It fields for the Number Operations Binder Page.');
}

function mbcp0013_admin_notice() {
    if (!is_admin()) {
        return;
    }

    $notice = get_transient('mbcp0013_activation_notice');
    if (empty($notice['message'])) {
        return;
    }

    $type = isset($notice['type']) ? sanitize_key($notice['type']) : 'success';
    $message = wp_kses_post($notice['message']);

    echo '<div class="notice notice-' . esc_attr($type) . ' is-dismissible"><p>' . $message . '</p></div>';
    delete_transient('mbcp0013_activation_notice');
}

function mbcp0013_set_notice($type, $message) {
    set_transient('mbcp0013_activation_notice', [
        'type' => sanitize_key($type),
        'message' => wp_kses_post($message)
    ], 60);
}

function mbcp0013_core_is_active() {
    if (class_exists('MathBinder_Core')) {
        return true;
    }

    if (function_exists('is_plugin_active')) {
        return is_plugin_active('mathbinder-core/mathbinder-core.php');
    }

    return false;
}
