<?php

namespace App\Http\Controllers\Api;

use App\Enums\Role;
use App\Http\Controllers\Controller;
use App\Http\Requests\LoginRequest;
use App\Http\Requests\RegisterRequest;
use App\Http\Resources\UserResource;
use App\Models\AccountSetting;
use App\Models\User;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Hash;
use Illuminate\Validation\ValidationException;

class AuthController extends Controller
{
    /** Registering always creates a new account owner, never a staff member. */
    public function register(RegisterRequest $request): JsonResponse
    {
        $user = User::create($request->validated() + [
            'role' => Role::Owner->value,
            'is_active' => true,
        ]);

        AccountSetting::put($user->id, []);

        return response()->json([
            'user' => new UserResource($user),
            'token' => $user->createToken($request->input('device_name', 'api'))->plainTextToken,
        ], 201);
    }

    public function login(LoginRequest $request): JsonResponse
    {
        $user = User::where('email', $request->email)->first();

        if (! $user || ! Hash::check($request->password, $user->password)) {
            throw ValidationException::withMessages([
                'email' => 'These credentials do not match our records.',
            ]);
        }

        // A suspended member keeps their password but loses the door.
        if (! $user->is_active) {
            throw ValidationException::withMessages([
                'email' => 'This account has been suspended. Speak to an account owner.',
            ]);
        }

        $user->forceFill(['last_login_at' => now()])->save();

        return response()->json([
            'user' => new UserResource($user),
            'token' => $user->createToken($request->input('device_name', 'api'))->plainTextToken,
        ]);
    }

    public function me(Request $request): UserResource
    {
        return new UserResource($request->user());
    }

    /** Revoke only the token used for this request. */
    public function logout(Request $request): JsonResponse
    {
        $request->user()->currentAccessToken()->delete();

        return response()->json(['message' => 'Logged out.']);
    }
}
