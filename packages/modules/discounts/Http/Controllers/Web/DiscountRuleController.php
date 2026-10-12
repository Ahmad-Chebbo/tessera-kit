<?php

namespace App\Modules\Discounts\Http\Controllers\Web;

use App\Http\Controllers\Controller;
use App\Modules\Catalog\Models\Category;
use App\Modules\Catalog\Models\Product;
use App\Modules\Customers\Models\Customer;
use App\Modules\Customers\Models\CustomerGroup;
use App\Modules\Discounts\Engine\FactRegistry;
use App\Modules\Discounts\Enums\DiscountAction;
use App\Modules\Discounts\Http\Requests\StoreDiscountRuleRequest;
use App\Modules\Discounts\Models\DiscountApplication;
use App\Modules\Discounts\Models\DiscountRule;
use App\Modules\Discounts\Services\DiscountPreview;
use App\Modules\Discounts\Templates\DiscountTemplates;
use App\Modules\Sales\Enums\OrderStatus;
use App\Modules\Tenant\Models\Branch;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;

class DiscountRuleController extends Controller
{
    private const LOOKUP_LIMIT = 500;

    public function index(Request $request): Response
    {
        $tenantId = $request->user()->tenant_id;

        $usage = DiscountApplication::query()
            ->join('orders', 'orders.id', '=', 'discount_applications.order_id')
            ->where('orders.tenant_id', $tenantId)
            ->where('orders.status', '!=', OrderStatus::Voided->value)
            ->groupBy('discount_applications.discount_rule_id')
            ->selectRaw('discount_applications.discount_rule_id as rule_id, COUNT(DISTINCT orders.id) as uses, SUM(discount_applications.amount) as total')
            ->get()
            ->keyBy('rule_id');

        $rules = DiscountRule::query()
            ->where('tenant_id', $tenantId)
            ->orderBy('priority')
            ->orderBy('id')
            ->get()
            ->map(fn (DiscountRule $rule): array => [
                ...$this->payload($rule),
                'status' => $this->status($rule),
                'uses' => (int) ($usage[$rule->id]->uses ?? 0),
                'total_discounted' => round((float) ($usage[$rule->id]->total ?? 0), 2),
            ]);

        $lookup = fn (string $model) => $model::query()->where('tenant_id', $tenantId)->orderBy('name')->limit(self::LOOKUP_LIMIT)->get(['id', 'name']);

        return Inertia::render('Discounts/Index', [
            'rules' => $rules,
            'templates' => collect(DiscountTemplates::definitions())->map(fn (array $definition, string $key): array => ['key' => $key, ...$definition])->values(),
            'lookups' => [
                'category' => $lookup(Category::class),
                'group' => $lookup(CustomerGroup::class),
                'product' => $lookup(Product::class),
            ],
        ]);
    }

    public function create(Request $request): Response
    {
        return Inertia::render('Discounts/Edit', [
            'rule' => null,
            'catalogue' => $this->catalogue($request),
        ]);
    }

    public function store(StoreDiscountRuleRequest $request): RedirectResponse
    {
        DiscountRule::create([...$request->ruleAttributes(), 'tenant_id' => $request->user()->tenant_id]);

        return redirect()->route('discounts.index')->with('success', 'Discount rule created.');
    }

    public function edit(Request $request, DiscountRule $rule): Response
    {
        $this->ensureOwned($request, $rule);

        return Inertia::render('Discounts/Edit', [
            'rule' => $this->payload($rule),
            'catalogue' => $this->catalogue($request),
        ]);
    }

    public function update(StoreDiscountRuleRequest $request, DiscountRule $rule): RedirectResponse
    {
        $this->ensureOwned($request, $rule);
        $rule->update($request->ruleAttributes());

        return redirect()->route('discounts.index')->with('success', 'Discount rule updated.');
    }

    public function destroy(Request $request, DiscountRule $rule): RedirectResponse
    {
        $this->ensureOwned($request, $rule);
        $rule->delete();

        return back()->with('success', 'Discount rule deleted.');
    }

    public function toggle(Request $request, DiscountRule $rule): RedirectResponse
    {
        $this->ensureOwned($request, $rule);
        $rule->update(['is_active' => ! $rule->is_active]);

        return back()->with('success', $rule->is_active ? 'Discount rule activated.' : 'Discount rule paused.');
    }

    public function storeFromTemplate(Request $request, string $template): RedirectResponse
    {
        abort_unless(DiscountTemplates::exists($template), 404);

        $tenantId = $request->user()->tenant_id;
        $values = $request->validate([...DiscountTemplates::rules($template, $tenantId), 'is_active' => ['boolean']]);

        $rule = DiscountRule::create([
            ...DiscountTemplates::build($template, $values),
            'tenant_id' => $tenantId,
            'is_active' => $request->boolean('is_active', true),
        ]);

        return redirect()->route('discounts.index')->with('success', "Added the rule \"{$rule->name}\".");
    }

