<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Controller;
use App\Models\Inquiry;
use App\Models\InquiryAttachment;
use App\Services\FirebaseInquiryService;
use App\Services\InquiryPipelinePresenter;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Str;

class InquiryController extends Controller
{
    public function store(Request $request, FirebaseInquiryService $firebaseInquiry): JsonResponse
    {
        $data = $request->validate([
            'message' => ['required', 'string', 'max:10000'],
            'product_id' => ['nullable', 'integer', 'exists:products,id'],
            'attachments' => ['nullable', 'array', 'max:10'],
            'attachments.*' => ['file', 'max:10240', 'mimes:jpeg,jpg,png,webp,heic'],
        ]);

        $inquiry = Inquiry::create([
            'customer_user_id' => $request->user()->id,
            'product_id' => $data['product_id'] ?? null,
            'message' => $data['message'],
            'status' => 'open',
            'firebase_thread_id' => (string) Str::uuid(),
        ]);

        if (! empty($data['attachments'])) {
            foreach ($request->file('attachments', []) as $file) {
                $path = $file->store('inquiries/'.$inquiry->id, 'public');
                InquiryAttachment::create([
                    'inquiry_id' => $inquiry->id,
                    'storage_path' => $path,
                    'mime_type' => $file->getMimeType(),
                ]);
            }
        }

        $inquiry->load('attachments');

        $firebaseInquiry->syncInquiryThreadDocument($inquiry);

        return response()->json(['data' => $inquiry], 201);
    }

    public function mine(Request $request): JsonResponse
    {
        $items = Inquiry::query()
            ->where('customer_user_id', $request->user()->id)
            ->with(['attachments', 'product'])
            ->orderByDesc('created_at')
            ->paginate(20);

        return response()->json($items);
    }

    public function show(Request $request, Inquiry $inquiry, InquiryPipelinePresenter $pipeline): JsonResponse
    {
        $this->authorize('view', $inquiry);

        $inquiry->load(['attachments', 'product', 'quotes']);

        return response()->json([
            'data' => $inquiry,
            'meta' => [
                'pipeline' => $pipeline->present($inquiry),
            ],
        ]);
    }
}
