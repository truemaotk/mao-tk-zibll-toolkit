# 子比原生商品导入／导出

Toolkit `1.18.0-rc.1`，本地验收版。入口：Toolkit → 商品导入／导出。

模块 `data-migration-products` 默认启用；仅在后台商品迁移页加载资源。友情链接与文章模块的源码、任务格式、任务索引和控件基线保持独立。

## 使用顺序

1. 源站与目标站都使用子比原生商城，并开启本模块。当前核对版本为 Zibll 9.2；支持其子主题，未适配版本会停止操作。
2. 在“导出商品”选择商品分类、状态、日期，或输入商品 ID。一次最多 100 件，价格、规格、库存、发货配置、正文和本地图片一同导出。
3. 等任务结束后下载完整 ZIP。部分失败时明确显示失败项，只提供成功部分包；可补齐图片后重试。库存是创建导出任务时的快照，不是实时增量同步。
4. 目标站“导入商品”上传 ZIP，先查看预检计划。预检不新增商品、媒体或分类。默认跳过已有商品；必须明确选择更新，才允许覆盖已有草稿。
5. 点击“确认并开始”后逐张处理图片、逐件处理商品，再核对关联。可暂停、刷新、从任务记录恢复或重试失败项，每页 20 条。
6. 所有导入结果均为草稿。检查图片、价格、规格、库存、发货内容、分类继承、会员／积分相关功能及视频和下载地址，再在子比原生编辑器自行发布。

已有在售、私密、定时、待审核和回收站商品不允许覆盖。预检后的正文、SEO、库存、分类或其他配置变化会拒绝该项；覆盖前有原商品快照可随报告下载。分类仅新增，不修改已有分类配置或父级；冲突需要先核对后重新预检。

## 数据边界

| 数据 | 处理方式 |
| --- | --- |
| 原生商品 | 仅 `shop_product`，不含普通文章、WooCommerce 商品或付费阅读文章 |
| 商品配置 | 使用 `product_config`，保留规格数字键及 `\|组_值` 库存／发货组合键；未知顶级字段拒绝，不静默丢弃 |
| 商品分类与标签 | `shop_cat`、`shop_tag`，保留父级及新增分类的 `shop_cat_config`、`shop_tag_config` |
| 价格与支付索引 | 原生保存及其 `added_post_meta`／`updated_post_meta` 回调同步 `zibpay_price`、`zibpay_modo`，写后校验 |
| 图片 | 图集、主图、规格图、正文、自定义 TAB 和已识别配置图片；按原文件 SHA-256 去重并重建附件映射，不缩图或重新压缩原文件 |
| SEO | Rank Math 标题、描述、关键词、robots、canonical 和社交标题／描述／图片白名单；已知商品网址和图片映射到目标对象 |
| 商品关联 | 已知 HTML 媒体属性、核心图片块和本批 `[productbox]` 引用映射；其他扩展 ID 不猜测 |
| 视频、外部图片、下载实体 | 保留链接，不抓取外部文件；源站本地视频和下载文件仍需另行迁移 |
| 自动发货 | 固定文案和配置标识保留；不迁移实际卡密池、优惠码、邀请码或自动创建的记录。目标站须重新核对标识及库存来源 |
| 其他 | 不迁移订单、购买权益、客户、收货资料、销量、评价、优惠活动、站点配置和支付密钥 |

商品配置、分类配置与主题配置存在继承。此模块不覆盖目标站主题选项，不保证两站全局配置不同时最终售价、限购或服务描述完全一致。优惠活动也不自动绑定；导入后先检查草稿。

## 权限与文件

- 管理员、编辑他人内容及分类权限；导入原始 HTML 另需 `unfiltered_html`。媒体写入检查上传权限；写入口还检查 nonce、模块状态及环境。
- 格式为 `maotk-zibll-products` 版本 1，契约标记 `zibll-shop-9.2-v1`。不接受文章、友情链接或 WooCommerce ZIP。
- ZIP 最大 256 MiB，清单 16 MiB，展开 512 MiB；单图 8 MiB，总导出图片 200 MiB；每包 100 件，最多 512 个规格组合。
- 私有任务文件位于站外可写目录。必要时在 `wp-config.php` 设置 `MAO_TK_TOOLKIT_MIGRATION_TEMP` 为站外目录；不允许站内公开目录、任意路径或符号链接。
- 私有收件目录路径在导入页显示；大 ZIP 可先放到该目录，再在后台选择。浏览器上传仍受 PHP 上传上限影响。
- 商品任务前缀 `mao_tk_product_migration_`；有效期 24 小时，当前账号最近 20 个任务。过期清理在该账号后续访问任务时执行，并非后台定时清除承诺。
- 报告与迁移包可能包含固定发货文案和私有链接，按私有备份保存。备份不是一键回滚，失败后不自动删除已创建的媒体、分类或草稿。

## 源码契约与维护

基于用户提供的 `zibll-V9.2(1651).zip` 核对，SHA-256：`8ebd65b60287438224b340513580a0478400731f80a60cb6f90a775e84a8caa6`。主题源码不包含在 Toolkit 包内。

| 核对位置（相对子比目录） | 已使用的事实 |
| --- | --- |
| `inc/functions/shop/inc/class.init.php:361` | `register_post_type()` 注册 `shop_product`、`shop_cat`、`shop_discount`、`shop_tag`；本模块不复制优惠活动 |
| `inc/functions/shop/admin/options/meta-option.php:22` | CSF 序列化商品配置键 `product_config`；图集为附件 CSV，规格选项保存原始索引 |
| `inc/functions/shop/admin/options/term-option.php:24` | 分类／标签配置分别为 `shop_cat_config`、`shop_tag_config` |
| `inc/functions/shop/inc/product.php:643` | `zib_shop_get_product_config($post_id,$key=null,$default='')` 读取并缓存配置 |
| `inc/functions/shop/inc/product.php:664` | `zib_shop_update_postmeta_add_action($meta_id,$object_id,$meta_key,$meta_value)`；metadata 两个 action 优先级 99、4 参数，同步请求缓存和支付价格索引 |
| `inc/functions/shop/inc/product.php:688` | `zib_shop_save_product_config($post_id,$key,$value)`；传入整体配置时 `key=null`，内部走 WordPress metadata，调用方处理斜杠并清除 no-op 请求缓存 |
| `inc/functions/shop/inc/product.php:795` | `zib_shop_product_options_key_splicing()` 生成 `\|组_值`，迁移不重新排序或生成键 |

官方功能边界可参照[子比商品发布教程](https://www.zibll.com/40734.html)。字段适配以本次实际源码核对为准。

本地验证使用 WordPress、SQLite 和用户主题中真实商品 API／商品类型注册代码；未执行完整主题启动、授权、商城前台、真实交易或 MySQL/MariaDB 集成验收。上线前应在真实子比测试站复核，本验收版不声称没有潜在缺陷。
