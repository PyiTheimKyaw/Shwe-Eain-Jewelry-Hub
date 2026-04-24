<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\Inquiry;
use App\Services\MediaStorageService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Str;

class InquiryChatUploadController extends Controller
{
    public function __invoke(Request $request, Inquiry $inquiry, MediaStorageService $mediaStorage): JsonResponse
    {
        $this->authorize('view', $inquiry);

        $data = $request->validate([
            'file' => ['required', 'file', 'max:15360', 'mimes:jpeg,jpg,png,webp,heic'],
            'folder' => ['nullable', 'string', 'max:100'],
        ]);

        $folder = Str::slug($data['folder'] ?? 'inquiry-chat-'.$inquiry->id);
        $stored = $mediaStorage->storeImage($request->file('file'), 'uploads/'.$folder);

        return response()->json([
            'storage_path' => $stored['storage_path'],
            'url' => $stored['url'],
        ], 201);
    }
}
