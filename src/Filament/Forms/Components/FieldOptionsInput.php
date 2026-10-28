<?php

namespace Fayne\PageBuilder\Filament\Forms\Components;

use Closure;
use Fayne\PageBuilder\Support\FieldOptionDefinitions;
use Filament\Schemas\Components\Utilities\Get;
use Illuminate\Support\Facades\Gate;

class FieldOptionsInput extends DefinitionJsonInput
{
    protected string $view = 'filament-page-builder::field-options-input';

    protected ?string $widgetModel = null;

    protected function setUp(): void
    {
        parent::setUp();
        $this->rule(fn (Get $get): Closure => function (string $attribute, mixed $value, Closure $fail) use ($get): void {
            $options = is_string($value) ? json_decode($value, true) : $value;
            if (! is_array($options)) {
                return; // The inherited JSON rule reports invalid root configuration.
            }
            foreach (FieldOptionDefinitions::all()[$get('resolver')] ?? [] as $key => [$label, $kind]) {
                if (! array_key_exists($key, $options)) {
                    continue;
                }
                $item = $options[$key];
                if ($kind === 'json' && $key === 'options' && is_string($item)) {
                    try {
                        $item = json_decode($item, true, flags: JSON_THROW_ON_ERROR);
                    } catch (\JsonException) {
                        $fail(__('filament-page-builder::ui.invalid_json', ['label' => $label]));
                    }
                }
                if (in_array($kind, ['pairs', 'lines', 'multiple'], true) && ! is_array($item) && ! ($key === 'toolbar' && is_string($item) && is_array(json_decode($item, true)))) {
                    $fail(__('filament-page-builder::ui.must_be_a_list_or_key_value_configuration', ['label' => $label]));
                }
                if (in_array($kind, ['integer', 'number'], true) && ! is_numeric($item)) {
                    $fail(__('filament-page-builder::ui.must_be_a_number', ['label' => $label]));
                }
            }
        });
    }

    public function widgetModel(?string $model): static
    {
        $this->widgetModel = $model;

        return $this;
    }

    public function getResolverStatePath(): string
    {
        return preg_replace('/[^.]+$/', 'resolver', $this->getStatePath());
    }

    public function getOptionDefinitions(): array
    {
        $widgets = [];
        if ($this->widgetModel && Gate::allows('viewAny', $this->widgetModel)) {
            $widgets = $this->widgetModel::query()->pluck('name', 'id')->all();
        }

        return FieldOptionDefinitions::all($widgets);
    }
}
