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
3. 上传成功后，对象键会自动填入下方。也可填入现有对象键，例如 封面.zip。输入目标文章 ID 并生成引用，Toolkit 会先检查文件是否存在。
4. 点击「复制下载引用」，粘贴到同一篇文章的子比「下载链接」字段。从已发布文章的前台下载按钮测试。由子比的免费、登录或付费规则决定读者权限。每次授权点击都会签发 10 分钟有效的 Worker 地址。引用绑定文章 ID 和站点盐值，变更文章 ID、站点盐值或下载域名后需要重新生成。

## 查找和删除文件

在页面底部的「R2 已上传文件」查看当前桶里由 Toolkit 上传的文件，包括旧版本写入的对象。点击「用此文件生成引用」可填入对象键，然后填写对应的文章 ID。

点击「从 R2 删除」后核对完整对象键，再确认永久删除。Toolkit 收到 R2 的成功响应后会移除该文件的列表项。引用该文件的文章会失去下载资源，需要在文章编辑页移除或替换引用。用其他工具上传到桶里的文件在 Cloudflare 控制台管理。

## 验收

先上传一份非敏感小文件，再上传一份超过 32 MiB 的文件验证分片。用没有购买权限的账号确认得不到临时链接，再用已购买账号确认下载。免费或会员免付费资源应沿用子比每天的额度与下载记录。同一临时链接内的下载续传请求直接由 Worker 处理。

子比 9.2 的 zibpay/download.php 在验证购买和免费额度后触发 zibpay_download_before。Toolkit 在优先级 1 校验引用、文章阅读权限并准备签名。子比 zibpay_updata_pay_down_mate 在优先级 10 计数并记录，Toolkit 在最后发送不缓存的 302。其他下载链接继续按原生流程处理。

Toolkit 的上传和下载引用目前使用原生文章下载字段。关闭模块时，现有 R2 引用会由 Worker 拒绝。已有 R2 临时地址在自身有效期内可以继续使用。

[Cloudflare R2 API 凭据](https://developers.cloudflare.com/r2/api/tokens/) · [配置桶 CORS](https://developers.cloudflare.com/r2/buckets/cors/) · [R2 分片上传](https://developers.cloudflare.com/r2/objects/upload-objects/)
