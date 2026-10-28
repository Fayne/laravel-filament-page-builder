<?php

use Fayne\PageBuilder\Support\FieldSchemaFactory;
use Filament\Schemas\Schema;
use Livewire\Livewire;

require_once __DIR__.'/../Fixtures/ComplexContentForm.php';

beforeEach(fn () => app()->setLocale('zh_CN'));

it('round trips legacy HTML through a rich editor', function (string $resolver) {
    config(['complex-test.fields' => [['name' => 'body', 'resolver' => $resolver]]]);
    $raw = [['layout' => 'hero', 'key' => 'html', 'attributes' => ['body' => '<h2>标题</h2><p><strong>内容</strong> <a href="/about">链接</a></p>']]];
    Livewire::test(ComplexContentForm::class, ['content' => $raw])->call('save')->assertHasNoErrors()->assertSet('saved.components', $raw);
})->with(['rich_text']);

it('round trips weekly and exceptional opening hours in either raw encoding', function (bool $encoded) {
    config(['complex-test.fields' => [['name' => 'hours', 'resolver' => 'opening_hours']]]);
    $hours = ['monday' => ['09:00-12:00', '13:00-18:00'], 'sunday' => [], 'exceptions' => ['2026-12-25' => []]];
    $raw = [['layout' => 'hero', 'key' => 'hours', 'attributes' => ['hours' => $encoded ? json_encode($hours) : $hours]]];
    Livewire::test(ComplexContentForm::class, ['content' => $raw])->assertSee('星期一')->call('save')->assertHasNoErrors()->assertSet('saved.components', $raw);
})->with([true, false]);

it('rejects invalid overlapping or backwards opening ranges', function (array $ranges) {
    config(['complex-test.fields' => [['name' => 'hours', 'resolver' => 'opening_hours']]]);
    $raw = [['layout' => 'hero', 'key' => 'hours', 'attributes' => ['hours' => ['monday' => $ranges]]]];
    Livewire::test(ComplexContentForm::class, ['content' => $raw])->call('save')->assertHasErrors()->assertSet('saved', []);
})->with([[['25:00-26:00']], [['18:00-09:00']], [['09:00-13:00', '12:00-15:00']]]);

it('round trips legacy matrices and merged tables without flattening their contents', function (bool $legacy) {
    config(['complex-test.fields' => [['name' => 'table', 'resolver' => 'table']]]);
    $value = $legacy ? [['Title', 'Value'], ['Name', 'Alice']] : [
        'version' => 1, 'rows' => 1, 'columns' => 2, 'cells' => [[
            ['rowspan' => 1, 'colspan' => 2, 'hidden' => false, 'content' => ['nodeType' => 'document', 'data' => [], 'content' => [['nodeType' => 'paragraph', 'data' => [], 'content' => [['nodeType' => 'text', 'value' => 'Merged', 'marks' => [['type' => 'bold']], 'data' => []]]]]], 'cellStyle' => ['backgroundColor' => '#FFFFFF', 'textAlign' => 'center']],
            ['hidden' => true, 'anchor' => [0, 0]],
        ]],
    ];
    $raw = [['layout' => 'hero', 'key' => 'table-key', 'attributes' => ['table' => $value]]];
    Livewire::test(ComplexContentForm::class, ['content' => $raw])->assertSee('合并单元格')->call('save')->assertHasNoErrors()->assertSet('saved.components', $raw);
})->with([true, false]);

it('rejects invalid table overlap atomically', function () {
    config(['complex-test.fields' => [['name' => 'table', 'resolver' => 'table']]]);
    $form = Livewire::test(ComplexContentForm::class, ['content' => [['layout' => 'hero', 'key' => 't', 'attributes' => ['table' => [['A', 'B']]]]]]);
    $key = array_key_first($form->get('data.components'));
    expect($form->get('data.components.'.$key.'.data.attributes.table'))->toBeString();
    $table = json_decode($form->get('data.components.'.$key.'.data.attributes.table'), true);
    expect($table)->toHaveKey('cells');
    $table['cells'][0][0]['colspan'] = 2;
    $form->set('data.components.'.$key.'.data.attributes.table', json_encode($table))->call('save')->assertHasErrors()->assertSet('saved', []);
});

