# 复杂内容字段

本轮沿用已批准的二期动态字段方案，编辑状态与持久化/公开输出分开。所有 resolver 使用小写名称；定义由 Page/Menu 数据库提供。旧 Nova 包只读，无新增运行时依赖。

## 富文本

`rich_text` 使用 Filament RichEditor，持久化 HTML。未改动的 HTML，或仅经编辑器等价规范化的 HTML，保留原字符串；实际内容变化保存新 HTML。已有区块 key 不变。

支持 `toolbar` 数组或 JSON 数组：常用粗体/斜体/下划线、标题、列表、引用、链接、撤销/重做、代码、表格与颜色等原生工具。兼容 `heading`、`bullet-list`、`ordered-list`、`bulletedList`、`numberedList`、`blockQuote` 等旧工具名。`stacked` 映射整行布局，`withLabel` 控制标签。未知工具报明确诊断，不静默丢弃。

正文图片现使用 Filament 原生图片工具，不需要 CKEditor。旧 CKEditor 媒体库、HTML 源码模式及第三方扩展未移植，未知工具仍会阻止编辑保存。原生编辑器不能表示的结构，在其他内容被实际修改时仍可能被原生编辑器规范化；应先对这些历史内容作有代表性的人工验收。公开消费者应使用自己的安全 HTML 渲染边界。

### 正文图片

默认工具栏包含 `attachFiles`（图片按钮），也兼容旧 `image/uploadImage/imageUpload` 工具名；自定义工具栏需要包含相应工具。原生对话框提供图片上传、插入及替代文字编辑。使用 Core `filament-cms.media.disk`（默认 public），目录配置 `filament-page-builder.rich_text.images.directory` 默认 `rich-content`，最大体积 `max_size` 默认 5120 KB。正文图片使用公开 URL；宿主需配置该磁盘的 URL/存储访问。

服务端只接收 PNG/JPEG/GIF/WebP，校验活跃管理员与当前授权作用域内的 `tenant.uploadMedia` 权限，非图片、超限、伪造已有文件路径均拒绝。正文继续保存带图片 URL/标识的 HTML。已保存图片可重开及复制；允许的已有文件标识仅从当前服务器记录的富文本/嵌套 Widget 中读取，不信任客户端隐藏 envelope。移除正文图片不自动删除存储，避免影响复制、修订与其他引用。旧无文件标识的 HTML 图片在未修改保存时保留。

富文本只注册 `rich_text` resolver，不加载 CKEditor 或 Nova 的运行时。页面级自动化已验证上传→持久化 HTML→重开→复制→移除且文件保留。此前浏览器确认了按钮与上传弹窗；系统文件选择器自动化超时，浏览器手动选图、磁盘真实 URL 与 CDN/生产存储仍未据此验收。

## 表格

`table` 支持旧二维数组，以及 `version/rows/columns/cells` 结构。复用旧 TablePayloadNormalizer/Validator 的纯 PHP 规则，保留原许可证与数据契约。未修改时保留旧矩阵、JSON 编码、合并和样式；修改旧矩阵后使用新版结构持久化。

编辑器提供单元格、文字片段与段落编辑、粗体/斜体/下划线、文字颜色/字号、单元格背景/对齐、链接与目标窗口、合并/拆分、追加行列与删除末行末列。合并保留所选区域文字；有合并区域时须先拆分再删除行列。支持 `minRows/maxRows/minColumns/maxColumns`；服务器另有 1000 行、200 列、50000 单元格硬上限，拒绝越界、重叠与错误 anchor。复杂原 Nova 工具选项未直接照搬，未知配置仍拒绝。

页面传输状态是单个 JSON 字符串，避免 Livewire 将单元格内容更新拆成超深属性路径；保存时解码成原结构，不改变数据库契约或宿主全局深度限制。嵌套 Widget 自身造成的过深组件路径，仍受宿主 Livewire 限制；本轮浏览器验收的是 Page 顶层区块。

## 营业时间

`opening_hours` 使用 `monday` 至 `sunday` 的时间段列表，例如 `09:00-12:00`；`exceptions` 是 `YYYY-MM-DD` 或 `MM-DD` 日期到时间段的映射，空列表表示休息。兼容包含 `hours/data` 的时间段对象并保留其元数据。

支持 `allowExceptions`、`allowOverflowMidnight`、`useTextInputs`。默认提供开始时间选择与结束时间输入（支持 `24:00`）；文本模式直接编辑完整范围。服务端拒绝无效时间、倒序、重叠、无效日期及被禁用的例外配置。仅显式允许时接收跨午夜范围。数组与旧 JSON 字符串可无损往返。

## HTTP

`http` 保存请求配置，编辑与普通保存不发送请求。编辑器包含 URL、方法、请求头、JSON/表单请求体、附加数据、目标字段和响应路径。

宿主在 `filament-page-builder.http.endpoints` 逐个配置精确 HTTPS URL、允许方法、可选服务端请求头，以及是否允许公开读取：

```php
'http' => ['endpoints' => [
    'https://content.example.com/data' => [
        'methods' => ['GET'],
        'public' => true,
        // 'headers' => ['Authorization' => env('CONTENT_SOURCE_TOKEN')],
    ],
]],
```

默认不允许任何目标。请求超时 10 秒、连接超时 5 秒，不跟随重定向；服务端请求头覆盖存储中的同名请求头。支持 GET/POST/PUT/PATCH/DELETE；公开解析只允许配置了 `public=true` 的 GET，不让匿名读取触发写操作。

“测试请求”检查活跃 admin、记录 update Policy 与 `executeContentHttp` Policy。Page/MenuItem 分别使用 `executeContentHttpPage`、`executeContentHttpMenuItem` 权限。`fixedResponse=true` 时，测试结果随下一次表单保存写入 `fixedResponseData`，之后可复用；修改请求配置会清除界面中的旧缓存。

公开输出删除原 HTTP 配置，使用 `targetAttribute/responsePath` 映射 JSON（支持通配符），也兼容 `mappingTargetKey/mappingPathKey` 自定义键名；拒绝覆盖已有输出字段。失败响应映射为 null，不公开 URL、请求头或错误堆栈。`additionalData` 保留在配置编辑中，当前 API 延续旧 resolver 的目标字段映射，不附带原始配置。真实外部服务未执行验收。

## 不支持的 Sensitive 字段

Page Builder 与 Menu 共用的字段编辑器不再支持 `sensitive`：字段列表、渲染器、查看明文弹窗及对独立 Sensitive Field 软件包的依赖均已移除。独立 Sensitive Field 软件包仍可用于其他业务。

旧内容如果仍引用 `sensitive` 定义，编辑器会隐藏其值并按不支持的字段阻止保存，公开输出继续排除该字段。不会自动删除数据库中的旧内容。普通文本的 `readonly` 服务端原值保护保持不变。

HTTP 权限和端点仍由宿主部署时明确配置。远端 CI 与真实浏览器操作需要单独验收。
