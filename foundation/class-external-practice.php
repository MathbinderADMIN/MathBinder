<?php
if (!defined('ABSPATH')) exit;

final class MathBinder_External_Practice {
    const META_KEY = 'mb_external_practice_records_v1';
    const ACTION = 'mb_external_practice_save';
    const DOWNLOAD_ACTION = 'mb_external_practice_download';
    const MAX_FILE_SIZE = 8388608;

    public static function register() {
        add_action('admin_post_' . self::ACTION, [__CLASS__, 'handle_save']);
        add_action('admin_post_' . self::DOWNLOAD_ACTION, [__CLASS__, 'handle_download']);
        add_action('wp_enqueue_scripts', [__CLASS__, 'enqueue_assets'], 35);
    }

    public static function enqueue_assets() {
        if (is_page('evidence-folder') || is_page(MathBinder_Teacher_Dashboard::PAGE_SLUG)) wp_enqueue_style('mathbinder-external-practice', plugins_url('assets/external-practice.css', __FILE__), [], MathBinder_Core::VERSION);
    }

    public static function records($user_id) {
        $records = get_user_meta(absint($user_id), self::META_KEY, true);
        if (!is_array($records)) return [];
        uasort($records, function($a, $b) { return strcmp((string)($b['created_at'] ?? ''), (string)($a['created_at'] ?? '')); });
        return $records;
    }

    public static function record($user_id, $record_id) {
        $records = self::records($user_id);
        return $records[(string)$record_id] ?? null;
    }

    public static function review_key($record_id) { return 'external:' . sanitize_text_field((string)$record_id); }

    private static function redirect($notice) {
        wp_safe_redirect(add_query_arg('practice_notice', sanitize_key($notice), home_url('/evidence-folder/')) . '#external-practice');
        exit;
    }

    public static function handle_save() {
        if (!is_user_logged_in() || !MathBinder_Capabilities::can_view_student_dashboard()) wp_die('Student access required.', 'Student access required', ['response'=>403]);
        check_admin_referer(self::ACTION, 'mb_external_practice_nonce');
        $user_id = get_current_user_id();
        $platform = isset($_POST['platform']) ? sanitize_text_field(wp_unslash($_POST['platform'])) : '';
        if (!in_array($platform, ['IXL','Khan Academy','DeltaMath','Other'], true)) $platform = '';
        if ($platform === 'Other') $platform = isset($_POST['other_platform']) ? sanitize_text_field(wp_unslash($_POST['other_platform'])) : '';
        $title = isset($_POST['activity_title']) ? sanitize_text_field(wp_unslash($_POST['activity_title'])) : '';
        $completed_on = isset($_POST['completed_on']) ? sanitize_text_field(wp_unslash($_POST['completed_on'])) : '';
        $result = isset($_POST['result']) ? sanitize_text_field(wp_unslash($_POST['result'])) : '';
        $time_spent = isset($_POST['time_spent']) ? absint($_POST['time_spent']) : 0;
        $activity_url = isset($_POST['activity_url']) ? esc_url_raw(wp_unslash($_POST['activity_url'])) : '';
        $topic_id = isset($_POST['topic_id']) ? absint($_POST['topic_id']) : 0;
        $reflection = isset($_POST['reflection']) ? sanitize_textarea_field(wp_unslash($_POST['reflection'])) : '';
        $topic = $topic_id ? get_post($topic_id) : null;
        $valid_date = (bool)preg_match('/^\d{4}-\d{2}-\d{2}$/', $completed_on) && strtotime($completed_on) <= current_time('timestamp') + DAY_IN_SECONDS;
        if ($platform === '' || $title === '' || !$valid_date || $result === '' || $reflection === '' || !$topic || $topic->post_type !== MathBinder_Core::CPT || $topic->post_status !== 'publish') self::redirect('invalid');
        $record_id = wp_generate_uuid4();
        $evidence = self::save_evidence_file($user_id, $record_id);
        if (is_wp_error($evidence)) self::redirect($evidence->get_error_code());
        $terms = get_the_terms($topic_id, MathBinder_Core::TAX);
        $records = self::records($user_id);
        $records[$record_id] = ['id'=>$record_id,'platform'=>$platform,'activity_title'=>$title,'completed_on'=>$completed_on,'result'=>$result,'time_spent'=>$time_spent,'activity_url'=>$activity_url,'topic_id'=>$topic_id,'topic_title'=>get_the_title($topic_id),'topic_url'=>get_permalink($topic_id),'section'=>($terms && !is_wp_error($terms)) ? $terms[0]->name : 'MathBinder','reflection'=>$reflection,'evidence'=>$evidence,'status'=>'student_reported','created_at'=>current_time('mysql', true),'updated_at'=>current_time('mysql', true)];
        update_user_meta($user_id, self::META_KEY, $records);
        MathBinder_Audit_Log::record('create', 'external_practice_record', $user_id, ['record_id'=>$record_id,'platform'=>$platform,'topic_id'=>$topic_id]);
        self::redirect('saved');
    }

