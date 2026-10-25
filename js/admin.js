(function () {
    'use strict';

    var GROUPS = [
        {
            key: 'font',
            eyebrow: 'TYPE',
            title: '字体',
            fields: ['fontSource', 'fontLocal', 'fontFile', 'fontUrl']
        },
        {
            key: 'masthead',
            eyebrow: 'MASTHEAD',
            title: '页头',
            fields: ['logoUrl', 'yiyanSource', 'yiyanShowFrom', 'yiyan', 'yiyanCats']
        },
        {
            key: 'surface',
            eyebrow: 'SURFACE',
            title: '页面',
            fields: ['bgUrl', 'cardAlpha']
        },
        {
            key: 'colophon',
            eyebrow: 'COLOPHON',
            title: '联系与页脚',
            fields: ['contactsJson', 'copyright', 'footerbeian', 'policebeian']
        },
        {
            key: 'links',
            eyebrow: 'LINKS',
            title: '友链',
            fields: ['linksShow', 'friendsJson']
        }
    ];

    var WIDE_FIELDS = ['logoUrl', 'yiyan', 'yiyanCats', 'contactsJson', 'friendsJson', 'policebeian'];

    function formatBytes(bytes) {
        if (bytes >= 1048576) {
            return (bytes / 1048576).toFixed(1) + ' MB';
        }
        if (bytes >= 1024) {
            return Math.round(bytes / 1024) + ' KB';
        }
        return bytes + ' B';
    }

    function boot() {
        var config = window.yingAdminConfig || {};
        var form = document.querySelector('form.ying-settings');
        if (!form) {
            return;
        }

        form.classList.add('ying-settings-enhanced');

        var col = form.closest('[role="form"]') || form.parentElement;
        if (col) {
            col.classList.add('ying-settings-col');
        }
        var page = form.closest('.typecho-page-main');
        if (page) {
            page.classList.add('ying-settings-page');
        }

        var submit = form.querySelector('input[type="submit"], button[type="submit"]');
        var saveBar = submit ? submit.closest('ul.typecho-option') : null;
        if (saveBar) {
            saveBar.classList.add('ying-savebar');
        }

        var sourceInputs = Array.prototype.slice.call(form.querySelectorAll('input[name="fontSource"]'));
        var localRadios = Array.prototype.slice.call(form.querySelectorAll('input[name="fontLocal"][type="radio"]'));
        var hiddenLocal = form.querySelector('input[name="fontLocal"]:not([type="radio"])');
        var urlInput = form.querySelector('input[name="fontUrl"]');
        var urlField = form.querySelector('[data-ying-field="url"]');
        var sourceField = form.querySelector('[data-ying-field="source"]');
        var statusNode = form.querySelector('[data-ying-status]');
        var fileInput = form.querySelector('#ying-font-file');
        var drop = form.querySelector('.ying-drop');
        var dropFile = form.querySelector('.ying-drop-file');
        var libraryNote = form.querySelector('[data-library-note]');
        var deleteInputs = Array.prototype.slice.call(form.querySelectorAll('input[name="fontDelete[]"]'));

        function findField(name) {
            var input = form.querySelector(
                'input[name="' + name + '"], input[name="' + name + '[]"], ' +
                'textarea[name="' + name + '"], select[name="' + name + '"]'
            );
            return input ? input.closest('ul.typecho-option') : null;
        }

        function isSkinField(field) {
            return field.style.display === 'none' || !!field.querySelector('input[name="yingAdminSkin"]');
        }

        // Group the flat Typecho option list into readable sections.
        var grouped = [];
        var sections = [];
        GROUPS.forEach(function (group) {
            var section = document.createElement('section');
            section.className = 'ying-section ying-section-' + group.key;
            section.innerHTML = '<header class="ying-section-head">'
                + '<div class="ying-section-heading">'
                + '<p class="ying-section-eyebrow">' + group.eyebrow + '</p>'
                + '<h2 class="ying-section-title">' + group.title + '</h2>'
                + '</div>'
                + '</header>';
            var fieldsWrap = document.createElement('div');
            fieldsWrap.className = 'ying-fields';

            group.fields.forEach(function (name) {
                var field = findField(name);
                if (!field || grouped.indexOf(field) !== -1) {
                    return;
                }
                if (WIDE_FIELDS.indexOf(name) !== -1 || field.querySelector('textarea')) {
                    field.classList.add('ying-field-wide');
                }
                fieldsWrap.appendChild(field);
                grouped.push(field);
            });

            if (fieldsWrap.children.length === 0) {
                return;
            }
            section.appendChild(fieldsWrap);
            sections.push(section);
        });

        var directFields = Array.prototype.slice.call(form.children).filter(function (child) {
            return child.matches && child.matches('ul.typecho-option');
        });
        var leftovers = directFields.filter(function (field) {
            return grouped.indexOf(field) === -1 && field !== saveBar && !isSkinField(field);
        });
        if (leftovers.length) {
            var more = document.createElement('section');
            more.className = 'ying-section ying-section-more';
            more.innerHTML = '<header class="ying-section-head">'
                + '<div class="ying-section-heading">'
                + '<p class="ying-section-eyebrow">MORE</p>'
                + '<h2 class="ying-section-title">更多设置</h2>'
                + '</div>'
                + '</header>';
            var moreFields = document.createElement('div');
            moreFields.className = 'ying-fields';
            leftovers.forEach(function (field) {
                if (field.querySelector('textarea')) {
                    field.classList.add('ying-field-wide');
                }
                moreFields.appendChild(field);
            });
            more.appendChild(moreFields);
            sections.push(more);
        }

        var anchor = saveBar || null;
        sections.forEach(function (section) {
            form.insertBefore(section, anchor);
        });
        if (saveBar) {
            form.appendChild(saveBar);
        }

        // 友链页开关与友链名单同宽排布，避免单独占半列留下空白。
        var linksField = findField('linksShow');
        if (linksField) {
            var linksLi = linksField.firstElementChild;
            if (linksLi && linksLi.tagName === 'LI') {
                var linksLabel = null;
                var linksDesc = null;
                var linksOptions = [];
                Array.prototype.forEach.call(linksLi.children, function (child) {
                    if (child.matches('label.typecho-label')) {
                        linksLabel = child;
                    } else if (child.matches('p.description')) {
                        linksDesc = child;
                    } else if (child.matches('span.multiline')) {
                        linksOptions.push(child);
                    }
                });
                if (linksOptions.length) {
                    var linksHead = document.createElement('div');
                    linksHead.className = 'ying-inline-head';
                    if (linksLabel) {
                        linksHead.appendChild(linksLabel);
                    }
                    var linksControls = document.createElement('div');
                    linksControls.className = 'ying-inline-options';
                    linksOptions.forEach(function (option) {
                        linksControls.appendChild(option);
                    });
                    linksHead.appendChild(linksControls);
                    linksLi.insertBefore(linksHead, linksLi.firstChild);
                    if (linksDesc) {
                        linksLi.appendChild(linksDesc);
                    }
                    linksField.classList.add('ying-field-inline');
                }
            }
        }

        if (sourceField) {
            var sourceLi = sourceField.firstElementChild;
            if (sourceLi && sourceLi.tagName === 'LI') {
                sourceLi.classList.add('ying-source-field');
            }
        }

        var saveNote = null;
        if (saveBar) {
            saveNote = document.createElement('span');
            saveNote.className = 'ying-save-note';
            saveNote.textContent = '修改后点击保存；字体文件独立存放，不进入主题包。';
            if (submit && submit.parentNode) {
                submit.parentNode.insertBefore(saveNote, submit);
            } else {
                saveBar.appendChild(saveNote);
            }
        }

        var dirty = false;

        function currentSource() {
            for (var i = 0; i < sourceInputs.length; i++) {
                if (sourceInputs[i].checked) {
                    return sourceInputs[i].value;
                }
            }
            return config.source || 'local';
        }

        function currentLocal() {
            for (var i = 0; i < localRadios.length; i++) {
                if (localRadios[i].checked) {
                    return localRadios[i].value;
                }
            }
            return localRadios.length ? localRadios[0].value : '';
        }

        function libraryEntry(file) {
            var library = config.library || [];
            for (var i = 0; i < library.length; i++) {
                if (library[i].file === file) {
                    return library[i];
                }
            }
            return null;
        }

        function validCustomUrl(url) {
            return /^(https?:\/\/|\/)[^\s<>"']+$/i.test(url);
        }

        function statusFor(source) {
            if (source === 'none') {
                return '当前使用：系统字体（不加载字体文件）';
            }
            if (source === 'harmony') {
                return '当前使用：HarmonyOS CDN';
            }
            if (source === 'custom') {
                var raw = urlInput ? urlInput.value.trim() : String(config.customUrl || '');
                return validCustomUrl(raw)
                    ? '当前使用：自定义地址'
                    : '当前使用：系统字体（字体地址无效）';
            }
            var selected = currentLocal();
            var entry = libraryEntry(selected);
            return entry
                ? '当前使用：' + entry.name + ' · 本地字体'
                : '当前使用：系统字体（尚未选择本地字体）';
        }

        function updateSaveNote() {
            if (!saveNote) {
                return;
            }
            var pending = deleteInputs.filter(function (input) {
                return input.checked;
            }).length;
            if (pending > 0) {
                saveNote.textContent = '保存后将删除 ' + pending + ' 个字体文件。';
            } else if (dirty) {
                saveNote.textContent = '有未保存的修改，点击保存生效。';
            } else {
                saveNote.textContent = '修改后点击保存；字体文件独立存放，不进入主题包。';
            }
        }

        function markDirty() {
            if (!dirty) {
                dirty = true;
                if (saveBar) {
                    saveBar.classList.add('is-dirty');
                }
            }
            updateSaveNote();
        }

        function syncSource() {
            var source = currentSource();
            form.dataset.fontSource = source;

            if (urlField) {
                urlField.hidden = source !== 'custom';
            }
            if (urlInput) {
                urlInput.required = source === 'custom';
            }
            if (libraryNote) {
                libraryNote.textContent = source === 'local'
                    ? '选择卡片切换字体并预览；删除需要保存后生效。'
                    : '本地字体当前未启用；选择卡片会切换到本地字体。';
            }

            var selected = currentLocal();
            if (selected && hiddenLocal) {
                hiddenLocal.value = selected;
            }

            localRadios.forEach(function (input) {
                var card = input.closest('.ying-font-card');
                if (card) {
                    card.classList.toggle('is-active', input.checked);
                }
            });

            if (statusNode) {
                statusNode.textContent = statusFor(source);
            }
        }

        // Initial local selection: keep the hidden value and the visible card in sync.
        if (hiddenLocal) {
            var initialLocal = currentLocal();
            if (!initialLocal && localRadios.length) {
                var wanted = hiddenLocal.value;
                var matched = null;
                localRadios.forEach(function (input) {
                    if (input.value === wanted) {
                        matched = input;
                    }
                });
                (matched || localRadios[0]).checked = true;
                initialLocal = currentLocal();
            }
            if (initialLocal) {
                hiddenLocal.value = initialLocal;
            }
        }

        function updateDeleteUi(input) {
            var card = input.closest('.ying-font-card');
            var label = input.closest('.ying-font-remove');
            if (card) {
                card.classList.toggle('is-pending-delete', input.checked);
            }
            if (label) {
                label.classList.toggle('is-checked', input.checked);
            }
        }

        deleteInputs.forEach(function (input) {
            updateDeleteUi(input);
            input.addEventListener('change', function () {
                updateDeleteUi(input);
                updateSaveNote();
            });
        });

        sourceInputs.forEach(function (input) {
            input.addEventListener('change', syncSource);
        });

        localRadios.forEach(function (input) {
            input.addEventListener('change', function () {
                if (hiddenLocal) {
                    hiddenLocal.value = input.value;
                }
                sourceInputs.forEach(function (sourceInput) {
                    if (sourceInput.value === 'local') {
                        sourceInput.checked = true;
                    }
                });
                syncSource();
            });
        });

        if (urlInput) {
            var syncCustomStatus = function () {
                if (currentSource() === 'custom' && statusNode) {
                    statusNode.textContent = statusFor('custom');
                }
            };
            urlInput.addEventListener('input', syncCustomStatus);
            urlInput.addEventListener('change', syncCustomStatus);
        }

        function showFileError(message) {
            if (!drop) {
                return;
            }
            drop.classList.remove('has-file');
            drop.classList.add('is-error');
            if (dropFile) {
                dropFile.textContent = message;
                dropFile.dataset.empty = '0';
            }
        }

        function handleFile() {
            if (!fileInput || !drop) {
                return;
            }
            var file = fileInput.files && fileInput.files[0];
            drop.classList.remove('is-error');

            if (!file) {
                drop.classList.remove('has-file');
                if (dropFile) {
                    dropFile.textContent = '未选择文件';
                    dropFile.dataset.empty = '1';
                }
                return;
            }

            if (!/\.(woff2|woff|ttf|otf)$/i.test(file.name)) {
                showFileError('仅支持 WOFF2 / WOFF / TTF / OTF');
                fileInput.value = '';
                return;
            }

            if (config.uploadLimit && file.size > config.uploadLimit) {
                showFileError('文件超过上传上限');
                fileInput.value = '';
                return;
            }

            drop.classList.add('has-file');
            if (dropFile) {
                dropFile.textContent = file.name + ' · ' + formatBytes(file.size);
                dropFile.dataset.empty = '0';
            }
            markDirty();
        }

        if (fileInput) {
            fileInput.addEventListener('change', handleFile);
        }

        if (drop) {
            ['dragenter', 'dragover'].forEach(function (eventName) {
                drop.addEventListener(eventName, function (event) {
                    event.preventDefault();
                    drop.classList.add('is-dragover');
                });
            });
            ['dragleave', 'dragend'].forEach(function (eventName) {
                drop.addEventListener(eventName, function () {
                    drop.classList.remove('is-dragover');
                });
            });
            drop.addEventListener('drop', function (event) {
                event.preventDefault();
                drop.classList.remove('is-dragover');
                if (!fileInput || !event.dataTransfer || !event.dataTransfer.files.length) {
                    return;
                }
                if (event.dataTransfer.files.length > 1) {
                    showFileError('一次只能上传一个字体');
                    return;
                }
                try {
                    fileInput.files = event.dataTransfer.files;
                    handleFile();
                } catch (error) {
                    showFileError('请点击选择字体文件');
                }
            });
        }

        form.addEventListener('input', markDirty);
        form.addEventListener('change', markDirty);
        form.addEventListener('submit', function () {
            if (saveBar) {
                saveBar.classList.remove('is-dirty');
            }
            updateSaveNote();
        });

        syncSource();

        if (deleteInputs.some(function (input) { return input.checked; })) {
            dirty = true;
            if (saveBar) {
                saveBar.classList.add('is-dirty');
            }
        }
        updateSaveNote();
    }

    if (document.readyState === 'loading') {
        document.addEventListener('DOMContentLoaded', boot);
    } else {
        boot();
    }
})();
