<?php

namespace Tests\Architecture;

use App\Domain\Tenancy\Data\TenantContext;
use App\Domain\Tenancy\Queries\TenantOwnedRecordQuery;
use App\Models\Tenant;
use App\Models\User;
use App\Support\Tenancy\ResolvesTenantOwnedBindings;
use Illuminate\Auth\Access\AuthorizationException;
use Illuminate\Container\Container;
use Illuminate\Database\Connection;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\ModelNotFoundException;
use Illuminate\Database\Query\Builder as QueryBuilder;
use Illuminate\Http\Request;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;

final class TenantQueryScopeTest extends TestCase
{
    private ?Container $originalContainer = null;

    protected function setUp(): void
    {
        parent::setUp();

        $this->originalContainer = Container::getInstance();
        Container::setInstance(new Container);
        TenantOwnedQueryFixture::useConnection($this->emptyConnection());
    }

    protected function tearDown(): void
    {
        Container::setInstance($this->originalContainer);

        parent::tearDown();
    }

    public function test_query_helper_starts_with_tenant_predicate_before_record_key(): void
    {
        $query = TenantOwnedRecordQuery::forTenant($this->context(41), TenantOwnedQueryFixture::class)
            ->whereKey(99);

        $wheres = $query->getQuery()->wheres;

        $this->assertCount(2, $wheres);
        $this->assertSame('tenant_owned_records.tenant_id', $wheres[0]['column']);
        $this->assertSame(41, $wheres[0]['value']);
        $this->assertSame('tenant_owned_records.id', $wheres[1]['column']);
        $this->assertSame(99, $wheres[1]['value']);
    }

    public function test_find_or_fail_returns_only_the_owner_record_and_hides_missing_or_foreign_records(): void
    {
        $ownerRecord = TenantOwnedRecordQuery::findOrFail(
            $this->context(41),
            TenantOwnedQueryFixture::class,
            99,
        );

        $this->assertSame(99, $ownerRecord->getKey());

        foreach ([[$this->context(41), 100], [$this->context(42), 99]] as [$context, $recordId]) {
            try {
                TenantOwnedRecordQuery::findOrFail($context, TenantOwnedQueryFixture::class, $recordId);
                $this->fail('Tenant-owned lookup unexpectedly disclosed a record.');
            } catch (ModelNotFoundException $exception) {
                $this->assertSame(TenantOwnedQueryFixture::class, $exception->getModel());
            }
        }
    }

    public function test_route_binding_starts_with_tenant_predicate_before_route_key(): void
    {
        $request = Request::create('/tenant-records/99');
        $request->attributes->set(TenantContext::class, $this->context(41));
        Container::getInstance()->instance('request', $request);

        $model = new TenantOwnedRouteBindingFixture;
        $query = $model->resolveRouteBindingQuery($model->newQuery(), 99);
        $wheres = $query->getQuery()->wheres;

        $this->assertCount(2, $wheres);
        $this->assertSame('tenant_owned_records.tenant_id', $wheres[0]['column']);
        $this->assertSame(41, $wheres[0]['value']);
        $this->assertSame('id', $wheres[1]['column']);
        $this->assertSame(99, $wheres[1]['value']);
    }

    public function test_route_binding_fails_closed_when_tenant_context_is_absent(): void
    {
        Container::getInstance()->instance('request', Request::create('/tenant-records/99'));

        $model = new TenantOwnedRouteBindingFixture;

        try {
            $model->resolveRouteBindingQuery($model->newQuery(), 99);
            $this->fail('Route binding accepted a missing tenant context.');
        } catch (AuthorizationException $exception) {
            $this->assertSame('TENANT_CONTEXT_REQUIRED', $exception->getMessage());
        }
    }

    public function test_route_binding_hides_missing_and_foreign_records_like_the_owner_lookup(): void
    {
        foreach ([[$this->context(41), 99, true], [$this->context(41), 100, false], [$this->context(42), 99, false]] as [$context, $recordId, $visible]) {
            $request = Request::create('/tenant-records/'.$recordId);
            $request->attributes->set(TenantContext::class, $context);
            Container::getInstance()->instance('request', $request);

            $model = new TenantOwnedRouteBindingFixture;
            $query = $model->resolveRouteBindingQuery($model->newQuery(), $recordId);

            if ($visible) {
                $this->assertSame(99, $query->firstOrFail()->getKey());

                continue;
            }

            try {
                $query->firstOrFail();
                $this->fail('Route binding unexpectedly disclosed a record.');
            } catch (ModelNotFoundException $exception) {
                $this->assertSame(TenantOwnedRouteBindingFixture::class, $exception->getModel());
            }
        }
    }

