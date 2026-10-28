<?php

namespace Fayne\PageBuilder\Support;

use Fayne\PageBuilder\Contracts\ContentDefinitionRepository;
use Fayne\PageBuilder\Filament\Forms\Components\ContentBuilder;

final readonly class FieldResolverContext
{
    public function __construct(
        public ContentDefinitionRepository $definitions,
        public array $widgets,
        public array $definitionPath,
        public string $builderClass = ContentBuilder::class,
    ) {}
}
