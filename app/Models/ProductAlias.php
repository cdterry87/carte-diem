<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Model;

/**
 * @property int $id
 * @property int $product_id
 * @property string $normalized_alias
 */
#[Fillable(['product_id', 'normalized_alias', 'source'])]
class ProductAlias extends Model {}
