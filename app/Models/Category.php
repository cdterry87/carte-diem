<?php

namespace App\Models;

use App\Models\Concerns\BelongsToOwnerScope;
use App\Models\Concerns\HasPublicUuid;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;

/**
 * @property int $id
 * @property string $uuid
 * @property string $name
 * @property string $slug
 * @property string $scope_type
 * @property int|null $scope_id
 * @property int $sort_order
 * @property bool $is_active
 */
#[Fillable(['name', 'slug', 'scope_type', 'scope_id', 'sort_order', 'icon', 'is_active'])]
class Category extends Model
{
    use BelongsToOwnerScope, HasPublicUuid;

    public const SYSTEM = 'system';

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return ['is_active' => 'boolean'];
    }

    /**
     * Active system categories plus those custom to the given owner.
     *
     * @param  Builder<self>  $query
     * @return Builder<self>
     */
    public function scopeAvailableTo(Builder $query, Model $owner): Builder
    {
        return $query->where('is_active', true)->where(function (Builder $query) use ($owner) {
            $query->where('scope_type', self::SYSTEM)->orWhere(function (Builder $query) use ($owner) {
                $query->where('scope_type', $owner->getMorphClass())->where('scope_id', $owner->getKey());
            });
        })->orderBy('sort_order')->orderBy('name');
    }

    public function isSystem(): bool
    {
        return $this->scope_type === self::SYSTEM;
    }
}
