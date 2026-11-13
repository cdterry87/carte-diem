<?php

namespace App\Models;

use App\Models\Concerns\BelongsToOwnerScope;
use App\Models\Concerns\HasPublicUuid;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Support\Carbon;

/**
 * @property int $id
 * @property string $uuid
 * @property string $scope_type
 * @property int $scope_id
 * @property string $canonical_name
 * @property string $normalized_name
 * @property int|null $default_category_id
 * @property string|null $default_quantity
 * @property string|null $default_unit_code
 * @property Carbon|null $last_used_at
 * @property-read Category|null $defaultCategory
 */
#[Fillable(['scope_type', 'scope_id', 'canonical_name', 'normalized_name', 'default_category_id', 'default_quantity', 'default_unit_code', 'is_staple', 'last_used_at'])]
class Product extends Model
{
    use BelongsToOwnerScope, HasPublicUuid;

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'default_quantity' => 'decimal:3',
            'is_staple' => 'boolean',
            'last_used_at' => 'datetime',
        ];
    }

    /**
     * @return BelongsTo<Category, $this>
     */
    public function defaultCategory(): BelongsTo
    {
        return $this->belongsTo(Category::class, 'default_category_id');
    }

    /**
     * @return HasMany<ProductAlias, $this>
     */
    public function aliases(): HasMany
    {
        return $this->hasMany(ProductAlias::class);
    }

    /**
     * @return BelongsToMany<User, $this>
     */
    public function favoritedBy(): BelongsToMany
    {
        return $this->belongsToMany(User::class, 'product_favorites');
    }
}
