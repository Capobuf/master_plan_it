<?php

namespace Tests\Architecture;

use PHPUnit\Framework\TestCase;

class DependencyContractTest extends TestCase
{
    public function test_platform_dependencies_are_exactly_constrained(): void
    {
        $composer = json_decode(
            file_get_contents(dirname(__DIR__, 2).'/composer.json'),
            true,
            flags: JSON_THROW_ON_ERROR,
        );

        $this->assertSame('8.3.32', $composer['config']['platform']['php']);

        $expected = [
            'php' => '^8.3',
            'ext-bcmath' => '*',
            'laravel/framework' => '13.22.0',
            'laravel/sanctum' => '4.3.3',
            'laravel/tinker' => '3.0.2',
            'openspout/openspout' => '4.32.0',
            'overtrue/laravel-versionable' => '6.0.0',
            'spatie/laravel-permission' => '8.3.0',
        ];

        $expectedDev = [
            'fakerphp/faker' => '1.24.1',
            'larastan/larastan' => '3.10.0',
            'laravel/pail' => '1.2.7',
            'laravel/pao' => '1.1.3',
            'laravel/pint' => '1.30.3',
            'laravel/sail' => '1.64.0',
            'mockery/mockery' => '1.6.12',
            'nunomaduro/collision' => '8.9.5',
            'pestphp/pest' => '4.7.8',
            'pestphp/pest-plugin-laravel' => '4.1.0',
            'phpstan/phpstan' => '2.2.7',
            'phpunit/phpunit' => '12.5.33',
        ];

        $actual = $composer['require'];
        $actualDev = $composer['require-dev'];
        ksort($expected);
        ksort($expectedDev);
        ksort($actual);
        ksort($actualDev);

        $this->assertSame($expected, $actual);
        $this->assertSame($expectedDev, $actualDev);

        foreach (array_merge($composer['require'], $composer['require-dev']) as $package => $constraint) {
            if ($package === 'php' || str_starts_with($package, 'ext-')) {
                continue;
            }

            $this->assertDoesNotMatchRegularExpression('/[~^*|<>]/', $constraint, $package);
        }

        $lock = json_decode(
            file_get_contents(dirname(__DIR__, 2).'/composer.lock'),
            true,
            flags: JSON_THROW_ON_ERROR,
        );
        $lockedNames = array_column(
            array_merge($lock['packages'], $lock['packages-dev']),
            'name',
        );

        $this->assertSame(
            [],
            array_values(array_intersect(
                ['predis/predis', 'laravel/horizon', 'dompdf/dompdf', 'barryvdh/laravel-dompdf', 'knplabs/knp-snappy'],
                $lockedNames,
            )),
            'Forbidden worker, Redis, or server-side PDF dependency is locked.',
        );
    }
}
