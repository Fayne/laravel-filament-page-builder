<?php

namespace Fayne\PageBuilder\Support;

use Fayne\CMS\Support\ContentLocale;
use Fayne\EntityReference\Forms\Components\EntityReferenceSelect;
use Fayne\PageBuilder\Filament\Forms\Components\ContentBuilder;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\Context;
use Illuminate\Support\Facades\Gate;

final class EntityReferenceAdapter
{
    public function configure(EntityReferenceSelect $field, ContentBuilder $builder): void
    {
        $root = $builder->rootContentBuilder();
        $field->queryUsing(function (string $modelClass, string $alias, bool $forSelection) use ($root): ?Builder {
            $definition = config('filament-page-builder.entities.'.$alias);
            $admin = auth(config('filament-cms.guard', 'admin'))->user();
            if (! is_array($definition) || ($definition['model'] ?? null) !== $modelClass || ! $admin?->is_active || ! Gate::forUser($admin)->allows('viewAny', $modelClass)) {
                return null;
            }

            $source = $root->getRecord();
            $model = Context::scope(fn () => new $modelClass, ['filament-cms.region_id' => '']);
            if ($source instanceof Model) {
                $model->setConnection($source->getConnectionName());
            }
            $query = $model->newQuery();
            if ($forSelection && method_exists($model, 'usesTranslationGroup') && $model->usesTranslationGroup()) {
                $locale = $source?->locale ?? ($root->getLivewire()->data['locale'] ?? ContentLocale::default());
                $query->locale($locale);
            }

            return $query;
        })->authorizeUsing(function (Model $record): bool {
            $admin = auth(config('filament-cms.guard', 'admin'))->user();

            return $admin?->is_active && Gate::forUser($admin)->allows('view', $record);
        });
    }
}
