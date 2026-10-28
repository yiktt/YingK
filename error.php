<?php
/**
 * 独立错误页：接管 Typecho 核心异常的默认页面（如密码错误 403、数据库 503 等）。
 *
 * 启用方式：在站点根目录的 config.inc.php 中追加一行（路径按主题目录名调整）：
 *   define('__TYPECHO_EXCEPTION_FILE__', __DIR__ . '/usr/themes/Ying/error.php');
 * 不启用时，核心会显示内置的极简错误页；主题其余功能不受影响。
 */
if (!defined('__TYPECHO_ROOT_DIR__')) {
    exit;
}

// 异常发生时主题 functions.php 可能尚未加载，这里按需自举（仅函数定义，无副作用）
if (!function_exists('yingThemeChrome') && is_file(__DIR__ . '/functions.php')) {
    require_once __DIR__ . '/functions.php';
}

$errOptions = \Widget\Options::alloc();
$chrome = yingThemeChrome($errOptions);

$errCode = isset($code) ? (int)$code : 500;
$rawMessage = isset($message) ? trim(strip_tags((string)$message)) : '';
$errMessage = $rawMessage !== '' ? nl2br(htmlspecialchars($rawMessage, ENT_QUOTES, 'UTF-8')) : _t('服务器开小差了，请稍后再试。');
$isLocked = ($errCode === 403);

$errEyebrow = $isLocked ? _t('编年 · 口令不符') : _t('编年 · 出错');
$errHeadline = $isLocked ? _t('这页还锁着') : _t('出了点问题');
$errSeal = $isLocked ? _t('封') : _t('误');

$errSiteTitle = (string)$errOptions->title;
$errHomeUrl = $errOptions->siteUrl;

$errLogoRaw = trim((string)$errOptions->logoUrl);
if (!yingUrlIsSafe($errLogoRaw)) {
    $errLogoRaw = $errOptions->themeUrl . '/avatar.png';
}
$errFaviconRaw = trim((string)$errOptions->faviconUrl);
if (!yingUrlIsSafe($errFaviconRaw)) {
    $errFaviconRaw = $errLogoRaw;
}
?>
<!DOCTYPE html>
<html lang="zh-CN">
<head>
    <meta charset="<?php $errOptions->charset(); ?>">
    <meta name="renderer" content="webkit">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <meta name="robots" content="noindex">
    <link rel="icon" href="<?php echo htmlspecialchars($errFaviconRaw, ENT_QUOTES, 'UTF-8'); ?>">
    <title><?php echo htmlspecialchars($errCode . ' - ' . $errSiteTitle, ENT_QUOTES, 'UTF-8'); ?></title>
    <link rel="stylesheet" href="<?php $errOptions->themeUrl('css/normalize.min.css'); ?>">
    <link rel="stylesheet" href="<?php $errOptions->themeUrl('css/main.css'); ?>?v=<?php echo (int)filemtime(__DIR__ . '/css/main.css'); ?>">
    <?php if ($chrome['fontSource'] === 'cdn'): ?>
    <link rel="stylesheet" href="<?php $errOptions->themeUrl('css/zql-fonts.css'); ?>?v=<?php echo (int)filemtime(__DIR__ . '/css/zql-fonts.css'); ?>">
    <?php endif; ?>
    <style>
    <?php if ($chrome['hasWebFont'] && $chrome['fontSource'] !== 'cdn'): ?>
    @font-face {
        font-family: 'YingK Sans';
        src: <?php echo $chrome['fontSrc']; ?>;
        font-weight: normal;
        font-style: normal;
        font-display: swap;
    }
    <?php endif; ?>
    :root { --reading-font: <?php echo $chrome['readingFont']; ?>; --page-bg: url('<?php echo $chrome['bgCss']; ?>'); --card-alpha: <?php echo $chrome['cardAlpha']; ?>; }
    .err-brand { display: inline-block; margin: 0 0 4px; font-size: 12px; color: var(--faint); letter-spacing: .08em; }
    .err-brand:hover { color: var(--link-muted); text-decoration: none; }
    </style>
    <script>
    (function () {
        document.documentElement.classList.add('js');
        var saved = null;
        try {
            saved = localStorage.getItem('ying-theme');
        } catch (e) {}
        var theme = saved || (window.matchMedia && window.matchMedia('(prefers-color-scheme: dark)').matches
            ? 'dark' : 'light');
        document.documentElement.setAttribute('data-theme', theme);
    })();
    </script>
</head>
<body>
<div class="main">
    <div class="container">
        <a class="err-brand" href="<?php echo htmlspecialchars($errHomeUrl, ENT_QUOTES, 'UTF-8'); ?>"><?php echo htmlspecialchars($errSiteTitle, ENT_QUOTES, 'UTF-8'); ?></a>
        <div class="notfound">
            <p class="notfound-eyebrow" aria-hidden="true"><?php echo htmlspecialchars($errEyebrow, ENT_QUOTES, 'UTF-8'); ?></p>
            <div class="notfound-node" aria-hidden="true"></div>
            <div class="notfound-code-wrap">
                <p class="notfound-code" aria-hidden="true"><?php echo $errCode; ?></p>
                <span class="notfound-seal" aria-hidden="true"><?php echo htmlspecialchars($errSeal, ENT_QUOTES, 'UTF-8'); ?></span>
            </div>
            <h1 class="notfound-title"><?php echo htmlspecialchars($errHeadline, ENT_QUOTES, 'UTF-8'); ?></h1>
            <p class="notfound-text"><?php echo $errMessage; ?></p>
            <div class="notfound-actions">
                <button type="button" class="nf-btn" id="err-back"><?php _e('← 再试一次'); ?></button>
                <span class="nf-sep" aria-hidden="true">·</span>
                <a class="nf-btn nf-btn-ghost" href="<?php echo htmlspecialchars($errHomeUrl, ENT_QUOTES, 'UTF-8'); ?>"><?php _e('返回首页'); ?></a>
            </div>
        </div>
    </div>
</div>
<script>
(function () {
    var back = document.getElementById('err-back');
    if (!back) { return; }
    back.addEventListener('click', function () {
        if (window.history.length > 1) {
            window.history.back();
        } else {
            window.location.href = <?php echo json_encode($errHomeUrl); ?>;
        }
    });
})();
</script>
</body>
</html>
