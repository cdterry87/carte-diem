<?php

namespace App\Actions\Lists;

use App\Exceptions\DomainConflictException;
use App\Models\ShoppingListItem;
use Illuminate\Support\Facades\DB;

class UpdateListItem
{
    /**
     * @param  array<string, mixed>  $attributes
     */
    public function handle(ShoppingListItem $item, array $attributes, ?int $expectedRevision = null): ShoppingListItem
    {
        return DB::transaction(function () use ($item, $attributes, $expectedRevision): ShoppingListItem {
            /** @var ShoppingListItem $locked */
            $locked = ShoppingListItem::query()->whereKey($item->id)->lockForUpdate()->firstOrFail();

            if ($expectedRevision !== null && $locked->revision !== $expectedRevision) {
                throw new DomainConflictException('This item changed since you loaded it.', 'stale_revision', ['revision' => $locked->revision]);
            }

            $locked->fill($attributes);

            if ($locked->isDirty(['requested_quantity', 'unit_code', 'status'])) {
                $locked->needs_review = false;
            }

            if ($locked->isDirty()) {
                $locked->revision++;
                $locked->save();
            }

            return $locked->load(['category', 'product', 'addedBy']);
        });
    }
}