    #[DataProvider('unsafeTenantOwnedLookups')]
    public function test_bounded_analyzer_rejects_static_and_instantiated_tenant_owned_lookups(string $source): void
    {
        $this->assertNotSame([], self::tenantLookupViolations($source, ['App\\Models\\AuditEvent', 'App\\Models\\User']));
    }

    /**
     * @return array<string, array{string}>
     */
    public static function unsafeTenantOwnedLookups(): array
    {
        return [
            'direct static lookup' => ['<?php use App\\Models\\AuditEvent; AuditEvent::query()->findOrFail(99);'],
            'aliased static lookup' => ['<?php use App\\Models\\User as TenantUser; TenantUser::find(99);'],
            'leading separator aliased static lookup' => ['<?php use \\App\\Models\\AuditEvent as Event; Event::query();'],
            'grouped import static lookup' => ['<?php use App\\Models\\{AuditEvent as Event, Tenant}; Event::query()->firstOrFail();'],
            'leading separator grouped aliased instantiation' => ['<?php use \\App\\Models\\{AuditEvent as Event}; $event = new Event;'],
            'leading separator grouped direct static lookup' => ['<?php use \\App\\Models\\{AuditEvent}; AuditEvent::query();'],
            'leading separator grouped direct instantiation' => ['<?php use \\App\\Models\\{AuditEvent}; $event = new AuditEvent;'],
            'multiple import aliased static lookup' => ['<?php use App\\Models\\Tenant, App\\Models\\AuditEvent as Event; Event::query();'],
            'multiple import second leading separator alias' => ['<?php use App\\Models\\Tenant, \\App\\Models\\AuditEvent as Event; Event::query();'],
            'multiple import second leading separator instantiation' => ['<?php use App\\Models\\Tenant, \\App\\Models\\AuditEvent as Event; new Event;'],
            'leading separator grouped second component alias' => ['<?php use \\App\\Models\\{Tenant, AuditEvent as Event}; Event::query();'],
            'namespace alias static lookup' => ['<?php use App\\Models as Models; Models\\AuditEvent::query();'],
            'fully qualified static lookup' => ['<?php \\App\\Models\\AuditEvent::query()->whereKey(99)->first();'],
            'dynamic static lookup from class reference' => ['<?php use App\\Models\\AuditEvent; $type = AuditEvent::class; $type::query();'],
            'dynamic model instantiation from class reference' => ['<?php use App\\Models\\AuditEvent; $type = AuditEvent::class; new $type;'],
            'parenthesized class reference instantiation' => ['<?php use App\\Models\\AuditEvent; new (AuditEvent::class);'],
            'parenthesized class reference static lookup' => ['<?php use App\\Models\\AuditEvent; (AuditEvent::class)::query();'],
            'dynamic static method lookup' => ['<?php use App\\Models\\AuditEvent; AuditEvent::{$method}();'],
            'select escape' => ['<?php use App\\Models\\AuditEvent; AuditEvent::select("id")->get();'],
            'relationship escape' => ['<?php use App\\Models\\User; User::with("tenant")->get();'],
            'sole escape' => ['<?php use App\\Models\\AuditEvent; AuditEvent::sole();'],
            'value escape' => ['<?php use App\\Models\\AuditEvent; AuditEvent::value("id");'],
            'exists escape' => ['<?php use App\\Models\\User; User::exists();'],
            'aggregate escape' => ['<?php use App\\Models\\AuditEvent; AuditEvent::count();'],
            'aliased model instantiation' => ['<?php use App\\Models\\AuditEvent as Event; $event = new Event;'],
            'grouped import model instantiation' => ['<?php use App\\Models\\{User as TenantUser}; $user = new TenantUser();'],
            'fully qualified model instantiation' => ['<?php $event = new \\App\\Models\\AuditEvent;'],
        ];
    }

