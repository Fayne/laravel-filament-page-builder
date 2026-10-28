<?php

namespace Fayne\PageBuilder\Support;

use Fayne\PageBuilder\Contracts\ContentDefinitionRepository;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Str;
use InvalidArgumentException;

final class EloquentContentDefinitions implements ContentDefinitionRepository
{
    /** @param class-string<Model> $componentModel @param class-string<Model> $widgetModel */
    public function __construct(private readonly string $componentModel, private readonly string $widgetModel, private readonly ?string $connection = null) {}

    public function layouts(bool $widget = false): array
    {
        return $this->query($widget)->orderBy('id')->pluck('name', 'slug')->all();
    }

    public function fields(string $layout, bool $widget = false): array
    {
        $element = $this->query($widget)->where('slug', $layout)->first();
        if (! $element) {
            return [];
        }

        return $element->fields()->get()->map(function (Model $field): array {
            $options = $field->options ?? [];
            if ($field->resolver === 'widget' && isset($options['widgets'])) {
                $ids = $options['widgets'];
                if (! is_array($ids)) {
                    $ids = json_decode($ids, true, flags: JSON_THROW_ON_ERROR);
                }
                $widgets = $this->query(true)->whereIn('id', $ids)->pluck('slug', 'id')->all();
                $options['widgets'] = array_map(fn ($id): string => $widgets[$id]
                    ?? throw new InvalidArgumentException(__('filament-page-builder::errors.missing_widget_id', ['id' => $id])), $ids);
            }

            return [
                'name' => $field->slug ?: str_replace(' ', '_', Str::lower($field->title)),
                'title' => $field->title,
                'resolver' => $field->resolver,
                'options' => $options,
                'rules' => $field->rules ?? [],
            ];
        })->all();
    }

    private function query(bool $widget): Builder
    {
        $class = $widget ? $this->widgetModel : $this->componentModel;
        $model = new $class;
        if ($this->connection !== null) {
            $model->setConnection($this->connection);
        }

        return $model->newQuery();
    }
}
