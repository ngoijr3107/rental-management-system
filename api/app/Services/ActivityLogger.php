<?php

namespace App\Services;

use App\Models\ActivityLog;
use App\Models\User;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\Request;

/**
 * A deliberately small audit trail. It records the handful of actions people
 * argue about later — money moving, access changing, records disappearing —
 * rather than every read.
 */
class ActivityLogger
{
    public function record(
        User $actor,
        string $action,
        string $summary,
        ?Model $subject = null,
        array $context = []
    ): ActivityLog {
        return ActivityLog::create([
            'account_owner_id' => $actor->accountOwnerId(),
            'user_id' => $actor->id,
            'action' => $action,
            'subject_type' => $subject ? class_basename($subject) : null,
            'subject_id' => $subject?->getKey(),
            'summary' => $summary,
            'context' => $context ?: null,
            'ip_address' => Request::ip(),
        ]);
    }
}
