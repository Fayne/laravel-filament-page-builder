<?php

namespace Fayne\PageBuilder\Fields\Resolvers;

use Fayne\PageBuilder\Filament\Forms\Components\ContentRichEditor;
use Filament\Forms\Components\Field;

class RichTextResolver extends DefaultFieldResolver
{
    protected function build(string $name, array $options): Field
    {
        return ContentRichEditor::make($name)->filamentOptions($options);
    }
}
