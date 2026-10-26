<?php
/**
 * 关于
 *
 * @package custom
 */

if (!defined('__TYPECHO_ROOT_DIR__')) exit;
$this->need('header.php');
$this->need('about-head.php');
?>
<div class="post-content about-content" itemprop="articleBody">
    <?php echo yingContentHtml((string)$this->content); ?>
</div>
<p class="about-end" aria-hidden="true"><?php _e('完'); ?></p>

<?php $this->need('footer.php'); ?>
