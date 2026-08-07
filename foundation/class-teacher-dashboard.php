<?php
if (!defined('ABSPATH')) exit;

final class MathBinder_Teacher_Dashboard {
    const SHORTCODE = 'mathbinder_teacher_dashboard';
    const PAGE_SLUG = 'teacher-dashboard';

    public static function register() {
        add_shortcode(self::SHORTCODE, [__CLASS__, 'shortcode']);
        add_action('wp_enqueue_scripts', [__CLASS__, 'enqueue_assets'], 30);
        add_action('admin_post_mb_teacher_evidence_review', [__CLASS__, 'handle_evidence_review']);
        add_action('admin_post_mb_teacher_mastery_path', [__CLASS__, 'handle_mastery_path']);
        add_action('admin_post_mb_teacher_canvas_prepare', [__CLASS__, 'handle_canvas_prepare']);
        add_action('admin_post_mb_teacher_progress_export', [__CLASS__, 'handle_progress_export']);
        add_action('admin_post_mb_teacher_create_class', [__CLASS__, 'handle_create_class']);
        add_action('admin_post_mb_teacher_invite_student', [__CLASS__, 'handle_invite_student']);
        add_action('admin_post_mb_teacher_class_status', [__CLASS__, 'handle_class_status']);
        add_action('wp_ajax_mb_generate_mastery_path', [__CLASS__, 'ajax_generate_mastery_path']);
    }

    public static function ensure_page() {
        $page = get_page_by_path(self::PAGE_SLUG, OBJECT, 'page');
        $data = ['post_type'=>'page','post_status'=>'publish','post_title'=>'Teacher Dashboard','post_name'=>self::PAGE_SLUG,'post_content'=>'['.self::SHORTCODE.']'];
        if ($page) $data['ID'] = $page->ID;
        wp_insert_post($data);
    }

    public static function enqueue_assets() {
        if (is_page(self::PAGE_SLUG)) wp_enqueue_style('mathbinder-teacher-dashboard', plugins_url('assets/teacher-dashboard.css', __FILE__), [], MathBinder_Core::VERSION);
        if (is_page(self::PAGE_SLUG)) wp_enqueue_style('mathbinder-teacher-evidence-review', plugins_url('assets/teacher-evidence-review.css', __FILE__), ['mathbinder-teacher-dashboard'], MathBinder_Core::VERSION);
        if (is_page(self::PAGE_SLUG)) wp_enqueue_style('mathbinder-teacher-mastery-paths', plugins_url('assets/teacher-mastery-paths.css', __FILE__), ['mathbinder-teacher-dashboard'], MathBinder_Core::VERSION);
        if (is_page(self::PAGE_SLUG)) {
            wp_enqueue_script('mathbinder-teacher-mastery-paths', plugins_url('assets/teacher-mastery-paths.js', __FILE__), [], MathBinder_Core::VERSION, true);
            wp_localize_script('mathbinder-teacher-mastery-paths', 'MathBinderMasteryAI', [
                'ajaxUrl' => admin_url('admin-ajax.php'),
                'nonce' => wp_create_nonce('mb_generate_mastery_path'),
            ]);
        }
        if (is_page(self::PAGE_SLUG)) wp_enqueue_style('mathbinder-teacher-canvas', plugins_url('assets/teacher-canvas.css', __FILE__), ['mathbinder-teacher-dashboard'], MathBinder_Core::VERSION);
        if (is_page(self::PAGE_SLUG)) wp_enqueue_script('mathbinder-teacher-dashboard', plugins_url('assets/teacher-dashboard.js', __FILE__), [], MathBinder_Core::VERSION, true);
        if (is_page(self::PAGE_SLUG)) wp_enqueue_style('mathbinder-teacher-dashboard-complete', plugins_url('assets/teacher-dashboard-complete.css', __FILE__), ['mathbinder-teacher-dashboard'], MathBinder_Core::VERSION);
        if (is_page('evidence-folder')) wp_enqueue_style('mathbinder-student-teacher-review', plugins_url('assets/student-teacher-review.css', __FILE__), [], MathBinder_Core::VERSION);
    }

    private static function can_view() {
        $user = wp_get_current_user();
        return $user->exists() && (in_array('mb_teacher', (array)$user->roles, true) || in_array('mb_school_admin', (array)$user->roles, true) || user_can($user, 'manage_options'));
    }

    private static function classes($teacher_id) {
        global $wpdb;
        if (user_can($teacher_id, 'manage_options') || user_can($teacher_id, MathBinder_Capabilities::MANAGE_ORGANIZATIONS)) {
            return $wpdb->get_results("SELECT * FROM {$wpdb->prefix}mb_classes WHERE status='active' ORDER BY name,section_name", ARRAY_A) ?: [];
        }
        return $wpdb->get_results($wpdb->prepare("SELECT DISTINCT c.* FROM {$wpdb->prefix}mb_classes c LEFT JOIN {$wpdb->prefix}mb_enrollments e ON e.class_id=c.id AND e.user_id=%d AND e.role_key='teacher' AND e.status='active' WHERE c.status='active' AND (c.teacher_user_id=%d OR e.id IS NOT NULL) ORDER BY c.name,c.section_name", $teacher_id, $teacher_id), ARRAY_A) ?: [];
    }

    private static function teacher_can_manage_class($teacher_id, $class_id) {
        foreach (self::classes($teacher_id) as $class) {
            if ((int)$class['id'] === (int)$class_id) return true;
        }
        return false;
    }

    private static function class_profiles() {
        $profiles = get_option('mb_teacher_class_profiles_v1', []);
        return is_array($profiles) ? $profiles : [];
    }

    private static function teacher_organizations($teacher_id) {
        global $wpdb;
        if (user_can($teacher_id, 'manage_options') || user_can($teacher_id, MathBinder_Capabilities::MANAGE_ORGANIZATIONS)) {
            return MathBinder_Organization_Service::organizations();
        }
        return $wpdb->get_results($wpdb->prepare("SELECT DISTINCT o.* FROM {$wpdb->prefix}mb_organizations o LEFT JOIN {$wpdb->prefix}mb_classes c ON c.organization_id=o.id LEFT JOIN {$wpdb->prefix}mb_enrollments e ON e.class_id=c.id AND e.user_id=%d AND e.role_key='teacher' WHERE o.status='active' AND (o.owner_user_id=%d OR c.teacher_user_id=%d OR e.id IS NOT NULL) ORDER BY o.name", $teacher_id, $teacher_id, $teacher_id), ARRAY_A) ?: [];
    }

    private static function ensure_independent_workspace($teacher_id) {
        global $wpdb;
        $organizations = self::teacher_organizations($teacher_id);
        if ($organizations) return (int)$organizations[0]['id'];
        $user = get_userdata($teacher_id);
        $name = ($user && $user->display_name ? $user->display_name : 'Independent Teacher') . ' Classroom';
        $organization_id = MathBinder_Organization_Service::create_organization($name, 'independent_teacher');
        return is_wp_error($organization_id) ? $organization_id : (int)$organization_id;
    }

    private static function active_terms($organization_id) {
        global $wpdb;
        return $wpdb->get_results($wpdb->prepare("SELECT * FROM {$wpdb->prefix}mb_terms WHERE organization_id=%d AND status='active' ORDER BY starts_on DESC,id DESC", absint($organization_id)), ARRAY_A) ?: [];
    }

