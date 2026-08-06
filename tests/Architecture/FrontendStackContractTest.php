<?php

namespace Tests\Architecture;

use PHPUnit\Framework\TestCase;

class FrontendStackContractTest extends TestCase
{
    public function test_tailadmin_laravel_frontend_is_locally_built_from_the_blade_entrypoint(): void
    {
        $root = dirname(__DIR__, 2);
        $package = json_decode((string) file_get_contents($root.'/package.json'), true, flags: JSON_THROW_ON_ERROR);
        $lock = json_decode((string) file_get_contents($root.'/package-lock.json'), true, flags: JSON_THROW_ON_ERROR);

        $this->assertSame('3.14.9', $package['dependencies']['alpinejs'] ?? null);
        $this->assertSame('5.3.5', $package['dependencies']['apexcharts'] ?? null);
        $this->assertArrayNotHasKey('@inertiajs/react', $package['dependencies'] ?? []);
        $this->assertArrayNotHasKey('react', $package['dependencies'] ?? []);
        $this->assertSame('4.2.4', $lock['packages']['node_modules/tailwindcss']['version'] ?? null);

        $entrypoint = (string) file_get_contents($root.'/resources/js/app.js');
        $rootView = (string) file_get_contents($root.'/resources/views/layouts/app.blade.php');
        $viteConfig = (string) file_get_contents($root.'/vite.config.js');
        $assets = $entrypoint."\n".$rootView."\n".$viteConfig;

        $this->assertStringContainsString("from 'alpinejs'", $entrypoint);
        $this->assertStringContainsString("from 'apexcharts'", $entrypoint);
        $this->assertStringContainsString('@vite', $rootView);
        $this->assertStringContainsString("'resources/js/app.js'", $viteConfig);
        $this->assertStringNotContainsString('react()', $viteConfig);
        $this->assertDoesNotMatchRegularExpression('#https?://#', $assets);
    }

    public function test_operational_routes_keep_the_tenant_security_chain_and_tailadmin_shell(): void
    {
        $root = dirname(__DIR__, 2);
        $bootstrap = (string) file_get_contents($root.'/bootstrap/app.php');
        $routes = (string) file_get_contents($root.'/routes/operational.php');
        $layout = (string) file_get_contents($root.'/resources/views/layouts/sidebar.blade.php');

        $this->assertStringContainsString("require __DIR__.'/../routes/operational.php';", $bootstrap);
        foreach (['auth', 'active-user', 'tenant-context', 'permission-team-context', 'active-tenant', 'tenant-presentation'] as $middleware) {
            $this->assertStringContainsString("'{$middleware}'", $routes);
        }

        $this->assertStringContainsString('application-ability:dashboard.view', $routes);
        $this->assertStringContainsString('MenuHelper::groups()', $layout);
        $this->assertStringContainsString('$store.sidebar', $layout);
        $this->assertStringContainsString('<nav', $layout);
    }

    public function test_branch_has_no_legacy_application_frontend_stack(): void
    {
        $root = dirname(__DIR__, 2);
        $composer = json_decode((string) file_get_contents($root.'/composer.json'), true, flags: JSON_THROW_ON_ERROR);
        $package = json_decode((string) file_get_contents($root.'/package.json'), true, flags: JSON_THROW_ON_ERROR);
        $dependencies = array_merge(
            array_keys($composer['require'] ?? []),
            array_keys($composer['require-dev'] ?? []),
            array_keys($package['dependencies'] ?? []),
            array_keys($package['devDependencies'] ?? []),
        );

        foreach ($dependencies as $dependency) {
            $this->assertDoesNotMatchRegularExpression('/(?:preline|livewire|filament)/i', $dependency);
        }

        $viewFiles = [];
        $iterator = new \RecursiveIteratorIterator(
            new \RecursiveDirectoryIterator($root.'/resources/views', \FilesystemIterator::SKIP_DOTS),
        );
        foreach ($iterator as $file) {
            if ($file->isFile()) {
                $viewFiles[] = str_replace($root.'/', '', $file->getPathname());
            }
        }

        $this->assertContains('resources/views/layouts/app.blade.php', $viewFiles);
        $this->assertContains('resources/views/layouts/sidebar.blade.php', $viewFiles);
    }
}
