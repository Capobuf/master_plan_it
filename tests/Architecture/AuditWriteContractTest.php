<?php

namespace Tests\Architecture;

use App\Domain\Audit\Data\AuditProperties;
use App\Models\Builders\AuditEventBuilder;
use FilesystemIterator;
use InvalidArgumentException;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;
use RecursiveDirectoryIterator;
use RecursiveIteratorIterator;
use ReflectionMethod;
use stdClass;

class AuditWriteContractTest extends TestCase
{
    private const ORDINARY_CREATE_ALLOWLIST = [
        'Domain/Audit/AuditRecorder.php',
    ];

    private const RETENTION_DELETE_ALLOWLIST = [];

    public function test_valid_nested_json_safe_properties_are_immutable_values(): void
    {
        $properties = new AuditProperties([
            'change' => [
                'old' => 24,
                'new' => 36,
                'confirmed' => true,
                'note' => null,
            ],
        ]);

        $this->assertSame([
            'change' => [
                'old' => 24,
                'new' => 36,
                'confirmed' => true,
                'note' => null,
            ],
        ], $properties->toArray());
    }

    public function test_every_update_capable_builder_primitive_is_declared_as_denied(): void
    {
        $methods = [
            'updateOrCreate',
            'incrementOrCreate',
            'update',
            'upsert',
            'touch',
            'increment',
            'decrement',
            'incrementEach',
            'decrementEach',
            'updateOrInsert',
            'updateFrom',
            'delete',
            'forceDelete',
            'truncate',
        ];

        foreach ($methods as $method) {
            $reflection = new ReflectionMethod(AuditEventBuilder::class, $method);

            $this->assertSame(AuditEventBuilder::class, $reflection->getDeclaringClass()->getName());
            $this->assertSame('never', (string) $reflection->getReturnType());
        }
    }

    #[DataProvider('sensitiveProperties')]
    public function test_nested_sensitive_keys_are_rejected(array $properties): void
    {
        $this->expectException(InvalidArgumentException::class);

        new AuditProperties($properties);
    }

    /**
     * @return array<string, array{array<string, mixed>}>
     */
    public static function sensitiveProperties(): array
    {
        return [
            'password case insensitive' => [['change' => ['PassWord' => 'hidden']]],
            'token nested' => [['change' => ['apiToken' => 'hidden']]],
            'secret nested' => [['change' => ['client_secret' => 'hidden']]],
            'session nested' => [['change' => ['sessionId' => 'hidden']]],
            'cookie nested' => [['change' => ['Cookie' => 'hidden']]],
            'authorization nested' => [['change' => ['Authorization' => 'hidden']]],
            'credentials nested' => [['change' => ['CREDENTIALS' => 'hidden']]],
            'generic payload nested' => [['change' => ['PAYLOAD' => 'hidden']]],
            'attachment payload nested' => [['change' => ['ATTACHMENT_PAYLOAD' => 'hidden']]],
            'file content nested' => [['change' => ['file_content' => 'hidden']]],
            'file data nested' => [['change' => ['FILE_DATA' => 'hidden']]],
            'attachment bytes nested' => [['change' => ['attachmentBytes' => 'hidden']]],
        ];
    }

    public function test_safe_payload_metadata_key_is_accepted(): void
    {
        $properties = new AuditProperties(['payload_version_id' => 42]);

        $this->assertSame(['payload_version_id' => 42], $properties->toArray());
    }

    #[DataProvider('invalidPropertyValues')]
    public function test_non_json_safe_and_float_values_are_rejected(mixed $value): void
    {
        $this->expectException(InvalidArgumentException::class);

        new AuditProperties(['value' => $value]);
    }

    /**
     * @return array<string, array{mixed}>
     */
    public static function invalidPropertyValues(): array
    {
        return [
            'float' => [1.5],
            'object' => [new stdClass],
        ];
    }

    public function test_resource_values_are_rejected(): void
    {
        $resource = fopen('php://memory', 'r');
        $this->assertIsResource($resource);

        try {
            $this->expectException(InvalidArgumentException::class);
            new AuditProperties(['value' => $resource]);
        } finally {
            fclose($resource);
        }
    }

    public function test_invalid_json_is_rejected(): void
    {
        $this->expectException(InvalidArgumentException::class);

        new AuditProperties(['value' => "\xB1"]);
    }

