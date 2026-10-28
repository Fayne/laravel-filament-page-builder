# Changelog

## Unreleased

- AdvancedText is now a standalone dependency; Page Builder maps database definitions and retains server-side restoration for readonly content.

- Type-dependent visual field options and separate basic/options/rules tabs for Component and Widget field editors.
- Configurable built-in field labels/visibility, preserving advanced Options and legacy identifiers.

- Readable field types with stable resolver codes, image/file compatibility, visual validation rules and preserved advanced configuration.
- Shared Component/Widget field management with permission-aware parent links and scoped relation actions.

- Collapse existing component and nested Widget blocks, retain internal titles in headers, and expose native expand/collapse-all controls; newly added blocks open automatically.

- Native inline image uploads with uploadMedia authorization, type/size validation, server-owned attachment paths and preservation across edit/clone. No CKEditor runtime; legacy resolver names remain compatible.

- Rich HTML editors, merged/styled table editing, opening hours and explicitly authorized HTTP/Sensitive fields.
- Preserve raw legacy values and protected server values; omit sensitive/request configuration from public output.
- Table JSON transport avoids Livewire deep-update path failures; browser edit/save/reopen verified.

- Laravel 13 / Filament 5 migration in progress; independent package tests, configuration and service provider.
- Native content blocks, raw state adapter, nested widgets, media fields and separate public output resolution.
- Complete compatibility and browser acceptance remain pending; see package README and migration inventory.
