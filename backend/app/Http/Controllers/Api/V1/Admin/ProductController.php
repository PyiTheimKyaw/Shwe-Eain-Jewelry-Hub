<?php

namespace App\Http\Controllers\Api\V1\Admin;

use App\Http\Controllers\Controller;
use App\Models\Product;
use App\Models\ProductImage;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class ProductController extends Controller
{
    public function store(Request $request): JsonResponse
    {
        $this->authorize('create', Product::class);

        $data = $request->validate([
            'category_id' => ['required', 'exists:categories,id'],
            'sku' => ['required', 'string', 'max:100', 'unique:products,sku'],
            'title' => ['required', 'string', 'max:255'],
            'description' => ['nullable', 'string'],
            'weight_grams' => ['required', 'numeric', 'min:0', 'max:10000'],
            'labor_fee_mmk' => ['required', 'numeric', 'min:0'],
            'gem_price_mmk' => ['nullable', 'numeric', 'min:0'],
            'is_published' => ['boolean'],
            'is_ready_made' => ['boolean'],
            'is_custom_order_template' => ['boolean'],
        ]);

        $product = Product::create([
            ...$data,
            'is_published' => $data['is_published'] ?? false,
            'is_ready_made' => $data['is_ready_made'] ?? true,
            'is_custom_order_template' => $data['is_custom_order_template'] ?? false,
        ]);

        return response()->json(['data' => $product->load('images')], 201);
    }

    public function update(Request $request, Product $product): JsonResponse
    {
        $this->authorize('update', $product);

        $data = $request->validate([
            'category_id' => ['sometimes', 'exists:categories,id'],
            'sku' => ['sometimes', 'string', 'max:100', 'unique:products,sku,'.$product->id],
            'title' => ['sometimes', 'string', 'max:255'],
            'description' => ['nullable', 'string'],
            'weight_grams' => ['sometimes', 'numeric', 'min:0', 'max:10000'],
            'labor_fee_mmk' => ['sometimes', 'numeric', 'min:0'],
            'gem_price_mmk' => ['nullable', 'numeric', 'min:0'],
            'is_published' => ['boolean'],
            'is_ready_made' => ['boolean'],
            'is_custom_order_template' => ['boolean'],
        ]);

        $product->update($data);

        return response()->json(['data' => $product->fresh()->load('images')]);
    }

    public function destroy(Product $product): JsonResponse
    {
        $this->authorize('delete', $product);
        $product->delete();

        return response()->json(['message' => 'Deleted.']);
    }

    public function storeImage(Request $request, Product $product): JsonResponse
    {
        $this->authorize('update', $product);

        $request->validate([
            'image' => ['required', 'file', 'max:10240', 'mimes:jpeg,jpg,png,webp,heic'],
            'sort_order' => ['nullable', 'integer', 'min:0'],
        ]);

        $file = $request->file('image');
        $path = $file->store('products/'.$product->id, 'public');

        $image = ProductImage::create([
            'product_id' => $product->id,
            'storage_path' => $path,
            'sort_order' => $request->integer('sort_order', 0),
        ]);

        return response()->json(['data' => $image], 201);
    }
}
