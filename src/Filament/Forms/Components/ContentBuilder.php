<?php

namespace Fayne\PageBuilder\Filament\Forms\Components;

use Fayne\EntityReference\Forms\Components\EntityReferenceSelect;
use Fayne\PageBuilder\Contracts\ContentDefinitionRepository;
use Fayne\PageBuilder\Support\ContentStateAdapter;
use Fayne\PageBuilder\Support\EntityReferenceAdapter;
use Fayne\PageBuilder\Support\FieldResolverContext;
use Fayne\PageBuilder\Support\FieldSchemaFactory;
use Fayne\PageBuilder\Support\SensitiveContent;
use Filament\Actions\Action;
use Filament\Forms\Components\Builder;
use Filament\Forms\Components\Builder\Block;
use Filament\Forms\Components\Hidden;
use Filament\Forms\Components\TextInput;
use Filament\Schemas\Components\Group;
use Filament\Schemas\Components\Text;
use Illuminate\Validation\ValidationException;
use InvalidArgumentException;

class ContentBuilder extends Builder
{
    protected ?ContentDefinitionRepository $contentDefinitions = null;

    protected bool $nestedContentState = false;

    protected bool $widgetContent = false;

    protected array $contentLayouts = [];

    protected array $contentWidgets = [];

    protected array $definitionPath = [];

    protected array $unsupportedLayouts = [];

    public function definitions(ContentDefinitionRepository $definitions, array $layouts = [], array $widgets = []): static
    {
        $this->contentDefinitions = $definitions;
        $this->contentLayouts = $layouts;
        $this->contentWidgets = $widgets;
        $this->blocks(fn (ContentBuilder $component) => $component->buildBlocks($component->contentLayouts));
        $this->collapsed();
        $this->collapseAllAction(fn (Action $action): Action => $action->label(__('filament-page-builder::fields.collapse_all')));
        $this->expandAllAction(fn (Action $action): Action => $action->label(__('filament-page-builder::fields.expand_all')));
        $this->cloneable();
        $this->cloneAction(fn (Action $action): Action => $action->action(function (array $arguments, ContentBuilder $component): void {
            $items = $component->getRawState() ?? [];
            if (! isset($items[$arguments['item']])) {
                return;
            }
            $copy = (new ContentStateAdapter($component->contentDefinitions))->duplicate([$items[$arguments['item']]], $component->widgetContent)[0];
            $items[$component->generateUuid()] = $copy;
            $component->rawState($items);
            $component->callAfterStateUpdated();
        }));
        $this->mutateDehydratedStateUsing(function (?array $state, ContentBuilder $component): array {
            $component->assertKnownBlocks($state ?? []);

            if ($component->nestedContentState) {
                return array_values($state ?? []);
            }
            $content = (new ContentStateAdapter($component->contentDefinitions))->fromBuilder($state ?? [], $component->widgetContent);
            $record = $component->getRecord();
            $original = $record?->exists ? ($record->newQuery()->findOrFail($record->getKey())->getAttribute($component->getName()) ?? []) : [];

            return app(SensitiveContent::class)->restore($content, $original, $component->contentDefinitions, $component->widgetContent);
        });

        return $this;
    }

    public function hydrateState(?array &$hydratedDefaultState, bool $shouldCallHydrationHooks = true, bool $shouldApplyStateCasts = true, array &$appliedStateCastPaths = []): void
    {
        if (! $this->nestedContentState && $this->contentDefinitions) {
            $this->rawState((new ContentStateAdapter($this->contentDefinitions))->toBuilder($this->redactUnsupportedContent($this->getRawState() ?? [], $this->widgetContent), $this->widgetContent));
        }
        parent::hydrateState($hydratedDefaultState, $shouldCallHydrationHooks, $shouldApplyStateCasts, $appliedStateCastPaths);
    }

