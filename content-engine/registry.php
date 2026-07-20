<?php

if (!class_exists('MathBinder_Content_Registry')) {
    class MathBinder_Content_Registry
    {
        protected $engine;

        public function __construct($engine = null)
        {
            $this->engine = $engine instanceof MathBinder_Content_Engine ? $engine : new MathBinder_Content_Engine();
        }

        public function discover_lessons($content_dir = null)
        {
            return $this->engine->discover_lessons($content_dir);
        }

        public function discover_lessons_with_report($content_dir = null)
        {
            return $this->engine->discover_lessons_with_report($content_dir);
        }

        public function validate_lessons($content_dir = null)
        {
            $lessons = $this->discover_lessons($content_dir);
            $results = array();

            foreach ($lessons as $slug => $lesson) {
                $results[$slug] = $this->engine->validate_lesson($lesson, $slug);
            }

            return $results;
        }

        public function get_manifest($content_dir = null)
        {
            $lessons = $this->discover_lessons($content_dir);
            $manifest = array();

            foreach ($lessons as $slug => $lesson) {
                $manifest[$slug] = array(
                    'slug' => $slug,
                    'title' => isset($lesson['data']['title']) ? $lesson['data']['title'] : $slug,
                    'version' => $this->engine->get_lesson_version($lesson),
                    'file' => isset($lesson['file']) ? $lesson['file'] : null,
                );
            }

            return $manifest;
        }
    }
}

if (!function_exists('mathbinder_content_registry')) {
    function mathbinder_content_registry()
    {
        static $registry = null;
        if ($registry === null) {
            $registry = new MathBinder_Content_Registry();
        }
        return $registry;
    }
}
