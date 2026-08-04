<?php

namespace Tests\Browser\Support;

use InvalidArgumentException;
use RuntimeException;

final class BrowserMatrix
{
    /**
     * Resolved browser versions and driver endpoints are mandatory so a CI run cannot claim a
     * moving or unavailable browser target.
     *
     * @return array<string, array{browser: string, versionEnvironment: string, driverUrlEnvironment: string}>
     */
    public static function profiles(): array
    {
        return [
            'chrome-current' => self::profile('chrome', 'CHROME_CURRENT'),
            'chrome-previous' => self::profile('chrome', 'CHROME_PREVIOUS'),
            'edge-current' => self::profile('MicrosoftEdge', 'EDGE_CURRENT'),
            'edge-previous' => self::profile('MicrosoftEdge', 'EDGE_PREVIOUS'),
            'firefox-current' => self::profile('firefox', 'FIREFOX_CURRENT'),
            'firefox-previous' => self::profile('firefox', 'FIREFOX_PREVIOUS'),
            'safari-current' => self::profile('safari', 'SAFARI_CURRENT'),
        ];
    }

    /** @return list<int> */
    public static function viewports(): array
    {
        return [360, 768, 1280];
    }

    /** @return array{browser: string, version: string, driverUrl: string} */
    public static function selected(): array
    {
        $name = getenv('MPIT_BROWSER_PROFILE');
        if (! is_string($name) || $name === '') {
            throw new RuntimeException('MPIT_BROWSER_PROFILE must select an approved browser profile.');
        }

        $profiles = self::profiles();
        if (! array_key_exists($name, $profiles)) {
            throw new InvalidArgumentException('Unsupported browser profile: '.$name);
        }

        return self::resolve($profiles[$name]);
    }

    /** @return array<string, array{browser: string, version: string, driverUrl: string}> */
    public static function validateResolvedMatrix(): array
    {
        $resolved = [];
        foreach (self::profiles() as $name => $profile) {
            $resolved[$name] = self::resolve($profile);
        }

        foreach (['chrome', 'edge', 'firefox'] as $browser) {
            if ($resolved[$browser.'-current']['version'] === $resolved[$browser.'-previous']['version']) {
                throw new RuntimeException($browser.' current and previous profiles must resolve to distinct versions.');
            }
        }

        return $resolved;
    }

    /** @param array{browser: string, versionEnvironment: string, driverUrlEnvironment: string} $profile
     * @return array{browser: string, version: string, driverUrl: string}
     */
    private static function resolve(array $profile): array
    {
        $version = getenv($profile['versionEnvironment']);
        $driverUrl = getenv($profile['driverUrlEnvironment']);

        if (! is_string($version) || $version === '') {
            throw new RuntimeException($profile['versionEnvironment'].' must contain the resolved browser version.');
        }
        if (! is_string($driverUrl) || filter_var($driverUrl, FILTER_VALIDATE_URL) === false) {
            throw new RuntimeException($profile['driverUrlEnvironment'].' must contain a valid WebDriver URL.');
        }

        return [
            'browser' => $profile['browser'],
            'version' => $version,
            'driverUrl' => $driverUrl,
        ];
    }

    /** @param list<string> $arguments */
    public static function run(array $arguments): int
    {
        $arguments = array_values(array_filter($arguments, fn (string $argument): bool => $argument !== '--'));

        // Resolve the complete matrix before the first run so missing profiles cannot yield a partial claim.
        self::validateResolvedMatrix();

        foreach (array_keys(self::profiles()) as $name) {
            self::selectEnvironment($name);
            fwrite(STDOUT, "Running browser profile {$name}\n");
            $command = escapeshellarg(PHP_BINARY).' artisan dusk';
            if ($arguments !== []) {
                $command .= ' '.implode(' ', array_map('escapeshellarg', $arguments));
            }

            passthru($command, $exitCode);
            if ($exitCode !== 0) {
                return $exitCode;
            }
        }

        return 0;
    }

    /** @return array{browser: string, versionEnvironment: string, driverUrlEnvironment: string} */
    private static function profile(string $browser, string $environmentStem): array
    {
        return [
            'browser' => $browser,
            'versionEnvironment' => 'MPIT_'.$environmentStem.'_VERSION',
            'driverUrlEnvironment' => 'MPIT_'.$environmentStem.'_DRIVER_URL',
        ];
    }

    private static function selectEnvironment(string $name): void
    {
        putenv('MPIT_BROWSER_PROFILE='.$name);
        $_ENV['MPIT_BROWSER_PROFILE'] = $name;
        $_SERVER['MPIT_BROWSER_PROFILE'] = $name;
    }
}
