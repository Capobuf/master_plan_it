<?php

namespace Tests;

use Facebook\WebDriver\Remote\DesiredCapabilities;
use Facebook\WebDriver\Remote\RemoteWebDriver;
use Facebook\WebDriver\WebDriverCapabilities;
use Laravel\Dusk\TestCase as BaseTestCase;
use RuntimeException;
use Tests\Browser\Support\BrowserMatrix;

abstract class DuskTestCase extends BaseTestCase
{
    protected function driver(): RemoteWebDriver
    {
        $profile = BrowserMatrix::selected();
        $capabilities = new DesiredCapabilities([
            'browserName' => $profile['browser'],
            'browserVersion' => $profile['version'],
        ]);

        $driver = RemoteWebDriver::create($profile['driverUrl'], $capabilities);

        try {
            self::assertResolvedCapabilities($profile, $driver->getCapabilities());
        } catch (RuntimeException $exception) {
            $driver->quit();
            throw $exception;
        }

        return $driver;
    }

    /** @param array{browser: string, version: string, driverUrl: string} $profile */
    public static function assertResolvedCapabilities(
        array $profile,
        ?WebDriverCapabilities $capabilities,
    ): void {
        if ($capabilities === null) {
            throw new RuntimeException('WebDriver did not expose resolved capabilities.');
        }

        $browser = trim((string) $capabilities->getBrowserName());
        $version = trim((string) $capabilities->getVersion());
        if ($browser === '') {
            throw new RuntimeException('WebDriver returned an empty browser capability.');
        }
        if ($version === '') {
            throw new RuntimeException('WebDriver returned an empty browser-version capability.');
        }

        if (self::normalizedBrowser($browser) !== self::normalizedBrowser($profile['browser'])) {
            throw new RuntimeException("WebDriver returned browser {$browser}, expected {$profile['browser']}.");
        }
        $expectedVersion = trim($profile['version']);
        if ($version !== $expectedVersion && ! str_starts_with($version, $expectedVersion.'.')) {
            throw new RuntimeException("WebDriver returned version {$version}, expected {$profile['version']}.");
        }
    }

    private static function normalizedBrowser(string $browser): string
    {
        $normalized = strtolower((string) preg_replace('/[^a-z0-9]+/i', '', $browser));

        return match ($normalized) {
            'microsoftedge', 'msedge' => 'edge',
            'googlechrome', 'chrome' => 'chrome',
            default => $normalized,
        };
    }
}
