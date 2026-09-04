<?php
if (!defined('ABSPATH')) exit;

/** Instructor lesson selection and selected-lesson resource launches for Canvas. */
final class MathBinder_Canvas_Deep_Linking {
    const LAUNCH_PREFIX = 'mb_canvas_lesson_picker_';
    const RESOURCE_PREFIX = 'mb_canvas_resource_launch_';
    const LAUNCH_TTL = 20 * MINUTE_IN_SECONDS;

    public static function register() {
        add_action('template_redirect', [__CLASS__, 'authenticate_resource_session'], 0);
        add_action('template_redirect', [__CLASS__, 'route_pages'], 1);
        add_action('wp_enqueue_scripts', [__CLASS__, 'suppress_embedded_cookie_assets'], PHP_INT_MAX);
        add_action('wp_head', [__CLASS__, 'embedded_privacy_guard'], PHP_INT_MAX);
        add_filter('body_class', [__CLASS__, 'embedded_body_class']);
    }

    public static function embedded_body_class($classes) {
        if (self::current_resource_launch(get_queried_object_id())) $classes[] = 'mb-canvas-resource-launch';
        return $classes;
    }

    public static function suppress_embedded_cookie_assets() {
        if (!self::current_resource_launch(get_queried_object_id())) return;
        global $wp_scripts, $wp_styles;
        foreach ((array)($wp_scripts->queue ?? []) as $handle) {
            $src=(string)($wp_scripts->registered[$handle]->src ?? '');
            if (stripos($handle.' '.$src,'cookieadmin')!==false) wp_dequeue_script($handle);
        }
        foreach ((array)($wp_styles->queue ?? []) as $handle) {
            $src=(string)($wp_styles->registered[$handle]->src ?? '');
            if (stripos($handle.' '.$src,'cookieadmin')!==false) wp_dequeue_style($handle);
        }
    }

    public static function embedded_privacy_guard() {
        if (!self::current_resource_launch(get_queried_object_id())) return;
        ?><style id="mb-canvas-cookie-suppression">body.mb-canvas-resource-launch [id*="cookieadmin" i],body.mb-canvas-resource-launch [class*="cookieadmin" i],body.mb-canvas-resource-launch iframe[src*="cookieadmin" i]{display:none!important;visibility:hidden!important;pointer-events:none!important}</style>
        <script id="mb-canvas-cookie-suppression-script">document.addEventListener('DOMContentLoaded',function(){var hide=function(){document.querySelectorAll('body *').forEach(function(node){if(node.children.length>12)return;var text=(node.textContent||'').trim();if(text.indexOf('Powered by CookieAdmin')===-1)return;var target=node.closest('[role="dialog"],dialog')||node;while(target.parentElement&&target.parentElement!==document.body&&getComputedStyle(target).position!=='fixed')target=target.parentElement;target.style.setProperty('display','none','important');});};hide();new MutationObserver(hide).observe(document.body,{childList:true,subtree:true});});</script><?php
    }

    public static function authenticate_resource_session() {
        if (isset($_GET['mb_canvas_launch'])) self::current_resource_launch();
    }

