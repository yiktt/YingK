<?php
/**
 * 友链
 *
 * @package custom
 */

if (!defined('__TYPECHO_ROOT_DIR__')) exit;
$this->need('header.php');
if ($this->hidden) {
    echo yingLockedPageHtml($this);
    $this->need('footer.php');
    return;
}

$friends = [];
if ($this->options->linksShow !== 'off') {
    $list = null;
    $raw = trim((string)$this->options->friendsJson);
    if ($raw !== '') {
        $decoded = json_decode($raw, true);
        if (is_array($decoded)) {
            $list = $decoded;
        }
    }
    if ($list === null) {
        // 旧版固定字段兼容（未保存过新编辑器时）
        $list = [];
        for ($i = 1; $i <= 10; $i++) {
            $lname = trim((string)$this->options->{'friendName' . $i});
            $lurl = trim((string)$this->options->{'friendUrl' . $i});
            if ($lname === '' || $lurl === '') {
                continue;
            }
            $list[] = [
                'name' => $lname,
                'url' => $lurl,
                'desc' => trim((string)$this->options->{'friendDesc' . $i}),
                'avatar' => trim((string)$this->options->{'friendAvatar' . $i}),
            ];
        }
    }
    foreach ($list as $f) {
        if (!is_array($f)) {
            continue;
        }
        $name = isset($f['name']) && is_string($f['name']) ? trim($f['name']) : '';
        $url = isset($f['url']) && is_string($f['url']) ? trim($f['url']) : '';
        if ($name === '' || $url === '') {
            continue;
        }
        // 安全程：只接受 http(s) 链接，其它协议一律拒绝
        if (!preg_match('/^https?:\/\//i', $url)) {
            if (preg_match('/^[a-z][a-z0-9+.\-]*:/i', $url)) {
                continue; // 带 javascript: / data: 等危险协议
            }
            $url = 'https://' . ltrim($url, '/');
        }
        $desc = isset($f['desc']) && is_string($f['desc']) ? trim($f['desc']) : '';
        $avatar = isset($f['avatar']) && is_string($f['avatar']) ? trim($f['avatar']) : '';
        // 头像 URL 协议白名单
        if ($avatar !== '' && !preg_match('/^https?:\/\//i', $avatar)) {
            $avatar = '';
        }
        $host = parse_url($url, PHP_URL_HOST) ?: $url;
        $friends[] = [
            'name' => $name,
            'url' => $url,
            'desc' => $desc,
            'host' => $host,
            'avatar' => $avatar,
        ];
    }
}
$friendCount = count($friends);
?>

<header class="links-head">
    <h1 class="page-title" itemprop="name headline"><?php echo htmlspecialchars((string)$this->title, ENT_QUOTES, 'UTF-8'); ?></h1>
    <?php if ($friendCount > 0): ?><span class="links-total"><?php echo sprintf(_t('%d 位'), $friendCount); ?></span><?php endif; ?>
</header>
<div class="post-content links-body">
<?php if ($this->options->linksShow === 'off'): ?>
    <p class="links-empty">友链暂未开放。</p>
<?php elseif (empty($friends)): ?>
    <p class="links-empty">还没有友链，去后台「设置外观」添加吧。</p>
<?php else: ?>
    <div class="links-list">
        <?php foreach ($friends as $friend): ?>
        <a class="links-row" href="<?php echo htmlspecialchars($friend['url'], ENT_QUOTES); ?>" target="_blank" rel="noopener">
            <span class="links-name"><?php echo htmlspecialchars($friend['name']); ?></span>
            <span class="links-desc"><?php echo htmlspecialchars($friend['desc']); ?></span>
            <span class="links-host"><?php echo htmlspecialchars($friend['host']); ?></span>
        </a>
        <?php endforeach; ?>
    </div>
    <p class="links-note">欢迎交换友链 · 可通过右上角的联系方式找到我</p>
<?php endif; ?>
</div>
<?php $this->need('footer.php'); ?>
