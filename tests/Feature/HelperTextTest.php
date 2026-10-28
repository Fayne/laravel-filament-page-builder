<?php

require_once __DIR__.'/../Fixtures/ComplexContentForm.php';

use Fayne\PageBuilder\Contracts\ContentDefinitionRepository;
use Fayne\PageBuilder\Filament\Forms\Components\ContentBuilder;
use Filament\Schemas\Schema;
use Livewire\Livewire;

class HelperTextForm extends ComplexContentForm
{
    public function form(Schema $schema): Schema
    {
        $definitions = new class implements ContentDefinitionRepository
        {
            public function fields(string $layout, bool $widget = false): array
            {
                $fields = [['name' => 'classes', 'resolver' => 'text', 'options' => ['help' => config('helper-test.help')]]];
                if (! $widget) {
                    $fields[] = ['name' => 'items', 'resolver' => 'widget', 'options' => ['widgets' => ['card'], 'help' => config('helper-test.help')]];
                }

                return $fields;
            }
        };

        return $schema->components([ContentBuilder::make('components')->definitions($definitions, ['hero' => 'Hero'], ['card' => 'Card'])])->statePath('data');
    }
}

it('renders HTML helper links for component fields widget containers and nested widget fields', function () {
    config(['helper-test.help' => '<a href="https://tailwindcss.com/docs/styling-with-utility-classes" target="_blank">Tailwind manual</a>']);
    $raw = [['layout' => 'hero', 'key' => 'hero', 'attributes' => ['classes' => '', 'items' => [['layout' => 'card', 'key' => 'card', 'attributes' => ['classes' => '']]]]]];
    $form = Livewire::test(HelperTextForm::class, ['content' => $raw]);
    $document = new DOMDocument;
    @$document->loadHTML('<?xml encoding="UTF-8">'.$form->html());
    $links = (new DOMXPath($document))->query('//a[@href="https://tailwindcss.com/docs/styling-with-utility-classes"]');

    expect($links->length)->toBe(3);
    foreach ($links as $link) {
        expect($link->textContent)->toBe('Tailwind manual');
    }
    $form->call('save')->assertHasNoErrors()->assertSet('saved.components', $raw);
});

it('preserves trusted internal helper HTML without filtering in component and widget hints', function () {
    $html = '<details data-help-id="cms-help"><summary>说明</summary><a href="/manual" target="_blank" onclick="return false;">查看说明</a></details>';
    config(['helper-test.help' => $html]);
    $raw = [['layout' => 'hero', 'key' => 'hero', 'attributes' => ['classes' => '', 'items' => [['layout' => 'card', 'key' => 'card', 'attributes' => ['classes' => '']]]]]];
    $form = Livewire::test(HelperTextForm::class, ['content' => $raw]);

    expect(substr_count($form->html(), $html))->toBe(3);
});
