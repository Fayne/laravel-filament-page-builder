<?php

use Fayne\CMS\Models\Admin;
use Fayne\CMS\Support\Authorization\Authorization;
use Fayne\CMS\Support\Authorization\AuthorizationContext;
use Fayne\CMS\Support\Authorization\PermissionCatalog;
use Fayne\PageBuilder\Contracts\ContentDefinitionRepository;
use Fayne\PageBuilder\Filament\Forms\Components\ContentBuilder;
use Filament\Forms\Concerns\InteractsWithForms;
use Filament\Forms\Contracts\HasForms;
use Filament\Schemas\Schema;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Livewire\Component;
use Livewire\Livewire;
use Spatie\Permission\Models\Permission;
use UniFileManager\Core\Contracts\FileManagerAuthorizer;
use UniFileManager\FilamentFileManager\Livewire\UniFilePickerUploader;

class ContentMediaAuthorizer implements FileManagerAuthorizer
{
    public function can(mixed $user, string $operation, string $path = ''): bool
    {
        return $user instanceof Admin && Authorization::allows($user, 'tenant.uploadMedia')
            && in_array($operation, ['view', 'upload', 'preview', 'download'], true);
    }
}

beforeEach(function () {
    config([
        'auth.defaults.guard' => 'admin',
        'filament-file-manager.authorizer' => ContentMediaAuthorizer::class,
        'filament-file-manager.storage_areas.content-public' => ['enabled' => true, 'disk' => 'public', 'root' => '', 'visibility' => 'public'],
        'unifilemanager.storage_areas.content-public' => ['enabled' => true, 'disk' => 'public', 'root' => '', 'visibility' => 'public'],
    ]);
    $this->artisan('migrate', ['--force' => true]);
    PermissionCatalog::seed();
    app(AuthorizationContext::class)->activate(0);
    $admin = Admin::create(['name' => 'Uploader', 'email' => 'upload@example.test', 'password' => 'password', 'is_active' => true]);
    $admin->givePermissionTo(Permission::where('name', 'tenant.uploadMedia')->firstOrFail());
    auth('admin')->login($admin);
});

class MediaContentForm extends Component implements HasForms
{
    use InteractsWithForms;

    public array $data = [];

    public array $saved = [];

    public bool $multiple = false;

    public function mount(array $content = [], bool $multiple = false): void
    {
        $this->multiple = $multiple;
        $this->form->fill(['components' => $content]);
    }

    public function form(Schema $schema): Schema
    {
        return $schema->components([ContentBuilder::make('components')->definitions(new class($this->multiple) implements ContentDefinitionRepository
        {
            public function __construct(private bool $multiple) {}

            public function fields(string $layout, bool $widget = false): array
            {
                return [
                    ['name' => 'image', 'resolver' => 'file', 'options' => ['disk' => 'public', 'is_multiple' => $this->multiple]],
                    ['name' => 'json', 'resolver' => 'json'],
                ];
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

it('round trips existing media metadata and JSON through the real form', function () {
    Storage::fake('public');
    Storage::disk('public')->put('hero.txt', 'test');
    $content = [['layout' => 'hero', 'key' => 'stable', 'attributes' => ['image' => ['disk' => 'public', 'path' => 'hero.txt', 'custom' => 'keep'], 'json' => ['value' => false, 'count' => 0]]]];
    $component = Livewire::test(MediaContentForm::class, ['content' => $content]);
    $component->call('save')->assertHasNoErrors()->assertSet('saved.components', $content);
});

it('keeps metadata and order for multiple existing media references', function () {
    Storage::fake('public');
    Storage::disk('public')->put('one.txt', 'one');
    Storage::disk('public')->put('two.txt', 'two');
    $content = [['layout' => 'hero', 'key' => 'stable', 'attributes' => ['image' => [['disk' => 'public', 'path' => 'one.txt', 'custom' => 'one'], ['disk' => 'public', 'path' => 'two.txt', 'custom' => 'two']], 'json' => []]]];
    Livewire::test(MediaContentForm::class, ['content' => $content, 'multiple' => true])->call('save')->assertHasNoErrors()->assertSet('saved.components', $content);
});

it('stores an uploaded file and persists the configured disk and stored path', function () {
    Storage::fake('public');
    $test = Livewire::test(MediaContentForm::class, ['content' => [['layout' => 'hero', 'key' => 'stable', 'attributes' => ['image' => null, 'json' => []]]]]);
    $key = array_key_first($test->get('data.components'));
    $paths = [];
    Livewire::test(UniFilePickerUploader::class, ['pickerId' => 'content-image', 'multiple' => false, 'maxFiles' => 1, 'storageArea' => 'content-public'])
        ->set('uploads', [UploadedFile::fake()->createWithContent('new.txt', 'Content upload')])
        ->assertHasNoErrors()->assertDispatched('unifile-manager:uploaded', function (string $event, array $parameters) use (&$paths): bool {
            $paths = $parameters['paths'];

            return count($paths) === 1;
        });
    $test->set('data.components.'.$key.'.data.attributes.image', $paths[0])
        ->call('save')->assertHasNoErrors();
    $asset = $test->get('saved.components')[0]['attributes']['image'];
    expect($asset['disk'])->toBe('public')->and(Storage::disk('public')->exists($asset['path']))->toBeTrue();
});

it('rejects unauthenticated uploads before writing media to storage', function () {
    Storage::fake('public');
    auth('admin')->logout();
    Livewire::test(UniFilePickerUploader::class, ['pickerId' => 'content-image', 'multiple' => false, 'maxFiles' => 1, 'storageArea' => 'content-public'])
        ->set('uploads', [UploadedFile::fake()->createWithContent('new.txt', 'Denied upload')])
        ->assertHasErrors(['uploads'])->assertNotDispatched('unifile-manager:uploaded');
    expect(Storage::disk('public')->allFiles())->toBe([]);
});

it('does not invent absent media fields while saving unchanged content', function () {
    $content = [['layout' => 'hero', 'key' => 'existing', 'attributes' => ['json' => ['keep' => 0]]]];
    Livewire::test(MediaContentForm::class, ['content' => $content])->call('save')->assertHasNoErrors()->assertSet('saved.components', $content);
});
