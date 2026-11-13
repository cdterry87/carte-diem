<?php

use App\Services\ItemEntryParser;

it('parses free-text entries', function (string $entry, string $name, ?string $quantity, ?string $unit) {
    $parsed = (new ItemEntryParser)->parse($entry);

    expect($parsed->name)->toBe($name)
        ->and($parsed->quantity)->toBe($quantity)
        ->and($parsed->unit)->toBe($unit);
})->with([
    'name only' => ['apples', 'apples', null, null],
    'count' => ['3 apples', 'apples', '3.000', null],
    'unit' => ['2 lb apples', 'apples', '2.000', 'lb'],
    'unit alias' => ['2 Pounds apples', 'apples', '2.000', 'lb'],
    'fraction' => ['1/2 gal milk', 'milk', '0.500', 'gal'],
    'mixed fraction' => ['1 1/2 lb beef', 'beef', '1.500', 'lb'],
    'decimal' => ['1.5 kg rice', 'rice', '1.500', 'kg'],
    'of' => ['2 cans of beans', 'beans', '2.000', 'can'],
    'suffix' => ['eggs x 12', 'eggs', '12.000', null],
    'extra spaces' => ['  2   lb   apples ', 'apples', '2.000', 'lb'],
    'percent stays in name' => ['2% milk', '2% milk', null, null],
    'digit brand' => ['7up', '7up', null, null],
    'unit word as name' => ['2 bags', '2 bags', null, null],
    'zero denominator' => ['1/0 milk', '1/0 milk', null, null],
]);
