<x-dynamic-component :component="$getFieldWrapperView()" :field="$field">
    <div x-data="{
        state: $wire.$entangle(@js($getStatePath())),
        resolver: $wire.$entangle(@js($getResolverStatePath())),
        definitions: @js($getOptionDefinitions()),
        advanced: false,
        config() { try { const value = typeof this.state === 'string' ? JSON.parse(this.state) : this.state; return value !== null && typeof value === 'object' ? value : {}; } catch { return {}; } },
        valid() { try { const value = typeof this.state === 'string' ? JSON.parse(this.state) : this.state; return value === null || typeof value === 'object'; } catch { return false; } },
        fields() { return this.definitions[this.resolver] ?? {}; },
        value(key, kind) { const value = this.config()[key]; if (kind === 'lines') return Array.isArray(value) ? value.join('\n') : (value ?? ''); if (kind === 'json') return value === undefined ? '' : JSON.stringify(value, null, 2); return value ?? ''; },
        set(key, value, kind) {
            if (!this.valid()) return;
            const config = {...this.config()};
            if (value === '') delete config[key];
            else if (kind === 'number' || kind === 'integer') config[key] = Number(value);
            else if (kind === 'lines') config[key] = value.split('\n').filter(item => item !== '');
            else if (kind === 'json') { try { config[key] = JSON.parse(value); } catch { config[key] = value; } }
            else config[key] = value;
            this.state = JSON.stringify(config);
        },
        reset(key) { this.set(key, ''); },
        checked(key, field) { return this.config()[key] ?? field[3] ?? false; },
        selected(key) { let value = this.config()[key] ?? this.fields()[key]?.[3]; if (typeof value === 'string') { try { value = JSON.parse(value); } catch { value = []; } } return (Array.isArray(value) ? value : (value?.items ?? [])).map(String); },
        choices(key, field) { const choices = {...(field[2] ?? {})}; for (const value of this.selected(key)) if (!Object.hasOwn(choices, value)) choices[value] = value + @js(__('filament-page-builder::ui.existing_setting')); return choices; },
        setMultiple(key, values) { this.set(key, key === 'widgets' ? values.map(Number) : values); },
        pairs(key) { return Object.entries(this.config()[key] ?? {}); },
        setPair(key, oldKey, newKey, value) { const pairs = {...(this.config()[key] ?? {})}; delete pairs[oldKey]; pairs[newKey] = value; this.set(key, pairs); },
        addPair(key) { const pairs = {...(this.config()[key] ?? {})}; let index = 1; while (Object.hasOwn(pairs, 'option_' + index)) index++; pairs['option_' + index] = ''; this.set(key, pairs); },
        removePair(key, name) { const pairs = {...(this.config()[key] ?? {})}; delete pairs[name]; this.set(key, pairs); }
    }" class="cms-field-options">
        <p>{{ __('filament-page-builder::ui.field_options_help') }}</p>
        <p x-show="!valid()" role="alert">{{ __('filament-page-builder::ui.fix_json_before_visual') }}</p>
        <div x-show="!advanced && valid()" class="cms-option-grid">
            <template x-for="(field, key) in fields()" :key="resolver + ':' + key">
                <fieldset class="cms-option-field">
                    <legend x-text="field[0]"></legend>
                    <template x-if="field[1] === 'boolean'">
                        <label><input type="checkbox" :checked="checked(key, field)" @change="set(key, $event.target.checked)" :aria-label="field[0]"> {{ __('filament-page-builder::ui.enabled') }}</label>
                    </template>
                    <template x-if="['text', 'number', 'integer'].includes(field[1])">
                        <input :type="field[1] === 'text' ? 'text' : 'number'" :step="field[1] === 'integer' ? '1' : 'any'" :value="value(key)" @input="set(key, $event.target.value, field[1])" :aria-label="field[0]" placeholder="{{ __('filament-page-builder::ui.use_default') }}">
                    </template>
                    <template x-if="['lines', 'json'].includes(field[1])">
                        <textarea rows="4" :value="value(key, field[1])" @change="set(key, $event.target.value, field[1])" :aria-label="field[0]"></textarea>
                    </template>
                    <template x-if="field[1] === 'select'">
                        <select :value="value(key)" @change="set(key, $event.target.value)" :aria-label="field[0]">
                            <option value="">{{ __('filament-page-builder::ui.use_system_default') }}</option>
                            <template x-for="(label, option) in {...(field[2] ?? {}), ...(config()[key] && !Object.hasOwn(field[2] ?? {}, config()[key]) ? {[config()[key]]: config()[key] + @js(__('filament-page-builder::ui.existing_setting'))} : {})}" :key="option">
                                <option :value="option" :selected="String(value(key)) === String(option)" x-text="label"></option>
                            </template>
                        </select>
                    </template>
                    <template x-if="field[1] === 'multiple'">
                        <div>
                            <select multiple size="5" @change="setMultiple(key, Array.from($event.target.selectedOptions, option => option.value))" :aria-label="field[0]">
                                <template x-for="(label, option) in choices(key, field)" :key="option">
                                    <option :value="option" :selected="selected(key).includes(String(option))" x-text="label"></option>
                                </template>
                            </select>
                            <p x-show="key === 'widgets'">{{ __('filament-page-builder::ui.widget_allowlist_help') }}</p><p>{{ __('filament-page-builder::ui.hold_ctrl_or_command_to_select_multiple_items') }}</p>
                        </div>
                    </template>
                    <template x-if="field[1] === 'pairs'">
                        <div>
                            <template x-for="(pair, index) in pairs(key)" :key="index">
                                <div class="cms-option-pair">
                                    <input type="text" :value="pair[0]" @change="setPair(key, pair[0], $event.target.value, pair[1])" :aria-label="field[3]?.key ?? @js(__('filament-page-builder::ui.option_value'))" :placeholder="field[3]?.key ?? @js(__('filament-page-builder::ui.value'))">
                                    <input type="text" :value="pair[1]" @change="setPair(key, pair[0], pair[0], $event.target.value)" :aria-label="field[3]?.value ?? @js(__('filament-page-builder::ui.option_label'))" :placeholder="field[3]?.value ?? @js(__('filament-page-builder::ui.display_name'))">
                                    <button type="button" @click="removePair(key, pair[0])">{{ __('filament-page-builder::ui.delete') }}</button>
                                </div>
                            </template>
                            <button type="button" @click="addPair(key)">{{ __('filament-page-builder::ui.add_option') }}</button>
                        </div>
                    </template>
                    <button type="button" class="cms-option-reset" @click="reset(key)" x-show="Object.hasOwn(config(), key)">{{ __('filament-page-builder::ui.restore_default') }}</button>
                </fieldset>
            </template>
        </div>
        <p x-show="resolver === 'text'">{{ __('filament-page-builder::ui.text_options_help') }}</p>
        <p x-show="resolver === 'entity'">{{ __('filament-page-builder::ui.entity_options_help') }}</p>
        <p x-show="resolver === 'cascade'">{{ __('filament-page-builder::ui.cascade_options_help') }}</p>
        <p x-show="resolver === 'http'">{{ __('filament-page-builder::ui.http_options_help') }}</p>
        <details><summary>{{ __('filament-page-builder::ui.legacy_configuration_compatibility') }}</summary><p>{{ __('filament-page-builder::ui.legacy_options_help') }}</p></details>
        @include('filament-page-builder::editor-mode-toggle')
        <textarea x-show="advanced" x-cloak rows="12" aria-label="{{ __('filament-page-builder::ui.advanced_field_options_json') }}" :value="state" @input="state = $event.target.value"></textarea>
        <style>
            .cms-field-options{display:grid;gap:12px}.cms-field-options p{font-size:.875rem;color:#6b7280}.cms-option-grid{display:grid;grid-template-columns:repeat(auto-fit,minmax(min(100%,280px),1fr));gap:16px}.cms-option-field{border:1px solid #d1d5db;border-radius:8px;padding:12px;min-width:0}.cms-option-field legend{font-weight:600;padding:0 4px}.cms-field-options input:not([type=checkbox]),.cms-field-options select,.cms-field-options textarea{width:100%;border:1px solid #9ca3af;border-radius:6px;padding:8px;background:transparent}.cms-field-options button{border:1px solid #9ca3af;border-radius:6px;padding:4px 8px;width:fit-content;cursor:pointer}.cms-option-reset{font-size:.75rem;margin-top:6px}.cms-option-pair{display:flex;gap:6px;margin-bottom:8px}.cms-option-pair input{min-width:0}.dark .cms-option-field{border-color:#4b5563}
        </style>
    </div>
</x-dynamic-component>
