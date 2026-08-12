<?php

namespace Tests\Accounting\Integration;

use Illuminate\Foundation\Testing\DatabaseTransactions;
use Illuminate\Support\Facades\DB;
use Tests\TestCase;

final class AnnualExpenseProjectionTest extends TestCase
{
    use DatabaseTransactions;

    public function test_mysql_projection_loader_is_available_for_the_canonical_annual_dataset(): void
    {
        $this->assertSame('mysql', DB::connection()->getDriverName(), 'This accounting contract must run on MySQL.');
        $this->assertTrue(class_exists('App\\Domain\\Reporting\\Queries\\EconomicDatasetQuery'));
        $this->assertTrue(class_exists('App\\Domain\\Economics\\Services\\EconomicEngine'));
    }
}
