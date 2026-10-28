<?php

namespace Fayne\PageBuilder\Fields\Resolvers;

use Filament\Forms\Components\DateTimePicker;
use Filament\Forms\Components\Field;

class DatetimeResolver extends DefaultFieldResolver
{
    protected function build(string $name, array $options): Field
    {
        return DateTimePicker::make($name);
    }
}
