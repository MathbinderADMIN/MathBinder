<?php
if (!defined('ABSPATH')) exit;

final class MathBinder_REST_Controller {
    const NAMESPACE = 'mathbinder/v1';

    public static function register() {
        add_action('rest_api_init', [__CLASS__, 'routes']);
    }

    public static function routes() {
        register_rest_route(self::NAMESPACE, '/student/dashboard', [
            'methods' => WP_REST_Server::READABLE,
            'callback' => [__CLASS__, 'student_dashboard'],
            'permission_callback' => function () {
                return MathBinder_Capabilities::can_view_student_dashboard();
            },
        ]);
        register_rest_route(self::NAMESPACE, '/student/activity', [
            'methods' => WP_REST_Server::EDITABLE,
            'callback' => [__CLASS__, 'save_student_activity'],
            'permission_callback' => 'is_user_logged_in',
        ]);
        register_rest_route(self::NAMESPACE, '/student/preferences', [
            'methods' => WP_REST_Server::EDITABLE,
            'callback' => [__CLASS__, 'save_student_preferences'],
            'permission_callback' => function () { return MathBinder_Capabilities::can_view_student_dashboard(); },
        ]);
        register_rest_route(self::NAMESPACE, '/student/join-class', [
            'methods' => WP_REST_Server::CREATABLE,
            'callback' => [__CLASS__, 'join_class'],
            'permission_callback' => function () { return MathBinder_Capabilities::can_view_student_dashboard(); },
        ]);
        register_rest_route(self::NAMESPACE, '/identity/me', [
            'methods' => WP_REST_Server::READABLE,
            'callback' => [__CLASS__, 'identity_me'],
            'permission_callback' => 'is_user_logged_in',
        ]);
        register_rest_route(self::NAMESPACE, '/identity/workspace', [
            'methods' => WP_REST_Server::EDITABLE,
            'callback' => [__CLASS__, 'select_workspace'],
            'permission_callback' => 'is_user_logged_in',
            'args' => ['assignment_id'=>['required'=>true, 'type'=>'integer', 'sanitize_callback'=>'absint']],
        ]);
    }

    public static function student_dashboard() {
        $data = MathBinder_Student_Dashboard::data_for_user(get_current_user_id());
        MathBinder_Audit_Log::record('view', 'student_dashboard');
        return rest_ensure_response([
            'data' => $data,
            'meta' => [
                'api_version' => 'v1',
                'is_fixture' => !empty($data['is_fixture']),
            ],
        ]);
    }

    public static function save_student_activity(WP_REST_Request $request) {
        $payload = $request->get_json_params();
        $lessons = isset($payload['lessons']) && is_array($payload['lessons']) ? $payload['lessons'] : [];
        $clean = ['version'=>1, 'lastLessonId'=>'', 'lessons'=>[]];
        if (!empty($payload['lastLessonId'])) $clean['lastLessonId'] = sanitize_text_field((string)$payload['lastLessonId']);
        foreach (array_slice($lessons, 0, 500, true) as $id => $lesson) {
            if (!is_array($lesson)) continue;
            $key = sanitize_text_field((string)$id);
            if ($key === '') continue;
            $clean['lessons'][$key] = [
                'id'=>$key,
                'title'=>sanitize_text_field($lesson['title'] ?? ''),
                'url'=>esc_url_raw($lesson['url'] ?? ''),
                'section'=>sanitize_text_field($lesson['section'] ?? ''),
                'started'=>!empty($lesson['started']),
                'completed'=>!empty($lesson['completed']),
                'hasNotes'=>!empty($lesson['hasNotes']),
                'updatedAt'=>sanitize_text_field($lesson['updatedAt'] ?? ''),
                'completedAt'=>sanitize_text_field($lesson['completedAt'] ?? ''),
                'masteryScore'=>min(100, max(0, absint($lesson['masteryScore'] ?? 0))),
                'masteryPassed'=>!empty($lesson['masteryPassed']),
                'masteryAttempts'=>absint($lesson['masteryAttempts'] ?? 0),
                'masteryUpdatedAt'=>sanitize_text_field($lesson['masteryUpdatedAt'] ?? ''),
                'noteValues'=>isset($lesson['noteValues']) && is_array($lesson['noteValues'])
                    ? array_map(function($value){ return sanitize_textarea_field((string)$value); }, array_slice($lesson['noteValues'], 0, 4))
                    : [],
            ];
        }
        update_user_meta(get_current_user_id(), 'mb_student_activity_v1', $clean);
        update_user_meta(get_current_user_id(), 'mb_student_activity_synced_at', current_time('mysql', true));
        return rest_ensure_response(['data'=>['saved'=>true, 'lesson_count'=>count($clean['lessons'])]]);
    }

