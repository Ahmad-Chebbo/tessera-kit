<?php

namespace App\Modules\Attendance\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class StoreAttendanceExcuseRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user()?->can('manage attendance') ?? false;
    }

    /**
     * @return array<string, array<int, mixed>>
     */
    public function rules(): array
    {
        return [
            'user_id' => ['required', 'integer', Rule::exists('users', 'id')->where('tenant_id', $this->user()->tenant_id)],
            'date' => ['required', 'date_format:Y-m-d', 'before:today'],
            'reason' => ['required', 'string', 'max:255'],
        ];
    }
}
