<p align="center"><img src="https://res.cloudinary.com/fayne/image/upload/v1787643269/fayne_logo.png" width="300"></p>

# Laravel Filament Page Builder

简体中文 | [English](docs/README.en.md)

适用于 **PHP 8.4+、Laravel 13、Filament 5** 的动态内容编辑基础包。将字段定义转换为 Filament Builder/Block，保留持久化内容契约，并为 Page、Menu 或宿主资源提供内容解析能力。

## 功能与边界

| 能力 | 说明 |
| --- | --- |
| 动态字段 | 按稳定 resolver 标识生成表单，支持字段选项及创建/更新验证规则 |
| 区块与 Widget | 添加、复制、排序、折叠及嵌套 Widget；复制生成新 key |
| 存储适配 | 编辑态与 layout/key/internalTitle/attributes 持久化结构分离 |
| 复杂字段 | 富文本、表格、营业时间、级联、实体、媒体与受控 HTTP |
| 公开解析 | 按定义输出字段，解析文件/嵌套内容与已配置的实体/HTTP |
| 中英文界面 | 内置 zh_CN 与 en，字段业务标签由定义提供 |

## 1. 安装与宿主准备

先按 [CMS Core](../laravel-filament-cms-core/README.md) 配置宿主认证和授权，并在 Composer 中配置本私有包及关联依赖的软件源。除 Core 外还依赖 Advanced Text、Table Field、Opening Hours、Cascade、Entity Reference、Media Picker 和 Uni File Manager。

```bash
composer require fayne/laravel-filament-page-builder
php artisan vendor:publish --tag=filament-page-builder-config
php artisan filament:assets
```

本包无独立 Panel 插件、业务表迁移、CMS 初始化命令或公开页面 API，也不需要独立 npm 构建。宿主负责模型字段（建议 array cast）、表单 schema、磁盘与权限；Page/Menu 已提供各自的定义模型与管理界面。本包使用 Filament 原生 Builder，不加载 Nova 或 Whitecube 运行时。

## 2. 在表单中使用 ContentBuilder

在宿主 Filament schema 中加入字段。下面展示最小定义仓库，`fields()` 按 layout 返回字段定义；`definitions()` 的第二、第三个参数分别是 Component 和 Widget 的 `slug => label` 候选映射：

```php
use Fayne\PageBuilder\Contracts\ContentDefinitionRepository;
use Fayne\PageBuilder\Filament\Forms\Components\ContentBuilder;

$definitions = new class implements ContentDefinitionRepository
{
    public function fields(string $layout, bool $widget = false): array
    {
        return $layout === 'hero' ? [
            ['name' => 'heading', 'title' => 'Heading', 'resolver' => 'text'],
        ] : [];
    }
};

ContentBuilder::make('components')
    ->definitions($definitions, ['hero' => 'Hero']);
```

使用 Page 的数据库定义时，可改为：

```php
use Fayne\PageBuilder\Support\EloquentContentDefinitions;

$definitions = new EloquentContentDefinitions(
    config('filament-page.models.page_component'),
    config('filament-page.models.page_widget'),
);

ContentBuilder::make('components')->definitions(
    $definitions,
    $definitions->layouts(),
    $definitions->layouts(true),
);
```

第二个示例沿用前面的 ContentBuilder 导入，需要已经安装 Page；Menu 改用自己的组件/Widget 模型。多区域场景可将可信数据库连接名作为 EloquentContentDefinitions 第三个参数。使用这些服务前设置当前可信租户/授权上下文；本包不会从客户端字段推断内容归属。

## 3. 字段类型、选项与规则

| 分类 | resolver |
| --- | --- |
| 文本与数值 | `text`、`textarea`、`email`、`number`、`boolean` |
| 选择与结构化值 | `select`、`multiselect`、`key_value`、`tag`、`json` |
| 日期与颜色 | `date`、`datetime`、`color` |
| 媒体与引用 | `file`、`entity`、`cascade` |
| 复杂内容 | `rich_text`、`table`、`opening_hours`、`http` |
| 嵌套内容 | `widget`；候选定义与递归深度由服务端约束 |

字段定义使用 `name`、`title`、`resolver`、`options` 和 `rules`；数据库字段模型的 slug 会映射为 name。通用选项包括说明、必填、整行布局和默认值，具体选项以对应 resolver 为准。Page/Menu 提供基本设置、字段选项、验证规则三个管理 Tab，并保留高级 JSON 入口。

`rules`、`creationRules`、`updateRules` 对应通用/创建/更新操作。Text 的 readonly 使用服务端原值保护；其它字段按各自支持范围处理。`help` 支持可信管理内容的 HTML 提示。

`sensitive` 不属于支持类型，旧定义中的值不公开输出，编辑时会阻止不兼容保存，不自动删除历史数据。缺失区块定义、递归 Widget 或未实现的类型不能当作普通文本静默保存。详细选项见 [字段兼容清单](docs/field-compatibility.md)、[字段定义管理](docs/field-definition-management.md)。

## 4. 存储结构与公开输出

模型保存的内容示例：

