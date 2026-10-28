<?php

namespace Fayne\PageBuilder\Filament\Forms\Components;

use Fayne\CMS\Models\Admin;
use Fayne\CMS\Support\Authorization\Authorization;
use Filament\Forms\Components\FileUpload;
use InvalidArgumentException;
use Livewire\Features\SupportFileUploads\TemporaryUploadedFile;

class ContentFileUpload extends FileUpload
{
    public function saveUploadedFile(TemporaryUploadedFile $file): ?string
    {
        $admin = auth(config('filament-cms.guard', 'admin'))->user();
        abort_unless($admin instanceof Admin && Authorization::allows($admin, 'tenant.uploadMedia'), 403);

        return parent::saveUploadedFile($file);
    }

    public function hydrateFiles(): void
    {
        $value = $this->getRawState();
        if (is_array($value) && isset($value['path'])) {
            $value = [$value];
        }
        if (is_array($value)) {
            $value = array_map(function ($item) {
                if (! is_array($item)) {
                    return $item;
                }
                if (($item['disk'] ?? $this->getDiskName()) !== $this->getDiskName()) {
                    throw new InvalidArgumentException(__('filament-page-builder::errors.stored_media_disk_mismatch'));
                }

                return $item['path'] ?? throw new InvalidArgumentException(__('filament-page-builder::errors.stored_media_path_missing'));
            }, $value);
        }
        $this->rawState($value);
        // Missing remote files must not disappear from saved content when opening a form.
        $this->fetchFileInformation(false);
        parent::hydrateFiles();
    }
}
