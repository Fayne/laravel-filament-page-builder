<?php

namespace Fayne\PageBuilder\Filament\Forms\Components;

use Fayne\CMS\Models\Admin;
use Fayne\CMS\Support\Authorization\Authorization;
use Filament\Forms\Components\RichEditor;
use Illuminate\Support\Facades\Validator;
use Livewire\Features\SupportFileUploads\TemporaryUploadedFile;

class ContentRichEditor extends RichEditor
{
    protected ?ContentBuilder $contentBuilder = null;

    public function contentContext(ContentBuilder $builder): static
    {
        $this->contentBuilder = $builder;

        return $this;
    }

    public function saveUploadedFileAttachment(TemporaryUploadedFile $file): mixed
    {
        $guard = config('filament-cms.guard', 'admin');
        $admin = auth($guard)->user();
        abort_unless($admin instanceof Admin && Authorization::allows($admin, 'tenant.uploadMedia'), 403);
        Validator::validate(['file' => $file], ['file' => ['required', 'image', 'mimetypes:image/jpeg,image/png,image/gif,image/webp', 'max:'.$this->getFileAttachmentsMaxSize()]]);

        return parent::saveUploadedFileAttachment($file);
    }

    public function getOriginalFileAttachmentPaths(): array
    {
        if (! $this->contentBuilder) {
            return parent::getOriginalFileAttachmentPaths();
        }
        $root = $this->contentBuilder->rootContentBuilder();
        $record = $root->getRecord();
        if (! $record?->exists) {
            return [];
        }
        $ids = [];
        $visit = function (array $content, bool $widget = false) use (&$visit, $root, &$ids): void {
            foreach ($content as $block) {
                foreach ($root->getContentDefinitions()->fields($block['layout'], $widget) as $definition) {
                    $value = $block['attributes'][$definition['name']] ?? null;
                    if ($definition['resolver'] === 'rich_text' && is_string($value) && $value !== '') {
                        $this->getTipTapEditor()->setContent($value)->descendants(function (object $node) use (&$ids): void {
                            if ($node->type === 'image' && filled($node->attrs->id ?? null)) {
                                $ids[] = $node->attrs->id;
                            }
                        });
                    } elseif ($definition['resolver'] === 'widget' && is_array($value)) {
                        $visit($value, true);
                    }
                }
            }
        };
        $visit($record->getOriginal($root->getName()) ?? []);

        return $ids;
    }

    public function filamentOptions(array $options): static
    {
        $buttons = $options['toolbar'] ?? ['bold', 'italic', 'underline', 'strike', 'h2', 'h3', 'bulletList', 'orderedList', 'blockquote', 'link', 'attachFiles', 'undo', 'redo'];
        if (is_string($buttons)) {
            $buttons = json_decode($buttons, true, flags: JSON_THROW_ON_ERROR);
        }
        if (! is_array($buttons)) {
            throw new \InvalidArgumentException(__('filament-page-builder::errors.rich_toolbar_array'));
        }
        $mapped = [];
        foreach ($buttons as $button) {
            if (! in_array($button, ['attachFiles', 'bold', 'italic', 'underline', 'strike', 'h1', 'h2', 'h3', 'h4', 'h5', 'h6', 'bulletList', 'orderedList', 'blockquote', 'link', 'undo', 'redo', 'code', 'codeBlock', 'horizontalRule', 'table', 'textColor', 'alignStart', 'alignCenter', 'alignEnd', 'subscript', 'superscript', 'clearFormatting'], true)) {
                throw new \InvalidArgumentException(__('filament-page-builder::errors.rich_tool_unsupported', ['tool' => is_string($button) ? $button : 'invalid']));
            }
            $mapped[] = $button;
        }
        $this->toolbarButtons($mapped)->fileAttachments(true)->preventFileAttachmentPathTampering()
            ->fileAttachmentsDisk(config('filament-cms.media.disk', 'public'))
            ->fileAttachmentsDirectory(config('filament-page-builder.rich_text.images.directory', 'rich-content'))
            ->fileAttachmentsVisibility('public')
            ->fileAttachmentsAcceptedFileTypes(['image/jpeg', 'image/png', 'image/gif', 'image/webp'])
            ->fileAttachmentsMaxSize(config('filament-page-builder.rich_text.images.max_size', 5120));
        if ($options['stacked'] ?? true) {
            $this->columnSpanFull();
        }
        if (array_key_exists('withLabel', $options)) {
            $this->hiddenLabel(! $options['withLabel']);
        }

        return $this;
    }
}
