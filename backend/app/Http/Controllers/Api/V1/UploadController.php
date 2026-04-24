<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Controller;
use App\Services\MediaStorageService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Str;

class UploadController extends Controller
{
    public function __construct(
        protected MediaStorageService $mediaStorage
    ) {}

    public function presign(Request $request): JsonResponse
    {
        return response()->json([
            'message' => 'Presigned uploads not configured. Use POST /api/v1/uploads with multipart form.',
        ], 501);
    }

    public function store(Request $request): JsonResponse
    {
        $request->validate([
            'file' => ['required', 'file', 'max:15360'],
            'folder' => ['nullable', 'string', 'max:100'],
        ]);

        $folder = Str::slug($request->string('folder', 'misc'));
        $stored = $this->mediaStorage->storeImage($request->file('file'), 'uploads/'.$folder);

        return response()->json([
            'storage_path' => $stored['storage_path'],
            'url' => $stored['url'],
        ], 201);
    }
}
