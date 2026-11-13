<?php

namespace App\Http\Requests\Lists;

use App\Http\Requests\Concerns\ResolvesOwner;
use App\Models\ShoppingList;
use App\Models\Store;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\ValidationException;

class ShoppingListRequest extends FormRequest
{
    use ResolvesOwner;

    /**
     * @return array<string, array<int, mixed>>
     */
    public function rules(): array
    {
        $creating = $this->isMethod('post');

        return [
            ...($creating ? $this->ownerRules() : []),
            'title' => [$creating ? 'required' : 'sometimes', 'string', 'max:160'],
            'description' => ['sometimes', 'nullable', 'string', 'max:2000'],
            'default_store' => ['sometimes', 'nullable', 'uuid'],
        ];
    }

    /**
     * @return array<string, mixed>
     */
    public function listAttributes(Model $owner): array
    {
        $attributes = $this->safe()->only(['title', 'description']);

        if ($this->has('default_store')) {
            $attributes['default_store_id'] = $this->storeFor($owner)?->id;
        }

        return $attributes;
    }

    private function storeFor(Model $owner): ?Store
    {
        $uuid = $this->input('default_store');

        if ($uuid === null) {
            return null;
        }

        $store = Store::query()->forOwner($owner)->where('uuid', $uuid)->first();

        if ($store === null) {
            throw ValidationException::withMessages(['default_store' => __('That store is not available for this list.')]);
        }

        return $store;
    }

    public function targetOwner(): Model
    {
        $list = $this->route('list');

        if ($list instanceof ShoppingList && $list->owner !== null) {
            return $list->owner;
        }

        return $this->owner();
    }
}
