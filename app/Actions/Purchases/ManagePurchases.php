<?php

namespace App\Actions\Purchases;

use App\Enums\EntryMethod;
use App\Enums\VerificationStatus;
use App\Exceptions\DomainConflictException;
use App\Exceptions\SuspectedDuplicateException;
use App\Models\AuditLog;
use App\Models\Category;
use App\Models\Product;
use App\Models\PurchaseTransaction;
use App\Models\PurchaseTransactionItem;
use App\Models\Store;
use App\Models\User;
use App\Services\OwnerTimezone;
use App\Services\ProductResolver;
use Carbon\CarbonImmutable;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\UniqueConstraintViolationException;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;

/**
 * Standalone purchases and corrections. Spending is summed at read time from the single
 * stored transaction, so a correction replaces its amounts and can never count twice.
 *
 * @phpstan-type PurchaseLine array{uuid?: string|null, description: string, quantity?: string|float|int|null, unit?: string|null, category?: string|null, line_total_minor?: int|null}
 * @phpstan-type PurchaseInput array{merchant?: string|null, purchased_on?: string, subtotal_minor?: int|null, tax_minor?: int|null, discount_minor?: int|null, total_minor?: int|null, needs_review?: bool, items?: array<int, PurchaseLine>}
 */
class ManagePurchases
{
    private const AMOUNT_FIELDS = ['subtotal_minor', 'tax_minor', 'discount_minor', 'total_minor'];

    public function __construct(private readonly ProductResolver $products) {}

    /**
     * @param  PurchaseInput  $input
     */
    public function create(Model $owner, User $user, array $input, ?string $idempotencyKey = null, bool $allowDuplicate = false): PurchaseTransaction
    {
        $identifier = $idempotencyKey !== null
            ? hash('sha256', $user->id.':'.$idempotencyKey)
            : (string) Str::uuid7();

        $existing = $this->findRepeat($identifier);

        if ($existing !== null) {
            return $existing;
        }

        try {
            return DB::transaction(function () use ($owner, $user, $input, $idempotencyKey, $identifier, $allowDuplicate): PurchaseTransaction {
                $purchasedAt = $this->noonOn($input['purchased_on'] ?? now($user->timezone)->toDateString(), $user);
                $merchant = $this->cleanMerchant($input['merchant'] ?? null);

                if (! $allowDuplicate) {
                    $this->assertNotDuplicate($owner, $purchasedAt, $input['total_minor'] ?? null, $merchant);
                }

                $purchase = PurchaseTransaction::query()->create([
                    'owner_type' => $owner->getMorphClass(),
                    'owner_id' => $owner->getKey(),
                    'store_id' => $this->storeIdFor($owner, $merchant),
                    'merchant_name_snapshot' => $merchant,
                    'purchased_at' => $purchasedAt,
                    'currency_code' => PurchaseTransaction::currencyFor($owner),
                    'subtotal_minor' => $input['subtotal_minor'] ?? null,
                    'tax_minor' => $input['tax_minor'] ?? null,
                    'discount_minor' => $input['discount_minor'] ?? null,
                    'total_minor' => $input['total_minor'] ?? null,
                    'entry_method' => EntryMethod::Manual,
                    'verification_status' => ($input['needs_review'] ?? false) ? VerificationStatus::NeedsReview : VerificationStatus::Verified,
                    'source_kind' => 'manual',
                    'source_identifier' => $identifier,
                    'idempotency_key' => $idempotencyKey,
                    'created_by' => $user->id,
                ]);

                $this->syncItems($purchase, $owner, $input['items'] ?? []);

                AuditLog::record($user, 'purchase.created', $purchase);

                return $purchase;
            });
        } catch (UniqueConstraintViolationException $exception) {
            return $this->findRepeat($identifier) ?? throw $exception;
        }
    }

