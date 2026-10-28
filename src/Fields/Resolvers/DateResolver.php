<?php

namespace Fayne\PageBuilder\Fields\Resolvers;

use Filament\Forms\Components\DatePicker;
use Filament\Forms\Components\Field;

class DateResolver extends DefaultFieldResolver
{
    protected function build(string $name, array $options): Field
    {
        return DatePicker::make($name);
    }
}
