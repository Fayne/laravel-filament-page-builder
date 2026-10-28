<x-dynamic-component :component="$getFieldWrapperView()" :field="$field">
    <div x-data="{
        state: $wire.$entangle(@js($getStatePath())),
        catalog: @js(\Fayne\PageBuilder\Support\ValidationRuleConfig::catalog()),
        advanced: false,
        config() {
            if (typeof this.state === 'string') { try { return JSON.parse(this.state) ?? {}; } catch { return {}; } }
            return this.state ?? {};
        },
        opaque(group) { const value = this.config()[group]; return typeof value === 'string' && /(?:^|\|)(?:regex|not_regex):/.test(value); },
        entries(group) { const value = this.config()[group]; return Array.isArray(value) ? value : (typeof value === 'string' ? (this.opaque(group) ? [value] : value.split('|')) : []); },
        type(rule) {
            if (typeof rule !== 'string' || rule.includes('|')) return '__custom';
            const name = rule.split(':', 1)[0];
            return this.catalog[name] ? name : '__custom';
        },
        availableTypes(group, index) {
            const selected = this.entries(group).filter((_, i) => i !== index).map(rule => this.type(rule));
            const current = this.type(this.entries(group)[index]);
            return Object.keys(this.catalog).filter(type => type === current || !selected.includes(type));
        },
        parameter(rule) { const colon = String(rule).indexOf(':'); return colon === -1 ? '' : rule.slice(colon + 1); },
        bound(rule, position) { return this.parameter(rule).split(',')[position] ?? ''; },
        updateBound(group, index, position, value) {
            const rule = this.entries(group)[index];
            const bounds = [this.bound(rule, 0), this.bound(rule, 1)];
            bounds[position] = value;
            this.update(group, index, 'between:' + bounds.join(','));
        },
        update(group, index, value) { if (this.opaque(group)) return; const items = [...this.entries(group)]; items[index] = value; this.state = {...this.config(), [group]: items}; },
        changeType(group, index, type) {
            if (type === '__custom') { this.update(group, index, ''); return; }
            this.update(group, index, type + (this.catalog[type][1] ? ':' : ''));
        },
        add(group) { if (this.opaque(group)) return; this.state = {...this.config(), [group]: [...this.entries(group), this.availableTypes(group, -1)[0] ?? '']}; },
        remove(group, index) { if (this.opaque(group)) return; this.state = {...this.config(), [group]: this.entries(group).filter((_, i) => i !== index)}; }
    }" class="cms-definition-rules">
        <p>{{ __('filament-page-builder::ui.validation_rules_help') }}</p>
        @foreach (['rules' => __('filament-page-builder::ui.general_rules'), 'creationRules' => __('filament-page-builder::ui.creation_rules'), 'updateRules' => __('filament-page-builder::ui.update_rules')] as $group => $label)
            <details @if($group === 'rules') open @endif x-show="!advanced">
                <summary>{{ $label }}</summary>
                <p x-show="opaque(@js($group))">{{ __('filament-page-builder::ui.compound_regex_help') }}</p>
                <template x-for="(rule, index) in entries(@js($group))" :key="index">
                    <div class="cms-rule-row">
                        <select :disabled="opaque(@js($group))" :value="type(rule)" @change="changeType(@js($group), index, $event.target.value)" aria-label="{{ __('filament-page-builder::ui.group_type', ['label' => $label]) }}">
                            <template x-for="option in availableTypes(@js($group), index)" :key="option">
                                <option :value="option" :selected="type(rule) === option" x-text="catalog[option][0]"></option>
                            </template>
                            <option value="__custom">{{ __('filament-page-builder::ui.custom_or_compound_rule_preserve_source') }}</option>
                        </select>
                        <template x-if="type(rule) !== '__custom' && type(rule) !== 'between' && catalog[type(rule)]?.[1]">
                            <input type="text" :value="parameter(rule)" @input="update(@js($group), index, type(rule) + ':' + $event.target.value)" aria-label="{{ __('filament-page-builder::ui.group_parameters', ['label' => $label]) }}" placeholder="{{ __('filament-page-builder::ui.enter_rule_parameters') }}">
                        </template>
                        <template x-if="type(rule) === 'between'">
                            <input type="number" step="any" :value="bound(rule, 0)" @input="updateBound(@js($group), index, 0, $event.target.value)" aria-label="{{ __('filament-page-builder::ui.group_minimum', ['label' => $label]) }}" placeholder="{{ __('filament-page-builder::ui.minimum') }}">
                        </template>
                        <template x-if="type(rule) === 'between'">
                            <input type="number" step="any" :value="bound(rule, 1)" @input="updateBound(@js($group), index, 1, $event.target.value)" aria-label="{{ __('filament-page-builder::ui.group_maximum', ['label' => $label]) }}" placeholder="{{ __('filament-page-builder::ui.maximum') }}">
                        </template>
                        <template x-if="type(rule) === '__custom'">
                            <input type="text" :disabled="opaque(@js($group))" :value="typeof rule === 'string' ? rule : JSON.stringify(rule)" @input="update(@js($group), index, $event.target.value)" aria-label="{{ __('filament-page-builder::ui.group_original', ['label' => $label]) }}" placeholder="{{ __('filament-page-builder::ui.original_laravel_rule') }}">
                        </template>
                        <button type="button" :disabled="opaque(@js($group))" @click="remove(@js($group), index)" aria-label="{{ __('filament-page-builder::ui.delete_group', ['label' => $label]) }}">{{ __('filament-page-builder::ui.delete') }}</button>
                    </div>
                </template>
                <button type="button" :disabled="opaque(@js($group))" @click="add(@js($group))">{{ __('filament-page-builder::ui.add_group', ['label' => $label]) }}</button>
            </details>
        @endforeach
        @include('filament-page-builder::editor-mode-toggle')
        <div x-show="advanced" x-cloak>
            <p>{{ __('filament-page-builder::ui.advanced_rules_help') }}</p>
            <textarea rows="10" aria-label="{{ __('filament-page-builder::ui.advanced_rules_json') }}" :value="typeof state === 'string' ? state : JSON.stringify(state, null, 2)" @input="state = $event.target.value"></textarea>
        </div>
        <style>
            .cms-definition-rules{display:grid;gap:12px}.cms-definition-rules p{font-size:.875rem;color:#6b7280}.cms-definition-rules details{padding:12px;border:1px solid #d1d5db;border-radius:8px}.cms-definition-rules summary{font-weight:600;cursor:pointer;margin-bottom:8px}.cms-rule-row{display:flex;gap:8px;flex-wrap:wrap;margin:8px 0}.cms-rule-row select{flex:1;min-width:200px}.cms-rule-row input{flex:1;min-width:140px}.cms-definition-rules select,.cms-definition-rules input,.cms-definition-rules textarea{border:1px solid #9ca3af;border-radius:6px;padding:8px;background:transparent}.cms-definition-rules textarea{width:100%;font-family:monospace}.cms-definition-rules button{border:1px solid #9ca3af;border-radius:6px;padding:6px 10px;width:fit-content;cursor:pointer}.dark .cms-definition-rules details{border-color:#4b5563}
        </style>
    </div>
</x-dynamic-component>
