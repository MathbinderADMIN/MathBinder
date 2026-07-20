<?php

if (!class_exists('MathBinder_Lesson_Schema')) {
    class MathBinder_Lesson_Schema
    {
        protected $data = array();
        protected $supported_sections = array(
            'title',
            'overview',
            'teach_it',
            'at_a_glance',
            'common_questions',
            'watch_it',
            'practice_it',
            'my_math_notes',
            'real_life_math',
            'did_you_know',
        );

        public function __construct(array $data = array())
        {
            $this->data = $this->normalize_data($data);
        }

        public static function from_array(array $data = array())
        {
            return new self($data);
        }

        public function to_array()
        {
            return $this->data;
        }

        public function get_supported_sections()
        {
            return $this->supported_sections;
        }

        public function set_section($section, $value)
        {
            if (!is_string($section) || $section === '') {
                return $this;
            }

            $this->data[$section] = $this->normalize_value($value);
            return $this;
        }

        public function get_section($section, $default = null)
        {
            if (!is_string($section)) {
                return $default;
            }

            return array_key_exists($section, $this->data) ? $this->data[$section] : $default;
        }

        public function has_section($section)
        {
            return is_string($section) && array_key_exists($section, $this->data) && $this->has_content($this->data[$section]);
        }

        public function get_sections()
        {
            $sections = array();
            foreach ($this->supported_sections as $section) {
                if (array_key_exists($section, $this->data)) {
                    $sections[$section] = $this->data[$section];
                }
            }

            return $sections;
        }

        public function get_available_sections()
        {
            $sections = array();
            foreach ($this->supported_sections as $section) {
                if ($this->has_content($this->get_section($section))) {
                    $sections[] = $section;
                }
            }

            return $sections;
        }

        public function normalize_data(array $data)
        {
            $normalized = array();
            foreach ($data as $key => $value) {
                if (is_string($key)) {
                    $normalized[$key] = $this->normalize_value($value);
                }
            }

            foreach ($this->supported_sections as $section) {
                if (!array_key_exists($section, $normalized)) {
                    continue;
                }

                $normalized[$section] = $this->normalize_value($normalized[$section]);
            }

            return $normalized;
        }

        protected function normalize_value($value)
        {
            if ($value === null) {
                return '';
            }

            if (is_string($value)) {
                return trim($value);
            }

            if (is_array($value)) {
                $normalized = array();
                foreach ($value as $key => $item) {
                    if (is_string($key)) {
                        $normalized[$key] = $this->normalize_value($item);
                        continue;
                    }

                    $normalized[] = $this->normalize_value($item);
                }

                return $normalized;
            }

            if (is_scalar($value)) {
                return (string) $value;
            }

            return '';
        }

        protected function has_content($value)
        {
            if (is_array($value)) {
                return !empty($value);
            }

            return is_string($value) ? trim($value) !== '' : false;
        }
    }
}
