<?php

namespace Tests\Architecture;

use PHPUnit\Framework\AssertionFailedError;
use PHPUnit\Framework\TestCase;

class WriteRollbackCoverageTest extends TestCase
{
    private const ALLOWED_FAILURE_EXPECTATIONS = [
        'expectException(',
        '->throws(',
    ];

    private const ALLOWED_FAILURE_TRIGGERS = [
        'andThrow(',
        'willThrowException(',
        'throw new ',
    ];

    private const ALLOWED_ROLLBACK_ASSERTIONS = [
        'assertDatabaseHas(',
        'assertDatabaseMissing(',
        'assertDatabaseCount(',
        'assertModelExists(',
        'assertModelMissing(',
    ];

    public function test_every_domain_write_has_an_explicit_rollback_method_contract(): void
    {
        $root = dirname(__DIR__, 2);
        $contract = require __DIR__.'/Fixtures/domain-write-rollback-map.php';
        $writes = $contract['writes'] ?? null;

        $this->assertSame(3, $contract['schema'] ?? null);
        $this->assertIsArray($writes);

        $discovered = $this->discoverDomainWrites($root);
        $mapped = array_keys($writes);
        sort($mapped);

        $this->assertSame($discovered, $mapped, 'Every discovered domain write Action must be mapped.');
        $this->assertSame(count($discovered), $contract['expectedDomainWriteCount'] ?? null);

        foreach ($writes as $action => $entry) {
            $this->assertFileExists($root.'/'.$action);
            $this->assertEntryStructure($action, $entry);

            $testFile = $root.'/'.$entry['testFile'];
            $this->assertFileExists($testFile);
            $methodBody = $this->extractMethodBody(file_get_contents($testFile), $entry['testMethod']);
            $this->assertNotNull($methodBody, $entry['testFile'].'::'.$entry['testMethod'].' does not exist.');
            $this->assertMethodProvesRollback($methodBody, $entry);
        }
    }

    public function test_generic_assertions_cannot_satisfy_a_rollback_contract(): void
    {
        $entry = $this->validSyntheticEntry();
        $entry['rollbackAssertions'] = ['assertTrue('];

        $this->expectException(AssertionFailedError::class);
        $this->assertEntryStructure('app/Domain/Synthetic/Actions/SyntheticWrite.php', $entry);
    }

    public function test_action_reference_failure_and_atomicity_assertions_are_all_required(): void
    {
        $entry = $this->validSyntheticEntry();
        $strongMethod = 'SyntheticWrite::class; $mock->andThrow(new RuntimeException); '
            .'$action = app(SyntheticWrite::class); $action->execute(); '
            .'$this->expectException(RuntimeException::class); '
            .'$this->assertDatabaseHas("records", []); '
            .'$this->assertDatabaseMissing("partial_records", []);';
        $source = '<?php class SyntheticRollbackTest { public function '
            .$entry['testMethod'].'(): void { '.$strongMethod.' } }';
        $extracted = $this->extractMethodBody($source, $entry['testMethod']);

        $this->assertNotNull($extracted);
        $this->assertMethodProvesRollback($extracted, $entry);

        $rejected = false;
        try {
            $this->assertMethodProvesRollback('$this->assertTr'.'ue(true);', $entry);
        } catch (AssertionFailedError) {
            $rejected = true;
        }

        $this->assertTrue($rejected, 'A generic assertion unexpectedly satisfied the rollback contract.');
    }

    public function test_an_isolated_action_class_reference_is_not_an_invocation(): void
    {
        $entry = $this->validSyntheticEntry();
        $body = 'SyntheticWrite::class; $mock->andThrow(new RuntimeException); '
            .'$this->expectException(RuntimeException::class); '
            .'$this->assertDatabaseHas("records", []); '
            .'$this->assertDatabaseMissing("partial_records", []);';

        $rejected = false;
        try {
            $this->assertMethodProvesRollback($body, $entry);
        } catch (AssertionFailedError) {
            $rejected = true;
        }

        $this->assertTrue($rejected, 'An isolated Action::class reference unexpectedly counted as invocation.');
    }

    /** @return list<string> */
    private function discoverDomainWrites(string $root): array
    {
        $discovered = [];
        if (is_dir($root.'/app/Domain')) {
            $iterator = new \RecursiveIteratorIterator(new \RecursiveDirectoryIterator($root.'/app/Domain'));
            foreach ($iterator as $file) {
                if (! $file->isFile() || $file->getExtension() !== 'php') {
                    continue;
                }

                $relative = str_replace($root.'/', '', $file->getPathname());
                if (preg_match('#^app/Domain/[^/]+/Actions/[^/]+\.php$#', $relative) === 1) {
                    $discovered[] = $relative;
                }
            }
        }

        sort($discovered);

        return $discovered;
    }

