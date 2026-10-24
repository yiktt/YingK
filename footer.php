<?php if (!defined('__TYPECHO_ROOT_DIR__')) exit; ?>
            <div class="footer">
                 <nav class="nav">
                <?php $copyrightText = trim((string)$this->options->copyright); ?>
                <?php if ($copyrightText !== ''): ?>
                 <?php echo htmlspecialchars(str_replace('{Y}', date('Y'), $copyrightText), ENT_QUOTES); ?><?php if ($this->options->footerbeian || $this->options->policebeian): ?><br/><?php endif; ?>
                <?php endif; ?>
                <?php if ($this->options->footerbeian): ?>
                <a href="https://beian.miit.gov.cn/" target="_blank" rel="noopener"><span><?php $this->options->footerbeian(); ?></span></a><?php if ($this->options->policebeian): ?><br/><?php endif; ?>
                <?php endif; ?>
                <?php if ($this->options->policebeian): ?>
                <a href="https://beian.mps.gov.cn/#/query/webSearch?code=<?php echo htmlspecialchars(preg_replace('/[^0-9]/', '', (string)$this->options->policebeian), ENT_QUOTES); ?>" target="_blank" rel="noopener"><span><?php $this->options->policebeian(); ?></span></a>
                <?php endif; ?>
                 </nav>
            </div>
        </div>
    </div>
    <div class="top-menu" id="top-menu">
        <button type="button" id="menu-handle" class="top-menu-btn menu-handle" title="收起 / 展开菜单" aria-label="收起或展开菜单" aria-expanded="true"><i class="ri-arrow-up-s-line"></i></button>
        <div class="top-menu-items open" id="top-menu-items">
            <button type="button" id="theme-toggle" class="top-menu-btn" aria-label="切换夜间或白天模式"><i class="ri-moon-line"></i></button>
            <button type="button" id="search-btn" class="top-menu-btn" aria-label="打开搜索"><i class="ri-search-line"></i></button>
            <a href="<?php $this->options->feedUrl(); ?>" target="_blank" rel="noopener" class="top-menu-btn" aria-label="RSS 订阅"><i class="ri-rss-line"></i></a>
        </div>
        <form id="search-panel" class="search-panel" method="post" action="<?php $this->options->siteUrl(); ?>">
            <input type="text" name="s" placeholder="输入关键字，回车搜索…" aria-label="搜索关键字"/>
        </form>
    </div>
    <div class="float-actions">
        <?php if ($this->is('post')): ?>
        <a id="to_top" href="#" class="float-btn scroll-btn" title="回到顶部"><i class="ri-arrow-up-line"></i></a>
        <a id="to_bottom" href="#" class="float-btn scroll-btn" title="直达底部"><i class="ri-arrow-down-line"></i></a>
        <?php endif; ?>
    </div>
    <div class="back-float">
        <?php if ($this->is('post')): ?>
        <a href="<?php $this->options->siteUrl(); ?>" id="back-btn" class="float-btn" title="返回列表" aria-label="返回列表"><i class="ri-arrow-left-line"></i></a>
        <?php endif; ?>
    </div>
    <script src="<?php $this->options->themeUrl('js/app.js'); ?>"></script>
</body>
</html>
