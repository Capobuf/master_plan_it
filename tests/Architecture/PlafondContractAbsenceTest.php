<?php

namespace Tests\Architecture;

use PHPUnit\Framework\TestCase;

final class PlafondContractAbsenceTest extends TestCase
{
    public function test_deprecated_plafond_contract_vocabulary_is_absent_from_maintained_sources(): void
    {
        $root = dirname(__DIR__, 2);
        $files = [
            ...$this->files($root.'/app', ['php']),
            ...$this->files($root.'/frontend/src', ['ts', 'tsx']),
        ];
        $violations = [];
        foreach ($files as $file) {
            $contents = file_get_contents($file);
            if (! is_string($contents)) {
                continue;
            }
            foreach (['coverage_allocations', 'plafond_overrun', 'global_plafond_overrun', 'Completa Copertura', 'Sforamento'] as $forbidden) {
                if (str_contains($contents, $forbidden)) {
                    $violations[] = str_replace($root.'/', '', $file).': '.$forbidden;
                }
            }
        }

        $this->assertSame([], $violations, implode("\n", $violations));

        $budget = file_get_contents($root.'/app/Domain/Budget/Queries/AnnualBudgetQuery.php');
        $this->assertIsString($budget);
        $this->assertStringContainsString("'residual'", $budget);
    }

    /**
     * @param  list<string>  $extensions
     * @return list<string>
     */
    private function files(string $directory, array $extensions): array
    {
        $files = [];
        $iterator = new \RecursiveIteratorIterator(new \RecursiveDirectoryIterator($directory));
        foreach ($iterator as $file) {
            if ($file->isFile() && in_array($file->getExtension(), $extensions, true)) {
                $files[] = $file->getPathname();
            }
        }

        sort($files);

        return $files;
    }
}
