<?php

namespace App\Models;

use App\Models\Concerns\HasPublicUuid;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\MorphTo;
use Illuminate\Support\Carbon;

/**
 * @property int $id
 * @property string $uuid
 * @property string $owner_type
 * @property int $owner_id
 * @property int $product_id
 * @property int|null $cadence_days
 * @property bool $prediction_enabled
 * @property bool $reminder_enabled
 * @property Carbon|null $snoozed_until
 * @property-read Product $product
 * @property-read Model|null $owner
 */
#[Fillable(['owner_type', 'owner_id', 'product_id', 'cadence_days', 'prediction_enabled', 'reminder_enabled', 'snoozed_until'])]
class StapleRule extends Model
{
    use HasPublicUuid;

    /**
     * @var array<string, mixed>
     */
    protected $attributes = ['prediction_enabled' => true, 'reminder_enabled' => true];

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'prediction_enabled' => 'boolean',
            'reminder_enabled' => 'boolean',
            'snoozed_until' => 'datetime',
        ];
    }

    /**
     * @return BelongsTo<Product, $this>
     */
    public function product(): BelongsTo
    {
        return $this->belongsTo(Product::class);
    }

    /**
     * @return MorphTo<Model, $this>
     */
    public function owner(): MorphTo
    {
        return $this->morphTo();
    }

    public function isSnoozed(): bool
    {
        return $this->snoozed_until !== null && $this->snoozed_until->isFuture();
    }
}
