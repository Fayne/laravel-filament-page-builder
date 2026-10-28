<?php

namespace Fayne\PageBuilder\Fields\Resolvers;

use Filament\Forms\Components\Field;
use Filament\Forms\Components\Textarea;

class TextareaResolver extends DefaultFieldResolver
{
    protected function build(string $name, array $options): Field
    {
        return Textarea::make($name);
    }
}
