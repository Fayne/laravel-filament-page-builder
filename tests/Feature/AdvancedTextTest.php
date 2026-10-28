<?php

use Fayne\AdvancedText\Forms\Components\AdvancedText;
use Fayne\PageBuilder\Contracts\ContentDefinitionRepository;
use Fayne\PageBuilder\Support\FieldSchemaFactory;
use Fayne\PageBuilder\Support\SensitiveContent;
use Filament\Forms\Concerns\InteractsWithForms;
use Filament\Forms\Contracts\HasForms;
use Filament\Schemas\Schema;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Schema\Blueprint;
use Livewire\Component;
use Livewire\Livewire;

class AdvancedTextForm extends Component implements HasForms
{
    use InteractsWithForms;

    public array $data = [];

    public array $saved = [];

    public array $options = [];

    public array $rules = [];

    public function mount(mixed $value = null, array $options = [], array $rules = []): void
    {
        $this->options = $options;
        $this->rules = $rules;
        $this->form->fill(['value' => $value]);
    }

    public function form(Schema $schema): Schema
    {
        return $schema->components([(new FieldSchemaFactory)->make(['name' => 'value', 'resolver' => 'text', 'options' => $this->options, 'rules' => $this->rules])])->statePath('data');
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

it('maps database text options to the reusable AdvancedText field', function () {
    $field = (new FieldSchemaFactory)->make(['name' => 'title', 'resolver' => 'text', 'options' => ['options' => ['首页' => '/home'], 'copyable' => true, 'remote_options_url' => '/suggestions']]);
    expect($field)->toBeInstanceOf(AdvancedText::class);
    expect($field->getAdvancedTextConfig())->toMatchArray(['mapping' => ['首页' => '/home'], 'remoteUrl' => '/suggestions', 'copyable' => true]);
});

it('preserves composed values on reopen and normalizes edited values without duplicate affixes', function () {
    $options = ['prefix' => 'SKU-', 'suffix' => '-STD'];
    $form = Livewire::test(AdvancedTextForm::class, ['value' => 'SKU-1024-STD', 'options' => $options]);
    $form->assertSee('SKU-')->call('save')->assertHasNoErrors()->assertSet('saved.value', 'SKU-1024-STD');
    $form->set('data.value', '2048')->call('save')->assertHasNoErrors()->assertSet('saved.value', 'SKU-2048-STD');
    $form->set('data.value', '')->call('save')->assertHasNoErrors()->assertSet('saved.value', '');
});

it('supports visual only affixes and zero and null values without coercing them to empty', function () {
    Livewire::test(AdvancedTextForm::class, ['value' => '0', 'options' => ['prefix' => '¥', 'suffix' => '元', 'prefixConcatenate' => false, 'suffixConcatenate' => false]])
        ->call('save')->assertHasNoErrors()->assertSet('saved.value', '0');
    Livewire::test(AdvancedTextForm::class)->call('save')->assertHasNoErrors()->assertSet('saved.value', null);
    Livewire::test(AdvancedTextForm::class, ['options' => ['default_value' => '0']])->call('save')->assertHasNoErrors()->assertSet('saved.value', '0');
});

it('enforces max length on the editable core and keeps explicit Laravel rules on the saved value', function () {
    $form = Livewire::test(AdvancedTextForm::class, ['value' => 'SKU-123', 'options' => ['prefix' => 'SKU-', 'max_length' => 3]]);
    $form->call('save')->assertHasNoErrors()->assertSet('saved.value', 'SKU-123');
    $form->set('data.value', 'SKU-1234')->call('save')->assertHasErrors(['data.value']);
    Livewire::test(AdvancedTextForm::class, ['value' => '123', 'options' => ['prefix' => 'SKU-'], 'rules' => ['rules' => ['max:5']]])
        ->call('save')->assertHasErrors(['data.value']);
});

it('restores readonly text from server data including nested widgets and uses configured defaults for new blocks', function () {
    $definitions = new class implements ContentDefinitionRepository
    {
        public function fields(string $layout, bool $widget = false): array
        {
            return $widget ? [['name' => 'code', 'resolver' => 'text', 'options' => ['readonly' => true, 'default_value' => 'DEFAULT']]] : [['name' => 'items', 'resolver' => 'widget']];
        }
    };
    $original = [['layout' => 'hero', 'key' => 'parent', 'attributes' => ['items' => [['layout' => 'card', 'key' => 'child', 'attributes' => ['code' => 'SERVER']]]]]];
    $candidate = $original;
    $candidate[0]['attributes']['items'][0]['attributes']['code'] = 'FORGED';
    $candidate[0]['attributes']['items'][] = ['layout' => 'card', 'key' => 'new', 'attributes' => ['code' => 'FORGED']];
    $result = (new SensitiveContent)->restore($candidate, $original, $definitions);
    expect($result[0]['attributes']['items'][0]['attributes']['code'])->toBe('SERVER')
        ->and($result[0]['attributes']['items'][1]['attributes']['code'])->toBe('DEFAULT');
});

require_once __DIR__.'/../Fixtures/ComplexContentForm.php';

class AdvancedTextContentRecord extends Model
{
    protected $table = 'advanced_text_records';

    protected $guarded = [];

    public $timestamps = false;

    protected function casts(): array
    {
        return ['components' => 'array'];
    }
}

class AdvancedTextContentForm extends ComplexContentForm
{
    public int $recordId;

    public function mount(array $content, int $recordId = 0): void
    {
        $this->recordId = $recordId;
        parent::mount($content);
    }

    public function form(Schema $schema): Schema
    {
        return parent::form($schema)->model(AdvancedTextContentRecord::find($this->recordId));
    }

    public function save(): void
    {
        parent::save();
        AdvancedTextContentRecord::findOrFail($this->recordId)->update($this->saved);
    }
}

it('persists real builder edits while preserving old data and ignoring forged readonly envelopes', function () {
    Illuminate\Support\Facades\Schema::create('advanced_text_records', function (Blueprint $table) {
        $table->id();
        $table->json('components');
    });
    config()->set('complex-test.fields', [
        ['name' => 'code', 'resolver' => 'text', 'options' => ['readonly' => true]],
        ['name' => 'note', 'resolver' => 'text', 'options' => ['prefix' => 'SKU-']],
    ]);
    $raw = [['layout' => 'hero', 'key' => 'old', 'attributes' => ['code' => 'SERVER', 'note' => 'SKU-1', 'unknown' => false]]];
    $record = AdvancedTextContentRecord::create(['components' => $raw]);
    $form = Livewire::test(AdvancedTextContentForm::class, ['content' => $raw, 'recordId' => $record->id]);
    $form->call('save')->assertHasNoErrors();
    expect($record->fresh()->components)->toBe($raw);
    $key = array_key_first($form->get('data.components'));
    $form->set('data.components.'.$key.'.data.envelope.attributes.code', 'forged-envelope')
        ->set('data.components.'.$key.'.data.attributes.code', 'forged-value')
        ->set('data.components.'.$key.'.data.attributes.note', '2')->call('save')->assertHasNoErrors();
    expect($record->fresh()->components[0]['attributes'])->toBe(['code' => 'SERVER', 'note' => 'SKU-2', 'unknown' => false]);
});

it('connects the actual rendered input to its own suggestion datalist', function () {
    $html = Livewire::test(AdvancedTextForm::class, ['options' => ['suggestions' => ['首页', '产品']]])->html();
    expect(preg_match('/<input[^>]*\\slist="([^"]+)"/s', $html, $match))->toBe(1);
    expect($html)->toContain('<datalist id="'.$match[1].'"');
});

it('uses the same Unicode length limit in the rendered input and server validation', function () {
    $form = Livewire::test(AdvancedTextForm::class, ['value' => 'SKU-😀😀', 'options' => ['prefix' => 'SKU-', 'max_length' => 2]]);
    $form->call('save')->assertHasNoErrors()->assertSet('saved.value', 'SKU-😀😀');
    expect(preg_match('/<input[^>]*\\smaxlength=/s', $form->html()))->toBe(0);
    $form->set('data.value', 'SKU-😀😀字')->call('save')->assertHasErrors(['data.value']);
});
