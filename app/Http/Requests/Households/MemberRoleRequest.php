<?php

namespace App\Http\Requests\Households;

use App\Enums\HouseholdRole;
use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class MemberRoleRequest extends FormRequest
{
    /**
     * @return array<string, ValidationRule|array<mixed>|string>
     */
    public function rules(): array
    {
        return ['role' => ['required', Rule::enum(HouseholdRole::class)->except(HouseholdRole::Owner)]];
    }
}
