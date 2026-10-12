<?php

namespace App\Modules\Company\Http\Controllers\Web;

use App\Http\Controllers\Controller;
use App\Modules\Company\Models\Company;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;

class SettingsController extends Controller
{
    public function index(Request $request): Response
    {
        $user = $request->user();
        $company = Company::find($user->company_id);

        return Inertia::render('Settings/Index', [
            'company' => $company,
            'settings' => [
                'currency' => $company?->currency ?? 'USD',
                'tax_rate' => $company?->tax_rate ?? 0,
                'receipt_header' => $company?->receipt_header ?? '',
                'receipt_footer' => $company?->receipt_footer ?? '',
                'logo_url' => $company->logo_url ?? '',
                'require_pos_shift' => (bool) ($company?->require_pos_shift ?? false),
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

        $company = Company::findOrFail($user->company_id);
        $company->update(collect($validated)->except('logo')->all());

        if ($request->hasFile('logo')) {
            $company->clearMediaCollection('logo');
            $company->addMediaFromRequest('logo')->toMediaCollection('logo');
        }

        return redirect()->route('settings.index')
            ->with('success', 'Settings updated successfully.');
    }
}
