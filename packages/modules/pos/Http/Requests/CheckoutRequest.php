<?php

namespace App\Modules\Pos\Http\Requests;

use App\Modules\Orders\Services\SaleRules;
use App\Modules\Team\Models\User;
use Illuminate\Foundation\Http\FormRequest;

class CheckoutRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user() !== null && $this->user()->branch_id !== null;
    }

    /**
     * Rules for one sale, shared with offline sync so both paths validate identically.
     *
     * @return array<string, array<int, mixed>>
     */
    public static function saleRules(User $user, array $sale = []): array
    {
        return SaleRules::for($user, $sale);
    }

    /**
     * @return array<string, array<int, mixed>>
     */
    public function rules(): array
    {
        return self::saleRules($this->user(), $this->all());
    }

    /**
     * @return array<string, string>
     */
    public function messages(): array
    {
        return [
            'items.required' => 'Cart cannot be empty.',
            'items.min' => 'Cart must contain at least one item.',
            'items.*.product_id.required' => 'Product ID is required for each item.',
            'items.*.product_id.exists' => 'One or more products do not exist.',
            'items.*.quantity.required' => 'Quantity is required for each item.',
            'items.*.quantity.min' => 'Quantity must be greater than zero.',
            'cash_received.required' => 'Cash received amount is required.',
            'cash_received.min' => 'Cash received cannot be negative.',
            'discount_type.in' => 'Discount type must be either percentage or fixed.',
            'discount_value.min' => 'Discount value cannot be negative.',
            'discount_value.max' => 'A percentage discount cannot exceed 100.',
            'customer_id.required_if' => 'Select a customer for credit sales.',
        ];
    }
}
