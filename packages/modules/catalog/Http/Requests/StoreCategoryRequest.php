<?php

namespace App\Modules\Catalog\Http\Requests;

use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class StoreCategoryRequest extends FormRequest
{
    /**
     * Get the validation rules that apply to the request.
     *
     * @return array<string, ValidationRule|array<mixed>|string>
     */
    public function rules(): array
    {
        return [
            'name' => ['required', 'string', 'max:255'],
            'parent_id' => ['nullable', 'integer', Rule::exists('categories', 'id')->where('tenant_id', $this->user()->tenant_id)],
            'description' => ['nullable', 'string'],
            'color' => ['nullable', 'string'],
            'icon' => ['nullable', 'string'],
            'is_active' => ['boolean'],
        ];
    }
}
