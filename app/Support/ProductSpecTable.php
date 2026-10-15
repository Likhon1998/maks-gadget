<?php

namespace App\Support;

/**
 * Normalize admin-built specification tables for storage and storefront display.
 *
 * Shape:
 * [
 *   'headers' => ['Feature', 'Specification', ...],
 *   'rows' => [
 *     ['Brand', 'Samsung', ...],
 *     ...
 *   ],
 * ]
 */
class ProductSpecTable
{
    public const MAX_COLUMNS = 6;

    public const MAX_ROWS = 40;

    /**
     * @param  mixed  $input  JSON string, array, or null
     * @return array{headers: list<string>, rows: list<list<string>>}|null
     */
    public static function normalize(mixed $input): ?array
    {
        if (is_string($input)) {
            $decoded = json_decode($input, true);
            $input = is_array($decoded) ? $decoded : null;
        }

        if (! is_array($input)) {
            return null;
        }

        $headers = [];
        foreach (array_values($input['headers'] ?? []) as $header) {
            if (! is_scalar($header)) {
                continue;
            }
            $label = trim((string) $header);
            if ($label === '') {
                continue;
            }
            $headers[] = mb_substr($label, 0, 80);
            if (count($headers) >= self::MAX_COLUMNS) {
                break;
            }
        }

        if (count($headers) < 2) {
            $headers = ['Feature', 'Specification'];
        }

        $colCount = count($headers);
        $rows = [];

        foreach (array_values($input['rows'] ?? []) as $row) {
            if (! is_array($row)) {
                continue;
            }

            $cells = [];
            for ($i = 0; $i < $colCount; $i++) {
                $raw = $row[$i] ?? '';
                $cells[] = is_scalar($raw) ? trim(mb_substr((string) $raw, 0, 500)) : '';
            }

            // Drop completely empty rows
            if (count(array_filter($cells, fn ($c) => $c !== '')) === 0) {
                continue;
            }

            $rows[] = $cells;
            if (count($rows) >= self::MAX_ROWS) {
                break;
            }
        }

        if ($rows === []) {
            return null;
        }

        return [
            'headers' => $headers,
            'rows' => $rows,
        ];
    }

    /**
     * Build a starter table from legacy Key: Value description text.
     *
     * @return array{headers: list<string>, rows: list<list<string>>}|null
     */
    public static function fromDescription(?string $text): ?array
    {
        $parsed = ProductSpecParser::parse($text);
        if ($parsed['rows'] === []) {
            return null;
        }

        return [
            'headers' => ['Feature', 'Specification'],
            'rows' => array_map(
                fn (array $row) => [$row['feature'], $row['specification']],
                $parsed['rows']
            ),
        ];
    }

    /**
     * Default empty editor state (2 columns, 3 blank rows).
     *
     * @return array{headers: list<string>, rows: list<list<string>>}
     */
    public static function emptyEditor(): array
    {
        return [
            'headers' => ['Feature', 'Specification'],
            'rows' => [
                ['', ''],
                ['', ''],
                ['', ''],
            ],
        ];
    }
}
