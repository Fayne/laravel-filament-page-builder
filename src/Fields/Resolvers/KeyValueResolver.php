<?php

namespace Fayne\PageBuilder\Fields\Resolvers;

use Filament\Forms\Components\Field;
use Filament\Forms\Components\KeyValue;

class KeyValueResolver extends DefaultFieldResolver
{
    protected function build(string $name, array $options): Field
    {
        return KeyValue::make($name);
    }
}
