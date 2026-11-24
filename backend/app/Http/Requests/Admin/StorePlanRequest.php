<?php

declare(strict_types=1);

namespace App\Http\Requests\Admin;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class StorePlanRequest extends FormRequest
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
            // Plans are distinguished by name in every picker, so a second
            // plan with the same name would be impossible to tell apart.
            'name' => [
                'required',
                'string',
                'min:2',
                'max:120',
                Rule::unique('plans', 'name')->ignore($this->route('plan')),
            ],

            'description' => ['nullable', 'string', 'max:2000'],
            'price' => ['required', 'numeric', 'min:0', 'max:9999999'],
            'currency' => ['sometimes', 'string', 'size:3', 'in:SAR,AED,EGP,KWD,QAR,USD'],

            // The database stores bytes; the admin thinks in gigabytes, so the
            // conversion happens once here instead of in every caller.
            'storage_limit_gb' => ['required', 'numeric', 'min:0.1', 'max:100000'],

            'max_galleries' => ['nullable', 'integer', 'min:0', 'max:100000'],
            'max_clients' => ['nullable', 'integer', 'min:0', 'max:1000000'],
            'max_bookings_per_month' => ['nullable', 'integer', 'min:0', 'max:1000000'],

            'features' => ['sometimes', 'array', 'max:30'],
            'features.*' => ['string', 'max:120'],

            'is_active' => ['sometimes', 'boolean'],
            'sort_order' => ['sometimes', 'integer', 'min:0', 'max:9999'],
        ];
    }

    /**
     * @return array<string, string>
     */
    public function messages(): array
    {
        return [
            'name.required' => 'اسم الباقة مطلوب.',
            'name.unique' => 'توجد باقة بهذا الاسم مسبقاً — اختر اسماً مميزاً.',
            'price.required' => 'سعر الاشتراك مطلوب.',
            'price.numeric' => 'أدخل سعراً رقمياً.',
            'storage_limit_gb.required' => 'مساحة التخزين مطلوبة.',
            'storage_limit_gb.min' => 'المساحة يجب أن تكون أكبر من صفر جيجابايت.',
            'currency.in' => 'العملة غير مدعومة.',
        ];
    }
}
