<?php

namespace Fayne\PageBuilder\Filament\Forms;

use Fayne\PageBuilder\Filament\Forms\Components\FieldOptionsInput;
use Fayne\PageBuilder\Filament\Forms\Components\FieldTypeSelect;
use Fayne\PageBuilder\Filament\Forms\Components\ValidationRulesInput;
use Filament\Actions\Action;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\TextInput;
use Filament\Schemas\Components\Tabs;
use Filament\Schemas\Components\Tabs\Tab;
use Filament\Schemas\Components\Utilities\Get;
use Illuminate\Support\Facades\Gate;

final class FieldDefinitionSchema
{
    public static function owner(string $foreignKey, string $model, string $resource, string $label): Select
    {
        return Select::make($foreignKey)->label($label)->searchable()->required()
            ->default(function () use ($foreignKey, $model): mixed {
                $id = request()->query($foreignKey);
                if ((! is_int($id) && ! is_string($id)) || ! ctype_digit((string) $id)) {
                    return null;
                }
                $owner = $model::query()->find($id);

                return $owner && Gate::allows('view', $owner) ? $owner->getKey() : null;
            })
            ->options(fn () => $model::query()->pluck('name', 'id'))
            ->helperText(__('filament-page-builder::fields.shared_help'))
            ->hintAction(Action::make('manageDefinition')->label(__('filament-page-builder::fields.manage_definition'))->icon('heroicon-m-arrow-top-right-on-square')
                ->visible(fn (Get $get): bool => filled($get($foreignKey)) && Gate::allows('view', $model::find($get($foreignKey))))
                ->url(fn (Get $get): string => $resource::getUrl('edit', ['record' => $get($foreignKey)]))->openUrlInNewTab());
    }

    public static function components(?string $widgetModel = null): array
    {
        return [Tabs::make('fieldDefinition')->columnSpanFull()->tabs([
            Tab::make(__('filament-page-builder::fields.basic'))->columns(2)->schema([
                TextInput::make('title')->label(__('filament-page-builder::fields.name'))->required(),
                TextInput::make('slug')->label(__('filament-page-builder::fields.key'))->helperText(__('filament-page-builder::fields.key_help')),
                FieldTypeSelect::make('resolver'),
                TextInput::make('weight')->label(__('filament-page-builder::fields.sort'))->numeric()->default(99),
            ]),
            Tab::make(__('filament-page-builder::fields.options'))->schema([
                FieldOptionsInput::make('options')->widgetModel($widgetModel)->label(__('filament-page-builder::fields.options'))->columnSpanFull()->mutateDehydratedStateUsing(function (array $state, Get $get): array {
                    if ($get('resolver') === 'image') {
                        $state['is_image'] = true;
                    } elseif ($get('resolver') === 'file' && array_key_exists('is_image', $state)) {
                        $state['is_image'] = false;
                    }

                    return $state;
                }),
            ]),
            Tab::make(__('filament-page-builder::fields.validation'))->schema([ValidationRulesInput::make('rules')->columnSpanFull()]),
        ])];
    }
}
