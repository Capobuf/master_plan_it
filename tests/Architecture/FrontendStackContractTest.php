<?php

namespace Tests\Architecture;

use PHPUnit\Framework\TestCase;

class FrontendStackContractTest extends TestCase
{
    public function test_inertia_react_frontend_is_locally_built_from_the_typescript_entrypoint(): void
    {
        $root = dirname(__DIR__, 2);
        $package = json_decode((string) file_get_contents($root.'/package.json'), true, flags: JSON_THROW_ON_ERROR);
        $lock = json_decode((string) file_get_contents($root.'/package-lock.json'), true, flags: JSON_THROW_ON_ERROR);

        $this->assertSame('3.6.1', $package['dependencies']['@inertiajs/react'] ?? null);
        $this->assertSame('19.2.8', $package['dependencies']['react'] ?? null);
        $this->assertSame('19.2.8', $package['dependencies']['react-dom'] ?? null);
        $this->assertSame('4.7.0', $package['devDependencies']['@vitejs/plugin-react'] ?? null);
        $this->assertSame('7.0.2', $package['devDependencies']['typescript'] ?? null);
        $this->assertSame('4.2.4', $lock['packages']['node_modules/tailwindcss']['version'] ?? null);

        $entrypoint = (string) file_get_contents($root.'/resources/js/app.tsx');
        $rootView = (string) file_get_contents($root.'/resources/views/app.blade.php');
        $viteConfig = (string) file_get_contents($root.'/vite.config.js');
        $assets = $entrypoint."\n".$rootView."\n".$viteConfig;

        $this->assertMatchesRegularExpression('/from [\'\"]@inertiajs\\/react[\'\"]/', $entrypoint);
        $this->assertStringContainsString('import.meta.glob', $entrypoint);
        $this->assertStringContainsString('./pages/**/*.tsx', $entrypoint);
        $this->assertStringContainsString('@inertiaHead', $rootView);
        $this->assertStringContainsString('@inertia', $rootView);
        $this->assertStringContainsString("'resources/js/app.tsx'", $viteConfig);
        $this->assertStringContainsString('react()', $viteConfig);
        $this->assertDoesNotMatchRegularExpression('#https?://#', $assets);
    }

    public function test_operational_routes_keep_the_tenant_security_chain_and_inertia_shell(): void
    {
        $root = dirname(__DIR__, 2);
        $bootstrap = (string) file_get_contents($root.'/bootstrap/app.php');
        $routes = (string) file_get_contents($root.'/routes/operational.php');
        $layout = (string) file_get_contents($root.'/resources/js/layouts/AppLayout.tsx');

        $this->assertStringContainsString("require __DIR__.'/../routes/operational.php';", $bootstrap);
        foreach (['auth', 'active-user', 'tenant-context', 'permission-team-context', 'active-tenant', 'tenant-presentation'] as $middleware) {
            $this->assertStringContainsString("'{$middleware}'", $routes);
        }

        $this->assertStringContainsString('application-ability:dashboard.view', $routes);
        $this->assertStringContainsString('/operational', $layout);
        $this->assertStringContainsString('/platform/tenants', $layout);
        $this->assertStringContainsString('aria-label="Main navigation"', $layout);
    }
}
