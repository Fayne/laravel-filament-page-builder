<?php

namespace Fayne\PageBuilder\Fields\Resolvers;

use Filament\Forms\Components\Field;
use Filament\Forms\Components\Toggle;

class BooleanResolver extends DefaultFieldResolver
{
    protected function build(string $name, array $options): Field
    {
        return Toggle::make($name);
    }
}
