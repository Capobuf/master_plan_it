<?php

namespace Tests\Architecture;

use PHPUnit\Framework\TestCase;

class FrontendStackContractTest extends TestCase
{
    public function test_operational_frontend_dependencies_are_exact_and_locally_built(): void
    {
        $root = dirname(__DIR__, 2);
        $package = json_decode((string) file_get_contents($root.'/package.json'), true, flags: JSON_THROW_ON_ERROR);
        $lock = json_decode((string) file_get_contents($root.'/package-lock.json'), true, flags: JSON_THROW_ON_ERROR);

        $this->assertSame(['chart.js' => '4.5.1', 'preline' => '4.2.0'], $package['dependencies'] ?? null);
        $this->assertSame([
            '@tailwindcss/forms' => '0.5.11',
            '@tailwindcss/vite' => '4.2.4',
            'laravel-vite-plugin' => '1.3.0',
            'tailwindcss' => '4.2.4',
            'vite' => '6.4.3',
        ], $package['devDependencies'] ?? null);
        $this->assertSame('4.2.0', $lock['packages']['node_modules/preline']['version'] ?? null);
        $this->assertSame('4.2.4', $lock['packages']['node_modules/tailwindcss']['version'] ?? null);
        $this->assertSame('4.2.4', $lock['packages']['node_modules/@tailwindcss/vite']['version'] ?? null);
        $this->assertSame('0.5.11', $lock['packages']['node_modules/@tailwindcss/forms']['version'] ?? null);

        $assets = implode("\n", [
            (string) file_get_contents($root.'/resources/css/app.css'),
            (string) file_get_contents($root.'/resources/js/app.js'),
            (string) file_get_contents($root.'/resources/views/layouts/operational.blade.php'),
        ]);

        $this->assertStringContainsString('MIT + Preline UI Fair Use License', $assets);
        $this->assertStringContainsString("from 'preline'", $assets);
        $this->assertStringContainsString('HSStaticMethods.autoInit()', $assets);
        $this->assertStringContainsString('Livewire.hook', $assets);
        $this->assertStringContainsString("from 'chart.js/auto'", $assets);
        $this->assertStringNotContainsString('apexcharts', strtolower($assets));
        $this->assertDoesNotMatchRegularExpression('#https?://#', $assets);
        $this->assertFileExists($root.'/public/build/manifest.json');
    }

    public function test_operational_shell_reuses_the_platform_route_and_context_chain(): void
    {
        $root = dirname(__DIR__, 2);
        $bootstrap = (string) file_get_contents($root.'/bootstrap/app.php');
        $routes = (string) file_get_contents($root.'/routes/operational.php');
        $layout = (string) file_get_contents($root.'/resources/views/layouts/operational.blade.php');

        $this->assertStringContainsString("require __DIR__.'/../routes/operational.php';", $bootstrap);
        foreach (['auth', 'active-user', 'tenant-context', 'permission-team-context', 'active-tenant', 'tenant-presentation', 'can:dashboard.view'] as $middleware) {
            $this->assertStringContainsString("'{$middleware}'", $routes);
        }

        $this->assertStringContainsString("route('operational.index')", $layout);
        $this->assertStringContainsString("url('/admin')", $layout);
        $this->assertStringContainsString('Tenant context', $layout);
        $this->assertStringContainsString('aria-current', $layout);
    }
}