    public static function handle_create_class() {
        if (!is_user_logged_in() || !self::can_view()) wp_die('Teacher access required.', 'Teacher access required', ['response'=>403]);
        check_admin_referer('mb_teacher_create_class', 'mb_teacher_class_nonce');
        $teacher_id = get_current_user_id();
        $name = isset($_POST['class_name']) ? sanitize_text_field(wp_unslash($_POST['class_name'])) : '';
        $section = isset($_POST['section_name']) ? sanitize_text_field(wp_unslash($_POST['section_name'])) : '';
        $subject = isset($_POST['subject']) ? sanitize_text_field(wp_unslash($_POST['subject'])) : '';
        $grade = isset($_POST['grade_level']) ? sanitize_text_field(wp_unslash($_POST['grade_level'])) : '';
        $school_year = isset($_POST['school_year']) ? sanitize_text_field(wp_unslash($_POST['school_year'])) : '';
        $enrollment = isset($_POST['enrollment_mode']) ? sanitize_key(wp_unslash($_POST['enrollment_mode'])) : 'code';
        if (!in_array($enrollment, ['code','approval','closed'], true)) $enrollment = 'code';
        if ($name === '' || $subject === '' || $grade === '' || $school_year === '') {
            wp_safe_redirect(add_query_arg('class_notice', 'invalid', home_url('/'.self::PAGE_SLUG.'/')).'#classes'); exit;
        }
        $organization_id = isset($_POST['organization_id']) ? absint($_POST['organization_id']) : 0;
        $allowed_organizations = array_map('absint', wp_list_pluck(self::teacher_organizations($teacher_id), 'id'));
        if (!$organization_id) {
            $organization_id = self::ensure_independent_workspace($teacher_id);
            if (is_wp_error($organization_id)) wp_die(esc_html($organization_id->get_error_message()), 'Classroom setup failed', ['response'=>400]);
        } elseif (!in_array($organization_id, $allowed_organizations, true)) {
            wp_die('This organization is not available in your teacher workspace.', 'Organization unavailable', ['response'=>403]);
        }
        $term_id = isset($_POST['term_id']) ? absint($_POST['term_id']) : 0;
        $valid_term = false;
        foreach (self::active_terms($organization_id) as $term) if ((int)$term['id'] === $term_id) $valid_term = true;
        if (!$valid_term) $term_id = MathBinder_Organization_Service::create_term($organization_id, $school_year);
        $class_id = MathBinder_Organization_Service::create_class($organization_id, $term_id, $name, $section, $teacher_id);
        if (!$class_id) wp_die('The classroom could not be created.', 'Classroom setup failed', ['response'=>500]);
        MathBinder_Organization_Service::enroll($class_id, wp_get_current_user()->user_email, 'teacher');
        $profiles = self::class_profiles();
        $profiles[(string)$class_id] = ['subject'=>$subject,'grade_level'=>$grade,'school_year'=>$school_year,'enrollment_mode'=>$enrollment,'created_by'=>$teacher_id,'updated_at'=>current_time('mysql', true)];
        update_option('mb_teacher_class_profiles_v1', $profiles, false);
        wp_safe_redirect(add_query_arg(['class_notice'=>'created','class_id'=>$class_id], home_url('/'.self::PAGE_SLUG.'/')).'#classes'); exit;
    }

    public static function handle_invite_student() {
        if (!is_user_logged_in() || !self::can_view()) wp_die('Teacher access required.', 'Teacher access required', ['response'=>403]);
        check_admin_referer('mb_teacher_invite_student', 'mb_teacher_invite_nonce');
        $teacher_id = get_current_user_id();
        $class_id = isset($_POST['class_id']) ? absint($_POST['class_id']) : 0;
        $email = isset($_POST['student_email']) ? sanitize_email(wp_unslash($_POST['student_email'])) : '';
        if (!self::teacher_can_manage_class($teacher_id, $class_id) || !is_email($email)) {
            wp_safe_redirect(add_query_arg('class_notice', 'invite_invalid', home_url('/'.self::PAGE_SLUG.'/')).'#classes'); exit;
        }
        MathBinder_Organization_Service::enroll($class_id, $email, 'student');
        wp_safe_redirect(add_query_arg('class_notice', 'invited', home_url('/'.self::PAGE_SLUG.'/')).'#classes'); exit;
    }

    public static function handle_class_status() {
        if (!is_user_logged_in() || !self::can_view()) wp_die('Teacher access required.', 'Teacher access required', ['response'=>403]);
        check_admin_referer('mb_teacher_class_status', 'mb_teacher_class_status_nonce');
        $teacher_id = get_current_user_id();
        $class_id = isset($_POST['class_id']) ? absint($_POST['class_id']) : 0;
        if (!self::teacher_can_manage_class($teacher_id, $class_id)) wp_die('This class is not available in your teacher workspace.', 'Class unavailable', ['response'=>403]);
        MathBinder_Organization_Service::update_record_status('class', $class_id, 'archived');
        wp_safe_redirect(add_query_arg('class_notice', 'archived', home_url('/'.self::PAGE_SLUG.'/')).'#classes'); exit;
    }

    private static function students($classes) {
        global $wpdb;
        $ids = array_values(array_filter(array_map('absint', wp_list_pluck($classes, 'id'))));
        if (!$ids) return [];
        $placeholders = implode(',', array_fill(0, count($ids), '%d'));
        $sql = $wpdb->prepare("SELECT e.class_id,e.user_id,u.display_name,u.user_email,c.name AS class_name,c.section_name FROM {$wpdb->prefix}mb_enrollments e JOIN {$wpdb->users} u ON u.ID=e.user_id JOIN {$wpdb->prefix}mb_classes c ON c.id=e.class_id WHERE e.class_id IN ($placeholders) AND e.role_key='student' AND e.status='active' ORDER BY c.name,u.display_name", $ids);
        return $wpdb->get_results($sql, ARRAY_A) ?: [];
    }

    private static function authorized_student($teacher_id, $student_id) {
        foreach (self::students(self::classes($teacher_id)) as $student) {
            if ((int)$student['user_id'] === (int)$student_id) return true;
        }
        return false;
    }

    private static function reviews($student_id) {
        $reviews = get_user_meta(absint($student_id), 'mb_teacher_evidence_reviews_v1', true);
        return is_array($reviews) ? $reviews : [];
    }

    private static function mastery_paths() {
        $paths = get_option('mb_teacher_mastery_paths_v1', []);
        return is_array($paths) ? $paths : [];
    }

    private static function teacher_paths($teacher_id) {
        return array_values(array_filter(self::mastery_paths(), function($path) use ($teacher_id) {
            return (int)($path['teacher_id'] ?? 0) === (int)$teacher_id || user_can($teacher_id, 'manage_options');
        }));
    }

    private static function find_teacher_path($teacher_id, $path_id) {
        foreach (self::teacher_paths($teacher_id) as $path) {
            if ((string)($path['id'] ?? '') === (string)$path_id) return $path;
        }
        return null;
    }

    private static function lessons() {
        return get_posts(['post_type'=>'mb_binder_page','post_status'=>'publish','numberposts'=>-1,'orderby'=>'title','order'=>'ASC']);
    }

    private static function mastery_generation_schema() {
        $strings = ['type'=>'array','items'=>['type'=>'string'],'minItems'=>1];
        $questions = ['type'=>'array','items'=>['type'=>'string'],'minItems'=>8,'maxItems'=>8];
        return [
            'type'=>'object','additionalProperties'=>false,
            'properties'=>[
                'verified'=>['type'=>'boolean'], 'verification_message'=>['type'=>'string'],
                'standard_code'=>['type'=>'string'], 'standard_text'=>['type'=>'string'],
                'grade_course'=>['type'=>'string'], 'domain'=>['type'=>'string'],
                'skills'=>$strings, 'objectives'=>['type'=>'string'], 'prerequisites'=>['type'=>'string'],
                'pretest_questions'=>$questions, 'foundational'=>['type'=>'string'],
                'developing'=>['type'=>'string'], 'near_mastery'=>['type'=>'string'],
                'extension'=>['type'=>'string'], 'evidence'=>['type'=>'string'],
                'posttest_questions'=>$questions, 'reassessment'=>['type'=>'string'],
                'extension_activity'=>['type'=>'string'], 'alignment_check'=>['type'=>'string']
            ],
            'required'=>['verified','verification_message','standard_code','standard_text','grade_course','domain','skills','objectives','prerequisites','pretest_questions','foundational','developing','near_mastery','extension','evidence','posttest_questions','reassessment','extension_activity','alignment_check']
        ];
    }