    public function test_encoded_size_boundary_accepts_16384_bytes_and_rejects_16385(): void
    {
        $accepted = new AuditProperties(['v' => str_repeat('a', 16_376)]);

        $this->assertSame(16_384, strlen(json_encode($accepted->toArray(), JSON_THROW_ON_ERROR)));

        $this->expectException(InvalidArgumentException::class);

        new AuditProperties(['v' => str_repeat('a', 16_377)]);
    }

    #[DataProvider('auditWriteEscapes')]
    public function test_bounded_analyzer_rejects_audit_write_escapes(string $source): void
    {
        $this->assertNotSame([], self::auditWriteViolations($source));
    }

    /**
     * @return array<string, array{string}>
     */
    public static function auditWriteEscapes(): array
    {
        return [
            'direct insert' => ['<?php use App\\Models\\AuditEvent; AuditEvent::query()->insert([]);'],
            'direct insert or ignore using' => ['<?php use App\\Models\\AuditEvent; AuditEvent::query()->insertOrIgnoreUsing([], $query);'],
            'direct insert or ignore returning' => ['<?php use App\\Models\\AuditEvent; AuditEvent::query()->insertOrIgnoreReturning([]);'],
            'aliased upsert' => ['<?php use App\\Models\\AuditEvent as Event; Event::query()->upsert([], [], []);'],
            'assigned builder base update' => ['<?php use App\\Models\\AuditEvent; $builder = AuditEvent::query(); $builder->toBase()->update([]);'],
            'raw query from update' => ["<?php use Illuminate\\Support\\Facades\\DB; DB::query()->from('audit_events')->update([]);"],
            'aliased raw connection delete' => ["<?php use Illuminate\\Support\\Facades\\DB as Database; Database::connection()->query()->from('audit_events')->delete();"],
            'fully qualified raw truncate' => ["<?php \\Illuminate\\Support\\Facades\\DB::table('audit_events')->".'truncate();'],
            'relationship save' => ['<?php $user->auditEvents()->save($event);'],
            'assigned model save' => ['<?php use App\\Models\\AuditEvent; $event = new AuditEvent; $event->save();'],
            'assigned model save quietly' => ['<?php use App\\Models\\AuditEvent; $event = new AuditEvent; $event->saveQuietly();'],
            'direct increment' => ['<?php use App\\Models\\AuditEvent; AuditEvent::query()->increment("id");'],
            'direct decrement' => ['<?php use App\\Models\\AuditEvent; AuditEvent::query()->decrement("id");'],
        ];
    }

    public function test_recorder_create_allowlist_does_not_hide_update_or_delete(): void
    {
        $source = '<?php use App\\Models\\AuditEvent; '
            .'AuditEvent::query()->create([]); '
            .'AuditEvent::query()->update([]); '
            .'AuditEvent::query()->delete();';

        $violations = self::auditWriteViolations($source, allowRecorderCreate: true);

        $this->assertContains('AuditEvent::update', $violations);
        $this->assertContains('AuditEvent::delete', $violations);
        $this->assertNotContains('AuditEvent::create', $violations);
    }

    public function test_recorder_create_allowlist_does_not_allow_an_aliased_second_create(): void
    {
        $source = '<?php use App\\Models\\AuditEvent; use App\\Models\\AuditEvent as Event; '
            .'AuditEvent::query()->create([]); Event::query()->create([]);';

        $this->assertContains(
            'AuditEvent::create',
            self::auditWriteViolations($source, allowRecorderCreate: true),
        );
    }

    #[DataProvider('auditReadPaths')]
    public function test_bounded_analyzer_preserves_read_paths(string $source): void
    {
        $this->assertSame([], self::auditWriteViolations($source));
    }

    /**
     * @return array<string, array{string}>
     */
    public static function auditReadPaths(): array
    {
        return [
            'direct eloquent read' => ['<?php use App\\Models\\AuditEvent; AuditEvent::query()->whereKey(1)->first();'],
            'aliased assigned builder read' => ['<?php use App\\Models\\AuditEvent as Event; $builder = Event::query(); $builder->toBase()->count();'],
            'raw query read' => ["<?php use Illuminate\\Support\\Facades\\DB; DB::query()->from('audit_events')->get();"],
        ];
    }

