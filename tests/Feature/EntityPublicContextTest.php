<?php

use Fayne\PageBuilder\Contracts\ContentDefinitionRepository;
use Fayne\PageBuilder\Support\ContentOutputResolver;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

class ScopedPublicEntity extends Model
{
    protected $table = 'scoped_public_entities';

    protected $guarded = [];

    public $timestamps = false;

    public function scopePublished($query)
    {
        return $query->where('is_active', true);
    }

    public function publishedProjection(): array
    {
        return ['id' => $this->id, 'title' => $this->released_title, 'secret' => 'hidden'];
    }
}

beforeEach(function () {
    config(['database.connections.other_entities' => ['driver' => 'sqlite', 'database' => ':memory:', 'prefix' => '']]);
    foreach (['sqlite', 'other_entities'] as $connection) {
        Schema::connection($connection)->create('scoped_public_entities', function (Blueprint $table) {
            $table->id();
            $table->string('title');
            $table->string('released_title');
            $table->boolean('is_active');
        });
    }
    config(['filament-page-builder.resolve_entities' => true, 'filament-page-builder.entities' => [
        'page' => ['model' => ScopedPublicEntity::class, 'fields' => ['id', 'title']],
    ]]);
});

it('does not resolve unpublished entities and only exposes their published projection', function () {
    ScopedPublicEntity::create(['title' => 'Unpublished text', 'released_title' => 'Old', 'is_active' => false]);
    ScopedPublicEntity::create(['title' => 'Private draft', 'released_title' => 'Released text', 'is_active' => true]);
    $definitions = new class implements ContentDefinitionRepository
    {
        public function fields(string $layout, bool $widget = false): array
        {
            return [['name' => 'entity', 'resolver' => 'entity']];
        }
    };
    $content = [['layout' => 'hero', 'key' => 'hero', 'attributes' => ['entity' => [['model' => 'page', 'id' => 1], ['model' => 'page', 'id' => 2]]]]];
    $output = app(ContentOutputResolver::class)->resolve($content, $definitions);
    expect($output[0]['attributes']['entity'])->toBe([null, ['id' => 2, 'title' => 'Released text']]);
});

it('keeps nested entity references on the explicitly selected database', function () {
    ScopedPublicEntity::create(['title' => 'Wrong region', 'released_title' => 'Wrong region', 'is_active' => true]);
    ScopedPublicEntity::on('other_entities')->create(['title' => 'Draft', 'released_title' => 'Selected region', 'is_active' => true]);
    $definitions = new class implements ContentDefinitionRepository
    {
        public function fields(string $layout, bool $widget = false): array
        {
            return $widget ? [['name' => 'entity', 'resolver' => 'entity']] : [['name' => 'items', 'resolver' => 'widget']];
        }
    };
    $content = [['layout' => 'hero', 'key' => 'hero', 'attributes' => ['items' => [['layout' => 'card', 'key' => 'card', 'attributes' => ['entity' => ['model' => 'page', 'id' => 1]]]]]]];
    $output = app(ContentOutputResolver::class)->resolve($content, $definitions, connection: 'other_entities', locale: 'en');
    expect($output[0]['attributes']['items'][0]['attributes']['entity'])->toBe(['id' => 1, 'title' => 'Selected region']);
});
