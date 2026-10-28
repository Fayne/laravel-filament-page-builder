<?php

use Fayne\PageBuilder\Contracts\ContentDefinitionRepository;
use Fayne\PageBuilder\Support\ContentOutputResolver;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Facades\Storage;

it('publishes only declared fields even after fields or entire definitions are removed', function () {
    $definitions = new class implements ContentDefinitionRepository
    {
        public function fields(string $layout, bool $widget = false): array
        {
            return match ($layout) {
                'hero' => [['name' => 'title', 'resolver' => 'text'], ['name' => 'items', 'resolver' => 'widget']],
                'card' => [['name' => 'title', 'resolver' => 'text']],
                default => [],
            };
        }
    };
    $content = [
        ['layout' => 'hero', 'key' => 'hero', 'attributes' => [
            'title' => 'Public', 'retired_request' => ['headers' => ['Authorization' => 'private-token']],
            'items' => [['layout' => 'card', 'key' => 'card', 'attributes' => ['title' => 'Card', 'retired_secret' => 'private-value']]],
        ]],
        ['layout' => 'deleted', 'key' => 'deleted', 'attributes' => ['retired_secret' => 'private-value']],
    ];

    expect((new ContentOutputResolver)->resolve($content, $definitions))->toBe([
        ['layout' => 'hero', 'attributes' => ['title' => 'Public', 'items' => [['layout' => 'card', 'attributes' => ['title' => 'Card']]]]],
        ['layout' => 'deleted', 'attributes' => []],
    ])->and($content[0]['attributes'])->toHaveKey('retired_request')
        ->and($content[1]['attributes']['retired_secret'])->toBe('private-value');
});

it('preserves declared null false and zero values in public output', function () {
    $definitions = new class implements ContentDefinitionRepository
    {
        public function fields(string $layout, bool $widget = false): array
        {
            return [['name' => 'title', 'resolver' => 'text'], ['name' => 'enabled', 'resolver' => 'boolean'], ['name' => 'count', 'resolver' => 'number']];
        }
    };
    $attributes = ['title' => null, 'enabled' => false, 'count' => 0];
    expect((new ContentOutputResolver)->resolve([['layout' => 'hero', 'attributes' => $attributes]], $definitions))
        ->toBe([['layout' => 'hero', 'attributes' => $attributes]]);
});

it('resolves nested media only for output without changing raw content', function () {
    Storage::fake('public');
    Storage::disk('public')->put('hero.jpg', 'image');
    $definitions = new class implements ContentDefinitionRepository
    {
        public function fields(string $layout, bool $widget = false): array
        {
            return $layout === 'hero'
                ? [['name' => 'items', 'resolver' => 'widget']]
                : [['name' => 'image', 'resolver' => 'file', 'options' => ['is_multiple' => false]]];
        }
    };
    $content = [['layout' => 'hero', 'key' => 'a', 'internalTitle' => 'private', 'attributes' => ['items' => [
        ['layout' => 'card', 'key' => 'b', 'attributes' => ['image' => ['disk' => 'public', 'path' => 'hero.jpg']]],
    ]]]];
    $output = (new ContentOutputResolver)->resolve($content, $definitions);
    expect($output[0])->not->toHaveKey('key')->not->toHaveKey('internalTitle')
        ->and($output[0]['attributes']['items'][0]['attributes']['image']['url'])->toBe(Storage::disk('public')->url('hero.jpg'))
        ->and($content[0]['attributes']['items'][0]['attributes']['image'])->toBe(['disk' => 'public', 'path' => 'hero.jpg']);
});

it('refuses entity models outside the configured public projection allowlist', function () {
    $definitions = new class implements ContentDefinitionRepository
    {
        public function fields(string $layout, bool $widget = false): array
        {
            return [['name' => 'product', 'resolver' => 'entity']];
        }
    };
    config(['filament-page-builder.resolve_entities' => true]);
    (new ContentOutputResolver)->resolve([['layout' => 'hero', 'key' => 'x', 'attributes' => ['product' => ['model' => 'arbitrary', 'id' => 1]]]], $definitions);
})->throws(InvalidArgumentException::class);

class PublicContentEntity extends Model
{
    protected $table = 'public_entities';

    protected $guarded = [];

    public $timestamps = false;
}

it('returns only configured public entity fields and preserves single versus list references', function () {
    Schema::create('public_entities', function (Blueprint $table) {
        $table->id();
        $table->string('title');
        $table->string('secret');
    });
    PublicContentEntity::create(['title' => '产品', 'secret' => 'hidden']);
    config(['filament-page-builder.resolve_entities' => true, 'filament-page-builder.entities' => [
        'product' => ['model' => PublicContentEntity::class, 'fields' => ['id', 'title']],
    ]]);
    $definitions = new class implements ContentDefinitionRepository
    {
        public function fields(string $layout, bool $widget = false): array
        {
            return [['name' => 'product', 'resolver' => 'entity']];
        }
    };
    $output = (new ContentOutputResolver)->resolve([
        ['layout' => 'hero', 'key' => 'a', 'attributes' => ['product' => ['model' => 'product', 'id' => 1]]],
        ['layout' => 'hero', 'key' => 'b', 'attributes' => ['product' => [['model' => 'product', 'id' => 1]]]],
    ], $definitions);
    expect($output[0]['attributes']['product'])->toBe(['id' => 1, 'title' => '产品'])
        ->and($output[1]['attributes']['product'])->toBe([['id' => 1, 'title' => '产品']]);
});

it('refuses public output for unsupported custom resolvers instead of exposing raw configuration', function (string $resolver) {
    $definitions = new class($resolver) implements ContentDefinitionRepository
    {
        public function __construct(private string $resolver) {}

        public function fields(string $layout, bool $widget = false): array
        {
            return [['name' => 'private', 'resolver' => $this->resolver]];
        }
    };
    (new ContentOutputResolver)->resolve([['layout' => 'hero', 'key' => 'key', 'attributes' => ['private' => ['headers' => ['Authorization' => 'private fixture']]]]], $definitions);
})->with(['http', 'unknown'])->throws(InvalidArgumentException::class);

it('resolves references through configured aliases only', function () {
    Schema::create('public_entities', function (Blueprint $table) {
        $table->id();
        $table->string('title');
        $table->string('secret');
    });
    PublicContentEntity::create(['title' => 'Legacy product', 'secret' => 'hidden']);
    config(['filament-page-builder.resolve_entities' => true, 'filament-page-builder.entities' => ['product' => ['model' => PublicContentEntity::class, 'fields' => ['id', 'title']]]]);
    $definitions = new class implements ContentDefinitionRepository
    {
        public function fields(string $layout, bool $widget = false): array
        {
            return [['name' => 'product', 'resolver' => 'entity']];
        }
    };
    $output = (new ContentOutputResolver)->resolve([['layout' => 'hero', 'key' => 'key', 'attributes' => ['product' => ['model' => 'product', 'id' => 1]]]], $definitions);
    expect($output[0]['attributes']['product'])->toBe(['id' => 1, 'title' => 'Legacy product']);
});