    public static function begin_launch(array $result) {
        $message_type = (string)($result['message_type'] ?? '');
        if ($message_type === 'LtiResourceLinkRequest') return self::begin_resource_launch($result);
        if ($message_type !== 'LtiDeepLinkingRequest') return null;

        $roles = (array)($result['roles'] ?? []);
        if (self::is_learner($roles)) return null;
        if (!self::is_instructor($roles)) {
            return new WP_Error('mb_canvas_teacher_role', 'A Canvas instructor or administrator role is required to select a MathBinder lesson.', ['status'=>403]);
        }

        $claims = (array)($result['claims'] ?? []);
        $context = (array)($result['context'] ?? []);
        $subject = (string)($claims['sub'] ?? '');
        $settings = MathBinder_Canvas_Settings::get();
        if (!self::approved_mappings($subject, (string)($context['id'] ?? ''), $settings)) {
            return new WP_Error('mb_canvas_mapping_pending', 'The Canvas course and instructor identity must be approved in MathBinder before selecting a lesson.', ['status'=>403]);
        }

        $deep = (array)($claims['https://purl.imsglobal.org/spec/lti-dl/claim/deep_linking_settings'] ?? []);
        $return_url = esc_url_raw((string)($deep['deep_link_return_url'] ?? ''));
        if ($return_url === '' || !self::trusted_canvas_url($return_url, $settings)) {
            return new WP_Error('mb_canvas_return_url', 'Canvas did not provide an approved Deep Linking return URL.', ['status'=>400]);
        }

        $token = self::sign_launch([
            'return_url'=>$return_url,
            'data'=>(string)($deep['data'] ?? ''),
            'aud'=>rtrim((string)($claims['iss'] ?? $settings['platform_issuer'] ?? $settings['canvas_url']), '/'),
            'context_id'=>(string)($context['id'] ?? ''),
            'external_user_id'=>$subject,
            'created_at'=>time(),
        ]);

        return new WP_REST_Response(null, 303, ['Location'=>add_query_arg('mathbinder_canvas_lesson_picker', $token, home_url('/'))]);
    }

    public static function route_pages() {
        if (isset($_GET['mathbinder_canvas_select_lesson']) || isset($_GET['mathbinder_canvas_lesson_picker'])) {
            if (!defined('DONOTCACHEPAGE')) define('DONOTCACHEPAGE', true);
            if (!defined('DONOTCACHEOBJECT')) define('DONOTCACHEOBJECT', true);
        }
        if (isset($_GET['mathbinder_canvas_select_lesson']) && ($_SERVER['REQUEST_METHOD'] ?? '') === 'POST') {
            self::select_lesson();
        }
        if (isset($_GET['mathbinder_canvas_lesson_picker'])) {
            self::render_picker(sanitize_text_field(wp_unslash($_GET['mathbinder_canvas_lesson_picker'])));
        }
    }

    public static function select_lesson() {
        $token = sanitize_text_field(wp_unslash($_POST['launch_token'] ?? ''));
        $launch = self::launch($token);
        if (!$launch) self::page('Canvas lesson selection expired', 'Return to the Canvas assignment and open Select MathBinder Lesson again.');

        $lesson_id = absint($_POST['lesson_id'] ?? 0);
        $lesson = $lesson_id ? get_post($lesson_id) : null;
        if (!$lesson || $lesson->post_type !== MathBinder_Core::CPT || $lesson->post_status !== 'publish') {
            self::page('Choose a published MathBinder lesson', 'Return to the lesson list and select one published Binder Page.');
        }

        $item = [
            'type'=>'ltiResourceLink',
            'title'=>sanitize_text_field(get_the_title($lesson)),
            'text'=>sanitize_text_field((string)(get_the_excerpt($lesson) ?: 'Open this lesson in MathBinder.')),
            'url'=>rest_url(MathBinder_Canvas_Transport::REST_NAMESPACE . '/canvas/lti/launch'),
            'presentation'=>['documentTarget'=>'window'],
            'custom'=>[
                'mathbinder_lesson_id'=>(string)$lesson_id,
                'mathbinder_lesson_slug'=>(string)$lesson->post_name,
            ],
        ];
        $jwt = MathBinder_Canvas_Integration::adapter()->create_deep_link([
            'aud'=>$launch['aud'],
            'data'=>$launch['data'],
            'content_items'=>[$item],
        ]);
        if (is_wp_error($jwt)) self::page('Canvas lesson link could not be signed', $jwt->get_error_message());

        MathBinder_Audit_Log::record('select', 'canvas_lesson', $lesson_id, [
            'context_hash'=>hash('sha256', (string)$launch['context_id']),
            'subject_hash'=>hash('sha256', (string)$launch['external_user_id']),
        ]);
        self::return_to_canvas((string)$launch['return_url'], $jwt, get_the_title($lesson));
    }

