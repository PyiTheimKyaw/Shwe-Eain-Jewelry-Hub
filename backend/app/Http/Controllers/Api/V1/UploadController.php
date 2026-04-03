<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Controller;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;

class UploadController extends Controller
{
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
        $path = $request->file('file')->store('uploads/'.$folder, 'public');

        return response()->json([
            'storage_path' => $path,
            'url' => Storage::disk('public')->url($path),
        ], 201);
    }
}