    private static function save_evidence_file($user_id, $record_id) {
        if (empty($_FILES['practice_evidence']) || (int)$_FILES['practice_evidence']['error'] === UPLOAD_ERR_NO_FILE) return [];
        $file = $_FILES['practice_evidence'];
        if ((int)$file['error'] !== UPLOAD_ERR_OK || (int)$file['size'] > self::MAX_FILE_SIZE) return new WP_Error('file_invalid');
        $checked = wp_check_filetype_and_ext($file['tmp_name'], $file['name'], ['jpg'=>'image/jpeg','jpeg'=>'image/jpeg','png'=>'image/png','webp'=>'image/webp','pdf'=>'application/pdf']);
        if (empty($checked['ext']) || empty($checked['type'])) return new WP_Error('file_invalid');
        $base = trailingslashit(WP_CONTENT_DIR) . 'mathbinder-private';
        $student_dir = $base . '/' . hash_hmac('sha256', (string)$user_id, wp_salt('auth'));
        if (!wp_mkdir_p($student_dir)) return new WP_Error('file_storage');
        if (!file_exists($base . '/.htaccess')) @file_put_contents($base . '/.htaccess', "Require all denied\nDeny from all\n");
        if (!file_exists($base . '/index.php')) @file_put_contents($base . '/index.php', "<?php\nhttp_response_code(404);\n");
        $path = $student_dir . '/' . $record_id . '.' . $checked['ext'];
        if (!is_uploaded_file($file['tmp_name']) || !move_uploaded_file($file['tmp_name'], $path)) return new WP_Error('file_storage');
        @chmod($path, 0640);
        return ['path'=>$path,'name'=>sanitize_file_name($file['name']),'type'=>$checked['type'],'size'=>(int)$file['size']];
    }

    public static function evidence_url($student_id, $record_id) {
        return wp_nonce_url(add_query_arg(['action'=>self::DOWNLOAD_ACTION,'student_id'=>absint($student_id),'record_id'=>(string)$record_id], admin_url('admin-post.php')), self::DOWNLOAD_ACTION . ':' . absint($student_id) . ':' . (string)$record_id);
    }

    public static function handle_download() {
        if (!is_user_logged_in()) wp_die('Log in to view this evidence.', 'Login required', ['response'=>403]);
        $student_id = isset($_GET['student_id']) ? absint($_GET['student_id']) : 0;
        $record_id = isset($_GET['record_id']) ? sanitize_text_field(wp_unslash($_GET['record_id'])) : '';
        check_admin_referer(self::DOWNLOAD_ACTION . ':' . $student_id . ':' . $record_id);
        $viewer_id = get_current_user_id();
        if ($viewer_id !== $student_id && !MathBinder_Teacher_Dashboard::teacher_authorized_for_student($viewer_id, $student_id) && !user_can($viewer_id, 'manage_options')) wp_die('This evidence file is not available to your account.', 'Evidence unavailable', ['response'=>403]);
        $record = self::record($student_id, $record_id); $evidence = is_array($record) ? ($record['evidence'] ?? []) : [];
        $base = realpath(trailingslashit(WP_CONTENT_DIR) . 'mathbinder-private'); $real = !empty($evidence['path']) ? realpath($evidence['path']) : false;
        if (!$base || !$real || strpos($real, $base . DIRECTORY_SEPARATOR) !== 0 || !is_file($real)) wp_die('The evidence file could not be found.', 'Evidence unavailable', ['response'=>404]);
        nocache_headers(); header('X-Content-Type-Options: nosniff'); header('Content-Type: ' . sanitize_mime_type($evidence['type'] ?? 'application/octet-stream')); header('Content-Disposition: attachment; filename="' . sanitize_file_name($evidence['name'] ?? basename($real)) . '"'); header('Content-Length: ' . filesize($real)); readfile($real); exit;
    }

    public static function status_label($status) {
        $labels = ['student_reported'=>'Student Reported','verified'=>'Teacher Verified','revision_requested'=>'Revision Requested','mastered'=>'Mastered'];
        return $labels[(string)$status] ?? 'Student Reported';
    }

