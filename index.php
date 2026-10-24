<?php
/**
 * 简约而不简单
 *
 * @package YingK
 * @author yiktt
 * @version 3.1
 * @link https://github.com/yiktt/YingK
 */

if (!defined('__TYPECHO_ROOT_DIR__')) exit;
$this->need('header.php');
?>

<?php $this->need('nav.php'); ?>
</div>
<article>
    <div class="recent-posts section">
        <h2 class="section-header">随笔<i class="ri-quill-pen-line"></i></h2>
        <?php while ($this->next()): ?>
            <div class="posts">
                <div class="post">
                    <div class="time"><?php $this->date('Y年m月d日'); ?></div>
                    <a href="<?php $this->permalink(); ?>"><?php $this->title(); ?></a>
                </div>
            </div>
        <?php endwhile; ?>
    </div>
</article>
<ul class="pagination">
    <li class="page-item page-previous"><?php $this->pageLink('上一页', 'prev'); ?></li>
    <li class="page-item page-next"><?php $this->pageLink('下一页', 'next'); ?></li>
</ul>
<?php $this->need('footer.php'); ?>
