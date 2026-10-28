<?php

namespace Fayne\PageBuilder\Support;

use Fayne\PageBuilder\Contracts\ContentDefinitionRepository;
use Fayne\TableField\Support\TablePayloadNormalizer;
use Filament\Forms\Components\RichEditor\RichContentRenderer;
use Illuminate\Support\Str;
use InvalidArgumentException;

final class ContentStateAdapter
{
    public function __construct(private readonly ContentDefinitionRepository $definitions) {}

    public function toBuilder(array $content, bool $widget = false): array
    {
        $seen = [];
        $this->validate($content, $widget, $seen);

        return $this->hydrate($content, $widget);
    }

    public function fromBuilder(array $state, bool $widget = false): array
    {
        $content = [];
        foreach ($state as $block) {
            if (! is_array($block) || ! is_string($block['type'] ?? null) || ! is_array($block['data'] ?? null)) {
                throw new InvalidArgumentException('Invalid content block state.');
            }
            $data = $block['data'];
            $original = $data['envelope'] ?? [];
            $attributes = array_replace($original['attributes'] ?? [], $data['attributes'] ?? []);
            if (! is_array($attributes)) {
                throw new InvalidArgumentException('Block attributes must be an array.');
            }
            foreach ($this->widgetFields($block['type'], $widget) as $name) {
                if (isset($data['attributes'][$name])) {
                    $attributes[$name] = $this->fromBuilder($attributes[$name], true);
                }
            }
            if (isset($original['key'])) {
                foreach ($attributes as $name => $value) {
                    if (! array_key_exists($name, $original['attributes'] ?? []) && ($value === null || $value === [])) {
                        unset($attributes[$name]);
                    }
                }
            }
            foreach ($this->definitions->fields($block['type'], $widget) as $field) {
                $name = $field['name'];
                $previous = $original['attributes'][$name] ?? null;
                $current = $attributes[$name] ?? null;
                if ((is_string($previous) || is_int($previous) || is_float($previous))
                    && (is_string($current) || is_int($current) || is_float($current))
                    && ((string) $previous === (string) $current
                        || ($field['resolver'] === 'number' && is_numeric($previous) && is_numeric($current) && $previous == $current))) {
                    $attributes[$name] = $previous;
                }
                if (in_array($field['resolver'], ['cascade', 'opening_hours', 'http'], true) && is_string($previous) && array_key_exists($name, $attributes)) {
                    $decoded = $previous === '' ? [] : json_decode($previous, true, flags: JSON_THROW_ON_ERROR);
                    $attributes[$name] = $current === $decoded ? $previous : json_encode($current, JSON_THROW_ON_ERROR);
                }
                if ($field['resolver'] === 'entity' && array_key_exists($name, $attributes)) {
                    $decoded = is_string($previous) ? ($previous === '' ? null : json_decode($previous, true, flags: JSON_THROW_ON_ERROR)) : $previous;
                    $oldList = is_array($decoded) && array_is_list($decoded);
                    $items = is_array($current) && array_is_list($current) ? $current : ($current === null ? [] : [$current]);
                    $oldItems = $oldList ? $decoded : ($decoded === null ? [] : [$decoded]);
                    foreach ($items as &$reference) {
                        foreach ($oldItems as $old) {
                            if (($old['model'] ?? null) === ($reference['model'] ?? null) && (string) ($old['id'] ?? '') === (string) ($reference['id'] ?? '')) {
                                $reference = $old;
                                break;
                            }
                        }
                    }
                    unset($reference);
                    $value = ($oldList || ($field['options']['is_multiple'] ?? false)) ? $items : ($items[0] ?? null);
                    $attributes[$name] = is_string($previous) ? ($value === $decoded ? $previous : json_encode($value, JSON_THROW_ON_ERROR)) : $value;
                }
                if ($field['resolver'] === 'rich_text' && is_string($previous) && is_string($current)) {
                    $editor = RichContentRenderer::make()->getEditor();
                    if ($editor->setContent($previous)->getJSON() === $editor->setContent($current)->getJSON()) {
                        $attributes[$name] = $previous;
                    }
                }
                if ($field['resolver'] === 'table' && array_key_exists($name, $attributes)) {
                    $decoded = is_string($previous) ? ($previous === '' ? null : json_decode($previous, true, flags: JSON_THROW_ON_ERROR)) : $previous;
                    $normalized = TablePayloadNormalizer::normalize($decoded, $field['options'] ?? []);
                    $attributes[$name] = $current === $normalized ? $previous : (is_string($previous) ? json_encode($current, JSON_THROW_ON_ERROR) : $current);
                }
                if ($field['resolver'] !== 'file') {
                    continue;
                }
                $name = $field['name'];
                if (! array_key_exists($name, $attributes)) {
                    continue;
                }
                $previous = $original['attributes'][$name] ?? null;
                $current = $attributes[$name] ?? null;
                $multiple = (bool) ($field['options']['is_multiple'] ?? false);
                $oldItems = $multiple ? ($previous ?? []) : [$previous];
                $newItems = $multiple ? ($current ?? []) : [$current];
                foreach ($newItems as &$asset) {
                    foreach ($oldItems as $old) {
                        if (is_array($old) && is_array($asset) && isset($old['path'], $asset['path'])
                            && $old['path'] === $asset['path'] && ($old['disk'] ?? null) === ($asset['disk'] ?? null)) {
                            $asset = $old;
                            break;
                        }
                    }
                }
                unset($asset);
                $attributes[$name] = $multiple ? $newItems : ($newItems[0] ?? null);
            }
            $original['layout'] = $block['type'];
            $original['key'] ??= (string) Str::uuid();
            if (array_key_exists('internalTitle', $original) || isset($data['internalTitle'])) {
                $original['internalTitle'] = $data['internalTitle'];
            }
            $original['attributes'] = $attributes;
            $content[] = $original;
        }
        $seen = [];
        $this->validate($content, $widget, $seen);

        return $content;
    }

