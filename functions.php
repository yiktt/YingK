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
 * 通过文件头识别字体格式，不信任原始扩展名
 */
function yingDetectFontFormat(string $path): ?string
{
    if (!is_file($path) || filesize($path) === 0) {
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
    return $formats[$signature] ?? null;
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

function yingCdnFontUrls(): array
{
    return [
        'https://cdn.jsdelivr.net/gh/yiktt/YingK@main/assets/fonts/zql-v2.woff2',
        'https://fastly.jsdelivr.net/gh/yiktt/YingK@main/assets/fonts/zql-v2.woff2',
    ];
}

function yingFontUrls($options = null): array
{
    $options = $options ?: \Widget\Options::alloc();
    $source = yingFontSource($options);

    if ($source === 'cdn') {
        return yingCdnFontUrls();
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

function yingNewPostDays(int $created): int
{
    $today = strtotime(date('Y-m-d'));
    $day = strtotime(date('Y-m-d', $created));
    return (int)round(($today - $day) / 86400);
}

/**
 * 关于页统计：已发布文章数、总字数、运行天数
 * 建站日期取后台设置，未设置时回退到最早一篇文章的日期
 */
function yingSiteStats(): array
{
    $options = \Widget\Options::alloc();
    $db = \Typecho\Db::get();
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

    return ['posts' => $posts, 'words' => $words, 'days' => $days, 'start' => $start];
}

function yingUptimeText(int $days): string
{
    if ($days < 365) {
        return sprintf(_t('%d 天'), $days);
    }
    $years = intdiv($days, 365);
    $rest = $days % 365;
    return $rest > 0
        ? sprintf(_t('%d 年 %d 天'), $years, $rest)
        : sprintf(_t('%d 年'), $years);
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
            'secure' => !empty($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off',
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
        try {
            model = JSON.parse(storage.value || '[]');
            if (!Array.isArray(model)) {
                model = [];
            }
        } catch (e) {
            model = [];
        }

        function serialize() {
            storage.value = JSON.stringify(model);
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
                });
                wrap.appendChild(block);
            });
        }

        addBtn.addEventListener('click', function () {
            model.push({ name: '', url: '', desc: '', avatar: '' });
            render();
            serialize();
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
        try {
            model = JSON.parse(storage.value || '[]');
            if (!Array.isArray(model)) {
                model = [];
            }
        } catch (e) {
            model = [];
        }
        var typeOptions = '{$options}';

        function serialize() {
            storage.value = JSON.stringify(model);
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
                });
                wrap.appendChild(block);
            });
        }

        addBtn.addEventListener('click', function () {
            model.push({ type: 'github', url: '', icon: '' });
            render();
            serialize();
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

function themeConfig($form)
{
    yingCleanupLegacyViewMarks();
    $form->setEncodeType(\Typecho\Widget\Helper\Form::MULTIPART_ENCODE);
    $form->setAttribute('class', 'ying-settings');

    $themeOptions = \Widget\Options::alloc();
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

    $jsonListRule = function ($value): bool {
        $value = trim((string)$value);
        if ($value === '') {
            return true;
        }
        $data = json_decode($value, true);
        if (!is_array($data)) {
            return false;
        }
        foreach ($data as $item) {
            if (!is_array($item)) {
                return false;
            }
        }
        return true;
    };

    $contactIconRule = function ($value): bool {
        $value = trim((string)$value);
        if ($value === '') {
            return true;
        }
        $data = json_decode($value, true);
        if (!is_array($data)) {
            return false;
        }
        foreach ($data as $item) {
            if (!is_array($item) || ($item['type'] ?? '') !== 'link') {
                continue;
            }
            $icon = isset($item['icon']) && is_string($item['icon']) ? trim($item['icon']) : '';
            if ($icon !== '' && !yingUrlIsSafe($icon)) {
                return false;
            }
        }
        return true;
    };

    $contactsEl = new \Typecho\Widget\Helper\Form\Element\Text('contactsJson', null, $contactsInit, _t('联系方式管理'), _t('点击下方按钮添加联系方式，选择类型、填写链接后保存；自定义链接可填写图标 URL。桌面端显示在头像行右侧，窄屏自动换行。'));
    $contactsEl->description(yingContactsEditorHtml());
    $contactsEl->addRule($jsonListRule, _t('联系方式数据格式错误，请重新添加后再保存。'));
    $contactsEl->addRule($contactIconRule, _t('自定义链接的图标需以 http://、https:// 或 / 开头'));
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
        _t('填 0 到 100 之间的数字（百分比，越小越透出背景图），留空默认 75。')
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

    $siteStartDate = new \Typecho\Widget\Helper\Form\Element\Text(
        'siteStartDate',
        null,
        null,
        _t('建站日期'),
        _t('关于页「运行」统计的起始日期，格式 YYYY-MM-DD（例如 2024-05-01）；留空则使用最早一篇文章的日期。')
    );
    $form->addInput($siteStartDate);

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
    $friendsEl->addRule($jsonListRule, _t('友链数据格式错误，请重新添加后再保存。'));
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

    unset($settings['fontFile'], $settings['fontRemove'], $settings['yingAdminSkin']);
    $settings['fontUrl'] = trim((string)($settings['fontUrl'] ?? ''));

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
