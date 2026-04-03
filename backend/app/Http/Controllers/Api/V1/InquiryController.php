<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Controller;
use App\Models\Inquiry;
use App\Models\InquiryAttachment;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class InquiryController extends Controller
{
    public function store(Request $request): JsonResponse
    {
        $data = $request->validate([
            'message' => ['required', 'string', 'max:10000'],
            'attachments' => ['nullable', 'array', 'max:10'],
            'attachments.*' => ['file', 'max:10240', 'mimes:jpeg,jpg,png,webp,heic'],
        ]);

        $inquiry = Inquiry::create([
            'customer_user_id' => $request->user()->id,
            'message' => $data['message'],
            'status' => 'open',
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

        return response()->json(['data' => $inquiry], 201);
    }

    public function mine(Request $request): JsonResponse
    {
        $items = Inquiry::query()
            ->where('customer_user_id', $request->user()->id)
            ->with('attachments')
            ->orderByDesc('created_at')
            ->paginate(20);

        return response()->json($items);
    }

    public function show(Request $request, Inquiry $inquiry): JsonResponse
    {
        $this->authorize('view', $inquiry);

        $inquiry->load('attachments');

        return response()->json(['data' => $inquiry]);
    }
}
