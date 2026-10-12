<?php

namespace App\Modules\Tenant\Http\Controllers\Web;

use App\Http\Controllers\Controller;
use App\Modules\Tenant\Models\Tenant;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;

class SettingsController extends Controller
{
    public function index(Request $request): Response
    {
        $user = $request->user();
        $tenant = Tenant::find($user->tenant_id);

        return Inertia::render('Settings/Index', [
            'tenant' => $tenant,
            'settings' => [
                'currency' => $tenant?->currency ?? 'USD',
                'tax_rate' => $tenant?->tax_rate ?? 0,
                'receipt_header' => $tenant?->receipt_header ?? '',
                'receipt_footer' => $tenant?->receipt_footer ?? '',
                'logo_url' => $tenant->logo_url ?? '',
                'require_pos_shift' => (bool) ($tenant?->require_pos_shift ?? false),
            ],
        ]);
    }

    public function update(Request $request): RedirectResponse
    {
        $user = $request->user();

        $validated = $request->validate([
            'name' => ['required', 'string', 'max:255'],
            'currency' => ['required', 'string', 'size:3'],
            'tax_rate' => ['nullable', 'numeric', 'min:0', 'max:100'],
            'receipt_header' => ['nullable', 'string', 'max:500'],
            'receipt_footer' => ['nullable', 'string', 'max:500'],
            'require_pos_shift' => ['boolean'],
            'logo' => ['nullable', 'image', 'mimes:jpg,jpeg,png,webp', 'max:2048'],
        ]);

        $tenant = Tenant::findOrFail($user->tenant_id);
        $tenant->update(collect($validated)->except('logo')->all());

        if ($request->hasFile('logo')) {
            $tenant->clearMediaCollection('logo');
            $tenant->addMediaFromRequest('logo')->toMediaCollection('logo');
        }

        return redirect()->route('settings.index')
            ->with('success', 'Settings updated successfully.');
    }
}
