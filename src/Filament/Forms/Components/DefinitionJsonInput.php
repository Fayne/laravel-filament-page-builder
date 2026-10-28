<?php

namespace Fayne\PageBuilder\Filament\Forms\Components;

use Closure;
use Filament\Forms\Components\Textarea;

class DefinitionJsonInput extends Textarea
{
    protected function setUp(): void
    {
        parent::setUp();
        $this->rules(['json', fn (): Closure => function (string $attribute, mixed $value, Closure $fail): void {
            if (! is_string($value) || ! is_array(json_decode($value, true))) {
                $fail(__('filament-page-builder::ui.field_configuration_must_be_a_json_object_or_array'));
            }
        }]);
        $this->formatStateUsing(fn ($state) => json_encode($state ?? [], JSON_UNESCAPED_UNICODE | JSON_PRETTY_PRINT | JSON_THROW_ON_ERROR));
        $this->dehydrateStateUsing(fn ($state) => json_decode($state, true, flags: JSON_THROW_ON_ERROR));
    }
}
