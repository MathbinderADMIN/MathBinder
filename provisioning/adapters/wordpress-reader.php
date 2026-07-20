<?php
/**
 * WordPress read adapter scaffold for provisioning.
 */

defined('ABSPATH') || exit;

class MathBinder_WordPress_Reader {
    public function get_post($post_id) {
        $this->assert_positive_int($post_id, '$post_id');

        throw new LogicException('WordPress Reader not implemented.');
    }

    public function get_post_meta($post_id, $meta_key, $single = true) {
        $this->assert_positive_int($post_id, '$post_id');
        $this->assert_non_empty_string($meta_key, '$meta_key');
        $single = (bool) $single;

        throw new LogicException('WordPress Reader not implemented.');
    }

    public function get_term($term_id, $taxonomy = '') {
        $this->assert_positive_int($term_id, '$term_id');

        if (!is_string($taxonomy)) {
            throw new InvalidArgumentException('$taxonomy must be a string.');
        }

        throw new LogicException('WordPress Reader not implemented.');
    }

    public function get_option($option_name, $default = null) {
        $this->assert_non_empty_string($option_name, '$option_name');

        throw new LogicException('WordPress Reader not implemented.');
    }

    public function find_post_by_slug($slug, $post_type = 'page') {
        $this->assert_non_empty_string($slug, '$slug');
        $this->assert_non_empty_string($post_type, '$post_type');

        $slug = trim($slug);
        $post_type = trim($post_type);

        $posts = get_posts(array(
            'post_type' => $post_type,
            'name' => $slug,
            'post_status' => array('publish', 'private', 'draft', 'pending', 'future'),
            'posts_per_page' => 1,
            'orderby' => 'ID',
            'order' => 'DESC',
            'no_found_rows' => true,
            'suppress_filters' => false,
        ));

        if (empty($posts)) {
            return new MathBinder_WordPress_State(
                false,
                0,
                $post_type,
                $slug,
                '',
                array(),
                array()
            );
        }

        $post = $posts[0];

        return new MathBinder_WordPress_State(
            true,
            isset($post->ID) ? (int) $post->ID : 0,
            isset($post->post_type) ? (string) $post->post_type : $post_type,
            isset($post->post_name) ? (string) $post->post_name : $slug,
            isset($post->post_title) ? (string) $post->post_title : '',
            array(),
            array()
        );
    }

    private function assert_positive_int($value, $argument_name) {
        if (!is_int($value) || $value <= 0) {
            throw new InvalidArgumentException($argument_name . ' must be an integer greater than 0.');
        }
    }

    private function assert_non_empty_string($value, $argument_name) {
        if (!is_string($value) || trim($value) === '') {
            throw new InvalidArgumentException($argument_name . ' must be a non-empty string.');
        }
    }
}