```json
[
  {
    "layout": "hero",
    "key": "stable-block-key",
    "internalTitle": "Homepage banner",
    "attributes": {"heading": "Welcome"}
  }
]
```

`ContentStateAdapter` 在持久化结构和 Filament `type/data` 编辑态之间转换。重排保留 key，复制重新生成父子 key；未编辑的旧值和未知属性尽量按原格式保留。不要把 Builder 编辑态直接当作公开数据或新数据库契约。

公开解析在不反写原始引用的情况下生成展示数据：

```php
use Fayne\PageBuilder\Support\ContentOutputResolver;

$projection = app(ContentOutputResolver::class)->resolve(
    $storedContent,
    $definitions,
    connection: $connectionName,
    locale: 'en',
);
```

`$storedContent` 为持久化区块数组，`$connectionName` 是宿主选定的连接名（可为 null）。输出区块包含 `layout` 和按定义解析的 `attributes`，不携带内部 key/internalTitle 或未声明属性；文件、实体与 HTTP 按各自规则处理。Page/Menu 已在内容 API 和快照中集成解析器。本包不负责渲染前端 HTML，也不独立提供发布/预览端点。

## 5. 文件、富文本与媒体库

文件字段通过 Media Picker 使用 Uni File Manager 内嵌媒体库。示例定义：

```json
{
  "name": "image",
  "title": "Image",
  "resolver": "file",
  "options": {
    "disk": "public",
    "directory": "banners/home",
    "is_image": true,
    "is_multiple": false
  }
}
```

持久化值仍为 `{"disk":"public","path":"banners/home/file.jpg"}`；多选为数组。宿主需提供名为 `content-{disk}` 的服务端存储区域，根路径为空，并注册执行 CMS 权限的 authorizer。只向内容编辑器开放需要的公开磁盘。`directory` 同时限定浏览和上传目录，不会移动已有文件；范围外或丢失的旧选择需更换后保存。

富文本使用 Filament RichEditor，保存 HTML；支持配置的原生工具栏与图片上传，不使用 CKEditor。图片使用 Core 的媒体磁盘及 `rich_text.images.directory`/`max_size`，默认 `rich-content`、5120 KB，并检查管理员上传权限和图片类型。移除正文图片不自动删除磁盘文件，避免破坏修订或其它引用。

图片预览 URL、公开存储/CDN、真实上传、浏览器行为由宿主验收。安装升级后运行 `php artisan filament:assets`。

## 6. HTTP、实体与级联来源

HTTP 字段保存配置；普通编辑和保存不发送请求。测试请求要求记录 update 权限及 `executeContentHttp` 能力。允许目标在服务端逐项配置，默认不允许任何外部目标：

```php
// config/filament-page-builder.php
'http' => ['endpoints' => [
    'https://content.example.com/data' => [
        'methods' => ['GET'],
        'public' => true,
    ],
]],
```

只允许精确 HTTPS URL、已配置方法，不跟随重定向；公开解析只执行显式 `public=true` 的 GET。服务端请求头覆盖存储配置，公开输出使用响应映射结果，不暴露原请求配置。

实体编辑通过配置模型与标签白名单及记录授权检索；公开实体展开默认关闭，开启 `resolve_entities` 后仍需配置允许的模型/字段。级联外部源由独立 `filament-cascade.sources` 精确白名单管理，服务端校验父子路径。默认不允许管理员输入任意模型类或把任意 URL 当作可信来源。

更多配置见 [复杂字段](docs/complex-fields.md)、[Advanced Text](docs/advanced-text.md)。

## 7. 中英文与自定义字段

内置翻译目录为 `resources/lang/zh_CN` 与 `resources/lang/en`，跟随宿主界面 locale。内容翻译组与语言版本由 Page/Menu 管理；本包不会自动翻译字段值、组件名称或自定义说明。

字段注册表使用 Core 的 `filament-cms.fields.types`。可配置显示 label、enabled 和服务端 resolver 类；`enabled=false` 隐藏新增选择但保留旧内容处理。自定义类实现 `Fayne\PageBuilder\Contracts\FieldResolver`，由容器解析。注册表与公开输出是不同契约：新类型还需实现兼容的值转换、选项管理、公开解析及授权，不能只更换表单控件就宣称完整支持。

## 8. 测试与上线

在本包目录执行：

```bash
composer install
composer test
composer format
node --test tests/Browser/*.test.cjs
```

字段包有各自测试；Advanced Text 的交互测试在其所属包执行。上线前用真实持久化内容验证不修改保存、复制、嵌套、排序与公开输出；验证媒体授权、图片 URL、语言/租户/区域隔离，并在受控目标环境验证外部 HTTP。历史复杂 HTML、工具栏和未知字段需按兼容清单评估，不承诺所有旧 Nova 配置都可直接运行。包测试不等于真实浏览器或生产存储验收。

## 贡献代码

1. 创建功能分支。
2. 实现所需改动，并为新增功能或缺陷修复补充测试。
3. 确认相关测试通过，注明尚未完成的环境验收。
4. 修改公开用法时，同步更新中英文文档。
5. 提交合并请求。

## 许可证

本软件采用[专有许可证](LICENSE.md)，使用与分发须遵守其中的授权限制。
