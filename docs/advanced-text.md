# AdvancedText integration

The reusable field lives in `fayne/laravel-filament-advanced-text`. Page Builder's `FieldSchemaFactory` maps the database `text` definition to `Fayne\AdvancedText\Forms\Components\AdvancedText`, including labels and values, remote suggestions, affixes, length, copy, and read-only behavior.

Read-only value restoration remains in Page Builder because it depends on persisted component keys, nested Widgets, and server-owned content. The reusable field has no CMS guard, record, or Page Builder dependency.

See the [AdvancedText package guide](../../laravel-filament-advanced-text/README.md) for direct Filament usage and its `filament-advanced-text` configuration.