    #[DataProvider('allowedTenantLookupPaths')]
    public function test_bounded_analyzer_preserves_allowed_paths_and_ignores_comments_and_strings(string $source): void
    {
        $this->assertSame([], self::tenantLookupViolations($source, ['App\\Models\\AuditEvent', 'App\\Models\\User']));
    }

    /**
     * @return array<string, array{string}>
     */
    public static function allowedTenantLookupPaths(): array
    {
        return [
            'class reference' => ['<?php use App\\Models\\AuditEvent; $type = AuditEvent::class;'],
            'route-bound type hint' => ['<?php use App\\Models\\AuditEvent; function show(AuditEvent $event): void {}'],
            'tenant helper with class reference' => ['<?php use App\\Domain\\Tenancy\\Queries\\TenantOwnedRecordQuery; use App\\Models\\AuditEvent; $event = TenantOwnedRecordQuery::findOrFail($context, AuditEvent::class, 99);'],
            'global model lookup' => ['<?php use App\\Models\\Tenant; Tenant::query()->find(99);'],
            'multiple import with global model lookup' => ['<?php use App\\Models\\Tenant, App\\Models\\PlatformSetting; Tenant::query()->find(99);'],
            'comment and string' => ['<?php // AuditEvent::query()->find(99)\n$label = "new \\App\\Models\\AuditEvent";'],
        ];
    }

    public function test_bounded_application_layers_contain_no_unscoped_tenant_owned_lookup(): void
    {
        $root = dirname(__DIR__, 2);
        $tenantModels = self::tenantOwnedModels($root);

        $this->assertContains('App\\Models\\AuditEvent', $tenantModels);
        $this->assertContains('App\\Models\\User', $tenantModels);

        foreach (self::boundedApplicationFiles($root) as $file) {
            $this->assertSame(
                [],
                self::tenantLookupViolations((string) file_get_contents($file), $tenantModels),
                'Unscoped tenant-owned lookup found in '.str_replace($root.'/', '', $file),
            );
        }
    }

    public function test_nested_domain_queries_are_included_in_the_bounded_scan(): void
    {
        $this->assertTrue(self::isBoundedApplicationPath('app/Domain/Tenancy/Queries/Nested/RecordQuery.php'));
    }

    private function context(int $tenantId): TenantContext
    {
        $tenant = new Tenant;
        $tenant->forceFill([
            'id' => $tenantId,
            'language_code' => 'it',
            'timezone' => 'Europe/Rome',
            'currency_code' => 'EUR',
            'default_vat_rate' => '22.000000',
            'budget_basis' => 'net',
        ]);
        $tenant->syncOriginal();

        $actor = new User;
        $actor->forceFill(['id' => 5, 'tenant_id' => $tenantId]);

        return new TenantContext($tenant, $actor);
    }

    private function emptyConnection(): Connection
    {
        return new class(null) extends Connection
        {
            public function select($query, $bindings = [], $useReadPdo = true, array $fetchUsing = []): array
            {
                return $bindings === [41, 99] ? [['id' => 99, 'tenant_id' => 41]] : [];
            }
        };
    }

    /** @return list<string> */
    private static function tenantOwnedModels(string $root): array
    {
        $models = [];

        foreach (glob($root.'/app/Models/*.php') ?: [] as $file) {
            $source = file_get_contents($file);

            if (! is_string($source) || ! str_contains($source, "'tenant_id'")) {
                continue;
            }

            $models[] = 'App\\Models\\'.pathinfo($file, PATHINFO_FILENAME);
        }

        sort($models);

        return $models;
    }

    /** @return list<string> */
    private static function boundedApplicationFiles(string $root): array
    {
        $roots = [
            $root.'/app/Http/Controllers',
            $root.'/app/Http/Resources',
            $root.'/app/Domain',
        ];
        $files = [];

        foreach ($roots as $path) {
            if (! is_dir($path)) {
                continue;
            }

            $iterator = new \RecursiveIteratorIterator(
                new \RecursiveDirectoryIterator($path, \FilesystemIterator::SKIP_DOTS),
            );

            foreach ($iterator as $file) {
                if (! $file->isFile() || $file->getExtension() !== 'php') {
                    continue;
                }

                $relative = str_replace($root.'/', '', $file->getPathname());
                if (self::isBoundedApplicationPath($relative)) {
                    $files[] = $file->getPathname();
                }
            }
        }

        sort($files);

        return $files;
    }

