<?php
/**
 * 关于
 *
 * @package custom
 */

if (!defined('__TYPECHO_ROOT_DIR__')) exit;
$this->need('header.php');
?>

<header class="page-header about-header">
    <h1 class="page-title" itemprop="name headline"><?php $this->title(); ?></h1>
    <span class="page-rule" aria-hidden="true"></span>
    <div class="about-card">
        <div class="about-row"><span class="about-key">站长</span><span class="about-val"><?php echo htmlspecialchars((string)$this->options->title, ENT_QUOTES); ?></span></div>
        <div class="about-row"><span class="about-key">主题</span><span class="about-val">YingK · <a href="https://github.com/yiktt/YingK" target="_blank" rel="noopener">GitHub</a></span></div>
        <div class="about-row"><span class="about-key">程序</span><span class="about-val">Typecho</span></div>
    </div>
</header>
<div class="post-content about-content" itemprop="articleBody">
    <?php $this->content(); ?>
</div>

<?php $this->need('footer.php'); ?>
