<?php

require_once __DIR__.'/../Fixtures/ComplexContentForm.php';

use Fayne\PageBuilder\Contracts\ContentDefinitionRepository;
use Fayne\PageBuilder\Support\ContentHttpRequest;
use Fayne\PageBuilder\Support\ContentOutputResolver;
use Filament\Actions\Testing\TestAction;
use Filament\Schemas\Schema;
use Illuminate\Auth\GenericUser;
use Illuminate\Contracts\Auth\Access\Authorizable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Gate;
use Illuminate\Support\Facades\Http;

beforeEach(fn () => app()->setLocale('zh_CN'));

it('executes only explicitly allowed request methods and preserves wildcard response mapping', function () {
    $url = 'https://content.example.test/questions';
    config(['filament-page-builder.http.endpoints' => [$url => ['methods' => ['GET'], 'public' => true]]]);
    Http::fake([$url.'*' => Http::response(['results' => [['question' => 'One'], ['question' => 'Two']]])]);
    $result = (new ContentHttpRequest)->resolve(['url' => $url, 'method' => 'GET', 'targetAttribute' => 'questions', 'responsePath' => 'results.*.question']);
    expect($result)->toBe(['target' => 'questions', 'value' => ['One', 'Two']]);
    Http::assertSentCount(1);
});

it('rejects nonallowlisted endpoints or methods without sending a request', function (array $request) {
    config(['filament-page-builder.http.endpoints' => ['https://allowed.example.test/data' => ['methods' => ['GET']]]]);
    Http::fake();
    try {
        (new ContentHttpRequest)->execute($request);
        $this->fail('Request should be rejected');
    } catch (InvalidArgumentException) {
        Http::assertNothingSent();
    }
})->with([
    [['url' => 'https://foreign.example.test/data', 'method' => 'GET']],
    [['url' => 'https://allowed.example.test/data', 'method' => 'DELETE']],
]);

it('does not execute side effects from public output and never exposes raw credentials', function () {
    $url = 'https://content.example.test/data';
    config(['filament-page-builder.http.endpoints' => [$url => ['methods' => ['POST'], 'public' => true]]]);
    Http::fake();
    expect(fn () => (new ContentHttpRequest)->resolve(['url' => $url, 'method' => 'POST', 'headers' => ['Authorization' => 'secret'], 'targetAttribute' => 'data', 'responsePath' => 'data'], public: true))->toThrow(InvalidArgumentException::class);
    Http::assertNothingSent();
});

it('projects mapped HTTP values while keeping its raw configuration out of public attributes', function () {
    $url = 'https://content.example.test/data';
    config(['filament-page-builder.http.endpoints' => [$url => ['methods' => ['GET'], 'public' => true]]]);
    Http::fake([$url => Http::response(['data' => ['title' => 'Public']])]);
    $definitions = new class implements ContentDefinitionRepository
    {
        public function fields(string $layout, bool $widget = false): array
        {
            return [['name' => 'request', 'resolver' => 'http']];
        }
    };
    $raw = [['layout' => 'hero', 'key' => 'http', 'attributes' => ['request' => ['url' => $url, 'headers' => ['Authorization' => 'private'], 'targetAttribute' => 'result', 'responsePath' => 'data']]]];
    expect((new ContentOutputResolver)->resolve($raw, $definitions))->toBe([['layout' => 'hero', 'attributes' => ['result' => ['title' => 'Public']]]]);
});

it('round trips HTTP editor configuration without executing on hydrate or save', function () {
    config(['complex-test.fields' => [['name' => 'request', 'resolver' => 'http']]]);
    Http::fake();
    $raw = [['layout' => 'hero', 'key' => 'http', 'attributes' => ['request' => ['url' => 'https://not-yet-allowed.example.test/data', 'method' => 'GET', 'headers' => [['key' => 'X-Test', 'value' => 'fixture']], 'body' => '{}', 'targetAttribute' => 'result', 'responsePath' => 'data']]]];
    Livewire\Livewire::test(ComplexContentForm::class, ['content' => $raw])->assertSee('测试请求')->call('save')->assertHasNoErrors()->assertSet('saved.components', $raw);
    Http::assertNothingSent();
});

