<?php

namespace Tests\Architecture;

use PHPUnit\Framework\TestCase;

final class FrontendStackContractTest extends TestCase
{
    public function test_laravel_backend_contains_no_application_frontend_assets_or_root_node_manifests(): void
    {
        $root = dirname(__DIR__, 2);

        foreach (['package.json', 'package-lock.json', 'vite.config.js'] as $manifest) {
            $this->assertFileDoesNotExist($root.'/'.$manifest);
        }

        foreach (['resources/views', 'resources/css', 'resources/js'] as $directory) {
            $this->assertDirectoryDoesNotExist($root.'/'.$directory);
        }

        $this->assertFileDoesNotExist($root.'/routes/operational.php');
        $this->assertFileDoesNotExist($root.'/routes/web.php');
    }

    public function test_bootstrap_registers_only_api_and_console_application_routing(): void
    {
        $root = dirname(__DIR__, 2);
        $bootstrap = (string) file_get_contents($root.'/bootstrap/app.php');

        $this->assertStringContainsString("api: __DIR__.'/../routes/api.php'", $bootstrap);
        $this->assertStringContainsString("commands: __DIR__.'/../routes/console.php'", $bootstrap);
        $this->assertStringContainsString("health: '/up'", $bootstrap);
        $this->assertStringNotContainsString("web: __DIR__.'/../routes/web.php'", $bootstrap);
        $this->assertStringNotContainsString('routes/operational.php', $bootstrap);
        $this->assertStringNotContainsString('tenant-presentation', $bootstrap);
        $this->assertStringContainsString('statefulApi()', $bootstrap);
        $this->assertStringContainsString('ApiErrorResponse::from', $bootstrap);
    }

    public function test_backend_has_no_presentation_only_symbols(): void
    {
        $root = dirname(__DIR__, 2);

        foreach ([
            'app/Helpers/MenuHelper.php',
            'app/Support/Formatting/MoneyFormatter.php',
            'app/Http/Middleware/ApplyTenantPresentationContext.php',
            'app/Http/Middleware/ResolveOptionalTenantContext.php',
        ] as $path) {
            $this->assertFileDoesNotExist($root.'/'.$path);
        }

        foreach (['MenuHelper', 'MoneyFormatter', 'ApplyTenantPresentationContext', 'ResolveOptionalTenantContext'] as $symbol) {
            foreach (['app', 'bootstrap', 'routes'] as $directory) {
                $iterator = new \RecursiveIteratorIterator(
                    new \RecursiveDirectoryIterator($root.'/'.$directory, \FilesystemIterator::SKIP_DOTS),
                );

                foreach ($iterator as $file) {
                    if ($file->isFile() && str_ends_with($file->getFilename(), '.php')) {
                        $this->assertStringNotContainsString($symbol, (string) file_get_contents($file->getPathname()));
                    }
                }
            }
        }
    }
}
