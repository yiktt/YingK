<?php
/**
 * 归档
 *
 * @package custom
 */

if (!defined('__TYPECHO_ROOT_DIR__')) exit;
$this->need('header.php');
$db = \Typecho\Db::get();
$timezoneOffset = $this->options->timezone - $this->options->serverTimezone;
?>

<?php $this->need('nav.php'); ?>
</div>
<h1 class="post-title" itemprop="name headline">
    <?php $this->title(); ?>
</h1>
<div class="post-content" itemprop="articleBody">
    <ul class="archives">
        <?php \Widget\Contents\Post\Date::alloc('type=month&format=Y 年 m 月')->to($archives); ?>
        <?php while ($archives->next()): ?>
            <?php
            $year = intval($archives->year);
            $month = intval($archives->month);
            $nextYear = $month == 12 ? $year + 1 : $year;
            $nextMonth = $month == 12 ? 1 : $month + 1;
            $contents = $db->fetchAll($db->select('cid', 'title', 'created')->from('table.contents')
                ->where('table.contents.status = ?', 'publish')
                ->where('table.contents.created >= ?', strtotime(sprintf('%d-%02d-01', $year, $month)) - $timezoneOffset)
                ->where('table.contents.created < ?', strtotime(sprintf('%d-%02d-01', $nextYear, $nextMonth)) - $timezoneOffset)
                ->where('table.contents.type = ?', 'post')
                ->order('table.contents.created', \Typecho\Db::SORT_DESC));
            ?>
            <li class="archives-item">
                <div class="archives-item-content">
                    <h3 class="archives-item-title">
                        <a href="<?php $archives->permalink(); ?>"><?php $archives->date(); ?></a>
                        <span class="archives-count"><?php echo count($contents); ?> 篇</span>
                    </h3>
                    <ul class="archives-list">
                        <?php foreach ($contents as $content): ?>
                        <li class="archives-entry">
                            <span class="archives-time"><?php echo date('m月d日', $content['created'] + $timezoneOffset); ?></span>
                            <a class="archives-link" href="<?php echo htmlspecialchars(\Typecho\Router::url('post', $content, $this->options->index)); ?>"><?php echo htmlspecialchars($content['title']); ?></a>
                        </li>
                        <?php endforeach; ?>
                    </ul>
                </div>
            </li>
        <?php endwhile; ?>
    </ul>
</div>
<?php $this->need('footer.php'); ?>
