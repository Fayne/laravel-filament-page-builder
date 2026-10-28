<?php

namespace Fayne\PageBuilder\Filament\RelationManagers;

use Fayne\CMS\Support\ContentFieldTypes;
use Filament\Actions\CreateAction;
use Filament\Actions\DeleteAction;
use Filament\Actions\EditAction;
use Filament\Resources\RelationManagers\RelationManager;
use Filament\Schemas\Schema;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\Gate;
use Illuminate\Validation\ValidationException;

abstract class DefinitionFieldsRelationManager extends RelationManager
{
    protected static string $relationship = 'fields';

    protected static ?string $title = null;

    public static function getTitle(Model $ownerRecord, string $pageClass): string
    {
        return __('filament-page-builder::fields.management');
    }

    protected static bool $isLazy = false;

    public static function canViewForRecord(Model $ownerRecord, string $pageClass): bool
    {
        return Gate::allows('view', $ownerRecord) && parent::canViewForRecord($ownerRecord, $pageClass);
    }

    public function mount(): void
    {
        abort_unless(static::canViewForRecord($this->getOwnerRecord(), $this->getPageClass()), 403);
        parent::mount();
    }

    public function form(Schema $schema): Schema
    {
        $resource = static::$relatedResource;

        return $schema->components($resource::fieldFormComponents());
    }

    public function table(Table $table): Table
    {
        $resource = static::$relatedResource;

        return $table->recordTitleAttribute('title')->columns([
            TextColumn::make('title')->label(__('filament-page-builder::fields.name'))->searchable(),
            TextColumn::make('slug')->label(__('filament-page-builder::fields.key')),
            TextColumn::make('resolver')->label(__('filament-page-builder::fields.type'))->formatStateUsing(fn ($state, Model $record) => ContentFieldTypes::label($state, $record->options)),
            TextColumn::make('weight')->label(__('filament-page-builder::fields.sort'))->sortable(),
        ])->defaultSort('weight')->paginated(false)
            ->reorderable('weight', fn (): bool => $this->canReorder())
            ->headerActions([
                CreateAction::make()->label(__('filament-page-builder::fields.create'))
                    ->url(fn (): string => $resource::getUrl('create', [$this->getRelationship()->getForeignKeyName() => $this->getOwnerRecord()->getKey()]))
                    ->authorize(fn (): bool => Gate::allows('update', $this->getOwnerRecord()) && Gate::allows('create', $resource::getModel())),
            ])->recordActions([
                EditAction::make()->authorize(fn (Model $record): bool => Gate::allows('update', $this->getOwnerRecord()) && Gate::allows('update', $record)),
                DeleteAction::make()->authorize(fn (Model $record): bool => Gate::allows('update', $this->getOwnerRecord()) && Gate::allows('delete', $record)),
            ])->emptyStateHeading(__('filament-page-builder::ui.empty_fields_heading'))->emptyStateDescription(__('filament-page-builder::ui.empty_fields_description'));
    }

    protected function canReorder(): bool
    {
        return ! $this->isReadOnly() && Gate::allows('update', $this->getOwnerRecord())
            && $this->getOwnerRecord()->fields()->get()->every(fn (Model $field): bool => Gate::allows('update', $field));
    }

    public function reorderTable(array $order, int|string|null $draggedRecordKey = null): void
    {
        Gate::authorize('update', $this->getOwnerRecord());
        $this->getOwnerRecord()->getConnection()->transaction(function () use ($order): void {
            $fields = $this->getOwnerRecord()->fields()->lockForUpdate()->get();
            foreach ($fields as $field) {
                Gate::authorize('update', $field);
            }
            $ids = $fields->map(fn (Model $field): string => (string) $field->getKey())->sort(SORT_STRING)->values()->all();
            $submitted = array_map(fn ($id): string => is_int($id) || is_string($id) ? (string) $id : '', $order);
            $sorted = $submitted;
            sort($sorted, SORT_STRING);
            if (! array_is_list($order) || $sorted !== $ids) {
                throw ValidationException::withMessages(['table' => __('filament-page-builder::fields.invalid_order')]);
            }
            $positions = array_flip($submitted);
            foreach ($fields as $field) {
                $field->update(['weight' => $positions[(string) $field->getKey()] + 1]);
            }
        });
        $this->flushCachedTableRecords();
    }
}
