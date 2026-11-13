<?php

namespace App\Models;

use App\Enums\ListItemStatus;
use App\Models\Concerns\HasPublicUuid;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\SoftDeletes;
use Illuminate\Support\Carbon;

/**
 * @property int $id
 * @property string $uuid
 * @property int $shopping_list_id
 * @property int|null $product_id
 * @property string $name_snapshot
 * @property string|null $requested_quantity
 * @property string|null $unit_code
 * @property int|null $category_id
 * @property string|null $notes
 * @property int $sort_order
 * @property ListItemStatus $status
 * @property bool $needs_review
 * @property int $revision
 * @property int|null $added_by
 * @property Carbon|null $deleted_at
 * @property-read Category|null $category
 * @property-read Product|null $product
 * @property-read User|null $addedBy
 */
#[Fillable(['shopping_list_id', 'product_id', 'name_snapshot', 'requested_quantity', 'unit_code', 'category_id', 'notes', 'sort_order', 'status', 'needs_review', 'revision', 'added_by'])]
class ShoppingListItem extends Model
{
    use HasPublicUuid, SoftDeletes;

    /**
     * @var array<string, mixed>
     */
    protected $attributes = ['status' => 'needed', 'needs_review' => false, 'revision' => 1];

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'requested_quantity' => 'decimal:3',
            'status' => ListItemStatus::class,
            'needs_review' => 'boolean',
        ];
    }

    /**
     * @return BelongsTo<ShoppingList, $this>
     */
    public function shoppingList(): BelongsTo
    {
        return $this->belongsTo(ShoppingList::class);
    }

    /**
     * @return BelongsTo<Category, $this>
     */
    public function category(): BelongsTo
    {
        return $this->belongsTo(Category::class);
    }

    /**
     * @return BelongsTo<Product, $this>
     */
    public function product(): BelongsTo
    {
        return $this->belongsTo(Product::class);
    }

    /**
     * @return BelongsTo<User, $this>
     */
    public function addedBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'added_by');
    }
}
