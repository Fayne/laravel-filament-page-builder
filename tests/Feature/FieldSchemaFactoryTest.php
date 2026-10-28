<?php

use Fayne\PageBuilder\Contracts\ContentDefinitionRepository;
use Fayne\PageBuilder\Filament\Forms\Components\ContentBuilder;
use Fayne\PageBuilder\Support\FieldResolverContext;
use Fayne\PageBuilder\Support\FieldSchemaFactory;
use Filament\Forms\Components\ColorPicker;
use Filament\Forms\Components\DatePicker;
use Filament\Forms\Components\DateTimePicker;
use Filament\Forms\Components\KeyValue;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\TagsInput;
use Filament\Forms\Components\Textarea;
use Filament\Forms\Components\TextInput;
use Filament\Schemas\Schema;

it('builds nested widgets using the shipped field registry', function () {
    $definitions = new class implements ContentDefinitionRepository
    {
        public function fields(string $layout, bool $widget = false): array
        {
            return [['name' => 'title', 'resolver' => 'text']];
        }
    };
    $context = new FieldResolverContext($definitions, ['card' => 'Card'], []);
    expect((new FieldSchemaFactory)->make(['name' => 'cards', 'resolver' => 'widget'], $context))
        ->toBeInstanceOf(ContentBuilder::class);
});

it('applies widget validation rules for the current create or edit operation', function (string $operation, string $expected, string $excluded) {
    $definitions = new class implements ContentDefinitionRepository
    {
        public function fields(string $layout, bool $widget = false): array
        {
            return [];
        }
    };
    $field = (new FieldSchemaFactory)->make([
        'name' => 'cards', 'resolver' => 'widget',
        'rules' => ['rules' => ['array'], 'creationRules' => ['min:2'], 'updateRules' => ['max:3']],
    ], new FieldResolverContext($definitions, ['card' => 'Card'], []));
    $field->container(Schema::make()->operation($operation));

    expect($field->getValidationRules())->toContain('array', $expected)->not->toContain($excluded);
})->with([['create', 'min:2', 'max:3'], ['edit', 'max:3', 'min:2']]);

it('applies numeric bounds and validation rules from database definitions', function () {
    $field = (new FieldSchemaFactory)->make([
        'name' => 'amount', 'title' => '金额', 'resolver' => 'number',
        'options' => ['min' => 0, 'max' => 100, 'step' => 0.5, 'required' => true],
        'rules' => ['rules' => ['numeric', 'min:0']],
    ]);
    expect($field)->toBeInstanceOf(TextInput::class)
        ->and($field->getName())->toBe('amount')
        ->and($field->isRequired())->toBeTrue()
        ->and($field->getMinValue())->toBe(0)
        ->and($field->getMaxValue())->toBe(100)
        ->and($field->getStep())->toBe(0.5);
});

it('keeps option values and enables multiple selection', function () {
    $field = (new FieldSchemaFactory)->make(['name' => 'tags', 'resolver' => 'multiselect', 'options' => ['options' => ['a' => '甲', 'b' => '乙'], 'searchable' => true]]);
    expect($field)->toBeInstanceOf(Select::class)
        ->and($field->isMultiple())->toBeTrue()
        ->and($field->getOptions())->toBe(['a' => '甲', 'b' => '乙']);
});

it('keeps false and zero defaults instead of treating them as missing', function () {
    $field = (new FieldSchemaFactory)->make(['name' => 'enabled', 'resolver' => 'boolean', 'options' => ['default_value' => false]]);
    expect($field->getDefaultState())->toBeFalse();
});

it('rejects unsupported fields and options instead of silently changing behavior', function (array $definition) {
    (new FieldSchemaFactory)->make($definition);
})->with([
    [['name' => 'x', 'resolver' => 'invented']],
    [['name' => 'x', 'resolver' => 'text', 'options' => ['invented' => true]]],
])->throws(InvalidArgumentException::class);

it('builds standard fields without Nova classes', function (string $resolver, string $class) {
    expect((new FieldSchemaFactory)->make(['name' => 'value', 'resolver' => $resolver]))->toBeInstanceOf($class);
})->with([
    ['text', TextInput::class],
    ['textarea', Textarea::class],
    ['email', TextInput::class],
    ['date', DatePicker::class],
    ['datetime', DateTimePicker::class],
    ['color', ColorPicker::class],
    ['key_value', KeyValue::class],
    ['tag', TagsInput::class],
]);

it('applies operation specific rules alongside shared rules', function (string $operation, string $expected, string $excluded) {
    $field = (new FieldSchemaFactory)->make(['name' => 'value', 'resolver' => 'text', 'rules' => ['rules' => ['string'], 'creationRules' => ['min:3'], 'updateRules' => ['max:8']]]);
    $field->container(Schema::make()->operation($operation));
    expect($field->getValidationRules())->toContain('string', $expected)->not->toContain($excluded);
})->with([['create', 'min:3', 'max:8'], ['edit', 'max:8', 'min:3']]);

it('applies legacy textarea row configuration', function () {
    $field = (new FieldSchemaFactory)->make(['name' => 'description', 'resolver' => 'textarea', 'options' => ['rows' => 6]]);
    expect($field->getRows())->toBe(6);
});
