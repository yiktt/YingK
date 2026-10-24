<?php if (!defined('__TYPECHO_ROOT_DIR__')) exit;
$this->need('header.php');
?>

<?php $this->need('nav.php'); ?>
</div>
<h1 class="post-title">404 - <?php _e('页面没找到'); ?></h1>
<div class="post-content">
    <p><?php _e('你想查看的页面已被转移或删除了, 要不要搜索看看: '); ?></p>
    <form method="post">
        <p><input type="text" name="s" class="text" autofocus/> <button type="submit" class="submit"><?php _e('搜索'); ?></button></p>
    </form>
    <p><i class="ri-compass-3-line not-found-icon" aria-hidden="true"></i></p>
</div>

<?php $this->need('footer.php'); ?>
