<?php

namespace App\Actions\Notifications;

use App\Enums\NotificationType;
use App\Models\Household;
use App\Models\RecommendationDismissal;
use App\Models\StapleRule;
use App\Models\User;
use App\Notifications\StapleRemindersDue;
use App\Services\NotificationChannels;
use App\Services\PurchaseStats;
use App\Services\Recommendations;
use App\Services\ScopeAudience;
use App\Services\StapleSchedule;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;

class SendStapleReminders
{
    private const MINIMUM_WINDOW_DAYS = 7;

    public function __construct(
        private readonly PurchaseStats $stats,
        private readonly StapleSchedule $schedule,
        private readonly Recommendations $recommendations,
        private readonly ScopeAudience $audience,
        private readonly NotificationChannels $channels,
    ) {}

    /**
     * Sends one batched reminder per person per scope, and at most one per staple per window.
     *
     * @return int Reminders sent.
     */
    public function handle(): int
    {
        $sent = 0;

        StapleRule::query()
            ->where('reminder_enabled', true)
            ->where(fn ($rules) => $rules->whereNull('snoozed_until')->orWhere('snoozed_until', '<=', now()))
            ->with(['product', 'owner'])
            ->get()
            ->groupBy(fn (StapleRule $rule): string => $rule->owner_type.':'.$rule->owner_id)
            ->each(function (Collection $rules) use (&$sent): void {
                /** @var StapleRule $first */
                $first = $rules->first();

                if ($first->owner !== null) {
                    $sent += $this->remindOwner($first->owner, $rules);
                }
            });

        return $sent;
    }

    /**
     * @param  Collection<int, StapleRule>  $rules
     */
    private function remindOwner(Model $owner, Collection $rules): int
    {
        $stats = $this->stats->forOwner($owner);
        $needed = $this->recommendations->neededProductIds($owner);

        $due = $rules
            ->reject(fn (StapleRule $rule): bool => $needed->contains($rule->product_id))
            ->filter(fn (StapleRule $rule): bool => $this->schedule->isDue($rule, $stats->get($rule->product_id)));

        if ($due->isEmpty()) {
            return 0;
        }

        $sent = 0;

        foreach ($this->audience->forOwner($owner) as $user) {
            $sent += $this->remindUser($user, $owner, $due) ? 1 : 0;
        }

        return $sent;
    }

    /**
     * @param  Collection<int, StapleRule>  $due
     */
    private function remindUser(User $user, Model $owner, Collection $due): bool
    {
        // Held for the next run, so a reminder is delayed by quiet hours rather than lost.
        if ($this->channels->isQuiet($user) || $this->channels->for($user, NotificationType::ReminderDue) === []) {
            return false;
        }

        $dismissed = RecommendationDismissal::query()->where('user_id', $user->id)->inEffect()->pluck('product_id');
        $recent = DB::table('staple_reminder_deliveries')->where('user_id', $user->id)->whereIn('staple_rule_id', $due->pluck('id'))->pluck('sent_at', 'staple_rule_id');

        $rules = $due
            ->reject(fn (StapleRule $rule): bool => $dismissed->contains($rule->product_id))
            ->filter(function (StapleRule $rule) use ($recent): bool {
                $sentAt = $recent->get($rule->id);
                $window = max($rule->cadence_days ?? 0, self::MINIMUM_WINDOW_DAYS);

                return $sentAt === null || now()->parse($sentAt)->addDays($window)->isPast();
            })
            ->values();

        if ($rules->isEmpty()) {
            return false;
        }

        $user->notify(new StapleRemindersDue(
            $owner instanceof Household ? $owner->name : __('Personal'),
            $rules->map(fn (StapleRule $rule): string => $rule->product->canonical_name)->all(),
            $owner instanceof Household ? '/staples?household='.$owner->uuid : '/staples',
        ));

        foreach ($rules as $rule) {
            DB::table('staple_reminder_deliveries')->updateOrInsert(
                ['staple_rule_id' => $rule->id, 'user_id' => $user->id],
                ['sent_at' => now()],
            );
        }

        return true;
    }
}
