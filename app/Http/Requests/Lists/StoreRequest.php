<?php

namespace App\Http\Requests\Lists;

use App\Http\Requests\Concerns\ResolvesOwner;
use Illuminate\Foundation\Http\FormRequest;

class StoreRequest extends FormRequest
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
            'name' => [$creating ? 'required' : 'sometimes', 'string', 'max:120'],
            'location' => ['nullable', 'string', 'max:255'],
        ];
    }
}
