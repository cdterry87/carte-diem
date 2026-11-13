<?php

namespace App\Services;

class CategoryInferrer
{
    /**
     * @var array<string, list<string>>
     */
    private const KEYWORDS = [
        'produce' => ['apple', 'banana', 'orange', 'lemon', 'lime', 'grape', 'berry', 'berries', 'avocado', 'tomato', 'potato', 'onion', 'garlic', 'lettuce', 'spinach', 'kale', 'carrot', 'celery', 'pepper', 'cucumber', 'broccoli', 'mushroom', 'herb', 'cilantro', 'parsley', 'salad', 'squash', 'zucchini', 'melon', 'peach', 'pear'],
        'dairy-eggs' => ['milk', 'egg', 'cheese', 'butter', 'yogurt', 'yoghurt', 'cream', 'cheddar', 'mozzarella', 'parmesan'],
        'meat-seafood' => ['chicken', 'beef', 'pork', 'turkey', 'bacon', 'sausage', 'ham', 'steak', 'salmon', 'tuna', 'shrimp', 'fish', 'lamb'],
        'bakery' => ['bread', 'bagel', 'bun', 'roll', 'tortilla', 'muffin', 'croissant', 'cake', 'donut'],
        'frozen' => ['frozen', 'ice cream', 'popsicle', 'pizza'],
        'pantry' => ['rice', 'pasta', 'flour', 'sugar', 'salt', 'oil', 'vinegar', 'cereal', 'oat', 'bean', 'soup', 'sauce', 'spice', 'honey', 'peanut butter', 'jam', 'cracker', 'chips', 'snack', 'ketchup', 'mustard', 'mayo'],
        'beverages' => ['water', 'soda', 'juice', 'coffee', 'tea', 'beer', 'wine', 'seltzer', 'lemonade'],
        'household' => ['paper towel', 'toilet paper', 'detergent', 'dish soap', 'trash bag', 'foil', 'sponge', 'bleach', 'cleaner', 'napkin', 'battery', 'batteries', 'light bulb'],
        'personal-care' => ['shampoo', 'conditioner', 'toothpaste', 'toothbrush', 'deodorant', 'soap', 'lotion', 'razor', 'floss', 'sunscreen', 'tissue'],
        'pets' => ['dog food', 'cat food', 'kibble', 'cat litter', 'litter', 'pet'],
    ];

    /**
     * Returns a system category slug; longer keyword matches beat shorter ones.
     */
    public function infer(string $name): string
    {
        $name = strtolower($name);
        $bestSlug = 'other';
        $bestLength = 0;

        foreach (self::KEYWORDS as $slug => $keywords) {
            foreach ($keywords as $keyword) {
                if (strlen($keyword) > $bestLength && preg_match('/\b'.preg_quote($keyword, '/').'/', $name)) {
                    $bestSlug = $slug;
                    $bestLength = strlen($keyword);
                }
            }
        }

        return $bestSlug;
    }
}
