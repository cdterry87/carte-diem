<?php

namespace App\Http\Requests\Purchases;

use App\Http\Requests\Concerns\ResolvesOwner;
use Illuminate\Foundation\Http\FormRequest;

class MovePurchaseRequest extends FormRequest
{
    use ResolvesOwner;

    /**
     * @return array<string, array<int, mixed>>
     */
    public function rules(): array
    {
        return [
            ...$this->ownerRules(),
            'allow_duplicate' => ['sometimes', 'boolean'],
            'revision' => ['sometimes', 'integer', 'min:1'],
        ];
    }
}