    public function test_application_audit_writes_use_only_the_explicit_writer_allowlist(): void
    {
        $this->assertSame(['Domain/Audit/AuditRecorder.php'], self::ORDINARY_CREATE_ALLOWLIST);
        $this->assertSame([], self::RETENTION_DELETE_ALLOWLIST);

        $appPath = dirname(__DIR__, 2).'/app';
        $files = new RecursiveIteratorIterator(
            new RecursiveDirectoryIterator($appPath, FilesystemIterator::SKIP_DOTS),
        );

        foreach ($files as $file) {
            if ($file->getExtension() !== 'php') {
                continue;
            }

            $path = substr($file->getPathname(), strlen($appPath) + 1);
            $source = file_get_contents($file->getPathname());

            $this->assertIsString($source, "Unable to read {$path}.");

            $allowsRecorderCreate = in_array($path, self::ORDINARY_CREATE_ALLOWLIST, true);
            $allowsRetentionDelete = in_array($path, self::RETENTION_DELETE_ALLOWLIST, true);

            if ($allowsRecorderCreate) {
                $this->assertSame(
                    1,
                    preg_match_all('/\bAuditEvent\s*::\s*query\s*\(\s*\)\s*->\s*create\s*\(/', $source),
                    'AuditRecorder must contain exactly its one authorized create call.',
                );
            }

            $this->assertSame(
                [],
                self::auditWriteViolations($source, $allowsRecorderCreate, $allowsRetentionDelete),
                "Unauthorized audit write found in {$path}.",
            );
        }
    }

    /**
     * @return list<string>
     */
    private static function auditWriteViolations(
        string $source,
        bool $allowRecorderCreate = false,
        bool $allowRetentionDelete = false,
    ): array {
        $source = self::normalizePhpSource($source);
        $auditSymbols = self::importedSymbols($source, 'App\\Models\\AuditEvent', 'AuditEvent');
        $databaseSymbols = self::importedSymbols(
            $source,
            'Illuminate\\Support\\Facades\\DB',
            'DB',
        );
        $mutationMethods = '(?:createManyQuietly|createMany|createQuietly|create|forceCreateQuietly|forceCreate|firstOrCreate|createOrFirst|insertOrIgnoreReturning|insertOrIgnoreUsing|insertOrIgnore|insertGetId|insertUsing|insert|saveManyQuietly|saveMany|saveQuietly|saveOrFail|save|updateOrCreate|updateOrInsert|updateOrFail|updateQuietly|updateFrom|update|upsert|incrementEachQuietly|incrementEach|incrementOrCreate|incrementQuietly|increment|decrementEachQuietly|decrementEach|decrementQuietly|decrement|deleteQuietly|deleteOrFail|delete|forceDeleteQuietly|forceDelete|touchQuietly|touch|pushQuietly|push|destroy|truncate)';
        $auditPattern = self::symbolPattern($auditSymbols, 'App\\Models\\AuditEvent');
        $databasePattern = self::symbolPattern($databaseSymbols, 'Illuminate\\Support\\Facades\\DB');
        $violations = [];

        preg_match_all(
            '~(?<![A-Za-z0-9_\\\\])(?P<symbol>'.$auditPattern.')\s*::(?P<chain>[^;]+)~i',
            $source,
            $auditChains,
        );

        foreach ($auditChains['chain'] as $index => $chain) {
            preg_match_all('~(?:^|->)\s*(?P<method>'.$mutationMethods.')\s*\(~i', $chain, $matches);

            foreach ($matches['method'] as $method) {
                $isAuthorizedCreate = $allowRecorderCreate
                    && $auditChains['symbol'][$index] === 'AuditEvent'
                    && strcasecmp($method, 'create') === 0
                    && preg_match('~^\s*query\s*\(\s*\)\s*->\s*create\s*\(~i', $chain) === 1;
                $isAuthorizedRetentionDelete = $allowRetentionDelete
                    && in_array(strtolower($method), ['delete', 'forcedelete'], true);

                if (! $isAuthorizedCreate && ! $isAuthorizedRetentionDelete) {
                    $violations[] = "AuditEvent::{$method}";
                }
            }
        }

        preg_match_all(
            '~(?<![A-Za-z0-9_\\\\])(?:'.$databasePattern.')\s*::(?P<chain>[^;]+)~i',
            $source,
            $databaseChains,
        );

        foreach ($databaseChains['chain'] as $chain) {
            if (preg_match("~(?:table|from)\\s*\\(\\s*['\"]audit_events['\"]\\s*\\)~i", $chain) === 1
                && preg_match('~->\s*'.$mutationMethods.'\s*\(~i', $chain, $match) === 1) {
                $violations[] = 'raw audit_events '.$match[0];
            }
        }

        $trackedBuilders = self::trackedAuditBuilderVariables(
            $source,
            $auditPattern,
            $databasePattern,
        );

        foreach ($trackedBuilders as $variable) {
            if (preg_match_all(
                '~\$'.preg_quote($variable, '~').'\s*(?:->[^;]+)?->\s*(?P<method>'.$mutationMethods.')\s*\(~i',
                $source,
                $matches,
            ) > 0) {
                foreach ($matches['method'] as $method) {
                    $violations[] = '$'.$variable.'->'.$method;
                }
            }
        }

        if (preg_match_all(
            '~auditEvents\s*\(\s*\)(?:->[^;]+)?->\s*(?P<method>'.$mutationMethods.')\s*\(~i',
            $source,
            $relationshipMatches,
        ) > 0) {
            foreach ($relationshipMatches['method'] as $method) {
                $violations[] = 'auditEvents()->'.$method;
            }
        }

        return array_values(array_unique($violations));
    }

