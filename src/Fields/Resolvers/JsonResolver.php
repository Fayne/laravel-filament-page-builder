<?php

namespace Fayne\PageBuilder\Fields\Resolvers;

use Filament\Forms\Components\Field;
use Filament\Forms\Components\Textarea;

class JsonResolver extends DefaultFieldResolver
{
    protected function build(string $name, array $options): Field
    {
        return Textarea::make($name);
    }
}
