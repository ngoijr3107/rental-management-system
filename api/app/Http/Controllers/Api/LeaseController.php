<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Concerns\AuthorizesAbilities;
use App\Http\Controllers\Controller;
use App\Http\Requests\StoreLeaseRequest;
use App\Http\Requests\UpdateLeaseRequest;
use App\Http\Resources\LeaseResource;
use App\Models\Lease;
use App\Models\Tenant;
use App\Models\Unit;
use App\Services\ActivityLogger;
use App\Services\LeaseService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\AnonymousResourceCollection;
use Illuminate\Support\Carbon;
use Illuminate\Validation\ValidationException;

class LeaseController extends Controller
{
    use AuthorizesAbilities;

    public function __construct(
        private readonly LeaseService $leases,
        private readonly ActivityLogger $log,
    ) {
    }

    public function index(Request $request): AnonymousResourceCollection
    {
        $leases = Lease::query()
            ->ownedBy($request->user())
            ->with(['tenant', 'unit.property'])
            ->when($request->filled('status'), fn ($q) => $q->where('status', $request->status))
            ->when($request->filled('unit_id'), fn ($q) => $q->where('unit_id', $request->unit_id))
            ->when($request->filled('tenant_id'), fn ($q) => $q->where('tenant_id', $request->tenant_id))
            ->latest('start_date')
            ->paginate($request->integer('per_page', 15));

        return LeaseResource::collection($leases);
    }

    public function store(StoreLeaseRequest $request): JsonResponse
    {
        $unit = Unit::query()
            ->ownedBy($request->user())
            ->findOrFail($request->integer('unit_id'));

        // Prevent attaching another landlord's tenant to this lease.
        Tenant::query()
            ->ownedBy($request->user())
            ->findOrFail($request->integer('tenant_id'));

        $lease = $this->leases->create($unit, $request->validated());

        $this->log->record(
            $request->user(),
            'lease.started',
            "Let {$unit->label} to {$lease->tenant?->full_name}",
            $lease
        );

        return (new LeaseResource($lease))
            ->response()
            ->setStatusCode(201);
    }

    public function show(Request $request, int $lease): LeaseResource
    {
        $model = Lease::query()
            ->ownedBy($request->user())
            ->with(['tenant', 'unit.property', 'invoices'])
            ->findOrFail($lease);

        return new LeaseResource($model);
    }

    public function update(UpdateLeaseRequest $request, int $lease): LeaseResource
    {
        $model = Lease::query()->ownedBy($request->user())->findOrFail($lease);

        if ($request->filled('end_date')
            && Carbon::parse($request->input('end_date'))->lte($model->start_date)) {
            throw ValidationException::withMessages([
                'end_date' => 'The end date must be after the lease start date.',
            ]);
        }

        $model->update($request->validated());

        return new LeaseResource($model->fresh(['tenant', 'unit.property']));
    }

    /** End an active lease and release the unit. */
    public function terminate(Request $request, int $lease): LeaseResource
    {
        $this->must($request, 'leases.manage');

        $validated = $request->validate([
            'terminated_at' => ['nullable', 'date'],
        ]);

        $model = Lease::query()->ownedBy($request->user())->with('unit')->findOrFail($lease);

        $terminated = $this->leases->terminate($model, $validated['terminated_at'] ?? null);

        $this->log->record(
            $request->user(),
            'lease.terminated',
            "Ended the lease on {$terminated->unit?->label}",
            $terminated
        );

        return new LeaseResource($terminated);
    }
}
