<?php

namespace App\Services;

use App\Enums\ListItemStatus;
use App\Models\ShoppingListItem;
use App\Models\ShoppingTripItem;

class TripReconciler
{
    /**
     * Apply a purchased trip item to its persistent list item. See "Shopping trips"
     * in docs/decisions.md. Unpurchased trip items never reach this method.
     */
    public function apply(ShoppingTripItem $tripItem, ShoppingListItem $listItem): void
    {
        $requested = $this->thousandths($listItem->requested_quantity);
        $bought = $this->thousandths($tripItem->actual_quantity ?? $tripItem->planned_quantity);

        match (true) {
            $requested === null => $this->satisfy($listItem),
            $listItem->unit_code !== $tripItem->planned_unit => $this->flag($listItem),
            $bought === null => $this->flag($listItem),
            $bought >= $requested => $this->satisfy($listItem),
            default => $this->keepRemainder($listItem, $requested - $bought),
        };

        if ($listItem->isDirty()) {
            $listItem->revision++;
            $listItem->save();
        }
    }

    private function satisfy(ShoppingListItem $item): void
    {
        $item->status = ListItemStatus::Satisfied;
        $item->needs_review = false;
    }

    private function flag(ShoppingListItem $item): void
    {
        $item->status = ListItemStatus::Needed;
        $item->needs_review = true;
    }

    private function keepRemainder(ShoppingListItem $item, int $remaining): void
    {
        $item->status = ListItemStatus::Needed;
        $item->requested_quantity = number_format($remaining / 1000, 3, '.', '');
    }

    private function thousandths(?string $quantity): ?int
    {
        return $quantity === null ? null : (int) round(((float) $quantity) * 1000);
    }
}
