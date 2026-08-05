<?php

namespace App\Filament\Resources\PlanningYears;

use App\Domain\MasterData\Actions\DeactivatePlanningYear;
use App\Domain\MasterData\Actions\ReactivatePlanningYear;
use App\Domain\MasterData\Queries\PlanningYearListQuery;
use App\Domain\Tenancy\Data\TenantContext;
use App\Filament\Resources\Concerns\UsesTenantContextRoutes;
use App\Filament\Resources\PlanningYears\Pages\CreatePlanningYear;
use App\Filament\Resources\PlanningYears\Pages\ListPlanningYears;
use App\Models\PlanningYear;
use App\Models\User;
use App\Policies\PlanningYearPolicy;
use App\Support\Diagnostics\CorrelationId;
use Carbon\CarbonImmutable;
use Filament\Actions\Action;
use Filament\Forms\Components\TextInput;
use Filament\Resources\Pages\PageRegistration;
use Filament\Resources\Resource;
use Filament\Schemas\Schema;
use Filament\Tables\Columns\IconColumn;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Table;
use Illuminate\Auth\Access\AuthorizationException;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;

final class PlanningYearResource extends Resource
{
    use UsesTenantContextRoutes;

    protected static ?string $model = PlanningYear::class;

    protected static ?string $recordTitleAttribute = 'year_label';

    protected static ?string $navigationLabel = 'Planning years';

    public static function form(Schema $schema): Schema
    {
        return $schema->components([
            TextInput::make('year_label')
                ->label('Calendar year')
                ->required()
                ->numeric()
                ->integer()
                ->minValue(1000)
                ->maxValue(9999),
        ]);
    }

    public static function table(Table $table): Table
    {
        return $table
            ->columns([
                TextColumn::make('year_label')->label('Calendar year')->sortable(),
                TextColumn::make('start_date')
                    ->label('Start date')
                    ->state(fn (PlanningYear $record): string => self::localizedBoundary($record->year_label, 1, 1)),
                TextColumn::make('end_date')
                    ->label('End date')
                    ->state(fn (PlanningYear $record): string => self::localizedBoundary($record->year_label, 12, 31)),
                IconColumn::make('active')->boolean(),
            ])
            ->recordActions([
                Action::make('deactivate')
                    ->label('Deactivate')
                    ->color('warning')
                    ->requiresConfirmation()
                    ->visible(fn (PlanningYear $record): bool => $record->active)
                    ->authorize(fn (PlanningYear $record): bool => self::canDeactivate($record))
                    ->action(function (PlanningYear $record): void {
                        app(DeactivatePlanningYear::class)->execute(
                            self::authenticatedActor(),
                            self::tenantContext(),
                            $record,
                            $record->lock_version,
                            app(CorrelationId::class)->value(),
                        );
                    }),
                Action::make('reactivate')
                    ->label('Reactivate')
                    ->color('success')
                    ->visible(fn (PlanningYear $record): bool => ! $record->active)
                    ->authorize(fn (PlanningYear $record): bool => self::canReactivate($record))
                    ->action(function (PlanningYear $record): void {
                        app(ReactivatePlanningYear::class)->execute(
                            self::authenticatedActor(),
                            self::tenantContext(),
                            $record,
                            $record->lock_version,
                            app(CorrelationId::class)->value(),
                        );
                    }),
            ]);
    }

    /** @return Builder<PlanningYear> */
    public static function getEloquentQuery(): Builder
    {
        return app(PlanningYearListQuery::class)->forTenant(
            self::authenticatedActor(),
            self::tenantContext(),
        );
    }

    public static function canViewAny(): bool
    {
        try {
            return app(PlanningYearPolicy::class)->viewAny(self::authenticatedActor())->allowed();
        } catch (AuthorizationException) {
            return false;
        }
    }

    public static function canCreate(): bool
    {
        try {
            return app(PlanningYearPolicy::class)->create(self::authenticatedActor())->allowed();
        } catch (AuthorizationException) {
            return false;
        }
    }

    public static function canView(Model $record): bool
    {
        return $record instanceof PlanningYear
            && app(PlanningYearPolicy::class)->view(self::authenticatedActor(), $record)->allowed();
    }

    public static function canDeactivate(PlanningYear $record): bool
    {
        return app(PlanningYearPolicy::class)->deactivate(self::authenticatedActor(), $record)->allowed();
    }

    public static function canReactivate(PlanningYear $record): bool
    {
        return app(PlanningYearPolicy::class)->reactivate(self::authenticatedActor(), $record)->allowed();
    }

    /** @return array<string, PageRegistration> */
    public static function getPages(): array
    {
        return [
            'index' => ListPlanningYears::route('/'),
            'create' => CreatePlanningYear::route('/create'),
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

    private static function localizedBoundary(int $year, int $month, int $day): string
    {
        return CarbonImmutable::create($year, $month, $day, 0, 0, 0, 'UTC')
            ->locale(app()->getLocale())
            ->isoFormat('L');
    }
}
