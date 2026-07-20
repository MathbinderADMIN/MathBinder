<?php

require_once __DIR__ . '/preview-loader.php';

$preview = mathbinder_preview_render(isset($_GET['lesson']) ? $_GET['lesson'] : null);
$lesson_title = isset($preview['lesson_title']) ? $preview['lesson_title'] : 'Preview unavailable';
$lesson_id = isset($preview['lesson_id']) ? $preview['lesson_id'] : 'Not available';
$error_message = isset($preview['error_message']) ? $preview['error_message'] : '';
$lesson_html = isset($preview['html']) ? $preview['html'] : '';
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>MathBinder Lesson Preview</title>
    <link rel="stylesheet" href="./preview.css">
</head>
<body>
    <div class="mb-preview-shell">
        <header class="mb-preview-header">
            <div class="mb-preview-heading">
                <p class="mb-preview-badge">Development Preview — Not Published</p>
                <h1><?php echo htmlspecialchars($lesson_title, ENT_QUOTES, 'UTF-8'); ?></h1>
                <p class="mb-preview-meta"><strong>Lesson ID:</strong> <?php echo htmlspecialchars($lesson_id, ENT_QUOTES, 'UTF-8'); ?></p>
            </div>
            <form class="mb-preview-form" method="get" action="./index.php">
                <label class="mb-preview-form-label" for="lesson">Preview lesson</label>
                <input id="lesson" name="lesson" type="text" value="" placeholder="number-operations-production">
                <button type="submit">Load</button>
            </form>
        </header>

        <?php if ($error_message !== '') : ?>
            <div class="mb-preview-error" role="alert">
                <?php echo htmlspecialchars($error_message, ENT_QUOTES, 'UTF-8'); ?>
            </div>
        <?php endif; ?>

        <main class="mb-preview-main">
            <?php if ($lesson_html !== '') : ?>
                <?php echo $lesson_html; ?>
            <?php else : ?>
                <div class="mb-preview-empty">
                    <p>No lesson content was rendered.</p>
                </div>
            <?php endif; ?>
        </main>
    </div>
</body>
</html>
