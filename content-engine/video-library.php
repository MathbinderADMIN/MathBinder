<?php

if (!class_exists('MathBinder_Content_Video_Library')) {
    class MathBinder_Content_Video_Library
    {
        public function get_library()
        {
            return array(
                'place_value' => array(
                    'key' => 'place_value',
                    'title' => 'Place Value Foundations',
                    'source' => 'local',
                    'url' => 'https://example.org/videos/place-value.mp4',
                    'poster' => 'https://example.org/images/place-value.jpg',
                    'length' => '04:12',
                    'description' => 'Introduces place value through visual grouping and expanded notation.'
                ),
                'number_operations' => array(
                    'key' => 'number_operations',
                    'title' => 'Number Operations Essentials',
                    'source' => 'local',
                    'url' => 'https://example.org/videos/number-operations.mp4',
                    'poster' => 'https://example.org/images/number-operations.jpg',
                    'length' => '05:03',
                    'description' => 'Builds confidence with addition, subtraction, multiplication, and division strategies.'
                )
            );
        }

        public function get_video($key)
        {
            $library = $this->get_library();
            return isset($library[$key]) ? $library[$key] : null;
        }

        public function has_video($key)
        {
            return $this->get_video($key) !== null;
        }
    }
}

if (!function_exists('mathbinder_content_engine_video_library')) {
    function mathbinder_content_engine_video_library()
    {
        static $library = null;
        if ($library === null) {
            $library = new MathBinder_Content_Video_Library();
        }
        return $library;
    }
}

if (!function_exists('mathbinder_content_engine_load_video_resource')) {
    function mathbinder_content_engine_load_video_resource($key)
    {
        return mathbinder_content_engine_video_library()->get_video($key);
    }
}
