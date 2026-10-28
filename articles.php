<?php
/**
 * 归档
 *
 * @package custom
 */

if (!defined('__TYPECHO_ROOT_DIR__')) exit;
$this->need('header.php');
if ($this->hidden) {
    echo yingLockedPageHtml($this);
    $this->need('footer.php');
    return;
}

$offset = (int)$this->options->timezone - (int)$this->options->serverTimezone;
$db = \Typecho\Db::get();
// 归档只取标题/时间/链接所需字段，不装载正文；分组与链接输出保持原样。
$rows = $db->fetchAll($db->select('cid', 'title', 'slug', 'created', 'password', 'authorId')
    ->from('table.contents')
    ->where('status = ?', 'publish')
    ->where('created < ?', (int)$this->options->time)
    ->where('type = ?', 'post')
    ->order('created', \Typecho\Db::SORT_DESC));
$groups = [];
foreach ($rows as $row) {
    $localTime = (int)$row['created'] + $offset;
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
    $password = (string)$row['password'];
    $isHidden = $password !== ''
        && $password !== (string)\Typecho\Cookie::get('protectPassword_' . $row['cid'])
        && (int)$row['authorId'] !== (int)$this->user->uid
        && !$this->user->pass('editor', true);
    $groups[$key]['count']++;
    $groups[$key]['posts'][] = [
        'title' => $isHidden ? _t('此内容被密码保护') : (string)$row['title'],
        'permalink' => \Typecho\Router::url('post', $row, $this->options->index),
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
