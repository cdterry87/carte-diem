<?php

namespace App\Models;

use App\Models\Concerns\HasPublicUuid;
use Carbon\CarbonInterface;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * @property int $id
 * @property string $uuid
 * @property int $shopping_trip_id
 * @property int|null $source_list_item_id
 * @property int|null $product_id
 * @property int|null $category_id
 * @property string $name_snapshot
 * @property string|null $category_name_snapshot
 * @property int $category_sort_snapshot
 * @property string|null $planned_quantity
 * @property string|null $planned_unit
 * @property string|null $actual_quantity
 * @property int|null $line_total_minor
 * @property string|null $notes
 * @property bool $purchased
 * @property int|null $checked_by
 * @property CarbonInterface|null $checked_at
 * @property int $sort_order
 * @property int $revision
 * @property-read User|null $checker
 */
#[Fillable(['shopping_trip_id', 'source_list_item_id', 'product_id', 'category_id', 'name_snapshot', 'category_name_snapshot', 'category_sort_snapshot', 'planned_quantity', 'planned_unit', 'actual_quantity', 'line_total_minor', 'notes', 'purchased', 'checked_by', 'checked_at', 'sort_order', 'revision'])]
class ShoppingTripItem extends Model
{
    use HasPublicUuid;

    /**
     * @var array<string, mixed>
     */
    protected $attributes = ['purchased' => false, 'revision' => 1, 'category_sort_snapshot' => 0];

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'planned_quantity' => 'decimal:3',
            'actual_quantity' => 'decimal:3',
            'purchased' => 'boolean',
            'checked_at' => 'datetime',
        ];
    }

    /**
     * @return BelongsTo<User, $this>
     */
    public function checker(): BelongsTo
    {
        return $this->belongsTo(User::class, 'checked_by');
    }

    /**
     * @return array<string, mixed>
     */
    public static function snapshotAttributes(ShoppingListItem $item, ?int $categorySort = null): array
    {
        return [
            'source_list_item_id' => $item->id,
            'product_id' => $item->product_id,
            'category_id' => $item->category_id,
            'name_snapshot' => $item->name_snapshot,
            'category_name_snapshot' => $item->category?->name,
            'category_sort_snapshot' => $categorySort ?? $item->category->sort_order ?? 0,
            'planned_quantity' => $item->requested_quantity,
            'planned_unit' => $item->unit_code,
            'notes' => $item->notes,
            'sort_order' => $item->sort_order,
        ];
    }
}
