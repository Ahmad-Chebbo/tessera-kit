<?php

namespace App\Modules\Expenses\Http\Requests;

use App\Modules\Expenses\Enums\ExpenseType;
use App\Modules\Tenant\Services\BranchAccess;
use Tessera\Core\Http\PeriodRequest;

/**
 * The period of the expense pages (with the same ready-made periods as the reports) plus their filters.
 * Filters that make no sense are ignored rather than failing the page.
 */
class ExpenseFilterRequest extends PeriodRequest
{
    /**
     * @return array<string, int|string>
     */
    public function filters(): array
    {
        $filters = [];

        foreach (['category_id', 'employee_id'] as $key) {
            if (ctype_digit((string) $this->query($key))) {
                $filters[$key] = (int) $this->query($key);
            }
        }

        $branchId = app(BranchAccess::class)->effective($this->user(), $this->query('branch_id'));

        if ($branchId !== null) {
            $filters['branch_id'] = $branchId;
        }

        $type = $this->query('type');

        if (is_string($type) && in_array($type, ExpenseType::values(), true)) {
            $filters['type'] = $type;
        }

        $search = $this->query('search');

        if (is_string($search) && trim($search) !== '') {
            $filters['search'] = mb_substr(trim($search), 0, 100);
        }

        return $filters;
    }
}
