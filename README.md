# YingK

> 一个安静、克制的 [Typecho](https://typecho.org/) 单栏博客主题。

## 快速开始

前置：已完成 Typecho 安装；本主题不包含 Typecho 本体。Typecho 1.2.1 及以上（1.3.0 实测），PHP 8.x，数据库使用 Typecho 支持的 MySQL / MariaDB / SQLite / PostgreSQL。

```bash
cd usr/themes
git clone https://github.com/yiktt/YingK.git YingK
```

没有 shell 环境时，下载 [ZIP](https://github.com/yiktt/YingK/archive/refs/heads/main.zip) 解压到 `usr/themes/YingK`。随后：

1. 后台「控制台 → 外观」启用 **YingK**，在「设置外观」配置字体、头像、一言、置顶、关于页、联系方式等。
2. 新建独立页面并选择模板：归档 `articles.php`、友链 `links.php`、关于 `about.php`；普通页面用默认模板。

> 主题设置按目录名保存，修改主题文件夹名称后需要重新配置。关于页正文完全由「设置外观」维护，页面编辑器正文可留空。

> [!WARNING]
> 使用 SQLite 时，`usr/typecho.db` 位于 Web 根目录，必须禁止外部访问；Nginx / Apache 配置见[部署安全提示](docs/configuration.md#部署安全提示)。

字段说明、默认值、字体与模板细节见 **[配置说明](docs/configuration.md)**。

## 功能

- **阅读优先**：600px 单栏、玻璃卡片与留白；字体可选 ZQL CDN、站点本地、系统、鸿蒙 CDN 或自定义地址。
- **置顶文章**：后台填写文章 ID / 别名，最多 3 篇；首页第一页以朱砂竖线串联，第一篇显示「置顶」小印；加密文章自动跳过。
- **夜间模式**：跟随系统偏好并记忆选择，首屏无闪白；移动端工具坞收进页头菜单。
- **AJAX 局部加载**：站内链接与搜索只替换正文，前进/后退恢复滚动与焦点，异常自动回退整页。
- **一言与社交**：固定内容或 API，可选出处与位置；友链、联系方式可在后台增删，友链页可整体隐藏。
- **文章页**：中文日期、浏览计数、字数、标签、代码高亮与复制、回到顶部/底部。
- **密码文章**：锁定态沿用「编年」版式，隐藏标题、日期、作者等元信息；首页与归档页显示真实标题并标注「密」；口令错误同页内联提示。可选启用主题的 `error.php` 接管核心 403/5xx 页面。
- **关于页**：「站点档案 · 摘录笺」版式，含统计卡、站点足迹与文章活跃热力图；导语与自定义板块支持 Markdown，内容板块可在后台新增、改名。
- **后台可调**：七组外观设置，涵盖置顶、统计、标记、背景、字体、透明度、页脚与备案号等。
- **安全与隐私**：输出转义与协议白名单，关于页 Markdown 白名单清洗，锁文章 Cookie 使用 HttpOnly / SameSite=Lax，浏览量不存 IP。细节见[配置说明](docs/configuration.md)。

## 兼容与性能

- 已实测默认 `cid` / `slug` / 日期 / 分类固定链接；归档页只查询必要字段，不装载正文。
- 无构建步骤、无运行时框架；无 JS 时页面与正文链接仍可读，键盘、焦点、减少动态效果等可访问性支持保留。
- 主题目录约 7.8MB（含字体约 6.7MB）；默认 ZQL 字体按 `unicode-range` 分片按需加载，本地字体模式只加载选中文件，系统字体零下载。代码高亮按需加载。

## 开发

无构建步骤，直接修改 PHP、`css/main.css`、`js/app.js` 即可；升级或迁移时保持主题目录名不变，外观设置保存在 Typecho 数据库中。最近一次验证在 Typecho 1.3.0 + PHP 8.3.36 + SQLite 上完成，覆盖设置保存、锁定文章、固定链接、AJAX、关于页与响应式；Typecho 1.2.1 仅做源码对照。

维护者：[@yiktt](https://github.com/yiktt) · [GitHub Issues](https://github.com/yiktt/YingK/issues)。

## 原主题

原主题 **Ying** 归 [heysiu/Ying](https://github.com/heysiu/Ying)，原仓库未声明许可证；原始设计与素材版权归原作者及贡献者，闭源或商业再分发前请先取得上游授权。

## 许可

YingK 的新增/修改代码以 **MIT** 发布，详见 [LICENSE](LICENSE)。默认字体 ZQL（走路带风小可爱简 Regular 子集，`assets/fonts/zql-c1.woff2` 等 3 个分片）随仓库分发并经 jsDelivr 加载，字体站标注商用须授权；可选 HarmonyOS Sans SC Medium 受 HarmonyOS Fonts License Agreement 约束。后台上传字体由站点管理员自行提供，保存在 `usr/uploads/fonts`，不属于本仓库。