    private static function isBoundedApplicationPath(string $relative): bool
    {
        return preg_match('#^app/Domain/[^/]+/Queries(?:/.+)?/[^/]+\.php$#', $relative) === 1
            || str_starts_with($relative, 'app/Http/Controllers/')
            || str_starts_with($relative, 'app/Http/Resources/');
    }

    /**
     * @param  list<string>  $tenantModels
     * @return list<string>
     */
    private static function tenantLookupViolations(string $source, array $tenantModels): array
    {
        $code = self::executablePhp($source);
        $symbols = self::importedTenantModelSymbols($code, $tenantModels);
        $modelPattern = self::symbolPattern($symbols, $tenantModels);
        $violations = [];

        if ($modelPattern === '') {
            return [];
        }

        preg_match_all(
            '~(?<![A-Za-z0-9_\\\\])(?P<model>'.$modelPattern.')\s*::\s*(?P<method>[A-Za-z_][A-Za-z0-9_]*)\s*\(~i',
            $code,
            $staticLookups,
        );

        foreach ($staticLookups['model'] as $index => $model) {
            $violations[] = $model.'::'.$staticLookups['method'][$index];
        }

        preg_match_all(
            '~(?<![A-Za-z0-9_\\\\])(?P<model>'.$modelPattern.')\s*::\s*\{\s*\$[A-Za-z_][A-Za-z0-9_]*\s*\}\s*\(~i',
            $code,
            $dynamicStaticMethods,
        );

        foreach ($dynamicStaticMethods['model'] as $model) {
            $violations[] = $model.'::{$method}';
        }

        preg_match_all(
            '~\(\s*(?P<model>'.$modelPattern.')\s*::\s*class\s*\)\s*::\s*[A-Za-z_][A-Za-z0-9_]*\s*\(~i',
            $code,
            $parenthesizedStaticLookups,
        );

        foreach ($parenthesizedStaticLookups['model'] as $model) {
            $violations[] = '('.$model.'::class)::method';
        }

        preg_match_all(
            '~\$[A-Za-z_][A-Za-z0-9_]*\s*::\s*[A-Za-z_][A-Za-z0-9_]*\s*\(~',
            $code,
            $dynamicStaticLookups,
        );

        foreach ($dynamicStaticLookups[0] as $lookup) {
            $violations[] = trim($lookup);
        }

        preg_match_all(
            '~\bnew\s+(?P<model>'.$modelPattern.')\b~i',
            $code,
            $instantiations,
        );

        foreach ($instantiations['model'] as $model) {
            $violations[] = 'new '.$model;
        }

        preg_match_all(
            '~\bnew\s*\(\s*(?P<model>'.$modelPattern.')\s*::\s*class\s*\)~i',
            $code,
            $parenthesizedInstantiations,
        );

        foreach ($parenthesizedInstantiations['model'] as $model) {
            $violations[] = 'new ('.$model.'::class)';
        }

        preg_match_all('~\bnew\s+\$[A-Za-z_][A-Za-z0-9_]*\b~', $code, $dynamicInstantiations);

        foreach ($dynamicInstantiations[0] as $instantiation) {
            $violations[] = trim($instantiation);
        }

        return array_values(array_unique($violations));
    }

    /**
     * @param  list<string>  $tenantModels
     * @return array<string, string>
     */
    private static function importedTenantModelSymbols(string $code, array $tenantModels): array
    {
        $symbols = [];

        foreach ($tenantModels as $model) {
            $shortName = substr($model, strrpos($model, '\\') + 1);
            $symbols[$shortName] = $model;
            $symbols['\\'.$model] = $model;
        }

        foreach (self::importDeclarations($code) as $declaration) {
            foreach (self::splitImportComponents($declaration) as $component) {
                $component = ltrim(trim($component), '\\');

                if (preg_match('~^App\\\\Models\s+as\s+(?<alias>[A-Za-z_][A-Za-z0-9_]*)$~i', $component, $match) === 1) {
                    foreach ($tenantModels as $model) {
                        $shortName = substr($model, strrpos($model, '\\') + 1);
                        $symbols[$match['alias'].'\\'.$shortName] = $model;
                    }

                    continue;
                }

                if (preg_match('~^App\\\\Models\\\\\{(?<members>[^}]+)\}$~i', $component, $match) === 1) {
                    self::addGroupedTenantModelAliases($symbols, $match['members'], $tenantModels);

                    continue;
                }

                foreach ($tenantModels as $model) {
                    if (preg_match(
                        '~^'.preg_quote($model, '~').'\s+as\s+(?<alias>[A-Za-z_][A-Za-z0-9_]*)$~i',
                        $component,
                        $match,
                    ) === 1) {
                        $symbols[$match['alias']] = $model;
                    }
                }
            }
        }

        return $symbols;
    }

