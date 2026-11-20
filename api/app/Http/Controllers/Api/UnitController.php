<?php

namespace App\Http\Controllers\Api;

use App\Enums\LeaseStatus;
use App\Http\Controllers\Concerns\AuthorizesAbilities;
use App\Http\Controllers\Controller;
use App\Services\ActivityLogger;
use App\Http\Requests\UnitRequest;
use App\Http\Resources\UnitResource;
use App\Models\Property;
use App\Models\Unit;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\AnonymousResourceCollection;
use Illuminate\Validation\ValidationException;

class UnitController extends Controller
{
    use AuthorizesAbilities;

    public function __construct(private readonly ActivityLogger $log)
    {
    }

    public function index(Request $request): AnonymousResourceCollection
    {
        $units = Unit::query()
            ->ownedBy($request->user())
            ->with(['property', 'activeLease.tenant', 'coverPhoto'])
            ->withCount('photos')
            ->when($request->filled('property_id'), fn ($q) => $q->where('property_id', $request->property_id))
            ->when($request->filled('status'), fn ($q) => $q->where('status', $request->status))
            ->when($request->filled('search'), fn ($q) => $q->where('label', 'like', "%{$request->search}%"))
            ->orderBy('property_id')
            ->orderBy('label')
            ->paginate($request->integer('per_page', 15));

        return UnitResource::collection($units);
    }

    public function store(UnitRequest $request): JsonResponse
    {
        // Make sure the property really belongs to the authenticated landlord.
        $property = Property::query()
            ->ownedBy($request->user())
            ->findOrFail($request->integer('property_id'));

        $unit = $property->units()->create($request->safe()->except('property_id'));

        return (new UnitResource($unit->load('property')))
            ->response()
            ->setStatusCode(201);
    }

    public function show(Request $request, int $unit): UnitResource
    {
        $model = Unit::query()
            ->ownedBy($request->user())
            ->with(['property', 'activeLease.tenant', 'photos'])
            ->findOrFail($unit);

        return new UnitResource($model);
    }

    public function update(UnitRequest $request, int $unit): UnitResource
    {
        $model = Unit::query()->ownedBy($request->user())->findOrFail($unit);

        $data = $request->safe()->except('property_id');

        if ($request->filled('property_id')) {
            Property::query()->ownedBy($request->user())->findOrFail($request->integer('property_id'));
            $data['property_id'] = $request->integer('property_id');
        }

        $model->update($data);

        return new UnitResource($model->fresh('property'));
    }

    public function destroy(Request $request, int $unit): JsonResponse
    {
        $this->must($request, 'properties.manage');

        $model = Unit::query()->ownedBy($request->user())->findOrFail($unit);

        if ($model->leases()->where('status', LeaseStatus::Active)->exists()) {
            throw ValidationException::withMessages([
                'unit' => 'This unit has an active lease and cannot be deleted.',
            ]);
        }

        $name = $model->label;
        $model->delete();

        $this->log->record($request->user(), 'unit.deleted', "Deleted unit {$name}");

        return response()->json(['message' => 'Unit deleted.']);
    }
}
