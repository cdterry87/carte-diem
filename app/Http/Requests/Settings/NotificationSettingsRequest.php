<?php

namespace App\Http\Requests\Settings;

use App\Enums\NotificationType;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class NotificationSettingsRequest extends FormRequest
{
    /**
     * @return array<string, array<int, mixed>>
     */
    public function rules(): array
    {
        return [
            'preferences' => ['sometimes', 'array'],
            'preferences.*.type' => ['required', Rule::enum(NotificationType::class)],
            'preferences.*.in_app' => ['required', 'boolean'],
            'preferences.*.email' => ['required', 'boolean'],
            'quiet_start' => ['sometimes', 'nullable', 'date_format:H:i', 'required_with:quiet_end'],
            'quiet_end' => ['sometimes', 'nullable', 'date_format:H:i', 'required_with:quiet_start'],
        ];
    }
}