    public function duplicate(Request $request, DiscountRule $rule): RedirectResponse
    {
        $this->ensureOwned($request, $rule);

        $copy = $rule->replicate();
        $copy->name = $rule->name.' (copy)';
        $copy->is_active = false;
        $copy->coupon_code = null;
        $copy->save();

        return redirect()->route('discounts.edit', $copy)->with('success', 'Rule duplicated. Review it before activating.');
    }

    public function simulator(Request $request): Response
    {
        $tenantId = $request->user()->tenant_id;

        return Inertia::render('Discounts/Simulator', [
            'products' => Product::query()->where('tenant_id', $tenantId)->orderBy('name')->limit(self::LOOKUP_LIMIT)->get(['id', 'name']),
            'customers' => Customer::query()->where('tenant_id', $tenantId)->orderBy('name')->limit(self::LOOKUP_LIMIT)->get(['id', 'name']),
        ]);
    }

    public function simulate(Request $request, DiscountPreview $preview): JsonResponse
    {
        $tenantId = $request->user()->tenant_id;

        $data = $request->validate([
            'items' => ['required', 'array', 'min:1', 'max:100'],
            'items.*.product_id' => ['required', 'integer', 'exists:products,id,tenant_id,'.$tenantId],
            'items.*.quantity' => ['required', 'numeric', 'min:0.0001', 'max:1000000'],
            'customer_id' => ['nullable', 'integer', 'exists:customers,id,tenant_id,'.$tenantId],
            'coupon_code' => ['nullable', 'string', 'max:50'],
            'payment_type' => ['nullable', 'in:cash,debt'],
        ]);

        return response()->json($preview->run(
            $request->user(),
            $data['items'],
            $data['customer_id'] ?? null,
            $data['coupon_code'] ?? null,
            $data['payment_type'] ?? 'cash',
        ));
    }

    private function ensureOwned(Request $request, DiscountRule $rule): void
    {
        abort_unless($rule->tenant_id === $request->user()->tenant_id, 403);
    }

    /**
     * @return array<string, mixed>
     */
    private function payload(DiscountRule $rule): array
    {
        return [
            'id' => $rule->id,
            'name' => $rule->name,
            'description' => $rule->description,
            'is_active' => $rule->is_active,
            'priority' => $rule->priority,
            'stackable' => $rule->stackable,
            'starts_at' => $rule->starts_at?->format('Y-m-d\TH:i'),
            'ends_at' => $rule->ends_at?->format('Y-m-d\TH:i'),
            'conditions' => $rule->conditions ?? ['type' => 'group', 'logic' => 'all', 'children' => []],
            'expression' => $rule->expression,
            'action_type' => $rule->action_type->value,
            'action_value' => $rule->action_value,
            'action_config' => $rule->action_config ?? (object) [],
            'coupon_code' => $rule->coupon_code,
            'usage_limit' => $rule->usage_limit,
            'per_customer_limit' => $rule->per_customer_limit,
        ];
    }

    private function status(DiscountRule $rule): string
    {
        return match (true) {
            ! $rule->is_active => 'paused',
            $rule->starts_at !== null && $rule->starts_at->isFuture() => 'scheduled',
            $rule->ends_at !== null && $rule->ends_at->isPast() => 'expired',
            default => 'active',
        };
    }

    /**
     * Everything the rule builder offers: the facts a condition can use and the records it can point at.
     *
     * @return array<string, mixed>
     */
    private function catalogue(Request $request): array
    {
        $tenantId = $request->user()->tenant_id;
        $lookup = fn (string $model, array $columns = ['id', 'name']) => $model::query()->where('tenant_id', $tenantId)->orderBy('name')->limit(self::LOOKUP_LIMIT)->get($columns);

        return [
            'facts' => collect(FactRegistry::definitions())->map(fn (array $definition, string $key): array => ['key' => $key, ...$definition])->values(),
            'actions' => collect(DiscountAction::cases())->map(fn (DiscountAction $action): array => ['value' => $action->value, 'label' => $action->label()]),
            'lookups' => [
                'item.product_id' => $lookup(Product::class),
                'item.category_id' => $lookup(Category::class),
                'customer.id' => $lookup(Customer::class),
                'customer.group_id' => $lookup(CustomerGroup::class),
                'context.branch_id' => $lookup(Branch::class),
            ],
        ];
    }
}
