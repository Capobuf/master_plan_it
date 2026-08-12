<?php

namespace Tests\Architecture;

use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;

final class EconomicCoverageGateTest extends TestCase
{
    public function test_the_versioned_manifest_names_only_loadable_pure_economic_classes(): void
    {
        $manifest = require dirname(__DIR__).'/Support/economic-coverage-classes.php';

        $this->assertNotEmpty($manifest);
        foreach ($manifest as $class) {
            $this->assertTrue(class_exists($class), sprintf('Missing mandatory economic coverage class [%s].', $class));
        }
    }

    #[DataProvider('invalidCoberturaReports')]
    public function test_a_missing_class_or_incomplete_metric_is_rejected_by_the_gate(string $report, string $expected): void
    {
        $fixture = tempnam(sys_get_temp_dir(), 'economic-coverage-');
        file_put_contents($fixture, $report);

        $script = dirname(__DIR__).'/Support/assert-economic-coverage.php';
        $this->assertFileExists($script);
        exec(sprintf('php %s %s 2>&1', escapeshellarg($script), escapeshellarg($fixture)), $output, $status);
        unlink($fixture);

        $this->assertNotSame(0, $status);
        $this->assertStringContainsString($expected, implode("\n", $output));
    }

    /** @return array<string, array{string, string}> */
    public static function invalidCoberturaReports(): array
    {
        $classes = require dirname(__DIR__).'/Support/economic-coverage-classes.php';
        $complete = static function (array $overrides = []) use ($classes): string {
            $nodes = [];
            foreach ($classes as $class) {
                $rate = $overrides[$class] ?? ['line' => '1', 'branch' => '1'];
                $attributes = sprintf('name="%s" filename="%s.php" line-rate="%s" branch-rate="%s"', htmlspecialchars($class, ENT_XML1), htmlspecialchars(str_replace('\\', '/', $class), ENT_XML1), $rate['line'] ?? '', $rate['branch'] ?? '');
                $nodes[] = "<class {$attributes}><methods/><lines/></class>";
            }

            return '<coverage><packages><package name="App"><classes>'.implode('', $nodes).'</classes></package></packages></coverage>';
        };

        return [
            'missing manifest class' => ['<coverage><packages/></coverage>', 'Missing mandatory class'],
            'missing branch metric' => [$complete([$classes[0] => ['line' => '1', 'branch' => null]]), 'branch'],
            'line metric below threshold' => [$complete([$classes[0] => ['line' => '0.99', 'branch' => '1']]), 'line'],
        ];
    }
}
