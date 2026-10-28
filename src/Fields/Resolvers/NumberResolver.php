<?php

namespace Fayne\PageBuilder\Fields\Resolvers;

use Filament\Forms\Components\Field;
use Filament\Forms\Components\TextInput;

class NumberResolver extends DefaultFieldResolver
{
    protected function build(string $name, array $options): Field
    {
        return TextInput::make($name);
    }
}
