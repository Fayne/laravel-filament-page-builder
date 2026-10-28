<?php

require_once __DIR__.'/../Fixtures/ComplexContentForm.php';

use Fayne\PageBuilder\Contracts\ContentDefinitionRepository;
use Fayne\PageBuilder\Support\ContentOutputResolver;
use Fayne\PageBuilder\Support\SensitiveContent;
use Livewire\Livewire;

function sensitiveDefinitions(): ContentDefinitionRepository
{
    return new class implements ContentDefinitionRepository
    {
        public function fields(string $layout, bool $widget = false): array
        {
            return [['name' => 'secret', 'resolver' => 'sensitive'], ['name' => 'title', 'resolver' => 'text']];
        }
    };
}
it('restores sensitive values only from the current server record and removes forged replacements', function () {
    $definitions = sensitiveDefinitions();
    $original = [['layout' => 'hero', 'key' => 'existing', 'attributes' => ['secret' => 'server-only', 'title' => 'Old']]];
    $candidate = [['layout' => 'hero', 'key' => 'existing', 'attributes' => ['secret' => 'forged', 'title' => 'New']]];
    expect((new SensitiveContent)->restore($candidate, $original, $definitions)[0]['attributes'])->toBe(['title' => 'New', 'secret' => 'server-only']);
    $candidate[0]['key'] = 'new-copy';
    expect((new SensitiveContent)->restore($candidate, $original, $definitions)[0]['attributes'])->toBe(['title' => 'New']);
});

it('never includes sensitive attributes in public output', function () {
    $raw = [['layout' => 'hero', 'key' => 'key', 'attributes' => ['secret' => 'secret', 'title' => 'Public']]];
    expect((new ContentOutputResolver)->resolve($raw, sensitiveDefinitions()))->toBe([['layout' => 'hero', 'attributes' => ['title' => 'Public']]]);
});

it('blocks saving legacy sensitive definitions without exposing their values or offering a reveal action', function () {
    config(['complex-test.fields' => sensitiveDefinitions()->fields('hero')]);
    $raw = [['layout' => 'hero', 'key' => 'key', 'attributes' => ['secret' => 'legacy-private-value', 'title' => 'Public']]];
    $editor = Livewire::test(ComplexContentForm::class, ['content' => $raw])
        ->assertDontSee('legacy-private-value')->assertDontSee('查看敏感内容');
    expect(json_encode($editor->get('data')))->not->toContain('legacy-private-value');
    $editor->call('save')->assertHasErrors(['data.components'])->assertSet('saved', []);
});
