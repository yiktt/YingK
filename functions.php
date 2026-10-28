<?php
if (!defined('__TYPECHO_ROOT_DIR__')) exit;

/**
 * CSS url() 字符串安全转义
 */
function yingCssUrl(string $value): string
{
    return strtr($value, [
        '\\' => '\\\\',
        "'"  => "\\'",
        '"'  => "\\\"",
        '<'  => '\\3c ',
        '>'  => '\\3e ',
        "\n" => '\\a ',
        "\r" => '\\d ',
        "\f" => '\\c ',
    ]);
}

/**
 * 解析 php.ini 的容量写法
 */
function yingIniBytes(string $value): int
{
    $value = trim($value);
    if ($value === '') {
        return 0;
    }
    $unit = strtolower(substr($value, -1));
    $bytes = (float)$value;
    if ($unit === 'g') {
        $bytes *= 1024;
        $unit = 'm';
    }
    if ($unit === 'm') {
        $bytes *= 1024;
        $unit = 'k';
    }
    if ($unit === 'k') {
        $bytes *= 1024;
    }
    return (int)$bytes;
}

/**
 * 字体上传上限：取 upload_max_filesize 与 post_max_size 的较小值，最高 20MB
 */
function yingFontUploadLimit(): int
{
    $limits = [];
    foreach (['upload_max_filesize', 'post_max_size'] as $key) {
        $bytes = yingIniBytes((string)ini_get($key));
        if ($bytes > 0) {
            $limits[] = $bytes;
        }
    }
    $limit = $limits ? min($limits) : 20 * 1024 * 1024;
    return min($limit, 20 * 1024 * 1024);
}

function yingFontUploadDir(): string
{
    $root = defined('__TYPECHO_UPLOAD_ROOT_DIR__') ? __TYPECHO_UPLOAD_ROOT_DIR__ : __TYPECHO_ROOT_DIR__;
    $relative = (defined('__TYPECHO_UPLOAD_DIR__') ? __TYPECHO_UPLOAD_DIR__ : '/usr/uploads') . '/fonts';
    return str_replace('\\', '/', \Typecho\Common::url($relative, $root));
}

function yingFontUploadUrl(string $filename): string
{
    $relative = (defined('__TYPECHO_UPLOAD_DIR__') ? __TYPECHO_UPLOAD_DIR__ : '/usr/uploads')
        . '/fonts/' . rawurlencode($filename);
    $prefix = defined('__TYPECHO_UPLOAD_URL__') ? __TYPECHO_UPLOAD_URL__ : \Widget\Options::alloc()->siteUrl;
    return \Typecho\Common::url($relative, $prefix);
}

function yingIsLocalFont(string $filename): bool
{
    return preg_match('/^ying-font-[a-f0-9]{16}\.(woff2|woff|ttf|otf)$/', $filename) === 1;
}

/**
 * 校验字体容器结构，拒绝只有签名的空壳/截断文件
 * 只读取头部和表目录，不解析字形数据，也不执行字体代码。
 */
function yingFontStructureValid(string $path, string $format): bool
{
    $size = (int)filesize($path);
    if ($size < 12) {
        return false;
    }
    $handle = @fopen($path, 'rb');
    if (!$handle) {
        return false;
    }
    $header = fread($handle, 48);
    fclose($handle);
    if (!is_string($header) || strlen($header) < 12) {
        return false;
    }
    $readUint32 = static function (string $bytes, int $offset): int {
        $part = substr($bytes, $offset, 4);
        return strlen($part) === 4 ? (int)unpack('N', $part)[1] : 0;
    };
    $readUint16 = static function (string $bytes, int $offset): int {
        $part = substr($bytes, $offset, 2);
        return strlen($part) === 2 ? (int)unpack('n', $part)[1] : 0;
    };

    if ($format === 'woff2' || $format === 'woff') {
        $minimum = $format === 'woff2' ? 48 : 44;
        if ($size < $minimum || strlen($header) < $minimum) {
            return false;
        }
        $declaredLength = $readUint32($header, 8);
        $tableCount = $readUint16($header, 12);
        $totalSfntSize = $readUint32($header, 16);
        return $declaredLength === $size && $tableCount > 0 && $totalSfntSize > 0;
    }

    // TTF / OTF：校验 sfnt 表目录边界。
    $tableCount = $readUint16($header, 4);
    if ($tableCount < 1 || $tableCount > 512) {
        return false;
    }
    $directoryLength = 12 + $tableCount * 16;
    if ($directoryLength > $size) {
        return false;
    }
    $handle = @fopen($path, 'rb');
    if (!$handle) {
        return false;
    }
    $directory = fread($handle, $directoryLength);
    fclose($handle);
    if (!is_string($directory) || strlen($directory) !== $directoryLength) {
        return false;
    }
    for ($i = 0; $i < $tableCount; $i++) {
        $offset = 12 + $i * 16;
        $tableOffset = $readUint32($directory, $offset + 8);
        $tableLength = $readUint32($directory, $offset + 12);
        if ($tableOffset > $size || $tableLength > $size - $tableOffset) {
            return false;
        }
    }
    return true;
}

/**
 * 通过文件头识别字体格式，不信任原始扩展名
 */
function yingDetectFontFormat(string $path): ?string
{
    if (!is_file($path) || filesize($path) === 0) {
        return null;
    }
    // 上限 64MB：防止异常大文件占用内存/磁盘
    if ((int)filesize($path) > 64 * 1024 * 1024) {
        return null;
    }
    $handle = @fopen($path, 'rb');
    if (!$handle) {
        return null;
    }
    $signature = fread($handle, 4);
    fclose($handle);
    $formats = [
        'wOF2'             => 'woff2',
        'wOFF'             => 'woff',
        'OTTO'             => 'otf',
        "\x00\x01\x00\x00" => 'ttf',
        'true'             => 'ttf',
    ];
    $format = $formats[$signature] ?? null;
    if ($format === null || !yingFontStructureValid($path, $format)) {
        return null;
    }
    return $format;
}

function yingDeleteLocalFont(string $filename): void
{
    if (!yingIsLocalFont($filename)) {
        return;
    }
    $path = yingFontUploadDir() . '/' . $filename;
    if (is_file($path)) {
        @unlink($path);
    }
}

function yingAdminFlash(string $message): void
{
    \Typecho\Cookie::set('ying_admin_flash', $message, time() + 300);
}

/**
 * 字体地址白名单：http(s) 或站内根路径
 */
function yingUrlIsSafe(string $url): bool
{
    $url = trim($url);
    if ($url === '') {
        return false;
    }
    return preg_match('#^https?://[^\s<>"\']+$#i', $url) === 1
        || preg_match('#^/[^\s<>"\']*$#', $url) === 1;
}

function yingFontDisplayName(string $name): string
{
    $name = basename(str_replace('\\', '/', $name));
    if (!mb_check_encoding($name, 'UTF-8')) {
        $name = mb_convert_encoding($name, 'UTF-8', 'UTF-8');
    }
    $name = preg_replace('/[\x00-\x1F\x7F]+/u', '', $name) ?? '';
    $name = trim($name);
    if ($name === '') {
        $name = '未命名字体';
    }
    return mb_substr($name, 0, 120, 'UTF-8');
}

function yingFormatBytes(int $bytes): string
{
    if ($bytes >= 1048576) {
        return number_format($bytes / 1048576, 1, '.', '') . ' MB';
    }
    if ($bytes >= 1024) {
        return number_format($bytes / 1024, 0, '.', '') . ' KB';
    }
    return max(0, $bytes) . ' B';
}

function yingFontMetaFromFile(string $file, string $name = ''): array
{
    $path = yingFontUploadDir() . '/' . $file;
    $format = yingDetectFontFormat($path);
    if ($format === null) {
        $format = strtolower((string)pathinfo($file, PATHINFO_EXTENSION));
    }
    return [
        'file' => $file,
        'name' => yingFontDisplayName($name !== '' ? $name : $file),
        'format' => $format !== '' ? $format : 'font',
        'size' => is_file($path) ? (int)filesize($path) : 0,
        'time' => is_file($path) ? (int)filemtime($path) : time(),
    ];
}

/**
 * 读取字体库，顺带兼容旧版仅保存 fontLocal 的数据
 */
function yingFontLibraryFromOptions($options = null): array
{
    $options = $options ?: \Widget\Options::alloc();
    $library = [];
    $raw = trim((string)$options->fontLibrary);
    if ($raw !== '') {
        $decoded = json_decode($raw, true);
        if (is_array($decoded)) {
            foreach ($decoded as $item) {
                if (!is_array($item)) {
                    continue;
                }
                $file = (string)($item['file'] ?? '');
                if (!yingIsLocalFont($file) || !is_file(yingFontUploadDir() . '/' . $file)) {
                    continue;
                }
                $library[$file] = yingFontMetaFromFile($file, (string)($item['name'] ?? ''));
            }
        }
    }

    $legacy = trim((string)$options->fontLocal);
    if (yingIsLocalFont($legacy) && is_file(yingFontUploadDir() . '/' . $legacy) && !isset($library[$legacy])) {
        $library[$legacy] = yingFontMetaFromFile($legacy);
    }

    return array_values($library);
}

function yingFontSelectedFromList(array $library, string $wanted = ''): string
{
    foreach ($library as $item) {
        if (($item['file'] ?? '') === $wanted) {
            return $wanted;
        }
    }
    return $library ? (string)$library[0]['file'] : '';
}

function yingFontSource($options = null): string
{
    $options = $options ?: \Widget\Options::alloc();
    $source = trim((string)$options->fontSource);
    if (in_array($source, ['cdn', 'local', 'none', 'harmony', 'custom'], true)) {
        return $source;
    }

    $library = yingFontLibraryFromOptions($options);
    $selected = yingFontSelectedFromList($library, trim((string)$options->fontLocal));
    if ($selected !== '') {
        return 'local';
    }
    if (yingUrlIsSafe((string)$options->fontUrl)) {
        return 'custom';
    }
    return 'harmony';
}

function yingHarmonyFontUrls(): array
{
    return [
        'https://cdn.jsdelivr.net/gh/yiktt/YingK@main/assets/fonts/harmonyos-sans-sc-medium.woff',
        'https://fastly.jsdelivr.net/gh/yiktt/YingK@main/assets/fonts/harmonyos-sans-sc-medium.woff',
    ];
}

function yingFontUrls($options = null): array
{
    $options = $options ?: \Widget\Options::alloc();
    $source = yingFontSource($options);

    // CDN 字体的 @font-face（分片 + unicode-range）在 css/zql-fonts.css 中静态维护
    if ($source === 'cdn') {
        return [];
    }
    if ($source === 'harmony') {
        return yingHarmonyFontUrls();
    }
    if ($source === 'custom') {
        $url = trim((string)$options->fontUrl);
        return yingUrlIsSafe($url) ? [$url] : [];
    }
    if ($source === 'local') {
        $library = yingFontLibraryFromOptions($options);
        $selected = yingFontSelectedFromList($library, trim((string)$options->fontLocal));
        return $selected !== '' ? [yingFontUploadUrl($selected)] : [];
    }
    return [];
}

function yingFontStack(bool $hasWebFont): string
{
    if ($hasWebFont) {
        return "'YingK Sans', 'HarmonyOS Sans SC Medium', 'HarmonyOS Sans SC', -apple-system, BlinkMacSystemFont, 'Segoe UI', 'PingFang SC', 'Microsoft YaHei', 'Noto Sans CJK SC', sans-serif";
    }
    return "-apple-system, BlinkMacSystemFont, 'Segoe UI', 'PingFang SC', 'Microsoft YaHei', 'Noto Sans CJK SC', 'Helvetica Neue', Arial, sans-serif";
}

/**
 * 卡片透明度统一按 0–100 百分比解释；小于 1 的旧小数按比例兼容。
 * 1 => 0.01，2 => 0.02，75 => 0.75，空值/非法值 => 默认 0.75。
 */
function yingCardAlphaValue(string $raw): float
{
    $raw = trim($raw);
    if ($raw === '' || !is_numeric($raw)) {
        return 0.75;
    }
    $value = (float)$raw;
    if ($value > 0 && $value < 1) {
        return max(0.01, min(1, round($value, 2)));
    }
    return max(0.01, min(1, round($value / 100, 2)));
}

/**
 * 页头与独立错误页共用的主题渲染变量：字体来源、背景、卡片透明度
 * （header.php 与 error.php 共用，避免两处逻辑漂移）
 */