    public static function ajax_generate_mastery_path() {
        check_ajax_referer('mb_generate_mastery_path', 'nonce');
        if (!is_user_logged_in() || !self::can_view()) wp_send_json_error(['message'=>'Teacher access required.'], 403);
        if (!defined('MATHBINDER_OPENAI_API_KEY') || trim((string)MATHBINDER_OPENAI_API_KEY) === '') {
            wp_send_json_error(['message'=>'The secure AI connection is not configured.'], 503);
        }
        $title = isset($_POST['title']) ? sanitize_text_field(wp_unslash($_POST['title'])) : '';
        $standard = isset($_POST['standard']) ? sanitize_text_field(wp_unslash($_POST['standard'])) : '';
        $grade = isset($_POST['grade']) ? sanitize_text_field(wp_unslash($_POST['grade'])) : '';
        $allowed_grades = array_merge(['K'], array_map('strval', range(1, 12)));
        if ($title === '' || $standard === '' || !in_array($grade, $allowed_grades, true) || strlen($title) > 160 || strlen($standard) > 1000) {
            wp_send_json_error(['message'=>'Select a grade and enter a topic title plus one California mathematics standard code or its complete wording.'], 400);
        }
        $rate_key = 'mb_mastery_ai_' . get_current_user_id();
        $uses = absint(get_transient($rate_key));
        if ($uses >= 20) wp_send_json_error(['message'=>'The hourly generation limit has been reached. Please try again later.'], 429);
        set_transient($rate_key, $uses + 1, HOUR_IN_SECONDS);

        $instructions = <<<'PROMPT'
You are MathBinder's California K-12 mathematics curriculum generator. The authoritative content standards are the California Common Core State Standards for Mathematics, adopted August 2010 and modified January 2013. Apply the instructional guidance of California's State Board-adopted 2023 Mathematics Framework and the eight Standards for Mathematical Practice. First verify the supplied code and wording. Accept California-specific additions and high-school conceptual-category codes. Never infer a different standard from only the topic title. If the code is unknown, incomplete, ambiguous, non-mathematics, or conflicts with supplied wording, set verified=false, explain exactly what the teacher must correct, and return empty strings plus eight empty strings for each question array; do not generate generic content. If verified, preserve the exact standard code and faithful wording, identify grade/course and domain, decompose the standard into assessable skills, and generate a coherent mastery path. Pretest and posttest must each contain exactly eight complete, student-ready questions with sufficient numbers/data/context to solve; use parallel skills and difficulty but materially different values and contexts. Cover every skill in the standard, not neighboring standards. Include conceptual understanding, procedural fluency, application, reasoning, representation, and at least one error-analysis item when appropriate. Do not include answers in student questions. Assignments must state concrete student work, quantity, representations, and success evidence—not generic directions. Differentiate foundational, developing, near-mastery, and extension routes. The standard controls all content. Use plain text suitable for editable WordPress textareas. Do not publish; a teacher reviews everything.
PROMPT;
        $payload = [
            'model'=>'gpt-5.6-luna','store'=>false,
            'safety_identifier'=>hash('sha256', wp_salt('auth').'teacher-'.get_current_user_id()),
            'instructions'=>$instructions,
            'input'=>[[ 'role'=>'user', 'content'=>[[ 'type'=>'input_text', 'text'=>"Teacher-selected grade: {$grade}\nTopic or unit title: {$title}\nCalifornia mathematics standard supplied by teacher: {$standard}\nVerify that the selected grade is compatible with the standard. For a high-school conceptual-category standard, use the selected grade as the instructional difficulty and context. If a K-8 grade-specific standard conflicts with the selected grade, set verified=false." ]] ]],
            'text'=>['format'=>['type'=>'json_schema','name'=>'mathbinder_ca_mastery_path','strict'=>true,'schema'=>self::mastery_generation_schema()]]
        ];
        $response = wp_remote_post('https://api.openai.com/v1/responses', [
            'timeout'=>90,
            'headers'=>['Authorization'=>'Bearer '.trim((string)MATHBINDER_OPENAI_API_KEY),'Content-Type'=>'application/json'],
            'body'=>wp_json_encode($payload)
        ]);
        if (is_wp_error($response)) wp_send_json_error(['message'=>'MathBinder could not reach the generation service. Please try again.'], 502);
        $body = json_decode(wp_remote_retrieve_body($response), true);
        if (wp_remote_retrieve_response_code($response) >= 300) wp_send_json_error(['message'=>'The generation service could not complete this request.'], 502);
        $text = '';
        foreach (($body['output'] ?? []) as $output) foreach (($output['content'] ?? []) as $item) if (($item['type'] ?? '') === 'output_text') $text .= (string)($item['text'] ?? '');
        $draft = json_decode($text, true);
        if (!is_array($draft) || !array_key_exists('verified', $draft)) wp_send_json_error(['message'=>'The generated draft could not be validated. Please try again.'], 502);
        if (!$draft['verified']) wp_send_json_error(['message'=>sanitize_text_field($draft['verification_message'] ?? 'The standard could not be verified.')], 422);
        wp_send_json_success(['draft'=>$draft]);
    }

    public static function handle_canvas_prepare() {
        if (!is_user_logged_in() || !self::can_view()) wp_die('Teacher access required.', 'Teacher access required', ['response'=>403]);
        check_admin_referer('mb_teacher_canvas_prepare', 'mb_canvas_prepare_nonce');
        $teacher_id = get_current_user_id();
        $path_id = isset($_POST['path_id']) ? sanitize_text_field(wp_unslash($_POST['path_id'])) : '';
        $path = null;
        foreach (self::teacher_paths($teacher_id) as $candidate) if ((string)($candidate['id'] ?? '') === $path_id) { $path = $candidate; break; }
        if (!$path) wp_die('This mastery path is not available in your teacher workspace.', 'Mastery path unavailable', ['response'=>403]);
        $result = MathBinder_Canvas_Integration::prepare_assignment($path, $teacher_id);
        if (is_wp_error($result)) wp_die(esc_html($result->get_error_message()), 'Canvas preparation failed', ['response'=>400]);
        MathBinder_Audit_Log::record('prepare', 'canvas_assignment', $path_id, ['target_type'=>$path['target_type'],'target_id'=>$path['target_id']]);
        wp_safe_redirect(add_query_arg('canvas_notice', 'prepared', home_url('/'.self::PAGE_SLUG.'/')).'#canvas'); exit;
    }

