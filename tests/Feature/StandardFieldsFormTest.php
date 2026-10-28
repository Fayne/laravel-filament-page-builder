<?php

use Fayne\PageBuilder\Contracts\ContentDefinitionRepository;
use Fayne\PageBuilder\Filament\Forms\Components\ContentBuilder;
use Filament\Forms\Concerns\InteractsWithForms;
use Filament\Forms\Contracts\HasForms;
use Filament\Schemas\Schema;
use Livewire\Component;
use Livewire\Livewire;

class StandardFieldsForm extends Component implements HasForms
{
    use InteractsWithForms;

    public array $data = [];

    public array $saved = [];

    public function mount(array $content): void
    {
        $this->form->fill(['components' => $content]);
    }

    public function form(Schema $schema): Schema
    {
        return $schema->components([ContentBuilder::make('components')->definitions(new class implements ContentDefinitionRepository
        {
            public function fields(string $layout, bool $widget = false): array
            {
                return [
                    ['name' => 'number', 'resolver' => 'number'], ['name' => 'boolean', 'resolver' => 'boolean'],
                    ['name' => 'choice', 'resolver' => 'select', 'options' => ['options' => ['0' => 'Zero', '1' => 'One']]],
                    ['name' => 'choices', 'resolver' => 'multiselect', 'options' => ['options' => ['a' => 'A', 'b' => 'B']]],
                    ['name' => 'date', 'resolver' => 'date'], ['name' => 'datetime', 'resolver' => 'datetime'],
                    ['name' => 'color', 'resolver' => 'color'], ['name' => 'tags', 'resolver' => 'tag'], ['name' => 'pairs', 'resolver' => 'key_value'],
                ];
            }
        }, ['all' => 'All'])])->statePath('data');
    }

    public function save(): void
    {
        $this->saved = $this->form->getState();
    }

    public function render(): string
    {
        return '<div>{{ $this->form }}</div>';
    }
}

it('preserves standard field values and their scalar types through a real form', function () {
    $raw = [['layout' => 'all', 'key' => 'key', 'attributes' => ['number' => 0, 'boolean' => false, 'choice' => '0', 'choices' => ['b', 'a'], 'date' => '2026-10-20', 'datetime' => '2026-10-20 10:30:00', 'color' => '#ffffff', 'tags' => ['a', 'b'], 'pairs' => ['x' => '0']]]];
    $form = Livewire::test(StandardFieldsForm::class, ['content' => $raw])->call('save')->assertHasNoErrors();
    expect($form->get('saved.components'))->toBe($raw);
});
