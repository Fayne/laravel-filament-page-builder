<?php

namespace Fayne\PageBuilder\Fields\Resolvers;

use Filament\Forms\Components\Field;
use Filament\Forms\Components\TagsInput;

class TagResolver extends DefaultFieldResolver
{
    protected function build(string $name, array $options): Field
    {
        return TagsInput::make($name);
    }
}
