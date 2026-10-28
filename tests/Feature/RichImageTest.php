<?php

require_once __DIR__.'/../Fixtures/ComplexContentForm.php';

use Fayne\CMS\Models\Admin;
use Fayne\CMS\Support\Authorization\AuthorizationContext;
use Fayne\CMS\Support\Authorization\PermissionCatalog;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Livewire\Livewire;
use Spatie\Permission\Models\Permission;

beforeEach(function () {
    $this->artisan('migrate', ['--force' => true]);
    PermissionCatalog::seed();
    app(AuthorizationContext::class)->activate(0);
});

function richImageAdmin(bool $active = true, bool $upload = true): Admin
{
    $admin = Admin::create(['name' => 'Image editor', 'email' => 'image@example.test', 'password' => 'password', 'is_active' => $active]);
    if ($upload) {
        $admin->givePermissionTo(Permission::where('name', 'tenant.uploadMedia')->firstOrFail());
    }

    return $admin;
}
function richImageForm(): mixed
{
    config(['complex-test.fields' => [['name' => 'body', 'resolver' => 'rich_text']]]);

    return Livewire::test(ComplexContentForm::class, ['content' => [['layout' => 'hero', 'key' => 'stable', 'attributes' => ['body' => '<p>Article</p>']]]]);
}
function insertRichImage($form, UploadedFile $file): mixed
{
    $key = array_key_first($form->get('data.components'));
    $path = 'data.components.'.$key.'.data.attributes.body';

    return $form->set('componentFileAttachments.'.$path.'.image-upload', $file)
        ->set($path, ['type' => 'doc', 'content' => [['type' => 'image', 'attrs' => ['id' => 'image-upload', 'src' => 'https://temporary.example.test/preview', 'alt' => 'Article image']]]]);
}
it('stores an inline image through the real rich editor and persists usable HTML', function () {
    Storage::fake('public');
    auth('admin')->setUser(richImageAdmin());
    $form = insertRichImage(richImageForm(), UploadedFile::fake()->image('photo.png'));
    $form->call('save')->assertHasNoErrors();
    $html = $form->get('saved.components.0.attributes.body');
    expect($html)->toContain('<img', 'Article image', 'rich-content/')->not->toContain('temporary.example.test');
    expect(Storage::disk('public')->allFiles('rich-content'))->toHaveCount(1);
});
it('rejects inactive or unauthorized inline uploads before writing a file', function (bool $active, bool $upload) {
    Storage::fake('public');
    auth('admin')->setUser(richImageAdmin($active, $upload));
    insertRichImage(richImageForm(), UploadedFile::fake()->image('photo.png'))->call('save')->assertForbidden();
    expect(Storage::disk('public')->allFiles())->toBe([]);
})->with([[true, false], [false, true]]);
it('rejects nonimage attachments without storing them', function () {
    Storage::fake('public');
    auth('admin')->setUser(richImageAdmin());
    insertRichImage(richImageForm(), UploadedFile::fake()->create('document.pdf', 1, 'application/pdf'))->call('save')->assertHasErrors();
    expect(Storage::disk('public')->allFiles())->toBe([]);
});
it('preserves legacy img markup when saving an unchanged block', function () {
    config(['complex-test.fields' => [['name' => 'body', 'resolver' => 'rich_text']]]);
    $raw = [['layout' => 'hero', 'key' => 'stable', 'attributes' => ['body' => '<p>Legacy</p><img src="https://images.example.test/old.jpg" alt="Old image" width="640">']]];
    Livewire::test(ComplexContentForm::class, ['content' => $raw])->call('save')->assertHasNoErrors()->assertSet('saved.components', $raw);
});

it('rejects forged references to unrelated stored attachment paths', function () {
    Storage::fake('public');
    Storage::disk('public')->put('private-existing.png', 'fixture');
    $form = richImageForm();
    $key = array_key_first($form->get('data.components'));
    $form->set('data.components.'.$key.'.data.attributes.body', ['type' => 'doc', 'content' => [['type' => 'image', 'attrs' => ['id' => 'private-existing.png', 'src' => '/storage/private-existing.png']]]])->call('save')->assertHasErrors();
    expect(Storage::disk('public')->get('private-existing.png'))->toBe('fixture');
});
