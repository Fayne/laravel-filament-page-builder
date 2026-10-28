<?php

namespace Fayne\PageBuilder\Support;

use Fayne\CMS\Support\Media\MediaValueResolver;
use Fayne\PageBuilder\Contracts\ContentDefinitionRepository;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Arr;
use Illuminate\Support\Facades\Context;
use InvalidArgumentException;

final class ContentOutputResolver
{
    public function resolve(array $content, ContentDefinitionRepository $definitions, bool $widget = false, ?string $connection = null, ?string $locale = null): array
    {
        $output = [];
        foreach ($content as $block) {
            $stored = $block['attributes'];
            $attributes = [];
            foreach ($definitions->fields($block['layout'], $widget) as $field) {
                $name = $field['name'];
                if ($field['resolver'] === 'sensitive') {
                    continue;
                }
                if (! array_key_exists($name, $stored)) {
                    continue;
                }
                if ($stored[$name] === null) {
                    $attributes[$name] = null;

                    continue;
                }
                if ($field['resolver'] === 'http') {
                    $config = is_string($stored[$name]) ? json_decode($stored[$name], true, flags: JSON_THROW_ON_ERROR) : $stored[$name];
                    $resolved = app(ContentHttpRequest::class)->resolve($config, public: true, options: $field['options'] ?? []);
                    if (array_key_exists($resolved['target'], $stored) || array_key_exists($resolved['target'], $attributes)) {
                        throw new InvalidArgumentException(__('filament-page-builder::errors.http_output_conflict'));
                    }
                    $attributes[$resolved['target']] = $resolved['value'];

                    continue;
                }
                $attributes[$name] = match ($field['resolver']) {
                    'widget' => $this->resolve($stored[$name], $definitions, true, $connection, $locale),
                    'file' => $this->media($stored[$name], (bool) ($field['options']['is_multiple'] ?? false)),
                    'entity' => config('filament-page-builder.resolve_entities', false) ? $this->entity($stored[$name], $connection, $locale) : $stored[$name],
                    'text', 'textarea', 'email', 'number', 'boolean', 'select', 'multiselect', 'date', 'datetime', 'color', 'key_value', 'tag', 'json', 'rich_text', 'table', 'opening_hours', 'cascade' => $stored[$name],
                    default => throw new InvalidArgumentException(__('filament-page-builder::errors.unsupported_public_resolver', ['type' => $field['resolver']])),
                };
            }
            $output[] = ['layout' => $block['layout'], 'attributes' => $attributes];
        }

        return $output;
    }

    private function media(mixed $value, bool $multiple): mixed
    {
        $items = app(MediaValueResolver::class)->resolve($value, $multiple);

        return $multiple ? $items : ($items[0] ?? null);
    }

    private function entity(mixed $value, ?string $connection, ?string $locale): mixed
    {
        if (is_string($value)) {
            $value = json_decode($value, true, flags: JSON_THROW_ON_ERROR);
        }
        if (! is_array($value) || $value === []) {
            return $value;
        }
        if (array_is_list($value)) {
            return array_map(fn ($reference) => $this->entity($reference, $connection, $locale), $value);
        }
        $configured = config('filament-page-builder.entities', []);
        $reference = $value['model'] ?? '';
        $definition = $configured[$reference] ?? null;
        if (! is_array($definition) || ! is_subclass_of($definition['model'] ?? '', Model::class) || empty($definition['fields'])) {
            throw new InvalidArgumentException(__('filament-page-builder::errors.entity_projection_missing'));
        }

        $key = json_encode([$definition['model'], $connection, $value['id'] ?? null]);
        $stack = Context::get('filament-page-builder.entity_stack', []);
        if (in_array($key, $stack, true)) {
            return null;
        }
        $context = ['filament-page-builder.entity_stack' => [...$stack, $key]];
        if ($connection !== null) {
            $context['filament-cms.region_id'] = '';
        }

        return Context::scope(function () use ($definition, $value, $connection, $locale) {
            $model = new $definition['model'];
            if ($connection !== null) {
                $model->setConnection($connection);
            }
            $query = $model->newQuery();
            if (method_exists($model, 'scopePublished')) {
                $query->published();
            }
            $record = $query->find($value['id'] ?? null);
            if (! $record) {
                return null;
            }
            $projection = match (true) {
                $locale !== null && method_exists($record, 'publicProjectionForLocale') => $record->publicProjectionForLocale($locale),
                method_exists($record, 'publishedProjection') => $record->publishedProjection($definition['fields']),
                default => $record->only($definition['fields']),
            };

            return Arr::only($projection, $definition['fields']);
        }, $context);
    }
}
