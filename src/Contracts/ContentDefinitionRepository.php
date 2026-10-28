<?php

namespace Fayne\PageBuilder\Contracts;

interface ContentDefinitionRepository
{
    /** @return array<array{name: string, resolver: string, options?: array, rules?: array}> */
    public function fields(string $layout, bool $widget = false): array;
}
