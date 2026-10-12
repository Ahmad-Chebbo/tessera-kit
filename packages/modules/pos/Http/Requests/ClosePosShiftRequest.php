<?php

namespace App\Modules\Pos\Http\Requests;

use App\Modules\Pos\Models\PosShift;
use Illuminate\Foundation\Http\FormRequest;

class ClosePosShiftRequest extends FormRequest
{
    public function authorize(): bool
    {
        /** @var PosShift $shift */
        $shift = $this->route('posShift');
        $user = $this->user();

        return $user !== null
            && $shift->tenant_id === $user->tenant_id
            && ($shift->user_id === $user->id || $user->can('manage shifts'));
    }

    /**
     * @return array<string, array<int, mixed>>
     */
    public function rules(): array
    {
        return [
            'counted_cash' => ['required', 'numeric', 'min:0', 'max:99999999'],
            'notes' => ['nullable', 'string', 'max:1000'],
        ];
    }
}
