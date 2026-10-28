<p align="center"><img src="https://res.cloudinary.com/fayne/image/upload/v1787643269/fayne_logo.png" width="300"></p>

# Laravel Filament Page Builder

[简体中文](../README.md) | English

A dynamic-content editing foundation for **PHP 8.4+, Laravel 13, and Filament 5**. Converts field definitions into Filament Builder/Block forms, preserves persisted content contracts, and resolves content for Page, Menu, or host resources.

## Features and scope

| Capability | Description |
| --- | --- |
| Dynamic fields | Forms generated from stable resolver identifiers, with field options and create/update validation rules |
| Blocks and widgets | Add, clone, reorder, collapse, and nest widgets; clones receive new keys |
| Storage adaptation | Editing state separated from the persisted layout/key/internalTitle/attributes structure |
| Complex fields | Rich text, tables, opening hours, cascades, entities, media, and controlled HTTP requests |
| Public projection | Definition-based field output, files/nested content, and configured entity/HTTP resolution |
| Chinese and English UI | Built-in zh_CN and en; business field labels come from definitions |

## 1. Installation and host setup

Configure host authentication and authorization using [CMS Core](../../laravel-filament-cms-core/docs/README.en.md), and register Composer repositories for this private package and its dependencies. Besides Core, dependencies include Advanced Text, Table Field, Opening Hours, Cascade, Entity Reference, Media Picker, and Uni File Manager.

```bash
composer require fayne/laravel-filament-page-builder
php artisan vendor:publish --tag=filament-page-builder-config
php artisan filament:assets
```

There is no separate panel plugin, business-table migration, CMS initialization command, public page API, or independent npm build. The host supplies model attributes (an array cast is recommended), form schemas, disks, and permissions. Page/Menu provide their own definition models and management screens. This package uses Filament's native Builder without Nova or Whitecube runtimes.

## 2. Use ContentBuilder in a form

Add the field to a host Filament schema. This minimal repository returns field definitions from `fields()` by layout. The second and third `definitions()` arguments are Component and Widget `slug => label` choice maps:

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

To use Page's database definitions instead:

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

The second example reuses the ContentBuilder import above and requires Page to be installed. For Menu, substitute its component/widget models. For regional content, pass the trusted database connection name as the third EloquentContentDefinitions argument. Establish trusted tenant/authorization context before using these services; the package does not infer ownership from client fields.

## 3. Field types, options, and rules

| Category | Resolvers |
| --- | --- |
| Text and numbers | `text`, `textarea`, `email`, `number`, `boolean` |
| Choices and structured values | `select`, `multiselect`, `key_value`, `tag`, `json` |
| Dates and colors | `date`, `datetime`, `color` |
| Media and references | `file`, `entity`, `cascade` |
| Complex content | `rich_text`, `table`, `opening_hours`, `http` |
| Nested content | `widget`; server-controlled definition choices and recursion limits |

Definitions use `name`, `title`, `resolver`, `options`, and `rules`; database field slugs map to names. Common options include help text, required state, full-width layout, and defaults, with resolver-specific support. Page/Menu provide Basic settings, Field options, and Validation rules tabs while retaining advanced JSON editing.

`rules`, `creationRules`, and `updateRules` apply to general/create/update operations. Text readonly values are protected by restoring the server-side original; other fields follow their own supported options. `help` supports HTML from trusted administration content.

`sensitive` is not a supported type. Legacy values are excluded from public output and incompatible edits are blocked without deleting historical data. Missing block definitions, recursive widgets, and unimplemented types are not silently saved as plain text. See [field compatibility](field-compatibility.md) and [field definition management](field-definition-management.md) for detailed options (supplementary guides are currently in Chinese).

## 4. Storage structure and public output

Example persisted model content:

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

`ContentStateAdapter` converts between this format and Filament's `type/data` editing state. Reordering preserves keys; cloning generates new parent/child keys. Unedited legacy values and unknown attributes are retained in their original format where possible. Do not treat Builder editing state as public data or a new database contract.

Generate display data without rewriting raw references:

