<?php

namespace App\Filament\Resources\CostCenters;

use App\Domain\MasterData\Actions\DeactivateCostCenter;
use App\Domain\MasterData\Actions\DeleteCostCenter;
use App\Domain\MasterData\Actions\ReactivateCostCenter;
use App\Domain\MasterData\Queries\CostCenterTreeQuery;
use App\Domain\Tenancy\Data\TenantContext;
use App\Filament\Resources\Concerns\UsesTenantContextRoutes;
use App\Filament\Resources\CostCenters\Pages\CostCenterRevisionHistory;
use App\Filament\Resources\CostCenters\Pages\CreateCostCenter;
use App\Filament\Resources\CostCenters\Pages\EditCostCenter;
use App\Filament\Resources\CostCenters\Pages\ListCostCenters;
use App\Models\CostCenter;
use App\Models\Expense;
use App\Models\User;
use App\Policies\CostCenterPolicy;
use App\Support\Diagnostics\CorrelationId;
use Filament\Actions\Action;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\TextInput;
use Filament\Resources\Pages\PageRegistration;
use Filament\Resources\Resource;
use Filament\Schemas\Schema;
use Filament\Tables\Columns\IconColumn;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Table;
use Illuminate\Auth\Access\AuthorizationException;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Database\Eloquent\Model;

final class CostCenterResource extends Resource
{
    use UsesTenantContextRoutes;

    protected static ?string $model = CostCenter::class;

    protected static ?string $recordTitleAttribute = 'name';

    protected static ?string $navigationLabel = 'Cost centers';

    public static function form(Schema $schema): Schema
    {
        return $schema->components([
            TextInput::make('name')->required()->maxLength(255),
            Select::make('parent_id')
                ->label('Parent cost center')
                ->options(fn (): array => self::parentOptions())
                ->searchable()
                ->nullable(),
        ]);
    }

    public static function table(Table $table): Table
    {
        return $table
            ->columns([
                TextColumn::make('name')->sortable(),
                TextColumn::make('parent_name')->label('Parent')->state(fn (CostCenter $record): ?string => $record->parent?->name),
                IconColumn::make('active')->boolean(),
            ])
            ->recordActions([
                Action::make('edit')
                    ->url(fn (CostCenter $record): string => self::getUrl('edit', ['record' => $record]))
                    ->visible(fn (CostCenter $record): bool => self::canEdit($record)),
                Action::make('deactivate')
                    ->color('warning')
                    ->requiresConfirmation()
                    ->visible(fn (CostCenter $record): bool => $record->active)
                    ->authorize(fn (CostCenter $record): bool => self::canDeactivate($record))
                    ->action(function (CostCenter $record): void {
                        app(DeactivateCostCenter::class)->execute(
                            self::authenticatedActor(),
                            self::tenantContext(),
                            $record,
                            $record->lock_version,
                            app(CorrelationId::class)->value(),
                        );
                    }),
                Action::make('reactivate')
                    ->color('success')
                    ->requiresConfirmation()
                    ->visible(fn (CostCenter $record): bool => ! $record->active)
                    ->authorize(fn (CostCenter $record): bool => self::canReactivate($record))
                    ->action(function (CostCenter $record): void {
                        app(ReactivateCostCenter::class)->execute(
                            self::authenticatedActor(),
                            self::tenantContext(),
                            $record,
                            $record->lock_version,
                            app(CorrelationId::class)->value(),
                        );
                    }),
                Action::make('delete')
                    ->color('danger')
                    ->requiresConfirmation()
                    ->visible(fn (CostCenter $record): bool => self::canDelete($record))
                    ->action(function (CostCenter $record): void {
                        app(DeleteCostCenter::class)->execute(
                            self::authenticatedActor(),
                            self::tenantContext(),
                            $record,
                            $record->lock_version,
                            app(CorrelationId::class)->value(),
                        );
                    }),
                Action::make('history')
                    ->url(fn (CostCenter $record): string => self::getUrl('history', ['record' => $record]))
                    ->visible(fn (CostCenter $record): bool => self::canViewRevisions($record)),
            ]);
    }

