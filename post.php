<?php if (!defined('__TYPECHO_ROOT_DIR__')) exit;
$this->need('header.php');
?>

<h1 class="post-title" itemprop="name headline">
    <?php echo htmlspecialchars((string)$this->title, ENT_QUOTES, 'UTF-8'); ?>
</h1>
<div class="meta">
    <span><time datetime="<?php $this->date('c'); ?>"><?php $this->date('Y年m月d日'); ?></time></span>
    <span><?php _e('作者: '); ?><?php echo htmlspecialchars((string)$this->author->screenName, ENT_QUOTES, 'UTF-8'); ?></span>
    <span><?php _e('分类: '); ?><?php
        $postCategories = [];
        foreach ((array)$this->categories as $postCategory) {
            $postCategories[] = '<a href="' . htmlspecialchars((string)$postCategory['permalink'], ENT_QUOTES, 'UTF-8') . '">'
                . htmlspecialchars((string)$postCategory['name'], ENT_QUOTES, 'UTF-8') . '</a>';
        }
        echo implode(',', $postCategories);
    ?></span>
    <span><?php _e('浏览: '); ?><?php echo yingGetViews(intval($this->cid)); _e(' 次'); ?></span>
    <span><?php _e('约 '); ?><?php echo yingWordCount((string)$this->content); _e(' 字'); ?></span>
</div>
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

<?php $this->need('footer.php'); ?>
