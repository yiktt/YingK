<?php
if (!defined('__TYPECHO_ROOT_DIR__')) exit;

/**
 * 渲染文章前自增浏览次数
 */
function themeInit($archive)
{
    if ($archive->is('post')) {
        yingBumpViews(intval($archive->cid));
    }
}

/**
 * 浏览次数 +1（存于 typecho_fields 表；同一浏览器 24 小时去重）
 */
function yingBumpViews(int $cid): void
{
    $cookie = 'ying_view_' . $cid;
    if (isset($_COOKIE[$cookie])) {
        return;
    }
    try {
        $db = \Typecho\Db::get();
        $row = $db->fetchRow($db->select('int_value')->from('table.fields')
            ->where('cid = ?', $cid)->where('name = ?', 'views'));
        if ($row) {
            $db->query($db->update('table.fields')
                ->rows(['int_value' => intval($row['int_value']) + 1])
                ->where('cid = ?', $cid)->where('name = ?', 'views'));
        } else {
            $db->query($db->insert('table.fields')
                ->rows(['cid' => $cid, 'name' => 'views', 'type' => 'int', 'int_value' => 1]));
        }
        setcookie($cookie, '1', [
            'expires' => time() + 86400,
            'path' => '/',
            'secure' => !empty($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off',
            'httponly' => true,
            'samesite' => 'Lax',
        ]);
    } catch (\Exception $e) {
        // 计数失败不影响正常输出
    }
}

/**
 * 清理旧版按 IP/日期写入的 view_<md5> 去重标记
 */
function yingCleanupLegacyViewMarks(): void
{
    try {
        $db = \Typecho\Db::get();
        $rows = $db->fetchAll($db->select('cid', 'name')->from('table.fields')
            ->where('type = ?', 'str')
            ->where('name LIKE ?', 'view%'));
        foreach ($rows as $row) {
            if (preg_match('/^view_[0-9a-f]{32}$/', (string)$row['name'])) {
                $db->query($db->delete('table.fields')
                    ->where('cid = ?', $row['cid'])->where('name = ?', $row['name']));
            }
        }
    } catch (\Exception $e) {
    }
}

/**
 * 读取文章浏览次数
 */
function yingGetViews(int $cid): int
{
    try {
        $db = \Typecho\Db::get();
        $row = $db->fetchRow($db->select('int_value')->from('table.fields')
            ->where('cid = ?', $cid)->where('name = ?', 'views'));
        return $row ? intval($row['int_value']) : 0;
    } catch (\Exception $e) {
        return 0;
    }
}

/**
 * 统计文字数量（去除空白与标签）
 */
function yingWordCount(string $html): int
{
    $text = trim(preg_replace('/\s+/u', '', strip_tags($html)));
    return mb_strlen($text, 'UTF-8');
}

/**
 * 友链动态编辑器（后台 HTML/JS，通过表单 description 注入）
 */
function yingFriendsEditorHtml(): string
{
    return <<<'HTML'
<div id="friends-editor"></div>
<button type="button" id="friend-add-btn" class="btn btn-s">+ 添加友链</button>
<style>
#friends-editor .friend-block { border: 1px solid #dcdcdc; border-radius: 6px; padding: 10px 12px; margin: 8px 0 12px; background: #fafafa; }
#friends-editor .friend-block-head { display: flex; justify-content: space-between; align-items: center; margin-bottom: 8px; }
#friends-editor .friend-block-head strong { font-size: 13px; color: #444; }
#friends-editor .friend-del { border: 0; background: #ffeceb; color: #c0392b; border-radius: 4px; padding: 3px 12px; cursor: pointer; font-size: 12px; }
#friends-editor .friend-del:hover { background: #ffd6d3; }
#friends-editor .friend-row { display: flex; align-items: center; gap: 8px; margin: 6px 0; flex-wrap: wrap; }
#friends-editor .friend-row > label { flex: none; width: 42px; font-size: 13px; color: #666; margin: 0; }
#friends-editor .friend-row > input[type=text] { flex: 1; min-width: 180px; }
#friends-editor .friend-row .f-name { min-width: 140px !important; }
#friends-editor .friend-row > textarea { flex: 1; min-width: 260px; }
#friends-editor .friend-avatar-preview { flex: none; width: 36px; height: 36px; border-radius: 50%; background: #e6e6e6; display: inline-flex; align-items: center; justify-content: center; color: #999; font-size: 14px; overflow: hidden; }
#friend-add-btn { margin-top: 4px; }
</style>
<script>
(function () {
    function boot() {
        var storage = document.querySelector('input[name=friendsJson]');
        var wrap = document.getElementById('friends-editor');
        var addBtn = document.getElementById('friend-add-btn');
        if (!storage || !wrap || !addBtn || storage.dataset.yingReady) {
            return;
        }
        storage.dataset.yingReady = '1';
        storage.type = 'hidden';
        var model = [];
        try {
            model = JSON.parse(storage.value || '[]');
            if (!Array.isArray(model)) {
                model = [];
            }
        } catch (e) {
            model = [];
        }

        function serialize() {
            storage.value = JSON.stringify(model);
        }

        function drawPreview(el, avatar, name) {
            if (avatar) {
                el.innerHTML = '';
                var img = document.createElement('img');
                img.src = avatar;
                img.style.width = '36px';
                img.style.height = '36px';
                img.style.borderRadius = '50%';
                img.style.objectFit = 'cover';
                img.onerror = function () {
                    el.textContent = (name || '友').substring(0, 1);
                };
                el.appendChild(img);
            } else {
                el.textContent = (name || '友').substring(0, 1);
            }
        }

        function render() {
            wrap.innerHTML = '';
            model.forEach(function (f, i) {
                var block = document.createElement('div');
                block.className = 'friend-block';
                block.innerHTML =
                    '<div class="friend-block-head"><strong>友链 ' + (i + 1) + '</strong>' +
                    '<button type="button" class="friend-del">删除</button></div>' +
                    '<div class="friend-row"><span class="friend-avatar-preview"></span>' +
                    '<input type="text" class="f-avatar" placeholder="头像图片 URL（留空用首字徽标）"></div>' +
                    '<div class="friend-row"><label>名字</label><input type="text" class="f-name" placeholder="网站名称">' +
                    '<label>链接</label><input type="text" class="f-url" placeholder="https://example.com"></div>' +
                    '<div class="friend-row"><label>简介</label><textarea class="f-desc" rows="2" placeholder="一句话介绍"></textarea></div>';
                var avatarInput = block.querySelector('.f-avatar');
                var nameInput = block.querySelector('.f-name');
                var urlInput = block.querySelector('.f-url');
                var descInput = block.querySelector('.f-desc');
                var previewEl = block.querySelector('.friend-avatar-preview');
                avatarInput.value = f.avatar || '';
                nameInput.value = f.name || '';
                urlInput.value = f.url || '';
                descInput.value = f.desc || '';
                drawPreview(previewEl, f.avatar || '', f.name || '');
                var sync = function () {
                    f.avatar = avatarInput.value.trim();
                    f.name = nameInput.value.trim();
                    f.url = urlInput.value.trim();
                    f.desc = descInput.value.trim();
                    drawPreview(previewEl, f.avatar, f.name);
                    serialize();
                };
                avatarInput.addEventListener('input', sync);
                nameInput.addEventListener('input', sync);
                urlInput.addEventListener('input', sync);
                descInput.addEventListener('input', sync);
                block.querySelector('.friend-del').addEventListener('click', function () {
                    model.splice(i, 1);
                    render();
                    serialize();
                });
                wrap.appendChild(block);
            });
        }

        addBtn.addEventListener('click', function () {
            model.push({ name: '', url: '', desc: '', avatar: '' });
            render();
            serialize();
            var blocks = wrap.querySelectorAll('.friend-block');
            var last = blocks[blocks.length - 1];
            if (last) {
                last.scrollIntoView({ behavior: 'smooth', block: 'center' });
                last.querySelector('.f-name').focus();
            }
        });

        var form = storage.closest('form');
        if (form) {
            form.addEventListener('submit', serialize);
        }
        render();
    }
    if (document.readyState === 'loading') {
        document.addEventListener('DOMContentLoaded', boot);
    } else {
        boot();
    }
})();
</script>
HTML;
}

/**
 * 联系方式类型表（键 => [标签, 图标]）
 */
function yingContactTypes(): array
{
    return [
        'github'   => ['GitHub', 'ri-github-line'],
        'mail'     => ['邮箱', 'ri-mail-line'],
        'weibo'    => ['微博', 'ri-weibo-line'],
        'twitter'  => ['X', 'ri-twitter-x-line'],
        'bilibili' => ['B站', 'ri-bilibili-line'],
        'zhihu'    => ['知乎', 'ri-zhihu-line'],
        'telegram' => ['Telegram', 'ri-telegram-line'],
        'link'     => ['自定义链接', 'ri-link'],
    ];
}

/**
 * 联系方式动态编辑器（后台 HTML/JS）
 */
function yingContactsEditorHtml(): string
{
    $options = '';
    foreach (yingContactTypes() as $key => $info) {
        $options .= '<option value="' . $key . '">' . htmlspecialchars($info[0]) . '</option>';
    }

    return <<<HTML
<div id="contacts-editor"></div>
<button type="button" id="contact-add-btn" class="btn btn-s">+ 添加联系方式</button>
<style>
#contacts-editor .contact-block { border: 1px solid #dcdcdc; border-radius: 6px; padding: 10px 12px; margin: 8px 0 12px; background: #fafafa; display: flex; align-items: center; gap: 8px; flex-wrap: wrap; }
#contacts-editor .contact-block .ct-title { flex: none; font-size: 13px; color: #444; min-width: 64px; }
#contacts-editor .contact-block select { flex: none; }
#contacts-editor .contact-block input[type=text] { flex: 1; min-width: 200px; }
#contacts-editor .contact-del { border: 0; background: #ffeceb; color: #c0392b; border-radius: 4px; padding: 3px 12px; cursor: pointer; font-size: 12px; }
#contacts-editor .contact-del:hover { background: #ffd6d3; }
</style>
<script>
(function () {
    function boot() {
        var storage = document.querySelector('input[name=contactsJson]');
        var wrap = document.getElementById('contacts-editor');
        var addBtn = document.getElementById('contact-add-btn');
        if (!storage || !wrap || !addBtn || storage.dataset.yingReady) {
            return;
        }
        storage.dataset.yingReady = '1';
        storage.type = 'hidden';
        var model = [];
        try {
            model = JSON.parse(storage.value || '[]');
            if (!Array.isArray(model)) {
                model = [];
            }
        } catch (e) {
            model = [];
        }
        var typeOptions = '{$options}';

        function serialize() {
            storage.value = JSON.stringify(model);
        }

        function render() {
            wrap.innerHTML = '';
            model.forEach(function (c, i) {
                var block = document.createElement('div');
                block.className = 'contact-block';
                block.innerHTML =
                    '<span class="ct-title">联系方式 ' + (i + 1) + '</span>' +
                    '<select class="c-type">' + typeOptions + '</select>' +
                    '<input type="text" class="c-url" placeholder="链接或邮箱地址（自动补全协议）">' +
                    '<button type="button" class="contact-del">删除</button>';
                var typeSel = block.querySelector('.c-type');
                var urlInput = block.querySelector('.c-url');
                typeSel.value = c.type || 'link';
                urlInput.value = c.url || '';
                var sync = function () {
                    c.type = typeSel.value;
                    c.url = urlInput.value.trim();
                    serialize();
                };
                typeSel.addEventListener('change', sync);
                urlInput.addEventListener('input', sync);
                block.querySelector('.contact-del').addEventListener('click', function () {
                    model.splice(i, 1);
                    render();
                    serialize();
                });
                wrap.appendChild(block);
            });
        }

        addBtn.addEventListener('click', function () {
            model.push({ type: 'github', url: '' });
            render();
            serialize();
            var blocks = wrap.querySelectorAll('.contact-block');
            var last = blocks[blocks.length - 1];
            if (last) {
                last.scrollIntoView({ behavior: 'smooth', block: 'center' });
                last.querySelector('.c-url').focus();
            }
        });

        var form = storage.closest('form');
        if (form) {
            form.addEventListener('submit', serialize);
        }
        render();
    }
    if (document.readyState === 'loading') {
        document.addEventListener('DOMContentLoaded', boot);
    } else {
        boot();
    }
})();
</script>
HTML;
}

function themeConfig($form)
{
    yingCleanupLegacyViewMarks();

    $logoUrl = new \Typecho\Widget\Helper\Form\Element\Text(
        'logoUrl',
        null,
        null,
        _t('头像'),
        _t('在这里填入一个头像 URL 地址，留空使用主题内置头像。')
    );
    $form->addInput($logoUrl->addRule('url', _t('请填写一个合法的 URL 地址')));

    $yiyanSource = new \Typecho\Widget\Helper\Form\Element\Radio(
        'yiyanSource',
        ['fixed' => _t('固定内容（在下方填写）'), 'api' => _t('从一言 API 自动获取（每次打开页面随机一句话）')],
        'fixed',
        _t('一言来源'),
        _t('选择从哪里获取显示在头像旁边的一句话。')
    );
    $form->addInput($yiyanSource);

    $yiyan = new \Typecho\Widget\Helper\Form\Element\Textarea(
        'yiyan',
        null,
        '道德当身，故不以物惑',
        _t('一言（固定内容）'),
        _t('仅在上方选择"固定内容"时使用，将会显示在头像旁边；纯文本。')
    );
    $form->addInput($yiyan);

    $yiyanCats = new \Typecho\Widget\Helper\Form\Element\Checkbox(
        'yiyanCats',
        [
            'd' => _t('文学'),
            'i' => _t('诗词'),
            'k' => _t('哲学'),
            'a' => _t('动画'),
            'b' => _t('漫画'),
            'c' => _t('游戏'),
            'h' => _t('影视'),
            'j' => _t('网易云'),
        ],
        ['d', 'i'],
        _t('一言分类（API 模式）'),
        _t('勾选想获取的句子类型，可多选；全部取消则不限制分类。')
    );
    $form->addInput($yiyanCats->multiMode());

    $yiyanShowFrom = new \Typecho\Widget\Helper\Form\Element\Radio(
        'yiyanShowFrom',
        ['on' => _t('显示出处'), 'off' => _t('只显示句子')],
        'off',
        _t('一言出处（API 模式）'),
        _t('是否在句子后面附上来源，如"—— 《书名》"。')
    );
    $form->addInput($yiyanShowFrom);

    $bgUrl = new \Typecho\Widget\Helper\Form\Element\Text(
        'bgUrl',
        null,
        null,
        _t('背景图'),
        _t('在这里填入背景图 URL（全站生效），留空使用主题内置背景。')
    );
    $form->addInput($bgUrl->addRule('url', _t('请填写一个合法的 URL 地址')));

    // 联系方式：存于 contactsJson（JSON）；为空且旧字段有数据时从旧版固定字段迁移
    $optionsWidgetC = \Widget\Options::alloc();
    $contactsInit = trim((string)$optionsWidgetC->contactsJson);
    if ($contactsInit === '' || $contactsInit === '[]') {
        $legacyMap = [
            'githubUrl' => 'github', 'mailAddr' => 'mail', 'weiboUrl' => 'weibo',
            'twitterUrl' => 'twitter', 'bilibiliUrl' => 'bilibili', 'zhihuUrl' => 'zhihu',
            'telegramUrl' => 'telegram',
        ];
        $legacyContacts = [];
        foreach ($legacyMap as $cfield => $ctype) {
            $cval = trim((string)$optionsWidgetC->{$cfield});
            if ($cval !== '') {
                $legacyContacts[] = ['type' => $ctype, 'url' => $cval];
            }
        }
        if (!empty($legacyContacts)) {
            $contactsInit = json_encode($legacyContacts, JSON_UNESCAPED_UNICODE);
        }
    }

    $jsonListRule = function ($value): bool {
        $value = trim((string)$value);
        if ($value === '') {
            return true;
        }
        $data = json_decode($value, true);
        if (!is_array($data)) {
            return false;
        }
        foreach ($data as $item) {
            if (!is_array($item)) {
                return false;
            }
        }
        return true;
    };

    $contactsEl = new \Typecho\Widget\Helper\Form\Element\Text('contactsJson', null, $contactsInit, _t('联系方式管理'), _t('点击下方按钮添加联系方式，选择类型、填写链接后保存；桌面端显示在头像行右侧，窄屏自动换行。'));
    $contactsEl->description(yingContactsEditorHtml());
    $contactsEl->addRule($jsonListRule, _t('联系方式数据格式错误，请重新添加后再保存。'));
    $form->addInput($contactsEl);

    $copyright = new \Typecho\Widget\Helper\Form\Element\Text(
        'copyright',
        null,
        '© 2016 - {Y}',
        _t('页脚版权文字'),
        _t('显示在页脚左侧，{Y} 会自动替换为当前年份；留空则不显示版权行。')
    );
    $form->addInput($copyright);

    $footerbeian = new \Typecho\Widget\Helper\Form\Element\Text(
        'footerbeian',
        null,
        null,
        _t('备案号（ICP）'),
        _t('如"X ICP备0000000000号"，留空不显示。')
    );
    $form->addInput($footerbeian);

    $policebeian = new \Typecho\Widget\Helper\Form\Element\Text(
        'policebeian',
        null,
        null,
        _t('公安备案号'),
        _t('如"X公网安备 00000000000000号"，填完整文案，显示在 ICP 备案号下方，自动链接到公安备案查询页。')
    );
    $form->addInput($policebeian);

    $cardAlpha = new \Typecho\Widget\Helper\Form\Element\Text(
        'cardAlpha',
        null,
        '75',
        _t('卡片透明度'),
        _t('填 0 到 100 之间的数字（百分比，越小越透出背景图），留空默认 75。')
    );
    $form->addInput($cardAlpha);

    $linksShow = new \Typecho\Widget\Helper\Form\Element\Radio(
        'linksShow',
        ['on' => _t('显示友链页'), 'off' => _t('隐藏友链页')],
        'on',
        _t('友链页'),
        _t('隐藏后导航中不再显示友链入口，直接访问时显示"暂未开放"提示。需先在「管理 → 独立页面」新建页面并选择"友链"自定义模板。')
    );
    $form->addInput($linksShow);

    // 友链数据：存于 friendsJson（JSON），首次渲染时从旧版固定字段迁移
    $optionsWidget = \Widget\Options::alloc();
    $friendsInit = trim((string)$optionsWidget->friendsJson);
    if ($friendsInit === '') {
        $legacyFriends = [];
        for ($i = 1; $i <= 10; $i++) {
            $lname = trim((string)$optionsWidget->{'friendName' . $i});
            $lurl = trim((string)$optionsWidget->{'friendUrl' . $i});
            if ($lname === '' || $lurl === '') {
                continue;
            }
            $legacyFriends[] = [
                'name' => $lname,
                'url' => $lurl,
                'desc' => trim((string)$optionsWidget->{'friendDesc' . $i}),
                'avatar' => trim((string)$optionsWidget->{'friendAvatar' . $i}),
            ];
        }
        $friendsInit = json_encode($legacyFriends, JSON_UNESCAPED_UNICODE);
    }

    $friendsEl = new \Typecho\Widget\Helper\Form\Element\Text('friendsJson', null, $friendsInit, _t('友链管理'), _t('点击下方按钮添加友链，填写后保存即可；数据自动同步。'));
    $friendsEl->description(yingFriendsEditorHtml());
    $friendsEl->addRule($jsonListRule, _t('友链数据格式错误，请重新添加后再保存。'));
    $form->addInput($friendsEl);
}
