// 须在顶层捕获：DOMContentLoaded 回调执行时 document.currentScript 已为 null
var YING_THEME_URL = (document.currentScript && document.currentScript.src || '').replace(/\/js\/[^/]+$/, '');

// AJAX 换页期间 history.back() 会先触发浏览器原生滚动恢复：视口在内容交换前
// 瞬间跳到目标页保存的滚动位置（仍在旧页面内容上），随后内容再硬切，形成"闪一下"。
// 改为手动恢复：由 navigate() 在交换后同帧滚动到记录位置，视觉上只有一次原子切换。
if ('scrollRestoration' in history) {
    history.scrollRestoration = 'manual';
}

document.addEventListener('DOMContentLoaded', function () {
    var root = document.documentElement;

    // ===== 夜间 / 白天模式切换 =====
    var toggle = document.getElementById('theme-toggle');

    function syncToggle() {
        if (!toggle) {
            return;
        }
        var dark = root.getAttribute('data-theme') === 'dark';
        var icon = toggle.querySelector('i');
        if (icon) {
            icon.className = dark ? 'ri-sun-line' : 'ri-moon-line';
        }
        toggle.setAttribute('aria-pressed', dark ? 'true' : 'false');
        toggle.setAttribute('aria-label', dark ? '切换到白天模式' : '切换到夜间模式');
    }

    if (toggle) {
        toggle.addEventListener('click', function () {
            var next = root.getAttribute('data-theme') === 'dark' ? 'light' : 'dark';
            root.setAttribute('data-theme', next);
            try {
                localStorage.setItem('ying-theme', next);
            } catch (e) {}
            syncToggle();
        });
        syncToggle();
    }

    // ===== 头像首次加载：图片就绪后再淡入（样式见 main.css 的 site-avatar） =====
    var siteAvatar = document.querySelector('.header-top > img.site-avatar');
    if (siteAvatar) {
        var revealAvatar = function () {
            var apply = function () { siteAvatar.classList.add('is-avatar-in'); };
            if (window.requestAnimationFrame) {
                requestAnimationFrame(function () { requestAnimationFrame(apply); });
            } else {
                apply();
            }
        };
        if (siteAvatar.complete && siteAvatar.naturalWidth > 0) {
            revealAvatar();
        } else {
            siteAvatar.addEventListener('load', revealAvatar, {once: true});
            siteAvatar.addEventListener('error', revealAvatar, {once: true});
            setTimeout(revealAvatar, 3000);
        }
    }

    // ===== 一言 API：按后台配置获取随机句子（淡入过渡，失败回退固定内容） =====
    var yiyanEl = document.getElementById('yiyan-text');
    if (yiyanEl && yiyanEl.dataset.cats !== undefined) {
        var revealYiyan = function () {
            yiyanEl.classList.remove('is-loading');
        };
        var qs = 'encode=json';
        if (yiyanEl.dataset.cats) {
            yiyanEl.dataset.cats.split(',').forEach(function (c) {
                qs = 'c=' + encodeURIComponent(c) + '&' + qs;
            });
        }
        var ctrl = typeof AbortController !== 'undefined' ? new AbortController() : null;
        if (ctrl) {
            setTimeout(function () { ctrl.abort(); }, 3000);
        }
        fetch('https://v1.hitokoto.cn/?' + qs, ctrl ? {signal: ctrl.signal} : {})
            .then(function (r) { return r.json(); })
            .then(function (d) {
                var text = d && d.hitokoto ? d.hitokoto : '';
                if (text && yiyanEl.dataset.from === '1') {
                    var who = d.from_who || '';
                    var from = d.from || '';
                    if (who && from) {
                        text += ' —— ' + who + '《' + from + '》';
                    } else if (from) {
                        text += ' —— 《' + from + '》';
                    } else if (who) {
                        text += ' —— ' + who;
                    }
                }
                if (text) {
                    yiyanEl.textContent = text;
                } else {
                    yiyanEl.textContent = yiyanEl.dataset.fallback || '';
                }
                revealYiyan();
            })
            .catch(function () {
                yiyanEl.textContent = yiyanEl.dataset.fallback || '';
                revealYiyan();
            });
    }

    // ===== 顶部工具坞：桌面收起 / 展开（移动端不显示此把手） =====
    var menuHandle = document.getElementById('menu-handle');
    var menuItems = document.getElementById('top-menu-items');
    var menuHamburger = document.getElementById('menu-hamburger');
    var topMenuEl = document.getElementById('top-menu');
    var mobileMenuBreakpoint = 880;
    var lastMobileMenu = null;

    function isMobileMenu() {
        return window.innerWidth <= mobileMenuBreakpoint;
    }

    function isElementVisible(el) {
        if (!el) {
            return false;
        }
        var style = getComputedStyle(el);
        return style.display !== 'none' && style.visibility !== 'hidden' && el.getClientRects().length > 0;
    }

    // 收起时把主题/搜索/RSS 从 Tab 顺序移除；移动端工具坞未展开时同样不可操作。
    function syncMenuItemsAccessibility() {
        if (!menuItems) {
            return;
        }
        var collapsed = isMobileMenu()
            ? !(topMenuEl && topMenuEl.classList.contains('menu-open'))
            : !menuItems.classList.contains('open');
        if ('inert' in menuItems) {
            menuItems.inert = collapsed;
        }
        if (collapsed) {
            menuItems.setAttribute('inert', '');
        } else {
            menuItems.removeAttribute('inert');
        }
        menuItems.setAttribute('aria-hidden', collapsed ? 'true' : 'false');
        Array.prototype.forEach.call(menuItems.querySelectorAll('button, a'), function (el) {
            if (collapsed) {
                if (!el.hasAttribute('data-ying-tabindex')) {
                    el.setAttribute('data-ying-tabindex', String(el.tabIndex));
                }
                el.tabIndex = -1;
            } else {
                var original = el.getAttribute('data-ying-tabindex');
                el.removeAttribute('data-ying-tabindex');
                el.tabIndex = original === null ? 0 : parseInt(original, 10);
            }
        });
    }

    function clearMenuPosition() {
        if (topMenuEl) {
            topMenuEl.style.top = '';
            topMenuEl.style.right = '';
        }
    }

    function positionMenu() {
        if (!menuHamburger || !topMenuEl || !isMobileMenu()
            || !topMenuEl.classList.contains('menu-open')) {
            return;
        }
        var rect = menuHamburger.getBoundingClientRect();
        topMenuEl.style.top = (rect.bottom + 8) + 'px';
        topMenuEl.style.right = (window.innerWidth - rect.right) + 'px';
    }

    function setHamburgerMenu(open) {
        if (!menuHamburger || !topMenuEl) {
            return;
        }
        topMenuEl.classList.toggle('menu-open', open);
        menuHamburger.setAttribute('aria-expanded', open ? 'true' : 'false');
        if (open) {
            positionMenu();
        } else {
            clearMenuPosition();
        }
        syncMenuItemsAccessibility();
    }

    if (menuHandle && menuItems) {
        menuHandle.addEventListener('click', function () {
            var open = menuItems.classList.toggle('open');
            menuHandle.setAttribute('aria-expanded', open ? 'true' : 'false');
            var icon = menuHandle.querySelector('i');
            if (icon) {
                icon.className = open ? 'ri-arrow-up-s-line' : 'ri-arrow-down-s-line';
            }
            if (!open && menuItems.contains(document.activeElement)) {
                menuHandle.focus();
            }
            syncMenuItemsAccessibility();
        });
    }

    // ===== 移动端汉堡菜单（联系方式行末尾，默认收起，展开后工具坞定位到按钮下方） =====
    if (menuHamburger && topMenuEl) {
        menuHamburger.addEventListener('click', function () {
            setHamburgerMenu(!topMenuEl.classList.contains('menu-open'));
        });
        document.addEventListener('click', function (e) {
            if (!isMobileMenu()) {
                return;
            }
            if (topMenuEl.classList.contains('menu-open')
                && !topMenuEl.contains(e.target)
                && !menuHamburger.contains(e.target)) {
                setHamburgerMenu(false);
            }
        });
        document.addEventListener('keydown', function (e) {
            if (e.key === 'Escape' && topMenuEl.classList.contains('menu-open')) {
                setHamburgerMenu(false);
            }
        });
        var themeToggleBtn = document.getElementById('theme-toggle');
        if (themeToggleBtn) {
            themeToggleBtn.addEventListener('click', function () {
                if (isMobileMenu()) {
                    setHamburgerMenu(false);
                }
            });
        }
    }

    function syncMenuBreakpoint() {
        var mobile = isMobileMenu();
        if (lastMobileMenu === mobile) {
            return;
        }
        lastMobileMenu = mobile;
        if (topMenuEl) {
            topMenuEl.classList.remove('menu-open');
        }
        if (menuHamburger) {
            menuHamburger.setAttribute('aria-expanded', 'false');
        }
        clearMenuPosition();
        if (!mobile && menuItems) {
            menuItems.classList.add('open');
            if (menuHandle) {
                menuHandle.setAttribute('aria-expanded', 'true');
                var icon = menuHandle.querySelector('i');
                if (icon) {
                    icon.className = 'ri-arrow-up-s-line';
                }
            }
        }
        syncMenuItemsAccessibility();
    }

    syncMenuBreakpoint();
    window.addEventListener('resize', function () {
        syncMenuBreakpoint();
        if (topMenuEl && topMenuEl.classList.contains('menu-open')) {
            positionMenu();
        }
    }, {passive: true});

    // ===== 搜索面板 =====
    var searchBtn = document.getElementById('search-btn');
    var searchPanel = document.getElementById('search-panel');

    function closeSearch(restoreFocus) {
        if (!searchPanel) {
            return;
        }
        var wasOpen = searchPanel.classList.contains('open');
        searchPanel.classList.remove('open');
        if (searchBtn) {
            searchBtn.setAttribute('aria-expanded', 'false');
        }
        if (!restoreFocus || !wasOpen) {
            return;
        }
        // 只把焦点还给真实可见的控件，不制造新的隐藏焦点。
        var target = null;
        if (isMobileMenu()) {
            target = isElementVisible(menuHamburger) ? menuHamburger : null;
        } else {
            target = isElementVisible(searchBtn)
                ? searchBtn
                : (isElementVisible(menuHamburger) ? menuHamburger : null);
        }
        if (target) {
            target.focus();
        }
    }

    if (searchBtn && searchPanel) {
        var searchInput = searchPanel.querySelector('input');

        searchBtn.addEventListener('click', function () {
            var opened = searchPanel.classList.toggle('open');
            searchBtn.setAttribute('aria-expanded', opened ? 'true' : 'false');
            if (opened) {
                setHamburgerMenu(false);
                if (searchInput) {
                    searchInput.focus();
                }
            }
        });

        document.addEventListener('click', function (e) {
            if (searchPanel.classList.contains('open')
                && !searchPanel.contains(e.target)
                && !searchBtn.contains(e.target)) {
                closeSearch();
            }
        });

        document.addEventListener('keydown', function (e) {
            if (e.key === 'Escape' && searchPanel.classList.contains('open')) {
                closeSearch(true);
            }
        });
    }

    // ===== 回到顶部 / 直达底部（事件委托，局部换页后无需重新绑定） =====
    function updateScrollButtons() {
        var topBtn = document.getElementById('to_top');
        var bottomBtn = document.getElementById('to_bottom');
        var y = window.scrollY || window.pageYOffset;
        var max = document.documentElement.scrollHeight - window.innerHeight;
        if (topBtn) {
            topBtn.classList.toggle('show', y > 30);
        }
        if (bottomBtn) {
            bottomBtn.classList.toggle('show', y < max - 100);
        }
    }

    window.addEventListener('scroll', updateScrollButtons, {passive: true});
    window.addEventListener('resize', updateScrollButtons, {passive: true});
    window.addEventListener('load', updateScrollButtons);

    var reduceMotion = window.matchMedia && window.matchMedia('(prefers-reduced-motion: reduce)').matches;

    document.addEventListener('click', function (e) {
        var btn = e.target.closest ? e.target.closest('#to_top, #to_bottom') : null;
        if (!btn) {
            return;
        }
        e.preventDefault();
        window.scrollTo({
            top: btn.id === 'to_top' ? 0 : document.documentElement.scrollHeight,
            behavior: reduceMotion ? 'auto' : 'smooth'
        });
    });

    updateScrollButtons();

    // ===== 文章末尾：返回列表（本次会话内有站内跳转则回退上一页，直达则回首页） =====
    document.addEventListener('click', function (e) {
        var btn = e.target.closest ? e.target.closest('#back-btn') : null;
        if (!btn) {
            return;
        }
        var ajaxNav = false;
        try {
            ajaxNav = sessionStorage.getItem('yingBackable') === '1';
        } catch (err) {}
        var sameOrigin = false;
        try {
            sameOrigin = !!document.referrer && new URL(document.referrer).origin === location.origin;
        } catch (err) {}
        if ((ajaxNav || sameOrigin) && history.length > 1) {
            e.preventDefault();
            history.back();
        }
    });

    // ===== 404 页：回到上一页（无历史则回首页） =====
    document.addEventListener('click', function (e) {
        var btn = e.target.closest ? e.target.closest('#nf-back') : null;
        if (!btn) {
            return;
        }
        e.preventDefault();
        if (history.length > 1) {
            history.back();
        } else {
            var home = document.querySelector('#nf-home');
            location.href = home ? home.href : '/';
        }
    });

    // ===== 代码块装饰：语言标签 + 复制按钮 =====
    function decorateCodeBlocks() {
        var pres = document.querySelectorAll('.post-content pre');
        Array.prototype.forEach.call(pres, function (pre) {
            if (pre.parentElement && pre.parentElement.classList.contains('code-block')) {
                return;
            }
            var code = pre.querySelector('code');
            var lang = '';
            if (code && code.className) {
                var match = /(?:^|\s)lang-([^\s]+)/.exec(code.className);
                lang = match ? match[1] : '';
            }
            var wrap = document.createElement('figure');
            wrap.className = 'code-block';
            var header = document.createElement('figcaption');
            header.className = 'code-block-header';

            var langEl = document.createElement('span');
            langEl.className = 'code-lang';
            langEl.textContent = lang || 'text';

            var copyBtn = document.createElement('button');
            copyBtn.type = 'button';
            copyBtn.className = 'code-copy';
            copyBtn.title = '复制代码';
            copyBtn.innerHTML = '<i class="ri-file-copy-line"></i><span>复制</span>';

            header.appendChild(langEl);
            header.appendChild(copyBtn);
            pre.parentNode.insertBefore(wrap, pre);
            wrap.appendChild(header);
            wrap.appendChild(pre);
        });
    }

    document.addEventListener('click', function (e) {
        var btn = e.target.closest ? e.target.closest('.code-copy') : null;
        if (!btn) {
            return;
        }
        var block = btn.closest('.code-block');
        var code = block ? block.querySelector('code') : null;
        if (!code) {
            return;
        }
        var done = function () {
            var label = btn.querySelector('span');
            btn.classList.add('copied');
            if (label) {
                label.textContent = '已复制';
            }
            setTimeout(function () {
                btn.classList.remove('copied');
                if (label) {
                    label.textContent = '复制';
                }
            }, 1600);
        };
        var fallbackCopy = function () {
            var ta = document.createElement('textarea');
            ta.value = code.innerText;
            ta.style.position = 'fixed';
            ta.style.opacity = '0';
            document.body.appendChild(ta);
            ta.select();
            var ok = false;
            try {
                ok = document.execCommand('copy');
            } catch (err) {}
            ta.remove();
            if (ok) {
                done();
            }
        };
        if (navigator.clipboard && navigator.clipboard.writeText) {
            navigator.clipboard.writeText(code.innerText).then(done, fallbackCopy);
        } else {
            fallbackCopy();
        }
    });

    decorateCodeBlocks();

    // ===== 代码语法高亮：按需加载 highlight.js（VS Code Dark+ 配色见 main.css） =====
    var themeUrl = YING_THEME_URL;
    var hljsState = 'idle';

    function highlightCode() {
        if (!document.querySelector('.code-block code')) {
            return;
        }
        var run = function () {
            if (!window.hljs) {
                return;
            }
            document.querySelectorAll('.code-block code').forEach(function (code) {
                if (code.dataset.hlDone) {
                    return;
                }
                var m = /lang-([^\s]+)/.exec(code.className || '');
                if (m) {
                    code.classList.add('language-' + m[1]);
                }
                try {
                    window.hljs.highlightElement(code);
                    code.dataset.hlDone = '1';
                } catch (err) {}
            });
        };
        if (window.hljs) {
            run();
            return;
        }
        if (hljsState === 'loading') {
            return;
        }
        hljsState = 'loading';
        var s = document.createElement('script');
        s.src = themeUrl + '/js/vendor/highlight.min.js';
        s.onload = function () {
            hljsState = 'done';
            run();
        };
        document.head.appendChild(s);
    }

    highlightCode();

    // ===== AJAX 局部加载：页头保持不动，仅替换页头以下内容 =====
    var navLock = false;
    var progress = document.createElement('div');
    progress.className = 'nav-progress';
    progress.setAttribute('aria-hidden', 'true');
    document.body.appendChild(progress);

    function setNavigating(on) {
        var container = document.querySelector('.container');
        if (on) {
            root.classList.add('is-navigating');
            progress.classList.remove('done');
            if (container) {
                container.setAttribute('aria-busy', 'true');
            }
        } else {
            root.classList.remove('is-navigating');
            if (container) {
                container.removeAttribute('aria-busy');
            }
            progress.classList.add('done');
            setTimeout(function () {
                progress.classList.remove('done');
            }, 450);
        }
    }

    function saveScrollState() {
        try {
            var state = history.state || {};
            state.yingScroll = window.scrollY || window.pageYOffset || 0;
            history.replaceState(state, '', location.href);
        } catch (e) {}
    }

    function getScrollState() {
        try {
            var state = history.state;
            return state && typeof state.yingScroll === 'number' ? state.yingScroll : 0;
        } catch (e) {
            return 0;
        }
    }

    // 滚动位置写回当前历史条目（节流），前进/后退时据此恢复到原位
    var lastSavedScroll = -1;
    var scrollSaveTimer = 0;
    window.addEventListener('scroll', function () {
        if (scrollSaveTimer) {
            return;
        }
        scrollSaveTimer = setTimeout(function () {
            scrollSaveTimer = 0;
            if (navLock) {
                return;
            }
            var y = window.scrollY || window.pageYOffset || 0;
            if (y === lastSavedScroll) {
                return;
            }
            lastSavedScroll = y;
            saveScrollState();
        }, 300);
    }, {passive: true});

    function focusHeading() {
        var heading = document.querySelector('.container h1');
        if (!heading) {
            return;
        }
        heading.setAttribute('tabindex', '-1');
        try {
            heading.focus({preventScroll: true});
        } catch (e) {
            heading.focus();
        }
        heading.addEventListener('blur', function () {
            heading.removeAttribute('tabindex');
        }, {once: true});
    }

    function swapPage(doc, href) {
        var container = document.querySelector('.container');
        var newContainer = doc.querySelector('.container');

        if (!container || !newContainer) {
            location.href = href;
            return;
        }

        // 口令错误同页回显：结构未变，抑制入场动画重放，只留密印轻震
        // （须在下方 adoptNode 把节点移出 doc 之前判定）
        var inPlace = !!newContainer.querySelector('.post-lock.is-error');

        // 移除旧内容（保留页头 .header）——先快照，children 是动态集合
        Array.prototype.slice.call(container.children).forEach(function (el) {
            if (!el.classList.contains('header')) {
                el.remove();
            }
        });

        // 插入新内容（跳过新页面的页头）
        Array.prototype.slice.call(newContainer.children).forEach(function (el) {
            if (!el.classList.contains('header')) {
                container.appendChild(document.adoptNode(el));
            }
        });

        // 就地回显时给插入内容挂 no-enter；.back-float 节点跨交换保留，需显式切回
        var noEnterTargets = [document.querySelector('.back-float')];
        Array.prototype.slice.call(container.children).forEach(function (el) {
            if (!el.classList.contains('header')) {
                noEnterTargets.push(el);
            }
        });
        noEnterTargets.forEach(function (el) {
            if (el) {
                el.classList.toggle('no-enter', inPlace);
            }
        });

        // 同步导航高亮与 aria-current（只切换 class，不替换页头节点）
        var oldLis = document.querySelectorAll('.header .nav li');
        var newLis = doc.querySelectorAll('.header .nav li');
        if (oldLis.length === newLis.length) {
            Array.prototype.forEach.call(oldLis, function (li, i) {
                li.classList.toggle('active', newLis[i].classList.contains('active'));
                var oldLink = li.querySelector('a');
                var newLink = newLis[i].querySelector('a');
                if (oldLink && newLink) {
                    var current = newLink.getAttribute('aria-current');
                    if (current) {
                        oldLink.setAttribute('aria-current', current);
                    } else {
                        oldLink.removeAttribute('aria-current');
                    }
                }
            });
        }

        // 同步滚动按钮（文章页才有；只增删滚动按钮本身，
        // 不整体替换容器，避免销毁主题切换/搜索按钮的事件监听）
        var fa = document.querySelector('.float-actions');
        var newFa = doc.querySelector('.float-actions');
        if (fa) {
            Array.prototype.slice.call(fa.querySelectorAll('#to_top, #to_bottom')).forEach(function (el) {
                el.remove();
            });
            if (newFa) {
                var scrollBtns = Array.prototype.slice.call(newFa.querySelectorAll('#to_top, #to_bottom')).reverse();
                scrollBtns.forEach(function (el) {
                    fa.insertBefore(document.adoptNode(el), fa.firstChild);
                });
            }
        }

        // 同步左下返回按钮（文章页才有；点击走事件委托，替换安全）
        var oldBack = document.querySelector('.back-float');
        var newBack = doc.querySelector('.back-float');
        if (oldBack) {
            oldBack.innerHTML = newBack ? newBack.innerHTML : '';
        }
        document.body.classList.toggle('is-post', !!document.querySelector('.back-float .float-btn'));

        document.title = doc.title;
        closeSearch();
        updateScrollButtons();
        decorateCodeBlocks();
        highlightCode();
    }

    function navigate(href, push, options) {
        // 回退整页跳转：POST（搜索）场景走原生提交，避免丢失关键字
        var hardFallback = function () {
            if (options && typeof options.fallback === 'function') {
                options.fallback();
            } else {
                location.href = href;
            }
        };
        if (navLock) {
            hardFallback();
            return;
        }
        navLock = true;
        if (push) {
            saveScrollState();
        }
        var scrollToY = push ? 0 : getScrollState();
        setNavigating(true);
        var init = {credentials: 'same-origin'};
        if (options && options.method === 'POST') {
            init.method = 'POST';
            init.body = options.body;
            if (options.headers) {
                init.headers = options.headers;
            }
        }
        fetch(href, init)
            .then(function (r) {
                // 锁定态文章以 403 返回完整主题页：默认容忍以便就地换入；
                // 其余 403/4xx/5xx（如错误页，无 .container）仍会在结构校验处回退整页
                var tolerate = (options && options.tolerate) || [403];
                if (!r.ok && !(tolerate.indexOf(r.status) > -1)) {
                    throw new Error('HTTP ' + r.status);
                }
                return r.text().then(function (html) {
                    return {html: html, finalUrl: r.url};
                });
            })
            .then(function (res) {
                var html = res.html;
                var finalHref = res.finalUrl || href;
                var doc = new DOMParser().parseFromString(html, 'text/html');
                if (!doc.querySelector('.container') || !doc.querySelector('.header')) {
                    throw new Error('not a theme page');
                }
                if (doc.querySelector('.container script')) {
                    throw new Error('page contains script');
                }
                swapPage(doc, finalHref);
                if (push) {
                    history.pushState({yingScroll: 0}, '', finalHref);
                    try {
                        sessionStorage.setItem('yingBackable', '1');
                    } catch (e) {}
                }
                window.scrollTo(0, scrollToY);
                setTimeout(function () {
                    window.scrollTo(0, scrollToY);
                }, 0);
                if (options && typeof options.afterSwap === 'function') {
                    options.afterSwap();
                } else {
                    focusHeading();
                }
            })
            .catch(hardFallback)
            .then(function () {
                setNavigating(false);
                navLock = false;
            });
    }

    // 搜索面板：拦截提交，局部加载搜索结果页（与点击页面链接一致）
    var searchForm = document.getElementById('search-panel');
    if (searchForm) {
        searchForm.addEventListener('submit', function (e) {
            var input = searchForm.querySelector('input[name="s"]');
            var kw = input ? input.value.replace(/^\s+|\s+$/g, '') : '';
            if (!kw) {
                return;
            }
            e.preventDefault();
            setHamburgerMenu(false);
            navigate(searchForm.getAttribute('action') || location.href, true, {
                method: 'POST',
                body: new URLSearchParams({s: kw}).toString(),
                headers: {'Content-Type': 'application/x-www-form-urlencoded;charset=UTF-8'},
                fallback: function () { searchForm.submit(); }
            });
        });
    }

    // 密码表单：拦截提交，局部加载——口令错误同页提示，正确则就地换入正文
    // 用委托绑定，局部加载替换表单后依然有效
    document.addEventListener('submit', function (e) {
        var form = e.target && e.target.closest ? e.target.closest('.post-lock-form') : null;
        if (!form) {
            return;
        }
        var pass = form.querySelector('input[name="yingLockPass"]');
        if (!pass || !pass.value) {
            return;
        }
        e.preventDefault();
        navigate(form.getAttribute('action') || location.href, false, {
            method: 'POST',
            body: new FormData(form),
            tolerate: [403],
            afterSwap: function () {
                var input = document.querySelector('.post-lock-field input');
                if (input) {
                    input.focus();
                } else {
                    focusHeading();
                }
            },
            fallback: function () {
                if (form.isConnected) {
                    form.submit();
                } else {
                    location.reload();
                }
            }
        });
    });

    // 拦截站内链接点击
    document.addEventListener('click', function (e) {
        if (e.defaultPrevented || e.button !== 0 || e.metaKey || e.ctrlKey || e.shiftKey || e.altKey) {
            return;
        }
        var a = e.target.closest ? e.target.closest('a') : null;
        if (!a) {
            return;
        }
        var href = a.getAttribute('href');
        if (!href || href.charAt(0) === '#' || a.hasAttribute('download') || a.hasAttribute('data-no-ajax')) {
            return;
        }
        var target = a.getAttribute('target');
        if (target && target !== '_self') {
            return;
        }
        var url;
        try {
            url = new URL(a.href, location.href);
        } catch (err) {
            return;
        }
        if (url.origin !== location.origin) {
            return;
        }
        if (!/^https?:$/.test(url.protocol)) {
            return;
        }
        if (url.pathname.indexOf('/admin') === 0) {
            return;
        }
        // 含锚点的链接交给浏览器原生导航：跨页 #section 不会在 AJAX 换页时丢失。
        if (url.hash) {
            return;
        }
        e.preventDefault();
        navigate(url.href, true);
    });

    // 浏览器前进 / 后退
    window.addEventListener('popstate', function () {
        navigate(location.href, false);
    });
});