```php
use Fayne\PageBuilder\Support\ContentOutputResolver;

$projection = app(ContentOutputResolver::class)->resolve(
    $storedContent,
    $definitions,
    connection: $connectionName,
    locale: 'en',
);
```

`$storedContent` is the persisted block array; `$connectionName` is the host-selected connection name, or null. Output blocks contain `layout` and definition-resolved `attributes`, without internal key/internalTitle values or undeclared attributes. Files, entities, and HTTP follow their specific rules. Page/Menu already integrate the resolver into content APIs and snapshots. The package neither renders frontend HTML nor independently provides release/preview endpoints.

## 5. Files, rich text, and the media library

File fields use Media Picker with Uni File Manager's embedded library. Example definition:

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

Persisted values remain `{"disk":"public","path":"banners/home/file.jpg"}`, or an array for multiple selection. The host must register server-side `content-{disk}` storage areas with an empty root and an authorizer enforcing CMS permissions. Expose only the required public content disks. `directory` scopes browsing and uploads without moving existing files; missing or out-of-scope selections must be replaced before saving.

Rich text uses Filament RichEditor and stores HTML, with configured native toolbars and image uploads instead of CKEditor. Images use Core's media disk and `rich_text.images.directory`/`max_size`, defaulting to `rich-content` and 5120 KB, with administrator upload permission and image-type checks. Removing an inline image does not automatically delete its stored file, preserving revisions and other references.

The host must verify image URLs, public storage/CDN, actual uploads, and browser behavior. Run `php artisan filament:assets` after installation or upgrades.

## 6. HTTP, entities, and cascade sources

HTTP fields store configuration; ordinary editing and saving do not send requests. Test requests require record update permission and `executeContentHttp` capability. Register allowed endpoints individually on the server; none are allowed by default:

```php
// config/filament-page-builder.php
'http' => ['endpoints' => [
    'https://content.example.com/data' => [
        'methods' => ['GET'],
        'public' => true,
    ],
]],
```

Only exact HTTPS URLs and configured methods are allowed, without redirects. Public resolution executes only explicitly public GET endpoints. Server headers override stored headers; public output contains mapped response results, not the original request configuration.

Entity editing searches configured model/label allowlists with record authorization. Public entity expansion is disabled by default; enabling `resolve_entities` still requires allowed models/fields. External cascade sources use the separate `filament-cascade.sources` exact allowlist and server-side parent/child validation. Arbitrary administrator-supplied model classes or URLs are not trusted sources by default.

See [complex fields](complex-fields.md) and [Advanced Text](advanced-text.md) for additional configuration (Chinese guides).

## 7. Languages and custom fields

Built-in translations live in `resources/lang/zh_CN` and `resources/lang/en`, following the host interface locale. Page/Menu manage content translation groups and language versions; this package does not automatically translate values, component names, or custom help text.

The field registry uses Core's `filament-cms.fields.types`, configuring display labels, enabled states, and server-side resolver classes. `enabled=false` hides new selections while retaining existing-content handling. Custom classes implement `Fayne\PageBuilder\Contracts\FieldResolver` and are resolved by the container. Registry and public output are separate contracts: new types also need compatible value conversion, options, public resolution, and authorization. Replacing a form control alone does not establish complete support.

## 8. Tests and deployment

Run in this package directory:

```bash
composer install
composer test
composer format
node --test tests/Browser/*.test.cjs
```

Field packages have their own suites; Advanced Text interaction tests run in its owning package. Before deployment, verify no-change saves, cloning, nesting, ordering, and public output using real persisted samples. Check media authorization, image URLs, language/tenant/region isolation, and external HTTP in a controlled target environment. Assess historical complex HTML, toolbars, and unknown fields against the compatibility guide; not every old Nova configuration is directly supported. Package tests are not real-browser or production-storage acceptance.

## Contributing

1. Create a feature branch.
2. Implement the change and add tests for new behavior or bug fixes.
3. Verify the relevant tests and state any outstanding environment acceptance.
4. Update both language versions when public usage changes.
5. Submit a merge request.

## License

This software uses a [proprietary license](../LICENSE.md). Use and distribution are subject to its authorization restrictions.