    /** @param array<string, mixed> $entry */
    private function assertEntryStructure(string $action, array $entry): void
    {
        $this->assertSame(
            [
                'actionClass',
                'actionReference',
                'actionInvocation',
                'testFile',
                'testMethod',
                'failureTrigger',
                'failureExpectation',
                'rollbackAssertions',
            ],
            array_keys($entry),
            $action.' has an incomplete rollback-map entry.',
        );

        $expectedClass = str_replace('/', '\\', substr($action, 0, -4));
        $expectedClass = preg_replace('/^app\\\\/', 'App\\', $expectedClass);
        $shortName = substr($expectedClass, strrpos($expectedClass, '\\') + 1);

        $this->assertSame($expectedClass, $entry['actionClass']);
        $this->assertSame($shortName.'::class', $entry['actionReference']);
        $this->assertIsArray($entry['actionInvocation']);
        $this->assertSame(['mode', 'variable', 'method'], array_keys($entry['actionInvocation']));
        $this->assertContains($entry['actionInvocation']['mode'], ['assigned-new', 'assigned-container']);
        $this->assertMatchesRegularExpression('/^\$[a-z][a-zA-Z0-9_]*$/', $entry['actionInvocation']['variable']);
        $this->assertContains($entry['actionInvocation']['method'], ['execute', 'handle', '__invoke']);
        $this->assertMatchesRegularExpression('#^tests/(?:Accounting|Feature)/.+Test\.php$#', $entry['testFile']);
        $this->assertMatchesRegularExpression('/^test_[a-z0-9_]+$/', $entry['testMethod']);
        $this->assertContains($entry['failureTrigger'], self::ALLOWED_FAILURE_TRIGGERS);
        $this->assertContains($entry['failureExpectation'], self::ALLOWED_FAILURE_EXPECTATIONS);
        $this->assertIsArray($entry['rollbackAssertions']);
        $this->assertGreaterThanOrEqual(2, count(array_unique($entry['rollbackAssertions'])));

        foreach ($entry['rollbackAssertions'] as $assertion) {
            $this->assertContains($assertion, self::ALLOWED_ROLLBACK_ASSERTIONS);
        }
    }

    /** @param array<string, mixed> $entry */
    private function assertMethodProvesRollback(string $methodBody, array $entry): void
    {
        $code = $this->executableCode($methodBody);

        $this->assertMatchesRegularExpression($this->markerPattern($entry['actionReference']), $code);
        $this->assertActionInvocation($code, $entry);
        $this->assertMatchesRegularExpression($this->markerPattern($entry['failureTrigger']), $code);
        $this->assertMatchesRegularExpression($this->markerPattern($entry['failureExpectation']), $code);

        foreach ($entry['rollbackAssertions'] as $assertion) {
            $this->assertMatchesRegularExpression($this->markerPattern($assertion), $code);
        }
    }

    /** @param array<string, mixed> $entry */
    private function assertActionInvocation(string $code, array $entry): void
    {
        $shortName = substr($entry['actionReference'], 0, -7);
        $invocation = $entry['actionInvocation'];
        $variable = preg_quote($invocation['variable'], '/');

        if ($invocation['mode'] === 'assigned-new') {
            $resolution = '/'.$variable.'\s*=\s*new\s+'.preg_quote($shortName, '/').'\s*\(/i';
        } else {
            $classReference = preg_quote($shortName, '/').'\s*::\s*class';
            $resolution = '/'.$variable.'\s*=\s*(?:app|resolve)\s*\(\s*'.$classReference.'\s*\)/i';
        }

        $this->assertMatchesRegularExpression($resolution, $code);

        $method = $invocation['method'];
        $call = $method === '__invoke'
            ? '/'.$variable.'\s*\(/'
            : '/'.$variable.'\s*->\s*'.preg_quote($method, '/').'\s*\(/i';
        $this->assertMatchesRegularExpression($call, $code);
    }

    private function markerPattern(string $marker): string
    {
        if ($marker === 'throw new ') {
            return '/\bthrow\s+new\s+/i';
        }
        if (str_ends_with($marker, '::class')) {
            return '/\b'.preg_quote(substr($marker, 0, -7), '/').'\s*::\s*class\b/';
        }

        $method = rtrim($marker, '(');
        $prefix = str_starts_with($method, '->') ? '->\s*' : '\b';
        $method = ltrim($method, '->');

        return '/'.$prefix.preg_quote($method, '/').'\s*\(/i';
    }

    private function executableCode(string $methodBody): string
    {
        $code = '';
        foreach (token_get_all('<?php '.$methodBody) as $token) {
            if (is_array($token) && in_array($token[0], [
                T_OPEN_TAG,
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

    private function extractMethodBody(string $contents, string $method): ?string
    {
        $tokens = token_get_all($contents);
        $count = count($tokens);

        for ($index = 0; $index < $count; $index++) {
            if (! is_array($tokens[$index]) || $tokens[$index][0] !== T_FUNCTION) {
                continue;
            }

            do {
                $index++;
            } while ($index < $count && (! is_array($tokens[$index]) || $tokens[$index][0] !== T_STRING));

            if ($index >= $count || $tokens[$index][1] !== $method) {
                continue;
            }

            while ($index < $count && $tokens[$index] !== '{') {
                $index++;
            }

            $body = '';
            $depth = 0;
            for (; $index < $count; $index++) {
                $token = $tokens[$index];
                $text = is_array($token) ? $token[1] : $token;
                if ($text === '{') {
                    $depth++;
                    if ($depth === 1) {
                        continue;
                    }
                } elseif ($text === '}') {
                    $depth--;
                    if ($depth === 0) {
                        return $body;
                    }
                }

                if ($depth >= 1) {
                    $body .= $text;
                }
            }
        }

        return null;
    }

    /** @return array<string, mixed> */
    private function validSyntheticEntry(): array
    {
        return [
            'actionClass' => 'App\\Domain\\Synthetic\\Actions\\SyntheticWrite',
            'actionReference' => 'SyntheticWrite::class',
            'actionInvocation' => [
                'mode' => 'assigned-container',
                'variable' => '$action',
                'method' => 'execute',
            ],
            'testFile' => 'tests/Feature/Synthetic/SyntheticWriteRollbackTest.php',
            'testMethod' => 'test_failure_rolls_back_the_complete_write',
            'failureTrigger' => 'andThrow(',
            'failureExpectation' => 'expectException(',
            'rollbackAssertions' => ['assertDatabaseHas(', 'assertDatabaseMissing('],
        ];
    }
}
