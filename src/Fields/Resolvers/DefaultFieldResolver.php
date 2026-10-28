<?php

namespace Fayne\PageBuilder\Fields\Resolvers;

use Fayne\AdvancedText\Forms\Components\AdvancedText;
use Fayne\Cascade\Forms\Components\CascadeField;
use Fayne\EntityReference\Forms\Components\EntityReferenceSelect;
use Fayne\OpeningHours\Forms\Components\OpeningHoursField;
use Fayne\PageBuilder\Contracts\FieldResolver;
use Fayne\PageBuilder\Support\FieldResolverContext;
use Fayne\TableField\Forms\Components\TableField;
use Filament\Forms\Components\Field;
use Filament\Forms\Components\Select;
use Illuminate\Support\HtmlString;
use InvalidArgumentException;

abstract class DefaultFieldResolver implements FieldResolver
{
    abstract protected function build(string $name, array $options): Field;

    public function resolve(array $definition, ?FieldResolverContext $context = null): Field
    {
        $name = $definition['name'];
        $type = $definition['resolver'];
        $options = $definition['options'] ?? [];
        $common = ['help', 'required', 'readonly', 'fullWidth', 'default_value'];
        $specific = match ($type) {
            'text' => ['placeholder', 'max_length', 'suggestions', 'options', 'copyable', 'remote_options_url', 'prefix', 'suffix', 'prefixConcatenate', 'suffixConcatenate'],
            'email' => ['placeholder', 'max_length', 'suggestions'],
            'textarea' => ['placeholder', 'max_length', 'rows'],
            'number' => ['min', 'max', 'step'],
            'select', 'multiselect' => ['options', 'searchable'],
            'file' => ['disk', 'directory', 'is_image', 'is_multiple'],
            'entity' => ['reference', 'is_multiple'],
            'cascade' => ['options', 'fromFile', 'fromDirectory', 'fromUrl', 'displayInline', 'searchable'],
            'rich_text' => ['toolbar', 'stacked', 'withLabel'],
            'http' => ['fixedResponse'],
            'table' => ['minRows', 'maxRows', 'minColumns', 'maxColumns'],
            'opening_hours' => ['allowExceptions', 'allowOverflowMidnight', 'useTextInputs'],
            'json' => [],
            'boolean', 'date', 'datetime', 'color', 'key_value', 'tag' => [],
            default => throw new InvalidArgumentException(__('filament-page-builder::errors.unsupported_field', ['type' => $type])),
        };
        $unknown = array_diff(array_keys($options), $common, $specific);
        if ($unknown !== []) {
            throw new InvalidArgumentException(__('filament-page-builder::errors.unsupported_options', ['options' => implode(', ', $unknown)]));
        }
        $field = $this->build($name, $options);
        $field->label($definition['title'] ?? $name)
            ->helperText(isset($options['help']) ? new HtmlString($options['help']) : null)
            ->required((bool) ($options['required'] ?? false))
            ->rules($definition['rules']['rules'] ?? [])
            ->rules($definition['rules']['creationRules'] ?? [], fn (string $operation): bool => $operation === 'create')
            ->rules($definition['rules']['updateRules'] ?? [], fn (string $operation): bool => $operation === 'edit');
        if (array_key_exists('default_value', $options)) {
            $field->default($options['default_value']);
        }
        if ($options['fullWidth'] ?? false) {
            $field->columnSpanFull();
        }
        if (($options['readonly'] ?? false) && $type !== 'text') {
            throw new InvalidArgumentException(__('filament-page-builder::errors.readonly_unsupported'));
        }
        if ($type === 'text' && ($options['readonly'] ?? false)) {
            $field->dehydrated(false);
        }
        if (in_array($type, ['text', 'textarea', 'email'], true)) {
            $field->placeholder($options['placeholder'] ?? null)->maxLength($options['max_length'] ?? null);
            if ($type !== 'textarea') {
                $field->datalist($options['suggestions'] ?? []);
            }
        }
        if ($type === 'textarea' && isset($options['rows'])) {
            $field->rows((int) $options['rows']);
        }
        if ($type === 'email') {
            $field->email();
        }
        if ($type === 'number') {
            $field->numeric()->minValue($options['min'] ?? null)->maxValue($options['max'] ?? null)->step($options['step'] ?? 1);
        }
        if ($field instanceof TableField) {
            $field->tableOptions($options);
        }
        if ($field instanceof OpeningHoursField) {
            $field->hoursOptions($options);
        }
        if ($field instanceof CascadeField) {
            $field->cascadeOptions($options);
        }
        if ($field instanceof Select && ! ($field instanceof EntityReferenceSelect)) {
            $field->options($options['options'] ?? [])->multiple($type === 'multiselect')->searchable((bool) ($options['searchable'] ?? false));
        }

        if ($type === 'json') {
            $field->formatStateUsing(fn ($state) => json_encode($state, JSON_UNESCAPED_UNICODE | JSON_PRETTY_PRINT | JSON_THROW_ON_ERROR))
                ->rules([...($definition['rules']['rules'] ?? []), 'json'])
                ->dehydrateStateUsing(fn ($state) => json_decode($state, true, flags: JSON_THROW_ON_ERROR));
        }

        return $field;
    }

    protected function makeText(string $name, array $options): AdvancedText
    {
        $field = AdvancedText::make($name)
            ->options($options['options'] ?? [])
            ->remoteOptionsUrl($options['remote_options_url'] ?? null)
            ->prefix($options['prefix'] ?? null)
            ->suffix($options['suffix'] ?? null)
            ->prefixConcatenate((bool) ($options['prefixConcatenate'] ?? true))
            ->suffixConcatenate((bool) ($options['suffixConcatenate'] ?? true))
            ->copyable((bool) ($options['copyable'] ?? false))
            ->readOnly((bool) ($options['readonly'] ?? false))
            ->allowedRemoteUrls(config('filament-advanced-text.remote_urls', []));

        return $field;
    }

    protected function makeEntityReference(string $name, array $options): EntityReferenceSelect
    {
        $references = $options['reference'] ?? [];
        if (is_string($references)) {
            $references = json_decode($references, true, flags: JSON_THROW_ON_ERROR);
        }
        if (! is_array($references) || ! array_is_list($references)) {
            throw new InvalidArgumentException(__('filament-page-builder::errors.entity_aliases_required'));
        }
        $configured = config('filament-page-builder.entities', []);
        $allowed = [];
        foreach ($references as $alias) {
            if (isset($configured[$alias])) {
                $allowed[$alias] = $configured[$alias];
            }
        }

        return EntityReferenceSelect::make($name)->references($allowed, (bool) ($options['is_multiple'] ?? false));
    }
}
