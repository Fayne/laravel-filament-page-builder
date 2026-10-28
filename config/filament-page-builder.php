<?php

return [
    'rich_text' => ['images' => ['directory' => 'rich-content', 'max_size' => 5120]],
    'resolve_entities' => false,
    // Only explicitly configured model aliases and public fields may be resolved.
    'entities' => [],
    // Exact endpoints and methods; public resolution is GET-only and opt-in. Headers here override stored headers.
    'http' => ['endpoints' => []],
];