it('uses Filament toolbar identifiers directly', function () {
    $editor = (new FieldSchemaFactory)->make(['name' => 'body', 'resolver' => 'rich_text', 'options' => ['toolbar' => '["bold","h2","bulletList","link"]']]);
    $editor->container(Schema::make(new ComplexContentForm));
    expect(array_merge(...$editor->getToolbarButtons()))->toBe(['bold', 'h2', 'bulletList', 'link']);
});

it('saves edited rich HTML instead of restoring the previous value', function () {
    config(['complex-test.fields' => [['name' => 'body', 'resolver' => 'rich_text']]]);
    $raw = [['layout' => 'hero', 'key' => 'k', 'attributes' => ['body' => '<p>Old</p>']]];
    $form = Livewire::test(ComplexContentForm::class, ['content' => $raw]);
    $key = array_key_first($form->get('data.components'));
    $form->set('data.components.'.$key.'.data.attributes.body', '<p><em>New</em></p>')->call('save')->assertHasNoErrors()->assertSet('saved.components.0.attributes.body', '<p><em>New</em></p>');
});

it('retains structured table styling when editing a different cell', function () {
    config(['complex-test.fields' => [['name' => 'table', 'resolver' => 'table']]]);
    $form = Livewire::test(ComplexContentForm::class, ['content' => [['layout' => 'hero', 'key' => 'k', 'attributes' => ['table' => [['A', 'B']]]]]]);
    $key = array_key_first($form->get('data.components'));
    $path = 'data.components.'.$key.'.data.attributes.table';
    expect($form->get($path))->toBeString();
    $table = json_decode($form->get($path), true);
    $table['cells'][0][0]['cellStyle']['backgroundColor'] = '#DC2626';
    $table['cells'][0][1]['content']['content'][0]['content'][0]['value'] = 'Changed';
    $form->set($path, json_encode($table))->call('save')->assertHasNoErrors()->assertSet('saved.components.0.attributes.table.cells.0.0.cellStyle.backgroundColor', '#DC2626')->assertSet('saved.components.0.attributes.table.cells.0.1.content.content.0.content.0.value', 'Changed');
});

it('accepts midnight overflow only when explicitly enabled and rejects invalid exception dates', function () {
    config(['complex-test.fields' => [['name' => 'hours', 'resolver' => 'opening_hours', 'options' => ['allowOverflowMidnight' => true]]]]);
    $raw = [['layout' => 'hero', 'key' => 'k', 'attributes' => ['hours' => ['monday' => ['20:00-02:00']]]]];
    $form = Livewire::test(ComplexContentForm::class, ['content' => $raw])->call('save')->assertHasNoErrors()->assertSet('saved.components', $raw);
    $key = array_key_first($form->get('data.components'));
    $form->set('data.components.'.$key.'.data.attributes.hours.exceptions', ['2026-02-30' => []])->call('save')->assertHasErrors();
});

it('preserves legacy rich markup when the native editor only normalizes equivalent HTML', function () {
    config(['complex-test.fields' => [['name' => 'body', 'resolver' => 'rich_text']]]);
    $raw = [['layout' => 'hero', 'key' => 'k', 'attributes' => ['body' => '<p class="legacy"><b>Same</b></p>']]];
    $form = Livewire::test(ComplexContentForm::class, ['content' => $raw]);
    $key = array_key_first($form->get('data.components'));
    $form->set('data.components.'.$key.'.data.attributes.body', '<p><strong>Same</strong></p>')->call('save')->assertHasNoErrors()->assertSet('saved.components', $raw);
});