function yingThemeChrome($options = null): array
{
    $options = $options ?: \Widget\Options::alloc();

    $fontSource = yingFontSource($options);
    $fontUrls = $fontSource === 'cdn' ? [] : yingFontUrls($options);
    // CDN 字体的 @font-face 在 css/zql-fonts.css（分片 + unicode-range），此处仍算作有网络字体
    $hasWebFont = ($fontSource === 'cdn') || !empty($fontUrls);
    $fontSrc = implode(', ', array_map(function ($url) {
        return "url('" . yingCssUrl($url) . "')";
    }, $fontUrls));
    $readingFont = yingFontStack($hasWebFont);

    $bgRaw = $options->bgUrl ? (string)$options->bgUrl : $options->themeUrl . '/img/bg-default.jpg';
    $hasCustomBg = trim((string)$options->bgUrl) !== '';
    $bgCss = yingCssUrl($bgRaw);

    $cardAlpha = yingCardAlphaValue((string)$options->cardAlpha);

    return compact('fontSource', 'fontUrls', 'hasWebFont', 'fontSrc', 'readingFont', 'hasCustomBg', 'bgRaw', 'bgCss', 'cardAlpha');
}

function yingNewPostDays(int $created): int
{
    $options = \Widget\Options::alloc();
    $offset = (int)$options->timezone - (int)$options->serverTimezone;
    $today = strtotime(date('Y-m-d', time() + $offset));
    $day = strtotime(date('Y-m-d', $created + $offset));
    return (int)round(($today - $day) / 86400);
}

/**
 * 解析后台手动填写的置顶文章：文章 ID 或 slug，用逗号/空白分隔。
 */
function yingStickyTokens(string $value): array
{
    $tokens = [];
    foreach (preg_split('/[,，\s\x{3000}]+/u', trim($value)) ?: [] as $token) {
        $token = trim($token);
        if ($token !== '' && !in_array($token, $tokens, true)) {
            $tokens[] = $token;
        }
    }
    return $tokens;
}

/**
 * 首页第一页的置顶文章（最多 3 篇），按后台填写顺序返回。
 * 只取已发布、未加密、非定时的文章；删除/草稿/加锁的文章自动跳过。
 */
function yingStickyPosts(): array
{
    static $sticky = null;
    if ($sticky !== null) {
        return $sticky;
    }

    $options = \Widget\Options::alloc();
    $tokens = yingStickyTokens((string)($options->stickyPosts ?? ''));
    if (!$tokens) {
        return $sticky = [];
    }

    $db = \Typecho\Db::get();
    $rows = [];
    foreach ($tokens as $token) {
        if (count($rows) >= 3) {
            break;
        }
        $select = $db->select('cid', 'title', 'slug', 'created')
            ->from('table.contents')
            ->where('type = ?', 'post')
            ->where('status = ?', 'publish')
            ->where('created < ?', (int)$options->time)
            ->where('password IS NULL OR password = ?', '')
            ->limit(1);
        if (ctype_digit($token)) {
            $select->where('cid = ?', (int)$token);
        } else {
            $select->where('slug = ?', $token);
        }
        $row = $db->fetchRow($select);
        if (!$row) {
            continue;
        }
        $cid = (int)$row['cid'];
        if (isset($rows[$cid])) {
            continue;
        }
        $rows[$cid] = $row;
    }
    if (!$rows) {
        return $sticky = [];
    }

    $offset = (int)$options->timezone - (int)$options->serverTimezone;
    $route = \Typecho\Router::get('post');
    $routeParams = is_array($route) && isset($route['params']) && is_array($route['params'])
        ? $route['params'] : [];
    $needsCategory = in_array('category', $routeParams, true);
    $categories = [];
    if ($needsCategory) {
        $categoryRows = $db->fetchAll($db->select('table.relationships.cid', 'table.metas.slug')
            ->from('table.relationships')
            ->join('table.metas', 'table.relationships.mid = table.metas.mid')
            ->where('table.relationships.cid IN ?', array_keys($rows))
            ->where('table.metas.type = ?', 'category'));
        foreach ($categoryRows as $categoryRow) {
            $categoryCid = (int)$categoryRow['cid'];
            $categorySlug = (string)$categoryRow['slug'];
            if (!isset($categories[$categoryCid]) && $categorySlug !== '') {
                $categories[$categoryCid] = $categorySlug;
            }
        }
    }

    $posts = [];
    foreach ($rows as $cid => $row) {
        $created = (int)$row['created'];
        $params = [
            'cid' => $cid,
            'slug' => urlencode((string)$row['slug']),
            'category' => isset($categories[$cid]) ? urlencode($categories[$cid]) : '',
            'year' => date('Y', $created + $offset),
            'month' => date('m', $created + $offset),
            'day' => date('d', $created + $offset),
        ];
        $posts[] = [
            'cid' => $cid,
            'title' => (string)$row['title'],
            'permalink' => \Typecho\Router::url('post', $params, $options->index),
            'date' => date('Y年m月d日', $created + $offset),
        ];
    }
    return $sticky = $posts;
}

/**
 * 关于页统计缓存：只缓存聚合结果，不缓存文章正文。
 * 使用文章数量、修改时间、最大 cid、最早时间、受保护文章状态与站点日期做签名；
 * 发布/删除/编辑/加锁/解锁/建站日期变化后自动失效，另有 24 小时兜底刷新。
 */
function yingSiteStatsCachePath(): string
{
    $root = defined('__TYPECHO_ROOT_DIR__') ? __TYPECHO_ROOT_DIR__ : dirname(__DIR__, 3);
    $root = rtrim(str_replace('\\', '/', $root), '/');
    return $root . '/usr/uploads/.ying-site-stats-' . substr(md5($root), 0, 12) . '.json';
}

function yingSiteStatsSignature(\Widget\Options $options, $db): string
{
    $row = $db->fetchRow($db->select(
        'COUNT(*) AS total',
        'MAX(modified) AS modified',
        'MAX(cid) AS maxcid',
        'MIN(created) AS earliest'
    )->from('table.contents')
        ->where('status = ?', 'publish')
        ->where('created < ?', (int)$options->time)
        ->where('type = ?', 'post'));

    $protectedRows = $db->fetchAll($db->select('cid', 'password')
        ->from('table.contents')
        ->where('status = ?', 'publish')
        ->where('created < ?', (int)$options->time)
        ->where('type = ?', 'post')
        ->where('password IS NOT NULL'));
    $protectedIds = [];
    foreach ($protectedRows as $protectedRow) {
        if ((string)($protectedRow['password'] ?? '') !== '') {
            $protectedIds[] = (int)$protectedRow['cid'];
        }
    }
    sort($protectedIds, SORT_NUMERIC);

    return md5(json_encode([
        'total' => (int)($row['total'] ?? 0),
        'modified' => (int)($row['modified'] ?? 0),
        'maxcid' => (int)($row['maxcid'] ?? 0),
        'earliest' => (int)($row['earliest'] ?? 0),
        'protected' => $protectedIds,
        'start' => trim((string)$options->siteStartDate),
        'timezone' => (int)$options->timezone,
        'serverTimezone' => (int)$options->serverTimezone,
        'day' => date('Y-m-d'),
    ], JSON_UNESCAPED_UNICODE));
}

/**
 * 关于页统计：已发布文章数、总字数、运行天数
 * 建站日期取后台设置，未设置时回退到最早一篇文章的日期
 */
function yingSiteStats(): array
{
    $options = \Widget\Options::alloc();
    $db = \Typecho\Db::get();
    $cacheFile = yingSiteStatsCachePath();
    $signature = yingSiteStatsSignature($options, $db);
    if (is_file($cacheFile) && time() - (int)filemtime($cacheFile) < 86400) {
        $cached = json_decode((string)@file_get_contents($cacheFile), true);
        if (is_array($cached) && ($cached['signature'] ?? '') === $signature
            && isset($cached['stats']) && is_array($cached['stats'])) {
            $stats = $cached['stats'];
            if (isset($stats['posts'], $stats['words'], $stats['start'])
                && array_key_exists('days', $stats)) {
                return [
                    'posts' => (int)$stats['posts'],
                    'words' => (int)$stats['words'],
                    'days' => $stats['days'] === null ? null : (int)$stats['days'],
                    'start' => (string)$stats['start'],
                ];
            }
        }
    }

    $rows = $db->fetchAll($db->select('created', 'text')->from('table.contents')
        ->where('status = ?', 'publish')
        ->where('created < ?', (int)$options->time)
        ->where('type = ?', 'post'));

    $posts = count($rows);
    $words = 0;
    $earliest = null;
    foreach ($rows as $row) {
        $words += yingWordCount((string)$row['text']);
        $created = (int)$row['created'];
        if ($earliest === null || $created < $earliest) {
            $earliest = $created;
        }
    }

    $start = trim((string)$options->siteStartDate);
    if (!preg_match('/^\d{4}-\d{2}-\d{2}$/', $start) || !strtotime($start)) {
        $start = $earliest !== null ? date('Y-m-d', $earliest) : '';
    }

    $days = null;
    if ($start !== '') {
        $days = max(0, (int)round((strtotime(date('Y-m-d')) - strtotime($start)) / 86400)) + 1;
    }

    $stats = ['posts' => $posts, 'words' => $words, 'days' => $days, 'start' => $start];
    @file_put_contents(
        $cacheFile,
        json_encode(['signature' => $signature, 'stats' => $stats], JSON_UNESCAPED_UNICODE),
        LOCK_EX
    );
    return $stats;
}

/**
 * 档案版的站点足迹与热力图共享缓存；签名与统计缓存一致，
 * 文章发布/删除/编辑、时区或日期变化后失效。
 */
function yingAboutDataCachePath(): string
{
    $root = defined('__TYPECHO_ROOT_DIR__') ? __TYPECHO_ROOT_DIR__ : dirname(__DIR__, 3);
    $root = rtrim(str_replace('\\', '/', $root), '/');
    $temp = sys_get_temp_dir();
    if ($temp === '' || !is_dir($temp) || !is_writable($temp)) {
        return '';
    }
    return rtrim(str_replace('\\', '/', $temp), '/') . '/ying-about-' . substr(md5($root), 0, 12) . '.json';
}

function yingAboutMaskedTitle(array $row): string
{
    return (string)($row['password'] ?? '') !== ''
        ? _t('此内容被密码保护')
        : (string)($row['title'] ?? '');
}

function yingAboutTimelineData(): array
{
    $options = \Widget\Options::alloc();
    $db = \Typecho\Db::get();
    $offset = (int)$options->timezone - (int)$options->serverTimezone;
    $earliest = $db->fetchRow($db->select('cid', 'title', 'created', 'password')
        ->from('table.contents')
        ->where('status = ?', 'publish')
        ->where('created < ?', (int)$options->time)
        ->where('type = ?', 'post')
        ->order('created', \Typecho\Db::SORT_ASC)
        ->limit(1));
    $latest = $db->fetchRow($db->select('cid', 'title', 'created', 'modified', 'password')
        ->from('table.contents')
        ->where('status = ?', 'publish')
        ->where('created < ?', (int)$options->time)
        ->where('type = ?', 'post')
        ->order('modified', \Typecho\Db::SORT_DESC)
        ->limit(1));

    $startRaw = trim((string)$options->siteStartDate);
    $startExplicit = preg_match('/^\d{4}-\d{2}-\d{2}$/', $startRaw) === 1
        && (bool)strtotime($startRaw);
    $startDate = $startExplicit
        ? $startRaw
        : ($earliest ? date('Y-m-d', (int)$earliest['created'] + $offset) : '');
    $entries = [];
    if ($startDate !== '') {
        $entries[] = [
            'date' => $startDate,
            'label' => $startExplicit ? _t('站点建立') : _t('开始记录'),
            'title' => '',
        ];
    }
    $earliestDate = $earliest ? date('Y-m-d', (int)$earliest['created'] + $offset) : '';
    if ($earliest) {
        $entries[] = [
            'date' => $earliestDate,
            'label' => _t('最早文章'),
            'title' => yingAboutMaskedTitle($earliest),
        ];
    }
    $latestStamp = $latest ? ((int)$latest['modified'] ?: (int)$latest['created']) : 0;
    $latestDate = $latest ? date('Y-m-d', $latestStamp + $offset) : '';
    $sameAsEarliest = $earliest && $latest
        && (int)$earliest['cid'] === (int)$latest['cid']
        && $earliestDate === $latestDate;
    if ($latest && !$sameAsEarliest && $latestDate !== $startDate) {
        $entries[] = [
            'date' => $latestDate,
            'label' => _t('最近更新'),
            'title' => yingAboutMaskedTitle($latest),
        ];
    }
    $dates = array_unique(array_column($entries, 'date'));
    if (count($entries) < 2 || count($dates) < 2) {
        return [];
    }
    usort($entries, static function (array $a, array $b): int {
        return strcmp($b['date'], $a['date']);
    });
    return $entries;
}

