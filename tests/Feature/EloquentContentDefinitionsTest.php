<?php

use Fayne\PageBuilder\Support\EloquentContentDefinitions;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

class DefinitionComponent extends Model
{
    protected $table = 'test_components';

    protected $guarded = [];

    public $timestamps = false;

    public function fields()
    {
        return $this->hasMany(DefinitionField::class, 'component_id')->orderBy('weight');
    }
}
class DefinitionWidget extends DefinitionComponent
{
    protected $table = 'test_widgets';
}
class DefinitionField extends Model
{
    protected $table = 'test_fields';

    protected $guarded = [];

    public $timestamps = false;

    protected $casts = ['options' => 'array', 'rules' => 'array'];
}

it('reads ordered field definitions and translates allowed widget ids to slugs', function () {
    foreach (['test_components', 'test_widgets'] as $name) {
        Schema::create($name, function (Blueprint $table) {
            $table->id();
            $table->string('name');
            $table->string('slug');
        });
    }
    Schema::create('test_fields', function (Blueprint $table) {
        $table->id();
        $table->integer('component_id');
        $table->string('title');
        $table->string('slug')->nullable();
        $table->string('resolver');
        $table->json('options')->nullable();
        $table->json('rules')->nullable();
        $table->integer('weight')->default(0);
    });
    $component = DefinitionComponent::create(['name' => '首屏', 'slug' => 'hero']);
    $widget = DefinitionWidget::create(['name' => '卡片', 'slug' => 'card']);
    $component->fields()->create(['title' => 'Card List', 'resolver' => 'widget', 'options' => ['widgets' => [$widget->id]], 'weight' => 2]);
    $component->fields()->create(['title' => 'Main Title', 'slug' => 'headline', 'resolver' => 'text', 'weight' => 1]);
    $definitions = new EloquentContentDefinitions(DefinitionComponent::class, DefinitionWidget::class);
    expect($definitions->layouts())->toBe(['hero' => '首屏'])
        ->and($definitions->layouts(true))->toBe(['card' => '卡片'])
        ->and(array_column($definitions->fields('hero'), 'name'))->toBe(['headline', 'card_list'])
        ->and($definitions->fields('hero')[1]['options']['widgets'])->toBe(['card']);
});

it('loads definitions from the page connection instead of the current default database', function () {
    config(['database.connections.content_region' => ['driver' => 'sqlite', 'database' => ':memory:', 'prefix' => '']]);
    foreach (['test_components', 'test_widgets'] as $table) {
        Schema::connection('content_region')->create($table, function (Blueprint $table) {
            $table->id();
            $table->string('name');
            $table->string('slug');
        });
    }
    DefinitionComponent::on('content_region')->create(['name' => 'Regional', 'slug' => 'regional']);
    $definitions = new EloquentContentDefinitions(DefinitionComponent::class, DefinitionWidget::class, 'content_region');
    expect($definitions->layouts())->toBe(['regional' => 'Regional']);
});
