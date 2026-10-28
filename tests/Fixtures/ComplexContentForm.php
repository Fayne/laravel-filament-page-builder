<?php

use Fayne\PageBuilder\Contracts\ContentDefinitionRepository;
use Fayne\PageBuilder\Filament\Forms\Components\ContentBuilder;
use Filament\Actions\Concerns\InteractsWithActions;
use Filament\Actions\Contracts\HasActions;
use Filament\Forms\Concerns\InteractsWithForms;
use Filament\Forms\Contracts\HasForms;
use Filament\Schemas\Schema;
use Livewire\Component;

class ComplexContentForm extends Component implements HasActions, HasForms
{
    use InteractsWithActions;
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
                return config('complex-test.fields');
            }
        }, ['hero' => 'Hero'])])->statePath('data');
    }

    public function save(): void
    {
        $this->saved = $this->form->getState();
    }

    public function render(): string
    {
        return '<div>{{ $this->form }}<x-filament-actions::modals /></div>';
    }
}
