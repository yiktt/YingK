<?php if (!defined('__TYPECHO_ROOT_DIR__')) exit; ?>
<nav class="nav">
    <ul class="flat">
        <li<?php if ($this->is('index') || $this->is('post') || $this->is('archive')): ?> class="active"<?php endif; ?>><a href="<?php $this->options->siteUrl(); ?>">首页</a></li>
        <?php \Widget\Contents\Page\Rows::alloc()->to($pages); ?>
        <?php while ($pages->next()): ?>
            <?php if ($this->options->linksShow === 'off' && $pages->template === 'links.php') { continue; } ?>
            <li<?php if ($this->is('page', $pages->slug)): ?> class="active"<?php endif; ?>><a href="<?php $pages->permalink(); ?>"><?php $pages->title(); ?></a></li>
        <?php endwhile; ?>
    </ul>
</nav>
