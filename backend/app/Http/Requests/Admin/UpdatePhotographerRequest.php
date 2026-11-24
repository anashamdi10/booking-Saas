<?php

declare(strict_types=1);

namespace App\Http\Requests\Admin;

use App\Enums\SubscriptionStatus;
use App\Enums\UserStatus;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class UpdatePhotographerRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user()?->isAdmin() ?? false;
    }

    /**
     * @return array<string, mixed>
     */
    public function rules(): array
    {
        return [
            'status' => ['sometimes', Rule::enum(UserStatus::class)],
            'plan_id' => ['sometimes', 'nullable', 'integer', 'exists:plans,id'],
            'subscription_status' => ['sometimes', Rule::enum(SubscriptionStatus::class)],
            'starts_at' => ['sometimes', 'nullable', 'date'],
            'ends_at' => ['sometimes', 'nullable', 'date', 'after:starts_at'],
            'storage_limit_bytes' => ['sometimes', 'nullable', 'integer', 'min:0'],
            'admin_notes' => ['nullable', 'string', 'max:1000'],
        ];
    }
}