    /** @return Builder<CostCenter> */
    public static function getEloquentQuery(): Builder
    {
        return app(CostCenterTreeQuery::class)->builderForTenant(self::authenticatedActor(), self::tenantContext())
            ->with('parent');
    }

    public static function canViewAny(): bool
    {
        try {
            return app(CostCenterPolicy::class)->viewAny(self::authenticatedActor())->allowed();
        } catch (AuthorizationException) {
            return false;
        }
    }

    public static function canCreate(): bool
    {
        try {
            return app(CostCenterPolicy::class)->create(self::authenticatedActor())->allowed();
        } catch (AuthorizationException) {
            return false;
        }
    }

    public static function canView(Model $record): bool
    {
        return $record instanceof CostCenter
            && app(CostCenterPolicy::class)->view(self::authenticatedActor(), $record)->allowed();
    }

    public static function canEdit(Model $record): bool
    {
        return $record instanceof CostCenter
            && app(CostCenterPolicy::class)->{'update'}(self::authenticatedActor(), $record)->allowed();
    }

    public static function canDeactivate(CostCenter $record): bool
    {
        return app(CostCenterPolicy::class)->deactivate(self::authenticatedActor(), $record)->allowed();
    }

    public static function canReactivate(CostCenter $record): bool
    {
        return app(CostCenterPolicy::class)->reactivate(self::authenticatedActor(), $record)->allowed();
    }

    public static function canDelete(Model $record): bool
    {
        if (! $record instanceof CostCenter) {
            return false;
        }

        return app(CostCenterPolicy::class)->{'delete'}(self::authenticatedActor(), $record)->allowed()
            && ! CostCenter::query()->where('parent_id', $record->getKey())->exists()
            && ! Expense::withTrashed()
                ->where('tenant_id', self::tenantContext()->tenantId)
                ->where('cost_center_id', $record->getKey())
                ->exists();
    }

    public static function canViewRevisions(CostCenter $record): bool
    {
        return app(CostCenterPolicy::class)->viewRevisions(self::authenticatedActor(), $record)->allowed();
    }

    public static function canRestoreRevision(CostCenter $record): bool
    {
        return app(CostCenterPolicy::class)->restoreRevision(self::authenticatedActor(), $record)->allowed();
    }

    /** @return array<string, PageRegistration> */
    public static function getPages(): array
    {
        return [
            'index' => ListCostCenters::route('/'),
            'create' => CreateCostCenter::route('/create'),
            'edit' => EditCostCenter::route('/{record}/edit'),
            'history' => CostCenterRevisionHistory::route('/{record}/history'),
        ];
    }

    public static function authenticatedActor(): User
    {
        $actor = auth()->user();
        if (! $actor instanceof User) {
            throw new AuthorizationException('PERMISSION_DENIED');
        }

        return $actor;
    }

    public static function tenantContext(): TenantContext
    {
        return app(TenantContext::class);
    }

    /** @return array<int, string> */
    private static function parentOptions(): array
    {
        $options = [];

        foreach (app(CostCenterTreeQuery::class)->forTenant(self::authenticatedActor(), self::tenantContext()) as $costCenter) {
            foreach (self::flattenForOptions($costCenter) as $option) {
                if ($option->active) {
                    $options[(int) $option->getKey()] = $option->name;
                }
            }
        }

        return $options;
    }

    /** @return list<CostCenter> */
    private static function flattenForOptions(CostCenter $costCenter): array
    {
        $children = $costCenter->getRelation('children');
        $flattened = [$costCenter];

        if (! $children instanceof Collection) {
            return $flattened;
        }

        foreach ($children as $child) {
            if ($child instanceof CostCenter) {
                array_push($flattened, ...self::flattenForOptions($child));
            }
        }

        return $flattened;
    }
}
