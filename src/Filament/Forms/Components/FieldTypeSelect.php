<?php

namespace Fayne\PageBuilder\Filament\Forms\Components;

use Fayne\CMS\Support\ContentFieldTypes;
use Filament\Forms\Components\Select;
use Filament\Schemas\Components\Utilities\Get;

class FieldTypeSelect extends Select
{
    protected function setUp(): void
    {
        parent::setUp();
        $this->label(__('filament-page-builder::fields.type'))->required()->searchable()->live()
            ->options(function (): array {
                $options = ContentFieldTypes::options();
                $record = $this->getRecord();
                $original = $record?->resolver;
                if ($original === 'file' && ($record?->options['is_image'] ?? false)) {
                    $original = 'image';
                }
                if (in_array($original, array_keys(ContentFieldTypes::builtInOptions()), true) && ! isset($options[$original])) {
                    $options[$original] = ContentFieldTypes::label($original);
                }

                return $options;
            })
            ->formatStateUsing(function ($state, Get $get) {
                $options = $get('options');
                $options = is_string($options) ? json_decode($options, true) : $options;

                return $state === 'file' && ($options['is_image'] ?? false) ? 'image' : $state;
            })
            ->dehydrateStateUsing(fn ($state) => $state === 'image' ? 'file' : $state);
    }
}
