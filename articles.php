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
        'day' => date('m.d', $localTime),
    ];
}
$total = 0;
foreach ($groups as $group) {
    $total += $group['count'];
}
?>

<div class="archive-head">
    <h1 class="post-title" itemprop="name headline"><?php echo htmlspecialchars((string)$this->title, ENT_QUOTES, 'UTF-8'); ?></h1>
    <?php if ($total > 0): ?><span class="archive-total"><?php echo sprintf(_t('共 %d 篇'), $total); ?></span><?php endif; ?>
</div>
<div class="post-content archive-body" itemprop="articleBody">
    <?php if (empty($groups)): ?>
    <p class="list-empty">暂无文章。</p>
    <?php else: ?>
    <div class="archive-tree">
        <?php $firstGroup = true; ?>
        <?php foreach ($groups as $group): ?>
        <section class="archive-node<?php if ($firstGroup): ?> is-current<?php endif; ?>">
            <h3 class="archive-month">
                <a href="<?php echo htmlspecialchars($group['url'], ENT_QUOTES); ?>"><?php echo htmlspecialchars($group['label']); ?></a>
                <span class="archive-count"><?php echo intval($group['count']); ?> 篇</span>
            </h3>
            <ul class="archive-list">
                <?php foreach ($group['posts'] as $post): ?>
                <li class="archive-entry">
                    <a class="archive-link" href="<?php echo htmlspecialchars($post['permalink'], ENT_QUOTES); ?>"><?php echo htmlspecialchars($post['title']); ?></a>
                    <span class="archive-day"><?php echo htmlspecialchars($post['day']); ?></span>
                </li>
                <?php endforeach; ?>
            </ul>
        </section>
        <?php $firstGroup = false; ?>
        <?php endforeach; ?>
    </div>
    <?php endif; ?>
</div>
<?php $this->need('footer.php'); ?>
