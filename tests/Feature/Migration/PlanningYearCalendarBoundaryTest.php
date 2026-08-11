<?php

namespace Tests\Feature\Migration;

use App\Domain\MasterData\Data\CreatePlanningYearData;
use App\Models\PlanningYear;
use Illuminate\Foundation\Testing\DatabaseTransactions;
use Illuminate\Support\Facades\Schema;
use Illuminate\Validation\ValidationException;
use ReflectionClass;
use Tests\TestCase;

class PlanningYearCalendarBoundaryTest extends TestCase
{
    use DatabaseTransactions;

    public function test_calendar_identity_derives_immutable_january_first_and_december_thirty_first_boundaries(): void
    {
        $data = new CreatePlanningYearData(2026);

        $this->assertSame('2026-01-01', $data->startDate());
        $this->assertSame('2026-12-31', $data->endDate());
        $this->assertSame(['yearLabel'], array_map(
            static fn ($property): string => $property->getName(),
            (new ReflectionClass($data))->getProperties(),
        ));
        $this->assertFalse(Schema::hasColumn('planning_years', 'start_date'));
        $this->assertFalse(Schema::hasColumn('planning_years', 'end_date'));
        $this->assertFalse(property_exists(PlanningYear::class, 'start_date'));
        $this->assertFalse(property_exists(PlanningYear::class, 'end_date'));
    }

    public function test_import_input_rejects_non_calendar_boundaries_without_silent_normalization(): void
    {
        try {
            CreatePlanningYearData::fromImportedRow([
                'year_label' => 2026,
                'start_date' => '2026-02-01',
                'end_date' => '2026-12-31',
            ]);
            $this->fail('A non-calendar import boundary was silently normalized.');
        } catch (ValidationException $exception) {
            $this->assertArrayHasKey('start_date', $exception->errors());
        }

        $data = CreatePlanningYearData::fromImportedRow([
            'year_label' => 2026,
            'start_date' => '2026-01-01',
            'end_date' => '2026-12-31',
        ]);

        $this->assertSame('2026-01-01', $data->startDate());
        $this->assertSame('2026-12-31', $data->endDate());
    }
}
