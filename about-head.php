<?php if (!defined('__TYPECHO_ROOT_DIR__')) exit; ?>
<?php $stats = yingSiteStats(); ?>
<header class="about-head">
    <h1 class="page-title" itemprop="name headline"><?php echo htmlspecialchars((string)$this->title, ENT_QUOTES, 'UTF-8'); ?></h1>
    <div class="about-meta">
        <div class="about-item"><span class="about-key"><?php _e('站长'); ?></span><span class="about-val"><?php echo htmlspecialchars((string)$this->options->title, ENT_QUOTES); ?></span></div>
        <div class="about-item"><span class="about-key"><?php _e('主题'); ?></span><span class="about-val">YingK · <a href="https://github.com/yiktt/YingK" target="_blank" rel="noopener">GitHub</a></span></div>
        <div class="about-item"><span class="about-key"><?php _e('程序'); ?></span><span class="about-val">Typecho</span></div>
        <?php if ($stats['posts'] > 0): ?>
        <div class="about-item"><span class="about-key"><?php _e('文章'); ?></span><span class="about-val"><?php echo number_format($stats['posts']); ?> <?php _e('篇'); ?></span></div>
        <div class="about-item"><span class="about-key"><?php _e('总字数'); ?></span><span class="about-val"><?php echo number_format($stats['words']); ?> <?php _e('字'); ?></span></div>
        <?php endif; ?>
        <?php if ($stats['days'] !== null): ?>
        <div class="about-item"><span class="about-key"><?php _e('运行'); ?></span><span class="about-val" title="<?php echo htmlspecialchars($stats['start'], ENT_QUOTES); ?>"><?php echo htmlspecialchars(yingUptimeText((int)$stats['days']), ENT_QUOTES); ?></span></div>
        <?php endif; ?>
    </div>
</header>
