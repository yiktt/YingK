<?php if (!defined('__TYPECHO_ROOT_DIR__')) exit;
$this->need('header.php');
?>

<div class="notfound">
    <p class="notfound-eyebrow" aria-hidden="true"><?php _e('编年 · 缺页'); ?></p>
    <div class="notfound-node" aria-hidden="true"></div>
    <div class="notfound-code-wrap">
        <p class="notfound-code" aria-hidden="true">404</p>
        <span class="notfound-seal" aria-hidden="true"><?php _e('空'); ?></span>
    </div>
    <h1 class="notfound-title"><?php _e('页面没找到'); ?></h1>
    <p class="notfound-text"><?php _e('这篇编年从没有写下来，或已被移走。'); ?></p>
    <div class="notfound-actions">
        <a class="nf-btn" id="nf-home" href="<?php $this->options->siteUrl(); ?>"><i class="ri-arrow-left-line" aria-hidden="true"></i><?php _e('返回首页'); ?></a>
        <span class="nf-sep" aria-hidden="true">·</span>
        <button type="button" class="nf-btn nf-btn-ghost" id="nf-back"><?php _e('回到上一页'); ?></button>
    </div>
</div>

<?php $this->need('footer.php'); ?>
