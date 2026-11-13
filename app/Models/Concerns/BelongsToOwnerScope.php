<?php

namespace App\Models\Concerns;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\MorphTo;

trait BelongsToOwnerScope
{
    /**
     * @return MorphTo<Model, $this>
     */
    public function scope(): MorphTo
    {
        return $this->morphTo('scope', 'scope_type', 'scope_id');
    }

    /**
     * @param  Builder<static>  $query
     * @return Builder<static>
     */
    public function scopeForOwner(Builder $query, Model $owner): Builder
    {
        return $query->where('scope_type', $owner->getMorphClass())->where('scope_id', $owner->getKey());
    }

    public function isOwnedBy(Model $owner): bool
    {
        return $this->scope_type === $owner->getMorphClass() && (int) $this->scope_id === (int) $owner->getKey();
    }
}
