<?php

namespace Tests\Architecture;

use PHPUnit\Framework\AssertionFailedError;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;

class DevelopmentContractTest extends TestCase
{
    private const FORBIDDEN_DATABASE_PATTERNS = [
        'Refresh'.'Database',
        'Lazily'.'Refresh'.'Database',
        'Database'.'Migrations',
        'Database'.'Truncation',
        'migrate:'.'fresh',
        'db:'.'wipe',
        'DROP '.'DATABASE',
        'DROP '.'TABLE',
        'TRUNCATE'.' TABLE',
        '::truncate'.'(',
        '->truncate'.'(',
        'dropAll'.'Tables',
        'dropAll'.'Views',
        'dropAll'.'Types',
    ];

    private const FORBIDDEN_TEST_BYPASS_PATTERNS = [
        'markTest'.'Skipped',
        '->skip'.'(',
        '->todo'.'(',
        '->only'.'(',
    ];

    public function test_quality_dependencies_and_scripts_are_exact_and_non_destructive(): void
    {
        $root = dirname(__DIR__, 2);
        $composer = json_decode(
            file_get_contents($root.'/composer.json'),
            true,
            flags: JSON_THROW_ON_ERROR,
        );

        $this->assertSame(
            [
                'larastan/larastan' => '3.10.0',
                'pestphp/pest' => '4.7.8',
                'pestphp/pest-plugin-laravel' => '4.1.0',
                'phpstan/phpstan' => '2.2.7',
            ],
            array_intersect_key(
                $composer['require-dev'],
                array_flip([
                    'larastan/larastan',
                    'pestphp/pest',
                    'pestphp/pest-plugin-laravel',
                    'phpstan/phpstan',
                ]),
            ),
        );

        $requiredScripts = [
            'test:prepare',
            'test:static',
            'test:accounting',
            'test:application',
            'verify',
        ];

        foreach ($requiredScripts as $script) {
            $this->assertArrayHasKey($script, $composer['scripts']);
            $this->assertNotSame([], (array) $composer['scripts'][$script]);
        }
        $scripts = json_encode($composer['scripts'], JSON_THROW_ON_ERROR);
        $this->assertSame([], $this->matchingPatterns($scripts, self::FORBIDDEN_DATABASE_PATTERNS));

        $prepare = implode("\n", (array) $composer['scripts']['test:prepare']);
        $this->assertStringContainsString('copy(".env.testing.example", ".env.testing")', $prepare);
        $this->assertStringContainsString('APP_ENV=testing', $prepare);
        $this->assertStringContainsString('DB_URL', $prepare);
        $this->assertStringContainsString('master_plan_it_test', $prepare);
        $this->assertStringContainsString('$connection->getConfig("host") === "mysql"', $prepare);
        $this->assertStringContainsString('STRICT_TRANS_TABLES', $prepare);
        $this->assertStringContainsString('artisan migrate --env=testing --force', $prepare);
    }

    public function test_phpunit_uses_the_persistent_mysql_test_database(): void
    {
        $xml = simplexml_load_file(dirname(__DIR__, 2).'/phpunit.xml');
        $this->assertNotFalse($xml);

        $environment = [];
        foreach ($xml->php->env as $entry) {
            $environment[(string) $entry['name']] = (string) $entry['value'];
        }

        $this->assertSame('testing', $environment['APP_ENV'] ?? null);
        $this->assertSame('mysql', $environment['DB_CONNECTION'] ?? null);
        $this->assertSame('mysql', $environment['DB_HOST'] ?? null);
        $this->assertSame('master_plan_it_test', $environment['DB_DATABASE'] ?? null);
        $this->assertSame('', $environment['DB_URL'] ?? null);
        $this->assertSame('database', $environment['SESSION_DRIVER'] ?? null);
        $this->assertNotContains(':memory:', $environment);
        $this->assertNotContains('sqlite', $environment);

        foreach ($xml->php->env as $entry) {
            if (in_array((string) $entry['name'], [
                'APP_ENV',
                'DB_URL',
                'DB_CONNECTION',
                'DB_HOST',
                'DB_PORT',
                'DB_DATABASE',
                'DB_USERNAME',
                'DB_PASSWORD',
                'SESSION_DRIVER',
            ], true)) {
                $this->assertSame('true', (string) $entry['force']);
            }
        }

        $suiteDirectories = [];
        foreach ($xml->testsuites->testsuite as $suite) {
            foreach ($suite->directory as $directory) {
                $suiteDirectories[] = (string) $directory;
            }
        }

        $this->assertContains('tests/Architecture', $suiteDirectories);
        $this->assertContains('tests/Accounting', $suiteDirectories);
        $this->assertContains('tests/Feature', $suiteDirectories);
    }

