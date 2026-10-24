<?php if (!defined('__TYPECHO_ROOT_DIR__')) exit;
$this->need('header.php');
?>

<?php $this->need('nav.php'); ?>
</div>
<h1 class="post-title" itemprop="name headline">
    <?php $this->title(); ?>
</h1>
<div class="meta">
    <span><?php $this->date('Y年m月d日'); ?></span>
    <span><?php _e('作者: '); ?><?php $this->author(); ?></span>
    <span><?php _e('分类: '); ?><?php $this->category(','); ?></span>
    <span><?php _e('浏览: '); ?><?php echo yingGetViews(intval($this->cid)); _e(' 次'); ?></span>
    <span><?php _e('约 '); ?><?php echo yingWordCount((string)$this->content); _e(' 字'); ?></span>
</div>
<div class="post-content" itemprop="articleBody">
    <?php $this->content(); ?>
    <div class="post-tags">
        <nav class="nav tags">
            <ul class="flat">
                <?php $this->tags(' ', true, ''); ?>
            </ul>
        </nav>
    </div>
</div>

<?php $this->need('footer.php'); ?>
