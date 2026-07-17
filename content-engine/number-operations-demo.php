<?php
/**
 * Development proof-only demo.
 * This file safely loads the lesson schema, renderer, and production lesson data.
 * It returns rendered HTML instead of printing anything automatically.
 */

if (!function_exists('mathbinder_number_operations_demo_render')) {
    function mathbinder_number_operations_demo_render()
    {
        $lesson_file = __DIR__ . '/../content/number-operations-production.php';
        $schema_file = __DIR__ . '/lesson-schema.php';
        $renderer_file = __DIR__ . '/lesson-renderer.php';

        if (!is_file($lesson_file) || !is_file($schema_file) || !is_file($renderer_file)) {
            return '';
        }

        require_once $schema_file;
        require_once $renderer_file;

        if (!class_exists('MathBinder_Lesson_Schema') || !class_exists('MathBinder_Lesson_Renderer')) {
            return '';
        }

        $lesson_data = require $lesson_file;
        if (!is_array($lesson_data)) {
            return '';
        }

        try {
            $schema = new MathBinder_Lesson_Schema($lesson_data);
            $renderer = new MathBinder_Lesson_Renderer($schema);
            return $renderer->render_lesson($schema);
        } catch (Throwable $exception) {
            return '';
        }
    }
}

if (!function_exists('mathbinder_number_operations_demo_get_html')) {
    function mathbinder_number_operations_demo_get_html()
    {
        static $html = null;

        if ($html === null) {
            $html = mathbinder_number_operations_demo_render();
        }

        return $html;
    }
}
