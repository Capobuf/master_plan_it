<?php

declare(strict_types=1);

$report = $argv[1] ?? null;
if (! is_string($report) || ! is_file($report)) {
    fwrite(STDERR, "Coverage report is required.\n");
    exit(2);
}

$xml = @simplexml_load_file($report);
if ($xml === false) {
    fwrite(STDERR, "Invalid Cobertura report.\n");
    exit(2);
}

$classes = [];
foreach ($xml->xpath('//class') ?: [] as $class) {
    $attributes = $class->attributes();
    $classes[(string) $attributes['name']] = [
        'line' => isset($attributes['line-rate']) ? (string) $attributes['line-rate'] : null,
        'branch' => isset($attributes['branch-rate']) ? (string) $attributes['branch-rate'] : null,
    ];
}

foreach (require __DIR__.'/economic-coverage-classes.php' as $class) {
    $metric = $classes[$class] ?? null;
    if ($metric === null) {
        fwrite(STDERR, "Missing mandatory class {$class}.\n");
        exit(1);
    }
    foreach (['line', 'branch'] as $kind) {
        if ($metric[$kind] === null) {
            fwrite(STDERR, "Missing {$kind} metric for {$class}.\n");
            exit(1);
        }
        if ((float) $metric[$kind] < 1.0) {
            fwrite(STDERR, "{$kind} coverage below 100 for {$class}.\n");
            exit(1);
        }
    }
}

exit(0);
