<?php

require_once __DIR__ . '/helpers.php';
require_once __DIR__ . '/video-library.php';
require_once __DIR__ . '/registry.php';

if (!class_exists('MathBinder_Content_Engine')) {
    class MathBinder_Content_Engine
    {
        protected $content_dir;
        protected $video_library;

        public function __construct($content_dir = null)
        {
            $this->content_dir = $content_dir ? $content_dir : mathbinder_content_engine_content_dir();
            $this->video_library = new MathBinder_Content_Video_Library();
        }

        public function discover_lessons($content_dir = null)
        {
            $result = $this->discover_lessons_with_report($content_dir);
            return isset($result['lessons']) ? $result['lessons'] : array();
        }

        public function discover_lessons_with_report($content_dir = null)
        {
            $directory = $this->resolve_content_directory($content_dir);
            if ($directory === false) {
                return array(
                    'lessons' => array(),
                    'duplicates' => array(),
                    'errors' => array(
                        'The requested content directory is outside the official MathBinder content directory.'
                    ),
                    'directory' => null,
                );
            }

            if (!is_dir($directory)) {
                return array(
                    'lessons' => array(),
                    'duplicates' => array(),
                    'errors' => array(),
                    'directory' => $directory,
                );
            }

            $entries = scandir($directory);
            if (!is_array($entries)) {
                return array(
                    'lessons' => array(),
                    'duplicates' => array(),
                    'errors' => array(),
                    'directory' => $directory,
                );
            }

            $lessons = array();
            $duplicates = array();
            $errors = array();

            foreach ($entries as $entry) {
                if ($entry === '.' || $entry === '..' || strpos($entry, '.') === 0) {
                    continue;
                }

                $path = $directory . DIRECTORY_SEPARATOR . $entry;
                if (!is_file($path)) {
                    continue;
                }

                $extension = pathinfo($entry, PATHINFO_EXTENSION);
                if ($extension !== 'php') {
                    continue;
                }

                $resolved_file = $this->resolve_lesson_file_path($path);
                if ($resolved_file === false) {
                    $errors[] = sprintf('Skipped path outside the official content directory: %s', $path);
                    continue;
                }

                $slug = $this->slugify_basename($resolved_file);
                $lesson_data = $this->load_lesson_file($resolved_file);
                if (!is_array($lesson_data)) {
                    $errors[] = sprintf('Skipped invalid lesson definition: %s', $resolved_file);
                    continue;
                }

                if (isset($lessons[$slug])) {
                    $duplicates[] = array(
                        'slug' => $slug,
                        'first_file' => $lessons[$slug]['file'],
                        'duplicate_file' => $resolved_file,
                        'message' => sprintf('Skipped duplicate lesson slug "%s".', $slug),
                    );
                    $errors[] = sprintf('Skipped duplicate lesson slug "%s" from %s.', $slug, $resolved_file);
                    continue;
                }

                $lessons[$slug] = array(
                    'slug' => $slug,
                    'file' => $resolved_file,
                    'data' => $lesson_data,
                    'version' => $this->get_lesson_version($lesson_data),
                );
            }

            ksort($lessons);
            return array(
                'lessons' => $lessons,
                'duplicates' => $duplicates,
                'errors' => $errors,
                'directory' => $directory,
            );
        }

        public function load_lesson_file($file_path)
        {
            $resolved_file = $this->resolve_lesson_file_path($file_path);
            if ($resolved_file === false || !is_file($resolved_file)) {
                return array();
            }

            $lesson = require $resolved_file;
            return is_array($lesson) ? $lesson : array();
        }

        public function get_lesson($slug, $content_dir = null)
        {
            $lessons = $this->discover_lessons($content_dir);
            return isset($lessons[$slug]) ? $lessons[$slug] : null;
        }

        public function validate_lesson($lesson, $slug = null)
        {
            $payload = $this->lesson_payload($lesson);
            $errors = array();
            $required_keys = array('title', 'overview', 'teach_it', 'watch_it', 'practice_it', 'notes', 'real_life_math', 'did_you_know', 'certification');

            foreach ($required_keys as $key) {
                if (!array_key_exists($key, $payload)) {
                    $errors[] = sprintf('Missing required key "%s".', $key);
                }
            }

            if (isset($payload['title']) && !is_string($payload['title'])) {
                $errors[] = 'The title must be a string.';
            }

            if (isset($payload['overview']) && !is_string($payload['overview'])) {
                $errors[] = 'The overview must be a string.';
            }

            if (isset($payload['teach_it']) && !is_string($payload['teach_it'])) {
                $errors[] = 'The teach_it field must be a string.';
            }

            if (isset($payload['watch_it']) && !is_string($payload['watch_it'])) {
                $errors[] = 'The watch_it field must be a string.';
            }

            if (isset($payload['practice_it']) && !is_array($payload['practice_it'])) {
                $errors[] = 'The practice_it field must be an array.';
            }

            if (isset($payload['notes']) && !is_array($payload['notes'])) {
                $errors[] = 'The notes field must be an array.';
            }

            if (isset($payload['certification']) && !is_array($payload['certification'])) {
                $errors[] = 'The certification field must be an array.';
            }

            return array(
                'valid' => empty($errors),
                'errors' => $errors,
                'slug' => $slug,
            );
        }

        public function get_lesson_version($lesson)
        {
            $payload = $this->lesson_payload($lesson);
            if (isset($payload['version'])) {
                return $payload['version'];
            }

            if (isset($payload['schema_version'])) {
                return $payload['schema_version'];
            }

            return '1.0.0';
        }

        public function version_is_compatible($lesson, $target_version = '1.0.0')
        {
            return version_compare($this->get_lesson_version($lesson), $target_version, '>=');
        }

        public function needs_migration($lesson, $target_version = '1.0.0')
        {
            return version_compare($this->get_lesson_version($lesson), $target_version, '<');
        }

        public function migrate_lesson($lesson, $target_version = '1.0.0', $overwrite = false)
        {
            $payload = $this->lesson_payload($lesson);
            $migrated = $payload;

            if ($overwrite || !isset($migrated['version'])) {
                $migrated['version'] = $target_version;
            }

            if ($overwrite || !isset($migrated['practice_it']) || $this->is_missing_or_empty($migrated['practice_it'])) {
                $migrated['practice_it'] = array();
            }

            if ($overwrite || !isset($migrated['notes']) || $this->is_missing_or_empty($migrated['notes'])) {
                $migrated['notes'] = array();
            }

            if ($overwrite || !isset($migrated['certification']) || $this->is_missing_or_empty($migrated['certification'])) {
                $migrated['certification'] = array();
            }

            return $migrated;
        }

        public function safe_update_lesson($existing_lesson, $incoming_lesson, $overwrite = false)
        {
            $merged = is_array($existing_lesson) ? $existing_lesson : array();
            $incoming = is_array($incoming_lesson) ? $incoming_lesson : array();

            foreach ($incoming as $key => $value) {
                if ($this->should_preserve_existing_value($merged, $key, $value, $overwrite)) {
                    continue;
                }

                if (is_array($value) && isset($merged[$key]) && is_array($merged[$key])) {
                    $merged[$key] = $this->safe_update_lesson($merged[$key], $value, $overwrite);
                    continue;
                }

                $merged[$key] = $value;
            }

            return $merged;
        }

        public function preserve_existing_content($existing_lesson, $incoming_lesson, $overwrite = false)
        {
            return $this->safe_update_lesson($existing_lesson, $incoming_lesson, $overwrite);
        }

        public function load_video_resource($key)
        {
            return $this->video_library->get_video($key);
        }

        public function get_video_library()
        {
            return $this->video_library->get_library();
        }

        protected function lesson_payload($lesson)
        {
            if (isset($lesson['data']) && is_array($lesson['data'])) {
                return $lesson['data'];
            }

            return is_array($lesson) ? $lesson : array();
        }

        protected function should_preserve_existing_value($existing_lesson, $key, $value, $overwrite = false)
        {
            if ($overwrite) {
                return false;
            }

            if (!is_array($existing_lesson) || !array_key_exists($key, $existing_lesson)) {
                return false;
            }

            if ($this->is_missing_or_empty($existing_lesson[$key])) {
                return false;
            }

            return true;
        }

        protected function is_missing_or_empty($value)
        {
            if ($value === null) {
                return true;
            }

            if (is_string($value)) {
                return trim($value) === '';
            }

            if (is_array($value)) {
                return empty($value);
            }

            return false;
        }

        protected function resolve_content_directory($content_dir = null)
        {
            $official_directory = $this->normalize_path(mathbinder_content_engine_content_dir());
            $candidate_directory = $content_dir === null ? $official_directory : $this->normalize_path($content_dir);

            if ($candidate_directory === '') {
                return $official_directory;
            }

            $candidate_real = $this->real_path($candidate_directory);
            $official_real = $this->real_path($official_directory);

            if ($official_real === false) {
                return $candidate_real === false ? false : $candidate_real;
            }

            if ($candidate_real === false) {
                return false;
            }

            if ($this->is_path_within_directory($candidate_real, $official_real)) {
                return $candidate_real;
            }

            return false;
        }

        protected function resolve_lesson_file_path($file_path)
        {
            $candidate = $this->normalize_path($file_path);
            if ($candidate === '') {
                return false;
            }

            $resolved = $this->real_path($candidate);
            if ($resolved === false) {
                return false;
            }

            $official_directory = $this->real_path(mathbinder_content_engine_content_dir());
            if ($official_directory === false) {
                return false;
            }

            if (!$this->is_path_within_directory($resolved, $official_directory)) {
                return false;
            }

            return $resolved;
        }

        protected function real_path($path)
        {
            $normalized = $this->normalize_path($path);
            if ($normalized === '') {
                return false;
            }

            $resolved = realpath($normalized);
            return $resolved === false ? false : $this->normalize_path($resolved);
        }

        protected function normalize_path($path)
        {
            if (!is_string($path)) {
                return '';
            }

            $normalized = str_replace('\\', '/', $path);
            $normalized = preg_replace('#/+#', '/', $normalized);
            if ($normalized === null) {
                return '';
            }

            return rtrim($normalized, '/');
        }

        protected function is_path_within_directory($path, $directory)
        {
            $path = $this->normalize_path($path);
            $directory = $this->normalize_path($directory);

            if ($path === '' || $directory === '') {
                return false;
            }

            if ($path === $directory) {
                return true;
            }

            return strpos($path, $directory . '/') === 0;
        }

        protected function slugify_basename($file_path)
        {
            $basename = basename($file_path, '.php');
            return str_replace('_', '-', strtolower($basename));
        }
    }
}

if (!function_exists('mathbinder_content_engine')) {
    function mathbinder_content_engine()
    {
        static $engine = null;
        if ($engine === null) {
            $engine = new MathBinder_Content_Engine();
        }
        return $engine;
    }
}
