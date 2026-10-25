<?php
/**
 * 友链
 *
 * @package custom
 */

if (!defined('__TYPECHO_ROOT_DIR__')) exit;
$this->need('header.php');

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
        $name = isset($f['name']) ? trim((string)$f['name']) : '';
        $url = isset($f['url']) ? trim((string)$f['url']) : '';
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
        $desc = isset($f['desc']) ? trim((string)$f['desc']) : '';
        $avatar = isset($f['avatar']) ? trim((string)$f['avatar']) : '';
        // 头像 URL 协议白名单
        if ($avatar !== '' && !preg_match('/^https?:\/\//i', $avatar)) {
            $avatar = '';
        }
        $friends[] = [
            'name' => $name,
            'url' => $url,
            'desc' => $desc !== '' ? $desc : (parse_url($url, PHP_URL_HOST) ?: $url),
            'avatar' => $avatar,
        ];
    }
}
?>

<header class="page-header">
    <h1 class="page-title" itemprop="name headline"><?php $this->title(); ?></h1>
    <span class="page-rule" aria-hidden="true"></span>
</header>
<div class="post-content">
<?php if ($this->options->linksShow === 'off'): ?>
    <p class="links-empty">友链暂未开放。</p>
<?php elseif (empty($friends)): ?>
    <p class="links-empty">还没有友链，去后台「设置外观」添加吧。</p>
<?php else: ?>
    <ul class="links-grid">
        <?php foreach ($friends as $i => $friend): ?>
        <li class="link-card">
            <a href="<?php echo htmlspecialchars($friend['url'], ENT_QUOTES); ?>" target="_blank" rel="noopener">
                <?php if ($friend['avatar'] !== ''): ?>
                <img class="link-avatar-img" src="<?php echo htmlspecialchars($friend['avatar'], ENT_QUOTES); ?>" alt="<?php echo htmlspecialchars($friend['name'], ENT_QUOTES); ?>" width="38" height="38" loading="lazy" decoding="async"/>
                <?php else: ?>
                <span class="link-avatar link-color-<?php echo $i % 5; ?>"><?php echo htmlspecialchars(mb_substr($friend['name'], 0, 1, 'UTF-8')); ?></span>
                <?php endif; ?>
                <span class="link-info">
                    <span class="link-name"><?php echo htmlspecialchars($friend['name']); ?></span>
                    <span class="link-host"><?php echo htmlspecialchars($friend['desc']); ?></span>
                </span>
                <i class="ri-external-link-line link-external"></i>
            </a>
        </li>
        <?php endforeach; ?>
    </ul>
    <p class="links-note">欢迎交换友链 · 可通过右上角的联系方式找到我</p>
<?php endif; ?>
</div>
<?php $this->need('footer.php'); ?>
