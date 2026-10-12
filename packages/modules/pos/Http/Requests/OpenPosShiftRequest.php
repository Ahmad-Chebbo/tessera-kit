<?php

namespace App\Modules\Pos\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

class OpenPosShiftRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user()?->can('open pos shift') ?? false;
    }

    /**
     * @return array<string, array<int, mixed>>
     */
    public function rules(): array
    {
        return [
            'opening_float' => ['required', 'numeric', 'min:0', 'max:99999999'],
        ];
    }
}