    public function test_static_configuration_and_code_ownership_are_blocking(): void
    {
        $root = dirname(__DIR__, 2);

        foreach (['phpstan.neon', 'pint.json', '.github/CODEOWNERS'] as $path) {
            $this->assertFileExists($root.'/'.$path);
        }

        $phpstan = file_get_contents($root.'/phpstan.neon');
        $this->assertStringContainsString('vendor/larastan/larastan/extension.neon', $phpstan);
        $this->assertMatchesRegularExpression('/level:\s*(?:[6-9]|max)/', $phpstan);

        $codeowners = file_get_contents($root.'/.github/CODEOWNERS');
        $this->assertStringContainsString('/tests/Accounting/', $codeowners);
        $this->assertStringContainsString('/.github/workflows/', $codeowners);
    }

    #[DataProvider('mandatorySourceFiles')]
    public function test_mandatory_tests_are_not_skipped_focused_or_empty(string $file): void
    {
        $contents = $this->phpWithoutComments(file_get_contents($file));

        $this->assertSame([], $this->matchingPatterns($contents, self::FORBIDDEN_TEST_BYPASS_PATTERNS), $file);
        $this->assertSame([], $this->tautologicalAssertions($contents), $file);

        $units = $this->extractTestUnits($contents);
        $this->assertNotSame([], $units, $file.' contains no PHPUnit test method or Pest test closure.');
        foreach ($units as $label => $body) {
            $this->assertTestUnitHasObservableAssertion($file.'::'.$label, $body);
        }
    }

    /** @return iterable<string, array{string}> */
    public static function mandatorySourceFiles(): iterable
    {
        $root = dirname(__DIR__, 2);
        $iterator = new \RecursiveIteratorIterator(new \RecursiveDirectoryIterator($root.'/tests'));

        foreach ($iterator as $file) {
            if (! $file->isFile() || ! str_ends_with($file->getFilename(), 'Test.php')) {
                continue;
            }

            yield $file->getPathname() => [$file->getPathname()];
        }
    }

    public function test_test_code_and_quality_scripts_contain_no_destructive_database_reset(): void
    {
        $root = dirname(__DIR__, 2);
        $paths = [$root.'/composer.json'];
        $iterator = new \RecursiveIteratorIterator(new \RecursiveDirectoryIterator($root.'/tests'));

        foreach ($iterator as $file) {
            if ($file->isFile() && $file->getExtension() === 'php') {
                $paths[] = $file->getPathname();
            }
        }

        foreach ($paths as $path) {
            $contents = $path === $root.'/composer.json'
                ? file_get_contents($path)
                : $this->scannablePhp(file_get_contents($path));
            $this->assertSame([], $this->matchingPatterns($contents, self::FORBIDDEN_DATABASE_PATTERNS), $path);
        }
    }

    public function test_destructive_and_bypass_guards_are_case_insensitive_negative_controls(): void
    {
        $destructiveFixture = '<?php Artisan::call(\'MiGrAtE:'.'FrEsH\'); use ReFrEsH'.'DaTaBaSe;';
        $bypassFixture = '<?php $test->MaRkTeSt'.'SkIpPeD(); test(\'x\', fn () => true)->OnLy'.'();';

        $this->assertSame(
            ['Refresh'.'Database', 'migrate:'.'fresh'],
            $this->matchingPatterns($destructiveFixture, self::FORBIDDEN_DATABASE_PATTERNS),
        );
        $this->assertSame(
            ['markTest'.'Skipped', '->only'.'('],
            $this->matchingPatterns($bypassFixture, self::FORBIDDEN_TEST_BYPASS_PATTERNS),
        );

        $registered = array_keys(iterator_to_array(self::mandatorySourceFiles()));
        $this->assertContains(__FILE__, $registered);
        $this->assertContains(dirname(__DIR__).'/Architecture/DevelopmentEnvironmentTest.php', $registered);
    }

