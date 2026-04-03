<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Controller;
use App\Models\Order;
use App\Models\OrderProgressAttachment;
use App\Models\OrderProgressEvent;
use App\Services\FirebaseInquiryService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class OrderProgressController extends Controller
{
    public function __construct(
        protected FirebaseInquiryService $firebaseInquiry
    ) {}

    public function index(Request $request, Order $order): JsonResponse
    {
        $this->authorize('view', $order);

        $events = $order->progressEvents()
            ->with(['attachments', 'artisan'])
            ->orderBy('created_at')
            ->get();

        return response()->json(['data' => $events]);
    }

    public function store(Request $request, Order $order): JsonResponse
    {
        $this->authorize('addProgress', $order);

        $data = $request->validate([
            'stage' => ['required', 'in:casting,filing,polishing,setting,qc,other'],
            'note' => ['nullable', 'string', 'max:5000'],
            'firebase_event_id' => ['nullable', 'string', 'max:255'],
            'photos' => ['nullable', 'array', 'max:10'],
            'photos.*' => ['file', 'max:10240', 'mimes:jpeg,jpg,png,webp,heic'],
        ]);

        if (! empty($data['firebase_event_id'])) {
            $exists = OrderProgressEvent::query()
                ->where('firebase_event_id', $data['firebase_event_id'])
                ->exists();
            if ($exists) {
                return response()->json(['message' => 'Duplicate firebase_event_id.'], 409);
            }
        }

        $event = OrderProgressEvent::create([
            'order_id' => $order->id,
            'artisan_user_id' => $request->user()->id,
            'stage' => $data['stage'],
            'note' => $data['note'] ?? null,
            'firebase_event_id' => $data['firebase_event_id'] ?? null,
        ]);

        foreach ($request->file('photos', []) as $file) {
            $path = $file->store('orders/'.$order->id.'/progress', 'public');
            OrderProgressAttachment::create([
                'progress_event_id' => $event->id,
                'storage_path' => $path,
                'mime_type' => $file->getMimeType(),
            ]);
        }

        $event->load(['attachments', 'artisan']);

        $inquiry = $order->resolveInquiry();
        if ($inquiry !== null) {
            foreach ($event->attachments as $attachment) {
                $this->firebaseInquiry->publishProgressImageMessage($inquiry, $event, $attachment);
            }
            $this->firebaseInquiry->syncInquiryThreadDocument($inquiry);
        }

        return response()->json(['data' => $event], 201);
    }
}
