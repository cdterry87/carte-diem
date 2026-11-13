<?php

namespace App\Http\Requests\Lists;

use App\Http\Requests\Concerns\ResolvesOwner;
use Illuminate\Foundation\Http\FormRequest;

class StapleRuleRequest extends FormRequest
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
            ...($creating ? ['name' => ['required', 'string', 'max:160']] : []),
            'cadence_days' => ['sometimes', 'nullable', 'integer', 'min:1', 'max:365'],
            'prediction_enabled' => ['sometimes', 'boolean'],
            'reminder_enabled' => ['sometimes', 'boolean'],
            'snooze_days' => $creating ? ['prohibited'] : ['sometimes', 'nullable', 'integer', 'min:1', 'max:90'],
        ];
    }

    /**
     * @return array<string, mixed>
     */
    public function ruleAttributes(): array
    {
        $attributes = $this->safe()->only(['cadence_days', 'prediction_enabled', 'reminder_enabled']);

        if ($this->has('snooze_days')) {
            $attributes['snoozed_until'] = $this->filled('snooze_days') ? now()->addDays($this->integer('snooze_days')) : null;
        }

        return $attributes;
    }
}
