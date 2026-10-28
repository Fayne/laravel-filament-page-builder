<?php

namespace Fayne\PageBuilder\Filament\Forms\Components;

use Fayne\PageBuilder\Support\ContentHttpRequest;
use Filament\Actions\Action;
use Filament\Forms\Components\Field;
use Filament\Notifications\Notification;
use Filament\Schemas\Components\Text;
use Illuminate\Support\Facades\Gate;
use Illuminate\Validation\ValidationException;

class HttpContentField extends Field
{
    protected string $view = 'filament-page-builder::forms.http';

    protected array $httpOptions = [];

    public function httpOptions(array $options): static
    {
        $this->httpOptions = $options;

        return $this;
    }

    protected function setUp(): void
    {
        parent::setUp();
        $this->rule(fn (): \Closure => function (string $attribute, mixed $value, \Closure $fail): void {
            if ($value !== null && ! is_array($value)) {
                $fail(__('filament-page-builder::fields.http_must_be_object'));
            }
        });
        $this->registerActions([
            Action::make('testRequest')->label(__('filament-page-builder::fields.test_request'))->schema(fn (): array => [Text::make(__('filament-page-builder::fields.request_sent_on_click'))])->action(function (HttpContentField $component): void {
                $admin = auth(config('filament-cms.guard', 'admin'))->user();
                $record = $component->getRecord();
                abort_unless($admin?->is_active && $record, 403);
                Gate::forUser($admin)->authorize('update', $record);
                Gate::forUser($admin)->authorize('executeContentHttp', $record);
                try {
                    $response = app(ContentHttpRequest::class)->execute($component->getState() ?? []);
                } catch (\InvalidArgumentException $exception) {
                    throw ValidationException::withMessages([$component->getStatePath() => $exception->getMessage()]);
                }
                if ($component->httpOptions['fixedResponse'] ?? false) {
                    $component->state([...($component->getState() ?? []), 'fixedResponseData' => $response]);
                }
                Notification::make()->title($response['ok'] ? __('filament-page-builder::fields.request_succeeded') : __('filament-page-builder::fields.request_failed'))->body('HTTP '.($response['status'] ?? __('filament-page-builder::fields.connection_failed')))->send();
            }),
        ]);
    }

    public function hydrateState(?array &$hydratedDefaultState, bool $shouldCallHydrationHooks = true, bool $shouldApplyStateCasts = true, array &$appliedStateCastPaths = []): void
    {
        $value = $this->getRawState();
        if (is_string($value)) {
            $this->rawState($value === '' ? null : json_decode($value, true, flags: JSON_THROW_ON_ERROR));
        }
        parent::hydrateState($hydratedDefaultState, $shouldCallHydrationHooks, $shouldApplyStateCasts, $appliedStateCastPaths);
    }
}