    /**
     * @return list<string>
     */
    private static function importedSymbols(string $source, string $class, string $default): array
    {
        $symbols = [$default];

        if (preg_match_all(
            '~\buse\s+'.preg_quote($class, '~').'(?:\s+as\s+(?P<alias>[A-Za-z_][A-Za-z0-9_]*))?\s*;~i',
            $source,
            $matches,
        ) > 0) {
            foreach ($matches['alias'] as $alias) {
                $symbols[] = $alias === '' ? $default : $alias;
            }
        }

        return array_values(array_unique($symbols));
    }

    /**
     * @param  list<string>  $symbols
     */
    private static function symbolPattern(array $symbols, string $class): string
    {
        $patterns = array_map(static fn (string $symbol): string => preg_quote($symbol, '~'), $symbols);
        $patterns[] = preg_quote('\\'.$class, '~');

        return '(?:'.implode('|', array_unique($patterns)).')';
    }

    /**
     * @return list<string>
     */
    private static function trackedAuditBuilderVariables(
        string $source,
        string $auditPattern,
        string $databasePattern,
    ): array {
        $tracked = [];
        $statements = preg_split('/;/', $source) ?: [];

        foreach ($statements as $statement) {
            if (preg_match('~\$(?P<variable>[A-Za-z_][A-Za-z0-9_]*)\s*=\s*(?P<value>.+)$~s', $statement, $assignment) !== 1) {
                continue;
            }

            $value = $assignment['value'];
            $fromAuditModel = preg_match(
                '~(?:new\s+|(?<![A-Za-z0-9_\\\\]))(?:'.$auditPattern.')(?:::|\b)~i',
                $value,
            ) === 1;
            $fromRawAuditTable = preg_match(
                '~(?:'.$databasePattern.')\s*::[^;]*(?:table|from)\s*\(\s*[\'\"]audit_events[\'\"]\s*\)~i',
                $value,
            ) === 1;
            $fromTrackedVariable = false;

            foreach ($tracked as $trackedVariable) {
                if (preg_match('~\$'.preg_quote($trackedVariable, '~').'\b~', $value) === 1) {
                    $fromTrackedVariable = true;

                    break;
                }
            }

            if ($fromAuditModel || $fromRawAuditTable || $fromTrackedVariable) {
                $tracked[] = $assignment['variable'];
            }
        }

        return array_values(array_unique($tracked));
    }

    private static function normalizePhpSource(string $source): string
    {
        $normalized = '';

        foreach (token_get_all($source) as $token) {
            if (is_array($token)) {
                if (in_array($token[0], [T_COMMENT, T_DOC_COMMENT], true)) {
                    continue;
                }

                $normalized .= $token[0] === T_WHITESPACE ? ' ' : $token[1];

                continue;
            }

            $normalized .= $token;
        }

        return $normalized;
    }
}