    public function test_tautological_assertion_guard_is_a_negative_control(): void
    {
        $fixture = '<?php $this->AsSeRtTr'.'Ue( true ); expect(false)->toBeFa'.'lse();';

        $this->assertSame(
            ['assertTrue'.'(true)', 'expect(false)->toBeFalse'.'()'],
            $this->tautologicalAssertions($fixture),
        );
    }

    public function test_each_phpunit_method_and_pest_closure_needs_its_own_observable_assertion(): void
    {
        $fixture = <<<'PHP'
<?php
class EmbeddedTest {
    public function test_observable(): void { $this->assertSame(1, 1); }
    public function test_empty(): void { $value = 1; }
}
test('pest observable', fn () => expect(1)->toBe(1));
it('pest empty', function (): void { $value = 1; });
PHP;

        $units = $this->extractTestUnits($fixture);
        $this->assertSame(
            ['PHPUnit::test_observable', 'PHPUnit::test_empty', 'Pest::pest observable', 'Pest::pest empty'],
            array_keys($units),
        );

        $this->assertTestUnitHasObservableAssertion('PHPUnit::test_observable', $units['PHPUnit::test_observable']);
        $this->assertTestUnitHasObservableAssertion('Pest::pest observable', $units['Pest::pest observable']);

        foreach (['PHPUnit::test_empty', 'Pest::pest empty'] as $empty) {
            $rejected = false;
            try {
                $this->assertTestUnitHasObservableAssertion($empty, $units[$empty]);
            } catch (AssertionFailedError) {
                $rejected = true;
            }
            $this->assertTrue($rejected, $empty.' unexpectedly passed without an assertion.');
        }
    }

    public function test_phpunit_test_attributes_are_resolved_without_classifying_public_helpers(): void
    {
        $imported = <<<'PHP'
<?php
use PHPUnit\Framework\Attributes\Test;
class ImportedAttributeTest {
    #[Test]
    public function attribute_empty(): void { $value = 1; }
    #[\PHPUnit\Framework\Attributes\Test]
    public function fqcn_observable(): void { $this->assertSame(1, 1); }
    public function public_helper(): void { $this->assertSame(1, 1); }
    public function test_named_observable(): void { $this->assertSame(1, 1); }
}
PHP;
        $aliased = <<<'PHP'
<?php
use PHPUnit\Framework\Attributes\Test as PHPUnitTest;
class AliasedAttributeTest {
    #[PHPUnitTest]
    public function alias_observable(): void { $this->assertSame(1, 1); }
}
PHP;

        $units = array_merge($this->extractTestUnits($imported), $this->extractTestUnits($aliased));
        $this->assertSame(
            [
                'PHPUnit::attribute_empty',
                'PHPUnit::fqcn_observable',
                'PHPUnit::test_named_observable',
                'PHPUnit::alias_observable',
            ],
            array_keys($units),
        );
        $this->assertArrayNotHasKey('PHPUnit::public_helper', $units);

        foreach (['PHPUnit::fqcn_observable', 'PHPUnit::test_named_observable', 'PHPUnit::alias_observable'] as $observable) {
            $this->assertTestUnitHasObservableAssertion($observable, $units[$observable]);
        }

        $rejected = false;
        try {
            $this->assertTestUnitHasObservableAssertion(
                'PHPUnit::attribute_empty',
                $units['PHPUnit::attribute_empty'],
            );
        } catch (AssertionFailedError) {
            $rejected = true;
        }
        $this->assertTrue($rejected, 'An empty #[Test] method unexpectedly passed.');
    }

    public function test_authoritative_domain_code_contains_no_float_arithmetic(): void
    {
        $root = dirname(__DIR__, 2);
        $directories = [
            'app/Domain/Money',
            'app/Domain/Economics',
            'app/Domain/Expenses',
            'app/Domain/Contracts',
            'app/Domain/Reporting',
        ];

        foreach ($directories as $directory) {
            if (! is_dir($root.'/'.$directory)) {
                continue;
            }

            $iterator = new \RecursiveIteratorIterator(new \RecursiveDirectoryIterator($root.'/'.$directory));
            foreach ($iterator as $file) {
                if (! $file->isFile() || $file->getExtension() !== 'php') {
                    continue;
                }

                $contents = file_get_contents($file->getPathname());
                $this->assertDoesNotMatchRegularExpression(
                    '/(?:\bfloat\b|\(float\)|floatval\s*\()/i',
                    $contents,
                    $file->getPathname(),
                );
            }
        }

        $this->addToAssertionCount(1);
    }

