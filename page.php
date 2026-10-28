<?php if (!defined('__TYPECHO_ROOT_DIR__')) exit;
$this->need('header.php');
if ($this->hidden) {
    echo yingLockedPageHtml($this);
    $this->need('footer.php');
    return;
}
$isAboutPage = $this->is('page', 'start-page');
if ($isAboutPage) {
    $this->need('about-head.php');
    echo yingAboutMainHtml();
} else {
    ?>
<header class="page-header">
    <h1 class="page-title" itemprop="name headline"><?php echo htmlspecialchars((string)$this->title, ENT_QUOTES, 'UTF-8'); ?></h1>
    <span class="page-rule" aria-hidden="true"></span>
</header>
<div class="post-content" itemprop="articleBody">
    <?php echo yingContentHtml((string)$this->content); ?>
</div>
    <?php
}
$this->need('footer.php');
