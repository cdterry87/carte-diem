<?php

namespace App\Actions\Trips;

use App\Enums\TripStatus;
use App\Exceptions\DomainConflictException;
use App\Models\ShoppingTrip;
use Illuminate\Support\Facades\DB;

class CancelShoppingTrip
{
    public function handle(ShoppingTrip $trip): ShoppingTrip
    {
        return DB::transaction(function () use ($trip): ShoppingTrip {
            /** @var ShoppingTrip $locked */
            $locked = ShoppingTrip::query()->whereKey($trip->id)->lockForUpdate()->firstOrFail();

            if ($locked->status === TripStatus::Completed) {
                throw new DomainConflictException('A completed trip cannot be cancelled.', 'trip_ended');
            }

            if ($locked->status === TripStatus::Active) {
                $locked->update(['status' => TripStatus::Cancelled, 'cancelled_at' => now()]);
            }

            return $locked;
        });
    }
}
