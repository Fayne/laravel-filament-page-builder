<?php

use Fayne\PageBuilder\Contracts\ContentDefinitionRepository;
use Fayne\PageBuilder\Filament\Forms\Components\ContentBuilder;
use Filament\Forms\Concerns\InteractsWithForms;
use Filament\Forms\Contracts\HasForms;
use Filament\Schemas\Schema;
use Illuminate\Auth\GenericUser;
use Illuminate\Contracts\Auth\Access\Authorizable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Gate;
use Illuminate\Support\Facades\Schema as DatabaseSchema;
use Livewire\Component;
use Livewire\Livewire;

class EntityEditorUser extends GenericUser implements Authorizable
{
    use Illuminate\Foundation\Auth\Access\Authorizable;
}

class SelectableContentEntity extends Model
{
    protected $table = 'selectable_entities';

    protected $guarded = [];

    public $timestamps = false;
}
class SelectableContentPolicy
{
    public function viewAny($user): bool
    {
        return $user->is_active;
    }

    public function view($user, SelectableContentEntity $entity): bool
    {
        return $user->is_active && $entity->visible;
    }
}
class EntityReferenceForm extends Component implements HasForms
{
    use InteractsWithForms;

    public array $data = [];

    public array $saved = [];

    public bool $multiple = false;

    public function mount(array $content, bool $multiple = false): void
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
                return [['name' => 'entity', 'resolver' => 'entity', 'options' => ['reference' => '["product"]', 'is_multiple' => $this->multiple]]];
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
    DatabaseSchema::create('selectable_entities', function (Blueprint $table) {
        $table->id();
        $table->string('title');
        $table->boolean('visible')->default(true);
    });
    SelectableContentEntity::create(['title' => 'Public product']);
    SelectableContentEntity::create(['title' => 'Private product', 'visible' => false]);
    config(['filament-page-builder.entities' => ['product' => ['model' => SelectableContentEntity::class, 'label' => 'title', 'fields' => ['id', 'title']]]]);
    Gate::policy(SelectableContentEntity::class, SelectableContentPolicy::class);
    auth('admin')->setUser(new EntityEditorUser(['id' => 1, 'is_active' => true]));
});

it('round trips entity references through a real selector without resolving them into model data', function (bool $multiple, bool $encoded) {
    $value = $multiple ? [['model' => 'product', 'id' => 1, 'legacy' => 'keep']] : ['model' => 'product', 'id' => 1, 'legacy' => 'keep'];
    if ($encoded) {
        $value = json_encode($value);
    }
    $raw = [['layout' => 'hero', 'key' => 'key', 'attributes' => ['entity' => $value]]];
    Livewire::test(EntityReferenceForm::class, ['content' => $raw, 'multiple' => $multiple])->call('save')->assertHasNoErrors()->assertSet('saved.components', $raw);
})->with([[false, false], [true, false], [false, true], [true, true]]);

it('searches only explicitly allowed and viewable entity records', function () {
    $editor = Livewire::test(EntityReferenceForm::class, ['content' => [['layout' => 'hero', 'key' => 'key', 'attributes' => []]]]);
    $key = array_key_first($editor->get('data.components'));
    $field = $editor->instance()->form->getComponent('components.'.$key.'.data.attributes.entity');
    $results = $field->getSearchResults('product');
    expect(array_values($results))->toBe(['product: Public product']);
});

it('rejects a submitted entity reference that fails its view policy', function () {
    $raw = [['layout' => 'hero', 'key' => 'key', 'attributes' => ['entity' => ['model' => 'product', 'id' => 1]]]];
    $form = Livewire::test(EntityReferenceForm::class, ['content' => $raw]);
    $key = array_key_first($form->get('data.components'));
    $form->set('data.components.'.$key.'.data.attributes.entity', json_encode(['model' => 'product', 'id' => 2]))->call('save')->assertHasErrors()->assertSet('saved', []);
});

it('refuses saving an existing reference that is no longer viewable instead of clearing it', function () {
    $raw = [['layout' => 'hero', 'key' => 'key', 'attributes' => ['entity' => ['model' => 'product', 'id' => 2]]]];
    Livewire::test(EntityReferenceForm::class, ['content' => $raw])->call('save')->assertHasErrors()->assertSet('saved', []);
});

it('preserves empty legacy entity references without inventing a selection', function ($value) {
    $raw = [['layout' => 'hero', 'key' => 'key', 'attributes' => ['entity' => $value]]];
    Livewire::test(EntityReferenceForm::class, ['content' => $raw])->call('save')->assertHasNoErrors()->assertSet('saved.components', $raw);
})->with([[null], [''], [[]]]);