    public static function handle_mastery_path() {
        if (!is_user_logged_in() || !self::can_view()) wp_die('Teacher access required.', 'Teacher access required', ['response'=>403]);
        check_admin_referer('mb_teacher_mastery_path', 'mb_mastery_path_nonce');
        $teacher_id = get_current_user_id();
        $path_id = isset($_POST['path_id']) ? sanitize_text_field(wp_unslash($_POST['path_id'])) : '';
        $save_mode = isset($_POST['save_mode']) ? sanitize_key(wp_unslash($_POST['save_mode'])) : 'draft';
        if (!in_array($save_mode, ['draft','published'], true)) $save_mode = 'draft';
        $existing = $path_id !== '' ? self::find_teacher_path($teacher_id, $path_id) : null;
        if ($path_id !== '' && !$existing) wp_die('This mastery path is not available in your teacher workspace.', 'Mastery path unavailable', ['response'=>403]);
        $title = isset($_POST['path_title']) ? sanitize_text_field(wp_unslash($_POST['path_title'])) : '';
        $standard = isset($_POST['target_standard']) ? sanitize_text_field(wp_unslash($_POST['target_standard'])) : '';
        $grade_level = isset($_POST['mastery_grade_level']) ? sanitize_text_field(wp_unslash($_POST['mastery_grade_level'])) : '';
        $objectives = isset($_POST['objectives']) ? sanitize_textarea_field(wp_unslash($_POST['objectives'])) : '';
        $prerequisites = isset($_POST['prerequisites']) ? sanitize_textarea_field(wp_unslash($_POST['prerequisites'])) : '';
        $pretest_title = isset($_POST['pretest_title']) ? sanitize_text_field(wp_unslash($_POST['pretest_title'])) : '';
        $pretest_instructions = isset($_POST['pretest_instructions']) ? sanitize_textarea_field(wp_unslash($_POST['pretest_instructions'])) : '';
        $pretest_url = isset($_POST['pretest_url']) ? esc_url_raw(wp_unslash($_POST['pretest_url'])) : '';
        $evidence_requirements = isset($_POST['evidence_requirements']) ? sanitize_textarea_field(wp_unslash($_POST['evidence_requirements'])) : '';
        $posttest_title = isset($_POST['posttest_title']) ? sanitize_text_field(wp_unslash($_POST['posttest_title'])) : '';
        $posttest_instructions = isset($_POST['posttest_instructions']) ? sanitize_textarea_field(wp_unslash($_POST['posttest_instructions'])) : '';
        $posttest_url = isset($_POST['posttest_url']) ? esc_url_raw(wp_unslash($_POST['posttest_url'])) : '';
        $reassessment = isset($_POST['reassessment']) ? sanitize_textarea_field(wp_unslash($_POST['reassessment'])) : '';
        $extension_activity = isset($_POST['extension_activity']) ? sanitize_textarea_field(wp_unslash($_POST['extension_activity'])) : '';
        $threshold = isset($_POST['mastery_threshold']) ? absint($_POST['mastery_threshold']) : 80;
        $target = isset($_POST['target']) ? sanitize_text_field(wp_unslash($_POST['target'])) : '';
        $target_parts = explode(':', $target, 2);
        $target_type = sanitize_key($target_parts[0] ?? '');
        $target_id = absint($target_parts[1] ?? 0);
        $due_date = isset($_POST['due_date']) ? sanitize_text_field(wp_unslash($_POST['due_date'])) : '';
        $lesson_order = isset($_POST['lesson_order']) ? sanitize_text_field(wp_unslash($_POST['lesson_order'])) : '';
        $lesson_ids = array_values(array_unique(array_filter(array_map('absint', explode(',', $lesson_order)))));
        $branches = [];
        foreach (['foundational','developing','near_mastery','extension'] as $branch) $branches[$branch] = isset($_POST[$branch]) ? sanitize_textarea_field(wp_unslash($_POST[$branch])) : '';
        $valid_target = false;
        if ($target_type === 'class') foreach (self::classes($teacher_id) as $class) if ((int)$class['id'] === $target_id) $valid_target = true;
        if ($target_type === 'student') $valid_target = self::authorized_student($teacher_id, $target_id);
        $valid_due_date = $due_date === '' || (bool)preg_match('/^\d{4}-\d{2}-\d{2}$/', $due_date);
        $allowed_mastery_grades = array_merge(['K'], array_map('strval', range(1, 12)));
        $publish_fields = [$title,$standard,$grade_level,$objectives,$prerequisites,$pretest_title,$pretest_instructions,$evidence_requirements,$posttest_title,$posttest_instructions,$reassessment];
        if ($title === '' || $standard === '' || !in_array($grade_level, $allowed_mastery_grades, true) || !$valid_due_date || $threshold < 1 || $threshold > 100 || ($save_mode === 'published' && (in_array('', $publish_fields, true) || in_array('', $branches, true) || !$valid_target || !$lesson_ids))) {
            wp_safe_redirect(add_query_arg('path_notice', 'invalid', home_url('/'.self::PAGE_SLUG.'/')).'#mastery-paths'); exit;
        }
        $published_lessons = $lesson_ids ? get_posts(['post_type'=>'mb_binder_page','post_status'=>'publish','post__in'=>$lesson_ids,'numberposts'=>-1,'fields'=>'ids']) : [];
        if ($lesson_ids && count($published_lessons) !== count($lesson_ids)) {
            wp_safe_redirect(add_query_arg('path_notice', 'invalid', home_url('/'.self::PAGE_SLUG.'/')).'#mastery-paths'); exit;
        }
        $paths = self::mastery_paths(); if ($path_id === '') $path_id = wp_generate_uuid4();
        $created_at = $existing['created_at'] ?? current_time('mysql', true);
        $paths[$path_id] = ['id'=>$path_id,'teacher_id'=>$teacher_id,'teacher_name'=>wp_get_current_user()->display_name,'title'=>$title,'standard'=>$standard,'objectives'=>$objectives,'prerequisites'=>$prerequisites,'pretest'=>['title'=>$pretest_title,'instructions'=>$pretest_instructions,'url'=>$pretest_url],'mastery_threshold'=>$threshold,'target_type'=>$target_type,'target_id'=>$target_id,'due_date'=>$due_date,'lesson_ids'=>$lesson_ids,'branches'=>$branches,'evidence_requirements'=>$evidence_requirements,'posttest'=>['title'=>$posttest_title,'instructions'=>$posttest_instructions,'url'=>$posttest_url],'reassessment'=>$reassessment,'extension_activity'=>$extension_activity,'status'=>$save_mode,'created_at'=>$created_at,'updated_at'=>current_time('mysql', true)];
        $paths[$path_id]['grade_level'] = $grade_level;
        update_option('mb_teacher_mastery_paths_v1', $paths, false);
        MathBinder_Audit_Log::record($existing ? 'update' : 'create', 'teacher_mastery_path', $target_id, ['path_id'=>$path_id,'target_type'=>$target_type,'mastery_threshold'=>$threshold,'status'=>$save_mode]);
        wp_safe_redirect(add_query_arg('path_notice', $save_mode === 'published' ? 'published' : 'saved', home_url('/'.self::PAGE_SLUG.'/')).'#mastery-paths'); exit;
    }

    public static function handle_evidence_review() {
        if (!is_user_logged_in() || !self::can_view()) wp_die('Teacher access required.', 'Teacher access required', ['response'=>403]);
        check_admin_referer('mb_teacher_evidence_review', 'mb_teacher_review_nonce');
        $teacher_id = get_current_user_id();
        $student_id = isset($_POST['student_id']) ? absint($_POST['student_id']) : 0;
        $lesson_id = isset($_POST['lesson_id']) ? sanitize_text_field(wp_unslash($_POST['lesson_id'])) : '';
        $decision = isset($_POST['decision']) ? sanitize_key(wp_unslash($_POST['decision'])) : '';
        $feedback = isset($_POST['feedback']) ? sanitize_textarea_field(wp_unslash($_POST['feedback'])) : '';
        if (!$student_id || $lesson_id === '' || !in_array($decision, ['feedback','revision_requested','mastered'], true) || !self::authorized_student($teacher_id, $student_id)) wp_die('This evidence record is not available in your teacher workspace.', 'Evidence unavailable', ['response'=>403]);
        $activity = self::activity($student_id);
        if (empty($activity['lessons'][$lesson_id]['completed'])) wp_die('Only completed lesson evidence can be reviewed.', 'Evidence incomplete', ['response'=>400]);
        if (($decision === 'feedback' || $decision === 'revision_requested') && $feedback === '') {
            wp_safe_redirect(add_query_arg(['student'=>$student_id,'review_notice'=>'feedback_required'], home_url('/'.self::PAGE_SLUG.'/')).'#evidence'); exit;
        }
        $reviews = self::reviews($student_id);
        $reviews[$lesson_id] = ['lesson_id'=>$lesson_id,'decision'=>$decision,'feedback'=>$feedback,'teacher_id'=>$teacher_id,'teacher_name'=>wp_get_current_user()->display_name,'reviewed_at'=>current_time('mysql', true)];
        update_user_meta($student_id, 'mb_teacher_evidence_reviews_v1', $reviews);
        MathBinder_Audit_Log::record('update', 'teacher_evidence_review', $student_id, ['lesson_id'=>$lesson_id,'decision'=>$decision]);
        wp_safe_redirect(add_query_arg(['student'=>$student_id,'review_notice'=>'saved'], home_url('/'.self::PAGE_SLUG.'/')).'#evidence'); exit;
    }

    private static function activity($user_id) {
        $activity = get_user_meta(absint($user_id), 'mb_student_activity_v1', true);
        if (!is_array($activity)) $activity = ['lessons'=>[]];
        if (empty($activity['lessons']) || !is_array($activity['lessons'])) $activity['lessons'] = [];
        return $activity;
    }

    private static function metrics($user_id) {
        $activity = self::activity($user_id); $completed = 0; $notes = 0; $last = '';
        foreach ($activity['lessons'] as $lesson) {
            if (!empty($lesson['completed'])) $completed++;
            if (!empty($lesson['hasNotes'])) $notes++;
            $updated = (string)($lesson['updatedAt'] ?? '');
            if ($updated > $last) $last = $updated;
        }
        return ['completed'=>$completed,'notes'=>$notes,'last'=>$last,'activity'=>$activity];
    }

