<?php

namespace Fayne\PageBuilder\Fields\Resolvers;

use Fayne\OpeningHours\Forms\Components\OpeningHoursField;
use Filament\Forms\Components\Field;

class OpeningHoursResolver extends DefaultFieldResolver
{
    protected function build(string $name, array $options): Field
    {
        return OpeningHoursField::make($name);
    }
}
