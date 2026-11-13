<?php

namespace App\Http\Requests\Lists;

use App\Enums\ListShareRole;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class ShareListRequest extends FormRequest
{
    /**
     * @return array<string, array<int, mixed>>
     */
    public function rules(): array
    {
        return [
            'email' => [$this->isMethod('post') ? 'required' : 'prohibited', 'email', 'max:255'],
            'role' => [$this->isMethod('post') ? 'sometimes' : 'required', Rule::enum(ListShareRole::class)],
        ];
    }

    public function role(): ListShareRole
    {
        return $this->enum('role', ListShareRole::class) ?? ListShareRole::Editor;
    }
}