function yingAboutHeatmapData(): array
{
    $options = \Widget\Options::alloc();
    $db = \Typecho\Db::get();
    $offset = (int)$options->timezone - (int)$options->serverTimezone;
    $todayLocal = date('Y-m-d', time() + $offset);
    try {
        $today = new \DateTimeImmutable($todayLocal, new \DateTimeZone('UTC'));
        $start = $today->modify('-364 days');
        $start = $start->modify('-' . (int)$start->format('w') . ' days');
    } catch (\Exception $e) {
        return [];
    }
    $fromUtc = $start->getTimestamp() - $offset;
    $rows = $db->fetchAll($db->select('created')->from('table.contents')
        ->where('status = ?', 'publish')
        ->where('created < ?', (int)$options->time)
        ->where('type = ?', 'post')
        ->where('created >= ?', (int)$fromUtc));
    $counts = [];
    foreach ($rows as $row) {
        $key = date('Y-m-d', (int)$row['created'] + $offset);
        $counts[$key] = ($counts[$key] ?? 0) + 1;
    }
    $cells = [];
    $total = 0;
    // 按“星期一行 × 53 周列”的 row-major 顺序生成，供 CSS grid 直接布局。
    for ($day = 0; $day < 7; $day++) {
        for ($week = 0; $week < 53; $week++) {
            $date = $start->modify('+' . ($week * 7 + $day) . ' days');
            $key = $date->format('Y-m-d');
            $future = $date > $today;
            $count = $future ? 0 : (int)($counts[$key] ?? 0);
            if (!$future) {
                $total += $count;
            }
            $cells[] = [
                'date' => $key,
                'count' => $count,
                'future' => $future,
                'weekday' => (int)$date->format('w'),
            ];
        }
    }
    if ($total === 0) {
        return [];
    }
    $months = [];
    $cursor = $start->modify('first day of this month');
    while ($cursor <= $today && count($months) < 13) {
        $months[] = $cursor->format('n') . _t('月');
        $cursor = $cursor->modify('+1 month');
    }
    return [
        'total' => $total,
        'cells' => $cells,
        'months' => $months,
    ];
}

function yingAboutData(): array
{
    $options = \Widget\Options::alloc();
    $db = \Typecho\Db::get();
    $cacheFile = yingAboutDataCachePath();
    $signature = yingSiteStatsSignature($options, $db);
    if ($cacheFile !== '' && is_file($cacheFile) && time() - (int)filemtime($cacheFile) < 86400) {
        $cached = json_decode((string)@file_get_contents($cacheFile), true);
        if (is_array($cached) && ($cached['signature'] ?? '') === $signature
            && isset($cached['timeline'], $cached['heatmap'])
            && is_array($cached['timeline']) && is_array($cached['heatmap'])) {
            return ['timeline' => $cached['timeline'], 'heatmap' => $cached['heatmap']];
        }
    }
    $data = [
        'timeline' => yingAboutTimelineData(),
        'heatmap' => yingAboutHeatmapData(),
    ];
    if ($cacheFile !== '') {
        @file_put_contents(
            $cacheFile,
            json_encode(['signature' => $signature, 'timeline' => $data['timeline'], 'heatmap' => $data['heatmap']], JSON_UNESCAPED_UNICODE),
            LOCK_EX
        );
    }
    return $data;
}

/**
 * 关于页统计的启用项（后台勾选；选项从未保存时默认三项全开）
 */
function yingAboutStatsEnabled(): array
{
    $raw = \Widget\Options::alloc()->aboutStats;
    if ($raw === null) {
        return ['posts', 'words', 'days'];
    }
    $list = is_array($raw) ? $raw : explode(',', (string)$raw);
    return array_values(array_intersect(['posts', 'words', 'days'], $list));
}

function yingAboutStatsPos(): string
{
    return (string)\Widget\Options::alloc()->aboutStatsPos === 'end' ? 'end' : 'top';
}

function yingAboutStatsAlign(): string
{
    $align = (string)\Widget\Options::alloc()->aboutStatsAlign;
    return in_array($align, ['center', 'right'], true) ? $align : 'left';
}

/**
 * 运行时长（数字后「年 / 天」用朱砂色标记）
 */
function yingUptimeHtml(int $days): string
{
    if ($days < 365) {
        return number_format($days) . '<span class="u">' . _t('天') . '</span>';
    }
    $years = intdiv($days, 365);
    $rest = $days % 365;
    $out = number_format($years) . '<span class="u">' . _t('年') . '</span>';
    if ($rest > 0) {
        $out .= ' ' . number_format($rest) . '<span class="u">' . _t('天') . '</span>';
    }
    return $out;
}

function yingAboutShowTimeline(): bool
{
    return (string)\Widget\Options::alloc()->aboutShowTimeline !== 'off';
}

function yingAboutShowHeatmap(): bool
{
    return (string)\Widget\Options::alloc()->aboutShowHeatmap !== 'off';
}

/**
 * 档案版统计卡片的数据项；3/2/1 项均按实际启用项生成。
 */
function yingAboutStatsItems(): array
{
    $enabled = yingAboutStatsEnabled();
    if (!$enabled) {
        return [];
    }
    $stats = yingSiteStats();
    $items = [];
    if (in_array('posts', $enabled, true) && (int)$stats['posts'] > 0) {
        $items[] = ['label' => _t('文章'), 'value' => number_format((int)$stats['posts']), 'unit' => _t('篇'), 'title' => ''];
    }
    if (in_array('words', $enabled, true) && (int)$stats['words'] > 0) {
        $items[] = ['label' => _t('总字数'), 'value' => number_format((int)$stats['words']), 'unit' => _t('字'), 'title' => ''];
    }
    if (in_array('days', $enabled, true) && $stats['days'] !== null) {
        $items[] = ['label' => _t('运行'), 'value' => number_format((int)$stats['days']), 'unit' => _t('天'), 'title' => (string)$stats['start']];
    }
    return $items;
}

/**
 * 档案版统计卡：大号衬线数字 + 小标签 + 朱砂单位。
 */
function yingAboutStatsCardsHtml(string $slot): string
{
    if ($slot !== yingAboutStatsPos()) {
        return '';
    }
    $items = yingAboutStatsItems();
    if (!$items) {
        return '';
    }
    $count = count($items);
    $html = '<div class="about-stat-cards is-align-' . yingAboutStatsAlign()
        . ($slot === 'end' ? ' is-end' : '') . ' is-count-' . $count
        . '" role="list" aria-label="' . _t('站点统计') . '">';
    foreach ($items as $item) {
        $title = $item['title'] !== ''
            ? ' title="' . htmlspecialchars(_t('起于 ') . $item['title'], ENT_QUOTES) . '"'
            : '';
        $html .= '<div class="about-stat-card" role="listitem"' . $title . '>'
            . '<span class="about-stat-value">' . htmlspecialchars($item['value'], ENT_QUOTES)
            . '<span class="u">' . htmlspecialchars($item['unit'], ENT_QUOTES) . '</span></span>'
            . '<span class="about-stat-label">' . htmlspecialchars($item['label'], ENT_QUOTES) . '</span>'
            . '</div>';
    }
    return $html . '</div>';
}

function yingAboutStatsDisplayHtml(string $slot): string
{
    return yingAboutStatsCardsHtml($slot);
}

function yingFontSourceOptions(): array
{
    return [
        'cdn' => [_t('ZQL CDN'), _t('默认')],
        'local' => [_t('本地字体'), _t('上传')],
        'none' => [_t('系统字体'), _t('不加载文件')],
        'harmony' => [_t('鸿蒙 CDN'), _t('备用')],
        'custom' => [_t('自定义链接'), _t('URL')],
    ];
}

function yingFontStatusText(string $source, array $library, string $selected, string $url): string
{
    if ($source === 'none') {
        return _t('当前使用：系统字体（不加载字体文件）');
    }
    if ($source === 'cdn') {
        return _t('当前使用：ZQL CDN（jsDelivr）');
    }
    if ($source === 'harmony') {
        return _t('当前使用：鸿蒙 CDN（jsDelivr）');
    }
    if ($source === 'custom') {
        return yingUrlIsSafe($url)
            ? _t('当前使用：自定义地址')
            : _t('当前使用：系统字体（字体地址无效）');
    }
    foreach ($library as $item) {
        if (($item['file'] ?? '') === $selected) {
            return _t('当前使用：') . $item['name'] . _t(' · 本地字体');
        }
    }
    return _t('当前使用：系统字体（尚未选择本地字体）');
}

/**
 * 后台字体库列表
 */
function yingFontLibraryHtml(array $library, string $selected, array $pendingDelete = []): string
{
    if (empty($library)) {
        return '<div class="ying-font-empty">'
            . '<span class="ying-font-empty-glyph" aria-hidden="true">Aa</span>'
            . '<span class="ying-font-empty-text">'
            . '<span class="ying-font-empty-title">' . _t('字体库还是空的') . '</span>'
            . '<span class="ying-font-empty-note">' . _t('上传一个字体，它会出现在这里。') . '</span>'
            . '</span>'
            . '</div>';
    }

    $html = '<div class="ying-library" role="radiogroup" aria-label="' . _t('本地字体库') . '">';
    foreach ($library as $index => $font) {
        $file = (string)$font['file'];
        $id = 'ying-font-choice-' . $index;
        $active = $file === $selected;
        $pending = in_array($file, $pendingDelete, true);
        $name = htmlspecialchars((string)$font['name'], ENT_QUOTES, 'UTF-8');
        $format = strtoupper((string)$font['format']);
        $meta = $format . ' · ' . yingFormatBytes((int)$font['size']) . ' · ' . date('Y-m-d', (int)$font['time']);

        $html .= '<div class="ying-font-card' . ($active ? ' is-active' : '') . ($pending ? ' is-pending-delete' : '') . '"'
            . ' style="--ying-preview:\'YingPreview-' . $index . '\'"'
            . ' data-file="' . htmlspecialchars($file, ENT_QUOTES, 'UTF-8') . '"'
            . ' data-name="' . $name . '"'
            . ' data-format="' . htmlspecialchars($format, ENT_QUOTES, 'UTF-8') . '"'
            . ' data-size="' . htmlspecialchars(yingFormatBytes((int)$font['size']), ENT_QUOTES, 'UTF-8') . '"'
            . ' data-date="' . date('Y-m-d', (int)$font['time']) . '">';
        $html .= '<input type="radio" name="fontLocal" id="' . $id . '" value="' . htmlspecialchars($file, ENT_QUOTES, 'UTF-8') . '"'
            . ' aria-label="' . $name . '"' . ($active ? ' checked' : '') . '>';
        $html .= '<label class="ying-font-card-main" for="' . $id . '">';
        $html .= '<span class="ying-font-glyph" aria-hidden="true">Aa 永</span>';
        $html .= '<span class="ying-font-text">';
        $html .= '<span class="ying-font-name">' . $name;
        if ($active) {
            $html .= '<span class="ying-font-stamp">' . _t('已选择') . '</span>';
        }
        if ($pending) {
            $html .= '<span class="ying-font-pending-stamp">' . _t('待删除') . '</span>';
        }
        $html .= '</span>';
        $html .= '<span class="ying-font-meta">' . htmlspecialchars($meta, ENT_QUOTES, 'UTF-8') . '</span>';
        $html .= '</span>';
        $html .= '</label>';
        $html .= '<label class="ying-font-remove"><input type="checkbox" name="fontDelete[]" value="' . htmlspecialchars($file, ENT_QUOTES, 'UTF-8') . '"'
            . ' aria-label="' . htmlspecialchars(_t('删除字体') . ' ' . $font['name'], ENT_QUOTES, 'UTF-8') . '"'
            . ($pending ? ' checked' : '') . '><span>' . _t('删除') . '</span></label>';
        $html .= '</div>';
    }
    $html .= '</div>';
    $html .= '<span class="ying-font-library-note" data-library-note">' . _t('选择卡片切换字体并预览；删除需要保存后生效。') . '</span>';
    return $html;
}

