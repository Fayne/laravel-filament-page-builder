<?php

use Fayne\PageBuilder\Contracts\ContentDefinitionRepository;
use Fayne\PageBuilder\Filament\Forms\Components\ContentBuilder;
use Fayne\PageBuilder\Support\FieldSchemaFactory;
use Filament\Actions\Concerns\InteractsWithActions;
use Filament\Actions\Contracts\HasActions;
use Filament\Actions\Testing\TestAction;
use Filament\Forms\Concerns\InteractsWithForms;
use Filament\Forms\Contracts\HasForms;
use Filament\Schemas\Schema;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Storage;
use Livewire\Component;
use Livewire\Livewire;

class CascadeContentForm extends Component implements HasActions, HasForms
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
                return [['name' => 'location', 'resolver' => 'cascade', 'options' => config('cascade-test.options')]];
            }
        }, ['hero' => 'Hero'])])->statePath('data');
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
beforeEach(function () {
    config(['cascade-test.options' => ['options' => [
        ['value' => 1, 'label' => 'Province', 'children' => [['value' => 2, 'label' => 'City']]],
    ]]]);
});
it('round trips legacy encoded and array cascade paths through dependent selectors', function (bool $encoded) {
    $path = $encoded ? '[1,2]' : [1, 2];
    $raw = [['layout' => 'hero', 'key' => 'key', 'attributes' => ['location' => $path]]];
    Livewire::test(CascadeContentForm::class, ['content' => $raw])->assertSee('Province')->assertSee('City')->call('save')->assertHasNoErrors()->assertSet('saved.components', $raw);
})->with([true, false]);
it('rejects a forged cascade child outside its selected parent', function () {
    $raw = [['layout' => 'hero', 'key' => 'key', 'attributes' => ['location' => [1, 2]]]];
    $form = Livewire::test(CascadeContentForm::class, ['content' => $raw]);
    $key = array_key_first($form->get('data.components'));
    $form->set('data.components.'.$key.'.data.attributes.location', [1, 'foreign'])->call('save')->assertHasErrors()->assertSet('saved', []);
});
it('requires an explicit allowlist for external option sources', function () {
    (new FieldSchemaFactory)->make(['name' => 'location', 'resolver' => 'cascade', 'options' => ['fromUrl' => 'https://unconfigured.example.test/options']]);
})->throws(InvalidArgumentException::class);
it('loads dependent remote choices server side only from a configured source', function () {
    $url = 'https://options.example.test/locations';
    config(['filament-cascade.sources.urls' => [$url], 'cascade-test.options' => ['fromUrl' => $url]]);
    Http::fake([$url.'*' => fn ($request) => Http::response($request['parent'] ? [['value' => 2, 'label' => 'Remote city', 'leaf' => true]] : [['value' => 1, 'label' => 'Remote province', 'leaf' => false]])]);
    $raw = [['layout' => 'hero', 'key' => 'key', 'attributes' => ['location' => '[1,2]']]];
    Livewire::test(CascadeContentForm::class, ['content' => $raw])->assertSee('Remote province')->assertSee('Remote city')->call('save')->assertHasNoErrors()->assertSet('saved.components', $raw);
    Http::assertSent(fn ($request) => $request['parent'] == 1);
});

it('reads configured JSON files and directories without changing encoded paths', function (string $source) {
    Storage::fake('local');
    $disk = Storage::disk('local');
    if ($source === 'file') {
        $disk->put('cascade/options.json', json_encode([['value' => 1, 'label' => 'Province', 'children' => [['value' => 2, 'label' => 'City']]]]));
        $path = $disk->path('cascade/options.json');
        config(['filament-cascade.sources.files' => [$path], 'cascade-test.options' => ['fromFile' => $path]]);
    } else {
        $disk->put('cascade/root.json', json_encode([['value' => 1, 'label' => 'Province', 'leaf' => false]]));
        $disk->put('cascade/1.json', json_encode([['value' => 2, 'label' => 'City', 'leaf' => true]]));
        $path = $disk->path('cascade');
        config(['filament-cascade.sources.directories' => [$path], 'cascade-test.options' => ['fromDirectory' => $path]]);
    }
    $raw = [['layout' => 'hero', 'key' => 'key', 'attributes' => ['location' => '[1,2]']]];
    Livewire::test(CascadeContentForm::class, ['content' => $raw])->assertSee('Province')->assertSee('City')->call('save')->assertHasNoErrors()->assertSet('saved.components', $raw);
})->with(['file', 'directory']);

it('rejects scalar path tampering as validation rather than a rendering error', function () {
    $raw = [['layout' => 'hero', 'key' => 'key', 'attributes' => ['location' => [1, 2]]]];
    $form = Livewire::test(CascadeContentForm::class, ['content' => $raw]);
    $key = array_key_first($form->get('data.components'));
    $form->set('data.components.'.$key.'.data.attributes.location', 'forged')->call('save')->assertHasErrors()->assertSet('saved', []);
});

it('initializes a newly added cascade block with an empty array path', function () {
    $form = Livewire::test(CascadeContentForm::class, ['content' => []]);
    $form->callAction(TestAction::make('add')->schemaComponent('components')->arguments(['block' => 'hero']));
    $blocks = $form->get('data.components');
    $first = reset($blocks);
    expect($first['data']['attributes']['location'])->toBe([]);
});

it('rejects ambiguous cascade source configuration before rendering', function () {
    $url = 'https://options.example.test/locations';
    config(['filament-cascade.sources.urls' => [$url]]);
    (new FieldSchemaFactory)->make(['name' => 'location', 'resolver' => 'cascade', 'options' => ['options' => [], 'fromUrl' => $url]]);
})->throws(InvalidArgumentException::class);
