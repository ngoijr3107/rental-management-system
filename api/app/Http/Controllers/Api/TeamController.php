<?php

namespace App\Http\Controllers\Api;

use App\Enums\Role;
use App\Http\Controllers\Concerns\AuthorizesAbilities;
use App\Http\Controllers\Controller;
use App\Http\Requests\StoreTeamMemberRequest;
use App\Http\Requests\UpdateTeamMemberRequest;
use App\Http\Resources\TeamMemberResource;
use App\Models\User;
use App\Services\ActivityLogger;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\AnonymousResourceCollection;
use Illuminate\Validation\ValidationException;

class TeamController extends Controller
{
    use AuthorizesAbilities;

    public function __construct(private readonly ActivityLogger $log)
    {
    }

    /** Everyone on this account, owner first. */
    public function index(Request $request): AnonymousResourceCollection
    {
        $this->must($request, 'users.manage');

        $ownerId = $request->user()->accountOwnerId();

        $members = User::query()
            ->where('id', $ownerId)
            ->orWhere('account_owner_id', $ownerId)
            ->orderByRaw('CASE WHEN account_owner_id IS NULL THEN 0 ELSE 1 END')
            ->orderBy('name')
            ->get();

        return TeamMemberResource::collection($members);
    }

    public function store(StoreTeamMemberRequest $request): JsonResponse
    {
        $member = User::create([
            'account_owner_id' => $request->user()->accountOwnerId(),
            'name' => $request->validated('name'),
            'email' => $request->validated('email'),
            'phone' => $request->validated('phone'),
            'role' => $request->validated('role'),
            'password' => $request->validated('password'),
            'is_active' => true,
        ]);

        $this->log->record(
            $request->user(),
            'user.added',
            "Added {$member->name} as {$member->role->value}",
            $member,
            ['role' => $member->role->value]
        );

        return (new TeamMemberResource($member))->response()->setStatusCode(201);
    }

    public function update(UpdateTeamMemberRequest $request, int $member): TeamMemberResource
    {
        $model = $this->member($request, $member);

        if ($model->isOwner()) {
            throw ValidationException::withMessages([
                'member' => 'The account owner cannot be edited here.',
            ]);
        }

        $before = ['role' => $model->role->value, 'is_active' => $model->is_active];

        $data = collect($request->validated())
            ->reject(fn ($value, $key) => $key === 'password' && blank($value))
            ->all();

        $model->update($data);

        $this->log->record(
            $request->user(),
            'user.updated',
            "Updated access for {$model->name}",
            $model,
            ['before' => $before, 'after' => ['role' => $model->role->value, 'is_active' => $model->is_active]]
        );

        return new TeamMemberResource($model->fresh());
    }

    /** Removing a member revokes their tokens; portfolio records are untouched. */
    public function destroy(Request $request, int $member): JsonResponse
    {
        $this->must($request, 'users.manage');

        $model = $this->member($request, $member);

        if ($model->isOwner()) {
            throw ValidationException::withMessages([
                'member' => 'The account owner cannot be removed.',
            ]);
        }

        if ($model->id === $request->user()->id) {
            throw ValidationException::withMessages([
                'member' => 'You cannot remove your own access.',
            ]);
        }

        $name = $model->name;
        $model->tokens()->delete();
        $model->delete();

        $this->log->record($request->user(), 'user.removed', "Removed {$name} from the account");

        return response()->json(['message' => 'Member removed.']);
    }

    /** The roles an owner may hand out, with what each one allows. */
    public function roles(Request $request): JsonResponse
    {
        $this->must($request, 'users.manage');

        return response()->json([
            'data' => collect(Role::cases())
                ->map(fn (Role $role) => [
                    'value' => $role->value,
                    'label' => $role->label(),
                    'assignable' => in_array($role->value, Role::assignable(), true),
                    'abilities' => $role->abilities(),
                ])
                ->values(),
        ]);
    }

    private function member(Request $request, int $id): User
    {
        $ownerId = $request->user()->accountOwnerId();

        return User::query()
            ->where(fn ($q) => $q->where('id', $ownerId)->orWhere('account_owner_id', $ownerId))
            ->findOrFail($id);
    }
}
