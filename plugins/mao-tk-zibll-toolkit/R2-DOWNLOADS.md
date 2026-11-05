# R2 文件上传与下载

Toolkit 工作台的「站点功能」包含「R2 文件上传与下载」。安装后模块默认关闭，配置完成后可以在工作台开启。需要 64 位 PHP、SimpleXML 和子比 9.2 的原生下载入口。

## 连接私有桶

1. 在 Cloudflare R2 的 API Tokens 中创建「Object Read & Write」凭据，范围仅选私有下载桶。记录 Account ID、Access Key ID 与 Secret Access Key。
2. 打开 WordPress「Mao TK Toolkit → R2 文件上传与下载 → 连接设置」，在「R2 上传凭据」中填入这三项和私有桶名称。
3. 在「Worker 下载签名」中填入 Worker 的 HTTPS 下载域名（例如 `https://download.maotk.com`）以及 Worker 中 `MAOTK_R2_SIGNING_KEY` 的原值。签名密钥是 64 个十六进制字符，与 R2 的 Secret Access Key 分开。
4. 保存后查看两组状态。显示「已配置」代表所需字段已读取。通过下方的小文件上传和生成引用验证实际连接。

后台仅限管理员配置。PHP OpenSSL 使用 WordPress 服务端安全密钥加密三个敏感字段。页面不会回显已保存的值。保留 WordPress 安全密钥可继续读取设置，更换后重新填写。R2 连接设置不进入 Toolkit 配置导出。

### 可选的服务器配置

旧版 `wp-config.php` 配置仍可用，已定义的常量优先于相应后台字段。新的接入直接使用后台表单即可。

~~~php
define('MAOTK_R2_ACCOUNT_ID', '<R2 Account ID>');
define('MAOTK_R2_BUCKET', 'maotk-private-downloads');
define('MAOTK_R2_ACCESS_KEY_ID', '<R2 Access Key ID>');
define('MAOTK_R2_SECRET_ACCESS_KEY', '<R2 Secret Access Key>');
define('MAOTK_R2_DOWNLOAD_ORIGIN', 'https://download.maotk.com');
define('MAOTK_R2_SIGNING_KEY', '<Worker 中保存的 64 个十六进制字符>');
~~~

在桶的 CORS Policy 中合并以下规则。AllowedOrigins 使用实际打开 WordPress 后台的协议和域名。

~~~json
[
  {
    "AllowedOrigins": ["https://www.maotk.com"],
    "AllowedMethods": ["PUT"],
    "AllowedHeaders": ["Content-Type"],
    "MaxAgeSeconds": 3600
  }
]
~~~

上传使用标准 R2 S3 端点，文件分片由浏览器直传。正式下载域名绑定 Worker，桶保持私有。

## 上传并生成引用

1. 打开 Toolkit「R2 文件上传与下载」，确认模块和连接状态，选择本机文件并开始上传。每次上传一个文件，每片 32 MiB，单文件上限 30 GiB。
2. 保持本页打开。浏览器最多同时直传三个 32 MiB 分片。单片失败会自动重试，仍失败时点「继续上传」，此前成功的分片会保留。确认阶段失败时可重试确认，已合并的文件会通过 R2 文件大小核对恢复结果。关闭或刷新页面会丢失当前页的任务状态，R2 桶的未完成上传生命周期负责清理遗留分片。
3. 上传成功后，对象键会自动填入下方。也可填入现有对象键，例如 封面.zip。输入目标文章 ID，选择下载方式并生成引用，Toolkit 会先检查文件是否存在。
4. 根据下方说明把引用填到对应位置。从已发布文章的前台下载按钮测试，每次成功授权都会签发 10 分钟有效的 Worker 地址。两种方式可以在同一篇文章中使用。

### 公开下载 · 正文卡片

1. 在 Toolkit 中为公开文件选择「公开下载 · 正文卡片」，生成并复制本站链接。
2. 粘贴到同一篇文章正文的子比「文件下载」区块，选择链接作为文件来源。卡片可直接放在正文里，也可放在 Gutenberg 的组或分栏内。更新后清理该文章的页面缓存，用无痕窗口点卡片一次。
3. 文章内已有付费资源时，沿用原执行价和子比下载设置。公开文件单独签名，公开下载不计入子比资源下载次数。

