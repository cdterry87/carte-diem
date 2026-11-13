<?php

namespace App\Http\Requests\Households;

use App\Enums\HouseholdRole;
use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class InviteMemberRequest extends FormRequest
{
    /**
     * @return array<string, ValidationRule|array<mixed>|string>
     */
    public function rules(): array
    {
        return [
            'email' => ['required', 'email', 'max:255'],
            'role' => ['sometimes', Rule::enum(HouseholdRole::class)->except(HouseholdRole::Owner)],
        ];
    }

    public function role(): HouseholdRole
    {
        return $this->enum('role', HouseholdRole::class) ?? HouseholdRole::Member;
    }
}
