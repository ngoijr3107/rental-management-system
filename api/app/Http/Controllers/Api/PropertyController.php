<?php

namespace App\Http\Controllers\Api;

use App\Enums\LeaseStatus;
use App\Http\Controllers\Concerns\AuthorizesAbilities;
use App\Http\Controllers\Controller;
use App\Services\ActivityLogger;
use App\Http\Requests\PropertyRequest;
use App\Http\Resources\PropertyResource;
use App\Models\Property;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\AnonymousResourceCollection;
use Illuminate\Validation\ValidationException;

class PropertyController extends Controller
{
    use AuthorizesAbilities;

    public function __construct(private readonly ActivityLogger $log)
    {
    }

    public function index(Request $request): AnonymousResourceCollection
    {
        $properties = Property::query()
            ->ownedBy($request->user())
            ->withCount('units')
            ->when($request->filled('search'), fn ($q) => $q->where(
                fn ($sub) => $sub->where('name', 'like', "%{$request->search}%")
                    ->orWhere('address', 'like', "%{$request->search}%")
            ))
            ->when($request->filled('type'), fn ($q) => $q->where('type', $request->type))
            ->latest('id')
            ->paginate($request->integer('per_page', 15));

        return PropertyResource::collection($properties);
    }

    public function store(PropertyRequest $request): JsonResponse
    {
        $property = $request->user()->account()->properties()->create($request->validated());

        return (new PropertyResource($property))
            ->response()
            ->setStatusCode(201);
    }

    public function show(Request $request, int $property): PropertyResource
    {
        $model = Property::query()
            ->ownedBy($request->user())
            ->with('units')
            ->withCount('units')
            ->findOrFail($property);

        return new PropertyResource($model);
    }

    public function update(PropertyRequest $request, int $property): PropertyResource
    {
        $model = Property::query()->ownedBy($request->user())->findOrFail($property);
        $model->update($request->validated());

        return new PropertyResource($model->fresh());
    }

    public function destroy(Request $request, int $property): JsonResponse
    {
        $this->must($request, 'properties.manage');

        $model = Property::query()->ownedBy($request->user())->findOrFail($property);

        $hasActiveLeases = $model->units()
            ->whereHas('leases', fn ($q) => $q->where('status', LeaseStatus::Active))
            ->exists();

        if ($hasActiveLeases) {
            throw ValidationException::withMessages([
                'property' => 'This property still has active leases. Terminate them first.',
            ]);
        }

        $name = $model->name;
        $model->delete();

        $this->log->record($request->user(), 'property.deleted', "Deleted property {$name}");

        return response()->json(['message' => 'Property deleted.']);
    }
}
