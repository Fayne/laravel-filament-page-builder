<?php

namespace Fayne\PageBuilder\Fields\Resolvers;

use Fayne\PageBuilder\Filament\Forms\Components\HttpContentField;
use Filament\Forms\Components\Field;

class HttpResolver extends DefaultFieldResolver
{
    protected function build(string $name, array $options): Field
    {
        return HttpContentField::make($name)->httpOptions($options);
    }
}