    public function duplicate(array $state, bool $widget = false): array
    {
        foreach ($state as &$block) {
            $block['data']['envelope']['key'] = (string) Str::uuid();
            foreach ($this->widgetFields($block['type'], $widget) as $name) {
                if (isset($block['data']['attributes'][$name])) {
                    $block['data']['attributes'][$name] = $this->duplicate($block['data']['attributes'][$name], true);
                }
            }
        }

        return $state;
    }

    private function hydrate(array $content, bool $widget): array
    {
        $state = [];
        foreach ($content as $block) {
            $attributes = $block['attributes'];
            foreach ($this->widgetFields($block['layout'], $widget) as $name) {
                if (isset($attributes[$name])) {
                    $attributes[$name] = $this->hydrate($attributes[$name], true);
                }
            }
            $data = ['envelope' => $block, 'attributes' => $attributes];
            if (array_key_exists('internalTitle', $block)) {
                $data['internalTitle'] = $block['internalTitle'];
            }
            $state[] = ['type' => $block['layout'], 'data' => $data];
        }

        return $state;
    }

    private function validate(array $content, bool $widget, array &$seen): void
    {
        if (! array_is_list($content)) {
            throw new InvalidArgumentException('Content must be a list of blocks.');
        }
        foreach ($content as $block) {
            if (! is_array($block) || ! is_string($block['layout'] ?? null) || $block['layout'] === ''
                || ! is_string($block['key'] ?? null) || $block['key'] === '' || ! is_array($block['attributes'] ?? null)) {
                throw new InvalidArgumentException('Invalid persisted content block.');
            }
            if (isset($seen[$block['key']])) {
                throw new InvalidArgumentException('Duplicate persistent content key.');
            }
            $seen[$block['key']] = true;
            foreach ($this->widgetFields($block['layout'], $widget) as $name) {
                if (isset($block['attributes'][$name])) {
                    if (! is_array($block['attributes'][$name])) {
                        throw new InvalidArgumentException('Widget content must be a list of blocks.');
                    }
                    $this->validate($block['attributes'][$name], true, $seen);
                }
            }
        }
    }

    private function widgetFields(string $layout, bool $widget): array
    {
        return array_column(array_filter($this->definitions->fields($layout, $widget),
            fn (array $field): bool => $field['resolver'] === 'widget'), 'name');
    }
}
