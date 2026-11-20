<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Concerns\AuthorizesAbilities;
use App\Http\Controllers\Controller;
use App\Http\Requests\UnitPhotoRequest;
use App\Http\Resources\UnitPhotoResource;
use App\Models\Unit;
use App\Models\UnitPhoto;
use App\Services\ActivityLogger;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\AnonymousResourceCollection;
use Illuminate\Support\Facades\Storage;

class UnitPhotoController extends Controller
{
    use AuthorizesAbilities;

    public function __construct(private readonly ActivityLogger $log)
    {
    }

    public function index(Request $request, int $unit): AnonymousResourceCollection
    {
        $model = Unit::query()->ownedBy($request->user())->findOrFail($unit);

        return UnitPhotoResource::collection($model->photos);
    }

    public function store(UnitPhotoRequest $request, int $unit): JsonResponse
    {
        $model = Unit::query()->ownedBy($request->user())->findOrFail($unit);

        $position = (int) $model->photos()->max('position');
        $hasCover = $model->photos()->where('is_cover', true)->exists();
        $created = [];

        foreach ($request->file('photos') as $file) {
            $path = $file->store("units/{$model->id}", 'public');

            $created[] = $model->photos()->create([
                'path' => $path,
                'caption' => $request->validated('caption'),
                // The very first photo becomes the cover automatically.
                'is_cover' => ! $hasCover && empty($created),
                'position' => ++$position,
            ]);
        }

        $this->log->record(
            $request->user(),
            'unit.photos',
            count($created).' photo(s) added to '.$model->label,
            $model
        );

        return UnitPhotoResource::collection(collect($created))
            ->response()
            ->setStatusCode(201);
    }

    /** Pick which photo represents the unit. */
    public function setCover(Request $request, int $unit, int $photo): UnitPhotoResource
    {
        $this->must($request, 'properties.manage');

        $model = Unit::query()->ownedBy($request->user())->findOrFail($unit);
        $target = $model->photos()->findOrFail($photo);

        $model->photos()->update(['is_cover' => false]);
        $target->update(['is_cover' => true]);

        return new UnitPhotoResource($target->fresh());
    }

    public function destroy(Request $request, int $unit, int $photo): JsonResponse
    {
        $this->must($request, 'properties.manage');

        $model = Unit::query()->ownedBy($request->user())->findOrFail($unit);
        $target = $model->photos()->findOrFail($photo);

        Storage::disk('public')->delete($target->path);
        $wasCover = $target->is_cover;
        $target->delete();

        // Never leave a unit with photos but no cover.
        if ($wasCover) {
            $next = $model->photos()->first();
            $next?->update(['is_cover' => true]);
        }

        return response()->json(['message' => 'Photo removed.']);
    }
}
