<?php

namespace Fayne\PageBuilder\Fields\Resolvers;

use Filament\Forms\Components\ColorPicker;
use Filament\Forms\Components\Field;

class ColorResolver extends DefaultFieldResolver
{
    protected function build(string $name, array $options): Field
    {
        return ColorPicker::make($name);
    }
}
