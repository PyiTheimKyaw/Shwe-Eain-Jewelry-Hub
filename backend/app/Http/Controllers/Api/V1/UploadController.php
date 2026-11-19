<?php

namespace App\Http\Controllers\Api\V1;

use App\Enums\UserRole;
use App\Http\Controllers\Controller;
use App\Models\Inquiry;
use App\Services\InquiryImageCandidateService;
use App\Services\MediaStorageService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Str;

class UploadController extends Controller
{
    public function __construct(
        protected MediaStorageService $mediaStorage,
        protected InquiryImageCandidateService $candidates,
    ) {}

    public function presign(Request $request): JsonResponse
    {
        return response()->json([
            'message' => 'Presigned uploads not configured. Use POST /api/v1/uploads with multipart form.',
        ], 501);
    }

    public function store(Request $request): JsonResponse
    {
        $user = $request->user();
        if ($user === null || ! in_array($user->role, [UserRole::Admin, UserRole::Artisan, UserRole::Customer], true)) {
            abort(403, 'Unauthorized upload access.');
        }

        $request->validate([
            'file' => ['required', 'file', 'max:15360', 'mimes:jpeg,jpg,png,webp,heic'],
            'folder' => ['nullable', 'string', 'max:100'],
            'inquiry_id' => ['nullable', 'integer', 'exists:inquiries,id'],
        ]);

        $requestedFolder = Str::slug($request->string('folder', 'misc'));
        $allowedByRole = match ($user->role) {
            UserRole::Admin => ['inquiry-chat', 'order-progress', 'misc'],
            UserRole::Artisan => ['order-progress', 'misc'],
            UserRole::Customer => ['inquiry-chat', 'deposit-proof', 'misc'],
        };
        $folder = in_array($requestedFolder, $allowedByRole, true) ? $requestedFolder : 'misc';
        $stored = $this->mediaStorage->storeImage($request->file('file'), 'uploads/'.$folder);

        if ($folder === 'inquiry-chat' && $request->filled('inquiry_id')) {
            $inquiry = Inquiry::query()->find((int) $request->input('inquiry_id'));
            if ($inquiry !== null && $this->candidates->canUserAttachToInquiry($user, $inquiry)) {
                $this->candidates->createFromUpload(
                    inquiry: $inquiry,
                    source: 'api_chat_upload',
                    senderRole: (string) $user->role->value,
                    storagePath: $stored['storage_path'] ?? null,
                    url: $stored['url'] ?? null,
                    metadata: [
                        'uploader_user_id' => $user->id,
                        'upload_via' => 'api_v1_uploads',
                        'folder' => $folder,
                    ],
                );
            }
        }

        return response()->json([
            'storage_path' => $stored['storage_path'],
            'url' => $stored['url'],
        ], 201);
    }
}
