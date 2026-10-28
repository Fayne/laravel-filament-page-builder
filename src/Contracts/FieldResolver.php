<?php

namespace Fayne\PageBuilder\Contracts;

use Fayne\PageBuilder\Support\FieldResolverContext;
use Filament\Forms\Components\Field;

interface FieldResolver
{
    public function resolve(array $definition, ?FieldResolverContext $context = null): Field;
}
