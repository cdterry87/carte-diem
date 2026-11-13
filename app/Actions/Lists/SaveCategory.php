<?php

namespace App\Actions\Lists;

use App\Exceptions\DomainConflictException;
use App\Models\Category;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Str;

class SaveCategory
{
    /**
     * @param  array{name: string, sort_order?: int}  $attributes
     */
    public function create(Model $owner, array $attributes): Category
    {
        $slug = Str::slug($attributes['name']);
        $existing = Category::query()->forOwner($owner)->where('slug', $slug)->first();

        if ($existing?->is_active) {
            throw new DomainConflictException('That category already exists.', 'duplicate_category');
        }

        if ($existing !== null) {
            $existing->update(['is_active' => true, 'name' => $attributes['name']]);

            return $existing;
        }

        return Category::query()->create([
            'name' => $attributes['name'],
            'slug' => $slug,
            'scope_type' => $owner->getMorphClass(),
            'scope_id' => $owner->getKey(),
            'sort_order' => $attributes['sort_order'] ?? 500,
        ]);
    }

    /**
     * @param  array{name?: string, sort_order?: int}  $attributes
     */
    public function update(Category $category, array $attributes): Category
    {
        if (isset($attributes['name'])) {
            $slug = Str::slug($attributes['name']);
            $existing = Category::query()
                ->where('scope_type', $category->scope_type)
                ->where('scope_id', $category->scope_id)
                ->where('slug', $slug)
                ->whereKeyNot($category->id)
                ->first();

            if ($existing?->is_active) {
                throw new DomainConflictException('That category already exists.', 'duplicate_category');
            }

            // A removed category keeps its row for history, but must free the name.
            $existing?->update(['slug' => $slug.'-'.$existing->id]);
            $attributes['slug'] = $slug;
        }

        $category->update($attributes);

        return $category;
    }
}
