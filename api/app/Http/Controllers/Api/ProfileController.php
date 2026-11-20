<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Http\Requests\UpdatePasswordRequest;
use App\Http\Requests\UpdateProfileRequest;
use App\Http\Resources\UserResource;
use App\Services\ActivityLogger;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

/**
 * A member's own account. Distinct from TeamController, which is an owner
 * administering other people: here anyone signed in edits themselves, and
 * nobody can change their own role.
 */
class ProfileController extends Controller
{
    public function __construct(private readonly ActivityLogger $log)
    {
    }

    public function show(Request $request): UserResource
    {
        return new UserResource($request->user());
    }

    public function update(UpdateProfileRequest $request): UserResource
    {
        $user = $request->user();
        $user->update($request->validated());

        $this->log->record($user, 'profile.updated', "{$user->name} updated their profile", $user);

        return new UserResource($user->fresh());
    }

    /**
     * Change password, then drop every other session. If the password was
     * changed because it may have been seen, leaving other tokens alive
     * would defeat the point.
     */
    public function updatePassword(UpdatePasswordRequest $request): JsonResponse
    {
        $user = $request->user();
        $currentToken = $user->currentAccessToken();

        $user->forceFill(['password' => $request->validated('password')])->save();

        $user->tokens()
            ->when($currentToken, fn ($q) => $q->where('id', '!=', $currentToken->id))
            ->delete();

        $this->log->record($user, 'profile.password', "{$user->name} changed their password", $user);

        return response()->json([
            'message' => 'Password changed. Other devices have been signed out.',
        ]);
    }
}
