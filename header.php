<?php if (!defined('__TYPECHO_ROOT_DIR__')) exit;
$bgUrl = $this->options->bgUrl ? $this->options->bgUrl : $this->options->themeUrl . '/img/bg-default.jpg';
$hasCustomBg = trim((string)$this->options->bgUrl) !== '';
$bgCss = yingCssUrl($bgUrl);
$fontUrls = yingFontUrls($this->options);
$hasWebFont = !empty($fontUrls);
$fontSrc = implode(', ', array_map(function ($url) {
    return "url('" . yingCssUrl($url) . "')";
}, $fontUrls));
$readingFont = yingFontStack($hasWebFont);
$logoRaw = trim((string)$this->options->logoUrl);
if (!yingUrlIsSafe($logoRaw)) {
    $logoRaw = $this->options->themeUrl . '/avatar.png';
}
$logoUrl = htmlspecialchars($logoRaw, ENT_QUOTES);
$faviconRaw = trim((string)$this->options->faviconUrl);
if (!yingUrlIsSafe($faviconRaw)) {
    $faviconRaw = $logoRaw;
}
$faviconUrl = htmlspecialchars($faviconRaw, ENT_QUOTES);
$siteTitle = htmlspecialchars((string)$this->options->title, ENT_QUOTES);
$alphaRaw = trim((string)$this->options->cardAlpha);
$alphaVal = is_numeric($alphaRaw) && $alphaRaw !== '' ? floatval($alphaRaw) : 75;
if ($alphaVal > 1) {
    $alphaVal = $alphaVal / 100;
}
$cardAlpha = max(0.05, min(1, round($alphaVal, 2)));

// 字体 CDN 预连接
$preconnects = [];
$fontSource = yingFontSource($this->options);
if ($fontSource === 'cdn' || $fontSource === 'harmony') {
    $preconnects[] = 'https://cdn.jsdelivr.net';
    $preconnects[] = 'https://fastly.jsdelivr.net';
} elseif ($fontSource === 'custom') {
    $fontUrlRaw = trim((string)$this->options->fontUrl);
    $fontHost = parse_url($fontUrlRaw, PHP_URL_HOST);
    $fontScheme = parse_url($fontUrlRaw, PHP_URL_SCHEME);
    if (is_string($fontHost) && $fontHost !== '' && is_string($fontScheme)
        && in_array(strtolower($fontScheme), ['http', 'https'], true)) {
        $preconnects[] = strtolower($fontScheme) . '://' . $fontHost;
    }
}

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
    $cicon = $ctype === 'link' && isset($c['icon']) && is_string($c['icon']) ? trim($c['icon']) : '';
    $contacts[] = [
        'label' => $contactTypes[$ctype][0],
        'icon' => $contactTypes[$ctype][1],
        'iconUrl' => $cicon !== '' && yingUrlIsSafe($cicon) ? $cicon : '',
        'url' => $url,
    ];
}

// 一言 API 模式参数
$yiyanCats = implode(',', (array)$this->options->yiyanCats);

