<?php

use Fayne\CMS\Support\ContentFieldTypes;
use Fayne\PageBuilder\Fields\Resolvers\TextResolver;
use Fayne\PageBuilder\Filament\Forms\Components\FieldTypeSelect;
use Fayne\PageBuilder\Support\FieldOptionDefinitions;
use Fayne\PageBuilder\Support\FieldSchemaFactory;
use Filament\Schemas\Schema;
use Illuminate\Database\Eloquent\Model;

it('does not offer sensitive fields in the shared page and menu field selector', function () {
    expect(ContentFieldTypes::options())->not->toHaveKey('sensitive')
        ->and(FieldTypeSelect::make('resolver')->container(Schema::make())->getOptions())->not->toHaveKey('sensitive')
        ->and(FieldOptionDefinitions::all())->not->toHaveKey('sensitive');
});

it('rejects a sensitive definition instead of creating a sensitive input', function () {
    expect(fn () => (new FieldSchemaFactory)->make(['name' => 'secret', 'resolver' => 'sensitive']))
        ->toThrow(InvalidArgumentException::class);
});

it('ignores sensitive entries in previously published host configuration even for an existing field', function () {
    config(['filament-cms.fields.types.sensitive' => ['label' => 'Sensitive', 'enabled' => true, 'resolver' => TextResolver::class]]);
    $record = new class extends Model {};
    $record->forceFill(['resolver' => 'sensitive']);

    expect(ContentFieldTypes::options())->not->toHaveKey('sensitive')
        ->and(FieldTypeSelect::make('resolver')->container(Schema::make()->model($record))->getOptions())->not->toHaveKey('sensitive');
    expect(fn () => (new FieldSchemaFactory)->make(['name' => 'secret', 'resolver' => 'sensitive']))
        ->toThrow(InvalidArgumentException::class);
});