    /**
     * @param  PurchaseInput  $input
     */
    public function update(PurchaseTransaction $purchase, User $user, array $input, ?int $expectedRevision = null, bool $allowDuplicate = false): PurchaseTransaction
    {
        return DB::transaction(function () use ($purchase, $user, $input, $expectedRevision, $allowDuplicate): PurchaseTransaction {
            /** @var PurchaseTransaction $locked */
            $locked = PurchaseTransaction::query()->whereKey($purchase->id)->lockForUpdate()->firstOrFail();

            if ($expectedRevision !== null && $locked->revision !== $expectedRevision) {
                throw new DomainConflictException('This purchase changed since you loaded it.', 'stale_revision', ['revision' => $locked->revision]);
            }

            /** @var Model $owner */
            $owner = $locked->owner;

            if (array_key_exists('merchant', $input)) {
                $merchant = $this->cleanMerchant($input['merchant']);
                $locked->merchant_name_snapshot = $merchant;
                $locked->store_id = $this->storeIdFor($owner, $merchant);
            }

            if (isset($input['purchased_on']) && $input['purchased_on'] !== $locked->purchased_at->setTimezone($user->timezone)->toDateString()) {
                $locked->setAttribute('purchased_at', $this->noonOn($input['purchased_on'], $user));
            }

            foreach (self::AMOUNT_FIELDS as $field) {
                if (array_key_exists($field, $input)) {
                    $locked->setAttribute($field, $input[$field]);
                }
            }

            if (array_key_exists('needs_review', $input)) {
                $locked->verification_status = $input['needs_review'] ? VerificationStatus::NeedsReview : VerificationStatus::Verified;
            }

            if (! $allowDuplicate && $locked->isDirty(['total_minor', 'purchased_at', 'merchant_name_snapshot'])) {
                $this->assertNotDuplicate($owner, CarbonImmutable::parse($locked->purchased_at), $locked->total_minor, $locked->merchant_name_snapshot, $locked->id);
            }

            $changed = array_keys($locked->getDirty());
            $amounts = array_intersect_key($locked->getOriginal(), array_flip(array_intersect($changed, self::AMOUNT_FIELDS)));
            $itemsChanged = isset($input['items']) && $this->syncItems($locked, $owner, $input['items']);

            if ($changed === [] && ! $itemsChanged) {
                return $locked;
            }

            $locked->revision++;
            $locked->save();

            AuditLog::record($user, 'purchase.corrected', $locked, array_filter([
                'changed' => $changed,
                'previous_amounts' => $amounts,
                'items_changed' => $itemsChanged,
            ]));

            return $locked;
        });
    }

    public function delete(PurchaseTransaction $purchase, User $user): void
    {
        $purchase->delete();
        AuditLog::record($user, 'purchase.deleted', $purchase);
    }

    public function restore(PurchaseTransaction $purchase, User $user): void
    {
        $purchase->restore();
        AuditLog::record($user, 'purchase.restored', $purchase);
    }

    /**
     * Moves a purchase to another space. Stores, products, and custom categories
     * are scoped, so each is matched again there. A trip purchase stays with its trip's list.
     */
    public function move(PurchaseTransaction $purchase, Model $target, User $user, ?int $expectedRevision = null, bool $allowDuplicate = false): PurchaseTransaction
    {
        return DB::transaction(function () use ($purchase, $target, $user, $expectedRevision, $allowDuplicate): PurchaseTransaction {
            /** @var PurchaseTransaction $locked */
            $locked = PurchaseTransaction::query()->whereKey($purchase->id)->lockForUpdate()->firstOrFail();

            if ($expectedRevision !== null && $locked->revision !== $expectedRevision) {
                throw new DomainConflictException('This purchase changed since you loaded it.', 'stale_revision', ['revision' => $locked->revision]);
            }

            if ($locked->shopping_trip_id !== null) {
                throw new DomainConflictException('A purchase from a trip stays with that trip.', 'trip_purchase');
            }

            if ($locked->owner_type === $target->getMorphClass() && $locked->owner_id === $target->getKey()) {
                return $locked;
            }

            if (! $allowDuplicate) {
                $this->assertNotDuplicate($target, CarbonImmutable::parse($locked->purchased_at), $locked->total_minor, $locked->merchant_name_snapshot);
            }

            $from = ['type' => $locked->owner_type, 'id' => $locked->owner_id];

            $locked->items()->with('category')->get()->each(function (PurchaseTransactionItem $item) use ($target): void {
                $product = $this->products->resolve($target, $item->description_snapshot);
                $item->product_id = $product->id;

                if ($item->category === null || ! $item->category->isSystem()) {
                    $this->applyCategory($item, $target, null, $product->default_category_id, 0);
                }

                $item->save();
            });

            $locked->update([
                'owner_type' => $target->getMorphClass(),
                'owner_id' => $target->getKey(),
                'store_id' => $this->storeIdFor($target, $locked->merchant_name_snapshot),
                'revision' => $locked->revision + 1,
            ]);
            $locked->unsetRelation('owner');

            AuditLog::record($user, 'purchase.moved', $locked, ['from' => $from]);

            return $locked;
        });
    }

    private function findRepeat(string $identifier): ?PurchaseTransaction
    {
        return PurchaseTransaction::withTrashed()
            ->where('source_kind', 'manual')
            ->where('source_identifier', $identifier)
            ->first();
    }

    /**
     * A date alone is stored at noon in the entering user's zone, so it reads as the same day for nearby viewers.
     */
    private function noonOn(string $date, User $user): CarbonImmutable
    {
        return CarbonImmutable::parse($date.' 12:00:00', $user->timezone)->utc();
    }