/**
 * 后台字体上传区
 */
function yingFontUploadHtml(string $limitText): string
{
    return '<div class="ying-upload-wrap">'
        . '<label class="ying-drop" for="ying-font-file">'
        . '<input type="file" id="ying-font-file" name="fontFile" class="ying-file-input" accept=".woff2,.woff,.ttf,.otf" aria-label="' . _t('选择字体文件') . '">'
        . '<span class="ying-drop-mark" aria-hidden="true">+</span>'
        . '<span class="ying-drop-title">' . _t('拖入字体，或点击选择') . '</span>'
        . '<span class="ying-drop-note">' . _t('WOFF2 / WOFF / TTF / OTF · 单个不超过 ') . $limitText . '</span>'
        . '<span class="ying-drop-file" data-empty="1" role="status" aria-live="polite">' . _t('未选择文件') . '</span>'
        . '</label>'
        . '<p class="ying-upload-note">' . _t('上传后自动选中；文件保存在本站，不进入主题包。请确认字体授权。') . '</p>'
        . '</div>';
}

/**
 * 渲染文章前自增浏览次数
 */
function themeInit($archive)
{
    if ($archive->is('post')) {
        yingBumpViews(intval($archive->cid));
    }
}

/**
 * 浏览次数 +1（存于 typecho_fields 表；同一浏览器 24 小时去重）
 */
