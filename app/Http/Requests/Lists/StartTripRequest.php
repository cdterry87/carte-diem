<?php

namespace App\Http\Requests\Lists;

use App\Models\ShoppingList;
use App\Models\Store;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\ValidationException;

class StartTripRequest extends FormRequest
{
    /**
     * @return array<string, array<int, mixed>>
     */
    public function rules(): array
    {
        return [
            'store' => ['sometimes', 'nullable', 'uuid'],
            'no_store' => ['sometimes', 'boolean'],
            'items' => ['sometimes', 'array', 'min:1'],
            'items.*' => ['uuid'],
        ];
    }

    public function store(): ?Store
    {
        $uuid = $this->input('store');
        /** @var ShoppingList $list */
        $list = $this->route('list');

        if ($uuid === null || $list->owner === null) {
            return null;
        }

        $store = Store::query()->forOwner($list->owner)->where('uuid', $uuid)->first();

        if ($store === null) {
            throw ValidationException::withMessages(['store' => __('That store is not available for this list.')]);
        }

        return $store;
    }

    /**
     * @return list<string>|null
     */
    public function itemUuids(): ?array
    {
        /** @var list<string>|null */
        return $this->input('items');
    }
}
