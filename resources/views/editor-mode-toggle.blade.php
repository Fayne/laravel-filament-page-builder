<button
    type="button"
    class="cms-editor-mode-toggle"
    @click="advanced = !advanced"
    :aria-pressed="advanced"
    :aria-label="advanced ? @js(__('filament-page-builder::ui.return_to_visual_editor')) : @js(__('filament-page-builder::ui.switch_to_json'))"
    :title="advanced ? @js(__('filament-page-builder::ui.configure_options_using_the_form')) : @js(__('filament-page-builder::ui.edit_json_source_directly'))"
>
    <span x-show="!advanced" aria-hidden="true">
        <x-filament::icon icon="heroicon-m-code-bracket" class="size-4" />
    </span>
    <span x-show="advanced" x-cloak aria-hidden="true">
        <x-filament::icon icon="heroicon-m-queue-list" class="size-4" />
    </span>
    <span x-text="advanced ? @js(__('filament-page-builder::ui.return_to_visual_editor')) : @js(__('filament-page-builder::ui.switch_to_json'))">{{ __('filament-page-builder::ui.switch_to_json') }}</span>
</button>
<style>
    .cms-editor-mode-toggle.cms-editor-mode-toggle{display:inline-flex;align-items:center;gap:6px;min-height:36px;padding:6px 12px;border:1px solid #9ca3af;border-radius:8px;font-size:.875rem;font-weight:500;transition:background-color .15s,border-color .15s}.cms-editor-mode-toggle:hover{background-color:#f3f4f6}.cms-editor-mode-toggle:focus-visible{outline:2px solid #6366f1;outline-offset:3px}.cms-editor-mode-toggle[aria-pressed=true]{background-color:#eef2ff;border-color:#818cf8;color:#3730a3}.dark .cms-editor-mode-toggle:hover{background-color:#374151}.dark .cms-editor-mode-toggle[aria-pressed=true]{background-color:#312e81;border-color:#818cf8;color:#e0e7ff}
</style>
