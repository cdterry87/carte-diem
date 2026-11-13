<?php

namespace App\Http\Requests\Concerns;

use App\Models\User;
use App\Services\OwnerResolver;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Validation\Rule;

trait ResolvesOwner
{
    /**
     * @return array<string, array<int, mixed>>
     */
    protected function ownerRules(bool $required = true): array
    {
        return [
            'owner' => [$required ? 'required' : 'sometimes', 'array'],
            'owner.type' => [$required ? 'required' : 'sometimes', Rule::in(['user', 'household'])],
            'owner.uuid' => ['nullable', 'uuid'],
        ];
    }

    public function owner(): Model
    {
        /** @var User $user */
        $user = $this->user();

        return app(OwnerResolver::class)->resolve(
            $user,
            $this->string('owner.type')->toString() ?: 'user',
            $this->input('owner.uuid'),
        );
    }
}
