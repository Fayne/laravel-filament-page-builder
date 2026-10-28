<?php

namespace Fayne\PageBuilder\Fields\Resolvers;

use Filament\Forms\Components\Field;

class TextResolver extends DefaultFieldResolver
{
    protected function build(string $name, array $options): Field
    {
        return $this->makeText($name, $options);
    }
}
