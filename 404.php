<?php if (!defined('__TYPECHO_ROOT_DIR__')) exit;
$this->need('header.php');
?>

<div class="notfound">
    <p class="notfound-code" aria-hidden="true">404</p>
    <h1 class="notfound-title"><?php _e('页面没找到'); ?></h1>
    <p class="notfound-text"><?php _e('你想查看的页面已被转移或删除了。'); ?></p>
    <form class="notfound-search" method="post" action="<?php $this->options->siteUrl(); ?>" role="search">
        <input type="text" name="s" placeholder="<?php _e('输入关键字搜索…'); ?>" aria-label="<?php _e('搜索'); ?>"/>
    </form>
    <div class="notfound-actions">
        <a class="nf-btn nf-btn-solid" href="<?php $this->options->siteUrl(); ?>"><?php _e('返回首页'); ?></a>
        <button type="button" class="nf-btn nf-btn-ghost" id="nf-back"><?php _e('回到上一页'); ?></button>
    </div>
</div>

<?php $this->need('footer.php'); ?>
