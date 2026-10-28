<?php

namespace Fayne\PageBuilder\Support;

use Fayne\AdvancedText\Support\AdvancedTextValue;
use Fayne\PageBuilder\Contracts\ContentDefinitionRepository;

final class SensitiveContent
{
    public function restore(array $content, array $original, ContentDefinitionRepository $definitions, bool $widget = false): array
    {
        $byKey = array_column($original, null, 'key');
        foreach ($content as &$block) {
            $previous = $byKey[$block['key']] ?? null;
            if (($previous['layout'] ?? null) !== $block['layout']) {
                $previous = null;
            }
            foreach ($definitions->fields($block['layout'], $widget) as $field) {
                $name = $field['name'];
                if ($field['resolver'] === 'sensitive') {
                    unset($block['attributes'][$name]);
                    if (array_key_exists($name, $previous['attributes'] ?? [])) {
                        $block['attributes'][$name] = $previous['attributes'][$name];
                    }
                } elseif ($field['resolver'] === 'text' && ($field['options']['readonly'] ?? false)) {
                    if (array_key_exists($name, $previous['attributes'] ?? [])) {
                        $block['attributes'][$name] = $previous['attributes'][$name];
                    } else {
                        unset($block['attributes'][$name]);
                        if (array_key_exists('default_value', $field['options'])) {
                            $block['attributes'][$name] = AdvancedTextValue::normalize($field['options']['default_value'], $field['options']);
                        }
                    }
                } elseif ($field['resolver'] === 'widget' && isset($block['attributes'][$name])) {
                    $block['attributes'][$name] = $this->restore($block['attributes'][$name], $previous['attributes'][$name] ?? [], $definitions, true);
                }
            }
        }

        return $content;
    }

    public function contains(array $content, ContentDefinitionRepository $definitions, bool $widget = false): bool
    {
        foreach ($content as $block) {
            foreach ($definitions->fields($block['layout'], $widget) as $field) {
                if ($field['resolver'] === 'sensitive' && array_key_exists($field['name'], $block['attributes'])) {
                    return true;
                }
                if ($field['resolver'] === 'widget' && $this->contains($block['attributes'][$field['name']] ?? [], $definitions, true)) {
                    return true;
                }
            }
        }

        return false;
    }
}
