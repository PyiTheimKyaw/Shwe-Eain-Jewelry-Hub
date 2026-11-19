<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\Inquiry;
use App\Services\InquiryImageCandidateService;
use App\Services\MediaStorageService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Str;

class InquiryChatUploadController extends Controller
{
    public function __invoke(
        Request $request,
        Inquiry $inquiry,
        MediaStorageService $mediaStorage,
        InquiryImageCandidateService $candidates
    ): JsonResponse
    {
        $this->authorize('update', $inquiry);

        $data = $request->validate([
            'file' => ['required', 'file', 'max:15360', 'mimes:jpeg,jpg,png,webp,heic'],
            'folder' => ['nullable', 'string', 'max:100'],
        ]);

        $folder = Str::slug($data['folder'] ?? 'inquiry-chat-'.$inquiry->id);
        $stored = $mediaStorage->storeImage($request->file('file'), 'uploads/'.$folder);
        $actor = $request->user();
        if ($actor !== null) {
            $candidates->createFromUpload(
                inquiry: $inquiry,
                source: 'admin_chat_upload',
                senderRole: 'admin',
                storagePath: $stored['storage_path'] ?? null,
                url: $stored['url'] ?? null,
                metadata: [
                    'uploader_user_id' => $actor->id,
                    'upload_via' => 'filament_chat_upload',
                    'folder' => $folder,
                ],
            );
        }

        return response()->json([
            'storage_path' => $stored['storage_path'],
            'url' => $stored['url'],
        ], 201);
    }
}