    /** @return list<string> */
    private static function importDeclarations(string $code): array
    {
        $declarations = [];
        $tokens = token_get_all($code);

        for ($index = 0, $count = count($tokens); $index < $count; $index++) {
            if (! is_array($tokens[$index]) || $tokens[$index][0] !== T_USE) {
                continue;
            }

            $declaration = '';
            for ($index++; $index < $count; $index++) {
                $token = $tokens[$index];
                $text = is_array($token) ? $token[1] : $token;

                if ($text === ';') {
                    break;
                }

                $declaration .= $text;
            }

            if (! str_contains($declaration, '(')) {
                $declarations[] = $declaration;
            }
        }

        return $declarations;
    }

    /** @return list<string> */
    private static function splitImportComponents(string $declaration): array
    {
        $components = [];
        $component = '';
        $depth = 0;

        foreach (str_split($declaration) as $character) {
            if ($character === '{') {
                $depth++;
            } elseif ($character === '}') {
                $depth--;
            }

            if ($character === ',' && $depth === 0) {
                $components[] = $component;
                $component = '';

                continue;
            }

            $component .= $character;
        }

        if (trim($component) !== '') {
            $components[] = $component;
        }

        return $components;
    }

    /**
     * @param  array<string, string>  $symbols
     * @param  list<string>  $tenantModels
     */
    private static function addGroupedTenantModelAliases(array &$symbols, string $members, array $tenantModels): void
    {
        foreach (self::splitImportComponents($members) as $member) {
            foreach ($tenantModels as $model) {
                $shortName = substr($model, strrpos($model, '\\') + 1);

                if (preg_match(
                    '~^\s*'.preg_quote($shortName, '~').'\s+as\s+(?<alias>[A-Za-z_][A-Za-z0-9_]*)\s*$~i',
                    $member,
                    $match,
                ) === 1) {
                    $symbols[$match['alias']] = $model;
                }
            }
        }
    }

    /**
     * @param  array<string, string>  $symbols
     * @param  list<string>  $tenantModels
     */
    private static function symbolPattern(array $symbols, array $tenantModels): string
    {
        $candidates = array_keys($symbols);
        foreach ($tenantModels as $model) {
            $candidates[] = '\\'.$model;
        }

        $candidates = array_values(array_unique($candidates));
        usort($candidates, static fn (string $left, string $right): int => strlen($right) <=> strlen($left));

        return implode('|', array_map(static fn (string $symbol): string => preg_quote($symbol, '~'), $candidates));
    }

    private static function executablePhp(string $source): string
    {
        $code = '';

        foreach (token_get_all($source) as $token) {
            if (is_array($token) && in_array($token[0], [
                T_COMMENT,
                T_DOC_COMMENT,
                T_CONSTANT_ENCAPSED_STRING,
                T_ENCAPSED_AND_WHITESPACE,
            ], true)) {
                continue;
            }

            $code .= is_array($token) ? $token[1] : $token;
        }

        return $code;
    }
}

class TenantOwnedQueryFixture extends Model
{
    private static ?Connection $testConnection = null;

    protected $table = 'tenant_owned_records';

    public static function useConnection(Connection $connection): void
    {
        self::$testConnection = $connection;
    }

    public function newQuery()
    {
        return $this->newEloquentBuilder(
            new QueryBuilder(self::$testConnection),
        )->setModel($this);
    }
}

final class TenantOwnedRouteBindingFixture extends TenantOwnedQueryFixture
{
    use ResolvesTenantOwnedBindings;
}
