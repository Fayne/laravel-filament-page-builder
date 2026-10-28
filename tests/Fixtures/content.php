<?php

return [
    [
        'layout' => 'hero', 'key' => 'hero-original', 'internalTitle' => '首页首屏', 'popover' => null,
        'attributes' => [
            'title' => '欢迎', 'internalTitle' => '业务标题', '__content' => ['keep' => true],
            'zero' => 0, 'enabled' => false, 'optional' => null, 'empty' => [],
            'image' => ['disk' => 'public', 'path' => 'hero.jpg'],
            'entity' => ['model' => 'product', 'id' => 7],
            'json' => [['layout' => 'ordinary', 'key' => 'json-key', 'attributes' => ['x' => 1]]],
            'items' => [[
                'layout' => 'card', 'key' => 'card-original', 'internalTitle' => null,
                'attributes' => ['title' => '卡片', 'items' => [[
                    'layout' => 'link', 'key' => 'link-original',
                    'attributes' => ['url' => '/about'],
                ]]],
            ]],
            'unknown' => ['preserved' => true],
        ],
    ],
    ['layout' => 'retired', 'key' => 'retired-original', 'attributes' => ['old' => 'value']],
];
