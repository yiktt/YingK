<?php if (!defined('__TYPECHO_ROOT_DIR__')) exit; ?>
<header class="about-head about-head-dossier">
    <p class="about-eyebrow">ABOUT / 站点档案</p>
    <h1 class="page-title" itemprop="name headline"><?php echo htmlspecialchars((string)$this->title, ENT_QUOTES, 'UTF-8'); ?></h1>
    <?php echo yingAboutStatsDisplayHtml('top'); ?>
</header>
