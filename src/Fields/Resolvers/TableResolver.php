<?php

namespace Fayne\PageBuilder\Fields\Resolvers;

use Fayne\TableField\Forms\Components\TableField;
use Filament\Forms\Components\Field;

class TableResolver extends DefaultFieldResolver
{
    protected function build(string $name, array $options): Field
    {
        return TableField::make($name);
    }
}
