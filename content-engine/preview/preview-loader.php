<?php

if (!class_exists('MathBinder_Preview_Loader')) {
    class MathBinder_Preview_Loader
    {
        protected $approved_content_dir;
        protected $schema_file;
        protected $renderer_file;

        public function __construct()
        {
            $this->approved_content_dir = $this->resolve_approved_content_dir();
            $this->schema_file = dirname(__DIR__) . '/lesson-schema.php';
            $this->renderer_file = dirname(__DIR__) . '/lesson-renderer.php';
        }

        public function resolve_lesson_file($lesson_param = null)
        {
            $normalized_name = $this->normalize_lesson_name($lesson_param);
            if ($normalized_name === null) {
                return null;
            }

            $approved_dir = $this->approved_content_dir;
            if ($approved_dir === null) {
                return null;
            }

            $candidate = $approved_dir . DIRECTORY_SEPARATOR . $normalized_name;
            $resolved = realpath($candidate);
            if ($resolved === false || !is_file($resolved) || !is_readable($resolved)) {
                return null;
            }

            $base_dir = realpath($approved_dir);
            if ($base_dir === false) {
                return null;
            }

            if ($resolved !== $base_dir && strpos($resolved, $base_dir . DIRECTORY_SEPARATOR) !== 0) {
                return null;
            }

            return $resolved;
        }

        public function render_preview($lesson_param = null)
        {
            $lesson_path = $this->resolve_lesson_file($lesson_param);
            if ($lesson_path === null) {
                return $this->build_error_result('The requested lesson could not be found. Please choose a valid lesson preview.');
            }

            try {
                set_error_handler(function ($severity, $message, $file, $line) {
                    throw new ErrorException($message, 0, $severity, $file, $line);
                });

                require_once $this->schema_file;
                require_once $this->renderer_file;

                if (!class_exists('MathBinder_Lesson_Schema') || !class_exists('MathBinder_Lesson_Renderer')) {
                    return $this->build_error_result('The preview environment is missing required lesson components.');
                }

                $lesson_data = require $lesson_path;
                if (!is_array($lesson_data)) {
                    return $this->build_error_result('The lesson data is invalid and could not be previewed.');
                }

                $schema = new MathBinder_Lesson_Schema($lesson_data);
                $renderer = new MathBinder_Lesson_Renderer($schema);
                $html = $renderer->render_lesson($schema);

                return array(
                    'ok' => true,
                    'lesson_title' => $this->coerce_display_value(isset($lesson_data['title']) ? $lesson_data['title'] : null, basename($lesson_path, '.php')),
                    'lesson_id' => $this->coerce_display_value(isset($lesson_data['lesson_id']) ? $lesson_data['lesson_id'] : null, 'Not available'),
                    'lesson_file' => basename($lesson_path),
                    'html' => $html,
                    'error_message' => '',
                );
            } catch (Throwable $exception) {
                return $this->build_error_result('The lesson could not be previewed right now. Please try another lesson.');
            } finally {
                restore_error_handler();
            }
        }

        protected function normalize_lesson_name($lesson_param)
        {
            if ($lesson_param === null) {
                return 'number-operations-production.php';
            }

            if (!is_string($lesson_param)) {
                return null;
            }

            $value = trim($lesson_param);
            if ($value === '') {
                return 'number-operations-production.php';
            }

            if (strpos($value, '/') !== false || strpos($value, '\\') !== false || strpos($value, ':') !== false || strpos($value, '://') !== false || strpos($value, '..') !== false) {
                return null;
            }

            if (preg_match('/^[a-z0-9-]+$/', $value) === 1) {
                return $value . '.php';
            }

            if (preg_match('/^[a-z0-9-]+\.php$/', $value) === 1) {
                return $value;
            }

            return null;
        }

        protected function resolve_approved_content_dir()
        {
            $path = realpath(dirname(__DIR__) . '/..' . DIRECTORY_SEPARATOR . 'content');
            return $path !== false ? $path : null;
        }

        protected function build_error_result($message)
        {
            return array(
                'ok' => false,
                'lesson_title' => 'Preview unavailable',
                'lesson_id' => 'Not available',
                'lesson_file' => '',
                'html' => '',
                'error_message' => $message,
            );
        }

        protected function coerce_display_value($value, $fallback)
        {
            if (is_string($value)) {
                $value = trim($value);
                return $value !== '' ? $value : $fallback;
            }

            if (is_int($value) || is_float($value)) {
                return (string) $value;
            }

            return $fallback;
        }
    }
}

if (!function_exists('mathbinder_preview_render')) {
    function mathbinder_preview_render($lesson_param = null)
    {
        $loader = new MathBinder_Preview_Loader();
        return $loader->render_preview($lesson_param);
    }
}
