<?php

namespace App\Models;

use App\Models\Concerns\HasPublicUuid;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * @property int $id
 * @property string $uuid
 * @property string $description_snapshot
 * @property string|null $quantity
 * @property string|null $unit_code
 * @property int|null $line_total_minor
 * @property int|null $category_id
 * @property string|null $category_name_snapshot
 * @property int|null $product_id
 * @property bool $is_verified
 * @property-read Category|null $category
 * @property-read Product $product
 */
#[Fillable(['purchase_transaction_id', 'source_trip_item_id', 'product_id', 'description_snapshot', 'quantity', 'unit_code', 'line_total_minor', 'unit_price_minor', 'category_id', 'category_name_snapshot', 'is_verified'])]
class PurchaseTransactionItem extends Model
{
    use HasPublicUuid;

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'quantity' => 'decimal:3',
            'is_verified' => 'boolean',
        ];
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
}
