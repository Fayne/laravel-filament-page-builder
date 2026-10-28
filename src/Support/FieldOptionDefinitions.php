<?php

namespace Fayne\PageBuilder\Support;

final class FieldOptionDefinitions
{
    public static function all(array $widgets = []): array
    {
        $common = ['help' => [__('filament-page-builder::ui.help_text'), 'text'], 'required' => [__('filament-page-builder::ui.required'), 'boolean'], 'fullWidth' => [__('filament-page-builder::ui.full_width'), 'boolean']];
        $text = ['placeholder' => [__('filament-page-builder::ui.placeholder'), 'text'], 'max_length' => [__('filament-page-builder::ui.maximum_length'), 'integer'], 'default_value' => [__('filament-page-builder::ui.default_value'), 'text']];
        $rich = ['toolbar' => [__('filament-page-builder::ui.toolbar_buttons'), 'multiple', array_combine(['bold', 'italic', 'underline', 'strike', 'h1', 'h2', 'h3', 'bulletList', 'orderedList', 'blockquote', 'link', 'attachFiles', 'undo', 'redo', 'code', 'codeBlock', 'horizontalRule', 'table', 'textColor', 'alignStart', 'alignCenter', 'alignEnd', 'subscript', 'superscript', 'clearFormatting'], [__('filament-page-builder::ui.bold'), __('filament-page-builder::ui.italic'), __('filament-page-builder::ui.underline'), __('filament-page-builder::ui.strikethrough'), __('filament-page-builder::ui.heading_1'), __('filament-page-builder::ui.heading_2'), __('filament-page-builder::ui.heading_3'), __('filament-page-builder::ui.bullet_list'), __('filament-page-builder::ui.ordered_list'), __('filament-page-builder::ui.blockquote'), __('filament-page-builder::ui.link'), __('filament-page-builder::ui.upload_image'), __('filament-page-builder::ui.undo'), __('filament-page-builder::ui.redo'), __('filament-page-builder::ui.inline_code'), __('filament-page-builder::ui.code_block'), __('filament-page-builder::ui.horizontal_rule'), __('filament-page-builder::ui.table'), __('filament-page-builder::ui.text_color'), __('filament-page-builder::ui.align_left'), __('filament-page-builder::ui.align_center'), __('filament-page-builder::ui.align_right'), __('filament-page-builder::ui.subscript'), __('filament-page-builder::ui.superscript'), __('filament-page-builder::ui.clear_formatting')])], 'stacked' => [__('filament-page-builder::ui.full_width_editor'), 'boolean', null, true], 'withLabel' => [__('filament-page-builder::ui.show_field_name'), 'boolean', null, true]];
        $rich['toolbar'][3] = ['bold', 'italic', 'underline', 'strike', 'h2', 'h3', 'bulletList', 'orderedList', 'blockquote', 'link', 'attachFiles', 'undo', 'redo'];
        $file = ['directory' => [__('filament-page-builder::ui.directory'), 'text'], 'disk' => [__('filament-page-builder::ui.storage_disk'), 'select', array_combine($disks = array_keys(config('filesystems.disks', [])), $disks)], 'is_multiple' => [__('filament-page-builder::ui.allow_multiple_files'), 'boolean']];
        $specific = [
            'text' => $text + [
                'suggestions' => [__('filament-page-builder::ui.suggestions_one_per_line'), 'lines'],
                'options' => [__('filament-page-builder::ui.suggestions_labels_and_values'), 'pairs', null, ['key' => __('filament-page-builder::ui.display_name'), 'value' => __('filament-page-builder::ui.stored_value')]],
                'remote_options_url' => [__('filament-page-builder::ui.remote_suggestions_url'), 'text'],
                'prefix' => [__('filament-page-builder::ui.fixed_prefix'), 'text'], 'suffix' => [__('filament-page-builder::ui.fixed_suffix'), 'text'],
                'prefixConcatenate' => [__('filament-page-builder::ui.include_prefix_in_stored_value'), 'boolean', null, true],
                'suffixConcatenate' => [__('filament-page-builder::ui.include_suffix_in_stored_value'), 'boolean', null, true],
                'copyable' => [__('filament-page-builder::ui.allow_copying'), 'boolean'], 'readonly' => [__('filament-page-builder::ui.read_only_retain_server_value'), 'boolean'],
            ],
            'email' => $text + ['suggestions' => [__('filament-page-builder::ui.suggestions_one_per_line'), 'lines']],
            'textarea' => $text + ['rows' => [__('filament-page-builder::ui.input_rows'), 'integer']],
            'number' => ['min' => [__('filament-page-builder::ui.minimum'), 'number'], 'max' => [__('filament-page-builder::ui.maximum'), 'number'], 'step' => [__('filament-page-builder::ui.step'), 'number'], 'default_value' => [__('filament-page-builder::ui.default_value'), 'number']],
            'select' => ['options' => [__('filament-page-builder::ui.options_values_and_labels'), 'pairs'], 'searchable' => [__('filament-page-builder::ui.searchable'), 'boolean'], 'default_value' => [__('filament-page-builder::ui.default_value'), 'text']],
            'multiselect' => ['options' => [__('filament-page-builder::ui.options_values_and_labels'), 'pairs'], 'searchable' => [__('filament-page-builder::ui.searchable'), 'boolean'], 'default_value' => [__('filament-page-builder::ui.default_selections_one_per_line'), 'lines']],
            'file' => $file, 'image' => $file,
            'boolean' => ['default_value' => [__('filament-page-builder::ui.enabled_by_default'), 'boolean']],
            'date' => ['default_value' => [__('filament-page-builder::ui.default_date_yyyy_mm_dd'), 'text']],
            'datetime' => ['default_value' => [__('filament-page-builder::ui.default_date_and_time'), 'text']],
            'color' => ['default_value' => [__('filament-page-builder::ui.default_color'), 'text']],
            'tag' => ['default_value' => [__('filament-page-builder::ui.default_tags_one_per_line'), 'lines']],
            'key_value' => ['default_value' => [__('filament-page-builder::ui.default_key_value_pairs'), 'pairs']],
            'json' => ['default_value' => [__('filament-page-builder::ui.default_content_json'), 'json']],
            'widget' => ['widgets' => [__('filament-page-builder::ui.allowed_widgets'), 'multiple', $widgets], 'limit' => [__('filament-page-builder::ui.maximum_count'), 'integer']],
            'rich_text' => $rich,
            'table' => ['minRows' => [__('filament-page-builder::ui.minimum_rows'), 'integer'], 'maxRows' => [__('filament-page-builder::ui.maximum_rows'), 'integer'], 'minColumns' => [__('filament-page-builder::ui.minimum_columns'), 'integer'], 'maxColumns' => [__('filament-page-builder::ui.maximum_columns'), 'integer']],
            'opening_hours' => ['allowExceptions' => [__('filament-page-builder::ui.allow_exception_dates'), 'boolean', null, true], 'allowOverflowMidnight' => [__('filament-page-builder::ui.allow_overnight_hours'), 'boolean'], 'useTextInputs' => [__('filament-page-builder::ui.use_text_time_inputs'), 'boolean']],
            'entity' => ['reference' => [__('filament-page-builder::ui.referenceable_content_types'), 'multiple', collect(config('filament-page-builder.entities', []))->mapWithKeys(fn ($value, $key) => [$key => $value['title'] ?? $key])->all()], 'is_multiple' => [__('filament-page-builder::ui.allow_multiple_selections'), 'boolean']],
            'cascade' => ['options' => [__('filament-page-builder::ui.static_hierarchy_data_json'), 'json'], 'fromFile' => [__('filament-page-builder::ui.file_source'), 'select', self::sources('files')], 'fromDirectory' => [__('filament-page-builder::ui.directory_source'), 'select', self::sources('directories')], 'fromUrl' => [__('filament-page-builder::ui.endpoint_source'), 'select', self::sources('urls')], 'displayInline' => [__('filament-page-builder::ui.display_inline'), 'boolean'], 'searchable' => [__('filament-page-builder::ui.searchable'), 'boolean']],
            'http' => ['fixedResponse' => [__('filament-page-builder::ui.use_fixed_response'), 'boolean']],
        ];
        foreach ($specific as $type => &$options) {
            $options = $common + $options;
        }

        return $specific;
    }

    private static function sources(string $type): array
    {
        $sources = config('filament-cascade.sources.'.$type, []);

        return array_combine($sources, $sources);
    }
}
