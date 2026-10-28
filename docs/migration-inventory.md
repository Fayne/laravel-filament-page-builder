# 工程迁移对照

此表只说明文件职责去向，功能是否完成另见状态文件。`docs/legacy-reference` 是原 Nova 使用资料，不应作为新包安装指引。

| 原文件/目录 | 目标与处理 |
| --- | --- |
| `.gitignore` | 适配独立依赖及测试缓存 |
| `LICENSE.md` | 原文保留 |
| `README.md` | 新安装说明；原文归档到 docs/legacy-reference |
| `composer.json` | PHP 8.4 / Laravel 13 / Filament 5 与独立测试工具链 |
| `dist` | Nova 资产不加载，Filament 提供基础编辑器；Menu 使用包内 Blade/Alpine |
| `docs` | 原文归档到 docs/legacy-reference/docs；新规格/状态在 packages/docs |
| `package-lock.json` | 旧 Nova 依赖锁不适用，未复制 |
| `package.json` | 旧 Nova 前端构建不适用；当前 UI 无新增独立前端编译入口 |
| `postcss.config.js` | 旧 Nova 构建不适用，未复制 |
| `resources` | 语言文件保留；Nova/Vue 界面由原生 Builder 或 Blade 树替代 |
| `src` | 按模型/服务/策略/Filament Resource 职责迁移，功能核对继续进行 |
| `stubs` | 模型和迁移归属 Page 包；不继续向宿主生成 Nova 代码 |
| `webpack.mix.js` | 旧 Nova Mix 构建由 Filament 原生组件替代 |
| `webpack.mix.js.dist` | 旧 Nova Mix 构建由 Filament 原生组件替代 |

2026-10-21：另迁入 `laravel-nova-table-field/src/Support/{TablePayloadNormalizer,TablePayloadValidator}.php` 到本包 `src/Support/Table/`，只改命名空间与格式，沿用相同作者与原文许可证。原 Vue 工具界面由本包单元格编辑器替代。HTTP/Sensitive 行为迁入 `ContentHttpRequest`、`SensitiveContent` 与各自 Filament 字段，未复制 Nova 控制器、任意资源属性读取或 ActionEvent 耦合。营业时间按已安装旧包契约重新实现，不引入 Nova 依赖。
