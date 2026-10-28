const {test} = require('node:test');
const assert = require('node:assert/strict');
const {readFileSync} = require('node:fs');
const {resolve} = require('node:path');
const view = readFileSync(resolve(__dirname, '../../resources/views/validation-rules-input.blade.php'), 'utf8');
const catalog = {required:['必填',false], nullable:['允许为空',false], min:['最小值',true], max:['最大值',true]};
const expression = view.match(/<div x-data="([\s\S]*?)" class=/)[1]
    .replace('@js($getStatePath())', "'rules'")
    .replace('@js(\\Fayne\\PageBuilder\\Support\\ValidationRuleConfig::catalog())', JSON.stringify(catalog));
function editor(initial) {
    return new Function('$wire', `return (${expression})`)({$entangle: () => structuredClone(initial)});
}
test('legacy pipe rules are split safely for editing while unchanged raw storage stays intact', () => {
    const original = {updateRules:'nullable|max:30', custom:{keep:true}};
    const form = editor(original);
    assert.deepEqual(form.entries('updateRules'), ['nullable','max:30']);
    assert.deepEqual(form.state, original);
    form.add('updateRules');
    assert.deepEqual(form.state, {updateRules:['nullable','max:30','required'],custom:{keep:true}});
});
test('opaque regex strings cannot be accidentally wrapped into a different rule array', () => {
    const original = {rules:'required|regex:/^(a|b)$/'};
    const form = editor(original);
    form.add('rules');
    form.update('rules',0,'required');
    form.remove('rules',0);
    assert.deepEqual(form.state, original);
});
test('between bounds reopen and edit independently without losing the other bound or groups', () => {
    const form = editor({rules:['between:-2.5,100'], updateRules:['nullable']});
    assert.equal(form.bound('between:-2.5,100', 0), '-2.5');
    assert.equal(form.bound('between:-2.5,100', 1), '100');
    form.updateBound('rules', 0, 0, '0');
    assert.deepEqual(form.state, {rules:['between:0,100'], updateRules:['nullable']});
    form.updateBound('rules', 0, 1, '');
    assert.equal(form.state.rules[0], 'between:0,');
    form.updateBound('rules', 0, 1, '20.5');
    assert.equal(form.state.rules[0], 'between:0,20.5');
    assert.equal(form.bound('between:', 1), '');
});
test('rule choices exclude other selected types in the same group and restore deleted types', () => {
    const form = editor({rules:['required','max:20'],creationRules:['required']});
    assert.deepEqual(form.availableTypes('rules', 0), ['required','nullable','min']);
    assert.deepEqual(form.availableTypes('rules', 1), ['nullable','min','max']);
    assert.ok(form.availableTypes('creationRules', 0).includes('max'));
    form.add('rules');
    assert.deepEqual(form.state.rules, ['required','max:20','nullable']);
    form.remove('rules', 0);
    assert.ok(form.availableTypes('rules', 0).includes('required'));
});