// 一言位置：可移到联系方式图标下方；没有联系方式时仍显示在头像下方
$yiyanAside = $this->options->yiyanPosition === 'contacts' && !empty($contacts);
$yiyanHtml = '';
if ($this->options->yiyanSource === 'api') {
    $yiyanHtml = '<h2><span id="yiyan-text" class="is-loading" data-cats="'
        . htmlspecialchars($yiyanCats, ENT_QUOTES)
        . '" data-from="' . ($this->options->yiyanShowFrom === 'on' ? '1' : '0')
        . '" data-fallback="' . htmlspecialchars((string)$this->options->yiyan, ENT_QUOTES)
        . '">…</span></h2>';
} else {
    $yiyanHtml = '<h2><span>' . htmlspecialchars((string)$this->options->yiyan, ENT_QUOTES) . '</span></h2>';
}
?>
<!DOCTYPE html>
<html lang="zh-CN">
<head>
    <meta charset="<?php $this->options->charset(); ?>">
    <meta name="renderer" content="webkit">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <link rel="icon" href="<?php echo $faviconUrl; ?>">
    <?php foreach ($preconnects as $preconnectHost): ?>
    <link rel="preconnect" href="<?php echo htmlspecialchars($preconnectHost, ENT_QUOTES, 'UTF-8'); ?>" crossorigin>
    <?php endforeach; ?>
    <title><?php
        $titlePrefix = '';
        $titleArchive = $this->getArchiveTitle();
        if (!empty($titleArchive)) {
            $titleDefines = [
                'category' => _t('分类 %s 下的文章'),
                'search'   => _t('包含关键字 %s 的文章'),
                'tag'      => _t('标签 %s 下的文章'),
                'author'   => _t('%s 发布的文章')
            ];
            $titleDefine = isset($titleDefines[$this->getArchiveType()]) ? $titleDefines[$this->getArchiveType()] : '%s';
            $titlePrefix = sprintf($titleDefine, $titleArchive) . ' - ';
        }
        echo htmlspecialchars($titlePrefix . $siteTitle, ENT_QUOTES, 'UTF-8');
    ?></title>

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

    <link rel="stylesheet" href="<?php $this->options->themeUrl('css/normalize.min.css'); ?>">
    <link rel="stylesheet" href="<?php $this->options->themeUrl('css/main.css'); ?>?v=<?php echo (int)filemtime(__DIR__ . '/css/main.css'); ?>">
    <link rel="stylesheet" href="<?php $this->options->themeUrl('css/remixicon.min.css'); ?>?v=<?php echo (int)filemtime(__DIR__ . '/css/remixicon.min.css'); ?>">
    <style>
    <?php if ($hasWebFont): ?>
    @font-face {
        font-family: 'YingK Sans';
        src: <?php echo $fontSrc; ?>;
        font-weight: normal;
        font-style: normal;
        font-display: swap;
    }
    <?php endif; ?>
    :root { --reading-font: <?php echo $readingFont; ?>; --page-bg: url('<?php echo $bgCss; ?>'); --card-alpha: <?php echo $cardAlpha; ?>; }
    </style>
    <?php $this->header('generator=&wlw=&rss2=&rss1=&atom='); ?>
    <?php
    $feedTitle = (!empty($titleArchive) ? $titleArchive . ' &raquo; ' : '') . $siteTitle;
    ?>
    <link rel="alternate" type="application/rss+xml" title="<?php echo htmlspecialchars($feedTitle . ' &raquo; RSS 2.0', ENT_QUOTES, 'UTF-8'); ?>" href="<?php echo htmlspecialchars($this->getArchiveFeedUrl(), ENT_QUOTES, 'UTF-8'); ?>">
    <link rel="alternate" type="application/rdf+xml" title="<?php echo htmlspecialchars($feedTitle . ' &raquo; RSS 1.0', ENT_QUOTES, 'UTF-8'); ?>" href="<?php echo htmlspecialchars($this->getArchiveFeedRssUrl(), ENT_QUOTES, 'UTF-8'); ?>">
    <link rel="alternate" type="application/atom+xml" title="<?php echo htmlspecialchars($feedTitle . ' &raquo; ATOM 1.0', ENT_QUOTES, 'UTF-8'); ?>" href="<?php echo htmlspecialchars($this->getArchiveFeedAtomUrl(), ENT_QUOTES, 'UTF-8'); ?>">
</head>
<body<?php $bodyClasses = [];
    if ($this->is('post')) {
        $bodyClasses[] = 'is-post';
    }
    if ($hasCustomBg) {
        $bodyClasses[] = 'has-custom-bg';
    }
    if ($bodyClasses): ?> class="<?php echo implode(' ', $bodyClasses); ?>"<?php endif; ?>>
    <a class="skip-link" href="#main-content">跳到主要内容</a>
    <div class="main">
        <div class="container">
            <div class="header<?php if ($yiyanAside): ?> header-yiyan-aside<?php endif; ?>">
                <div class="header-top">
                <img class="site-avatar" src="<?php echo $logoUrl; ?>" width="72" height="72" alt="<?php echo $siteTitle; ?>"/>
                <?php if ($yiyanAside): ?>
                <div class="header-aside">
                <?php endif; ?>
                <?php if ($contacts): ?>
                    <nav class="nav contacts" aria-label="联系方式">
                        <ul class="flat">
                            <?php foreach ($contacts as $contact): ?>
                            <li><a href="<?php echo htmlspecialchars($contact['url'], ENT_QUOTES); ?>"<?php if (stripos($contact['url'], 'http') === 0): ?> target="_blank" rel="noopener"<?php endif; ?> title="<?php echo htmlspecialchars($contact['label'], ENT_QUOTES); ?>" aria-label="<?php echo htmlspecialchars($contact['label'], ENT_QUOTES); ?>"><?php if (!empty($contact['iconUrl'])): ?><img class="contact-icon" src="<?php echo htmlspecialchars($contact['iconUrl'], ENT_QUOTES, 'UTF-8'); ?>" alt="" width="19" height="19" referrerpolicy="no-referrer"><?php else: ?><i class="<?php echo $contact['icon']; ?>"></i><?php endif; ?></a></li>
                            <?php endforeach; ?>
                        </ul>
                    </nav>
                <?php endif; ?>
                <button type="button" id="menu-hamburger" class="menu-hamburger<?php if (!$contacts): ?> menu-hamburger-solo<?php endif; ?>" aria-label="打开菜单" aria-expanded="false" aria-controls="top-menu"><i class="ri-menu-line"></i></button>
                <?php if ($yiyanAside): ?>
                    <div class="site-description site-description-aside"><?php echo $yiyanHtml; ?></div>
                </div>
                <?php endif; ?>
                </div>
                <?php if (!$yiyanAside): ?>
                <div class="site-description"><?php echo $yiyanHtml; ?></div>
                <?php endif; ?>
                <?php $this->need('nav.php'); ?>
            </div>
            <main id="main-content" tabindex="-1">