    private function phpWithoutComments(string $contents): string
    {
        $code = '';
        foreach (token_get_all($contents) as $token) {
            if (is_array($token) && in_array($token[0], [T_COMMENT, T_DOC_COMMENT], true)) {
                continue;
            }

            $code .= is_array($token) ? $token[1] : $token;
        }

        return $code;
    }

    private function scannablePhp(string $contents): string
    {
        $contents = preg_replace(
            '~(assertStringNotContainsString\s*\(\s*)([\'\"])(?:\\\\.|(?!\2).)*\2(\s*,)~is',
            '$1\'\'$3',
            $contents,
        ) ?? $contents;

        return $this->phpWithoutComments($contents);
    }

    /** @param list<string> $patterns
     * @return list<string>
     */
    private function matchingPatterns(string $contents, array $patterns): array
    {
        return array_values(array_filter(
            $patterns,
            fn (string $pattern): bool => stripos($contents, $pattern) !== false,
        ));
    }

    /** @return list<string> */
    private function tautologicalAssertions(string $contents): array
    {
        $patterns = [
            'assertTrue'.'(true)' => '/\bassertTrue\s*\(\s*true\s*\)/i',
            'assertFalse'.'(false)' => '/\bassertFalse\s*\(\s*false\s*\)/i',
            'assertSame'.'(literal, literal)' => '/\bassertSame\s*\(\s*(true|false|null)\s*,\s*\1\s*\)/i',
            'expect(true)->toBeTrue'.'()' => '/\bexpect\s*\(\s*true\s*\)\s*->\s*toBeTrue\s*\(\s*\)/i',
            'expect(false)->toBeFalse'.'()' => '/\bexpect\s*\(\s*false\s*\)\s*->\s*toBeFalse\s*\(\s*\)/i',
            'expect(literal)->toBe'.'(literal)' => '/\bexpect\s*\(\s*(true|false|null)\s*\)\s*->\s*toBe\s*\(\s*\1\s*\)/i',
        ];

        return array_keys(array_filter(
            $patterns,
            fn (string $pattern): bool => preg_match($pattern, $contents) === 1,
        ));
    }

    /** @return array<string, string> */
    private function extractTestUnits(string $contents): array
    {
        $tokens = token_get_all($contents);
        $testAttributeAliases = $this->phpunitTestAttributeAliases($contents);
        $units = [];
        $count = count($tokens);

        for ($index = 0; $index < $count; $index++) {
            $token = $tokens[$index];

            if (is_array($token) && $token[0] === T_FUNCTION) {
                $nameIndex = $this->nextTokenOfType($tokens, $index + 1, T_STRING);
                $isNamedTest = $nameIndex !== null && str_starts_with($tokens[$nameIndex][1], 'test_');
                $isAttributedTest = $nameIndex !== null
                    && $this->hasPhpunitTestAttribute($tokens, $index, $testAttributeAliases);

                if ($nameIndex !== null && ($isNamedTest || $isAttributedTest)) {
                    $visibility = $this->methodVisibility($tokens, $index);
                    if ($visibility === T_PRIVATE || $visibility === T_PROTECTED) {
                        continue;
                    }

                    [$body, $end] = $this->bracedBody($tokens, $nameIndex);
                    $units['PHPUnit::'.$tokens[$nameIndex][1]] = $body;
                    $index = $end;
                }

                continue;
            }

            if (! is_array($token) || $token[0] !== T_STRING || ! in_array(strtolower($token[1]), ['test', 'it'], true)) {
                continue;
            }

            $previous = $this->previousSignificantToken($tokens, $index - 1);
            if (is_array($previous) && in_array($previous[0], [T_OBJECT_OPERATOR, T_DOUBLE_COLON], true)) {
                continue;
            }

            $open = $this->nextSignificantIndex($tokens, $index + 1);
            if ($open === null || $tokens[$open] !== '(') {
                continue;
            }

            $labelIndex = $this->nextSignificantIndex($tokens, $open + 1);
            if ($labelIndex === null || ! is_array($tokens[$labelIndex]) || $tokens[$labelIndex][0] !== T_CONSTANT_ENCAPSED_STRING) {
                continue;
            }
            $label = stripcslashes(substr($tokens[$labelIndex][1], 1, -1));

            $closureIndex = $this->nextClosureIndex($tokens, $labelIndex + 1);
            if ($closureIndex === null) {
                continue;
            }

            if ($tokens[$closureIndex][0] === T_FUNCTION) {
                [$body, $end] = $this->bracedBody($tokens, $closureIndex);
            } else {
                [$body, $end] = $this->arrowBody($tokens, $closureIndex, $open);
            }

            $key = 'Pest::'.$label;
            for ($suffix = 2; array_key_exists($key, $units); $suffix++) {
                $key = 'Pest::'.$label.'#'.$suffix;
            }
            $units[$key] = $body;
            $index = $end;
        }

        return $units;
    }

