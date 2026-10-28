# 字段迁移清单

依据旧 Core 的字段定义逐项评估当前实现。此清单不表示保留 Nova 字段标识；运行时只注册当前表格中的 resolver。未支持的 resolver/options 会显示诊断并拒绝保存，不能以普通输入框替代复杂字段。

| 旧 resolver | 当前编辑适配 | 旧选项与差距 |
| --- | --- | --- |
| text | 独立 AdvancedText 包 | 静态/远程建议、显示值映射、前后缀拼接、默认值、字数统计与复制；readonly 原值恢复由 Page Builder 负责，见 [AdvancedText](advanced-text.md) |
| textarea | Textarea | default_value、max_length、rows 已接入 |
| boolean | Toggle | default_value，保留 false |
| number | Numeric TextInput | min/max/step 为当前支持选项；旧 Trait 无额外配置 |
| file | ContentMediaPicker / ManagedFilePicker | disk/is_image/is_multiple/directory；服务端存储区域、权限、路径与 MIME 校验；持久化仍为 disk/path |
| select | Select | options/searchable |
| multiselect | multiple Select | options；raw 数组保留 |
| key_value | KeyValue | 无专用旧 Trait |
| tag | TagsInput | 无专用旧 Trait |
| date / datetime | DatePicker / DateTimePicker | 旧 Trait 无额外选项；浏览器与时区验收待补 |
| color | ColorPicker | default_value |
| json | JSON Textarea | 实际 JSON 校验、解码；布尔/数值/空数组往返有测试 |
| email | email TextInput | 邮箱校验 |
| widget | 嵌套 ContentBuilder | widgets ID 转 slug、limit；真实表单两层嵌套/复制有测试；递归定义当前拒绝 |
| entity | 授权检索的单选/多选 Select | reference/is_multiple；配置模型与标签白名单；逐模型/记录授权；旧数组及 JSON 字符串往返有测试；真实浏览器检索待验收 |
| cascade | 逐级原生 Select 与可选搜索 | options/fromFile/fromDirectory/fromUrl/displayInline/searchable 已接入；外部源要求服务端精确白名单；最多 64 级，服务端校验父子路径；旧 JSON 字符串保留 |
| rich_text | 原生 RichEditor，HTML 持久化 | stacked/toolbar/withLabel；等价规范化保留既有 HTML；原生图片上传已接入；不加载 CKEditor 或 Nova 运行时，未知工具拒绝 |
| table | 单元格/段落编辑、合并/拆分、样式/链接 | 旧矩阵与结构化表格往返；复用原纯 PHP 校验；支持行列上下限，前端传输 JSON 避免深层路径错误 |
| opening_hours | 每周时间段与例外日期编辑 | allowExceptions/allowOverflowMidnight/useTextInputs；服务端校验日期、时间与重叠；旧 JSON 字符串保留 |
| HTTP 自定义字段（http） | 请求配置编辑与显式授权测试请求 | 精确 HTTPS 目标/方法白名单、不重定向；响应路径与缓存映射；公开仅允许配置的 GET，删除原配置 |

公共选项：help/required/fullWidth/default_value 已接入。readonly 已支持 Text 的服务端原值保护，其他类型仍拒绝编辑。rules/creationRules/updateRules 对应通用/create/edit 校验；Widget 同样按当前 create/edit 操作应用专用规则。未知选项明确报错，不静默忽略。

持久化：保留 layout/key/internalTitle/attributes 与未知属性；Builder type/data 不入库。复制生成新的父子 key，重排保留 key。普通 JSON 中的 layout 不被识别为 Widget。缺失区块定义显示诊断并阻止保存；嵌套 Widget 选项/循环定义错误的完整诊断界面仍待补。

验证范围（2026-10-21）：包内独立套件 70 项 / 150 断言通过；真实后台已验证 Cascade 新增、父级改变清空子级、保存重开、导出再导入；干净 Laravel 13 宿主安装与 Page 编辑入口通过。Entity 浏览器检索、其余完整字段矩阵及远端 CI 尚未验收。

2026-10-21 复杂字段续做与使用边界见 [复杂字段说明](complex-fields.md)。本轮 Page Builder 103 项 / 235 断言、Page 34 / 125、Menu 27 / 76；真实浏览器已验收富文本、顶层表格与营业时间保存重开。此前 70 / 150 是历史检查点。

2026-10-21 正文图片续做：默认原生图片工具、uploadMedia 授权、图片类型/体积校验、服务器记录文件引用验证、保存重开及复制。三包最新 PB109/251、Page35/141、Menu27/76，共171项/468断言。实际浏览器确认按钮/上传弹窗，系统选图工具受限，未将浏览器完整上传或生产存储标记完成。
