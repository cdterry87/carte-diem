<?php

namespace App\Actions\Recommendations;

use App\Models\Product;
use App\Models\RecommendationDismissal;
use App\Models\User;

class DismissRecommendation
{
    private const DISMISS_DAYS = 30;

    private const SNOOZE_DAYS = 7;

    public function handle(User $user, Product $product, string $reason): void
    {
        RecommendationDismissal::query()->updateOrCreate(
            ['user_id' => $user->id, 'product_id' => $product->id],
            [
                'reason' => $reason,
                'dismissed_until' => match ($reason) {
                    RecommendationDismissal::SNOOZE => now()->addDays(self::SNOOZE_DAYS),
                    RecommendationDismissal::DISMISS => now()->addDays(self::DISMISS_DAYS),
                    default => null,
                },
            ],
        );
    }

    public function lift(User $user, Product $product): void
    {
        RecommendationDismissal::query()->where('user_id', $user->id)->where('product_id', $product->id)->delete();
    }
}
