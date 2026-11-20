<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Http\Requests\LogoUploadRequest;
use App\Http\Requests\SettingsRequest;
use App\Models\AccountSetting;
use App\Services\ActivityLogger;
use Illuminate\Support\Facades\Storage;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class SettingsController extends Controller
{
    public function __construct(private readonly ActivityLogger $log)
    {
    }

    /**
     * Readable by any member — the client needs the currency and locale to
     * render anything. Only an owner may change them.
     */
    public function show(Request $request): JsonResponse
    {
        return response()->json([
            'data' => AccountSetting::presentable($request->user()->accountOwnerId()),
        ]);
    }

    public function update(SettingsRequest $request): JsonResponse
    {
        $ownerId = $request->user()->accountOwnerId();
        $before = AccountSetting::forOwner($ownerId);
        $values = AccountSetting::put($ownerId, $request->validated());

        $changed = array_keys(array_diff_assoc(
            array_map(fn ($v) => is_bool($v) ? (int) $v : $v, $values),
            array_map(fn ($v) => is_bool($v) ? (int) $v : $v, $before)
        ));

        if ($changed) {
            $this->log->record(
                $request->user(),
                'settings.updated',
                'Updated account settings: '.implode(', ', $changed),
                null,
                ['changed' => $changed]
            );
        }

        return response()->json(['data' => AccountSetting::presentable($ownerId)]);
    }

    /** Replace the account's logo. The previous file is removed, not orphaned. */
    public function uploadLogo(LogoUploadRequest $request): JsonResponse
    {
        $ownerId = $request->user()->accountOwnerId();
        $previous = AccountSetting::forOwner($ownerId)['logo_path'] ?? null;

        $path = $request->file('logo')->store("logos/{$ownerId}", 'public');

        AccountSetting::put($ownerId, ['logo_path' => $path]);

        if ($previous && $previous !== $path) {
            Storage::disk('public')->delete($previous);
        }

        $this->log->record($request->user(), 'settings.logo', 'Updated the account logo');

        return response()->json(['data' => AccountSetting::presentable($ownerId)]);
    }

    public function deleteLogo(Request $request): JsonResponse
    {
        abort_unless($request->user()->hasPermission('settings.manage'), 403);

        $ownerId = $request->user()->accountOwnerId();
        $previous = AccountSetting::forOwner($ownerId)['logo_path'] ?? null;

        if ($previous) {
            Storage::disk('public')->delete($previous);
        }

        AccountSetting::put($ownerId, ['logo_path' => null]);

        $this->log->record($request->user(), 'settings.logo', 'Removed the account logo');

        return response()->json(['data' => AccountSetting::presentable($ownerId)]);
    }
}
