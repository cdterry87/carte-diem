<?php

namespace App\Services;

class ItemEntryParser
{
    /**
     * @var array<string, string>
     */
    private const UNITS = [
        'lb' => 'lb', 'lbs' => 'lb', 'pound' => 'lb', 'pounds' => 'lb',
        'oz' => 'oz', 'ounce' => 'oz', 'ounces' => 'oz',
        'kg' => 'kg', 'kilo' => 'kg', 'kilos' => 'kg',
        'g' => 'g', 'gram' => 'g', 'grams' => 'g',
        'l' => 'l', 'liter' => 'l', 'liters' => 'l', 'litre' => 'l', 'litres' => 'l',
        'ml' => 'ml',
        'gal' => 'gal', 'gallon' => 'gal', 'gallons' => 'gal',
        'qt' => 'qt', 'quart' => 'qt', 'quarts' => 'qt',
        'pt' => 'pt', 'pint' => 'pt', 'pints' => 'pt',
        'dozen' => 'dozen', 'dz' => 'dozen',
        'pack' => 'pack', 'packs' => 'pack', 'pk' => 'pack',
        'box' => 'box', 'boxes' => 'box',
        'can' => 'can', 'cans' => 'can',
        'bottle' => 'bottle', 'bottles' => 'bottle',
        'bag' => 'bag', 'bags' => 'bag',
        'jar' => 'jar', 'jars' => 'jar',
        'bunch' => 'bunch', 'bunches' => 'bunch',
        'loaf' => 'loaf', 'loaves' => 'loaf',
        'case' => 'case', 'cases' => 'case',
    ];

    public function parse(string $entry): ParsedItem
    {
        $entry = trim((string) preg_replace('/\s+/', ' ', $entry));

        if (preg_match('/^(\d+ \d+\/\d+|\d+\/\d+|\d*\.?\d+) (.+)$/', $entry, $matches)) {
            $quantity = $this->toDecimal($matches[1]);
            $rest = $matches[2];

            if ($quantity === null) {
                return new ParsedItem($entry);
            }

            $words = explode(' ', $rest, 2);
            $unit = self::UNITS[strtolower($words[0])] ?? null;

            if ($unit !== null && isset($words[1])) {
                $rest = (string) preg_replace('/^of /i', '', $words[1]);

                return new ParsedItem($rest, $quantity, $unit);
            }

            if ($unit !== null) {
                return new ParsedItem($entry);
            }

            return new ParsedItem($rest, $quantity);
        }

        if (preg_match('/^(.+?) x ?(\d*\.?\d+)$/i', $entry, $matches)) {
            return new ParsedItem($matches[1], $this->toDecimal($matches[2]));
        }

        return new ParsedItem($entry);
    }

    private function toDecimal(string $value): ?string
    {
        if (str_contains($value, '/')) {
            $whole = 0;

            if (str_contains($value, ' ')) {
                [$whole, $value] = explode(' ', $value);
            }

            [$numerator, $denominator] = explode('/', $value);

            if ((int) $denominator === 0) {
                return null;
            }

            return number_format((int) $whole + (int) $numerator / (int) $denominator, 3, '.', '');
        }

        return number_format((float) $value, 3, '.', '');
    }
}
