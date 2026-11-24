<?php

declare(strict_types=1);

namespace App\Http\Requests\Photographer;

use App\Enums\LandingTemplate;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class UpdateProfileRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user() !== null;
    }

    /**
     * @return array<string, mixed>
     */
    public function rules(): array
    {
        $hex = ['regex:/^#[0-9a-fA-F]{6}$/'];

        return [
            'name' => ['sometimes', 'string', 'min:3', 'max:120'],
            'headline' => ['nullable', 'string', 'max:160'],
            'bio' => ['nullable', 'string', 'max:3000'],
            'city' => ['nullable', 'string', 'max:100'],
            'country' => ['nullable', 'string', 'size:2'],
            'phone' => ['nullable', 'string', 'max:32', 'regex:/^[0-9+\-\s()]+$/'],
            'whatsapp' => ['nullable', 'string', 'max:32', 'regex:/^[0-9+\-\s()]+$/'],
            'website' => ['nullable', 'url', 'max:255'],
            'years_experience' => ['nullable', 'integer', 'min:0', 'max:70'],
            'landing_template' => ['sometimes', Rule::enum(LandingTemplate::class)],
            'theme' => ['sometimes', 'array'],
            'theme.primary' => ['sometimes', 'string', ...$hex],
            'theme.accent' => ['sometimes', 'string', ...$hex],
            'theme.background' => ['sometimes', 'string', ...$hex],
            'theme.surface' => ['sometimes', 'string', ...$hex],
            'theme.text' => ['sometimes', 'string', ...$hex],
        ];
    }
}
