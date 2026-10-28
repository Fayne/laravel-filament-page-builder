const {test} = require('node:test');
const assert = require('node:assert/strict');
const {readFileSync} = require('node:fs');
const {resolve} = require('node:path');
function editor(initial, resolver = 'text') {
    const view = readFileSync(resolve(__dirname, '../../resources/views/field-options-input.blade.php'), 'utf8');
    const expression = view.match(/<div x-data="([\s\S]*?)" class=/)[1]
        .replace('@js($getStatePath())', "'options'")
        .replace('@js($getResolverStatePath())', "'resolver'")
        .replace('@js($getOptionDefinitions())', JSON.stringify({text:{placeholder:['提示','text']},number:{step:['步长','number']}}))
        .replace("@js(__('filament-page-builder::ui.existing_setting'))", JSON.stringify(' (existing)'));
    return new Function('$wire', `return (${expression})`)({$entangle: path => path === 'options' ? JSON.stringify(initial) : resolver});
}
test('visual options preserve unknown nested data, value types and configuration on type switches', () => {
    const initial = {legacy:{keep:true}, placeholder:'原文', required:false};
    const form = editor(initial);
    assert.deepEqual(form.config(), initial);
    form.set('placeholder', '新提示');
    form.set('required', true);
    form.resolver = 'number';
    form.set('step', '0.5', 'number');
    assert.deepEqual(form.config(), {...initial, placeholder:'新提示', required:true, step:0.5});
    form.set('step', '', 'number');
    assert.equal(Object.hasOwn(form.config(), 'step'), false);
    assert.equal(form.config().placeholder, '新提示');
});
test('candidate pairs, lists and numeric widget IDs use the legacy options structure', () => {
    const form = editor({options:{draft:'草稿'}, widgets:[1,2]});
    form.setPair('options', 'draft', 'draft', '待发布');
    assert.deepEqual(form.config().options, {draft:'待发布'});
    form.setPair('options', 'draft', 'published', '已发布');
    assert.deepEqual(form.config().options, {published:'已发布'});
    form.set('suggestions', '甲\n乙', 'lines');
    assert.deepEqual(form.config().suggestions, ['甲','乙']);
    form.setMultiple('widgets', ['2','3']);
    assert.deepEqual(form.config().widgets, [2,3]);
});
test('invalid advanced JSON is never overwritten by a visual edit', () => {
    const form = editor({});
    form.state = '{broken';
    form.set('required', true);
    assert.equal(form.state, '{broken');
});
test('boolean defaults and legacy rich toolbar values are shown without writing defaults', () => {
    const form = editor({toolbar:'{"items":["bold","uploadImage"]}'});
    assert.deepEqual(form.selected('toolbar'), ['bold','uploadImage']);
    assert.equal(form.checked('allowExceptions', ['允许例外','boolean',null,true]), true);
    assert.equal(Object.hasOwn(form.config(), 'allowExceptions'), false);
});
