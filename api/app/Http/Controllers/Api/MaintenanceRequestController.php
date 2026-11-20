<?php

namespace App\Http\Controllers\Api;

use App\Enums\MaintenanceStatus;
use App\Http\Controllers\Concerns\AuthorizesAbilities;
use App\Http\Controllers\Controller;
use App\Http\Requests\MaintenanceRequestRequest;
use App\Http\Resources\MaintenanceRequestResource;
use App\Models\MaintenanceRequest;
use App\Models\Tenant;
use App\Models\Unit;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\AnonymousResourceCollection;

class MaintenanceRequestController extends Controller
{
    use AuthorizesAbilities;

    public function index(Request $request): AnonymousResourceCollection
    {
        $items = MaintenanceRequest::query()
            ->ownedBy($request->user())
            ->with(['unit.property', 'tenant'])
            ->when($request->filled('status'), fn ($q) => $q->where('status', $request->status))
            ->when($request->filled('priority'), fn ($q) => $q->where('priority', $request->priority))
            ->when($request->filled('unit_id'), fn ($q) => $q->where('unit_id', $request->unit_id))
            ->latest('reported_at')
            ->paginate($request->integer('per_page', 15));

        return MaintenanceRequestResource::collection($items);
    }

    public function store(MaintenanceRequestRequest $request): JsonResponse
    {
        Unit::query()->ownedBy($request->user())->findOrFail($request->integer('unit_id'));

        if ($request->filled('tenant_id')) {
            Tenant::query()->ownedBy($request->user())->findOrFail($request->integer('tenant_id'));
        }

        $item = MaintenanceRequest::create($request->validated() + [
            'reported_at' => $request->input('reported_at', now()->toDateString()),
        ]);

        return (new MaintenanceRequestResource($item->load('unit.property', 'tenant')))
            ->response()
            ->setStatusCode(201);
    }

    public function show(Request $request, int $maintenance_request): MaintenanceRequestResource
    {
        $item = MaintenanceRequest::query()
            ->ownedBy($request->user())
            ->with(['unit.property', 'tenant'])
            ->findOrFail($maintenance_request);

        return new MaintenanceRequestResource($item);
    }

    public function update(MaintenanceRequestRequest $request, int $maintenance_request): MaintenanceRequestResource
    {
        $item = MaintenanceRequest::query()->ownedBy($request->user())->findOrFail($maintenance_request);

        $data = $request->validated();

        // Stamp the resolution date automatically the first time it is resolved.
        if (($data['status'] ?? null) === MaintenanceStatus::Resolved->value && ! $item->resolved_at) {
            $data['resolved_at'] = now()->toDateString();
        }

        $item->update($data);

        return new MaintenanceRequestResource($item->fresh(['unit.property', 'tenant']));
    }

    public function destroy(Request $request, int $maintenance_request): JsonResponse
    {
        $this->must($request, 'maintenance.manage');

        $item = MaintenanceRequest::query()->ownedBy($request->user())->findOrFail($maintenance_request);
        $item->delete();

        return response()->json(['message' => 'Maintenance request deleted.']);
    }
}
