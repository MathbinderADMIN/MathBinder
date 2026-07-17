<?php

require_once __DIR__ . '/lesson-schema.php';

if (!class_exists('MathBinder_Lesson_Renderer')) {
    class MathBinder_Lesson_Renderer
    {
        protected $section_renderers = array();

        public function __construct($lesson = null)
        {
            $this->register_default_renderers();
            if ($lesson !== null) {
                $this->set_lesson($lesson);
            }
        }

        public function set_lesson($lesson)
        {
            $this->lesson = $this->coerce_schema($lesson);
            return $this;
        }

        public function register_section_renderer($section_key, callable $renderer, $priority = 10)
        {
            if (!is_string($section_key) || $section_key === '') {
                return $this;
            }

            if (!isset($this->section_renderers[$section_key])) {
                $this->section_renderers[$section_key] = array();
            }

            $this->section_renderers[$section_key][] = array(
                'priority' => (int) $priority,
                'renderer' => $renderer,
            );

            usort($this->section_renderers[$section_key], function ($left, $right) {
                return $left['priority'] <=> $right['priority'];
            });

            return $this;
        }

        public function render_lesson($lesson = null)
        {
            $schema = $this->coerce_schema($lesson !== null ? $lesson : $this->get_current_lesson());
            $sections = $schema->get_available_sections();
            $output = array();

            foreach ($sections as $section) {
                $output[] = $this->render_section($section, $schema->get_section($section));
            }

            return implode("\n", array_filter($output));
        }

        public function render_section($section_key, $value = null)
        {
            if (!is_string($section_key) || $section_key === '') {
                return '';
            }

            $renderer = $this->resolve_section_renderer($section_key);
            if ($renderer !== null) {
                return $renderer($this, $section_key, $value);
            }

            return $this->render_generic_section($section_key, $value);
        }

        public function render_title_section($section_key, $value)
        {
            return '<h1 class="mb-lesson-title">' . $this->escape($value) . '</h1>';
        }

        public function render_text_section($section_key, $value)
        {
            $label = $this->format_section_label($section_key);
            return '<section class="mb-lesson-section mb-lesson-section-' . $this->escape($section_key) . '"><h2 class="mb-lesson-section-title">' . $this->escape($label) . '</h2><div class="mb-lesson-section-content">' . $this->escape($value) . '</div></section>';
        }

        public function render_list_section($section_key, $value)
        {
            $label = $this->format_section_label($section_key);
            if (!is_array($value) || empty($value)) {
                return $this->render_text_section($section_key, $value);
            }

            $items = array();
            foreach ($value as $item) {
                $items[] = '<li>' . $this->render_list_item($item) . '</li>';
            }

            return '<section class="mb-lesson-section mb-lesson-section-' . $this->escape($section_key) . '"><h2 class="mb-lesson-section-title">' . $this->escape($label) . '</h2><ul class="mb-lesson-list">' . implode('', $items) . '</ul></section>';
        }

        public function render_generic_section($section_key, $value)
        {
            if (is_array($value)) {
                return $this->render_list_section($section_key, $value);
            }

            return $this->render_text_section($section_key, $value);
        }

        protected function resolve_section_renderer($section_key)
        {
            if (!isset($this->section_renderers[$section_key]) || empty($this->section_renderers[$section_key])) {
                return null;
            }

            $registered = end($this->section_renderers[$section_key]);
            return isset($registered['renderer']) ? $registered['renderer'] : null;
        }

        protected function register_default_renderers()
        {
            $this->register_section_renderer('title', array($this, 'render_title_section'));
            $this->register_section_renderer('overview', array($this, 'render_text_section'));
            $this->register_section_renderer('teach_it', array($this, 'render_text_section'));
            $this->register_section_renderer('at_a_glance', array($this, 'render_list_section'));
            $this->register_section_renderer('common_questions', array($this, 'render_list_section'));
            $this->register_section_renderer('watch_it', array($this, 'render_text_section'));
            $this->register_section_renderer('practice_it', array($this, 'render_list_section'));
            $this->register_section_renderer('my_math_notes', array($this, 'render_list_section'));
            $this->register_section_renderer('real_life_math', array($this, 'render_text_section'));
            $this->register_section_renderer('did_you_know', array($this, 'render_text_section'));
        }

        protected function render_list_item($item)
        {
            if (is_array($item)) {
                $sub_items = array();
                foreach ($item as $sub_item) {
                    $sub_items[] = '<li>' . $this->render_list_item($sub_item) . '</li>';
                }

                return '<ul>' . implode('', $sub_items) . '</ul>';
            }

            return $this->escape($item);
        }

        protected function format_section_label($section_key)
        {
            $labels = array(
                'title' => 'Title',
                'overview' => 'Overview',
                'teach_it' => 'Teach It',
                'at_a_glance' => 'At a Glance',
                'common_questions' => 'Common Questions',
                'watch_it' => 'Watch It',
                'practice_it' => 'Practice It',
                'my_math_notes' => 'My Math Notes',
                'real_life_math' => 'Real-Life Math',
                'did_you_know' => 'Did You Know',
            );

            return isset($labels[$section_key]) ? $labels[$section_key] : ucwords(str_replace('_', ' ', $section_key));
        }

        protected function escape($value)
        {
            return htmlspecialchars((string) $value, ENT_QUOTES, 'UTF-8');
        }

        protected function coerce_schema($lesson)
        {
            if ($lesson instanceof MathBinder_Lesson_Schema) {
                return $lesson;
            }

            if (is_array($lesson)) {
                return MathBinder_Lesson_Schema::from_array($lesson);
            }

            return MathBinder_Lesson_Schema::from_array(array());
        }

        protected function get_current_lesson()
        {
            return isset($this->lesson) ? $this->lesson : MathBinder_Lesson_Schema::from_array(array());
        }
    }
}
