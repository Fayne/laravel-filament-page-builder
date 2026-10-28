<?php

use Fayne\PageBuilder\Contracts\ContentDefinitionRepository;
use Fayne\PageBuilder\Filament\Forms\Components\ContentBuilder;
use Filament\Actions\Concerns\InteractsWithActions;
use Filament\Actions\Contracts\HasActions;
use Filament\Actions\Testing\TestAction;
use Filament\Forms\Concerns\InteractsWithForms;
use Filament\Forms\Contracts\HasForms;
use Filament\Schemas\Schema;
use Livewire\Component;
use Livewire\Livewire;

class TestContentForm extends Component implements HasActions, HasForms
{
    use InteractsWithActions;
    use InteractsWithForms;

    public array $data = [];

    public array $saved = [];

    public function mount(array $content = []): void
    {
        $this->form->fill(['components' => $content]);
    }

    public function form(Schema $schema): Schema
    {
        return $schema->components([
            ContentBuilder::make('components')->definitions(new class implements ContentDefinitionRepository
            {
                public function fields(string $layout, bool $widget = false): array
                {
                    return match ($layout) {
                        'hero' => [
                            ['name' => 'title', 'resolver' => 'text', 'rules' => ['rules' => ['required']]],
                            ['name' => 'items', 'resolver' => 'widget', 'options' => ['widgets' => ['card'], 'help' => '请选择展示卡片', 'fullWidth' => true]],
                        ],
                        'card' => [['name' => 'title', 'resolver' => 'text'], ['name' => 'links', 'resolver' => 'widget', 'options' => ['widgets' => ['link']]]],
                        'link' => [['name' => 'url', 'resolver' => 'text']],
                        default => [],
                    };
                }
            }, ['hero' => '首屏'], ['card' => '卡片', 'link' => '链接']),
        ])->statePath('data');
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

beforeEach(fn () => app()->setLocale('zh_CN'));

it('round trips a real Filament form including unknown attributes and nested widget keys', function () {
    $content = [[
        'layout' => 'hero', 'key' => 'parent-key', 'internalTitle' => '首屏',
        'attributes' => ['title' => '欢迎', 'unknown' => ['x' => false], 'items' => [[
            'layout' => 'card', 'key' => 'child-key', 'attributes' => ['title' => '卡片', 'old' => 0],
        ]]],
    ]];
    Livewire::test(TestContentForm::class, ['content' => $content])
        ->call('save')->assertHasNoErrors()->assertSet('saved.components', $content);
});

it('validates content field rules before saving', function () {
    $content = [['layout' => 'hero', 'key' => 'key', 'attributes' => ['title' => '', 'items' => []]]];
    Livewire::test(TestContentForm::class, ['content' => $content])->call('save')->assertHasErrors();
});

it('shows missing definitions and blocks saving instead of discarding unknown blocks', function () {
    Livewire::test(TestContentForm::class, ['content' => [['layout' => 'deleted', 'key' => 'key', 'attributes' => []]]])
        ->assertSee('定义缺失')->call('save')->assertHasErrors()->assertSet('saved', []);
});

it('does not add missing optional fields to existing blocks when saving unchanged', function () {
    $content = [['layout' => 'hero', 'key' => 'existing', 'attributes' => ['title' => 'Welcome']]];
    Livewire::test(TestContentForm::class, ['content' => $content])->call('save')->assertHasNoErrors()->assertSet('saved.components', $content);
});

it('clones a real editor block with new persistent keys for its nested widgets', function () {
    $content = [['layout' => 'hero', 'key' => 'parent', 'attributes' => ['title' => 'Welcome', 'items' => [['layout' => 'card', 'key' => 'child', 'attributes' => ['title' => 'Card']]]]]];
    $test = Livewire::test(TestContentForm::class, ['content' => $content]);
    $key = array_key_first($test->get('data.components'));
    $test->callAction(TestAction::make('clone')->schemaComponent('components')->arguments(['item' => $key]))->call('save')->assertHasNoErrors();
    $saved = $test->get('saved.components');
    expect($saved)->toHaveCount(2)->and($saved[0]['key'])->toBe('parent')->and($saved[1]['key'])->not->toBe('parent')
        ->and($saved[1]['attributes']['items'][0]['key'])->not->toBe('child');
});

it('round trips two widget levels through the real editor', function () {
    $content = [['layout' => 'hero', 'key' => 'root', 'attributes' => ['title' => 'Welcome', 'items' => [['layout' => 'card', 'key' => 'card', 'attributes' => ['title' => 'Card', 'links' => [['layout' => 'link', 'key' => 'link', 'attributes' => ['url' => '/about']]]]]]]]];
    Livewire::test(TestContentForm::class, ['content' => $content])->call('save')->assertHasNoErrors()->assertSet('saved.components', $content);
});

class UnsupportedContentForm extends TestContentForm
{
    public function form(Schema $schema): Schema
    {
        return $schema->components([ContentBuilder::make('components')->definitions(new class implements ContentDefinitionRepository
        {
            public function fields(string $layout, bool $widget = false): array
            {
                return [['name' => 'secret', 'resolver' => 'unsupported_secret']];
            }
        }, ['legacy' => 'Legacy'])])->statePath('data');
    }
}

it('renders unsupported field diagnostics and refuses to overwrite original content', function () {
    $raw = [['layout' => 'legacy', 'key' => 'original', 'attributes' => ['secret' => 'preserve']]];
    $form = Livewire::test(UnsupportedContentForm::class, ['content' => $raw])->assertSee(__('filament-page-builder::errors.invalid_field_resolver', ['type' => 'unsupported_secret']));
    expect(json_encode($form->get('data')))->not->toContain('preserve');
    $form->call('save')->assertHasErrors()->assertSet('saved', []);
});

class SharedSlugContentForm extends TestContentForm
{
    public function form(Schema $schema): Schema
    {
        return $schema->components([ContentBuilder::make('components')->definitions(new class implements ContentDefinitionRepository
        {
            public function fields(string $layout, bool $widget = false): array
            {
                return $widget ? [['name' => 'title', 'resolver' => 'text']] : [['name' => 'items', 'resolver' => 'widget', 'options' => ['widgets' => ['hero']]]];
            }
        }, ['hero' => 'Hero'], ['hero' => 'Hero widget'])])->statePath('data');
    }
}

it('allows a component and widget to share a slug without a false recursion error', function () {
    $raw = [['layout' => 'hero', 'key' => 'parent', 'attributes' => ['items' => [['layout' => 'hero', 'key' => 'child', 'attributes' => ['title' => 'Child']]]]]];
    Livewire::test(SharedSlugContentForm::class, ['content' => $raw])->call('save')->assertHasNoErrors()->assertSet('saved.components', $raw);
});

it('collapses existing components and nested widgets while retaining descriptive headers', function () {
    $content = [['layout' => 'hero', 'key' => 'parent', 'internalTitle' => 'Welcome', 'attributes' => ['title' => 'Welcome', 'items' => [['layout' => 'card', 'key' => 'child', 'internalTitle' => 'First card', 'attributes' => ['title' => 'Card']]]]]];
    $editor = Livewire::test(TestContentForm::class, ['content' => $content]);
    $builder = $editor->instance()->form->getComponent('components');
    expect($builder->isCollapsible())->toBeTrue()->and($builder->isCollapsed())->toBeTrue();
    $editor->assertSee('Welcome [首屏]')->assertSee('First card [卡片]')->assertSee('全部展开')->assertSee('全部收起');
    $editor->call('save')->assertHasNoErrors()->assertSet('saved.components', $content);
});

it('opens a newly added block and updates its header when the internal title changes', function () {
    $editor = Livewire::test(TestContentForm::class);
    $editor->callAction(TestAction::make('add')->schemaComponent('components')->arguments(['block' => 'hero']))->assertHasNoErrors();
    expect($editor->instance()->form->getComponent('components')->isCollapsed())->toBeFalse();
    $key = array_key_first($editor->get('data.components'));
    $editor->set('data.components.'.$key.'.data.internalTitle', 'New headline')->assertSee('New headline [首屏]');
    expect($editor->instance()->form->getComponent('components')->isCollapsible())->toBeTrue();
});

it('applies nested widget help options in the real content editor', function () {
    Livewire::test(TestContentForm::class, ['content' => [['layout' => 'hero', 'key' => 'hero', 'attributes' => ['title' => 'Title', 'items' => []]]]])
        ->assertSee('请选择展示卡片');
});
