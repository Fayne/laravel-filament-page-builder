<?php

use Fayne\PageBuilder\Filament\Forms\Components\FieldOptionsInput;
use Fayne\PageBuilder\Filament\Forms\FieldDefinitionSchema;
use Fayne\PageBuilder\Support\FieldSchemaFactory;
use Filament\Actions\Concerns\InteractsWithActions;
use Filament\Actions\Contracts\HasActions;
use Filament\Forms\Concerns\InteractsWithForms;
use Filament\Forms\Contracts\HasForms;
use Filament\Schemas\Components\Tabs;
use Filament\Schemas\Schema;
use Livewire\Component;
use Livewire\Livewire;

beforeEach(fn () => app()->setLocale('zh_CN'));

class FriendlyDefinitionForm extends Component implements HasActions, HasForms
{
    use InteractsWithActions;
    use InteractsWithForms;

    public array $data = [];

    public array $saved = [];

    public function mount(array $definition = []): void
    {
        $this->form->fill($definition);
    }

    public function form(Schema $schema): Schema
    {
        return $schema->components(FieldDefinitionSchema::components())->statePath('data');
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

it('offers human readable field types while retaining resolver identifiers', function () {
    $editor = Livewire::test(FriendlyDefinitionForm::class, ['definition' => ['title' => 'Headline', 'resolver' => 'text']]);
    $field = $editor->instance()->form->getComponent('resolver');
    expect($field->getOptions())->toMatchArray(['text' => '文本框', 'textarea' => '多行文本框', 'rich_text' => '富文本', 'image' => '图片', 'file' => '文件']);
    $editor->call('save')->assertHasNoErrors()->assertSet('saved.resolver', 'text');
});

it('stores image selection as the compatible file resolver and image option', function () {
    Livewire::test(FriendlyDefinitionForm::class, ['definition' => ['title' => 'Picture', 'resolver' => 'image']])
        ->call('save')->assertHasNoErrors()->assertSet('saved.resolver', 'file')->assertSet('saved.options.is_image', true);
});

it('reopens image fields without losing options', function () {
    $options = ['disk' => 'public', 'is_image' => true, 'is_multiple' => true];
    $editor = Livewire::test(FriendlyDefinitionForm::class, ['definition' => ['title' => 'Picture', 'resolver' => 'file', 'options' => $options]]);
    $editor->assertSet('data.resolver', 'image')->call('save')->assertHasNoErrors()->assertSet('saved.resolver', 'file')->assertSet('saved.options', $options);
});

it('keeps operation specific rules custom regex and unknown metadata on an unchanged save', function () {
    $rules = ['rules' => ['required', 'regex:/^(a|b):c$/'], 'creationRules' => ['min:2'], 'updateRules' => 'nullable|max:30', 'custom' => ['preserve' => true]];
    Livewire::test(FriendlyDefinitionForm::class, ['definition' => ['title' => 'Name', 'resolver' => 'text', 'rules' => $rules]])
        ->assertSee('通用规则')->assertSee('创建规则')->assertSee('更新规则')->call('save')->assertHasNoErrors()->assertSet('saved.rules', $rules);
});

it('accepts advanced JSON but rejects malformed rules and incomplete common parameters', function () {
    $editor = Livewire::test(FriendlyDefinitionForm::class, ['definition' => ['title' => 'Name', 'resolver' => 'text']]);
    $editor->set('data.rules', '{"rules":["required","max:20"]}')->call('save')->assertHasNoErrors()->assertSet('saved.rules.rules', ['required', 'max:20']);
    $editor->set('data.rules', 'broken json')->call('save')->assertHasErrors(['data.rules']);
    $editor->set('data.rules', ['rules' => ['max:']])->call('save')->assertHasErrors(['data.rules']);
});

it('deduplicates identical rules per group on save without dropping distinct parameters or metadata', function () {
    $rules = ['rules' => ['required', 'required', 'max:20', 'max:30', 'max:20', 'regex:/^(a|b)$/', 'regex:/^(a|b)$/'], 'creationRules' => ['required', 'required'], 'updateRules' => 'nullable|nullable|max:30|max:30', 'custom' => ['keep' => true]];
    Livewire::test(FriendlyDefinitionForm::class, ['definition' => ['title' => 'Name', 'resolver' => 'text', 'rules' => $rules]])
        ->call('save')->assertHasNoErrors()->assertSet('saved.rules', [
            'rules' => ['required', 'max:20', 'max:30', 'regex:/^(a|b)$/'],
            'creationRules' => ['required'],
            'updateRules' => 'nullable|max:30',
            'custom' => ['keep' => true],
        ]);
});

it('preserves opaque regex pipe strings when saving rules', function () {
    $rules = ['rules' => 'required|regex:/^(a|b)$/|required'];
    Livewire::test(FriendlyDefinitionForm::class, ['definition' => ['title' => 'Name', 'resolver' => 'text', 'rules' => $rules]])
        ->call('save')->assertHasNoErrors()->assertSet('saved.rules', $rules);
});

it('renders type dependent options while preserving untouched and advanced configuration', function () {
    $options = ['placeholder' => '请输入', 'max_length' => 80, 'legacy' => ['keep' => true]];
    $editor = Livewire::test(FriendlyDefinitionForm::class, ['definition' => ['title' => 'Name', 'resolver' => 'text', 'options' => $options]])
        ->assertSee('字段选项')->assertSee('最大长度')->call('save')->assertHasNoErrors()->assertSet('saved.options', $options);
    expect($editor->instance()->form->getComponent('options'))->toBeInstanceOf(FieldOptionsInput::class);
    $editor->set('data.resolver', 'number')->set('data.options', '{"min":0,"max":10,"step":0.5}')
        ->call('save')->assertHasNoErrors()->assertSet('saved.options', ['min' => 0, 'max' => 10, 'step' => 0.5]);
});

it('allows configured type names and hiding types without registering unsupported runtimes', function () {
    config()->set('filament-cms.fields.types.text.label', '单行文字');
    config()->set('filament-cms.fields.types.http.enabled', false);
    $editor = Livewire::test(FriendlyDefinitionForm::class, ['definition' => ['title' => 'Name', 'resolver' => 'text']]);
    $types = $editor->instance()->form->getComponent('resolver')->getOptions();
    expect($types['text'])->toBe('单行文字')->and($types)->not->toHaveKey('ckeditor')->not->toHaveKey('tiptap')->not->toHaveKey('http');
});

it('rejects malformed structured options before saving', function () {
    Livewire::test(FriendlyDefinitionForm::class, ['definition' => ['title' => 'Cascade', 'resolver' => 'cascade']])
        ->set('data.options', '{"options":"{broken"}')->call('save')->assertHasErrors(['data.options']);
});

it('uses saved visual options when building the actual content fields', function () {
    $editor = Livewire::test(FriendlyDefinitionForm::class, ['definition' => ['title' => 'Count', 'slug' => 'count', 'resolver' => 'number', 'options' => ['min' => 0, 'max' => 10, 'step' => 0.5]]])
        ->call('save')->assertHasNoErrors();
    $saved = $editor->get('saved');
    $field = (new FieldSchemaFactory)->make($saved + ['name' => 'count']);
    expect($field->getMinValue())->toBe(0)->and($field->getMaxValue())->toBe(10)->and($field->getStep())->toBe(0.5);
});

it('groups the shared definition editor into basic options and validation tabs without changing state paths', function () {
    $editor = Livewire::test(FriendlyDefinitionForm::class, ['definition' => ['title' => 'Name', 'resolver' => 'text', 'options' => ['max_length' => 100], 'rules' => ['rules' => ['required']]]]);
    $tabs = $editor->instance()->form->getComponents()[0];
    expect($tabs)->toBeInstanceOf(Tabs::class);
    expect(array_map(fn ($tab) => $tab->getLabel(), $tabs->getChildSchema()->getComponents()))->toBe(['基本设置', '字段选项', '验证规则']);
    $editor->call('save')->assertHasNoErrors()->assertSet('saved.options.max_length', 100)->assertSet('saved.rules.rules', ['required']);
});
