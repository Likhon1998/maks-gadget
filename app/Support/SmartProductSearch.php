<?php

namespace App\Support;

use App\Models\Product;
use Illuminate\Support\Collection;
use Illuminate\Support\Str;

/**
 * Typo-tolerant storefront search: expands misspellings, aliases, and fuzzy ranks.
 */
class SmartProductSearch
{
    /**
     * Canonical gadget terms and common misspellings / alternate spellings.
     *
     * @var array<string, list<string>>
     */
    protected static array $aliases = [
        'iphone' => ['iphone', 'ifone', 'iphon', 'i phone', 'eyephone', 'ipone', 'iapple phone'],
        'samsung' => ['samsung', 'samung', 'samsang', 'samsumg', 'samsgung', 'samsng'],
        'airpods' => ['airpods', 'airpod', 'air pot', 'airports', 'earpods', 'air pod', 'airpodss', 'airpodes'],
        'xiaomi' => ['xiaomi', 'xiomi', 'xiaomi', 'shaomi', 'mi phone'],
        'redmi' => ['redmi', 'red mi', 'radmi'],
        'oneplus' => ['oneplus', 'one plus', 'onepluss', '1+', 'one +'],
        'realme' => ['realme', 'realm', 'relme', 'real me'],
        'oppo' => ['oppo', 'opo', 'oppo phone'],
        'vivo' => ['vivo', 'vivoo'],
        'apple' => ['apple', 'aple', 'appel', 'appl'],
        'macbook' => ['macbook', 'mac book', 'mackbook', 'macbok'],
        'ipad' => ['ipad', 'i pad', 'ipd'],
        'pixel' => ['pixel', 'pixle', 'googel pixel', 'google pixel'],
        'nothing' => ['nothing', 'nothin', 'nothing phone'],
        'sony' => ['sony', 'sonyy'],
        'jbl' => ['jbl', 'j b l'],
        'baseus' => ['baseus', 'bases', 'basues'],
        'anker' => ['anker', 'ankor'],
        'charger' => ['charger', 'chargers', 'chager', 'chrger', 'adapte', 'adapter'],
        'earphone' => ['earphone', 'earphones', 'ear phone', 'headphone', 'headphones', 'headset'],
        'powerbank' => ['powerbank', 'power bank', 'powerban', 'powrbank'],
        'cable' => ['cable', 'cabel', 'usb cable', 'type c', 'type-c'],
        'watch' => ['watch', 'smartwatch', 'smart watch', 'iwatch'],
        'laptop' => ['laptop', 'lapotop', 'notebook'],
        'tab' => ['tab', 'tablet', 'teblet'],
    ];

    public static function normalize(string $value): string
    {
        $value = Str::lower(trim($value));
        $value = str_replace(['&', '+'], [' and ', ' plus '], $value);
        $value = preg_replace('/[^a-z0-9\s]+/u', ' ', $value) ?? $value;
        $value = preg_replace('/\s+/u', ' ', $value) ?? $value;

        return trim($value);
    }

    /**
     * Build search terms that tolerate typos and aliases.
     *
     * @return list<string>
     */
    public static function expand(string $query): array
    {
        $raw = trim($query);
        if ($raw === '') {
            return [];
        }

        $normalized = self::normalize($raw);
        $terms = [$raw, $normalized];

        foreach (preg_split('/\s+/', $normalized) ?: [] as $token) {
            if (mb_strlen($token) >= 2) {
                $terms[] = $token;
            }
        }

        foreach (self::$aliases as $canonical => $variants) {
            $all = array_values(array_unique(array_merge([$canonical], $variants)));
            $matched = false;

            foreach ($all as $variant) {
                $v = self::normalize($variant);
                if ($v === '') {
                    continue;
                }
                if ($normalized === $v
                    || str_contains($normalized, $v)
                    || str_contains($v, $normalized)
                    || self::fuzzyClose($normalized, $v)
                ) {
                    $matched = true;
                    break;
                }

                foreach (preg_split('/\s+/', $normalized) ?: [] as $token) {
                    if (mb_strlen($token) >= 3 && self::fuzzyClose($token, $v)) {
                        $matched = true;
                        break 2;
                    }
                }
            }

            if ($matched) {
                $terms[] = $canonical;
                foreach ($variants as $variant) {
                    $terms[] = $variant;
                }
            }
        }

        foreach (preg_split('/\s+/', $normalized) ?: [] as $token) {
            $len = mb_strlen($token);
            if ($len < 4 || $len > 14) {
                continue;
            }
            // Drop one character (common mistype)
            for ($i = 0; $i < $len; $i++) {
                $terms[] = mb_substr($token, 0, $i).mb_substr($token, $i + 1);
            }
            // Adjacent transpose
            for ($i = 0; $i < $len - 1; $i++) {
                $chars = preg_split('//u', $token, -1, PREG_SPLIT_NO_EMPTY) ?: [];
                if (! isset($chars[$i], $chars[$i + 1])) {
                    continue;
                }
                [$chars[$i], $chars[$i + 1]] = [$chars[$i + 1], $chars[$i]];
                $terms[] = implode('', $chars);
            }
        }

        $terms = array_map(static fn ($term) => trim((string) $term), $terms);
        $terms = array_values(array_unique(array_filter($terms, static fn ($term) => mb_strlen($term) >= 2)));

        return array_slice($terms, 0, 36);
    }