    public static function join_class(WP_REST_Request $request) {
        global $wpdb;
        $code = strtoupper(preg_replace('/[^A-Z0-9]/', '', (string) $request->get_param('class_code')));
        if ($code === '') return new WP_Error('mb_class_code_required', 'Enter a class code.', ['status'=>400]);
        $class = $wpdb->get_row($wpdb->prepare("SELECT * FROM {$wpdb->prefix}mb_classes WHERE class_code=%s AND status='active'", $code), ARRAY_A);
        if (!$class) return new WP_Error('mb_class_not_found', 'That class code is not active. Check the code and try again.', ['status'=>404]);
        $user_id = get_current_user_id();
        $existing = $wpdb->get_row($wpdb->prepare("SELECT id,status FROM {$wpdb->prefix}mb_enrollments WHERE class_id=%d AND user_id=%d AND role_key='student'", $class['id'], $user_id), ARRAY_A);
        $now = current_time('mysql', true);
        if ($existing) {
            if ($existing['status'] !== 'active') $wpdb->update($wpdb->prefix.'mb_enrollments', ['status'=>'active','source'=>'class_code','approved_by'=>0,'updated_at'=>$now], ['id'=>(int)$existing['id']]);
        } else {
            $inserted = $wpdb->insert($wpdb->prefix.'mb_enrollments', ['class_id'=>(int)$class['id'],'user_id'=>$user_id,'invited_email'=>'','role_key'=>'student','status'=>'active','source'=>'class_code','approved_by'=>0,'created_at'=>$now,'updated_at'=>$now], ['%d','%d','%s','%s','%s','%s','%d','%s','%s']);
            if (!$inserted) return new WP_Error('mb_class_join_failed', 'MathBinder could not join the class. Please ask your teacher for help.', ['status'=>500]);
        }
        MathBinder_Identity_Service::assign_role($user_id, 'student', 'class', (int)$class['id']);
        MathBinder_Audit_Log::record('join_class', 'class', (int)$class['id'], ['user_id'=>$user_id,'source'=>'class_code']);
        return rest_ensure_response(['data'=>['joined'=>true,'class_id'=>(int)$class['id'],'class_name'=>trim($class['name'].' '.$class['section_name'])]]);
    }

    public static function save_student_preferences(WP_REST_Request $request) {
        $payload = $request->get_json_params();
        $themes = ['teal','purple','blue','pink','green','gold'];
        $allowed_stickers = ['⭐','➗','🚀','🌈','💡','🎵','🔬','🏆'];
        $theme = sanitize_key($payload['theme'] ?? 'teal');
        if (!in_array($theme, $themes, true)) $theme = 'teal';
        $stickers = isset($payload['stickers']) && is_array($payload['stickers']) ? array_values(array_intersect($allowed_stickers, array_map('sanitize_text_field', $payload['stickers']))) : [];
        $preferences = ['title'=>sanitize_text_field($payload['title'] ?? 'My MathBinder'),'theme'=>$theme,'stickers'=>array_slice($stickers, 0, 8)];
        if ($preferences['title'] === '') $preferences['title'] = 'My MathBinder';
        update_user_meta(get_current_user_id(), 'mb_student_binder_preferences_v1', $preferences);
        MathBinder_Audit_Log::record('update', 'student_binder_preferences', get_current_user_id());
        return rest_ensure_response(['data'=>$preferences]);
    }

    public static function identity_me() {
        $user_id = get_current_user_id();
        return rest_ensure_response(['data'=>[
            'permanent_key'=>MathBinder_Identity_Service::permanent_key($user_id),
            'assignments'=>MathBinder_Identity_Service::assignments($user_id),
            'active_workspace'=>MathBinder_Identity_Service::active_workspace($user_id),
            'email_verified'=>(bool)get_user_meta($user_id, 'mb_email_verified_at', true),
        ], 'meta'=>['api_version'=>'v1']]);
    }

    public static function select_workspace(WP_REST_Request $request) {
        $result = MathBinder_Identity_Service::select_workspace(get_current_user_id(), $request->get_param('assignment_id'));
        if (is_wp_error($result)) return $result;
        return rest_ensure_response(['data'=>['active_workspace'=>MathBinder_Identity_Service::active_workspace(get_current_user_id())]]);
    }
}
