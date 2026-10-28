<?php

namespace Fayne\PageBuilder\Fields\Resolvers;

use Fayne\Cascade\Forms\Components\CascadeField;
use Filament\Forms\Components\Field;

class CascadeResolver extends DefaultFieldResolver
{
    protected function build(string $name, array $options): Field
    {
        return CascadeField::make($name);
    }
}
