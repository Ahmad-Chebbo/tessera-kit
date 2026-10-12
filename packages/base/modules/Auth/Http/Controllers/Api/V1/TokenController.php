<?php

namespace App\Modules\Auth\Http\Controllers\Api\V1;

use App\Http\Controllers\Controller;
use App\Modules\Auth\Http\Requests\IssueTokenRequest;
use App\Modules\Team\Models\User;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Hash;
use Illuminate\Validation\ValidationException;

class TokenController extends Controller
{
    /**
     * Exchanges credentials for an API token. Deactivated accounts get the same answer as wrong passwords.
     */
    public function store(IssueTokenRequest $request): JsonResponse
    {
        $user = User::query()->where('email', $request->validated('email'))->first();

        if ($user === null || ! $user->is_active || ! Hash::check($request->validated('password'), $user->password)) {
            throw ValidationException::withMessages(['email' => ['The provided credentials are incorrect.']]);
        }

        return response()->json([
            'token' => $user->createToken($request->validated('device_name'))->plainTextToken,
            'user' => ['id' => $user->id, 'name' => $user->name, 'email' => $user->email, 'tenant_id' => $user->tenant_id, 'branch_id' => $user->branch_id],
        ], 201);
    }

    /**
     * The signed in user with the permissions they hold, so a client knows what to offer.
     */
    public function me(Request $request): JsonResponse
    {
        $user = $request->user();

        return response()->json(['data' => [
            'id' => $user->id,
            'name' => $user->name,
            'email' => $user->email,
            'tenant_id' => $user->tenant_id,
            'branch_id' => $user->branch_id,
            'permissions' => $user->getAllPermissions()->pluck('name')->filter(fn (string $name): bool => $user->hasPermissionTo($name))->values(),
        ]]);
    }

    /**
     * Revokes the token that made the request.
     */
    public function destroy(Request $request): JsonResponse
    {
        $request->user()->currentAccessToken()->delete();

        return response()->json(null, 204);
    }
}
