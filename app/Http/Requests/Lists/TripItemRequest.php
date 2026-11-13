<?php

namespace App\Http\Requests\Lists;

use App\Models\ShoppingTrip;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Support\Facades\Gate;

class TripItemRequest extends FormRequest
{
    /**
     * @return array<string, array<int, mixed>>
     */
    public function rules(): array
    {
        return [
            'purchased' => ['sometimes', 'boolean'],
            'actual_quantity' => ['sometimes', 'nullable', 'numeric', 'gt:0', 'max:999999'],
            'line_total_minor' => ['sometimes', 'nullable', 'integer', 'min:0', 'max:99999999999'],
            'notes' => ['sometimes', 'nullable', 'string', 'max:1000'],
            'revision' => ['sometimes', 'integer', 'min:1'],
        ];
    }

    public function authorizeAmounts(ShoppingTrip $trip): void
    {
        if ($this->has('line_total_minor')) {
            Gate::authorize('manageAmounts', $trip);
        }
    }

    /**
     * @return array{purchased?: bool, actual_quantity?: string|float|int|null, line_total_minor?: int|null, notes?: string|null}
     */
    public function tripAttributes(): array
    {
        /** @var array{purchased?: bool, actual_quantity?: string|float|int|null, line_total_minor?: int|null, notes?: string|null} */
        return $this->safe()->only(['purchased', 'actual_quantity', 'line_total_minor', 'notes']);
    }
}