function yingBumpViews(int $cid): void
{
    $cookie = 'ying_view_' . $cid;
    if (isset($_COOKIE[$cookie])) {
        return;
    }
    try {
        $db = \Typecho\Db::get();
        $affected = $db->query($db->update('table.fields')
            ->expression('int_value', 'int_value + 1')
            ->where('cid = ?', $cid)->where('name = ?', 'views'));
        if ($affected === 0) {
            $db->query($db->insert('table.fields')
                ->rows(['cid' => $cid, 'name' => 'views', 'type' => 'int', 'int_value' => 1]));
        }
        setcookie($cookie, '1', [
            'expires' => time() + 86400,
            'path' => '/',
            'secure' => (!empty($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off')
                || (($_SERVER['HTTP_X_FORWARDED_PROTO'] ?? '') === 'https')
                || ((int)($_SERVER['SERVER_PORT'] ?? 0) === 443),
            'httponly' => true,
            'samesite' => 'Lax',
        ]);
    } catch (\Exception $e) {
        // 计数失败不影响正常输出
    }
}

/**
 * 清理旧版按 IP/日期写入的 view_<md5> 去重标记
 */
function yingCleanupLegacyViewMarks(): void
{
    try {
        $db = \Typecho\Db::get();
        $rows = $db->fetchAll($db->select('cid', 'name')->from('table.fields')
            ->where('type = ?', 'str')
            ->where('name LIKE ?', 'view%'));
        foreach ($rows as $row) {
            if (preg_match('/^view_[0-9a-f]{32}$/', (string)$row['name'])) {
                $db->query($db->delete('table.fields')
                    ->where('cid = ?', $row['cid'])->where('name = ?', $row['name']));
            }
        }
    } catch (\Exception $e) {
    }
}

/**
 * 读取文章浏览次数
 */
function yingGetViews(int $cid): int
{
    try {
        $db = \Typecho\Db::get();
        $row = $db->fetchRow($db->select('int_value')->from('table.fields')
            ->where('cid = ?', $cid)->where('name = ?', 'views'));
        return $row ? intval($row['int_value']) : 0;
    } catch (\Exception $e) {
        return 0;
    }
}

/**
 * 统计文字数量（去除空白与标签）
 */
function yingWordCount(string $html): int
{
    $text = trim(preg_replace('/\s+/u', '', strip_tags($html)));
    return mb_strlen($text, 'UTF-8');
}

/**
 * JSON 根节点必须是列表；对象（含数字键对象）不再静默兼容，
 * 避免 JS 编辑器把对象当成空数组后覆盖原有数据。
 */
function yingJsonList(string $value): ?array
{
    $value = trim($value);
    if ($value === '') {
        return [];
    }
    // json_decode 会把数字键对象转成数组；先按原始根字符拒绝对象。
    if ($value[0] !== '[') {
        return null;
    }
    $data = json_decode($value, true);
    if (!is_array($data)) {
        return null;
    }
    $expected = 0;
    foreach ($data as $key => $item) {
        if ($key !== $expected++) {
            return null;
        }
    }
    return $data;
}

/**
 * 锁定页面统一输出核心口令表单：不渲染模板正文/统计，仍可正常解锁。
 */
function yingLockedPageHtml($archive): string
{
    return '<div class="post-content post-protected">' . (string)$archive->content . '</div>';
}

/**
 * 关于页 Markdown：只解析 Markdown 语法，原始 HTML 保持转义。
 */
function yingAboutMarkdown(string $text): string
{
    $text = trim($text);
    if ($text === '') {
        return '';
    }
    if (class_exists('\Utils\HyperDown')) {
        static $parser;
        if (!$parser) {
            $parser = new \Utils\HyperDown();
            $parser->enableHtml(false);
        }
        return $parser->makeHtml($text);
    }
    return '<p>' . nl2br(htmlspecialchars($text, ENT_QUOTES, 'UTF-8')) . '</p>';
}

/**
 * 档案版：单栏摘录笺、统计卡、站点足迹、近一年热力图。
 * 只使用主题现有纸墨色与朱砂强调，不引入外部资源。
 */
function yingAboutDossierHtml(): string
{
    $options = \Widget\Options::alloc();
    $fields = ['aboutIntro', 'aboutQuote', 'aboutTopics', 'aboutTheme', 'aboutStack', 'aboutOutro'];
    $values = [];
    foreach ($fields as $field) {
        $values[$field] = trim((string)$options->{$field});
    }
    $escape = static function (string $value): string {
        return htmlspecialchars($value, ENT_QUOTES, 'UTF-8');
    };
    $pairs = static function (string $raw): array {
        $rows = [];
        foreach (preg_split('/\R/u', $raw) ?: [] as $line) {
            $line = trim($line);
            if ($line === '') {
                continue;
            }
            $parts = preg_split('/\s*[|｜]\s*/u', $line, 2) ?: [];
            $key = trim((string)($parts[0] ?? ''));
            if ($key === '') {
                continue;
            }
            $rows[] = [$key, trim((string)($parts[1] ?? ''))];
        }
        return $rows;
    };

    $extraSections = [];
    $extrasValue = $options->aboutExtraSections;
    if (is_array($extrasValue)) {
        $extrasData = $extrasValue;
    } else {
        $extrasRaw = trim((string)$extrasValue);
        $extrasData = $extrasRaw !== '' ? yingJsonList($extrasRaw) : [];
    }
    if (is_array($extrasData)) {
        foreach ($extrasData as $extra) {
            if (!is_array($extra)) {
                continue;
            }
            $extraTitle = isset($extra['title']) && is_string($extra['title'])
                ? trim($extra['title']) : '';
            $extraBody = isset($extra['body']) && is_string($extra['body'])
                ? trim($extra['body']) : '';
            if ($extraTitle === '' && $extraBody === '') {
                continue;
            }
            $extraSections[] = ['title' => $extraTitle, 'body' => $extraBody];
        }
    }

    $intro = $values['aboutIntro'] !== '' ? yingAboutMarkdown($values['aboutIntro']) : '';
    $quoteHtml = $values['aboutQuote'] !== ''
        ? '<blockquote class="about-quote about-letter-pullquote"><p>'
            . $escape($values['aboutQuote']) . '</p></blockquote>'
        : '';
    $topics = $pairs($values['aboutTopics']);
    $topicsHtml = '';
    if ($topics) {
        $topicsHtml = '<ul class="about-letter-topics">';
        foreach ($topics as $topic) {
            $topicsHtml .= '<li><strong>' . $escape($topic[0]) . '</strong>'
                . ($topic[1] !== '' ? '<span>' . $escape($topic[1]) . '</span>' : '')
                . '</li>';
        }
        $topicsHtml .= '</ul>';
    }

    $themeHtml = $values['aboutTheme'] !== '' ? yingAboutMarkdown($values['aboutTheme']) : '';
    $stack = $pairs($values['aboutStack']);
    $stackHtml = '';
    if ($stack) {
        $stackHtml = '<table class="about-stack-table"><thead><tr><th>' . _t('组件')
            . '</th><th>' . _t('选择') . '</th></tr></thead><tbody>';
        foreach ($stack as $row) {
            $stackHtml .= '<tr><td>' . $escape($row[0]) . '</td><td>' . $escape($row[1]) . '</td></tr>';
        }
        $stackHtml .= '</tbody></table>';
    }

    $topicsTitle = trim((string)($options->aboutTopicsTitle ?? ''));
    if ($topicsTitle === '') {
        $topicsTitle = _t('这里写什么');
    }
    $themeTitle = trim((string)($options->aboutThemeTitle ?? ''));
    if ($themeTitle === '') {
        $themeTitle = _t('关于这个主题');
    }
    $stackTitle = trim((string)($options->aboutStackTitle ?? ''));
    if ($stackTitle === '') {
        $stackTitle = _t('技术栈');
    }

    $blocks = [];
    if ($topicsHtml !== '') {
        $blocks[] = [$topicsTitle, $topicsHtml];
    }
    if ($themeHtml !== '') {
        $blocks[] = [$themeTitle, $themeHtml];
    }
    if ($stackHtml !== '') {
        $blocks[] = [$stackTitle, $stackHtml];
    }
    foreach ($extraSections as $extraSection) {
        $blocks[] = [
            $extraSection['title'] !== '' ? $extraSection['title'] : _t('补记'),
            yingAboutMarkdown($extraSection['body']),
        ];
    }

    $data = [];
    if (yingAboutShowTimeline() || yingAboutShowHeatmap()) {
        $data = yingAboutData();
    }
    $timeline = yingAboutShowTimeline() && !empty($data['timeline']) ? $data['timeline'] : [];
    $heatmap = yingAboutShowHeatmap() && !empty($data['heatmap']) ? $data['heatmap'] : [];

    $html = '<div class="post-content about-content about-dossier" itemprop="articleBody">';
    if ($intro !== '' || $quoteHtml !== '' || $blocks !== []) {
        $html .= '<div class="about-letter">';
        if ($intro !== '') {
            $html .= '<div class="about-letter-intro">' . $intro . '</div>';
        }
        $html .= $quoteHtml;
        foreach ($blocks as $block) {
            $html .= '<section class="about-letter-line"><h2 class="about-letter-label">'
                . $escape($block[0]) . '</h2><div class="about-letter-body">'
                . $block[1] . '</div></section>';
        }
        $html .= '</div>';
    }
    if ($timeline) {
        $html .= '<section class="about-dossier-section about-footprint" aria-labelledby="about-footprint-title">'
            . '<h2 id="about-footprint-title" class="about-dossier-section-title">' . _t('站点足迹') . '</h2>'
            . '<ol class="about-footprint-list">';
        foreach ($timeline as $item) {
            $html .= '<li class="about-footprint-item"><time datetime="' . $escape($item['date']) . '">'
                . $escape($item['date']) . '</time><span class="about-footprint-event">'
                . $escape($item['label']) . '</span>';
            if ($item['title'] !== '') {
                $html .= '<span class="about-footprint-title">' . $escape($item['title']) . '</span>';
            }
            $html .= '</li>';
        }
        $html .= '</ol></section>';
    }
    if ($heatmap) {
        $max = 0;
        foreach ($heatmap['cells'] as $cell) {
            $max = max($max, (int)$cell['count']);
        }
        $html .= '<section class="about-dossier-section about-activity" aria-labelledby="about-activity-title">'
            . '<div class="about-dossier-section-head"><h2 id="about-activity-title" class="about-dossier-section-title">'
            . _t('文章活跃') . '</h2><span class="about-dossier-section-note">'
            . sprintf(_t('近一年 · %d 篇'), (int)$heatmap['total']) . '</span></div>'
            . '<div class="about-heatmap-months" aria-hidden="true">';
        foreach ($heatmap['months'] as $month) {
            $html .= '<span>' . $escape($month) . '</span>';
        }
        $html .= '</div><div class="about-heatmap-grid" role="img" aria-label="'
            . $escape(sprintf(_t('近一年文章活跃热力图，共 %d 篇；颜色越深表示当天文章越多'), (int)$heatmap['total']))
            . '">';
        foreach ($heatmap['cells'] as $cell) {
            $level = ((int)$cell['count'] === 0 || $max === 0)
                ? 0 : max(1, min(4, (int)ceil((int)$cell['count'] * 4 / $max)));
            $title = !empty($cell['future']) ? ''
                : $escape($cell['date'] . ' · ' . sprintf(_t('%d 篇'), (int)$cell['count']));
            $html .= '<span class="about-heatmap-cell level-' . $level
                . (!empty($cell['future']) ? ' is-future' : '')
                . '"' . ($title !== '' ? ' title="' . $title . '"' : '') . '></span>';
        }
        $html .= '</div></section>';
    }
    if ($values['aboutOutro'] !== '') {
        $html .= '<div class="about-dossier-outro">' . yingAboutMarkdown($values['aboutOutro']) . '</div>';
    }
    $html .= yingAboutStatsDisplayHtml('end');
    $html .= '</div><p class="about-end" aria-hidden="true">' . _t('完') . '</p>';
    return $html;
}

/**
 * 关于页结构化内容、文末统计与「完」的统一输出（about.php 与 start-page 共用）。
 */
function yingAboutMainHtml(): string
{
    return yingAboutDossierHtml();
}

/**
 * 为正文 <img> 补 loading="lazy" 与 decoding="async"，已声明 loading 的不动
 */
function yingContentHtml(string $html): string
{
    return preg_replace_callback('/<img\b([^>]*)>/i', function (array $m): string {
        $attrs = rtrim($m[1]);
        if (preg_match('/\bloading\s*=/i', $attrs)) {
            return $m[0];
        }
        if (substr($attrs, -1) === '/') {
            $attrs = rtrim(substr($attrs, 0, -1));
        }
        return '<img' . $attrs . ' loading="lazy" decoding="async">';
    }, $html) ?? $html;
}

/**
 * 友链动态编辑器（后台 HTML/JS，通过表单 description 注入）
 */
function yingFriendsEditorHtml(): string
{
    return <<<'HTML'
<div id="friends-editor"></div>
<button type="button" id="friend-add-btn" class="btn btn-s">+ 添加友链</button>
<style>
#friends-editor .friend-block { border: 1px solid #dcdcdc; border-radius: 6px; padding: 10px 12px; margin: 8px 0 12px; background: #fafafa; }
#friends-editor .friend-block-head { display: flex; justify-content: space-between; align-items: center; margin-bottom: 8px; }
#friends-editor .friend-block-head strong { font-size: 13px; color: #444; }
#friends-editor .friend-del { border: 0; background: #ffeceb; color: #c0392b; border-radius: 4px; padding: 3px 12px; cursor: pointer; font-size: 12px; }
#friends-editor .friend-del:hover { background: #ffd6d3; }
#friends-editor .friend-row { display: flex; align-items: center; gap: 8px; margin: 6px 0; flex-wrap: wrap; }
#friends-editor .friend-row > label { flex: none; width: 42px; font-size: 13px; color: #666; margin: 0; }
#friends-editor .friend-row > input[type=text] { flex: 1; min-width: 180px; }
#friends-editor .friend-row .f-name { min-width: 140px !important; }
#friends-editor .friend-row > textarea { flex: 1; min-width: 260px; }
#friends-editor .friend-avatar-preview { flex: none; width: 36px; height: 36px; border-radius: 50%; background: #e6e6e6; display: inline-flex; align-items: center; justify-content: center; color: #999; font-size: 14px; overflow: hidden; }
#friend-add-btn { margin-top: 4px; }
</style>
<script>
(function () {
    function boot() {
        var storage = document.querySelector('input[name=friendsJson]');
        var wrap = document.getElementById('friends-editor');
        var addBtn = document.getElementById('friend-add-btn');
        if (!storage || !wrap || !addBtn || storage.dataset.yingReady) {
            return;
        }
        storage.dataset.yingReady = '1';
        storage.type = 'hidden';
        var model = [];
        var rootInvalid = false;
        var warning = document.createElement('p');
        warning.id = 'friends-json-warning';
        warning.className = 'ying-json-warning';
        warning.setAttribute('role', 'alert');
        warning.hidden = true;
        wrap.parentNode.insertBefore(warning, wrap);
        function showWarning(message) {
            rootInvalid = true;
            warning.textContent = message;
            warning.hidden = false;
            addBtn.disabled = true;
        }
        try {
            var raw = storage.value.trim();
            if (raw !== '') {
                var parsed = JSON.parse(raw);
                if (Array.isArray(parsed)) {
                    model = parsed;
                } else {
                    showWarning('友链数据的根节点必须是列表（数组），当前数据未改动；请修复后再保存。');
                }
            }
        } catch (e) {
            showWarning('友链数据不是合法 JSON，当前数据未改动；请修复后再保存。');
        }

        function serialize() {
            if (!rootInvalid) {
                storage.value = JSON.stringify(model);
            }
        }

        function notifyDirty() {
            storage.dispatchEvent(new Event('input', {bubbles: true}));
        }

        function drawPreview(el, avatar, name) {
            if (avatar) {
                el.innerHTML = '';
                var img = document.createElement('img');
                img.src = avatar;
                img.style.width = '36px';
                img.style.height = '36px';
                img.style.borderRadius = '50%';
                img.style.objectFit = 'cover';
                img.onerror = function () {
                    el.textContent = (name || '友').substring(0, 1);
                };
                el.appendChild(img);
            } else {
                el.textContent = (name || '友').substring(0, 1);
            }
        }

        function render() {
            wrap.innerHTML = '';
            model.forEach(function (f, i) {
                var block = document.createElement('div');
                block.className = 'friend-block';
                block.innerHTML =
                    '<div class="friend-block-head"><strong>友链 ' + (i + 1) + '</strong>' +
                    '<button type="button" class="friend-del">删除</button></div>' +
                    '<div class="friend-row"><span class="friend-avatar-preview"></span>' +
                    '<input type="text" class="f-avatar" placeholder="头像图片 URL（留空用首字徽标）"></div>' +
                    '<div class="friend-row"><label>名字</label><input type="text" class="f-name" placeholder="网站名称">' +
                    '<label>链接</label><input type="text" class="f-url" placeholder="https://example.com"></div>' +
                    '<div class="friend-row"><label>简介</label><textarea class="f-desc" rows="2" placeholder="一句话介绍"></textarea></div>';
                var avatarInput = block.querySelector('.f-avatar');
                var nameInput = block.querySelector('.f-name');
                var urlInput = block.querySelector('.f-url');
                var descInput = block.querySelector('.f-desc');
                var previewEl = block.querySelector('.friend-avatar-preview');
                avatarInput.value = f.avatar || '';
                nameInput.value = f.name || '';
                urlInput.value = f.url || '';
                descInput.value = f.desc || '';
                drawPreview(previewEl, f.avatar || '', f.name || '');
                var sync = function () {
                    f.avatar = avatarInput.value.trim();
                    f.name = nameInput.value.trim();
                    f.url = urlInput.value.trim();
                    f.desc = descInput.value.trim();
                    drawPreview(previewEl, f.avatar, f.name);
                    serialize();
                };
                avatarInput.addEventListener('input', sync);
                nameInput.addEventListener('input', sync);
                urlInput.addEventListener('input', sync);
                descInput.addEventListener('input', sync);
                block.querySelector('.friend-del').addEventListener('click', function () {
                    model.splice(i, 1);
                    render();
                    serialize();
                    if (!rootInvalid) {
                        notifyDirty();
                    }
                });
                wrap.appendChild(block);
            });
        }

        addBtn.addEventListener('click', function () {
            model.push({ name: '', url: '', desc: '', avatar: '' });
            render();
            serialize();
            if (!rootInvalid) {
                notifyDirty();
            }
            var blocks = wrap.querySelectorAll('.friend-block');
            var last = blocks[blocks.length - 1];
            if (last) {
                last.scrollIntoView({ behavior: 'smooth', block: 'center' });
                last.querySelector('.f-name').focus();
            }
        });

        var form = storage.closest('form');
        if (form) {
            form.addEventListener('submit', serialize);
        }
        render();
    }
    if (document.readyState === 'loading') {
        document.addEventListener('DOMContentLoaded', boot);
    } else {
        boot();
    }
})();
</script>
HTML;
}

/**
 * 联系方式类型表（键 => [标签, 图标]）
 */
function yingContactTypes(): array
{
    return [
        'github'   => ['GitHub', 'ri-github-line'],
        'mail'     => ['邮箱', 'ri-mail-line'],
        'weibo'    => ['微博', 'ri-weibo-line'],
        'twitter'  => ['X', 'ri-twitter-x-line'],
        'bilibili' => ['B站', 'ri-bilibili-line'],
        'zhihu'    => ['知乎', 'ri-zhihu-line'],
        'telegram' => ['Telegram', 'ri-telegram-line'],
        'link'     => ['自定义链接', 'ri-link'],
    ];
}

/**
 * 联系方式动态编辑器（后台 HTML/JS）
 */
function yingContactsEditorHtml(): string
{
    $options = '';
    foreach (yingContactTypes() as $key => $info) {
        $options .= '<option value="' . $key . '">' . htmlspecialchars($info[0]) . '</option>';
    }

    return <<<HTML
<div id="contacts-editor"></div>
<button type="button" id="contact-add-btn" class="btn btn-s">+ 添加联系方式</button>
<style>
#contacts-editor .contact-block { border: 1px solid #dcdcdc; border-radius: 6px; padding: 10px 12px; margin: 8px 0 12px; background: #fafafa; display: flex; align-items: center; gap: 8px; flex-wrap: wrap; }
#contacts-editor .contact-block .ct-title { flex: none; font-size: 13px; color: #444; min-width: 64px; }
#contacts-editor .contact-block select { flex: none; }
#contacts-editor .contact-block input[type=text] { flex: 1; min-width: 200px; }
#contacts-editor .contact-icon-row { flex: 0 0 100%; display: flex; align-items: center; gap: 8px; padding-top: 2px; }
#contacts-editor .contact-icon-row[hidden] { display: none !important; }
#contacts-editor .contact-icon-row label { flex: none; width: 42px; margin: 0; color: #666; font-size: 13px; }
#contacts-editor .contact-icon-row input[type=text] { flex: 1; min-width: 200px; }
#contacts-editor .contact-del { border: 0; background: #ffeceb; color: #c0392b; border-radius: 4px; padding: 3px 12px; cursor: pointer; font-size: 12px; }
#contacts-editor .contact-del:hover { background: #ffd6d3; }
</style>
<script>
(function () {
    function boot() {
        var storage = document.querySelector('input[name=contactsJson]');
        var wrap = document.getElementById('contacts-editor');
        var addBtn = document.getElementById('contact-add-btn');
        if (!storage || !wrap || !addBtn || storage.dataset.yingReady) {
            return;
        }
        storage.dataset.yingReady = '1';
        storage.type = 'hidden';
        var model = [];
        var rootInvalid = false;
        var warning = document.createElement('p');
        warning.id = 'contacts-json-warning';
        warning.className = 'ying-json-warning';
        warning.setAttribute('role', 'alert');
        warning.hidden = true;
        wrap.parentNode.insertBefore(warning, wrap);
        function showWarning(message) {
            rootInvalid = true;
            warning.textContent = message;
            warning.hidden = false;
            addBtn.disabled = true;
        }
        try {
            var raw = storage.value.trim();
            if (raw !== '') {
                var parsed = JSON.parse(raw);
                if (Array.isArray(parsed)) {
                    model = parsed;
                } else {
                    showWarning('联系方式数据的根节点必须是列表（数组），当前数据未改动；请修复后再保存。');
                }
            }
        } catch (e) {
            showWarning('联系方式数据不是合法 JSON，当前数据未改动；请修复后再保存。');
        }
        var typeOptions = '{$options}';

        function serialize() {
            if (!rootInvalid) {
                storage.value = JSON.stringify(model);
            }
        }

        function notifyDirty() {
            storage.dispatchEvent(new Event('input', {bubbles: true}));
        }

        function render() {
            wrap.innerHTML = '';
            model.forEach(function (c, i) {
                var block = document.createElement('div');
                block.className = 'contact-block';
                block.innerHTML =
                    '<span class="ct-title">联系方式 ' + (i + 1) + '</span>' +
                    '<select class="c-type">' + typeOptions + '</select>' +
                    '<input type="text" class="c-url" placeholder="链接或邮箱地址（自动补全协议）">' +
                    '<button type="button" class="contact-del">删除</button>' +
                    '<div class="contact-icon-row" hidden><label>图标</label>' +
                    '<input type="text" class="c-icon" placeholder="自定义图标 URL（选“自定义链接”时可填，留空用默认图标）"></div>';
                var typeSel = block.querySelector('.c-type');
                var urlInput = block.querySelector('.c-url');
                var iconRow = block.querySelector('.contact-icon-row');
                var iconInput = block.querySelector('.c-icon');
                typeSel.value = c.type || 'link';
                urlInput.value = c.url || '';
                iconInput.value = c.icon || '';
                var syncType = function () {
                    iconRow.hidden = typeSel.value !== 'link';
                };
                var sync = function () {
                    c.type = typeSel.value;
                    c.url = urlInput.value.trim();
                    c.icon = iconInput.value.trim();
                    serialize();
                    syncType();
                };
                syncType();
                typeSel.addEventListener('change', sync);
                urlInput.addEventListener('input', sync);
                iconInput.addEventListener('input', sync);
                block.querySelector('.contact-del').addEventListener('click', function () {
                    model.splice(i, 1);
                    render();
                    serialize();
                    if (!rootInvalid) {
                        notifyDirty();
                    }
                });
                wrap.appendChild(block);
            });
        }

        addBtn.addEventListener('click', function () {
            model.push({ type: 'github', url: '', icon: '' });
            render();
            serialize();
            if (!rootInvalid) {
                notifyDirty();
            }
            var blocks = wrap.querySelectorAll('.contact-block');
            var last = blocks[blocks.length - 1];
            if (last) {
                last.scrollIntoView({ behavior: 'smooth', block: 'center' });
                last.querySelector('.c-url').focus();
            }
        });

        var form = storage.closest('form');
        if (form) {
            form.addEventListener('submit', serialize);
        }
        render();
    }
    if (document.readyState === 'loading') {
        document.addEventListener('DOMContentLoaded', boot);
    } else {
        boot();
    }
})();
</script>
HTML;
}

/**
 * 关于页自定义内容板块编辑器（标题 + 多段纯文本，可增删）。
 */
function yingAboutSectionsEditorHtml(): string
{
    return <<<'HTML'
<div id="about-sections-editor"></div>
<button type="button" id="about-section-add" class="btn btn-s">+ 添加内容板块</button>
<script>
(function () {
    function boot() {
        var storage = document.querySelector('input[name=aboutExtraSections]');
        var wrap = document.getElementById('about-sections-editor');
        var addBtn = document.getElementById('about-section-add');
        if (!storage || !wrap || !addBtn || storage.dataset.yingReady) {
            return;
        }
        storage.dataset.yingReady = '1';
        storage.type = 'hidden';
        var model = [];
        var rootInvalid = false;
        var warning = document.createElement('p');
        warning.id = 'about-sections-warning';
        warning.className = 'ying-json-warning';
        warning.setAttribute('role', 'alert');
        warning.hidden = true;
        wrap.parentNode.insertBefore(warning, wrap);
        function showWarning(message) {
            rootInvalid = true;
            warning.textContent = message;
            warning.hidden = false;
            addBtn.disabled = true;
        }
        try {
            var raw = storage.value.trim();
            if (raw !== '') {
                var parsed = JSON.parse(raw);
                if (Array.isArray(parsed)) {
                    model = parsed;
                } else {
                    showWarning('内容板块数据的根节点必须是列表（数组），当前数据未改动；请修复后再保存。');
                }
            }
        } catch (e) {
            showWarning('内容板块数据不是合法 JSON，当前数据未改动；请修复后再保存。');
        }

        function serialize() {
            if (!rootInvalid) {
                storage.value = JSON.stringify(model);
            }
        }

        function notifyDirty() {
            storage.dispatchEvent(new Event('input', {bubbles: true}));
        }

        function render() {
            wrap.innerHTML = '';
            model.forEach(function (item, index) {
                var block = document.createElement('div');
                block.className = 'about-section-block';
                block.innerHTML =
                    '<div class="about-section-head"><strong>内容板块 ' + (index + 1) + '</strong>' +
                    '<button type="button" class="about-section-del">删除</button></div>' +
                    '<div class="about-section-field"><label>标题</label>' +
                    '<input type="text" class="about-section-title" placeholder="例如：近况"></div>' +
                    '<div class="about-section-field"><label>正文</label>' +
                    '<textarea class="about-section-body" rows="3" placeholder="每行一段，纯文本"></textarea></div>';
                var titleInput = block.querySelector('.about-section-title');
                var bodyInput = block.querySelector('.about-section-body');
                titleInput.value = typeof item.title === 'string' ? item.title : '';
                bodyInput.value = typeof item.body === 'string' ? item.body : '';
                var sync = function () {
                    item.title = titleInput.value.trim();
                    item.body = bodyInput.value;
                    serialize();
                };
                titleInput.addEventListener('input', function () { sync(); notifyDirty(); });
                bodyInput.addEventListener('input', function () { sync(); notifyDirty(); });
                block.querySelector('.about-section-del').addEventListener('click', function () {
                    model.splice(index, 1);
                    render();
                    serialize();
                    notifyDirty();
                });
                wrap.appendChild(block);
            });
        }

        addBtn.addEventListener('click', function () {
            model.push({title: '', body: ''});
            render();
            serialize();
            notifyDirty();
            var blocks = wrap.querySelectorAll('.about-section-block');
            var last = blocks[blocks.length - 1];
            if (last) {
                last.scrollIntoView({behavior: 'smooth', block: 'center'});
                last.querySelector('.about-section-title').focus();
            }
        });

        var form = storage.closest('form');
        if (form) {
            form.addEventListener('submit', serialize);
        }
        render();
    }
    if (document.readyState === 'loading') {
        document.addEventListener('DOMContentLoaded', boot);
    } else {
        boot();
    }
})();
</script>
HTML;
}

function themeConfig($form)
{
    yingCleanupLegacyViewMarks();
    $form->setEncodeType(\Typecho\Widget\Helper\Form::MULTIPART_ENCODE);
    $form->setAttribute('class', 'ying-settings');

    $themeOptions = \Widget\Options::alloc();
    if (is_array($themeOptions->aboutExtraSections)) {
        $themeOptions->aboutExtraSections = json_encode($themeOptions->aboutExtraSections, JSON_UNESCAPED_UNICODE);
    }
    $fontLibrary = yingFontLibraryFromOptions($themeOptions);
    $fontSourceOptions = yingFontSourceOptions();
    $currentSource = yingFontSource($themeOptions);
    $selectedFontLocal = yingFontSelectedFromList($fontLibrary, trim((string)$themeOptions->fontLocal));
    $currentFontUrl = trim((string)$themeOptions->fontUrl);
    $request = \Typecho\Request::getInstance();
    $pendingDelete = [];

    if ($request->isPost()) {
        if (isset($_POST['fontSource']) && is_string($_POST['fontSource'])
            && array_key_exists($_POST['fontSource'], $fontSourceOptions)) {
            $currentSource = $_POST['fontSource'];
        }
        if (isset($_POST['fontLocal']) && is_string($_POST['fontLocal'])) {
            $selectedFontLocal = yingFontSelectedFromList($fontLibrary, $_POST['fontLocal']);
        }
        if (isset($_POST['fontUrl']) && is_string($_POST['fontUrl'])) {
            $currentFontUrl = trim($_POST['fontUrl']);
        }
        if (isset($_POST['fontDelete']) && is_array($_POST['fontDelete'])) {
            foreach ($_POST['fontDelete'] as $deleteFile) {
                if (is_string($deleteFile)) {
                    $pendingDelete[] = $deleteFile;
                }
            }
        }
    }

    $fontFlash = '';
    if (!$request->isPost()) {
        $flash = (string)\Typecho\Cookie::get('ying_admin_flash', '');
        if ($flash !== '') {
            \Typecho\Cookie::delete('ying_admin_flash');
            $fontFlash = '<span class="ying-flash-error">' . htmlspecialchars($flash, ENT_QUOTES, 'UTF-8') . '</span>';
        }
    }

    $fontLimitText = number_format(yingFontUploadLimit() / 1048576, 1, '.', '') . ' MB';
    $previewCss = '';
    foreach ($fontLibrary as $index => $font) {
        $previewCss .= "@font-face{font-family:'YingPreview-{$index}';src:url('"
            . yingCssUrl(yingFontUploadUrl($font['file']))
            . "');font-weight:normal;font-style:normal;font-display:swap;}";
    }

    $adminConfig = [
        'source' => $currentSource,
        'customUrl' => $currentFontUrl,
        'uploadLimit' => yingFontUploadLimit(),
        'library' => array_map(function ($font) {
            return [
                'file' => $font['file'],
                'name' => $font['name'],
                'format' => strtoupper($font['format']),
                'size' => yingFormatBytes((int)$font['size']),
                'date' => date('Y-m-d', (int)$font['time']),
            ];
        }, $fontLibrary),
    ];
    $adminConfigJson = json_encode(
        $adminConfig,
        JSON_UNESCAPED_UNICODE | JSON_HEX_TAG | JSON_HEX_AMP | JSON_HEX_APOS | JSON_HEX_QUOT
    ) ?: '{}';

    $assetUrl = function (string $relative) use ($themeOptions): string {
        $path = __DIR__ . '/' . $relative;
        $version = is_file($path) ? (int)filemtime($path) : 0;
        return rtrim((string)$themeOptions->themeUrl, '/') . '/' . $relative . ($version ? '?v=' . $version : '');
    };
    $adminCssUrl = $assetUrl('css/admin.css');
    $adminJsUrl = $assetUrl('js/admin.js');
    $skinHtml = '<style>' . $previewCss . '</style>'
        . '<link rel="stylesheet" href="' . htmlspecialchars($adminCssUrl, ENT_QUOTES, 'UTF-8') . '">'
        . '<script>window.yingAdminConfig=' . $adminConfigJson . ';</script>'
        . '<script src="' . htmlspecialchars($adminJsUrl, ENT_QUOTES, 'UTF-8') . '" defer></script>';
    $skin = new \Typecho\Widget\Helper\Form\Element\Text('yingAdminSkin', null, '1', null, $skinHtml);
    $skin->setAttribute('style', 'display:none');
    $skin->input->setAttribute('style', 'display:none');
    $form->addInput($skin);

    $logoUrl = new \Typecho\Widget\Helper\Form\Element\Text(
        'logoUrl',
        null,
        null,
        _t('头像'),
        _t('在这里填入一个头像 URL 地址，留空使用主题内置头像。')
    );
    $form->addInput($logoUrl->addRule('url', _t('请填写一个合法的 URL 地址')));

    $faviconUrl = new \Typecho\Widget\Helper\Form\Element\Text(
        'faviconUrl',
        null,
        null,
        _t('标签页图标'),
        _t('填入浏览器标签页图标（favicon）的 URL 地址，留空使用上方头像。')
    );
    $form->addInput($faviconUrl->addRule('url', _t('请填写一个合法的 URL 地址')));

    $yiyanSource = new \Typecho\Widget\Helper\Form\Element\Radio(
        'yiyanSource',
        ['fixed' => _t('固定内容'), 'api' => _t('从一言 API 自动获取')],
        'fixed',
        _t('一言来源')
    );
    $form->addInput($yiyanSource->multiMode());

    $yiyanPosition = new \Typecho\Widget\Helper\Form\Element\Radio(
        'yiyanPosition',
        ['avatar' => _t('头像下方'), 'contacts' => _t('联系方式下方')],
        'avatar',
        _t('一言位置'),
        _t('一言显示在头像下方（默认），或移到联系方式图标下方；没有联系方式时仍显示在头像下方。')
    );
    $form->addInput($yiyanPosition->multiMode());

    $yiyan = new \Typecho\Widget\Helper\Form\Element\Textarea(
        'yiyan',
        null,
        '道德当身，故不以物惑',
        _t('一言（固定内容）'),
        _t('仅在上方选择"固定内容"时使用，将会显示在头像旁边；纯文本。')
    );
    $form->addInput($yiyan);

    $yiyanCats = new \Typecho\Widget\Helper\Form\Element\Checkbox(
        'yiyanCats',
        [
            'd' => _t('文学'),
            'i' => _t('诗词'),
            'k' => _t('哲学'),
            'a' => _t('动画'),
            'b' => _t('漫画'),
            'c' => _t('游戏'),
            'h' => _t('影视'),
            'j' => _t('网易云'),
        ],
        ['d', 'i'],
        _t('一言分类（API 模式）'),
        _t('勾选想获取的句子类型，可多选；全部取消则不限制分类。')
    );
    $form->addInput($yiyanCats->multiMode());

    $yiyanShowFrom = new \Typecho\Widget\Helper\Form\Element\Radio(
        'yiyanShowFrom',
        ['on' => _t('显示出处'), 'off' => _t('只显示句子')],
        'off',
        _t('一言出处（API 模式）'),
        _t('是否在句子后面附上来源，如"—— 《书名》"。')
    );
    $form->addInput($yiyanShowFrom->multiMode());

    $bgUrl = new \Typecho\Widget\Helper\Form\Element\Text(
        'bgUrl',
        null,
        null,
        _t('背景图'),
        _t('在这里填入背景图 URL（全站生效），留空使用主题内置背景。')
    );
    $form->addInput($bgUrl->addRule('url', _t('请填写一个合法的 URL 地址')));

    $statusText = yingFontStatusText($currentSource, $fontLibrary, $selectedFontLocal, $currentFontUrl);
    $sourceOptionLabels = [];
    foreach ($fontSourceOptions as $sourceKey => $sourceInfo) {
        $sourceOptionLabels[$sourceKey] = '<span class="ying-opt-label">'
            . htmlspecialchars($sourceInfo[0], ENT_QUOTES, 'UTF-8') . '</span><small>'
            . htmlspecialchars($sourceInfo[1], ENT_QUOTES, 'UTF-8') . '</small>';
    }
    $fontSource = new \Typecho\Widget\Helper\Form\Element\Radio(
        'fontSource',
        $sourceOptionLabels,
        'cdn',
        _t('字体来源')
    );
    $fontSource->value($currentSource);
    $fontSource->setAttribute('data-ying-field', 'source');
    $fontSource->description(
        '<span class="ying-font-status" data-ying-status aria-live="polite">'
        . htmlspecialchars($statusText, ENT_QUOTES, 'UTF-8') . '</span>'
    );
    $fontSource->addRule(function ($value): bool {
        if (!array_key_exists($value, yingFontSourceOptions())) {
            return false;
        }
        if ($value === 'custom') {
            return trim((string)($_POST['fontUrl'] ?? '')) !== '';
        }
        return true;
    }, _t('选择“自定义链接”时，请填写字体文件地址。'));
    $form->addInput($fontSource->multiMode());

    $libraryLabel = _t('字体库') . ' <span class="ying-count">' . count($fontLibrary) . '</span>';
    $fontLocal = new \Typecho\Widget\Helper\Form\Element\Text(
        'fontLocal',
        null,
        $selectedFontLocal,
        $libraryLabel,
        yingFontLibraryHtml($fontLibrary, $selectedFontLocal, $pendingDelete)
    );
    $fontLocal->input->setAttribute('style', 'display:none');
    $fontLocal->setAttribute('data-ying-field', 'library');
    $form->addInput($fontLocal);

    $fontFile = new \Typecho\Widget\Helper\Form\Element\Text(
        'fontFile',
        null,
        '1',
        _t('上传字体'),
        yingFontUploadHtml($fontLimitText) . $fontFlash
    );
    $fontFile->input->setAttribute('style', 'display:none');
    $fontFile->setAttribute('data-ying-field', 'upload');
    $fontFile->addRule(function ($value): bool {
        if (!isset($_FILES['fontFile']) || !is_array($_FILES['fontFile'])) {
            return true;
        }
        $file = $_FILES['fontFile'];
        $error = $file['error'] ?? UPLOAD_ERR_NO_FILE;
        if ($error === UPLOAD_ERR_NO_FILE) {
            return true;
        }
        if ($error !== UPLOAD_ERR_OK) {
            return false;
        }
        $tmp = $file['tmp_name'] ?? '';
        if (!is_string($tmp) || !is_uploaded_file($tmp)) {
            return false;
        }
        $size = (int)($file['size'] ?? 0);
        if ($size <= 0 || $size > yingFontUploadLimit()) {
            return false;
        }
        return yingDetectFontFormat($tmp) !== null;
    }, _t('字体文件无效：仅支持 woff2 / woff / ttf / otf，且不能超过上传上限。'));
    $form->addInput($fontFile);

    $fontUrl = new \Typecho\Widget\Helper\Form\Element\Text(
        'fontUrl',
        null,
        null,
        _t('自定义字体地址'),
        _t('只在“自定义链接”时生效；以 http://、https:// 或 / 开头。外部地址需支持 CORS。')
    );
    $fontUrl->addRule(function ($value): bool {
        $value = trim((string)$value);
        if ($value === '') {
            return true;
        }
        if (($_POST['fontSource'] ?? '') !== 'custom') {
            return true;
        }
        return yingUrlIsSafe($value);
    }, _t('字体地址需以 http://、https:// 或 / 开头'));
    $fontUrl->setAttribute('data-ying-field', 'url');
    $form->addInput($fontUrl);

    // 联系方式：存于 contactsJson（JSON）；未配置时从旧字段迁移，默认一条本项目 GitHub
    $optionsWidgetC = \Widget\Options::alloc();
    $contactsInit = trim((string)$optionsWidgetC->contactsJson);
    if (!isset($optionsWidgetC->contactsJson) || $contactsInit === '') {
        $legacyMap = [
            'githubUrl' => 'github', 'mailAddr' => 'mail', 'weiboUrl' => 'weibo',
            'twitterUrl' => 'twitter', 'bilibiliUrl' => 'bilibili', 'zhihuUrl' => 'zhihu',
            'telegramUrl' => 'telegram',
        ];
        $legacyContacts = [];
        foreach ($legacyMap as $cfield => $ctype) {
            $cval = trim((string)$optionsWidgetC->{$cfield});
            if ($cval !== '') {
                $legacyContacts[] = ['type' => $ctype, 'url' => $cval];
            }
        }
        if (!empty($legacyContacts)) {
            $contactsInit = json_encode($legacyContacts, JSON_UNESCAPED_UNICODE);
        } else {
            $contactsInit = json_encode([
                ['type' => 'github', 'url' => 'https://github.com/yiktt/YingK'],
            ], JSON_UNESCAPED_UNICODE);
        }
    }

    $contactsRule = function ($value): bool {
        $data = yingJsonList((string)$value);
        if ($data === null) {
            return false;
        }
        $types = yingContactTypes();
        foreach ($data as $item) {
            if (!is_array($item)) {
                return false;
            }
            $type = $item['type'] ?? 'link';
            if (!is_string($type) || !array_key_exists($type, $types)) {
                return false;
            }
            if (isset($item['url']) && !is_string($item['url'])) {
                return false;
            }
            if (isset($item['icon']) && !is_string($item['icon'])) {
                return false;
            }
            $icon = isset($item['icon']) && is_string($item['icon']) ? trim($item['icon']) : '';
            if ($icon !== '' && !yingUrlIsSafe($icon)) {
                return false;
            }
        }
        return true;
    };

    $friendsRule = function ($value): bool {
        $data = yingJsonList((string)$value);
        if ($data === null) {
            return false;
        }
        foreach ($data as $item) {
            if (!is_array($item)) {
                return false;
            }
            foreach (['name', 'url', 'desc', 'avatar'] as $key) {
                if (isset($item[$key]) && !is_string($item[$key])) {
                    return false;
                }
            }
        }
        return true;
    };

    $contactsEl = new \Typecho\Widget\Helper\Form\Element\Text('contactsJson', null, $contactsInit, _t('联系方式管理'), _t('点击下方按钮添加联系方式，选择类型、填写链接后保存；自定义链接可填写图标 URL。桌面端显示在头像行右侧，窄屏自动换行。'));
    $contactsEl->description(yingContactsEditorHtml());
    $contactsEl->addRule($contactsRule, _t('联系方式数据格式错误：根节点须为列表，type/url/icon 须为字符串，且 type 须在白名单内。'));
    $form->addInput($contactsEl);

    $copyright = new \Typecho\Widget\Helper\Form\Element\Text(
        'copyright',
        null,
        '© 2016 - {Y}',
        _t('页脚版权文字'),
        _t('显示在页脚左侧，{Y} 会自动替换为当前年份；留空则不显示版权行。')
    );
    $form->addInput($copyright);

    $footerbeian = new \Typecho\Widget\Helper\Form\Element\Text(
        'footerbeian',
        null,
        null,
        _t('备案号（ICP）'),
        _t('如"X ICP备0000000000号"，留空不显示。')
    );
    $form->addInput($footerbeian);

    $policebeian = new \Typecho\Widget\Helper\Form\Element\Text(
        'policebeian',
        null,
        null,
        _t('公安备案号'),
        _t('如"X公网安备 00000000000000号"，填完整文案，显示在 ICP 备案号下方，自动链接到公安备案查询页。')
    );
    $form->addInput($policebeian);

    $cardAlpha = new \Typecho\Widget\Helper\Form\Element\Text(
        'cardAlpha',
        null,
        '75',
        _t('卡片透明度'),
        _t('填 0 到 100 之间的百分比（越小越透出背景图；0 按最低 1% 处理），留空默认 75。旧版小于 1 的小数会按比例兼容。')
    );
    $form->addInput($cardAlpha);

    $newPostMark = new \Typecho\Widget\Helper\Form\Element\Radio(
        'newPostMark',
        [
            'none' => _t('不标记'),
            'stamp' => _t('朱砂小印「新」'),
            'relative' => _t('相对时间（今天/昨天/N 天前）'),
            'both' => _t('小印 + 相对时间'),
        ],
        'none',
        _t('新文章标记'),
        _t('首页与列表页中，对 3 个自然日内发布的文章显示标记；默认不标记。')
    );
    $form->addInput($newPostMark->multiMode());

    $stickyPosts = new \Typecho\Widget\Helper\Form\Element\Text(
        'stickyPosts',
        null,
        null,
        _t('置顶文章'),
        _t('填写文章 ID 或别名（slug），用逗号分隔；按填写顺序显示，最多 3 篇。例：12, 34, my-post。置顶展示仅在首页第一页，文章会从首页普通列表移除；加密文章自动跳过。文章 ID 可在编辑页地址的 cid= 后查看。')
    );
    $stickyPosts->addRule(function ($value): bool {
        return count(yingStickyTokens((string)$value)) <= 3;
    }, _t('置顶文章最多 3 篇，请用逗号分隔。'));
    $stickyPosts->input->setAttribute('placeholder', '12, 34, my-post');
    $form->addInput($stickyPosts);

    $aboutIntro = new \Typecho\Widget\Helper\Form\Element\Textarea(
        'aboutIntro',
        null,
        null,
        _t('关于页导语'),
        _t('支持 Markdown；留空不显示。填写任一关于页内容字段后，前台改用这里的结构化内容。')
    );
    $form->addInput($aboutIntro);

    $aboutQuote = new \Typecho\Widget\Helper\Form\Element\Text(
        'aboutQuote',
        null,
        null,
        _t('关于页引言'),
        _t('显示为导语下方的引用，纯文本；留空不显示。')
    );
    $form->addInput($aboutQuote);

    $aboutTopics = new \Typecho\Widget\Helper\Form\Element\Textarea(
        'aboutTopics',
        null,
        null,
        _t('内容方向'),
        _t('每行一条，格式：标题 | 说明；留空隐藏整个栏目。')
    );
    $form->addInput($aboutTopics);

    $aboutTopicsTitle = new \Typecho\Widget\Helper\Form\Element\Text(
        'aboutTopicsTitle',
        null,
        _t('这里写什么'),
        _t('内容方向标题'),
        _t('留空使用默认「这里写什么」。')
    );
    $form->addInput($aboutTopicsTitle);

    $aboutTheme = new \Typecho\Widget\Helper\Form\Element\Textarea(
        'aboutTheme',
        null,
        null,
        _t('关于主题'),
        _t('支持 Markdown；留空隐藏整个栏目。')
    );
    $form->addInput($aboutTheme);

    $aboutThemeTitle = new \Typecho\Widget\Helper\Form\Element\Text(
        'aboutThemeTitle',
        null,
        _t('关于这个主题'),
        _t('主题介绍标题'),
        _t('留空使用默认「关于这个主题」。')
    );
    $form->addInput($aboutThemeTitle);

    $aboutStack = new \Typecho\Widget\Helper\Form\Element\Textarea(
        'aboutStack',
        null,
        null,
        _t('技术栈'),
        _t('每行一条，格式：组件 | 内容；自动生成两列表格，留空隐藏整个栏目。')
    );
    $form->addInput($aboutStack);

    $aboutStackTitle = new \Typecho\Widget\Helper\Form\Element\Text(
        'aboutStackTitle',
        null,
        _t('技术栈'),
        _t('技术栈标题'),
        _t('留空使用默认「技术栈」。')
    );
    $form->addInput($aboutStackTitle);

    $aboutSectionsStored = $themeOptions->aboutExtraSections;
    $aboutSectionsInit = is_array($aboutSectionsStored)
        ? json_encode($aboutSectionsStored, JSON_UNESCAPED_UNICODE)
        : trim((string)$aboutSectionsStored);
    if ($aboutSectionsInit === '') {
        $aboutSectionsInit = '[]';
    }
    $aboutSectionsRule = function ($value): bool {
        $data = yingJsonList((string)$value);
        if ($data === null) {
            return false;
        }
        foreach ($data as $item) {
            if (!is_array($item)) {
                return false;
            }
            foreach (['title', 'body'] as $key) {
                if (isset($item[$key]) && !is_string($item[$key])) {
                    return false;
                }
            }
        }
        return true;
    };
    $aboutExtraSections = new \Typecho\Widget\Helper\Form\Element\Text(
        'aboutExtraSections',
        null,
        $aboutSectionsInit,
        _t('自定义内容板块'),
        _t('可添加多个「标题 + 正文」板块，显示在技术栈之后、结尾寄语之前；正文支持 Markdown，留空不显示。')
    );
    $aboutExtraSections->description(yingAboutSectionsEditorHtml());
    $aboutExtraSections->addRule($aboutSectionsRule, _t('自定义内容板块格式错误：根节点须为列表，标题和正文须为字符串。'));
    $form->addInput($aboutExtraSections);

    $aboutOutro = new \Typecho\Widget\Helper\Form\Element\Text(
        'aboutOutro',
        null,
        null,
        _t('结尾寄语'),
        _t('支持 Markdown；显示在技术栈之后、统计与「完」之前，留空不显示。')
    );
    $form->addInput($aboutOutro);

    $siteStartDate = new \Typecho\Widget\Helper\Form\Element\Text(
        'siteStartDate',
        null,
        null,
        _t('建站日期'),
        _t('关于页「运行」统计的起始日期，格式 YYYY-MM-DD（例如 2024-05-01）；留空则使用最早一篇文章的日期。')
    );
    $form->addInput($siteStartDate);

    $aboutStats = new \Typecho\Widget\Helper\Form\Element\Checkbox(
        'aboutStats',
        [
            'posts' => _t('文章篇数'),
            'words' => _t('总字数'),
            'days' => _t('运行时长'),
        ],
        ['posts', 'words', 'days'],
        _t('关于页统计'),
        _t('勾选要在关于页展示的统计项，可任选 1–3 项；全部不勾选则不显示。运行时长依赖上方「建站日期」。')
    );
    $form->addInput($aboutStats);

    $aboutStatsPos = new \Typecho\Widget\Helper\Form\Element\Radio(
        'aboutStatsPos',
        ['top' => _t('标题下方'), 'end' => _t('文末落款')],
        'top',
        _t('关于页统计位置'),
        _t('「文末落款」会放在正文结尾、居中「完」之前；「标题下方」紧贴关于页标题。')
    );
    $form->addInput($aboutStatsPos->multiMode());

    $aboutStatsAlign = new \Typecho\Widget\Helper\Form\Element\Radio(
        'aboutStatsAlign',
        ['left' => _t('向左对齐'), 'center' => _t('居中'), 'right' => _t('向右对齐')],
        'left',
        _t('关于页统计对齐'),
        _t('控制整块统计在关于页中的水平位置；点线与行内数字布局保持不变。')
    );
    $form->addInput($aboutStatsAlign->multiMode());

    $aboutShowTimeline = new \Typecho\Widget\Helper\Form\Element\Radio(
        'aboutShowTimeline',
        ['on' => _t('显示'), 'off' => _t('隐藏')],
        'on',
        _t('站点足迹'),
        _t('根据建站日期、最早文章与最近更新自动生成时间线；锁定文章只显示占位标题。数据不足时自动隐藏。')
    );
    $form->addInput($aboutShowTimeline->multiMode());

    $aboutShowHeatmap = new \Typecho\Widget\Helper\Form\Element\Radio(
        'aboutShowHeatmap',
        ['on' => _t('显示'), 'off' => _t('隐藏')],
        'on',
        _t('文章活跃热力图'),
        _t('使用已发布文章的创建日期生成近一年热力图，不读取 IP、评论或正文；无文章时自动隐藏。')
    );
    $form->addInput($aboutShowHeatmap->multiMode());

    $linksShow = new \Typecho\Widget\Helper\Form\Element\Radio(
        'linksShow',
        ['on' => _t('显示友链页'), 'off' => _t('隐藏友链页')],
        'on',
        _t('友链页'),
        _t('隐藏后导航中不再显示友链入口，直接访问时显示"暂未开放"提示。需先在「管理 → 独立页面」新建页面并选择"友链"自定义模板。')
    );
    $form->addInput($linksShow->multiMode());

    // 友链数据：存于 friendsJson（JSON）；未配置时从旧字段迁移，默认一条 Typecho 官方
    $optionsWidget = \Widget\Options::alloc();
    $friendsInit = trim((string)$optionsWidget->friendsJson);
    if (!isset($optionsWidget->friendsJson) || $friendsInit === '') {
        $legacyFriends = [];
        for ($i = 1; $i <= 10; $i++) {
            $lname = trim((string)$optionsWidget->{'friendName' . $i});
            $lurl = trim((string)$optionsWidget->{'friendUrl' . $i});
            if ($lname === '' || $lurl === '') {
                continue;
            }
            $legacyFriends[] = [
                'name' => $lname,
                'url' => $lurl,
                'desc' => trim((string)$optionsWidget->{'friendDesc' . $i}),
                'avatar' => trim((string)$optionsWidget->{'friendAvatar' . $i}),
            ];
        }
        if (!empty($legacyFriends)) {
            $friendsInit = json_encode($legacyFriends, JSON_UNESCAPED_UNICODE);
        } else {
            $friendsInit = json_encode([
                [
                    'name' => 'Typecho 官方',
                    'url' => 'https://typecho.org',
                    'desc' => '简洁优雅的博客程序',
                    'avatar' => '',
                ],
            ], JSON_UNESCAPED_UNICODE);
        }
    }

    $friendsEl = new \Typecho\Widget\Helper\Form\Element\Text('friendsJson', null, $friendsInit, _t('友链管理'), _t('点击下方按钮添加友链，填写后保存即可；数据自动同步。'));
    $friendsEl->description(yingFriendsEditorHtml());
    $friendsEl->addRule($friendsRule, _t('友链数据格式错误：根节点须为列表，name/url/desc/avatar 须为字符串。'));
    $form->addInput($friendsEl);
}

/**
 * 保存外观设置：处理本地字体上传/删除，并接管 Typecho 默认保存流程
 */
function themeConfigHandle(array $settings, bool $isInit): bool
{
    $options = \Widget\Options::alloc();
    $theme = $isInit
        ? trim((string)\Typecho\Request::getInstance()->get('change'), './')
        : trim((string)$options->theme, './');
    if ($theme === '') {
        return false;
    }

    unset($settings['fontFile'], $settings['yingAdminSkin']);
    $settings['fontUrl'] = trim((string)($settings['fontUrl'] ?? ''));
    $settings['stickyPosts'] = implode(', ', array_slice(
        yingStickyTokens((string)($settings['stickyPosts'] ?? '')),
        0,
        3
    ));

    $startDate = trim((string)($settings['siteStartDate'] ?? ''));
    if ($startDate !== '') {
        $parts = explode('-', $startDate);
        $valid = count($parts) === 3
            && preg_match('/^\d{4}-\d{2}-\d{2}$/', $startDate)
            && checkdate((int)$parts[1], (int)$parts[2], (int)$parts[0]);
        if (!$valid) {
            $startDate = '';
        }
    }
    $settings['siteStartDate'] = $startDate;

    if ($isInit) {
        $settings['fontSource'] = 'cdn';
        $settings['fontLocal'] = '';
        $settings['fontLibrary'] = '[]';
        $settings['fontUrl'] = '';
    } else {
        $library = yingFontLibraryFromOptions($options);
        $selected = trim((string)($settings['fontLocal'] ?? ''));
        $selected = yingFontSelectedFromList($library, $selected);

        $source = (string)($settings['fontSource'] ?? '');
        if (!array_key_exists($source, yingFontSourceOptions())) {
            $source = yingFontSource($options);
        }

        $deleteFiles = [];
        if (isset($_POST['fontDelete']) && is_array($_POST['fontDelete'])) {
            foreach ($_POST['fontDelete'] as $deleteFile) {
                if (is_string($deleteFile)) {
                    $deleteFiles[] = $deleteFile;
                }
            }
        }
        if ($deleteFiles) {
            $kept = [];
            $selectedDeleted = false;
            foreach ($library as $font) {
                if (in_array($font['file'], $deleteFiles, true)) {
                    yingDeleteLocalFont($font['file']);
                    if ($font['file'] === $selected) {
                        $selectedDeleted = true;
                    }
                } else {
                    $kept[] = $font;
                }
            }
            $library = $kept;
            if ($selectedDeleted) {
                $selected = yingFontSelectedFromList($library, '');
            }
        }

        $upload = $_FILES['fontFile'] ?? null;
        $hasUpload = is_array($upload)
            && (($upload['error'] ?? UPLOAD_ERR_NO_FILE) !== UPLOAD_ERR_NO_FILE);
        if ($hasUpload) {
            $tmp = $upload['tmp_name'] ?? '';
            $format = is_string($tmp) && is_uploaded_file($tmp) ? yingDetectFontFormat($tmp) : null;
            if ($format === null) {
                yingAdminFlash('字体文件无效，未替换现有字体。');
            } else {
                $dir = yingFontUploadDir();
                if (!is_dir($dir)) {
                    @mkdir($dir, 0755, true);
                }
                $filename = 'ying-font-' . bin2hex(random_bytes(8)) . '.' . $format;
                if (is_dir($dir) && @move_uploaded_file($tmp, $dir . '/' . $filename)) {
                    $originalName = $upload['name'] ?? '';
                    $newFont = yingFontMetaFromFile($filename, is_string($originalName) ? $originalName : '');
                    $replaced = false;
                    foreach ($library as $index => $font) {
                        if (mb_strtolower($font['name'], 'UTF-8') === mb_strtolower($newFont['name'], 'UTF-8')) {
                            yingDeleteLocalFont($font['file']);
                            $library[$index] = $newFont;
                            $replaced = true;
                            break;
                        }
                    }
                    if (!$replaced) {
                        $library[] = $newFont;
                    }
                    $selected = $filename;
                    $source = 'local';
                } else {
                    yingAdminFlash('字体文件保存失败，请检查 usr/uploads/fonts 目录权限。');
                }
            }
        }

        $selected = yingFontSelectedFromList($library, $selected);
        if ($source === 'custom' && !yingUrlIsSafe($settings['fontUrl'])) {
            $source = 'none';
        }
        $settings['fontSource'] = $source;
        $settings['fontLocal'] = $selected;
        $libraryJson = json_encode($library, JSON_UNESCAPED_UNICODE);
        $settings['fontLibrary'] = $libraryJson === false ? '[]' : $libraryJson;
    }

    $name = 'theme:' . $theme;
    $value = json_encode($settings);
    $db = \Typecho\Db::get();
    $exists = $db->fetchRow($db->select('name')->from('table.options')->where('name = ?', $name));
    if ($exists) {
        $db->query($db->update('table.options')->rows(['value' => $value])->where('name = ?', $name));
    } else {
        $db->query($db->insert('table.options')->rows([
            'name'  => $name,
            'user'  => 0,
            'value' => $value,
        ]));
    }

    return true;
}
