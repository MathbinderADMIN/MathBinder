I’m gathering the plugin’s code and asset references so I can produce a complete architecture map without changing anything.

Searched for files matching `MathBinder` · `*.php`, 4 matches

Searched for files matching `MathBinder` · `*.css`, 2 matches

Searched for files matching `MathBinder` · `*.js`, 2 matches

Read [](file:///c%3A/MathBinder)

I’ve identified the main files and I’m pulling the remaining references so the map includes the custom hooks, fields, and reusable methods accurately.

Searched for regex `_mb_[A-Za-z0-9_]+|register_post_type\(|register_taxonomy\(|add_shortcode\(|add_submenu_page\(|add_meta_box\(|wp_ajax_|admin_post_|template_include|wp_enqueue|render_|public function|private function` (`**/*.php`), 190 results

Read [](file:///c%3A/MathBinder/mathbinder-core.php#2300-2300), lines 2300 to 2394

## MathBinder architecture map

This plugin is organized as a single WordPress plugin core with custom content types, admin workflows, template-driven front-end rendering, and a small set of assets.

### 1) PHP files

- Core plugin entry point: mathbinder-core.php
  - Registers the plugin, custom post type, taxonomy, hooks, admin pages, meta boxes, shortcodes, AJAX handlers, rendering helpers, and upgrade logic.
- Content-pack bootstrap: mathbinder-content-pack-001-2.php
  - Populates sample lesson content metadata for a packaged curriculum item.
- Single-page template: single-mb_binder_page.php
  - Renders the full public Binder Page experience.
- Section archive template: taxonomy-mb_binder_section.php
  - Renders the Binder Section landing/archive view.

### 2) CSS files

- Front-end styles: mathbinder.css
- Admin styles: mathbinder-admin.css

### 3) JavaScript files

- Front-end behaviors: mathbinder-front.js
  - Search, reveal-on-scroll, footer injection, and interactive UI behaviors.
- Admin behaviors: mathbinder-admin.js
  - Media picker integration for admin-side file selection.

### 4) Image assets

- mathbinder-binder-only.png
- mathbinder-binder-scene-v82.png
- mathbinder-binder-scene-v84.png
- mathbinder-binder-scene-v85.png
- mathbinder-binder-scene-v86.png
- mathbinder-binder-scene-v90.png
- mathbinder-binder-scene-v92.png
- mathbinder-binder-scene-v923.png
- mathbinder-binder-scene-v94.png
- mathbinder-binder-scene.png
- mathbinder-homepage-hero.png
- mathbinder-icon.svg
- mathbinder-logo.svg
- mathbinder-open-page-only.png

---

## Content model

### 5) Custom post types

- `mb_binder_page`
  - Defined in mathbinder-core.php
  - Represents a Binder Page lesson/topic.

### 6) Taxonomies

- `mb_binder_section`
  - Defined in mathbinder-core.php
  - Groups Binder Pages into sections such as The Number System and Algebraic Expressions.

### 7) Shortcodes

Defined in mathbinder-core.php:

- `mathbinder_topics`
- `mathbinder_home`
- `mathbinder_progress`
- `mathbinder_collection`

### 8) Templates

- Public single lesson page: single-mb_binder_page.php
- Section archive page: taxonomy-mb_binder_section.php

---

## Admin and request handling

### 9) AJAX handler

- `mb_topic_search`
  - Registered in mathbinder-core.php
  - Executed by `ajax_topic_search()`
  - Used by the front-end search UI in mathbinder-front.js

### 10) Admin pages

Custom admin pages added by the plugin:

- Quick Add
  - Rendered by `render_quick_add_page()`
  - Action: `mb_quick_add`
- Lesson Builder
  - Rendered by `render_lesson_builder_page()`
  - Actions:
    - `mb_lesson_builder_create`
    - `mb_clone_lesson`
    - `mb_update_lesson_status`
    - `mb_gold_certify`

The plugin also uses the built-in WordPress admin screens for the custom post type and taxonomy.

---

## Custom meta fields

These are stored as post meta using keys beginning with `_mb_`.

### Content and lesson structure
- `_mb_subtitle`
- `_mb_essential_question`
- `_mb_introduction`
- `_mb_learning_targets`
- `_mb_vocabulary`
- `_mb_worked_examples`
- `_mb_real_life`
- `_mb_difficulty`
- `_mb_estimated_time`
- `_mb_prerequisites`

### Practice and interactivity
- `_mb_learn_checks`
- `_mb_common_questions`
- `_mb_common_mistakes`
- `_mb_video_chapters`
- `_mb_watch_vocabulary`
- `_mb_pause_prompts`
- `_mb_video_transcript`
- `_mb_practice_warmup`
- `_mb_guided_practice`
- `_mb_independent_practice`
- `_mb_challenge_practice`
- `_mb_real_world_practice`
- `_mb_mastery_questions`

### Resources and media
- `_mb_videos`
- `_mb_ixl`
- `_mb_khan`
- `_mb_delta`
- `_mb_desmos`
- `_mb_other_resources`
- `_mb_printable_pdf`
- `_mb_interactive_version`
- `_mb_answer_key`

### Parent and teacher support
- `_mb_parent_summary`
- `_mb_parent_conversation`
- `_mb_parent_mistakes`
- `_mb_parent_five_minute`
- `_mb_parent_activity`
- `_mb_parent_help`
- `_mb_master_it`
- `_mb_related_topics`
- `_mb_teacher_objectives`
- `_mb_teacher_pacing`
- `_mb_teacher_materials`
- `_mb_teacher_misconceptions`
- `_mb_teacher_differentiation`
- `_mb_teacher_small_group`
- `_mb_teacher_formative`
- `_mb_teacher_connections`
- `_mb_teacher_extensions`
- `_mb_teacher_notes`
- `_mb_standards`

### Workflow and certification
- `_mb_lesson_status`
- `_mb_gold_certification`
- `_mb_gold_certification_missing`
- `_mb_gold_certification_date`
- `_mb_gold_certification_percent`
- `_mb_template_version`

### Internal and bootstrap fields
- `_mb_homepage_shortcode_ready`
- `_mb_number` (stored on taxonomy terms, not posts)

---

## Reusable functions and helpers

These are the main reusable methods in mathbinder-core.php, grouped by responsibility.

### Registration and lifecycle
- `__construct()`
- `register_content_types()`
- `add_quick_add_page()`
- `body_classes()`
- `maybe_upgrade()`
- `activate()`

### Admin workflow and form handling
- `render_quick_add_page()`
- `handle_quick_add()`
- `render_lesson_builder_page()`
- `handle_lesson_builder_create()`
- `handle_clone_lesson()`
- `handle_update_lesson_status()`
- `handle_gold_certify()`
- `admin_notice()`

### Lesson-builder data and status
- `lesson_builder_required_fields()`
- `lesson_builder_placeholders()`
- `lesson_builder_groups()`
- `lesson_field_is_complete()`
- `lesson_completion_data()`
- `curriculum_dashboard_data()`

### Meta box and editor helpers
- `add_meta_boxes()`
- `field()`
- `textarea()`
- `input()`
- `select()`
- `media_input()`
- `render_overview_box()`
- `render_teach_box()`
- `render_resources_box()`
- `render_downloads_box()`
- `render_support_box()`
- `render_teacher_box()`
- `render_gold_certification_box()`
- `render_checklist_box()`
- `save_meta()`

### Front-end rendering helpers
- `enqueue_frontend_assets()`
- `enqueue_admin_assets()`
- `load_single_template()`
- `lines()`
- `render_list()`
- `parse_resources()`
- `render_resource_cards()`
- `render_videos()`
- `render_common_questions()`
- `render_interactive_vocabulary()`
- `render_step_examples()`
- `render_misconception_cards()`
- `render_learn_checks()`
- `render_video_chapters()`
- `render_watch_vocab()`
- `render_pause_prompts()`
- `render_practice_items()`
- `section_toggle()`
- `get_section_pages()`
- `render_support_cards()`
- `render_mastery_questions()`
- `get_adjacent_topic()`

### Shortcodes and page output
- `homepage_shortcode()`
- `progress_shortcode()`
- `collection_shortcode()`
- `topics_shortcode()`

### Search and admin list behavior
- `ajax_topic_search()`
- `columns()`
- `column_content()`

### Content setup and migration
- `ensure_sections()`
- `topic_preset()`
- `migrate_place_value()`

---

## Overall architecture in one sentence

The plugin is a meta-driven WordPress content system: it defines Binder Pages and Binder Sections, stores lesson content in custom post meta, renders those values through custom templates and shortcodes, and adds admin workflows for creation, certification, and lesson management.