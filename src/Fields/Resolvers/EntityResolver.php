<?php

namespace Fayne\PageBuilder\Fields\Resolvers;

use Filament\Forms\Components\Field;

class EntityResolver extends DefaultFieldResolver
{
    protected function build(string $name, array $options): Field
    {
        return $this->makeEntityReference($name, $options);
    }
}
