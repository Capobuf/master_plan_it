<?php

namespace Tests\Feature\Console;

use Illuminate\Support\Facades\Artisan;
use Tests\TestCase;

final class TestResetGreenfieldCommandTest extends TestCase
{
    public function test_the_only_greenfield_reset_command_rejects_a_database_outside_the_exact_allowlist(): void
    {
        config()->set('app.env', 'testing');
        config()->set('database.default', 'sqlite');

        $status = Artisan::call('app:test-reset-greenfield');

        $this->assertNotSame(0, $status);
        $this->assertStringContainsString('mysql', strtolower(Artisan::output()));
    }
}
