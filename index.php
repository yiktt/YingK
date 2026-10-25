<?php
/**
 * 简约而不简单
 *
 * @package YingK
 * @author yiktt
 * @link https://github.com/yiktt/YingK
 */

if (!defined('__TYPECHO_ROOT_DIR__')) exit;
$this->need('header.php');
?>

<article>
    <div class="recent-posts section">
        <h1 class="section-header"><?php if ($this->is('archive')): ?><?php $this->archiveTitle([
            'category' => _t('分类：%s'),
            'search'   => _t('搜索：%s'),
            'tag'      => _t('标签：%s'),
            'author'   => _t('作者：%s'),
            'date'     => _t('归档：%s')
        ], '', ''); ?><?php else: ?><?php _e('随笔'); ?><?php endif; ?><i class="ri-quill-pen-line" aria-hidden="true"></i></h1>
        <?php $hasPosts = false; ?>
        <?php while ($this->next()): $hasPosts = true; ?>
            <div class="posts">
                <div class="post">
                    <div class="time"><?php $this->date('Y年m月d日'); ?></div>
                    <h2 class="post-title-link"><a href="<?php $this->permalink(); ?>"><?php $this->title(); ?></a></h2>
                </div>
            </div>
        <?php endwhile; ?>
        <?php if (!$hasPosts): ?>
            <p class="list-empty">暂无文章。</p>
        <?php endif; ?>
    </div>
</article>
<ul class="pagination">
    <li class="page-item page-previous"><?php $this->pageLink('上一页', 'prev'); ?></li>
    <li class="page-item page-next"><?php $this->pageLink('下一页', 'next'); ?></li>
</ul>
<?php $this->need('footer.php'); ?>