    public static function fuzzyClose(string $a, string $b): bool
    {
        $a = self::normalize($a);
        $b = self::normalize($b);
        if ($a === '' || $b === '') {
            return false;
        }
        if ($a === $b) {
            return true;
        }

        $maxLen = max(mb_strlen($a), mb_strlen($b));
        $allowed = $maxLen <= 4 ? 1 : ($maxLen <= 8 ? 2 : 3);

        if (function_exists('levenshtein') && strlen($a) <= 255 && strlen($b) <= 255) {
            if (levenshtein($a, $b) <= $allowed) {
                return true;
            }
        }

        if (function_exists('metaphone') && metaphone($a) !== '' && metaphone($a) === metaphone($b)) {
            return true;
        }

        similar_text($a, $b, $percent);

        return $percent >= 78;
    }

    /**
     * Apply typo-tolerant LIKE filters to a product query.
     */
    public static function applyToQuery($query, string $search, string $likeOperator = 'like'): void
    {
        $terms = self::expand($search);
        if ($terms === []) {
            return;
        }

        $query->where(function ($builder) use ($terms, $likeOperator) {
            foreach ($terms as $term) {
                $pattern = '%'.$term.'%';
                $builder->orWhere('name', $likeOperator, $pattern)
                    ->orWhere('brand_name', $likeOperator, $pattern)
                    ->orWhere('sku', $likeOperator, $pattern)
                    ->orWhere('barcode', $likeOperator, $pattern)
                    ->orWhere('short_description', $likeOperator, $pattern)
                    ->orWhereHas('brand', fn ($brand) => $brand->where('name', $likeOperator, $pattern))
                    ->orWhereHas('category', fn ($category) => $category->where('name', $likeOperator, $pattern));
            }
        });
    }

    public static function score(Product $product, string $query): float
    {
        $needle = self::normalize($query);
        if ($needle === '') {
            return 0.0;
        }

        $name = self::normalize($product->storefrontDisplayName());
        $brand = self::normalize((string) ($product->brand?->name ?? $product->brand_name ?? ''));
        $sku = self::normalize((string) ($product->sku ?? ''));
        $category = self::normalize((string) ($product->category?->name ?? ''));
        $haystack = trim($name.' '.$brand.' '.$sku.' '.$category);

        $score = 0.0;

        if ($name === $needle || $brand === $needle) {
            $score += 1000;
        }
        if (str_starts_with($name, $needle) || str_starts_with($brand, $needle)) {
            $score += 420;
        }
        if (str_contains($name, $needle) || str_contains($brand, $needle) || str_contains($haystack, $needle)) {
            $score += 320;
        }

        foreach (preg_split('/\s+/', $needle) ?: [] as $token) {
            if (mb_strlen($token) < 2) {
                continue;
            }
            if (str_contains($name, $token) || str_contains($brand, $token)) {
                $score += 90;
            } elseif (self::fuzzyClose($token, $name) || self::tokenFuzzyIn($token, $name) || self::tokenFuzzyIn($token, $brand)) {
                $score += 70;
            } elseif (function_exists('metaphone')) {
                foreach (preg_split('/\s+/', $haystack) ?: [] as $word) {
                    if (mb_strlen($word) < 3) {
                        continue;
                    }
                    if (metaphone($token) !== '' && metaphone($token) === metaphone($word)) {
                        $score += 55;
                        break;
                    }
                }
            }
        }

        // Whole-query fuzzy against name/brand
        if (self::fuzzyClose($needle, $name) || self::fuzzyClose($needle, $brand)) {
            $score += 180;
        }

        similar_text($needle, $name, $namePct);
        similar_text($needle, $haystack, $hayPct);
        $score += max($namePct, $hayPct) * 2.2;

        if (function_exists('levenshtein') && strlen($needle) <= 255 && strlen($name) <= 255) {
            $score -= levenshtein($needle, $name) * 3;
        }

        if (($product->is_best_seller ?? false) === true) {
            $score += 12;
        }

        return $score;
    }

    protected static function tokenFuzzyIn(string $token, string $text): bool
    {
        foreach (preg_split('/\s+/', $text) ?: [] as $word) {
            if (mb_strlen($word) < 3) {
                continue;
            }
            if (self::fuzzyClose($token, $word)) {
                return true;
            }
        }

        return false;
    }

    /**
     * Keep products that are relevant enough for the query, sorted best-first.
     *
     * @param  Collection<int, Product>  $products
     * @return Collection<int, Product>
     */
    public static function rank(Collection $products, string $query, int $limit): Collection
    {
        $needle = self::normalize($query);
        if ($needle === '') {
            return $products->take($limit)->values();
        }

        $minScore = mb_strlen($needle) <= 3 ? 35 : 55;

        return $products
            ->map(fn (Product $product) => [
                'product' => $product,
                'score' => self::score($product, $query),
            ])
            ->filter(fn (array $row) => $row['score'] >= $minScore)
            ->sortByDesc('score')
            ->take($limit)
            ->map(fn (array $row) => $row['product'])
            ->values();
    }
}