    protected function redactUnsupportedContent(array $content, bool $widget): array
    {
        foreach ($content as &$block) {
            $layouts = $widget ? $this->contentWidgets : $this->contentLayouts;
            if (! isset($layouts[$block['layout']])) {
                $block['attributes'] = [];

                continue;
            }
            foreach ($this->contentDefinitions->fields($block['layout'], $widget) as $definition) {
                $name = $definition['name'];
                if ($definition['resolver'] === 'sensitive') {
                    unset($block['attributes'][$name]);

                    continue;
                }
                if ($definition['resolver'] === 'widget') {
                    if (isset($block['attributes'][$name]) && is_array($block['attributes'][$name])) {
                        $block['attributes'][$name] = $this->redactUnsupportedContent($block['attributes'][$name], true);
                    }

                    continue;
                }
                try {
                    (new FieldSchemaFactory)->make($definition);
                } catch (InvalidArgumentException) {
                    unset($block['attributes'][$name]);
                }
            }
        }

        return $content;
    }

    protected function buildBlocks(array $layouts): array
    {
        $blocks = [];
        $this->unsupportedLayouts = [];
        foreach ($layouts as $slug => $label) {
            if (in_array(($this->widgetContent ? 'widget:' : 'component:').$slug, $this->definitionPath, true)) {
                throw new InvalidArgumentException(__('filament-page-builder::errors.recursive_widget', ['slug' => $slug]));
            }
            $fields = [];
            foreach ($this->contentDefinitions->fields($slug, $this->widgetContent) as $definition) {
                if ($definition['resolver'] !== 'widget') {
                    try {
                        $field = (new FieldSchemaFactory)->make($definition);
                        if ($field instanceof ContentRichEditor) {
                            $field->contentContext($this);
                        }
                        if ($field instanceof EntityReferenceSelect) {
                            (new EntityReferenceAdapter)->configure($field, $this);
                        }
                        $fields[] = $field;
                    } catch (InvalidArgumentException $exception) {
                        $this->unsupportedLayouts[$slug] = true;
                        $fields[] = Text::make($exception->getMessage());
                    }

                    continue;
                }
                $fields[] = (new FieldSchemaFactory)->make($definition, new FieldResolverContext(
                    $this->contentDefinitions,
                    $this->contentWidgets,
                    [...$this->definitionPath, ($this->widgetContent ? 'widget:' : 'component:').$slug],
                    static::class,
                ));
            }
            $blocks[] = Block::make($slug)->label(fn (?array $state): string => filled($state['internalTitle'] ?? null) ? $state['internalTitle'].' ['.$label.']' : $label)->schema([
                Hidden::make('envelope'),
                TextInput::make('internalTitle')->label(__('filament-page-builder::fields.internal_title'))->live(onBlur: true),
                Group::make($fields)->statePath('attributes'),
            ]);
        }

        foreach ($this->getRawState() ?? [] as $item) {
            $type = $item['type'] ?? null;
            if (! is_string($type) || isset($layouts[$type])) {
                continue;
            }
            $blocks[] = Block::make($type)->label(__('filament-page-builder::fields.missing_definition', ['type' => $type]))->schema([
                Hidden::make('envelope'),
                Text::make(__('filament-page-builder::fields.restore_definition')),
            ]);
        }

        return $blocks;
    }

    public function nestedWidgetContext(array $definitionPath): static
    {
        $this->nestedContentState = true;
        $this->widgetContent = true;
        $this->definitionPath = $definitionPath;

        return $this;
    }

    public function getContentDefinitions(): ContentDefinitionRepository
    {
        return $this->contentDefinitions;
    }

    public function rootContentBuilder(): self
    {
        $root = $this;
        $container = $this->getContainer();
        while ($parent = $container->getParentComponent()) {
            if ($parent instanceof self) {
                $root = $parent;
            }
            $container = $parent->getContainer();
        }

        return $root;
    }

    protected function assertKnownBlocks(array $state): void
    {
        $this->buildBlocks($this->contentLayouts);
        foreach ($state as $item) {
            if (isset($this->unsupportedLayouts[$item['type'] ?? ''])) {
                throw ValidationException::withMessages([$this->getStatePath() => __('filament-page-builder::ui.unsupported_field_settings')]);
            }
            if (! isset($this->contentLayouts[$item['type'] ?? ''])) {
                throw ValidationException::withMessages([$this->getStatePath() => __('filament-page-builder::ui.missing_block_definitions')]);
            }
        }
    }
}
