# Mao TK Zibll Toolkit

适用于 WordPress + Zibll 子比主题的模块化工具箱，同时提供文章写作技能 `maotk-toolkit-articles`。

**当前插件：1.13.5 · SKILL：1.3.0**

> 本仓库按作者决定公开展示源码，**暂未授予开源许可**。公开可见不等于允许任意使用、修改或再分发。第三方组件继续适用各自随附许可，详见 [权利说明](RIGHTS.md) 与 [第三方说明](THIRD_PARTY_NOTICES.md)。

## 下载与安装

前往 [Releases](https://github.com/truemaotk/mao-tk-zibll-toolkit/releases) 下载：

| 文件 | 用途 |
| --- | --- |
| `mao-tk-zibll-toolkit-1.13.5.zip` | WordPress 后台 → 插件 → 安装插件 → 上传插件 |
| `maotk-toolkit-articles-1.3.0.zip` | 本地 Codex 写作技能；保持整个技能文件夹完整 |
| `SHA256SUMS.txt` | 对应发行包的 SHA-256 校验值 |

插件最新版见 [Toolkit 1.13.5 发布页](https://github.com/truemaotk/mao-tk-zibll-toolkit/releases/tag/v1.13.5)。SKILL 1.3.0 使用独立 [技能发布页](https://github.com/truemaotk/mao-tk-zibll-toolkit/releases/tag/skill-v1.3.0)，两者分别安装与更新。技能包含 WebP／无损图片、新文章封面、Rank Math 与旧文完整保留流程；1.3.0 增加资源迁入新站、旧站引用扫描、附件与免费／付费权限验收。

GitHub 自动生成的 **Source code / Download ZIP 是整仓库源码，不是 WordPress 安装包**。请使用 Releases 中的插件 ZIP。升级前备份；已有明确关闭的模块不会被强制开启。使用与再分发权限以本仓库权利说明为准。

## 两部分怎样配合

- **Toolkit 装在网站上**：提供模块设置、实际样式与交互。
- **SKILL 供本地 AI 读取**：规定文章结构、组件调用、检查方法；不包含网站账号或发布权限。
- 标题与目录继续使用子比原生，文章不内嵌 CSS / JS。
- 下载、付费、商品、权限、视频与文章联动仍由原生功能处理。

调用示例：

```text
使用 $maotk-toolkit-articles，写一篇关于……的文章。
目标网站：……；面向读者：……；交付方式：草稿 / 已明确授权的发布。
请复用 Toolkit 样式，保留子比原生标题与目录，正文不添加 CSS 和 JS。
```

安装技能前先核对当前客户端能识别的技能目录，完整复制 `skills/maotk-toolkit-articles/`，不要只复制 SKILL.md 或重复安装同名副本。详细说明见 [技能安装与复用](skills/maotk-toolkit-articles/references/install-and-reuse.md)。

## 功能

- 模块工作台，独立启停，显示实际运行状态。
- 文章顶部信息、文章时效提示。
- 原生代码框外观、链接预览、TAB B 极简线条、文章卡片、阅读与折叠。
- 五类正文提示框，支持颜色继承、位置、本地图标和尺寸。
- 页面案例展示：后台统一维护案例和分类，页面选择模板与分类，保留主题容器。
- 私密链接可见性：后台角色多选、账号例外规则，游客始终不可见，保留原生私密标记。
- 站点维护页面：默认关闭，管理员可预览；独立设置标题、Logo、倒计时与备用入口。
- 头像与背景预设：后台集中管理，本人确认后更新；保留原生上传入口。
- 订单查询：插件提供可选页面模板，登录账号只查自己的订单，游客依赖原生有效凭据。
- 404 公益页：保留真实 404、主题页头页脚和搜索；可关闭公益信息或照片，接口异常有回退。
- 后台统一控件：原生下拉菜单、居中线性图标与轻悬停反馈，兼容触屏、键盘和减少动态效果。
- 1.6.5 起：给子比原生导航 Logo 补尺寸并保留自动宽度，为指定原生表单补自动填充语义和评论辅助框唯一 ID；不修改主题核心、文章正文或表单提交逻辑。
- 1.6.4：案例区域上下留白分别调整，默认各 10px，范围 0–200px；兼容旧配置与合法的 0。

完整组件边界见 [插件说明](plugins/mao-tk-zibll-toolkit/README.md)、[文章组件](plugins/mao-tk-zibll-toolkit/article-ui/README.md)、[案例展示](plugins/mao-tk-zibll-toolkit/showcase/README.md)。其中较早版本的“待测试”文字是当时记录；本次验收范围见 [发行说明](docs/RELEASE_NOTES.md)。

## 环境

插件声明 WordPress 6.0+、PHP 7.4+；文章 UI 需要 WordPress 6.2+，完整提示框与案例能力需要 6.3+。依赖 Zibll 的模块识别父主题目录 `zibll`，支持对应子主题。其他主题需另行适配。

当前实际站点核对组合是 **Zibll 9.2 / WordPress 7.1.4 / PHP 8.5.12**。这不代表所有版本组合均通过测试；仓库不包含子比付费主题源码。

## 源码目录

```text
plugins/mao-tk-zibll-toolkit/    WordPress 可安装插件完整生产源码
skills/maotk-toolkit-articles/  SKILL、13 份区块模板、参考说明与只读检查器
tools/build-release.ps1        从上述目录生成两个独立 ZIP
docs/                         维护入口与当前验收范围
```

仓库不包含生产数据库、站点配置、Cookie、密码、令牌、私有文章导出、浏览器资料或机器专用测试夹具。

## 教程

- [Toolkit 安装、配置与组件教程](https://www.maotk.com/mao-tk-zibll-toolkit-tutorial/)
- [文章排版 SKILL 使用教程](https://www.maotk.com/maotk-toolkit-articles-skill-guide/)
- [开发与验证](docs/DEVELOPMENT.md)

作者：[Mao TK](https://www.maotk.com/)。
