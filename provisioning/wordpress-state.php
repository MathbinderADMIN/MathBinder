<?php
/**
 * Immutable representation of normalized WordPress object state.
 */

defined('ABSPATH') || exit;

class MathBinder_WordPress_State {
    /** @var bool */
    private $exists;

    /** @var int */
    private $object_id;

    /** @var string */
    private $object_type;

    /** @var string */
    private $slug;

    /** @var string */
    private $title;

    /** @var array */
    private $meta;

    /** @var array */
    private $taxonomy_terms;

    /**
     * @param mixed $exists
     * @param mixed $object_id
     * @param mixed $object_type
     * @param mixed $slug
     * @param mixed $title
     * @param mixed $meta
     * @param mixed $taxonomy_terms
     */
    public function __construct($exists, $object_id, $object_type, $slug, $title, $meta, $taxonomy_terms) {
        $this->exists = (bool) $exists;
        $this->object_id = is_int($object_id) ? $object_id : (int) $object_id;
        $this->object_type = (string) $object_type;
        $this->slug = (string) $slug;
        $this->title = (string) $title;
        $this->meta = is_array($meta) ? $meta : array();
        $this->taxonomy_terms = is_array($taxonomy_terms) ? $taxonomy_terms : array();
    }

    /**
     * @return bool
     */
    public function get_exists() {
        return $this->exists;
    }

    /**
     * @return int
     */
    public function get_object_id() {
        return $this->object_id;
    }

    /**
     * @return string
     */
    public function get_object_type() {
        return $this->object_type;
    }

    /**
     * @return string
     */
    public function get_slug() {
        return $this->slug;
    }

    /**
     * @return string
     */
    public function get_title() {
        return $this->title;
    }

    /**
     * @return array
     */
    public function get_meta() {
        return $this->meta;
    }

    /**
     * @return array
     */
    public function get_taxonomy_terms() {
        return $this->taxonomy_terms;
    }

    /**
     * @return array<string, mixed>
     */
    public function to_array() {
        return array(
            'exists' => $this->exists,
            'object_id' => $this->object_id,
            'object_type' => $this->object_type,
            'slug' => $this->slug,
            'title' => $this->title,
            'meta' => $this->meta,
            'taxonomy_terms' => $this->taxonomy_terms,
        );
    }

    /**
     * @return string|false
     */
    public function to_json() {
        return json_encode($this->to_array());
    }
}
