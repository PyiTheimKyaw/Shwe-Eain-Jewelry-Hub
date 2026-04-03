<?php

namespace App\Http\Controllers\Api\V1\Admin;

use App\Http\Controllers\Controller;
use App\Models\ProductImage;
use Illuminate\Http\JsonResponse;
use Illuminate\Support\Facades\Storage;

class ProductImageController extends Controller
{
    public function destroy(ProductImage $productImage): JsonResponse
    {
        $this->authorize('delete', $productImage);

        Storage::disk('public')->delete($productImage->storage_path);
        $productImage->delete();

        return response()->json(['message' => 'Removed.']);
    }
}
