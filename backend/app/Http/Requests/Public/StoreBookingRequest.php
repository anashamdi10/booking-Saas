<?php

declare(strict_types=1);

namespace App\Http\Requests\Public;

use App\Enums\BookingSource;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class StoreBookingRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    /**
     * @return array<string, mixed>
     */
    public function rules(): array
    {
        return [
            'photographer' => ['required', 'string', 'max:120'],
            'start_at' => ['required', 'date', 'after:now', 'before:+1 year'],
            'package_id' => ['nullable', 'integer'],
            'customer_name' => ['required', 'string', 'min:3', 'max:120'],
            'customer_email' => ['required', 'string', 'email:rfc', 'max:190'],
            'customer_phone' => ['nullable', 'string', 'max:32', 'regex:/^[0-9+\-\s()]+$/'],
            'notes' => ['nullable', 'string', 'max:1000'],
            'source' => ['sometimes', Rule::enum(BookingSource::class)],
            'website' => ['nullable', 'size:0'],
        ];
    }

    /**
     * @return array<string, string>
     */
    public function messages(): array
    {
        return [
            'start_at.after' => 'لا يمكن الحجز في وقت سابق.',
            'customer_email.email' => 'البريد الإلكتروني غير صالح.',
            'website.size' => 'تعذر إرسال الطلب.',
        ];
    }

    protected function prepareForValidation(): void
    {
        $this->merge([
            'customer_email' => is_string($this->customer_email)
                ? strtolower(trim($this->customer_email))
                : $this->customer_email,
        ]);
    }
}