it('uses the configured fixed response without repeating requests and honors custom mapping keys', function () {
    Http::fake();
    $result = (new ContentHttpRequest)->resolve(['mappingTargetKey' => 'destination', 'mappingPathKey' => 'path', 'destination' => 'answer', 'path' => 'data.value', 'fixedResponseData' => ['ok' => true, 'response_body' => '{"data":{"value":42}}']], options: ['fixedResponse' => true]);
    expect($result)->toBe(['target' => 'answer', 'value' => 42]);
    Http::assertNothingSent();
});

it('supports form bodies and server-owned headers without following redirects', function (string $type) {
    $url = 'https://content.example.test/form';
    config(['filament-page-builder.http.endpoints' => [$url => ['methods' => ['POST'], 'headers' => ['Authorization' => 'server-value']]]]);
    Http::fake([$url => Http::response('', 302, ['Location' => 'https://foreign.example.test/private'])]);
    $response = (new ContentHttpRequest)->execute(['url' => $url, 'method' => 'POST', 'bodyType' => $type, 'headers' => ['Authorization' => 'client-value'], 'body' => [['key' => 'name', 'value' => 'Example']]]);
    expect($response['ok'])->toBeFalse()->and($response['status'])->toBe(302);
    Http::assertSent(fn ($request) => $request->hasHeader('Authorization', 'server-value'));
    Http::assertSentCount(1);
})->with(['form-data', 'x-www-form-urlencoded']);

class HttpActionUser extends GenericUser implements Authorizable
{
    use Illuminate\Foundation\Auth\Access\Authorizable;
}
class HttpActionRecord extends Model
{
    protected $table = 'http_action_records';

    protected $guarded = [];

    public $timestamps = false;

    protected function casts(): array
    {
        return ['components' => 'array'];
    }
}
class HttpActionForm extends ComplexContentForm
{
    public int $recordId;

    public function mount(array $content, int $recordId = 0): void
    {
        $this->recordId = $recordId;
        parent::mount($content);
    }

    public function form(Schema $schema): Schema
    {
        return parent::form($schema)->model(HttpActionRecord::find($this->recordId));
    }
}
it('reauthorizes direct HTTP test actions before sending and caches a fixed response only after execution', function () {
    Illuminate\Support\Facades\Schema::create('http_action_records', function (Blueprint $table) {
        $table->id();
        $table->json('components');
    });
    $url = 'https://content.example.test/action';
    config(['complex-test.fields' => [['name' => 'request', 'resolver' => 'http', 'options' => ['fixedResponse' => true]]], 'filament-page-builder.http.endpoints' => [$url => ['methods' => ['GET']]]]);
    $raw = [['layout' => 'hero', 'key' => 'http', 'attributes' => ['request' => ['url' => $url, 'method' => 'GET']]]];
    $record = HttpActionRecord::create(['components' => $raw]);
    auth('admin')->setUser(new HttpActionUser(['id' => 1, 'is_active' => true]));
    Gate::define('update', fn () => true);
    Gate::define('executeContentHttp', fn () => false);
    Http::fake([$url => Http::response(['data' => 'fixture'])]);
    $form = Livewire\Livewire::test(HttpActionForm::class, ['content' => $raw, 'recordId' => $record->id]);
    $key = array_key_first($form->get('data.components'));
    $action = TestAction::make('testRequest')->schemaComponent('components.'.$key.'.data.attributes.request');
    $form->mountAction($action)->callMountedAction()->assertForbidden();
    Http::assertNothingSent();
    Gate::define('executeContentHttp', fn () => true);
    $form = Livewire\Livewire::test(HttpActionForm::class, ['content' => $raw, 'recordId' => $record->id]);
    $key = array_key_first($form->get('data.components'));
    $form->callAction(TestAction::make('testRequest')->schemaComponent('components.'.$key.'.data.attributes.request'))->assertHasNoErrors()->call('save')->assertSet('saved.components.0.attributes.request.fixedResponseData.ok', true);
    Http::assertSentCount(1);
});

it('rejects malformed HTTP values as configuration errors', function () {
    expect(fn () => (new ContentHttpRequest)->execute(['url' => ['invalid'], 'method' => 'GET']))->toThrow(InvalidArgumentException::class);
});
