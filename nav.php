<?php if (!defined('__TYPECHO_ROOT_DIR__')) exit; ?>
<nav class="nav" aria-label="主导航">
    <ul class="flat">
        <?php $isHomeActive = $this->is('index') || $this->is('post') || $this->is('archive'); ?>
        <li<?php if ($isHomeActive): ?> class="active"<?php endif; ?>><a href="<?php $this->options->siteUrl(); ?>"<?php if ($this->is('index')): ?> aria-current="page"<?php endif; ?>>首页</a></li>
        <?php \Widget\Contents\Page\Rows::alloc()->to($pages); ?>
        <?php while ($pages->next()): ?>
            <?php if ($this->options->linksShow === 'off' && $pages->template === 'links.php') { continue; } ?>
            <?php $isCurrentPage = $this->is('page', $pages->slug); ?>
            <li<?php if ($isCurrentPage): ?> class="active"<?php endif; ?>><a href="<?php $pages->permalink(); ?>"<?php if ($isCurrentPage): ?> aria-current="page"<?php endif; ?>><?php $pages->title(); ?></a></li>
        <?php endwhile; ?>
    </ul>
</nav>