    private static function assignments_for_student($student_id, $paths, $class_id) {
        $items = []; $activity = self::activity($student_id); $reviews = self::reviews($student_id);
        foreach ($paths as $path) {
            if (($path['status'] ?? '') !== 'published') continue;
            $type = (string)($path['target_type'] ?? ''); $target = absint($path['target_id'] ?? 0);
            if (!(($type === 'student' && $target === (int)$student_id) || ($type === 'class' && $target === (int)$class_id))) continue;
            $lesson_ids = array_values(array_filter(array_map('absint', (array)($path['lesson_ids'] ?? []))));
            $completed = 0; $mastered = 0;
            foreach ($lesson_ids as $lesson_id) {
                if (!empty($activity['lessons'][(string)$lesson_id]['completed'])) $completed++;
                if (($reviews[(string)$lesson_id]['decision'] ?? '') === 'mastered') $mastered++;
            }
            $total = count($lesson_ids); $percent = $total ? (int)round(($completed / $total) * 100) : 0;
            $due = (string)($path['due_date'] ?? ''); $status = $percent >= 100 ? 'Complete' : ($completed ? 'In progress' : 'Not started');
            if ($due && $percent < 100 && strtotime($due.' 23:59:59') < current_time('timestamp')) $status = 'Past due';
            $items[] = ['path'=>$path,'total'=>$total,'completed'=>$completed,'mastered'=>$mastered,'percent'=>$percent,'status'=>$status];
        }
        return $items;
    }

    public static function handle_progress_export() {
        if (!is_user_logged_in() || !self::can_view()) wp_die('Teacher access required.', 'Teacher access required', ['response'=>403]);
        check_admin_referer('mb_teacher_progress_export');
        $teacher_id = get_current_user_id(); $classes = self::classes($teacher_id); $students = self::students($classes); $paths = self::teacher_paths($teacher_id);
        nocache_headers(); header('Content-Type: text/csv; charset=utf-8'); header('Content-Disposition: attachment; filename="mathbinder-student-progress-'.gmdate('Y-m-d').'.csv"');
        $out = fopen('php://output', 'w');
        fputcsv($out, ['Student','Email','Class','Completed Lessons','Saved Notes','Last Activity','Assigned Paths','Completed Paths','Past Due Paths']);
        foreach ($students as $student) {
            $metrics = self::metrics($student['user_id']); $assignments = self::assignments_for_student($student['user_id'], $paths, $student['class_id']);
            $complete = count(array_filter($assignments, function($item){ return $item['status'] === 'Complete'; }));
            $past_due = count(array_filter($assignments, function($item){ return $item['status'] === 'Past due'; }));
            fputcsv($out, [$student['display_name'],$student['user_email'],$student['class_name'].($student['section_name'] ? ' · '.$student['section_name'] : ''),$metrics['completed'],$metrics['notes'],$metrics['last'],count($assignments),$complete,$past_due]);
        }
        fclose($out); exit;
    }

