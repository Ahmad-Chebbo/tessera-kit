<?php

namespace App\Modules\Pos\Http\Controllers\Web;

use App\Http\Controllers\Controller;
use App\Modules\Orders\Models\Order;
use App\Modules\Pos\Http\Requests\ClosePosShiftRequest;
use App\Modules\Pos\Http\Requests\OpenPosShiftRequest;
use App\Modules\Pos\Models\PosShift;
use App\Modules\Pos\Services\PosShiftService;
use App\Modules\Team\Models\User;
use App\Modules\Tenant\Services\BranchAccess;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;
use InvalidArgumentException;

class PosShiftController extends Controller
{
    public function __construct(private PosShiftService $shifts, private BranchAccess $access) {}

    public function index(Request $request): Response
    {
        $actor = $request->user();
        $seesEveryone = $actor->can('view shift reports');

        $query = PosShift::query()
            ->where('tenant_id', $actor->tenant_id)
            ->when(! $seesEveryone, fn ($query) => $query->where('user_id', $actor->id))
            ->when($seesEveryone, fn ($query) => $this->access->scope($query, $actor, $request->query('branch_id')))
            ->when($seesEveryone && $request->integer('user_id'), fn ($query, $id) => $query->where('user_id', $id))
            ->when(in_array($request->query('status'), [PosShift::OPEN, PosShift::CLOSED], true), fn ($query) => $query->where('status', $request->query('status')));

        return Inertia::render('PosShifts/Index', [
            'shifts' => $query->with(['user:id,name', 'branch:id,name'])->latest('opened_at')->paginate(15)->withQueryString(),
            'currentShift' => ($current = $this->shifts->current($actor)) ? $this->shiftPayload($current) : null,
            'employees' => $seesEveryone ? $this->access->scope(User::query()->where('tenant_id', $actor->tenant_id), $actor, $request->query('branch_id'))->orderBy('name')->get(['id', 'name']) : [],
            'filters' => [...$request->only(['user_id', 'status']), 'branch_id' => $this->access->canSeeAll($actor) ? $this->access->effective($actor, $request->query('branch_id')) : null],
            'can' => ['open' => $actor->can('open pos shift'), 'seeAll' => $seesEveryone],
        ]);
    }

    public function open(OpenPosShiftRequest $request): RedirectResponse
    {
        try {
            $this->shifts->open($request->user(), (float) $request->validated('opening_float'));
        } catch (InvalidArgumentException $e) {
            return back()->with('error', $e->getMessage());
        }

        return back()->with('success', 'Shift opened.');
    }

    public function close(ClosePosShiftRequest $request, PosShift $posShift): RedirectResponse
    {
        try {
            $this->shifts->close($posShift, (float) $request->validated('counted_cash'), $request->validated('notes'), $request->user());
        } catch (InvalidArgumentException $e) {
            return back()->with('error', $e->getMessage());
        }

        return redirect()->route('pos-shifts.show', $posShift)->with('success', 'Shift closed.');
    }

    public function show(Request $request, PosShift $posShift): Response
    {
        $actor = $request->user();

        abort_unless(
            $posShift->tenant_id === $actor->tenant_id
                && ($posShift->user_id === $actor->id || ($actor->can('view shift reports') && $this->access->allows($actor, $posShift->branch_id))),
            403,
        );

        $posShift->load(['user:id,name', 'branch:id,name']);

        return Inertia::render('PosShifts/Show', [
            'shift' => $this->shiftPayload($posShift),
            'summary' => $posShift->isOpen() ? $this->shifts->summary($posShift) : $posShift->summary,
            'orders' => Order::query()
                ->where('pos_shift_id', $posShift->id)
                ->orderBy('id')
                ->limit(200)
                ->get(['id', 'order_number', 'total', 'payment_type', 'status', 'created_at'])
                ->map(fn (Order $order): array => [
                    'id' => $order->id,
                    'order_number' => $order->order_number,
                    'total' => (float) $order->total,
                    'payment_type' => $order->payment_type,
                    'status' => $order->status->value,
                    'created_at' => $order->created_at->toIso8601String(),
                ]),
            'can' => [
                'close' => $posShift->isOpen() && ($posShift->user_id === $actor->id || $actor->can('manage shifts')),
            ],
        ]);
    }

    /**
     * @return array<string, mixed>
     */
    private function shiftPayload(PosShift $shift): array
    {
        return [
            'id' => $shift->id,
            'status' => $shift->status,
            'user' => $shift->user ? ['id' => $shift->user->id, 'name' => $shift->user->name] : null,
            'branch' => $shift->branch ? ['id' => $shift->branch->id, 'name' => $shift->branch->name] : null,
            'opened_at' => $shift->opened_at->toIso8601String(),
            'closed_at' => $shift->closed_at?->toIso8601String(),
            'opening_float' => (float) $shift->opening_float,
            'expected_cash' => $shift->expected_cash !== null ? (float) $shift->expected_cash : null,
            'counted_cash' => $shift->counted_cash !== null ? (float) $shift->counted_cash : null,
            'variance' => $shift->variance !== null ? (float) $shift->variance : null,
            'notes' => $shift->notes,
        ];
    }
}
