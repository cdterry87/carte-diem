<?php

use App\Services\CategoryInferrer;

it('infers system categories from names', function (string $name, string $slug) {
    expect((new CategoryInferrer)->infer($name))->toBe($slug);
})->with([
    ['Gala Apples', 'produce'],
    ['whole milk', 'dairy-eggs'],
    ['peanut butter', 'pantry'],
    ['ice cream', 'frozen'],
    ['dog food', 'pets'],
    ['mystery thing', 'other'],
]);
