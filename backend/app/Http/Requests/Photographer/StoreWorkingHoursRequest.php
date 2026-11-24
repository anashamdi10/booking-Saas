<?php

declare(strict_types=1);

namespace App\Http\Requests\Photographer;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Validator;

class StoreWorkingHoursRequest extends FormRequest
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
            'hours' => ['required', 'array', 'max:21'],
            'hours.*.weekday' => ['required', 'integer', 'between:0,6'],
            'hours.*.start_time' => ['required', 'string', 'regex:/^([01]\d|2[0-3]):[0-5]\d$/'],
            'hours.*.end_time' => ['required', 'string', 'regex:/^([01]\d|2[0-3]):[0-5]\d$/'],
            'hours.*.slot_interval_minutes' => ['sometimes', 'integer', 'min:15', 'max:480'],
            'hours.*.is_active' => ['sometimes', 'boolean'],
            'hours.*.id' => ['sometimes', 'integer'],
        ];
    }

    /**
     * @return array<string, string>
     */
    public function messages(): array
    {
        return [
            'hours.*.start_time.regex' => 'صيغة وقت البداية غير صحيحة (HH:MM).',
            'hours.*.end_time.regex' => 'صيغة وقت النهاية غير صحيحة (HH:MM).',
        ];
    }

    /**
     * @param  array<int, array<string, mixed>>  $hours
     */
    public function withValidator(Validator $validator): void
    {
        $validator->after(function (Validator $validator): void {
            foreach ($this->input('hours', []) as $index => $hour) {
                $start = $hour['start_time'] ?? null;
                $end = $hour['end_time'] ?? null;

                if (is_string($start) && is_string($end) && strtotime($end) <= strtotime($start)) {
                    $validator->errors()->add("hours.{$index}.end_time", 'وقت النهاية يجب أن يكون بعد وقت البداية.');
                }
            }
        });
    }
}