    private static function begin_resource_launch(array $result) {
        $claims = (array)($result['claims'] ?? []);
        $custom = (array)($claims['https://purl.imsglobal.org/spec/lti/claim/custom'] ?? []);
        $lesson_id = absint($custom['mathbinder_lesson_id'] ?? 0);
        if (!$lesson_id) return null;

        $context = (array)($result['context'] ?? []);
        $settings = MathBinder_Canvas_Settings::get();
        $user_mapping = MathBinder_Canvas_Repository::mapping('user', (string)($claims['sub'] ?? ''), $settings);
        $context_mapping = MathBinder_Canvas_Repository::mapping('context', (string)($context['id'] ?? ''), $settings);
        if (!self::mapping_is_approved($user_mapping, 'user') || !self::mapping_is_approved($context_mapping, 'class')) {
            return new WP_Error('mb_canvas_mapping_pending', 'This Canvas course and identity must be approved in MathBinder before opening the lesson.', ['status'=>403]);
        }
        $mathbinder_user_id = absint($user_mapping['mathbinder_id'] ?? 0);
        $mathbinder_user = $mathbinder_user_id ? get_userdata($mathbinder_user_id) : false;
        if (!$mathbinder_user) {
            return new WP_Error('mb_canvas_user_unavailable', 'The approved Canvas identity no longer points to an active MathBinder account.', ['status'=>403]);
        }
        wp_set_current_user($mathbinder_user_id);
        wp_set_auth_cookie($mathbinder_user_id, true, is_ssl());
        MathBinder_Audit_Log::record('authenticated', 'canvas_lti_user', $mathbinder_user_id, [
            'context_hash'=>hash('sha256', (string)($context['id'] ?? '')),
            'subject_hash'=>hash('sha256', (string)($claims['sub'] ?? '')),
        ]);
        $lesson = get_post($lesson_id);
        if (!$lesson || $lesson->post_type !== MathBinder_Core::CPT || $lesson->post_status !== 'publish') {
            return new WP_Error('mb_canvas_lesson_unavailable', 'The selected MathBinder lesson is not published.', ['status'=>404]);
        }
        $resource_token = MathBinder_Canvas_Crypto::b64url_encode(random_bytes(32));
        $user_metadata = json_decode((string)($user_mapping['metadata_json'] ?? '{}'), true);
        $ags = (array)($result['services']['ags'] ?? []);
        $current_lineitem = esc_url_raw((string)($ags['lineitem'] ?? ''));
        $current_scores_url = $current_lineitem !== '' ? rtrim($current_lineitem, '/') . '/scores' : '';
        if ($current_scores_url === '') $current_scores_url = esc_url_raw((string)($user_metadata['scores_url'] ?? ''));
        set_transient(self::RESOURCE_PREFIX . hash('sha256', $resource_token), [
            'lesson_id'=>$lesson_id,
            'user_id'=>$mathbinder_user_id,
            'class_id'=>absint($context_mapping['mathbinder_id'] ?? 0),
            'roles'=>array_values(array_map('strval', (array)($result['roles'] ?? []))),
            'context_id'=>(string)($context['id'] ?? ''),
            'external_user_id'=>(string)($user_mapping['external_id'] ?? ($claims['sub'] ?? '')),
            'scores_url'=>$current_scores_url,
            'created_at'=>time(),
        ], 12 * HOUR_IN_SECONDS);
        return new WP_REST_Response(null, 303, ['Location'=>add_query_arg('mb_canvas_launch', $resource_token, get_permalink($lesson))]);
    }

