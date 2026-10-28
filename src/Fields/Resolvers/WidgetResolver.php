<?php

namespace Fayne\PageBuilder\Fields\Resolvers;

use Fayne\PageBuilder\Contracts\FieldResolver;
use Fayne\PageBuilder\Support\FieldResolverContext;
use Filament\Forms\Components\Field;
use Illuminate\Support\HtmlString;
use InvalidArgumentException;

class WidgetResolver implements FieldResolver
{
    public function resolve(array $definition, ?FieldResolverContext $context = null): Field
    {
        if (! $context) {
            throw new InvalidArgumentException(__('filament-page-builder::errors.widget_context_required'));
        }
        $options = $definition['options'] ?? [];
        if (array_diff(array_keys($options), ['widgets', 'limit', 'help', 'required', 'fullWidth']) !== []) {
            throw new InvalidArgumentException(__('filament-page-builder::errors.unsupported_widget_options'));
        }
        $allowed = $options['widgets'] ?? array_keys($context->widgets);
        if (array_diff($allowed, array_keys($context->widgets)) !== []) {
            throw new InvalidArgumentException(__('filament-page-builder::errors.missing_widget_definition'));
        }
        $builder = $context->builderClass::make($definition['name']);

        return $builder->nestedWidgetContext($context->definitionPath)
            ->definitions($context->definitions, array_intersect_key($context->widgets, array_flip($allowed)), $context->widgets)
            ->label($definition['title'] ?? $definition['name'])->maxItems($options['limit'] ?? null)
            ->helperText(isset($options['help']) ? new HtmlString($options['help']) : null)->columnSpan(($options['fullWidth'] ?? false) ? 'full' : 1)
            ->required((bool) ($options['required'] ?? false))
            ->rules($definition['rules']['rules'] ?? [])
            ->rules($definition['rules']['creationRules'] ?? [], fn (string $operation): bool => $operation === 'create')
            ->rules($definition['rules']['updateRules'] ?? [], fn (string $operation): bool => $operation === 'edit');
    }
}
