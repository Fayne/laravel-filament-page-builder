# 字段定义管理

Page 与 Menu 的 Component、Widget 字段使用相同的管理表单。字段类型显示中文名称，保存稳定 resolver 标识（如 `text`、`textarea`、`rich_text`），由运行时映射到对应处理器，不保存 PHP 类名。

“图片”通过 `FileUpload` 和 `options.is_image=true` 配置。富文本统一使用 `rich_text` resolver，并直接保存 Filament 支持的工具栏标识。

验证规则分为通用、创建时、更新时三组。选择规则并填写参数，例如“最大长度”加 `100` 对应 `max:100`。高级 JSON 可编辑自定义或复杂规则，原有未知配置会保留。普通管道规则字符串可视化显示，修改后保存为规则数组；含正则的管道字符串只允许通过高级入口编辑，避免拆分改变含义。Options 提供随类型变化的可视化配置，同时保留高级 JSON 入口。

在 Component 或 Widget 的编辑页内管理其字段；独立字段表单也提供“管理所属定义”入口，并遵守所属定义的查看权限。定义是共享的，修改可能影响所有引用它的 Page 或 Menu 内容，并不属于某个单独页面。

每组的可视化规则选项排除其他行已选择的类型；删除后可重新选择。保存时按组去除完全相同的规则，保留首次出现的顺序和参数不同的规则。普通管道字符串也去重但维持字符串格式，含正则的管道字符串原样保留，避免破坏表达式。`between` 提供最小值、最大值两个输入框。

## 表单分组与类型选项

Page/Menu 的 Component Field、Widget Field（包括定义页内的关联管理）共用三个原生 Tab：基本设置、字段选项、验证规则。切换 Tab 不改变字段路径，未修改的配置不补写默认值。输入提示、默认值、数字范围、候选项、文件磁盘/多选、Widget 范围/数量、富文本工具栏、表格尺寸及营业时间等按类型显示。复杂级联静态数据仍用 JSON；外部来源和关联类型从服务端允许列表选择。

通用说明、必填和整行布局可配置；嵌套 Widget 的说明及整行布局也会生效。Text 的远程建议、复制、只读及前后缀由独立的 [AdvancedText](advanced-text.md) 字段包提供。

类型列表通过宿主 `config/filament-cms.php` 的 `fields.types` 控制，例如：

```php
'fields' => [
    'types' => ['text' => '单行文本'],
],
```

未配置的内置类型照常显示；字符串改名，false 隐藏新选择。已有记录使用被隐藏的类型仍可回显和保留。此入口不注册新的字段运行时，也不允许管理员填写任意 PHP 类名。类型能力仍由 FieldSchemaFactory / ContentBuilder 明确实现。可通过 `php artisan vendor:publish --tag=filament-cms-config` 发布配置；本开发宿主已发布。服务端设置缓存时需按宿主部署流程刷新配置。

实体类型由 Page Builder 的 `entities` 配置提供；通用引用字段通过宿主注入的作用域查询和记录授权回调。级联动态来源由独立 `filament-cascade.sources` 白名单提供。Widget 候选项来自 Page/Menu 各自配置的 Widget 模型，并检查查看列表权限。选项变更保存回原 `options` JSON，无新增数据库字段。

从 Component/Widget 的“新增字段”入口跳转到独立创建页时，链接携带实际关联外键（例如 page_component_id），所属选择器默认选中对应定义。不存在、格式无效或无查看权限的记录不预选；直接打开创建页仍可手动选择所属定义。

## 配置字段实现

`filament-cms.fields.types` 是完整注册表，每个类型配置 `label`、`resolver`、`enabled`。`enabled=false` 隐藏新增选择，已有内容仍可以使用 resolver 编辑。

```php
'fields' => [
    'types' => [
        'table' => [
            'label' => '表格',
            'resolver' => App\Fields\BetterTableResolver::class,
            'enabled' => true,
        ],
    ],
],
```

自定义类实现 `Fayne\PageBuilder\Contracts\FieldResolver` 的 `resolve(array $definition, ?FieldResolverContext $context = null): Field`，返回已配置的 Filament 字段。definition 包含 name、resolver、title、options 和 rules；适配器负责应用选项、校验及值转换。类由容器解析，支持构造函数依赖注入。默认实现位于 `Fields/Resolvers`；工厂不再按类型选择组件类。

替换已有类型时，保持该类型的持久化契约及 CMS 权限要求。既有类型的选项管理、公开输出、敏感值保护和附件授权仍遵循内容层规则；新类型或不同存储格式需要另外接入这些规则。widget 通过配置中的 WidgetResolver 构建嵌套 ContentBuilder；FieldResolverContext 提供定义仓库、Widget 候选、嵌套路径和 Builder 类。替换 Widget resolver 时必须保留这些内容约束。
