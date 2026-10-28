<?php

namespace Fayne\PageBuilder\Fields\Resolvers;

use Filament\Forms\Components\Field;
use Filament\Forms\Components\Select;

class SelectResolver extends DefaultFieldResolver
{
    protected function build(string $name, array $options): Field
    {
        return Select::make($name);
    }
}
