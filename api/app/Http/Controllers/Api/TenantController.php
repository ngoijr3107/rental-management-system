<?php

namespace App\Http\Controllers\Api;

use App\Enums\LeaseStatus;
use App\Http\Controllers\Concerns\AuthorizesAbilities;
use App\Http\Controllers\Controller;
use App\Services\ActivityLogger;
use App\Http\Requests\TenantRequest;
use App\Http\Resources\TenantResource;
use App\Models\Tenant;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\AnonymousResourceCollection;
use Illuminate\Validation\ValidationException;

class TenantController extends Controller
{
    use AuthorizesAbilities;

    public function __construct(private readonly ActivityLogger $log)
    {
    }

    public function index(Request $request): AnonymousResourceCollection
    {
        $tenants = Tenant::query()
            ->ownedBy($request->user())
            ->when($request->filled('search'), fn ($q) => $q->where(
                fn ($sub) => $sub->where('first_name', 'like', "%{$request->search}%")
                    ->orWhere('last_name', 'like', "%{$request->search}%")
                    ->orWhere('phone', 'like', "%{$request->search}%")
                    ->orWhere('email', 'like', "%{$request->search}%")
            ))
            ->orderBy('last_name')
            ->paginate($request->integer('per_page', 15));

        return TenantResource::collection($tenants);
    }

    public function store(TenantRequest $request): JsonResponse
    {
        $tenant = $request->user()->account()->tenants()->create($request->validated());

        return (new TenantResource($tenant))
            ->response()
            ->setStatusCode(201);
    }

    public function show(Request $request, int $tenant): TenantResource
    {
        $model = Tenant::query()
            ->ownedBy($request->user())
            ->with(['leases.unit.property'])
            ->findOrFail($tenant);

        return new TenantResource($model);
    }

    public function update(TenantRequest $request, int $tenant): TenantResource
    {
        $model = Tenant::query()->ownedBy($request->user())->findOrFail($tenant);
        $model->update($request->validated());

        return new TenantResource($model->fresh());
    }

    public function destroy(Request $request, int $tenant): JsonResponse
    {
        $this->must($request, 'tenants.manage');

        $model = Tenant::query()->ownedBy($request->user())->findOrFail($tenant);

        if ($model->leases()->where('status', LeaseStatus::Active)->exists()) {
            throw ValidationException::withMessages([
                'tenant' => 'This tenant has an active lease and cannot be deleted.',
            ]);
        }

        $name = $model->full_name;
        $model->delete();

        $this->log->record($request->user(), 'tenant.deleted', "Deleted tenant {$name}");

        return response()->json(['message' => 'Tenant deleted.']);
    }
}
