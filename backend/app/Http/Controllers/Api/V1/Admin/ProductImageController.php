<?php

namespace App\Http\Controllers\Api\V1\Admin;

use App\Http\Controllers\Controller;
use App\Models\ProductImage;
use App\Services\MediaStorageService;
use Illuminate\Http\JsonResponse;

class ProductImageController extends Controller
{
    public function __construct(
        protected MediaStorageService $mediaStorage
    ) {}

    public function destroy(ProductImage $productImage): JsonResponse
    {
        $this->authorize('delete', $productImage);

        $this->mediaStorage->deleteByStoragePath($productImage->storage_path);
        $productImage->delete();

        return response()->json(['message' => 'Removed.']);
    }
}
