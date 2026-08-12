<?php

namespace Tests\Accounting\Integration;

use App\Domain\Budget\Services\AnnualEconomicMutationGuard;
use Illuminate\Database\ConnectionInterface;
use Illuminate\Database\QueryException;
use Illuminate\Support\Facades\DB;
use Tests\TestCase;

final class AnnualEconomicMutationConcurrencyTest extends TestCase
{
    public function test_same_year_writers_serialize_while_distinct_years_remain_independent(): void
    {
        $this->assertSame('mysql', DB::connection()->getDriverName(), 'This concurrency contract must run on MySQL.');

        $connections = $this->isolatedConnections('annual_guard');
        $first = $connections['first'];
        $second = $connections['second'];
        [$tenantId, $firstYearId, $secondYearId] = $this->createTenantAndYears($first);

        try {
            $first->beginTransaction();
            $this->onConnection($connections['first_name'], fn (): mixed => app(AnnualEconomicMutationGuard::class)
                ->acquire($tenantId, [$firstYearId]));

            $this->expectLockWaitTimeout($second, $connections['second_name'], fn (): mixed => app(AnnualEconomicMutationGuard::class)
                ->acquire($tenantId, [$firstYearId]));
            $first->commit();

            $first->beginTransaction();
            $this->onConnection($connections['first_name'], fn (): mixed => app(AnnualEconomicMutationGuard::class)
                ->acquire($tenantId, [$firstYearId]));
            $second->beginTransaction();
            $differentYear = $this->onConnection(
                $connections['second_name'],
                fn (): mixed => app(AnnualEconomicMutationGuard::class)->acquire($tenantId, [$secondYearId]),
            );
            $this->assertSame([$secondYearId], $differentYear->keys()->all());
            $second->rollBack();
            $first->rollBack();

            $second->beginTransaction();
            $afterRelease = $this->onConnection(
                $connections['second_name'],
                fn (): mixed => app(AnnualEconomicMutationGuard::class)->acquire($tenantId, [$firstYearId]),
            );
            $this->assertSame([$firstYearId], $afterRelease->keys()->all());
            $second->rollBack();
        } finally {
            $this->cleanupConnections($connections, $tenantId);
        }
    }

    public function test_tenant_scoped_basis_lock_serializes_every_existing_year(): void
    {
        $this->assertSame('mysql', DB::connection()->getDriverName(), 'This concurrency contract must run on MySQL.');

        $connections = $this->isolatedConnections('annual_basis_guard');
        $first = $connections['first'];
        $second = $connections['second'];
        [$tenantId, $firstYearId, $secondYearId] = $this->createTenantAndYears($first);

        try {
            $first->beginTransaction();
            $locked = $this->onConnection(
                $connections['first_name'],
                fn (): mixed => app(AnnualEconomicMutationGuard::class)
                    ->acquire($tenantId, [$secondYearId, $firstYearId], lockTenant: true),
            );
            $this->assertSame([$firstYearId, $secondYearId], $locked->keys()->all());

            $this->expectLockWaitTimeout($second, $connections['second_name'], fn (): mixed => app(AnnualEconomicMutationGuard::class)
                ->acquire($tenantId, [$secondYearId]));
            $first->commit();

            $second->beginTransaction();
            $afterRelease = $this->onConnection(
                $connections['second_name'],
                fn (): mixed => app(AnnualEconomicMutationGuard::class)->acquire($tenantId, [$secondYearId]),
            );
            $this->assertSame([$secondYearId], $afterRelease->keys()->all());
            $second->rollBack();
        } finally {
            $this->cleanupConnections($connections, $tenantId);
        }
    }

    /**
     * @return array{
     *     first_name: string,
     *     second_name: string,
     *     original_default: string,
     *     first: ConnectionInterface,
     *     second: ConnectionInterface
     * }
     */
    private function isolatedConnections(string $prefix): array
    {
        $suffix = str_replace('-', '', (string) str()->uuid());
        $firstName = $prefix.'_first_'.$suffix;
        $secondName = $prefix.'_second_'.$suffix;
        $connection = config('database.connections.mysql');
        config()->set("database.connections.{$firstName}", $connection);
        config()->set("database.connections.{$secondName}", $connection);

        return [
            'first_name' => $firstName,
            'second_name' => $secondName,
            'original_default' => (string) config('database.default'),
            'first' => DB::connection($firstName),
            'second' => DB::connection($secondName),
        ];
    }

    /** @return array{int, int, int} */
    private function createTenantAndYears(ConnectionInterface $connection): array
    {
        $now = now();
        $tenantId = (int) $connection->table('tenants')->insertGetId([
            'name' => 'Tenant annual guard',
            'code' => 'annual-guard-'.str()->uuid(),
            'state' => 'active',
            'currency_code' => 'EUR',
            'language_code' => 'it',
            'timezone' => 'Europe/Rome',
            'default_vat_rate' => '22.00',
            'budget_basis' => 'net',
            'attachment_quota_bytes' => 1,
            'deletion_reason_required' => false,
            'lock_version' => 1,
            'created_at' => $now,
            'updated_at' => $now,
        ]);
        $firstYearId = (int) $connection->table('planning_years')->insertGetId([
            'tenant_id' => $tenantId,
            'year_label' => 2041,
            'active' => true,
            'lock_version' => 1,
            'created_at' => $now,
            'updated_at' => $now,
        ]);
        $secondYearId = (int) $connection->table('planning_years')->insertGetId([
            'tenant_id' => $tenantId,
            'year_label' => 2042,
            'active' => true,
            'lock_version' => 1,
            'created_at' => $now,
            'updated_at' => $now,
        ]);

        return [$tenantId, $firstYearId, $secondYearId];
    }

    private function expectLockWaitTimeout(
        ConnectionInterface $connection,
        string $connectionName,
        callable $operation,
    ): void {
        $connection->statement('SET SESSION innodb_lock_wait_timeout = 1');
        $connection->beginTransaction();

        try {
            $this->onConnection($connectionName, $operation);
            $this->fail('A concurrent annual mutation bypassed the serialization guard.');
        } catch (QueryException $exception) {
            $this->assertSame(1205, (int) ($exception->errorInfo[1] ?? 0));
        } finally {
            $connection->rollBack();
        }
    }

    private function onConnection(string $connectionName, callable $operation): mixed
    {
        $previous = (string) config('database.default');
        config()->set('database.default', $connectionName);

        try {
            return $operation();
        } finally {
            config()->set('database.default', $previous);
        }
    }

    /**
     * @param array{
     *     first_name: string,
     *     second_name: string,
     *     original_default: string,
     *     first: ConnectionInterface,
     *     second: ConnectionInterface
     * } $connections
     */
    private function cleanupConnections(array $connections, int $tenantId): void
    {
        foreach ([$connections['first'], $connections['second']] as $connection) {
            while ($connection->transactionLevel() > 0) {
                $connection->rollBack();
            }
        }

        config()->set('database.default', $connections['original_default']);
        $connections['first']->table('planning_years')->where('tenant_id', $tenantId)->delete();
        $connections['first']->table('tenants')->where('id', $tenantId)->delete();
        DB::purge($connections['first_name']);
        DB::purge($connections['second_name']);
    }
}
