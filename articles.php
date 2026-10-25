<?php
/**
 * 归档
 *
 * @package custom
 */

if (!defined('__TYPECHO_ROOT_DIR__')) exit;
$this->need('header.php');

$offset = $this->options->timezone - $this->options->serverTimezone;
$recent = \Widget\Contents\Post\Recent::alloc('pageSize=9999');
$groups = [];
while ($recent->next()) {
    $localTime = intval($recent->created) + $offset;
    $key = date('Y-m', $localTime);
    if (!isset($groups[$key])) {
        $year = date('Y', $localTime);
        $month = date('m', $localTime);
        $groups[$key] = [
            'label' => date('Y 年 m 月', $localTime),
            'url' => \Typecho\Router::url('archive_month', ['year' => $year, 'month' => $month], $this->options->index),
            'count' => 0,
            'posts' => [],
        ];
    }
    $groups[$key]['count']++;
    $groups[$key]['posts'][] = [
        'title' => $recent->title,
        'permalink' => $recent->permalink,
        'day' => date('m月d日', $localTime),
    ];
}
?>

<h1 class="post-title" itemprop="name headline">
    <?php $this->title(); ?>
</h1>
<div class="post-content" itemprop="articleBody">
    <?php if (empty($groups)): ?>
    <p class="list-empty">暂无文章。</p>
    <?php else: ?>
    <ul class="archives">
        <?php foreach ($groups as $group): ?>
        <li class="archives-item">
            <div class="archives-item-content">
                <h3 class="archives-item-title">
                    <a href="<?php echo htmlspecialchars($group['url'], ENT_QUOTES); ?>"><?php echo htmlspecialchars($group['label']); ?></a>
                    <span class="archives-count"><?php echo intval($group['count']); ?> 篇</span>
                </h3>
                <ul class="archives-list">
                    <?php foreach ($group['posts'] as $post): ?>
                    <li class="archives-entry">
                        <span class="archives-time"><?php echo htmlspecialchars($post['day']); ?></span>
                        <a class="archives-link" href="<?php echo htmlspecialchars($post['permalink'], ENT_QUOTES); ?>"><?php echo htmlspecialchars($post['title']); ?></a>
                    </li>
                    <?php endforeach; ?>
                </ul>
            </div>
        </li>
        <?php endforeach; ?>
    </ul>
    <?php endif; ?>
</div>
<?php $this->need('footer.php'); ?>
