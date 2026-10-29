<?php if (!defined('__TYPECHO_ROOT_DIR__')) exit;

$isLockedPost = $this->hidden;
$lockError = false;
$isLockSubmit = $isLockedPost ? ($this->request->isPost() && $this->request->is('yingLockPass')) : false;
/** 口令表单的 CSRF 令牌：以固定 permalink 为盐，避免局部加载时地址栏无 token 导致校验漂移 */
$lockToken = $isLockedPost ? (string)$this->security->getToken($this->permalink) : '';

/** 锁定态口令校验：正确则写入核心同名的解锁 cookie 并回跳本页，错误则同页提示 */
if ($isLockedPost && $isLockSubmit) {
    if (!hash_equals($lockToken, (string)$this->request->get('yingLockToken'))) {
        // 不信任外部 Referer；回到固定文章地址，避免开放跳转。
        $this->response->redirect($this->permalink);
    }

    if (hash_equals((string)$this->password, (string)$this->request->get('yingLockPass'))) {
        // 值仍需与 Typecho 核心同名 cookie 一致；单独设置安全属性，避免 JS 读取口令。
        $cookieName = \Typecho\Cookie::getPrefix() . 'protectPassword_' . $this->cid;
        $cookieValue = (string)$this->password;
        $cookieSecure = \Typecho\Cookie::getSecure()
            || (!empty($_SERVER['HTTPS']) && strtolower((string)$_SERVER['HTTPS']) !== 'off')
            || (int)($_SERVER['SERVER_PORT'] ?? 0) === 443;
        $_COOKIE[$cookieName] = $cookieValue;
        setcookie($cookieName, $cookieValue, [
            'expires' => 0,
            'path' => \Typecho\Cookie::getPath(),
            'secure' => $cookieSecure,
            'httponly' => true,
            'samesite' => 'Lax',
        ]);
        $this->response->redirect($this->permalink);
    }

    $lockError = true;
}

yingPrivateCacheHeaders();

$this->need('header.php');
?>

<?php if (!$isLockedPost): ?>
<h1 class="post-title" itemprop="name headline">
    <?php echo htmlspecialchars((string)$this->title, ENT_QUOTES, 'UTF-8'); ?>
</h1>
<div class="meta">
    <span><time datetime="<?php $this->date('c'); ?>"><?php $this->date('Y年m月d日'); ?></time></span>
    <span><?php _e('作者: '); ?><?php echo htmlspecialchars((string)$this->author->screenName, ENT_QUOTES, 'UTF-8'); ?></span>
    <?php
    $postCategories = [];
    foreach ((array)$this->categories as $postCategory) {
        $postCategories[] = '<a href="' . htmlspecialchars((string)$postCategory['permalink'], ENT_QUOTES, 'UTF-8') . '">'
            . htmlspecialchars((string)$postCategory['name'], ENT_QUOTES, 'UTF-8') . '</a>';
    }
    ?>
    <?php if ($postCategories): ?>
    <span><?php _e('分类: '); ?><?php echo implode(',', $postCategories); ?></span>
    <?php endif; ?>
    <span><?php _e('浏览: '); ?><?php echo yingGetViews(intval($this->cid)); _e(' 次'); ?></span>
    <span><?php _e('约 '); ?><?php echo yingWordCount((string)$this->content); _e(' 字'); ?></span>
</div>
<?php endif; ?>

<?php if ($isLockedPost): ?>
<div class="post-content post-protected">
    <div class="post-lock<?php echo $lockError ? ' is-error' : ''; ?>">
        <p class="notfound-eyebrow" aria-hidden="true"><?php _e('编年 · 已加锁'); ?></p>
        <div class="notfound-node" aria-hidden="true"></div>
        <span class="post-lock-seal" aria-hidden="true"><?php _e('密'); ?></span>
        <h1 class="post-lock-title"><?php _e('请输入密码访问'); ?></h1>
        <p class="post-lock-sub"><?php _e('本文已加锁，请向站长索取口令'); ?></p>
        <form class="post-lock-form" action="<?php echo htmlspecialchars((string)$this->permalink, ENT_QUOTES, 'UTF-8'); ?>" method="post">
            <span class="post-lock-field">
                <input type="password" name="yingLockPass" aria-label="<?php _e('访问密码'); ?>" placeholder="<?php _e('输入访问密码'); ?>" autocomplete="current-password" required<?php echo $lockError ? ' autofocus' : ''; ?>/>
            </span>
            <input type="hidden" name="yingLockToken" value="<?php echo htmlspecialchars($lockToken, ENT_QUOTES, 'UTF-8'); ?>"/>
            <span class="post-lock-action">
                <button type="submit" class="post-lock-submit"><?php _e('进入 →'); ?></button>
            </span>
        </form>
        <?php if ($lockError): ?>
        <p class="post-lock-error" role="alert"><?php _e('口令不符，请重试'); ?></p>
        <?php endif; ?>
    </div>
</div>
<?php else: ?>
<div class="post-content" itemprop="articleBody">
    <?php echo yingContentHtml((string)$this->content); ?>
    <?php if ($this->tags): ?>
    <div class="post-tags">
        <nav class="nav tags" aria-label="文章标签">
            <ul class="flat">
                <li><?php
                    $postTags = [];
                    foreach ((array)$this->tags as $postTag) {
                        $postTags[] = '<a href="' . htmlspecialchars((string)$postTag['permalink'], ENT_QUOTES, 'UTF-8') . '">'
                            . htmlspecialchars((string)$postTag['name'], ENT_QUOTES, 'UTF-8') . '</a>';
                    }
                    echo implode('</li><li>', $postTags);
                ?></li>
            </ul>
        </nav>
    </div>
    <?php endif; ?>
</div>
<?php endif; ?>

<?php $this->need('footer.php'); ?>