    public static function shortcode() {
        if (!is_user_logged_in()) return '<section class="mb-dashboard-gate"><h1>Teacher Dashboard</h1><p>Log in with your teacher account to continue.</p><a class="mb-button mb-button-primary" href="'.esc_url(MathBinder_Frontend_Auth::login_url(get_permalink())).'">Log In</a></section>';
        if (!self::can_view()) return '<section class="mb-dashboard-gate"><h1>Teacher access required</h1><p>This dashboard is available only in a Teacher or School Administrator workspace.</p></section>';

        $user = wp_get_current_user(); $classes = self::classes($user->ID); $students = self::students($classes); $paths = self::teacher_paths($user->ID); $published_paths = array_values(array_filter($paths, function($path){ return ($path['status'] ?? 'published') === 'published'; })); $lessons = self::lessons(); $canvas_status = MathBinder_Canvas_Integration::status(); $canvas_queue = MathBinder_Canvas_Integration::for_teacher($user->ID); $organizations = self::teacher_organizations($user->ID); $class_profiles = self::class_profiles();
        $total_completed = 0; $active_students = 0; $rows = [];
        foreach ($students as $student) { $student['metrics'] = self::metrics($student['user_id']); $student['assignments'] = self::assignments_for_student($student['user_id'], $published_paths, $student['class_id']); $rows[] = $student; $total_completed += $student['metrics']['completed']; if ($student['metrics']['last']) $active_students++; }
        $selected_id = isset($_GET['student']) ? absint($_GET['student']) : 0; $selected = null;
        foreach ($rows as $row) if ((int)$row['user_id'] === $selected_id) { $selected = $row; break; }
        $reviews = $selected ? self::reviews($selected_id) : [];
        $review_notice = isset($_GET['review_notice']) ? sanitize_key(wp_unslash($_GET['review_notice'])) : '';
        $path_notice = isset($_GET['path_notice']) ? sanitize_key(wp_unslash($_GET['path_notice'])) : '';
        $class_notice = isset($_GET['class_notice']) ? sanitize_key(wp_unslash($_GET['class_notice'])) : '';
        $edit_path_id = isset($_GET['edit_path']) ? sanitize_text_field(wp_unslash($_GET['edit_path'])) : '';
        $edit_path = $edit_path_id !== '' ? self::find_teacher_path($user->ID, $edit_path_id) : null;
        $builder = wp_parse_args((array)$edit_path, ['id'=>'','title'=>'','standard'=>'','grade_level'=>'','objectives'=>'','prerequisites'=>'','mastery_threshold'=>80,'target_type'=>'class','target_id'=>(int)($classes[0]['id'] ?? 0),'due_date'=>'','lesson_ids'=>[],'branches'=>[],'pretest'=>[],'evidence_requirements'=>'','posttest'=>[],'reassessment'=>'','extension_activity'=>'','status'=>'draft']);
        $builder['branches'] = wp_parse_args((array)$builder['branches'], ['foundational'=>'','developing'=>'','near_mastery'=>'','extension'=>'']);
        $builder['pretest'] = wp_parse_args((array)$builder['pretest'], ['title'=>'','instructions'=>'','url'=>'']);
        $builder['posttest'] = wp_parse_args((array)$builder['posttest'], ['title'=>'','instructions'=>'','url'=>'']);
        $canvas_notice = isset($_GET['canvas_notice']) ? sanitize_key(wp_unslash($_GET['canvas_notice'])) : '';
        ob_start(); ?>
        <div class="mb-teacher-dashboard">
            <header class="mb-teacher-hero"><div><span>Teacher workspace</span><h1>Welcome, <?php echo esc_html($user->display_name ?: $user->user_login); ?></h1><p>See class enrollment and real MathBinder activity in one place.</p></div><a href="<?php echo esc_url(home_url('/mathbinder-account/')); ?>">Account &amp; Workspaces</a></header>
            <nav class="mb-teacher-nav" aria-label="Teacher dashboard sections"><a class="is-active" href="#overview">Overview</a><a href="#classes">My Classes</a><a href="#roster">Student Progress</a><a href="#mastery-paths">Mastery Paths</a><a href="#evidence">Evidence</a><a href="#canvas">Canvas</a></nav>
            <section id="overview" class="mb-teacher-stats">
                <article><small>Active classes</small><strong><?php echo count($classes); ?></strong><span>Assigned to this workspace</span></article>
                <article><small>Enrolled students</small><strong><?php echo count($students); ?></strong><span>Across your classes</span></article>
                <article><small>Students with activity</small><strong><?php echo $active_students; ?></strong><span>Synced lesson activity</span></article>
                <article><small>Completed lessons</small><strong><?php echo $total_completed; ?></strong><span>All enrolled students</span></article>
            </section>
            <section id="classes" class="mb-teacher-panel"><div class="mb-teacher-heading mb-teacher-heading-actions"><div><small>Classroom</small><h2>My Classes</h2><p>Create a classroom, share its enrollment code, and manage the roster from this dashboard.</p></div><a class="mb-teacher-export" href="#create-class">Create a Class</a></div>
                <?php if ($class_notice === 'created'): ?><div class="mb-teacher-review-notice is-success" role="status">Class created. Its class code is ready to share, and the Mastery Path Builder is now unlocked.</div><?php elseif ($class_notice === 'invited'): ?><div class="mb-teacher-review-notice is-success" role="status">The student was added or invited successfully.</div><?php elseif ($class_notice === 'archived'): ?><div class="mb-teacher-review-notice is-success" role="status">The class was archived.</div><?php elseif (in_array($class_notice, ['invalid','invite_invalid'], true)): ?><div class="mb-teacher-review-notice is-error" role="alert">Please check the classroom information and try again.</div><?php endif; ?>
                <?php if (!$classes): ?><div class="mb-teacher-empty"><strong>No classes yet.</strong><p>Create your own classroom below. Organization administrators may also assign centrally managed classes.</p></div>
                <?php else: ?><div class="mb-teacher-class-grid"><?php foreach ($classes as $class): $profile=$class_profiles[(string)$class['id']] ?? []; $join_url=add_query_arg('class_code',$class['class_code'],home_url('/student-dashboard/')); ?><article><span><?php echo esc_html($class['section_name'] ?: ($profile['subject'] ?? 'Class')); ?></span><h3><?php echo esc_html($class['name']); ?></h3><p><?php echo esc_html(trim(($profile['subject'] ?? '').' · '.($profile['grade_level'] ?? ''), ' ·')); ?></p><p>Class code: <strong><?php echo esc_html($class['class_code']); ?></strong></p><div class="mb-class-actions"><a href="<?php echo esc_url($join_url); ?>">Enrollment link</a><details><summary>Invite student</summary><form method="post" action="<?php echo esc_url(admin_url('admin-post.php')); ?>"><input type="hidden" name="action" value="mb_teacher_invite_student"><input type="hidden" name="class_id" value="<?php echo absint($class['id']); ?>"><?php wp_nonce_field('mb_teacher_invite_student','mb_teacher_invite_nonce'); ?><label>Student email<input type="email" name="student_email" required></label><button type="submit">Add or Invite</button></form></details><form method="post" action="<?php echo esc_url(admin_url('admin-post.php')); ?>" onsubmit="return confirm('Archive this class?');"><input type="hidden" name="action" value="mb_teacher_class_status"><input type="hidden" name="class_id" value="<?php echo absint($class['id']); ?>"><?php wp_nonce_field('mb_teacher_class_status','mb_teacher_class_status_nonce'); ?><button class="mb-link-button" type="submit">Archive</button></form></div></article><?php endforeach; ?></div><?php endif; ?>
                <details id="create-class" class="mb-class-setup" <?php echo !$classes || $class_notice === 'invalid' ? 'open' : ''; ?>><summary>Create a new classroom</summary><form method="post" action="<?php echo esc_url(admin_url('admin-post.php')); ?>"><input type="hidden" name="action" value="mb_teacher_create_class"><?php wp_nonce_field('mb_teacher_create_class','mb_teacher_class_nonce'); ?><div class="mb-class-form-grid"><label>Class name<input type="text" name="class_name" maxlength="190" required placeholder="Example: Period 2 Math"></label><label>Section (optional)<input type="text" name="section_name" maxlength="120" placeholder="Example: Room 4 or Tuesday/Thursday"></label><label>Subject<input type="text" name="subject" maxlength="100" required value="Mathematics"></label><label>Grade level<input type="text" name="grade_level" maxlength="80" required placeholder="Example: Grades 7–8"></label><label>School year or term<input type="text" name="school_year" maxlength="120" required value="2026–2027"></label><?php if ($organizations): ?><label>Organization<select name="organization_id"><option value="0">Independent teacher workspace</option><?php foreach($organizations as $organization): ?><option value="<?php echo absint($organization['id']); ?>"><?php echo esc_html($organization['name']); ?></option><?php endforeach; ?></select></label><?php endif; ?><label>Enrollment setting<select name="enrollment_mode"><option value="code">Students may join with class code</option><option value="approval">Teacher approval required</option><option value="closed">Invitations only</option></select></label></div><button class="mb-teacher-primary" type="submit">Create Classroom</button></form></details>
            </section>
            <section id="roster" class="mb-teacher-panel"><div class="mb-teacher-heading mb-teacher-heading-actions"><div><small>Real student data</small><h2>Student Progress</h2></div><a class="mb-teacher-export" href="<?php echo esc_url(wp_nonce_url(admin_url('admin-post.php?action=mb_teacher_progress_export'), 'mb_teacher_progress_export')); ?>">Export CSV</a></div>
                <?php if (!$students): ?><div class="mb-teacher-empty"><strong>No active students are enrolled.</strong><p>Students will appear after they are enrolled in one of your assigned classes.</p></div>
                <?php else: ?><div class="mb-teacher-filters"><label>Find a student<input type="search" placeholder="Search by name or email" data-mb-roster-search></label><label>Class<select data-mb-roster-class><option value="">All classes</option><?php foreach ($classes as $class): ?><option value="<?php echo absint($class['id']); ?>"><?php echo esc_html($class['name'].($class['section_name'] ? ' · '.$class['section_name'] : '')); ?></option><?php endforeach; ?></select></label><label>Status<select data-mb-roster-status><option value="">All activity</option><option value="active">Has activity</option><option value="inactive">No activity</option><option value="past-due">Past due</option></select></label></div><div class="mb-teacher-table-wrap"><table><thead><tr><th>Student</th><th>Class</th><th>Assignments</th><th>Completed</th><th>Last activity</th><th>Details</th></tr></thead><tbody data-mb-roster><?php foreach ($rows as $row): $past_due=(bool)array_filter($row['assignments'],function($item){return $item['status']==='Past due';}); ?><tr data-name="<?php echo esc_attr(strtolower($row['display_name'].' '.$row['user_email'])); ?>" data-class="<?php echo absint($row['class_id']); ?>" data-activity="<?php echo $row['metrics']['last']?'active':'inactive'; ?>" data-past-due="<?php echo $past_due?'1':'0'; ?>"><td><strong><?php echo esc_html($row['display_name']); ?></strong><small><?php echo esc_html($row['user_email']); ?></small></td><td><?php echo esc_html($row['class_name'].($row['section_name'] ? ' · '.$row['section_name'] : '')); ?></td><td><?php echo count($row['assignments']); ?></td><td><?php echo intval($row['metrics']['completed']); ?><small><?php echo intval($row['metrics']['notes']); ?> saved note(s)</small></td><td><?php echo $row['metrics']['last'] ? esc_html(wp_date(get_option('date_format'), strtotime($row['metrics']['last']))) : 'No activity yet'; ?></td><td><a href="<?php echo esc_url(add_query_arg('student', $row['user_id'], get_permalink()).'#student-details'); ?>">View progress</a></td></tr><?php endforeach; ?></tbody></table><p class="mb-teacher-no-results" data-mb-roster-empty hidden>No students match these filters.</p></div><?php endif; ?>
            </section>
            <?php if ($selected): ?><section id="student-details" class="mb-teacher-panel"><div class="mb-teacher-heading"><div><small>Individual progress</small><h2><?php echo esc_html($selected['display_name']); ?></h2><p><?php echo esc_html($selected['class_name']); ?> · <?php echo intval($selected['metrics']['completed']); ?> completed lesson(s) · <?php echo intval($selected['metrics']['notes']); ?> saved note(s)</p></div></div><?php if (!$selected['assignments']): ?><div class="mb-teacher-empty"><strong>No published assignments yet.</strong><p>Assign this student or class a published mastery path to begin tracking progress.</p></div><?php else: ?><div class="mb-assignment-progress-grid"><?php foreach ($selected['assignments'] as $item): ?><article><div><small><?php echo esc_html($item['status']); ?></small><h3><?php echo esc_html($item['path']['title']); ?></h3><p><?php echo intval($item['completed']); ?> of <?php echo intval($item['total']); ?> lessons complete · <?php echo intval($item['mastered']); ?> mastered</p></div><strong><?php echo intval($item['percent']); ?>%</strong><div class="mb-assignment-track"><span style="width:<?php echo intval($item['percent']); ?>%"></span></div><?php if (!empty($item['path']['due_date'])): ?><time>Due <?php echo esc_html(wp_date(get_option('date_format'), strtotime($item['path']['due_date']))); ?></time><?php endif; ?></article><?php endforeach; ?></div><?php endif; ?></section><?php endif; ?>
            <section id="mastery-paths" class="mb-teacher-panel"><div class="mb-teacher-heading"><div><small>Instructional engine</small><h2>Mastery Path Builder</h2><p>Select a grade, enter a title, and identify the California mathematics standard. MathBinder builds the pretest, assignments, and posttest for you to review and edit.</p></div></div>
                <?php if ($path_notice === 'published'): ?><div class="mb-teacher-review-notice is-success" role="status">Mastery path previewed, published, and assigned.</div><?php elseif ($path_notice === 'saved'): ?><div class="mb-teacher-review-notice is-success" role="status">Mastery path draft saved.</div><?php elseif ($path_notice === 'invalid'): ?><div class="mb-teacher-review-notice is-error" role="alert">The path could not be published. Complete every required step, select an assignment target, and preview the student sequence.</div><?php endif; ?>
                <?php if (!$classes): ?><div class="mb-teacher-empty"><strong>A class assignment is required.</strong><p>An administrator must assign this teacher to a class before a mastery path can be published.</p></div>
                <?php else: ?><form class="mb-mastery-path-form" data-mb-mastery-builder method="post" action="<?php echo esc_url(admin_url('admin-post.php')); ?>"><input type="hidden" name="action" value="mb_teacher_mastery_path"><input type="hidden" name="path_id" value="<?php echo esc_attr($builder['id']); ?>"><input type="hidden" name="lesson_order" value="<?php echo esc_attr(implode(',', array_map('absint', (array)$builder['lesson_ids']))); ?>" data-mb-lesson-order><?php wp_nonce_field('mb_teacher_mastery_path', 'mb_mastery_path_nonce'); ?>
                    <div class="mb-mastery-builder-head"><div><strong><?php echo $edit_path ? 'Edit generated mastery path' : 'Create a mastery path'; ?></strong><span data-mb-builder-status><?php echo esc_html(ucfirst($builder['status'])); ?></span></div><ol aria-label="Builder progress"><?php foreach (['Describe','Review & edit','Preview & publish'] as $index=>$label): ?><li class="<?php echo $index===0?'is-active':''; ?>" data-mb-step-dot="<?php echo $index+1; ?>"><b><?php echo $index+1; ?></b><span><?php echo esc_html($label); ?></span></li><?php endforeach; ?></ol></div>
                    <section class="mb-mastery-step is-active" data-mb-step="1"><span class="mb-mastery-step-kicker">Step 1 of 3</span><h3>What should MathBinder build?</h3><p>Select the grade, enter the title, and identify the California mathematics standard.</p><div class="mb-mastery-path-grid"><label>Grade level<select name="mastery_grade_level" required><option value="">Select grade</option><?php foreach (array_merge(['K'], array_map('strval', range(1, 12))) as $grade_option): ?><option value="<?php echo esc_attr($grade_option); ?>" <?php selected((string)$builder['grade_level'], $grade_option); ?>><?php echo $grade_option === 'K' ? 'Kindergarten' : 'Grade '.esc_html($grade_option); ?></option><?php endforeach; ?></select></label><label>Topic or unit title<input type="text" name="path_title" maxlength="160" value="<?php echo esc_attr($builder['title']); ?>" required placeholder="Example: Ratios and proportional relationships"></label><label>Target California standard<input type="text" name="target_standard" maxlength="1000" value="<?php echo esc_attr($builder['standard']); ?>" required placeholder="Example: 7.RP.A.2 or paste the complete standard wording"></label></div><button type="button" class="mb-teacher-primary" data-mb-generate><?php echo $edit_path ? 'Regenerate Draft' : 'Generate Pretest, Assignments & Posttest'; ?></button><p class="mb-mastery-path-note" data-mb-generation-status>MathBinder verifies the grade and standard before generating. Nothing publishes until you review and approve it.</p></section>
                    <section class="mb-mastery-step" data-mb-step="2" hidden><span class="mb-mastery-step-kicker">Step 2 of 3</span><h3>Review and edit MathBinder's draft</h3><p>Every field below is editable. Adjust questions, directions, assignments, or routes before previewing.</p><input type="hidden" name="mastery_threshold" value="<?php echo absint($builder['mastery_threshold']); ?>"><div class="mb-generated-card"><h4>Learning goal</h4><label>Learning objective<textarea name="objectives" rows="3" maxlength="3000" required><?php echo esc_textarea($builder['objectives']); ?></textarea></label><label>Prerequisites<textarea name="prerequisites" rows="3" maxlength="3000" required><?php echo esc_textarea($builder['prerequisites']); ?></textarea></label></div><div class="mb-generated-card"><h4>Diagnostic pretest · 8 questions</h4><label>Pretest title<input type="text" name="pretest_title" maxlength="160" value="<?php echo esc_attr($builder['pretest']['title']); ?>" required></label><label>Directions and editable questions<textarea name="pretest_instructions" rows="13" maxlength="6000" required><?php echo esc_textarea($builder['pretest']['instructions']); ?></textarea></label><input type="hidden" name="pretest_url" value="<?php echo esc_attr($builder['pretest']['url']); ?>"></div><div class="mb-generated-card"><h4>Differentiated assignments</h4><div class="mb-mastery-branch-grid"><label>Foundational route<textarea name="foundational" rows="5" maxlength="3000" required><?php echo esc_textarea($builder['branches']['foundational']); ?></textarea></label><label>Developing route<textarea name="developing" rows="5" maxlength="3000" required><?php echo esc_textarea($builder['branches']['developing']); ?></textarea></label><label>Near-mastery route<textarea name="near_mastery" rows="5" maxlength="3000" required><?php echo esc_textarea($builder['branches']['near_mastery']); ?></textarea></label><label>Extension route (80%+)<textarea name="extension" rows="5" maxlength="3000" required><?php echo esc_textarea($builder['branches']['extension']); ?></textarea></label></div><details><summary>Review suggested MathBinder lessons</summary><div class="mb-mastery-sequence-layout"><div class="mb-mastery-lesson-list"><?php foreach ($lessons as $lesson): $checked=in_array((int)$lesson->ID,array_map('intval',(array)$builder['lesson_ids']),true); ?><label><input type="checkbox" data-mb-lesson value="<?php echo absint($lesson->ID); ?>" data-title="<?php echo esc_attr($lesson->post_title); ?>" <?php checked($checked); ?>> <?php echo esc_html($lesson->post_title); ?></label><?php endforeach; ?></div><ol class="mb-mastery-selected" data-mb-selected-lessons></ol></div></details></div><div class="mb-generated-card"><h4>Evidence Folder</h4><label>Generated evidence requirement<textarea name="evidence_requirements" rows="5" maxlength="4000" required><?php echo esc_textarea($builder['evidence_requirements']); ?></textarea></label></div><div class="mb-generated-card"><h4>Posttest · equivalent mastery check</h4><label>Posttest title<input type="text" name="posttest_title" maxlength="160" value="<?php echo esc_attr($builder['posttest']['title']); ?>" required></label><label>Directions and editable questions<textarea name="posttest_instructions" rows="13" maxlength="6000" required><?php echo esc_textarea($builder['posttest']['instructions']); ?></textarea></label><input type="hidden" name="posttest_url" value="<?php echo esc_attr($builder['posttest']['url']); ?>"></div><div class="mb-generated-card"><h4>Next routes</h4><label>Reteaching and reassessment<textarea name="reassessment" rows="4" maxlength="3000" required><?php echo esc_textarea($builder['reassessment']); ?></textarea></label><label>Extension after mastery<textarea name="extension_activity" rows="4" maxlength="3000"><?php echo esc_textarea($builder['extension_activity']); ?></textarea></label></div></section>
                    <section class="mb-mastery-step" data-mb-step="3" hidden><span class="mb-mastery-step-kicker">Step 3 of 3</span><h3>Preview and approve</h3><div class="mb-mastery-path-grid"><label>Assign to class or student<select name="target" required><optgroup label="Classes"><?php foreach ($classes as $class): $selected=$builder['target_type']==='class'&&(int)$builder['target_id']===(int)$class['id']; ?><option value="class:<?php echo absint($class['id']); ?>" <?php selected($selected); ?>><?php echo esc_html($class['name'].($class['section_name'] ? ' · '.$class['section_name'] : '')); ?></option><?php endforeach; ?></optgroup><?php if ($students): ?><optgroup label="Individual students"><?php foreach ($students as $student): $selected=$builder['target_type']==='student'&&(int)$builder['target_id']===(int)$student['user_id']; ?><option value="student:<?php echo absint($student['user_id']); ?>" <?php selected($selected); ?>><?php echo esc_html($student['display_name'].' · '.$student['class_name']); ?></option><?php endforeach; ?></optgroup><?php endif; ?></select></label><label>Due date (optional)<input type="date" name="due_date" value="<?php echo esc_attr($builder['due_date']); ?>"></label></div><p>Review the complete student experience. Publishing remains locked until this preview has been opened.</p><button class="mb-mastery-preview" type="button" data-mb-preview>Open Complete Preview</button><div class="mb-mastery-preview-status" data-mb-preview-status>Preview required before publishing.</div></section>
                    <div class="mb-mastery-builder-actions"><button type="button" data-mb-prev disabled>Back</button><button type="submit" name="save_mode" value="draft">Save Draft</button><button type="button" class="is-primary" data-mb-next>Continue</button><button type="submit" class="is-primary" name="save_mode" value="published" data-mb-publish disabled>Approve &amp; Publish</button></div>
                    <dialog class="mb-student-preview" data-mb-preview-dialog><div class="mb-preview-title"><div><small>Student preview</small><h3 data-preview-title>Mastery Path</h3></div><button type="button" data-mb-close-preview aria-label="Close preview">×</button></div><ol data-mb-preview-sequence></ol><button type="button" class="mb-mastery-publish" data-mb-close-preview>Return to Builder</button></dialog>
                </form><?php endif; ?>
                <div class="mb-mastery-path-list"><h3>Saved Mastery Paths</h3><?php if (!$paths): ?><div class="mb-teacher-empty"><strong>No mastery paths saved yet.</strong><p>Your drafts and published assignments will appear here.</p></div><?php else: ?><?php foreach ($paths as $path): ?><article><div><small><?php echo esc_html(ucwords(str_replace('_',' ', $path['target_type'] ?? 'class'))); ?> assignment</small><strong><?php echo esc_html($path['title']); ?></strong><span><?php echo absint($path['mastery_threshold']); ?>% mastery · <?php echo count((array)($path['lesson_ids'] ?? [])); ?> lesson(s)<?php echo !empty($path['due_date']) ? ' · Due '.esc_html(wp_date(get_option('date_format'), strtotime($path['due_date']))) : ''; ?></span></div><div class="mb-mastery-list-actions"><b class="is-<?php echo esc_attr($path['status'] ?? 'published'); ?>"><?php echo esc_html(ucfirst($path['status'] ?? 'published')); ?></b><a href="<?php echo esc_url(add_query_arg('edit_path',$path['id'],get_permalink()).'#mastery-paths'); ?>">Edit</a></div></article><?php endforeach; ?><?php endif; ?></div>
            </section>
            <section id="evidence" class="mb-teacher-panel"><div class="mb-teacher-heading"><div><small>Completed lesson record</small><h2>Evidence Review</h2></div></div>
                <?php if (!$selected): ?><div class="mb-teacher-empty"><strong>Select a student from the progress table.</strong><p>The student’s synced completed lessons and notes indicators will appear here for review.</p></div>
                <?php else: ?><h3><?php echo esc_html($selected['display_name']); ?></h3>
                    <?php if ($review_notice === 'saved'): ?><div class="mb-teacher-review-notice is-success" role="status">Evidence review saved.</div><?php elseif ($review_notice === 'feedback_required'): ?><div class="mb-teacher-review-notice is-error" role="alert">Add feedback before saving that decision.</div><?php endif; ?>
                    <div class="mb-teacher-evidence-list"><?php $found=false; foreach ($selected['metrics']['activity']['lessons'] as $lesson_id=>$lesson): if (empty($lesson['completed'])) continue; $found=true; $review=$reviews[(string)$lesson_id] ?? []; ?><article class="mb-teacher-evidence-card">
                        <div class="mb-teacher-evidence-summary"><div><small><?php echo esc_html($lesson['section'] ?? 'MathBinder'); ?></small><strong><?php echo esc_html($lesson['title'] ?? 'Completed lesson'); ?></strong></div><span><?php echo !empty($lesson['hasNotes']) ? 'Notes included' : 'Completed'; ?></span><?php if (!empty($lesson['url'])): ?><a href="<?php echo esc_url($lesson['url']); ?>">Open lesson</a><?php endif; ?></div>
                        <?php if ($review): ?><div class="mb-teacher-review-history"><strong><?php echo esc_html($review['decision']==='mastered' ? 'Mastered' : ($review['decision']==='revision_requested' ? 'Revision requested' : 'Feedback sent')); ?></strong><span>Reviewed <?php echo esc_html(wp_date(get_option('date_format'), strtotime($review['reviewed_at']))); ?> by <?php echo esc_html($review['teacher_name']); ?></span><?php if (!empty($review['feedback'])): ?><p><?php echo esc_html($review['feedback']); ?></p><?php endif; ?></div><?php endif; ?>
                        <form class="mb-teacher-review-form" method="post" action="<?php echo esc_url(admin_url('admin-post.php')); ?>"><input type="hidden" name="action" value="mb_teacher_evidence_review"><input type="hidden" name="student_id" value="<?php echo absint($selected_id); ?>"><input type="hidden" name="lesson_id" value="<?php echo esc_attr($lesson_id); ?>"><?php wp_nonce_field('mb_teacher_evidence_review', 'mb_teacher_review_nonce'); ?><label>Teacher feedback<textarea name="feedback" rows="3" maxlength="2000" placeholder="Share specific, helpful feedback with this student."><?php echo esc_textarea($review['feedback'] ?? ''); ?></textarea></label><div class="mb-teacher-review-actions"><button type="submit" name="decision" value="feedback">Save Feedback</button><button class="is-revision" type="submit" name="decision" value="revision_requested">Request Revision</button><button class="is-mastered" type="submit" name="decision" value="mastered">Mark Mastered</button></div></form>
                    </article><?php endforeach; if(!$found): ?><div class="mb-teacher-empty"><strong>No completed lesson evidence yet.</strong><p>Activity will appear after the student marks a lesson complete.</p></div><?php endif; ?></div><?php endif; ?>
            </section>
            <section id="canvas" class="mb-teacher-panel mb-canvas-panel"><div class="mb-teacher-heading"><div><small>Integration foundation</small><h2>Canvas</h2><p>Prepare MathBinder mastery paths for a future Canvas LTI 1.3 sandbox connection.</p></div><span class="mb-canvas-status <?php echo $canvas_status['adapter_ready'] ? 'is-ready' : 'is-off'; ?>"><?php echo esc_html($canvas_status['label']); ?></span></div>
                <?php if ($canvas_notice === 'prepared'): ?><div class="mb-teacher-review-notice is-success" role="status">Assignment prepared for Canvas. Nothing was sent because the live connection is disabled.</div><?php endif; ?>
                <div class="mb-canvas-safety"><strong>Safe staging mode</strong><p><?php echo esc_html($canvas_status['detail']); ?></p><p>Canvas IDs remain external mappings. They never replace permanent MathBinder student, class, assignment, or grade records.</p></div>
                <?php if (!$published_paths): ?><div class="mb-teacher-empty"><strong>No published mastery paths are ready.</strong><p>Publish a MathBinder Mastery Path before preparing a Canvas assignment.</p></div>
                <?php else: ?><div class="mb-canvas-path-list"><?php foreach ($published_paths as $path): $prepared = false; foreach ($canvas_queue as $queued) if (($queued['mathbinder_id'] ?? '') === ($path['id'] ?? '')) $prepared = $queued; ?><article><div><small><?php echo esc_html(ucwords(str_replace('_',' ', $path['target_type']))); ?> assignment</small><strong><?php echo esc_html($path['title']); ?></strong><span><?php echo $prepared ? 'Ready for Canvas setup' : 'MathBinder only'; ?></span></div><?php if ($prepared): ?><b>Prepared</b><?php else: ?><form method="post" action="<?php echo esc_url(admin_url('admin-post.php')); ?>"><input type="hidden" name="action" value="mb_teacher_canvas_prepare"><input type="hidden" name="path_id" value="<?php echo esc_attr($path['id']); ?>"><?php wp_nonce_field('mb_teacher_canvas_prepare', 'mb_canvas_prepare_nonce'); ?><button type="submit">Prepare for Canvas</button></form><?php endif; ?></article><?php endforeach; ?></div><?php endif; ?>
                <p class="mb-canvas-future"><strong>Integration contracts defined:</strong> Deep Linking, Assignment and Grade Services line items and scores, autograding with teacher override, Canvas launches, and secure evidence handoff for SpeedGrader. Live transport remains disabled until a separately authenticated sandbox adapter passes every activation gate.</p>
            </section>
        </div><?php return ob_get_clean();
    }
}