    public static function current_resource_launch($lesson_id = 0) {
        $token = sanitize_text_field(wp_unslash($_REQUEST['mb_canvas_launch'] ?? ''));
        if ($token === '' && !empty($_SERVER['HTTP_REFERER'])) {
            $query = [];
            parse_str((string)wp_parse_url(wp_unslash($_SERVER['HTTP_REFERER']), PHP_URL_QUERY), $query);
            $token = sanitize_text_field($query['mb_canvas_launch'] ?? '');
        }
        if ($token === '') {
            $assignment_token=sanitize_text_field(wp_unslash($_REQUEST['mb_canvas_assignment_session']??''));
            $assignment_launch=self::assignment_form_launch($assignment_token,$lesson_id);
            if(!$assignment_launch)return [];
            $assignment_user_id=absint($assignment_launch['user_id']??0);
            if(!$assignment_user_id||!get_userdata($assignment_user_id))return [];
            wp_set_current_user($assignment_user_id);
            $assignment_launch['assignment_session']=$assignment_token;
            return $assignment_launch;
        }
        $launch = get_transient(self::RESOURCE_PREFIX . hash('sha256', $token));
        if (!is_array($launch)) return [];
        $launch_user_id = absint($launch['user_id'] ?? 0);
        if (!$launch_user_id || !get_userdata($launch_user_id)) return [];
        // The signed, high-entropy resource token is authoritative for this
        // embedded request. Canvas iframes may omit the MathBinder cookie or
        // send an unrelated existing site session (including an administrator
        // testing in the same browser). Do not reject a valid launch because
        // of that ambient cookie; scope WordPress to the mapped Canvas user.
        wp_set_current_user($launch_user_id);
        if ($lesson_id && absint($launch['lesson_id'] ?? 0) !== absint($lesson_id)) return [];
        // Preserve the opaque token for server-rendered assignment forms. Some
        // embedded browsers omit the launch query from form posts/referrers.
        $launch['resource_token'] = $token;
        return $launch;
    }

    public static function resource_launch_is_student(array $launch) {
        foreach ((array)($launch['roles'] ?? []) as $role) {
            if (stripos((string)$role, 'learner') !== false || stripos((string)$role, 'student') !== false) return true;
        }
        return false;
    }

    /**
     * Create a self-contained assignment POST session from an already
     * validated Canvas resource launch. This survives iframe cookie blocking,
     * canonical redirects, and transient/object-cache eviction after render.
     */
    public static function assignment_form_token(array $launch) {
        $payload = [
            'purpose'=>'canvas_assignment_form',
            'lesson_id'=>absint($launch['lesson_id']??0),
            'user_id'=>absint($launch['user_id']??0),
            'class_id'=>absint($launch['class_id']??0),
            'roles'=>array_values(array_map('strval',(array)($launch['roles']??[]))),
            'external_user_id'=>(string)($launch['external_user_id']??''),
            'scores_url'=>esc_url_raw((string)($launch['scores_url']??'')),
            'created_at'=>time(),
        ];
        if(!$payload['lesson_id']||!$payload['user_id']||!$payload['class_id'])return '';
        $encoded=MathBinder_Canvas_Crypto::b64url_encode(wp_json_encode($payload));
        $signature=MathBinder_Canvas_Crypto::b64url_encode(hash_hmac('sha256',$encoded,wp_salt('auth'),true));
        return $encoded.'.'.$signature;
    }

    public static function assignment_form_launch($token,$lesson_id=0) {
        $parts=explode('.',(string)$token);
        if(count($parts)!==2||$parts[0]===''||$parts[1]==='')return [];
        $expected=MathBinder_Canvas_Crypto::b64url_encode(hash_hmac('sha256',$parts[0],wp_salt('auth'),true));
        if(!hash_equals($expected,$parts[1]))return [];
        $decoded=MathBinder_Canvas_Crypto::b64url_decode($parts[0]);
        $launch=is_string($decoded)?json_decode($decoded,true):null;
        if(!is_array($launch)||($launch['purpose']??'')!=='canvas_assignment_form')return [];
        $created=absint($launch['created_at']??0);
        if(!$created||$created>time()+60||$created<time()-(12*HOUR_IN_SECONDS))return [];
        if($lesson_id&&absint($launch['lesson_id']??0)!==absint($lesson_id))return [];
        if(!absint($launch['user_id']??0)||!absint($launch['class_id']??0))return [];
        return $launch;
    }

