<?php

use Fayne\PageBuilder\Contracts\ContentDefinitionRepository;
use Fayne\PageBuilder\Support\ContentStateAdapter;

function contentAdapter(): ContentStateAdapter
{
    return new ContentStateAdapter(new class implements ContentDefinitionRepository
    {
        public function fields(string $layout, bool $widget = false): array
        {
            return match ($layout) {
                'hero', 'card' => [['name' => 'items', 'resolver' => 'widget', 'options' => [], 'rules' => []]],
                default => [],
            };
        }
    });
}

it('round trips nested content without losing metadata or scalar types', function () {
    $content = require __DIR__.'/../Fixtures/content.php';
    expect(contentAdapter()->fromBuilder(contentAdapter()->toBuilder($content)))->toBe($content);
});

it('edits a declared nested widget without converting ordinary JSON', function () {
    $content = require __DIR__.'/../Fixtures/content.php';
    $state = contentAdapter()->toBuilder($content);
    $state[0]['data']['attributes']['items'][0]['data']['attributes']['title'] = '更新卡片';
    $saved = contentAdapter()->fromBuilder($state);
    expect($saved[0]['attributes']['items'][0]['attributes']['title'])->toBe('更新卡片')
        ->and($saved[0]['attributes']['json'])->toBe($content[0]['attributes']['json'])
        ->and($saved[0]['attributes']['internalTitle'])->toBe('业务标题');
});

it('keeps persistent keys when the editor reorders items with temporary UUID indexes', function () {
    $content = require __DIR__.'/../Fixtures/content.php';
    $state = contentAdapter()->toBuilder($content);
    $saved = contentAdapter()->fromBuilder(['temporary-b' => $state[1], 'temporary-a' => $state[0]]);
    expect(array_column($saved, 'key'))->toBe(['retired-original', 'hero-original']);
});

it('duplicates every nested persistent key without changing the original or ordinary JSON', function () {
    $content = require __DIR__.'/../Fixtures/content.php';
    $state = contentAdapter()->toBuilder($content);
    $copy = contentAdapter()->fromBuilder(contentAdapter()->duplicate($state));
    $keys = [$copy[0]['key'], $copy[0]['attributes']['items'][0]['key'], $copy[0]['attributes']['items'][0]['attributes']['items'][0]['key'], $copy[1]['key']];
    expect(count(array_unique($keys)))->toBe(4);
    foreach ($keys as $key) {
        expect($key)->not->toBeIn(['hero-original', 'card-original', 'link-original', 'retired-original']);
    }
    expect(contentAdapter()->fromBuilder($state))->toBe($content)
        ->and($copy[0]['attributes']['json'])->toBe($content[0]['attributes']['json']);
});

it('assigns stable keys to newly added blocks on persistence', function () {
    $saved = contentAdapter()->fromBuilder([['type' => 'hero', 'data' => ['attributes' => ['title' => '新页面']]]]);
    expect($saved[0]['key'])->toBeString()->not->toBeEmpty()
        ->and($saved[0]['layout'])->toBe('hero')
        ->and($saved[0]['attributes'])->toBe(['title' => '新页面']);
});

it('rejects malformed imported content', function (array $content) {
    contentAdapter()->toBuilder($content);
})->with([
    [[['layout' => 'hero', 'key' => 'x']]],
    [[['layout' => '', 'key' => 'x', 'attributes' => []]]],
    [[['layout' => 'hero', 'key' => 'x', 'attributes' => 'broken']]],
    [[['layout' => 'hero', 'key' => 'x', 'attributes' => []], ['layout' => 'hero', 'key' => 'x', 'attributes' => []]]],
])->throws(InvalidArgumentException::class);

it('preserves unknown attributes omitted by the form schema', function () {
    $content = require __DIR__.'/../Fixtures/content.php';
    $state = contentAdapter()->toBuilder($content);
    unset($state[0]['data']['attributes']['unknown']);
    $saved = contentAdapter()->fromBuilder($state);
    expect($saved[0]['attributes']['unknown'])->toBe(['preserved' => true]);
});