    /** @return list<string> */
    private function phpunitTestAttributeAliases(string $contents): array
    {
        $aliases = [];
        preg_match_all(
            '/\buse\s+PHPUnit\\\\Framework\\\\Attributes\\\\Test(?:\s+as\s+([A-Za-z_][A-Za-z0-9_]*))?\s*;/i',
            $contents,
            $directImports,
            PREG_SET_ORDER,
        );
        foreach ($directImports as $import) {
            $aliases[] = strtolower($import[1] ?? 'Test');
        }

        preg_match_all(
            '/\buse\s+PHPUnit\\\\Framework\\\\Attributes\\\\\{([^}]+)\}\s*;/is',
            $contents,
            $groupImports,
            PREG_SET_ORDER,
        );
        foreach ($groupImports as $import) {
            foreach (explode(',', $import[1]) as $member) {
                if (preg_match('/^\s*Test(?:\s+as\s+([A-Za-z_][A-Za-z0-9_]*))?\s*$/i', $member, $match) === 1) {
                    $aliases[] = strtolower($match[1] ?? 'Test');
                }
            }
        }

        return array_values(array_unique($aliases));
    }

    /** @param list<mixed> $tokens
     * @param  list<string>  $aliases
     */
    private function hasPhpunitTestAttribute(array $tokens, int $functionIndex, array $aliases): bool
    {
        foreach ($this->methodAttributeNames($tokens, $functionIndex) as $attribute) {
            $normalized = strtolower(ltrim($attribute, '\\'));
            if ($normalized === 'phpunit\\framework\\attributes\\test' || in_array($normalized, $aliases, true)) {
                return true;
            }
        }

        return false;
    }

    /** @param list<mixed> $tokens
     * @return list<string>
     */
    private function methodAttributeNames(array $tokens, int $functionIndex): array
    {
        $start = $functionIndex - 1;
        while ($start >= 0 && ! in_array($tokens[$start], [';', '{', '}'], true)) {
            $start--;
        }

        $attributes = [];
        for ($index = $start + 1; $index < $functionIndex; $index++) {
            if (! is_array($tokens[$index]) || $tokens[$index][0] !== T_ATTRIBUTE) {
                continue;
            }

            $parentheses = 0;
            $expectName = true;
            for ($index++; $index < $functionIndex; $index++) {
                $token = $tokens[$index];
                if ($token === ']' && $parentheses === 0) {
                    break;
                }
                if ($token === '(') {
                    $parentheses++;

                    continue;
                }
                if ($token === ')') {
                    $parentheses--;

                    continue;
                }
                if ($token === ',' && $parentheses === 0) {
                    $expectName = true;

                    continue;
                }
                if (
                    $expectName
                    && $parentheses === 0
                    && is_array($token)
                    && in_array($token[0], [T_STRING, T_NAME_QUALIFIED, T_NAME_FULLY_QUALIFIED], true)
                ) {
                    $attributes[] = $token[1];
                    $expectName = false;
                }
            }
        }

        return $attributes;
    }

    private function assertTestUnitHasObservableAssertion(string $label, string $body): void
    {
        $body = $this->phpWithoutComments($body);
        $this->assertSame([], $this->tautologicalAssertions($body), $label);

        $code = $this->phpWithoutCommentsAndStrings($body);
        $this->assertMatchesRegularExpression(
            '/(?:\bassert[A-Z][A-Za-z0-9_]*\s*\(|\bexpect\s*\(|\bexpectException[A-Za-z0-9_]*\s*\(|\barch\s*\(|\bfail\s*\(|->\s*throws\s*\()/i',
            $code,
            $label.' contains no observable assertion.',
        );
    }

