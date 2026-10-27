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
        <h1 class="section-header"><?php if ($this->is('archive')): ?><?php
        $sectionArchive = $this->getArchiveTitle();
        if (!empty($sectionArchive)) {
            $sectionDefines = [
                'category' => _t('分类：%s'),
                'search'   => _t('搜索：%s'),
                'tag'      => _t('标签：%s'),
                'author'   => _t('作者：%s'),
                'date'     => _t('归档：%s')
            ];
            $sectionDefine = isset($sectionDefines[$this->getArchiveType()]) ? $sectionDefines[$this->getArchiveType()] : '%s';
            echo htmlspecialchars(sprintf($sectionDefine, $sectionArchive), ENT_QUOTES, 'UTF-8');
        }
        ?><?php else: ?><?php _e('随笔'); ?><?php endif; ?><i class="ri-quill-pen-line" aria-hidden="true"></i></h1>
        <?php
        $markMode = (string)$this->options->newPostMark;
        if (!in_array($markMode, ['none', 'stamp', 'relative', 'both'], true)) {
            $markMode = 'none';
        }
        $hasPosts = false;
        $tzOffset = (int)$this->options->timezone - (int)$this->options->serverTimezone;
        ?>
        <?php while ($this->next()): $hasPosts = true; ?>
            <?php
            $days = yingNewPostDays((int)$this->created);
            $isNew = $days >= 0 && $days < 3;
            $showStamp = $isNew && ($markMode === 'stamp' || $markMode === 'both');
            $showRelative = $isNew && ($markMode === 'relative' || $markMode === 'both');
            $absDate = htmlspecialchars(date('Y年m月d日', (int)$this->created + $tzOffset), ENT_QUOTES);
            if ($days === 0) {
                $relDate = _t('今天');
            } elseif ($days === 1) {
                $relDate = _t('昨天');
            } else {
                $relDate = sprintf(_t('%d 天前'), $days);
            }
            ?>
            <div class="posts">
                <div class="post">
                    <div class="time">
                        <?php if ($showRelative): ?><span class="time-new" title="<?php echo $absDate; ?>"><span class="time-dot" aria-hidden="true"></span><?php echo htmlspecialchars($relDate, ENT_QUOTES); ?></span><?php else: ?><?php $this->date('Y年m月d日'); ?><?php endif; ?>
                        <?php if ($showStamp): ?><span class="new-mark" title="<?php _e('3 天内发布'); ?>"><?php _e('新'); ?></span><?php endif; ?>
                    </div>
                    <h2 class="post-title-link"><a href="<?php $this->permalink(); ?>"><?php echo htmlspecialchars((string)$this->title, ENT_QUOTES, 'UTF-8'); ?></a></h2>
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
