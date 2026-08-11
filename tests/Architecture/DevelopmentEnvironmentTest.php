<?php

namespace Tests\Architecture;

use PHPUnit\Framework\TestCase;

class DevelopmentEnvironmentTest extends TestCase
{
    public function test_sail_environment_uses_exact_non_destructive_runtime_contract(): void
    {
        $root = dirname(__DIR__, 2);

        $this->assertFileExists($root.'/compose.yaml');
        $this->assertFileExists($root.'/docker/8.3/Dockerfile');
        $this->assertFileExists($root.'/docker/8.3/mysql-init.sh');
        $this->assertFileExists($root.'/.env.example');
        $this->assertFileExists($root.'/.env.testing.example');

        $compose = file_get_contents($root.'/compose.yaml');
        $this->assertStringContainsString('laravel.test:', $compose);
        $this->assertStringContainsString('context: ./docker/8.3', $compose);
        $this->assertStringContainsString('image: mysql:8.4.10', $compose);
        $this->assertStringContainsString("'127.0.0.1:\${APP_PORT:-8080}:80'", $compose);
        $this->assertStringNotContainsString("'8080:80'", $compose);
        $this->assertStringContainsString('frontend:', $compose);
        $this->assertStringContainsString('image: node:22-alpine', $compose);
        $this->assertStringContainsString("'0.0.0.0:\${FRONTEND_PORT:-5173}:5173'", $compose);
        $this->assertStringContainsString("VITE_INTERNAL_API_PROXY_TARGET: '\${VITE_INTERNAL_API_PROXY_TARGET:-http://laravel.test}'", $compose);
        $this->assertStringNotContainsString('selenium', $compose);
        $this->assertStringNotContainsString('dusk', $compose);
        $this->assertStringContainsString('sail-mysql:/var/lib/mysql', $compose);
        $this->assertStringNotContainsString('redis:', $compose);
        $this->assertStringNotContainsString('queue:work', $compose);

        $dockerfile = file_get_contents($root.'/docker/8.3/Dockerfile');
        $this->assertStringStartsWith('FROM php:8.3.32-cli-bookworm', $dockerfile);

        $development = file_get_contents($root.'/.env.example');
        $testing = file_get_contents($root.'/.env.testing.example');
        $this->assertStringContainsString("DB_CONNECTION=mysql\nDB_HOST=mysql\nDB_PORT=3306\nDB_DATABASE=master_plan_it\n", $development);
        $this->assertStringContainsString("APP_ENV=testing\n", $testing);
        $this->assertStringContainsString("DB_CONNECTION=mysql\nDB_HOST=mysql\nDB_PORT=3306\nDB_DATABASE=master_plan_it_test\n", $testing);

        $databaseInit = file_get_contents($root.'/docker/8.3/mysql-init.sh');
        $this->assertStringContainsString('CREATE DATABASE IF NOT EXISTS', $databaseInit);
        $this->assertStringContainsString('master_plan_it_test', $databaseInit);
        $this->assertStringNotContainsString('DROP DATABASE', $databaseInit);
    }
}