    private static function render_picker($token) {
        $launch = self::launch($token);
        if (!$launch) self::page('Canvas lesson selection expired', 'Return to the Canvas assignment and open Select MathBinder Lesson again.');
        $lessons = get_posts([
            'post_type'=>MathBinder_Core::CPT,
            'post_status'=>'publish',
            'posts_per_page'=>-1,
            'orderby'=>'title',
            'order'=>'ASC',
            'suppress_filters'=>false,
        ]);
        status_header(200);
        nocache_headers();
        header('Content-Type: text/html; charset=' . get_option('blog_charset'));
        ?><!doctype html><html><head><meta charset="<?php bloginfo('charset'); ?>"><meta name="viewport" content="width=device-width,initial-scale=1"><title>Select a MathBinder Lesson</title><style><?php echo self::page_css(); ?></style></head><body><main><header><span>MATHBINDER + CANVAS</span><h1>Select a lesson</h1><p>Choose one published Binder Page to add to this Canvas assignment.</p></header><?php if (!$lessons): ?><section><h2>No published lessons are available</h2><p>Publish at least one Binder Page in MathBinder, then reopen this tool from Canvas.</p></section><?php else: ?><label class="search"><strong>Search lessons</strong><input id="lesson-search" type="search" placeholder="Type a topic, title, grade, or section" autocomplete="off"></label><form method="post" action="<?php echo esc_url(add_query_arg('mathbinder_canvas_select_lesson','1',home_url('/'))); ?>"><input type="hidden" name="launch_token" value="<?php echo esc_attr($token); ?>"><div class="lessons" id="lesson-list"><?php foreach ($lessons as $lesson): $terms=wp_get_post_terms($lesson->ID,MathBinder_Core::TAX,['fields'=>'names']);$section=is_wp_error($terms)?'':implode(' · ',$terms);$grade=(string)get_post_meta($lesson->ID,'_mb_grade',true);$keywords=strtolower(get_the_title($lesson).' '.$section.' '.$grade); ?><label class="lesson" data-keywords="<?php echo esc_attr($keywords); ?>"><input type="radio" name="lesson_id" value="<?php echo absint($lesson->ID); ?>" required><span><strong><?php echo esc_html(get_the_title($lesson)); ?></strong><small><?php echo esc_html(implode(' · ',array_filter([$section,$grade!==''?'Grade '.$grade:'']))); ?></small></span></label><?php endforeach; ?></div><p id="no-results" hidden>No matching lessons found.</p><button type="submit">Add selected lesson to Canvas</button></form><script>(function(){var q=document.getElementById('lesson-search'),rows=[].slice.call(document.querySelectorAll('.lesson')),empty=document.getElementById('no-results');q.addEventListener('input',function(){var term=q.value.trim().toLowerCase(),shown=0;rows.forEach(function(row){var match=!term||row.dataset.keywords.indexOf(term)!==-1;row.hidden=!match;if(match)shown++;});empty.hidden=shown!==0;});})();</script><?php endif; ?><footer>Only the lesson link is returned to Canvas. MathBinder remains the authoritative lesson source.</footer></main></body></html><?php exit;
    }

    private static function return_to_canvas($return_url, $jwt, $title) {
        status_header(200);
        nocache_headers();
        header('Content-Type: text/html; charset=' . get_option('blog_charset'));
        ?><!doctype html><html><head><meta charset="<?php bloginfo('charset'); ?>"><meta name="viewport" content="width=device-width,initial-scale=1"><title>Returning to Canvas</title><style><?php echo self::page_css(); ?></style></head><body><main><section><h1>Adding <?php echo esc_html($title); ?>…</h1><p>The signed lesson link is being returned to your Canvas assignment.</p><form id="mb-return" method="post" action="<?php echo esc_url($return_url); ?>"><input type="hidden" name="JWT" value="<?php echo esc_attr($jwt); ?>"><button type="submit">Return to Canvas</button></form></section></main><script>document.getElementById('mb-return').submit();</script></body></html><?php exit;
    }

    private static function launch($token) {
        $parts = explode('.', (string)$token);
        if (count($parts) !== 2 || $parts[0] === '' || $parts[1] === '') return [];
        $expected = MathBinder_Canvas_Crypto::b64url_encode(hash_hmac('sha256', $parts[0], wp_salt('auth'), true));
        if (!hash_equals($expected, $parts[1])) return [];
        $decoded = MathBinder_Canvas_Crypto::b64url_decode($parts[0]);
        if (!is_string($decoded)) return [];
        $value = json_decode($decoded, true);
        if (!is_array($value) || empty($value['created_at'])) return [];
        $created = (int)$value['created_at'];
        if ($created > time() + 60 || $created < time() - self::LAUNCH_TTL) return [];
        return $value;
    }