公开引用仅用于可公开分享的文件。原文按钮以同源 POST 请求发起下载。直接打开公开引用会回到文章。WordPress 会核对已发布的文章、文章阅读权限、站点、桶及卡片里的链接。文章撤下、设密码或移除卡片后，旧引用将无法再签发新地址。已经签发的地址在其浏览器内及有效期内仍可用。迁站或更换 R2 账户、下载桶时，重新生成公开引用。

### 子比原生权限

1. 在同一篇文章的「付费功能 → 付费模式 → 付费下载」中打开「资源下载」。把引用保存到对应资源的「下载地址」。同一篇文章的原生资源共用文章执行价。
2. 在正文的子比「文件下载」区块中，将文件来源选为链接，填入同一引用。如果这篇文章只配置了一份 R2 资源，也可以在这个区块的链接字段填入 `#maotk-r2-download`。以后替换文件时更新原生下载地址即可，卡片名称和大小按需修改。
3. 更新文章后清理该文章的页面缓存。用符合子比权限的账号或无痕窗口打开公开页，点正文卡片一次即可下载。需要登录或购买时，卡片带读者到同页的子比资源区。绑定缺失或引用与文章不匹配时，卡片按钮显示为不可用。

正文卡片的渲染使用子比 9.2 的下载列表与链接生成函数。下载请求仍由 `zibpay/download.php` 验证权限和额度。直连 Worker 的引用会被拒绝。

## 查找和删除文件

在页面底部的「R2 已上传文件」查看当前桶里由 Toolkit 上传的文件，包括旧版本写入的对象。点击「用此文件生成引用」可填入对象键，然后填写对应的文章 ID。

点击「从 R2 删除」后核对完整对象键，再确认永久删除。Toolkit 收到 R2 的成功响应后会移除该文件的列表项。引用该文件的文章会失去下载资源，需要在文章编辑页移除或替换引用。用其他工具上传到桶里的文件在 Cloudflare 控制台管理。

## 验收

先上传一份非敏感小文件，再上传一份超过 32 MiB 的文件验证分片。用没有购买权限的账号确认得不到临时链接，再用已购买账号确认下载。免费或会员免付费资源应沿用子比每天的额度与下载记录。同一临时链接内的下载续传请求直接由 Worker 处理。

子比 9.2 的 zibpay/download.php 在验证购买和免费额度后触发 zibpay_download_before。Toolkit 在优先级 1 校验引用和阅读权限。首次请求先建立 Worker 会话，返回后重新经过子比验证。回调通过时，子比 zibpay_updata_pay_down_mate 在优先级 10 计数并记录，Toolkit 在最后发送不缓存的 302。其他下载链接继续按原生流程处理。

两种入口都由 WordPress 签发 10 分钟有效的 `/v2/download` 地址，Worker 验证签名、有效期和自身的浏览器 Cookie。有效期内，同一浏览器可继续请求 Range。正文卡片里已保存的 1.24.0-rc.7 公开链接在显示时自动切换到前台地址。升级后清理文章缓存，已分享的后台地址可换成原文地址。

## v2 Worker 升级顺序

1. 保留 Worker 当前生产版本与插件旧安装包。在 Worker Production 设置 `MAOTK_R2_ALLOW_LEGACY=1`，上线配套 v2 Worker，并确认当前版本下载仍能工作。可设置 `MAOTK_R2_SITE_ORIGIN` 为 WordPress 的实际前台源，例如 `https://www.maotk.com`。
2. 覆盖安装 Toolkit 1.24.0-rc.9，刷新包含 R2 卡片的页面缓存。验证原文点击、游客直开公开引用、付费权限、跨浏览器复制及同一浏览器续传。
3. 删除过渡变量 `MAOTK_R2_ALLOW_LEGACY` 或设为 `0` 并部署。验证旧的 `/v1/download` 返回 403。若需回退 Toolkit，先将 Worker 切回兼容模式。

关闭模块后，两种引用停止签发。已有 R2 临时地址在自身有效期内可以继续使用。

[Cloudflare R2 API 凭据](https://developers.cloudflare.com/r2/api/tokens/) · [配置桶 CORS](https://developers.cloudflare.com/r2/buckets/cors/) · [R2 分片上传](https://developers.cloudflare.com/r2/objects/upload-objects/)
