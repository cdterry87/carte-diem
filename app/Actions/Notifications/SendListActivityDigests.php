<?php

namespace App\Actions\Notifications;

use App\Models\ListActivityEvent;
use App\Models\ShoppingList;
use App\Models\User;
use App\Notifications\ListActivityDigest;
use App\Services\ScopeAudience;
use Illuminate\Support\Collection;

class SendListActivityDigests
{
    /**
     * Recent events wait, so a burst of edits becomes one notification.
     */
    private const SETTLE_MINUTES = 5;

    public function __construct(private readonly ScopeAudience $audience) {}

    /**
     * @return int Notifications sent.
     */
    public function handle(): int
    {
        $sent = 0;

        ListActivityEvent::query()
            ->whereNull('processed_at')
            ->where('created_at', '<=', now()->subMinutes(self::SETTLE_MINUTES))
            ->with('actor')
            ->get()
            ->groupBy('shopping_list_id')
            ->each(function (Collection $events, int|string $listId) use (&$sent): void {
                $sent += $this->digest((int) $listId, $events);

                ListActivityEvent::query()->whereKey($events->pluck('id'))->update(['processed_at' => now()]);
            });

        return $sent;
    }

    /**
     * @param  Collection<int, ListActivityEvent>  $events
     */
    private function digest(int $listId, Collection $events): int
    {
        $list = ShoppingList::query()->find($listId);

        if ($list === null) {
            return 0;
        }

        $sent = 0;

        foreach ($this->audience->forList($list) as $user) {
            $others = $events->reject(fn (ListActivityEvent $event): bool => $event->actor_id === $user->id);

            if ($others->isNotEmpty()) {
                /** @var User $user */
                $user->notify(new ListActivityDigest($list->title, $this->summary($others), '/lists/'.$list->uuid));
                $sent++;
            }
        }

        return $sent;
    }

    /**
     * Counts, not item names: this text also goes into email.
     *
     * @param  Collection<int, ListActivityEvent>  $events
     */
    private function summary(Collection $events): string
    {
        $actors = $events->map(fn (ListActivityEvent $event): string => $event->actor->name ?? __('Someone'))->unique()->values();
        $names = $actors->count() > 2 ? __(':first and :count others', ['first' => $actors->first(), 'count' => $actors->count() - 1]) : $actors->join(' and ');
        $added = $events->where('action', ListActivityEvent::ADDED)->count();
        $removed = $events->where('action', ListActivityEvent::REMOVED)->count();

        $changes = array_filter([
            $added > 0 ? trans_choice('added :count item|added :count items', $added, ['count' => $added]) : null,
            $removed > 0 ? trans_choice('removed :count item|removed :count items', $removed, ['count' => $removed]) : null,
        ]);

        return $names.' '.implode(' and ', $changes).'.';
    }
}
