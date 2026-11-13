<?php

namespace App\Services;

use App\Models\StapleRule;

class StapleSchedule
{
    /**
     * A staple is due once the longest applicable wait has passed: its manual cadence,
     * and its typical purchase interval when predictions are on. With neither, it is never due.
     * Time is measured from the last purchase, or from when the rule was created.
     */
    public function isDue(StapleRule $rule, ?ProductPurchaseStat $stat): bool
    {
        $interval = $rule->prediction_enabled ? $stat?->medianIntervalDays : null;
        $wait = max($rule->cadence_days ?? 0, $interval ?? 0);

        if ($wait <= 0) {
            return false;
        }

        $since = $stat !== null ? $stat->lastPurchasedAt : $rule->created_at;

        return $since !== null && $since->diffInDays(now(), true) >= $wait;
    }
}
