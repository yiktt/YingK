# YingK

> 一个安静、克制的 Typecho 单栏博客主题。让页头保持不动，让文字成为主角。

[![Typecho 1.2.1+](https://img.shields.io/badge/Typecho-1.2.1%2B-3b82f6?style=flat-square)](https://typecho.org/)
[![PHP 8.x](https://img.shields.io/badge/PHP-8.x-777bb4?style=flat-square)](https://www.php.net/)
[![License: MIT additions](https://img.shields.io/badge/license-MIT%20additions-green?style=flat-square)](LICENSE)

YingK 基于 [heysiu/Ying](https://github.com/heysiu/Ying) 二次开发，重做了移动端布局、局部加载、主题设置与阅读细节。静态资源全部本地托管：无 jQuery、无外部 CDN；只有可选的一言 API 模式会访问 `v1.hitokoto.cn`。

> **状态**：个人维护 · Typecho 1.2.1+（1.3.0 实测）· 最近验证 2026-10
>
> **刻意不做**：评论、侧栏、用户系统
>
> **许可边界**：本仓库新增/修改代码为 MIT；上游 Ying 未声明许可证，商业或闭源再分发前请先确认上游授权。

相对上游 Ying，YingK 重做了移动端页头、局部加载、外观设置、友链/联系方式编辑器、代码高亮与 404；上游的评论功能没有保留。

## 预览

截图使用 Typecho 1.3.0 + PHP 8.3 实际渲染，内容为演示文章。

> 根目录 `screenshot.png` 同时作为 Typecho 后台主题缩略图；其余截图位于 `docs/`。

### 首页

![YingK 首页：头像、一言、导航与文章列表](docs/preview-home.png)

### 文章页

| 白天 | 夜间 |
| --- | --- |
| ![YingK 文章页白天模式](screenshot.png) | ![YingK 文章页夜间模式](docs/preview-dark.png) |

## 为什么是 YingK

YingK 不是功能型 CMS 主题，它把预算放在“读”这件事上：

- **单栏、无评论、无侧栏**：阅读时没有侧栏、评论区和用户系统打断。
- **页头不动，内容局部加载**：站内跳转只替换内容区，前进/后退恢复滚动位置，焦点回到新页面标题。
- **资源自托管**：无 jQuery、无 CDN；图标、代码高亮与字体随主题走，不依赖第三方静态资源。
- **配置克制**：头像、一言、背景、卡片透明度、联系方式、友链、备案都在后台完成，不引入页面构建器。

**适合**：想要安静阅读、愿意自己维护 Typecho 的个人站点。

**不适合**：需要评论、侧栏、复杂页面构建器或多人协作的站点。

**代价**：完整的 HarmonyOS Sans SC Medium 字体约 5.4MB，首屏资源约 5.9MB；`font-display: swap` 不阻塞文字显示。具体见[兼容性与性能](#兼容性与性能)。

## 快速开始

> 前置：先完成 Typecho 安装（[下载](https://typecho.org/download) / [文档](https://docs.typecho.org/)）；本主题不包含 Typecho 本体，数据库与 PHP 扩展按 Typecho 要求配置。

| 要求 | 说明 |
| --- | --- |
| Typecho | 1.2.1 及以上；1.3.0 实测，1.2.1 仅做 API 源码对照 |
| PHP | 8.x |
| 数据库 | Typecho 支持的 MySQL / MariaDB / SQLite / PostgreSQL |
| 浏览器 | Chrome、Edge、Firefox、Safari 等现代浏览器，不支持 IE |
| 可选网络 | 一言 API 模式需要访问 `v1.hitokoto.cn` |

```bash
cd usr/themes
git clone https://github.com/yiktt/YingK.git YingK
```

没有 shell 环境时，也可以下载 [ZIP](https://github.com/yiktt/YingK/archive/refs/heads/main.zip) 并解压到 `usr/themes/YingK`。

1. 后台「控制台 → 外观」启用 **YingK**。
2. 进入「控制台 → 外观 → 设置外观」，配置头像、一言、背景图、联系方式、友链等。
3. 新建独立页面，并在「自定义模板」中选择对应模板：

| 页面 | 模板文件 |
| --- | --- |
| 归档 | `articles.php` |
| 友链 | `links.php` |
| 关于 | `about.php` |
| 普通页面 | 默认模板 |

> 主题设置按目录名保存。如果启用后修改主题文件夹名称，需要重新配置外观设置。

更完整的字段、页面模板和运行细节见 **[配置说明](docs/configuration.md)**。

## 功能

- **阅读优先**：600px 单栏、HarmonyOS Sans SC Medium、玻璃卡片与留白。
- **夜间模式**：跟随系统偏好、localStorage 记忆、右上工具坞切换，首屏无闪白。
- **AJAX 局部加载**：页头不动；前进/后退恢复滚动，焦点回到新页面标题，异常自动回退整页。
- **一言**：固定内容或 API；分类与出处可选；3 秒超时回退。
- **友链与联系方式**：后台可视化增删，兼容旧字段；友链页可整体隐藏。
- **文章页**：中文日期、浏览计数、字数、标签、代码高亮与复制、回到顶部/底部。
- **后台可调**：内置头像、背景图、卡片透明度、页脚版权、ICP / 公安备案号。
- **页面**：归档、友链、关于、404；归档链接适配 Typecho 自定义固定链接。
- **安全与隐私**：输出转义、协议白名单、浏览量不存储 IP、外链 `rel="noopener"`。

## 兼容性与性能

- **固定链接**：默认 `/archives/{cid}/`、`/archives/{slug}.html`、日期归档、分类归档均已实测；归档页使用 Typecho 内容组件生成链接，不依赖手动拼接。
- **资源**：无构建步骤、无运行时框架。字体约 5.4MB，首屏合计约 5.9MB；文章含代码时额外按需加载约 127KB 的 highlight.js。
- **字体**：正文与一言使用主题内置 HarmonyOS Sans SC Medium（woff，`font-display: swap`）；RemixIcon 仅保留 woff2。
- **无 JS**：页面与正文链接可读，“返回列表”链接可用；主题切换、搜索面板、代码高亮/复制与 AJAX 局部加载不可用。
- **可访问性**：支持键盘焦点、`aria-current`、`aria-pressed`、`aria-expanded`、减少动态效果偏好；主要触控目标 44px；已移除禁止缩放的 viewport 设置。
- **响应式**：390 / 752 / 1000 / 1200 宽度与浅色/暗色均已回归验证。

## 常见问题

**为什么没有评论？**
上游 Ying 的定位就是无评论阅读主题，YingK 保留这一取舍，不包含 `comments.php`。

**首页/文章页的字体为什么这么大？**
正文使用完整的 HarmonyOS Sans SC Medium（约 5.4MB），以保证中文字形覆盖；`font-display: swap` 让文字先显示。这是选择完整字体的直接代价，主题不会自动压缩或按需切片字体。

**友链 / 归档 / 关于页怎么建？**
按[快速开始](#快速开始)中的模板表创建独立页面；友链数据在外观设置里维护，详见[配置说明](docs/configuration.md)。

**浏览量为什么比统计工具少？**
浏览量按浏览器 Cookie 24 小时去重，且不过滤爬虫；清 Cookie 会重新计数。它用于展示文章热度，不等同于独立访客统计，详见[配置说明](docs/configuration.md#浏览量)。

**切换页面后某些脚本没执行？**
主题检测到内容区含脚本会自动整页加载。插件脚本或特殊链接可加 `data-no-ajax` 强制整页打开，详见[配置说明](docs/configuration.md#ajax-与渐进增强)。

## 开发与验证

- 无构建步骤：直接修改 PHP、`css/main.css`、`js/app.js` 即可。
- 升级：保持主题目录名不变，拉取或覆盖主题文件即可；外观设置与文章数据保存在 Typecho 数据库中。
- 本项目不单独维护发布编号；变更以 Git 提交记录为准。
- 本次验证在 **Typecho 1.3.0 + PHP 8.3.36 + SQLite** 上完成：PHP 语法检查、设置页保存/JSON 校验、旧标记清理、Cookie 去重、默认 cid / slug / 日期 / 分类固定链接、定时文章过滤、AJAX 前进后退与脚本回退、404、搜索与多宽度渲染均有实测。
- Typecho 1.2.1 的命名空间 API 与 1.3.0 源码对照通过；1.2.1 未做运行时回归。
- 问题与建议：[GitHub Issues](https://github.com/yiktt/YingK/issues)。

## 许可

本仓库中 YingK 的修改与新增代码以 **MIT** 协议发布，详见 [LICENSE](LICENSE)。

原主题 **Ying** 的归属以上游仓库为准：[heysiu/Ying](https://github.com/heysiu/Ying)。该仓库所有者为 [heysiu](https://github.com/heysiu)（GitHub 资料名 Siu），上游 [README](https://github.com/heysiu/Ying/blob/main/README.md#L11) 鸣谢油佬。原仓库未声明许可证。本项目保留上游仓库信息，原始主题的设计与素材版权归其原作者及贡献者所有；如需将原主题部分用于闭源或商业再分发，请先取得上游作者的明确授权。

## 致谢

- 原主题：[heysiu/Ying](https://github.com/heysiu/Ying)，上游 README 鸣谢油佬
- 字体：HarmonyOS Sans SC Medium（Copyright Huawei Device Co., Ltd.，HarmonyOS Fonts License Agreement）
- 图标：[Remix Icon](https://remixicon.com/)（Remix Icon License 1.0）
- 代码高亮：[highlight.js](https://highlightjs.org/) v11.11.1（BSD-3-Clause）
- 博客程序：[Typecho](https://typecho.org/)
