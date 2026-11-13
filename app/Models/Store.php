<?php

namespace App\Models;

use App\Models\Concerns\BelongsToOwnerScope;
use App\Models\Concerns\HasPublicUuid;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Model;

/**
 * @property int $id
 * @property string $uuid
 * @property string $scope_type
 * @property int $scope_id
 * @property string $name
 * @property string|null $location
 */
#[Fillable(['scope_type', 'scope_id', 'name', 'location'])]
class Store extends Model
{
    use BelongsToOwnerScope, HasPublicUuid;
}
