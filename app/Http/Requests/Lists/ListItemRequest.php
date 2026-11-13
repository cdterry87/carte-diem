<?php

namespace App\Http\Requests\Lists;

use App\Enums\ListItemStatus;
use App\Models\Category;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;
use Illuminate\Validation\ValidationException;

class ListItemRequest extends FormRequest
{
    /**
     * @return array<string, array<int, mixed>>
     */
    public function rules(): array
    {
        $creating = $this->isMethod('post');

        return [
            'name' => [$creating ? 'required' : 'sometimes', 'string', 'min:1', 'max:160'],
            'quantity' => ['sometimes', 'nullable', 'numeric', 'gt:0', 'max:999999'],
            'unit' => ['sometimes', 'nullable', 'string', 'max:16'],
            'category' => ['sometimes', 'nullable', 'uuid'],
            'notes' => ['sometimes', 'nullable', 'string', 'max:1000'],
            'status' => ['sometimes', Rule::enum(ListItemStatus::class)],
            'revision' => ['sometimes', 'integer', 'min:1'],
        ];
    }

    public function categoryFor(Model $owner): ?Category
    {
        $uuid = $this->input('category');

        if ($uuid === null) {
            return null;
        }

        $category = Category::query()->availableTo($owner)->where('uuid', $uuid)->first();

        if ($category === null) {
            throw ValidationException::withMessages(['category' => __('That category is not available for this list.')]);
        }

        return $category;
    }

    /**
     * @return array<string, mixed>
     */
    public function itemAttributes(Model $owner): array
    {
        return $this->mapped($owner, ['name' => 'name_snapshot', 'quantity' => 'requested_quantity', 'unit' => 'unit_code', 'notes' => 'notes', 'status' => 'status']);
    }

    /**
     * @param  array<string, string>  $columns
     * @return array<string, mixed>
     */
    private function mapped(Model $owner, array $columns): array
    {
        $attributes = [];

        foreach ($columns as $input => $column) {
            if ($this->has($input)) {
                $attributes[$column] = $this->input($input);
            }
        }

        if ($this->has('category')) {
            $attributes['category_id'] = $this->categoryFor($owner)?->id;
        }

        return $attributes;
    }
}
