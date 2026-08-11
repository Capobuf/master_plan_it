<?php

namespace App\Domain\Expenses\Data;

final class ExpenseRegisterColumns
{
    /** @var list<string> */
    public const KEYS = [
        'kind', 'contract', 'project', 'cost_center', 'vendor', 'net', 'vat', 'gross', 'state',
    ];

    /** @return list<array{key: string, visible: bool}> */
    public static function defaults(): array
    {
        return array_map(static fn (string $key): array => [
            'key' => $key,
            'visible' => $key !== 'vendor',
        ], self::KEYS);
    }

    /** @return list<array{key: string, visible: bool}> */
    public static function normalize(mixed $columns): array
    {
        $known = [];

        if (is_array($columns)) {
            foreach ($columns as $column) {
                if (! is_array($column) || ! isset($column['key']) || ! is_string($column['key'])) {
                    continue;
                }
                if (! in_array($column['key'], self::KEYS, true) || isset($known[$column['key']])) {
                    continue;
                }
                $known[$column['key']] = [
                    'key' => $column['key'],
                    'visible' => (bool) ($column['visible'] ?? false),
                ];
            }
        }

        foreach (self::defaults() as $default) {
            if (! isset($known[$default['key']])) {
                $known[$default['key']] = $default;
            }
        }

        return array_values($known);
    }
}
