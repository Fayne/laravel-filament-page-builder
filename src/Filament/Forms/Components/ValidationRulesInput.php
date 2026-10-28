<?php

namespace Fayne\PageBuilder\Filament\Forms\Components;

use Closure;
use Fayne\PageBuilder\Support\ValidationRuleConfig;
use Filament\Forms\Components\Field;
use InvalidArgumentException;

class ValidationRulesInput extends Field
{
    protected string $view = 'filament-page-builder::validation-rules-input';

    protected function setUp(): void
    {
        parent::setUp();
        $this->label(__('filament-page-builder::fields.validation'))->default([])
            ->rules([fn (): Closure => function (string $attribute, mixed $value, Closure $fail): void {
                try {
                    ValidationRuleConfig::decode($value);
                } catch (InvalidArgumentException $exception) {
                    $fail($exception->getMessage());
                }
            }])
            ->dehydrateStateUsing(fn ($state) => ValidationRuleConfig::decode($state));
    }
}
