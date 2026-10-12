<?php

namespace App\Modules\Tenant\Http\Controllers\Web;

use App\Http\Controllers\Controller;
use App\Modules\Tenant\Models\ExchangeRate;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;

class ExchangeRateController extends Controller
{
    public function index(Request $request): Response
    {
        $user = $request->user();

        $exchangeRates = ExchangeRate::query()
            ->where('tenant_id', $user->tenant_id)
            ->orderBy('from_currency')
            ->orderBy('to_currency')
            ->get();

        return Inertia::render('ExchangeRates/Index', [
            'exchangeRates' => $exchangeRates,
        ]);
    }

    public function store(Request $request): RedirectResponse
    {
        $user = $request->user();

        $validated = $request->validate([
            'from_currency' => ['required', 'string', 'size:3'],
            'to_currency' => ['required', 'string', 'size:3', 'different:from_currency'],
            'rate' => ['required', 'numeric', 'min:0.000001'],
        ]);

        $validated['tenant_id'] = $user->tenant_id;

        ExchangeRate::updateOrCreate(
            [
                'tenant_id' => $user->tenant_id,
                'from_currency' => $validated['from_currency'],
                'to_currency' => $validated['to_currency'],
            ],
            ['rate' => $validated['rate']]
        );

        return redirect()->route('exchange-rates.index')
            ->with('success', 'Exchange rate saved successfully.');
    }

    public function destroy(Request $request, ExchangeRate $exchangeRate): RedirectResponse
    {
        $user = $request->user();

        if ($exchangeRate->tenant_id !== $user->tenant_id) {
            abort(403);
        }

        $exchangeRate->delete();

        return redirect()->route('exchange-rates.index')
            ->with('success', 'Exchange rate deleted successfully.');
    }
}
