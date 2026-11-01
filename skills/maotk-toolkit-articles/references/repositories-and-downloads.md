# 项目仓库与下载更新

用于撰写或更新下列项目的相关文章。只引用文章实际涉及的项目，不给无关文章强塞仓库链接。维护日期：2026-11-01。

## 项目对应关系

| 项目 | GitHub 仓库 / 源码 | 下载入口 | 已有教程 |
| --- | --- | --- | --- |
| Mao TK Zibll Toolkit | [仓库](https://github.com/truemaotk/mao-tk-zibll-toolkit)；插件在 `plugins/mao-tk-zibll-toolkit/` | [Releases](https://github.com/truemaotk/mao-tk-zibll-toolkit/releases) 中的独立 Toolkit 插件 ZIP | [插件教程](https://www.maotk.com/mao-tk-zibll-toolkit-tutorial/) |
| maotk-toolkit-articles | 与 Toolkit 共用上述仓库；[SKILL 目录](https://github.com/truemaotk/mao-tk-zibll-toolkit/tree/main/skills/maotk-toolkit-articles) | 同一 Releases 中的独立 SKILL ZIP | [技能教程](https://www.maotk.com/maotk-toolkit-articles-skill-guide/) |
| Order Email Manager | [独立仓库](https://github.com/truemaotk/order-email-manager)；插件在 `plugins/xt-order-email-manager/` | [Releases](https://github.com/truemaotk/order-email-manager/releases) 中的独立邮件插件 ZIP | [邮件教程](https://www.maotk.com/order-email-manager-tutorial/) |

Toolkit 装在 WordPress，提供子比文章外观；SKILL 放在本地，指导 AI 正确调用这些组件。Order Email Manager 是独立的 WooCommerce 订单付款说明邮件插件，邮件功能不依赖 Toolkit。按读者需要交叉引用对应教程，说明这种关系，不把它们写成同一个插件。

本次技能交付为 SKILL 1.2.0，独立发行标签为 `skill-v1.2.0`；Toolkit 当前核对版本为 1.7.0，Order Email Manager 上次核对为 1.3.1。**这些是带日期的核对记录，不能据此认定以后仍是最新版本。** 每次使用前必须确认对应真实公开 assets；不能只凭预定标签声称已发布。SKILL 也可能与 Toolkit 一起发包，须分别核对包内版本，不能把 Toolkit 标签误写成 SKILL 版本。

## 写入或更新下载区

1. **先读旧文并备份。** 盘点当前下载块、实际目标、附件编号、版本、文件名、大小，以及相关安装说明和 SEO 摘要。保留现有标题、封面、正文完成内容和原生业务。
2. **核对当前正式发行。** 查看对应仓库的公开 Releases、发行说明和真实 assets；默认采用适用于本篇教程的最新正式包，不把草稿、预发行或 main 分支当成正式发布。对照包内插件入口/readme、技能内容与发行说明确认版本；需要描述已安装功能时，另查目标站点实际版本。更新文章不等于自动升级网站插件。用户明确要求历史教程时，保留该版本并标清范围。
3. **检查真正的安装包。** 下载并确认不是登录页、错误页或 GitHub 自动生成的整仓库 `Source code / Download ZIP`。Toolkit ZIP 根目录应为 `mao-tk-zibll-toolkit/`，邮件插件为 `xt-order-email-manager/`；SKILL ZIP 应完整包含 `maotk-toolkit-articles/` 及其 SKILL.md、引用文档、模板和检查器。核对文件大小并计算 SHA-256；有发行校验文件时再与其比对，没有时记录本次哈希，不冒充官方校验。网站附件和 GitHub 提供同一包时，对比哈希。地址或包无法核实时，不猜测版本、asset URL 或成功状态，先保留有效入口并说明缺项。
4. **原位替换过时入口。** 通过当前站点原生 `zibllblock/download` 支持的真实附件或下载地址更新现有块，不臆造区块属性。已核实仍是同一文件且入口有效时复用，不重复上传。同一项目、同一种安装包默认只保留一个当前主下载块；不同项目或不同用途的安装包可分别提供。移除旧版本入口、重复按钮和过时直链，GitHub 发布页可作为补充来源。历史下载只有在文章明确需要时才保留并标注。**移除文章旧入口不等于永久删除媒体文件、源文件或 GitHub 历史发行版。**
5. **同步相关文案，再检查。** 当前版本、文件名、大小、安装步骤及 SEO 摘要中涉及的版本应一致；不要批量改写历史更新记录或把旧截图说成新版。加入对应仓库和实际发布页链接，区分 WordPress 插件包与本地 SKILL 包。按当次授权保存或发布后重新加载回读，检查公开页的原生下载、实际文件、GitHub 跳转、目录和窄屏布局，保存真实截图；仅编辑器显示新版本不算上线完成。

站点文章/附件编号以本次实际读取为准，不照搬历史编号。公共下载无需在文章或技能中保存登录令牌、Cookie、签名会话链接或后台凭据。已有授权时直接完成相应任务；本参考本身不授权发布文章、GitHub 发版、升级插件或永久删除文件。

## 许可表述

截至维护日，Toolkit 与 SKILL 选择源码公开展示，暂未授予整体开源许可；Order Email Manager 原包已声明 GPL-2.0-or-later，字体和图标另有随附许可。写作时核对仓库当前权利说明和包内声明，保留第三方原许可，不把“GitHub 公开”一律写成“已开源”，也不替作者选择或更改许可证。
