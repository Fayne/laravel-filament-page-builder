<?php

namespace Fayne\PageBuilder\Filament\Forms\Components;

use Fayne\MediaPicker\Forms\Components\ManagedFilePicker;
use InvalidArgumentException;
use UniFileManager\Core\Contracts\StorageAreaResolver;

class ContentMediaPicker extends ManagedFilePicker
{
    protected string $contentDisk;

    public function contentDisk(string $disk): static
    {
        $this->contentDisk = $disk;

        return $this->storageArea('content-'.$disk);
    }

    public function getDiskName(): string
    {
        return $this->contentDisk;
    }

    protected function setUp(): void
    {
        parent::setUp();
        $this->afterStateHydrated(function (self $component, mixed $state): void {
            $component->state($component->pathsFromStoredState($state));
        });
        $this->mutateDehydratedStateUsing(function (self $component, mixed $state): mixed {
            // Keep UniFilePicker's server-side path, permission and MIME checks.
            $paths = $component->validateSelectionState($state);
            $area = app(StorageAreaResolver::class)->resolve($component->getStorageArea());
            $asset = fn (string $path): array => [
                'disk' => $component->getDiskName(),
                'path' => ltrim(($area['root'] ?? '').'/'.$path, '/'),
            ];

            return $component->isMultiple() ? array_map($asset, $paths) : ($paths === null ? null : $asset($paths));
        });
    }

    public function pathsFromStoredState(mixed $state): mixed
    {
        $path = function (mixed $asset): mixed {
            if (! is_array($asset)) {
                return $asset;
            }
            if (($asset['disk'] ?? $this->getDiskName()) !== $this->getDiskName()) {
                throw new InvalidArgumentException(__('filament-page-builder::errors.stored_media_disk_mismatch'));
            }

            return $asset['path'] ?? throw new InvalidArgumentException(__('filament-page-builder::errors.stored_media_path_missing'));
        };

        if ($this->isMultiple()) {
            return array_map($path, $state ?? []);
        }

        return $path($state);
    }
}
