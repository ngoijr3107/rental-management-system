<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\Alert;
use App\Models\OutboundMessage;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class AlertController extends Controller
{
    /** The bell: unread count plus the most recent items. */
    public function index(Request $request): JsonResponse
    {
        $query = Alert::query()->ownedBy($request->user());

        return response()->json([
            'unread' => (clone $query)->unread()->count(),
            'data' => (clone $query)->latest('id')->limit(30)->get()->map(fn (Alert $a) => [
                'id' => $a->id,
                'type' => $a->type,
                'title' => $a->title,
                'body' => $a->body,
                'link' => $a->link,
                'severity' => $a->severity,
                'read' => $a->read_at !== null,
                'created_at' => $a->created_at,
            ]),
        ]);
    }

    public function read(Request $request, int $alert): JsonResponse
    {
        Alert::query()->ownedBy($request->user())->whereKey($alert)->update(['read_at' => now()]);

        return response()->json(['message' => 'Marked read.']);
    }

    public function readAll(Request $request): JsonResponse
    {
        Alert::query()->ownedBy($request->user())->unread()->update(['read_at' => now()]);

        return response()->json(['message' => 'All marked read.']);
    }

    /** What has gone out to tenants, so a landlord can prove it was sent. */
    public function outbox(Request $request): JsonResponse
    {
        $messages = OutboundMessage::query()
            ->ownedBy($request->user())
            ->with('tenant:id,first_name,last_name')
            ->when($request->filled('status'), fn ($q) => $q->where('status', $request->status))
            ->latest('id')
            ->paginate($request->integer('per_page', 25));

        return response()->json([
            'data' => $messages->through(fn (OutboundMessage $m) => [
                'id' => $m->id,
                'tenant' => trim(($m->tenant?->first_name ?? '').' '.($m->tenant?->last_name ?? '')) ?: null,
                'channel' => $m->channel,
                'recipient' => $m->recipient,
                'type' => $m->type,
                'body' => $m->body,
                'status' => $m->status,
                'error' => $m->error,
                'sent_at' => $m->sent_at,
                'created_at' => $m->created_at,
            ])->items(),
            'meta' => [
                'current_page' => $messages->currentPage(),
                'last_page' => $messages->lastPage(),
                'total' => $messages->total(),
                'from' => $messages->firstItem(),
                'to' => $messages->lastItem(),
            ],
        ]);
    }
}