    private function cleanMerchant(?string $merchant): ?string
    {
        $merchant = trim((string) $merchant);

        return $merchant === '' ? null : $merchant;
    }

    private function storeIdFor(Model $owner, ?string $merchant): ?int
    {
        if ($merchant === null) {
            return null;
        }

        /** @var int|null */
        return Store::query()->forOwner($owner)->where('name', $merchant)->value('id');
    }

    /**
     * Same scope, same day, and the same merchant with the same total is flagged, never the total alone.
     * A trip purchase with no amount yet also counts, since its total likely belongs there instead.
     */
    private function assertNotDuplicate(Model $owner, CarbonImmutable $purchasedAt, ?int $total, ?string $merchant, ?int $ignoreId = null): void
    {
        if ($total === null) {
            return;
        }

        $day = $purchasedAt->setTimezone(OwnerTimezone::of($owner));

        $match = PurchaseTransaction::query()
            ->where('owner_type', $owner->getMorphClass())
            ->where('owner_id', $owner->getKey())
            ->whereBetween('purchased_at', [$day->startOfDay()->utc(), $day->endOfDay()->utc()])
            ->when($ignoreId !== null, fn (Builder $query) => $query->whereKeyNot($ignoreId))
            ->where(fn (Builder $query) => $query->where('total_minor', $total)
                ->orWhere(fn (Builder $trip) => $trip->whereNull('total_minor')->where('entry_method', EntryMethod::Trip)))
            ->when($merchant !== null, fn (Builder $query) => $query->where(fn (Builder $names) => $names
                ->where('merchant_name_snapshot', $merchant)
                ->orWhereNull('merchant_name_snapshot')))
            ->first();

        if ($match !== null) {
            $message = $match->total_minor === null
                ? 'A completed trip from that day has no amount yet. Add the total there, or save this as a separate purchase.'
                : 'A purchase with the same merchant, date, and total already exists. Save this anyway only if it was a separate purchase.';

            throw new SuspectedDuplicateException($message, 'possible_duplicate_purchase', ['purchase' => $match->uuid]);
        }
    }

    /**
     * Replaces the lines with those given. A line keeps its product unless its description changed,
     * and new descriptions resolve to products so manual purchases feed suggestions too.
     *
     * @param  array<int, PurchaseLine>  $lines
     */
    private function syncItems(PurchaseTransaction $purchase, Model $owner, array $lines): bool
    {
        $existing = $purchase->items()->get()->keyBy('uuid');
        $kept = [];
        $changed = false;

        foreach ($lines as $index => $line) {
            $description = trim($line['description']);
            $item = isset($line['uuid']) ? $existing->get($line['uuid']) : null;

            if (isset($line['uuid']) && $item === null) {
                throw ValidationException::withMessages(["items.{$index}.uuid" => __('That line is not on this purchase.')]);
            }

            /** @var PurchaseTransactionItem $model */
            $model = $item ?? new PurchaseTransactionItem(['purchase_transaction_id' => $purchase->id, 'is_verified' => true]);
            $product = null;

            if ($item === null || $item->description_snapshot !== $description) {
                $product = $this->products->resolve($owner, $description);
                $model->product_id = $product->id;
            }

            $model->description_snapshot = $description;
            $model->quantity = isset($line['quantity']) ? (string) $line['quantity'] : null;
            $model->unit_code = $line['unit'] ?? null;
            $model->line_total_minor = $line['line_total_minor'] ?? null;

            if (array_key_exists('category', $line) || $item === null) {
                $default = $product->default_category_id ?? Product::query()->whereKey($model->product_id)->value('default_category_id');
                $this->applyCategory($model, $owner, $line['category'] ?? null, $default, $index);
            }

            $changed = $changed || ! $model->exists || $model->isDirty();
            $model->save();
            $kept[] = $model->id;
        }

        $removed = $purchase->items()->whereNotIn('id', $kept)->delete();

        return $changed || $removed > 0;
    }

    private function applyCategory(PurchaseTransactionItem $item, Model $owner, ?string $uuid, ?int $defaultId, int $index): void
    {
        $category = null;

        if ($uuid !== null) {
            $category = Category::query()->availableTo($owner)->where('uuid', $uuid)->first();

            if ($category === null) {
                throw ValidationException::withMessages(["items.{$index}.category" => __('That category is not available here.')]);
            }
        } elseif ($defaultId !== null) {
            $category = Category::query()->find($defaultId);
        }

        $item->category_id = $category?->id;
        $item->category_name_snapshot = $category?->name;
    }
}
