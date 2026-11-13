<?php

namespace App\Http\Requests\Purchases;

use App\Http\Requests\Concerns\ResolvesOwner;
use App\Models\User;
use Illuminate\Contracts\Validation\Validator;
use Illuminate\Foundation\Http\FormRequest;

class PurchaseRequest extends FormRequest
{
    use ResolvesOwner;

    private const MAX_MINOR = 99_999_999_99;

    /**
     * @return array<string, array<int, mixed>>
     */
    public function rules(): array
    {
        $creating = $this->isMethod('post');
        $amount = ['sometimes', 'nullable', 'integer', 'min:0', 'max:'.self::MAX_MINOR];

        return [
            ...($creating ? $this->ownerRules() : []),
            'merchant' => [$creating ? 'required' : 'sometimes', $creating ? 'filled' : 'nullable', 'string', 'max:160'],
            'purchased_on' => [$creating ? 'required' : 'sometimes', 'date_format:Y-m-d'],
            'subtotal_minor' => $amount,
            'tax_minor' => $amount,
            'discount_minor' => $amount,
            'total_minor' => $creating ? ['required', 'integer', 'min:0', 'max:'.self::MAX_MINOR] : $amount,
            'needs_review' => ['sometimes', 'boolean'],
            'items' => ['sometimes', 'array', 'max:200'],
            'items.*.uuid' => ['sometimes', 'nullable', 'uuid'],
            'items.*.description' => ['required', 'string', 'max:160'],
            'items.*.quantity' => ['sometimes', 'nullable', 'numeric', 'gt:0', 'max:999999'],
            'items.*.unit' => ['sometimes', 'nullable', 'string', 'max:16'],
            'items.*.category' => ['sometimes', 'nullable', 'uuid'],
            'items.*.line_total_minor' => $amount,
            'idempotency_key' => ['sometimes', 'nullable', 'string', 'max:64'],
            'allow_duplicate' => ['sometimes', 'boolean'],
            'revision' => ['sometimes', 'integer', 'min:1'],
        ];
    }

    public function withValidator(Validator $validator): void
    {
        $validator->after(function (Validator $validator): void {
            /** @var User $user */
            $user = $this->user();
            $date = $this->input('purchased_on');

            // Days are compared as text, since both sides are Y-m-d in the user's own zone.
            if (is_string($date) && $date > now($user->timezone)->toDateString()) {
                $validator->errors()->add('purchased_on', __('The purchase date cannot be in the future.'));
            }
        });
    }

    /**
     * @return array<string, mixed>
     */
    public function purchaseInput(): array
    {
        /** @var array<string, mixed> */
        return $this->safe()->only(['merchant', 'purchased_on', 'subtotal_minor', 'tax_minor', 'discount_minor', 'total_minor', 'needs_review', 'items']);
    }

    public function revision(): ?int
    {
        return $this->has('revision') ? $this->integer('revision') : null;
    }
}