    public static function render_student_section($user_id, $teacher_reviews) {
        $records = self::records($user_id);
        $topics = get_posts(['post_type'=>MathBinder_Core::CPT,'post_status'=>'publish','numberposts'=>-1,'orderby'=>'title','order'=>'ASC']);
        $notice = isset($_GET['practice_notice']) ? sanitize_key(wp_unslash($_GET['practice_notice'])) : '';
        ob_start(); ?>
        <section id="external-practice" class="mb-external-practice">
            <div class="mb-dashboard-heading"><div><span class="mb-collection-kicker">Learning beyond MathBinder</span><h2>Completed Practice</h2></div><button type="button" data-mb-practice-toggle aria-expanded="false">Add Completed Practice</button></div>
            <?php if ($notice === 'saved'): ?><div class="mb-practice-notice is-success" role="status">Your completed practice was added as Student Reported evidence.</div><?php elseif ($notice): ?><div class="mb-practice-notice is-error" role="alert">The practice record could not be saved. Check every required field and any uploaded file, then try again.</div><?php endif; ?>
            <form class="mb-practice-form" method="post" enctype="multipart/form-data" action="<?php echo esc_url(admin_url('admin-post.php')); ?>" data-mb-practice-form hidden><input type="hidden" name="action" value="<?php echo esc_attr(self::ACTION); ?>"><?php wp_nonce_field(self::ACTION, 'mb_external_practice_nonce'); ?>
                <div class="mb-practice-grid"><label>Platform<select name="platform" required data-mb-platform><option value="">Select platform</option><option>IXL</option><option>Khan Academy</option><option>DeltaMath</option><option>Other</option></select></label><label data-mb-other-platform hidden>Platform name<input type="text" name="other_platform" maxlength="80"></label><label>Skill or lesson title<input type="text" name="activity_title" maxlength="180" required></label><label>Date completed<input type="date" name="completed_on" max="<?php echo esc_attr(current_time('Y-m-d')); ?>" required></label><label>Score or mastery result<input type="text" name="result" maxlength="120" placeholder="Example: SmartScore 90 or Mastered" required></label><label>Time spent in minutes (optional)<input type="number" name="time_spent" min="0" max="1440"></label><label>Related MathBinder topic<select name="topic_id" required><option value="">Select topic</option><?php foreach ($topics as $topic): ?><option value="<?php echo absint($topic->ID); ?>"><?php echo esc_html($topic->post_title); ?></option><?php endforeach; ?></select></label><label>Activity link (optional)<input type="url" name="activity_url" maxlength="1000" placeholder="https://"></label></div>
                <label>What did you learn?<textarea name="reflection" rows="4" maxlength="2000" required></textarea></label><label>Screenshot or certificate (optional)<input type="file" name="practice_evidence" accept=".pdf,.jpg,.jpeg,.png,.webp"><small>PDF, JPG, PNG, or WEBP; maximum 8 MB.</small></label><div class="mb-practice-form-actions"><button type="submit">Add to Evidence Folder</button><button type="button" data-mb-practice-cancel>Cancel</button></div>
            </form><div class="mb-practice-list">
            <?php if (!$records): ?><div class="mb-practice-empty"><strong>No external practice recorded yet.</strong><p>Add completed work from IXL, Khan Academy, DeltaMath, or another learning platform.</p></div><?php else: foreach ($records as $record_id=>$record): $review=$teacher_reviews[self::review_key($record_id)] ?? []; $status=$review['decision'] ?? ($record['status'] ?? 'student_reported'); ?><article class="mb-practice-card"><div class="mb-practice-card-head"><div><span><?php echo esc_html($record['platform']); ?></span><h3><?php echo esc_html($record['activity_title']); ?></h3></div><b class="is-<?php echo esc_attr($status); ?>"><?php echo esc_html(self::status_label($status)); ?></b></div><p><strong><?php echo esc_html($record['result']); ?></strong> · <?php echo esc_html(wp_date(get_option('date_format'), strtotime($record['completed_on']))); ?><?php if (!empty($record['time_spent'])): ?> · <?php echo absint($record['time_spent']); ?> minutes<?php endif; ?></p><p><?php echo esc_html($record['reflection']); ?></p><small>Related topic: <a href="<?php echo esc_url($record['topic_url']); ?>"><?php echo esc_html($record['topic_title']); ?></a></small><div class="mb-practice-links"><?php if (!empty($record['activity_url'])): ?><a href="<?php echo esc_url($record['activity_url']); ?>" target="_blank" rel="noopener noreferrer">Open activity</a><?php endif; ?><?php if (!empty($record['evidence']['path'])): ?><a href="<?php echo esc_url(self::evidence_url($user_id,$record_id)); ?>">View evidence file</a><?php endif; ?></div><?php if ($review): ?><div class="mb-student-teacher-review"><strong><?php echo esc_html(self::status_label($status)); ?></strong><span><?php echo esc_html($review['teacher_name'] ?? 'Your teacher'); ?></span><?php if (!empty($review['feedback'])): ?><p><?php echo esc_html($review['feedback']); ?></p><?php endif; ?></div><?php endif; ?></article><?php endforeach; endif; ?></div>
        </section><script>(function(){const root=document.getElementById('external-practice');if(!root)return;const form=root.querySelector('[data-mb-practice-form]'),toggle=root.querySelector('[data-mb-practice-toggle]'),cancel=root.querySelector('[data-mb-practice-cancel]'),platform=root.querySelector('[data-mb-platform]'),other=root.querySelector('[data-mb-other-platform]');function show(value){form.hidden=!value;toggle.setAttribute('aria-expanded',value?'true':'false');}toggle.addEventListener('click',()=>show(form.hidden));cancel.addEventListener('click',()=>show(false));platform.addEventListener('change',()=>{other.hidden=platform.value!=='Other';other.querySelector('input').required=platform.value==='Other';});})();</script>
        <?php return ob_get_clean();
    }
}
