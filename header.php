<?php if (!defined('__TYPECHO_ROOT_DIR__')) exit;
$bgUrl = $this->options->bgUrl ? $this->options->bgUrl : $this->options->themeUrl . '/img/bg-default.jpg';
$alphaRaw = trim((string)$this->options->cardAlpha);
$alphaVal = is_numeric($alphaRaw) && $alphaRaw !== '' ? floatval($alphaRaw) : 75;
if ($alphaVal > 1) {
    $alphaVal = $alphaVal / 100;
}
$cardAlpha = max(0.05, min(1, round($alphaVal, 2)));

// 联系方式：优先 JSON，空时回退旧字段
$contacts = [];
$contactTypes = [
    'github'   => ['GitHub', 'ri-github-line'],
    'mail'     => ['邮箱', 'ri-mail-line'],
    'weibo'    => ['微博', 'ri-weibo-line'],
    'twitter'  => ['X', 'ri-twitter-x-line'],
    'bilibili' => ['B站', 'ri-bilibili-line'],
    'zhihu'    => ['知乎', 'ri-zhihu-line'],
    'telegram' => ['Telegram', 'ri-telegram-line'],
    'link'     => ['链接', 'ri-link'],
];
$contactsRaw = trim((string)$this->options->contactsJson);
$contactsList = null;
if ($contactsRaw !== '') {
    $decoded = json_decode($contactsRaw, true);
    if (is_array($decoded)) {
        $contactsList = $decoded;
    }
}
if ($contactsList === null) {
    $legacyMap = [
        'githubUrl' => 'github', 'mailAddr' => 'mail', 'weiboUrl' => 'weibo',
        'twitterUrl' => 'twitter', 'bilibiliUrl' => 'bilibili', 'zhihuUrl' => 'zhihu',
        'telegramUrl' => 'telegram',
    ];
    $contactsList = [];
    foreach ($legacyMap as $lfield => $ltype) {
        $lval = trim((string)$this->options->{$lfield});
        if ($lval !== '') {
            $contactsList[] = ['type' => $ltype, 'url' => $lval];
        }
    }
}
foreach ($contactsList as $c) {
    $ctype = isset($c['type']) && isset($contactTypes[$c['type']]) ? $c['type'] : 'link';
    $curl = isset($c['url']) ? trim((string)$c['url']) : '';
    if ($curl === '') {
        continue;
    }
    // 安全程：拒绝 javascript:/data: 等危险协议
    if ($ctype !== 'mail' && preg_match('/^(javascript|data|vbscript):/i', $curl)) {
        continue;
    }
    if ($ctype === 'mail') {
        $url = 'mailto:' . $curl;
    } elseif ($ctype === 'telegram') {
        $url = preg_match('/^https?:/i', $curl) ? $curl : 'https://t.me/' . ltrim($curl, '@');
    } else {
        $url = preg_match('/^(https?:|mailto:)/i', $curl) ? $curl : 'https://' . ltrim($curl, '/');
    }
    $contacts[] = ['label' => $contactTypes[$ctype][0], 'icon' => $contactTypes[$ctype][1], 'url' => $url];
}

// 一言 API 模式参数
$yiyanCats = implode(',', (array)$this->options->yiyanCats);
?>
<!DOCTYPE html>
<html lang="zh-CN">
<head>
    <meta charset="<?php $this->options->charset(); ?>">
    <meta name="renderer" content="webkit">
    <meta name="viewport" content="width=device-width, initial-scale=1, maximum-scale=1">
    <title><?php $this->archiveTitle([
            'category' => _t('分类 %s 下的文章'),
            'search'   => _t('包含关键字 %s 的文章'),
            'tag'      => _t('标签 %s 下的文章'),
            'author'   => _t('%s 发布的文章')
        ], '', ' - '); ?><?php $this->options->title(); ?></title>

    <script>
    (function () {
        var saved = null;
        try {
            saved = localStorage.getItem('ying-theme');
        } catch (e) {}
        var theme = saved || (window.matchMedia && window.matchMedia('(prefers-color-scheme: dark)').matches
            ? 'dark' : 'light');
        document.documentElement.setAttribute('data-theme', theme);
    })();
    </script>

    <link rel="stylesheet" href="<?php $this->options->themeUrl('css/main.css'); ?>">
    <link rel="stylesheet" href="<?php $this->options->themeUrl('css/normalize.min.css'); ?>">
    <link rel="stylesheet" href="<?php $this->options->themeUrl('css/remixicon.min.css'); ?>">
    <style>:root { --page-bg: url('<?php echo htmlspecialchars($bgUrl, ENT_QUOTES); ?>'); --card-alpha: <?php echo htmlspecialchars($cardAlpha, ENT_QUOTES); ?>; }</style>
    <?php $this->header(); ?>
</head>
<body>
    <div class="main">
        <div class="container">
            <div class="header">
                <div class="header-top">
                <?php if ($this->options->logoUrl): ?>
                    <img src="<?php $this->options->logoUrl(); ?>" width="72" height="72" alt="<?php $this->options->title(); ?>"/>
                <?php endif; ?>
                <?php if ($contacts): ?>
                    <nav class="nav contacts">
                        <ul class="flat">
                            <?php foreach ($contacts as $contact): ?>
                            <li><a href="<?php echo htmlspecialchars($contact['url'], ENT_QUOTES); ?>"<?php if (stripos($contact['url'], 'http') === 0): ?> target="_blank" rel="noopener"<?php endif; ?> title="<?php echo htmlspecialchars($contact['label'], ENT_QUOTES); ?>"><i class="<?php echo $contact['icon']; ?>"></i></a></li>
                            <?php endforeach; ?>
                        </ul>
                    </nav>
                <?php endif; ?>
                </div>
                <div class="site-description">
                    <?php if ($this->options->yiyanSource === 'api'): ?>
                    <h2><span id="yiyan-text" class="is-loading" data-cats="<?php echo htmlspecialchars($yiyanCats, ENT_QUOTES); ?>" data-from="<?php echo $this->options->yiyanShowFrom === 'on' ? '1' : '0'; ?>" data-fallback="<?php echo htmlspecialchars((string)$this->options->yiyan, ENT_QUOTES); ?>">…</span></h2>
                    <?php else: ?>
                    <h2><span><?php $this->options->yiyan(); ?></span></h2>
                    <?php endif; ?>
                </div>
