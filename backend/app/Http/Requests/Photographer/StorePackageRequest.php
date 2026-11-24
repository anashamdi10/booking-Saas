<?php

declare(strict_types=1);

namespace App\Http\Requests\Photographer;

use Illuminate\Foundation\Http\FormRequest;

class StorePackageRequest extends FormRequest
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
        return [
            'name' => ['required', 'string', 'min:2', 'max:120'],
            'description' => ['nullable', 'string', 'max:2000'],
            'price' => ['required', 'numeric', 'min:0', 'max:9999999'],
            'currency' => ['sometimes', 'string', 'size:3', 'in:SAR,AED,EGP,KWD,QAR,USD'],
            'duration_minutes' => ['required', 'integer', 'min:15', 'max:1440'],
            'max_photos' => ['nullable', 'integer', 'min:1', 'max:10000'],
            'deliverables' => ['sometimes', 'array', 'max:30'],
            'deliverables.*' => ['string', 'max:120'],
            'is_active' => ['sometimes', 'boolean'],
            'sort_order' => ['sometimes', 'integer', 'min:0', 'max:9999'],
        ];
    }
}