    private static function sign_launch(array $launch) {
        $payload = MathBinder_Canvas_Crypto::b64url_encode(wp_json_encode($launch));
        $signature = MathBinder_Canvas_Crypto::b64url_encode(hash_hmac('sha256', $payload, wp_salt('auth'), true));
        return $payload . '.' . $signature;
    }

    private static function approved_mappings($subject, $context_id, array $settings) {
        $user = MathBinder_Canvas_Repository::mapping('user', $subject, $settings);
        $context = MathBinder_Canvas_Repository::mapping('context', $context_id, $settings);
        return self::mapping_is_approved($user, 'user') && self::mapping_is_approved($context, 'class');
    }

    private static function mapping_is_approved($mapping, $type) {
        return is_array($mapping) && ($mapping['status'] ?? '') === 'approved' && ($mapping['mathbinder_type'] ?? '') === $type && absint($mapping['mathbinder_id'] ?? 0) > 0;
    }

    private static function is_learner(array $roles) {
        foreach ($roles as $role) if (stripos((string)$role, 'learner') !== false || stripos((string)$role, 'student') !== false) return true;
        return false;
    }

    private static function is_instructor(array $roles) {
        foreach ($roles as $role) if (stripos((string)$role, 'instructor') !== false || stripos((string)$role, 'administrator') !== false || stripos((string)$role, 'teachingassistant') !== false || stripos((string)$role, 'teacher') !== false) return true;
        return false;
    }

    private static function trusted_canvas_url($url, array $settings) {
        $candidate = wp_parse_url($url);
        $canvas = wp_parse_url($settings['canvas_url'] ?? '');
        return is_array($candidate) && is_array($canvas) && strtolower((string)($candidate['scheme'] ?? '')) === 'https' && strtolower((string)($candidate['host'] ?? '')) === strtolower((string)($canvas['host'] ?? '')) && absint($candidate['port'] ?? 443) === absint($canvas['port'] ?? 443);
    }

    private static function page_css() {
        return 'body{margin:0;background:#f3f8fa;color:#17233b;font:16px system-ui,sans-serif}main{max-width:980px;margin:0 auto;padding:24px}header,section,.search,form{background:#fff;border:1px solid #d7e2e7;border-radius:16px;padding:22px;margin-bottom:16px}header{border-left:8px solid #078b8f}header span{color:#08777b;font-weight:900;letter-spacing:.12em}h1{margin:.35em 0;color:#4c2691}.search{display:grid;gap:8px}.search input{padding:13px;border:1px solid #9aa9b6;border-radius:9px;font:inherit}.lessons{display:grid;grid-template-columns:repeat(auto-fit,minmax(280px,1fr));gap:10px;max-height:460px;overflow:auto}.lesson{display:flex;gap:12px;padding:14px;border:1px solid #d8cff3;border-radius:11px;cursor:pointer}.lesson:hover{background:#f7f3ff}.lesson input{width:20px;height:20px}.lesson span{display:grid;gap:4px}.lesson small{color:#59677a}.lesson[hidden]{display:none}button{margin-top:18px;padding:14px 20px;border:0;border-radius:10px;background:#6d28d9;color:#fff;font-weight:900;font-size:1rem}footer{text-align:center;color:#687487;padding:14px}';
    }

    private static function page($title, $message) {
        status_header(400);
        nocache_headers();
        ?><!doctype html><html><head><meta name="viewport" content="width=device-width,initial-scale=1"><title><?php echo esc_html($title); ?></title><style><?php echo self::page_css(); ?></style></head><body><main><section><h1><?php echo esc_html($title); ?></h1><p><?php echo esc_html($message); ?></p></section></main></body></html><?php exit;
    }
}