    /** @param list<mixed> $tokens */
    private function nextTokenOfType(array $tokens, int $index, int $type): ?int
    {
        for ($count = count($tokens); $index < $count; $index++) {
            if (is_array($tokens[$index]) && $tokens[$index][0] === $type) {
                return $index;
            }
            if ($tokens[$index] === '(' || $tokens[$index] === '{') {
                return null;
            }
        }

        return null;
    }

    /** @param list<mixed> $tokens */
    private function methodVisibility(array $tokens, int $functionIndex): ?int
    {
        for ($index = $functionIndex - 1; $index >= 0; $index--) {
            $token = $tokens[$index];
            if ($token === ';' || $token === '{' || $token === '}') {
                return null;
            }
            if (is_array($token) && in_array($token[0], [T_PUBLIC, T_PROTECTED, T_PRIVATE], true)) {
                return $token[0];
            }
        }

        return null;
    }

    /** @param list<mixed> $tokens
     * @return array{string, int}
     */
    private function bracedBody(array $tokens, int $start): array
    {
        $count = count($tokens);
        while ($start < $count && $tokens[$start] !== '{') {
            $start++;
        }

        $body = '';
        $depth = 0;
        for ($index = $start; $index < $count; $index++) {
            $text = is_array($tokens[$index]) ? $tokens[$index][1] : $tokens[$index];
            if ($text === '{') {
                $depth++;
                if ($depth === 1) {
                    continue;
                }
            } elseif ($text === '}') {
                $depth--;
                if ($depth === 0) {
                    return [$body, $index];
                }
            }

            if ($depth >= 1) {
                $body .= $text;
            }
        }

        return [$body, $count - 1];
    }

    /** @param list<mixed> $tokens */
    private function nextClosureIndex(array $tokens, int $index): ?int
    {
        $depth = 1;
        for ($count = count($tokens); $index < $count; $index++) {
            $token = $tokens[$index];
            if ($token === '(') {
                $depth++;
            } elseif ($token === ')') {
                $depth--;
                if ($depth === 0) {
                    return null;
                }
            } elseif (is_array($token) && in_array($token[0], [T_FUNCTION, T_FN], true)) {
                return $index;
            }
        }

        return null;
    }

    /** @param list<mixed> $tokens
     * @return array{string, int}
     */
    private function arrowBody(array $tokens, int $closureIndex, int $callOpen): array
    {
        $count = count($tokens);
        $arrow = $closureIndex;
        while ($arrow < $count && (! is_array($tokens[$arrow]) || $tokens[$arrow][0] !== T_DOUBLE_ARROW)) {
            $arrow++;
        }

        $depth = 0;
        for ($index = $callOpen; $index <= $arrow; $index++) {
            $depth += $tokens[$index] === '(' ? 1 : 0;
            $depth -= $tokens[$index] === ')' ? 1 : 0;
        }

        $body = '';
        for ($index = $arrow + 1; $index < $count; $index++) {
            $token = $tokens[$index];
            if ($token === '(') {
                $depth++;
            } elseif ($token === ')') {
                $depth--;
                if ($depth === 0) {
                    return [$body, $index];
                }
            }
            $body .= is_array($token) ? $token[1] : $token;
        }

        return [$body, $count - 1];
    }

    /** @param list<mixed> $tokens */
    private function nextSignificantIndex(array $tokens, int $index): ?int
    {
        for ($count = count($tokens); $index < $count; $index++) {
            if (! is_array($tokens[$index]) || ! in_array($tokens[$index][0], [T_WHITESPACE, T_COMMENT, T_DOC_COMMENT], true)) {
                return $index;
            }
        }

        return null;
    }

    /** @param list<mixed> $tokens */
    private function previousSignificantToken(array $tokens, int $index): mixed
    {
        for (; $index >= 0; $index--) {
            if (! is_array($tokens[$index]) || ! in_array($tokens[$index][0], [T_WHITESPACE, T_COMMENT, T_DOC_COMMENT], true)) {
                return $tokens[$index];
            }
        }

        return null;
    }

    private function phpWithoutCommentsAndStrings(string $contents): string
    {
        $code = '';
        foreach (token_get_all('<?php '.$contents) as $token) {
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
}
