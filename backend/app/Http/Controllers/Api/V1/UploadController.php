<?php

namespace App\Http\Controllers\Api\V1;

use App\Enums\UserRole;
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
        $user = $request->user();
        if ($user === null || ! in_array($user->role, [UserRole::Admin, UserRole::Artisan, UserRole::Customer], true)) {
            abort(403, 'Unauthorized upload access.');
        }

        $request->validate([
            'file' => ['required', 'file', 'max:15360', 'mimes:jpeg,jpg,png,webp,heic'],
            'folder' => ['nullable', 'string', 'max:100'],
        ]);

        $requestedFolder = Str::slug($request->string('folder', 'misc'));
        $allowedByRole = match ($user->role) {
            UserRole::Admin => ['inquiry-chat', 'order-progress', 'misc'],
            UserRole::Artisan => ['order-progress', 'misc'],
            UserRole::Customer => ['inquiry-chat', 'deposit-proof', 'misc'],
        };
        $folder = in_array($requestedFolder, $allowedByRole, true) ? $requestedFolder : 'misc';
        $stored = $this->mediaStorage->storeImage($request->file('file'), 'uploads/'.$folder);

        return response()->json([
            'storage_path' => $stored['storage_path'],
            'url' => $stored['url'],
        ], 201);
    }
}
