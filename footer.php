<?php if (!defined('__TYPECHO_ROOT_DIR__')) exit; ?>
            </main>
            <div class="footer">
                 <nav class="nav" aria-label="页脚信息">
                <?php $copyrightText = trim((string)$this->options->copyright); ?>
                <?php if ($copyrightText !== ''): ?>
                 <?php echo htmlspecialchars(str_replace('{Y}', date('Y', time() + (int)$this->options->timezone - (int)$this->options->serverTimezone), $copyrightText), ENT_QUOTES); ?><?php if ($this->options->footerbeian || $this->options->policebeian): ?><br/><?php endif; ?>
                <?php endif; ?>
                <?php if ($this->options->footerbeian): ?>
                <a href="https://beian.miit.gov.cn/" target="_blank" rel="noopener"><span><?php echo htmlspecialchars((string)$this->options->footerbeian, ENT_QUOTES); ?></span></a><?php if ($this->options->policebeian): ?><br/><?php endif; ?>
                <?php endif; ?>
                <?php if ($this->options->policebeian): ?>
                <a href="https://beian.mps.gov.cn/#/query/webSearch?code=<?php echo htmlspecialchars(preg_replace('/[^0-9]/', '', (string)$this->options->policebeian), ENT_QUOTES); ?>" target="_blank" rel="noopener"><span><?php echo htmlspecialchars((string)$this->options->policebeian, ENT_QUOTES); ?></span></a>
                <?php endif; ?>
                 </nav>
            </div>
        </div>
    </div>
    <div class="top-menu" id="top-menu">
        <button type="button" id="menu-handle" class="top-menu-btn menu-handle" title="收起 / 展开菜单" aria-label="收起或展开菜单" aria-expanded="true" aria-controls="top-menu-items"><i class="ri-arrow-up-s-line"></i></button>
        <div class="top-menu-items open" id="top-menu-items">
            <button type="button" id="theme-toggle" class="top-menu-btn" aria-label="切换夜间或白天模式" aria-pressed="false"><i class="ri-moon-line"></i></button>
            <button type="button" id="search-btn" class="top-menu-btn" aria-label="打开搜索" aria-expanded="false" aria-controls="search-panel"><i class="ri-search-line"></i></button>
            <a href="<?php $this->options->feedUrl(); ?>" target="_blank" rel="noopener" class="top-menu-btn" aria-label="RSS 订阅"><i class="ri-rss-line"></i></a>
        </div>
        <form id="search-panel" class="search-panel" method="post" action="<?php $this->options->siteUrl(); ?>" role="search">
            <input type="text" name="s" placeholder="输入关键字，回车搜索…" aria-label="搜索关键字"/>
        </form>
    </div>
    <div class="float-actions">
        <?php if ($this->is('post')): ?>
        <button type="button" id="to_top" class="float-btn scroll-btn" title="回到顶部" aria-label="回到顶部"><i class="ri-arrow-up-line"></i></button>
        <button type="button" id="to_bottom" class="float-btn scroll-btn" title="直达底部" aria-label="直达底部"><i class="ri-arrow-down-line"></i></button>
        <?php endif; ?>
    </div>
    <div class="back-float">
        <?php if ($this->is('post')): ?>
        <a href="<?php $this->options->siteUrl(); ?>" id="back-btn" class="float-btn" aria-label="返回列表"><i class="ri-arrow-left-line"></i></a>
        <?php endif; ?>
    </div>
    <script src="<?php $this->options->themeUrl('js/app.js'); ?>?v=<?php echo (int)filemtime(__DIR__ . '/js/app.js'); ?>"></script>
</body>
</html>
