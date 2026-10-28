<?php

namespace Fayne\PageBuilder\Support;

use Fayne\CMS\Support\ContentFieldTypes;
use Fayne\PageBuilder\Contracts\FieldResolver;
use Filament\Forms\Components\Field;
use InvalidArgumentException;

final class FieldSchemaFactory
{
    public function make(array $definition, ?FieldResolverContext $context = null): Field
    {
        $type = $definition['resolver'];
        $class = ContentFieldTypes::definitions()[$type]['resolver'] ?? null;
        if (! is_string($class) || ! is_subclass_of($class, FieldResolver::class)) {
            throw new InvalidArgumentException(__('filament-page-builder::errors.invalid_field_resolver', ['type' => $type]));
        }

        return app($class)->resolve($definition, $context);
    }
}
