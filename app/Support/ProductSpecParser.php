<?php

namespace App\Support;

/**
 * Turns free-text product descriptions into Feature / Specification rows
 * while preserving any leftover narrative as prose.
 */
class ProductSpecParser
{
    /**
     * @return array{rows: array<int, array{feature: string, specification: string}>, prose: string}
     */
    public static function parse(?string $text): array
    {
        $text = trim((string) $text);
        if ($text === '') {
            return ['rows' => [], 'prose' => ''];
        }

        $rows = [];
        $proseLines = [];

        foreach (preg_split("/\r\n|\n|\r/", $text) as $rawLine) {
            $line = trim((string) $rawLine);
            if ($line === '') {
                continue;
            }

            // Bullet prefixes: "- Brand: Samsung", "• Model: X", "* Color: Black"
            $line = preg_replace('/^[\-\*\x{2022}\x{25CF}\x{25E6}]\s*/u', '', $line) ?? $line;

            if (preg_match('/^(.{1,80}?)\s*[:：\-–—|]\s*(.+)$/u', $line, $m)) {
                $feature = self::cleanLabel($m[1]);
                $specification = self::cleanValue($m[2]);

                if ($feature !== '' && $specification !== '' && self::looksLikeFeature($feature)) {
                    $rows[] = [
                        'feature' => $feature,
                        'specification' => $specification,
                    ];
                    continue;
                }
            }

            $proseLines[] = $line;
        }

        return [
            'rows' => $rows,
            'prose' => trim(implode("\n", $proseLines)),
        ];
    }

    public static function cleanLabel(string $label): string
    {
        $label = trim($label);
        $label = preg_replace('/\s+/u', ' ', $label) ?? $label;
        $label = rtrim($label, " .:：\-–—|");

        return trim($label);
    }

    public static function cleanValue(string $value): string
    {
        $value = trim($value);
        $value = preg_replace('/\s+/u', ' ', $value) ?? $value;

        return trim($value);
    }

    /**
     * Avoid treating full sentences as a "feature" label.
     */
    public static function looksLikeFeature(string $feature): bool
    {
        if (mb_strlen($feature) > 48) {
            return false;
        }

        // Too many words → likely prose, not a field name
        if (str_word_count($feature) > 6) {
            return false;
        }

        // Feature labels should not end with sentence punctuation
        if (preg_match('/[.!?]$/u', $feature)) {
            return false;
        }

        return true;
    }
}
