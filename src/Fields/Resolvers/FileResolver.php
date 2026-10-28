<?php

namespace Fayne\PageBuilder\Fields\Resolvers;

use Fayne\PageBuilder\Filament\Forms\Components\ContentMediaPicker;
use Filament\Forms\Components\Field;
use UniFileManager\Core\Support\DirectoryScope;

class FileResolver extends DefaultFieldResolver
{
    protected function build(string $name, array $options): Field
    {
        $field = ContentMediaPicker::make($name)
            ->contentDisk($options['disk'] ?? config('filament-cms.media.disk', 'public'))
            ->directory(DirectoryScope::normalise($options['directory'] ?? ''))
            ->multiple((bool) ($options['is_multiple'] ?? false));
        if ($options['is_image'] ?? false) {
            $field->allowedMimeTypes(['image/*']);
        }

        return $field;
    }
}
