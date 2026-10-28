<x-dynamic-component :component="$getFieldWrapperView()" :field="$field">
<div x-data="{
 state: $wire.$entangle(@js($getStatePath())),
 set(key,value) { this.state ??= {}; this.state[key]=value; delete this.state.fixedResponseData; },
 rows(key) { const value=this.state?.[key]; return Array.isArray(value) ? value : (value && typeof value==='object' ? Object.entries(value).map(([key,value])=>({key,value})) : []); },
 row(key,index,property,value) { let rows=this.rows(key).map(item=>({...item})); rows[index][property]=value;this.set(key,rows); }
}" style="display:grid;gap:12px">
    <label>URL<input type="url" aria-label="{{ __('filament-page-builder::ui.request_url') }}" :value="state?.url ?? ''" @input="set('url',$event.target.value)" style="width:100%" /></label>
    <label>{{ __('filament-page-builder::ui.method') }}<select aria-label="{{ __('filament-page-builder::ui.request_method') }}" :value="state?.method ?? 'GET'" @input="set('method',$event.target.value)">@foreach (['GET','POST','PUT','PATCH','DELETE'] as $method)<option>{{ $method }}</option>@endforeach</select></label>
    <label>{{ __('filament-page-builder::ui.body_type') }}<select aria-label="{{ __('filament-page-builder::ui.body_type') }}" :value="state?.bodyType ?? 'raw'" @input="set('bodyType',$event.target.value)"><option value="raw">JSON</option><option value="form-data">form-data</option><option value="x-www-form-urlencoded">x-www-form-urlencoded</option></select></label>
    <label>{{ __('filament-page-builder::ui.json_body') }}<textarea aria-label="{{ __('filament-page-builder::ui.json_body') }}" :value="typeof state?.body==='string' ? state.body : JSON.stringify(state?.body ?? {})" @input="set('body',$event.target.value)"></textarea></label>
    @foreach (['headers' => __('filament-page-builder::ui.headers'), 'additionalData' => __('filament-page-builder::ui.additional_data'), 'body' => __('filament-page-builder::ui.key_value_body')] as $key => $label)
        <div x-show="@js($key)!=='body' || (state?.bodyType && state.bodyType!=='raw')"><strong>{{ $label }}</strong>
            <template x-for="(item,index) in rows(@js($key))" :key="index"><div style="display:flex;gap:8px"><input type="text" aria-label="{{ __('filament-page-builder::ui.item_name', ['label' => $label]) }}" :value="item.key" @input="row(@js($key),index,'key',$event.target.value)" /><input type="text" aria-label="{{ __('filament-page-builder::ui.item_value', ['label' => $label]) }}" :value="item.value" @input="row(@js($key),index,'value',$event.target.value)" /><button type="button" @click="set(@js($key),rows(@js($key)).filter((_,i)=>i!==index))">{{ __('filament-page-builder::ui.delete') }}</button></div></template>
            <button type="button" @click="set(@js($key),[...rows(@js($key)),{key:'',value:''}])">{{ __('filament-page-builder::ui.add_item', ['label' => $label]) }}</button>
        </div>
    @endforeach
    <label>{{ __('filament-page-builder::ui.target_field') }}<input type="text" aria-label="{{ __('filament-page-builder::ui.response_target_field') }}" :value="state?.[state?.mappingTargetKey ?? 'targetAttribute'] ?? ''" @input="set(state?.mappingTargetKey ?? 'targetAttribute',$event.target.value)" /></label>
    <label>{{ __('filament-page-builder::ui.response_path') }}<input type="text" aria-label="{{ __('filament-page-builder::ui.response_path') }}" :value="state?.[state?.mappingPathKey ?? 'responsePath'] ?? ''" @input="set(state?.mappingPathKey ?? 'responsePath',$event.target.value)" /></label>
    {{ $field->getAction('testRequest') }}
</div>
</x-dynamic-component>
