<?php if (!defined('__TYPECHO_ROOT_DIR__')) exit;
$this->need('header.php');
$isAboutPage = $this->is('page', 'start-page');
?>

<?php if ($isAboutPage): ?>
<?php $this->need('about-head.php'); ?>
<?php else: ?>
<header class="page-header">
    <h1 class="page-title" itemprop="name headline"><?php echo htmlspecialchars((string)$this->title, ENT_QUOTES, 'UTF-8'); ?></h1>
    <span class="page-rule" aria-hidden="true"></span>
</header>
<?php endif; ?>
<div class="post-content<?php if ($isAboutPage): ?> about-content<?php endif; ?>" itemprop="articleBody">
    <?php echo yingContentHtml((string)$this->content); ?>
</div>
<?php if ($isAboutPage): ?>
<p class="about-end" aria-hidden="true"><?php _e('完'); ?></p>
<?php endif; ?>

<?php $this->need('footer.php'); ?>
