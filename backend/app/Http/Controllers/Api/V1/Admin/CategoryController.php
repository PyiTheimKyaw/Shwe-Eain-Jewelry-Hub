<?php

namespace App\Http\Controllers\Api\V1\Admin;

use App\Http\Controllers\Controller;
use App\Models\Category;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Str;

class CategoryController extends Controller
{
    public function store(Request $request): JsonResponse
    {
        $this->authorize('create', Category::class);

        $data = $request->validate([
            'name' => ['required', 'string', 'max:255'],
            'slug' => ['nullable', 'string', 'max:255', 'unique:categories,slug'],
            'sort_order' => ['nullable', 'integer', 'min:0'],
            'is_featured' => ['nullable', 'boolean'],
        ]);

        $slug = $data['slug'] ?? Str::slug($data['name']);
        if (Category::query()->where('slug', $slug)->exists()) {
            $slug .= '-'.Str::lower(Str::random(4));
        }

        $category = Category::create([
            'name' => $data['name'],
            'slug' => $slug,
            'sort_order' => $data['sort_order'] ?? 0,
            'is_featured' => (bool) ($data['is_featured'] ?? false),
        ]);

        return response()->json(['data' => $category], 201);
    }

    public function update(Request $request, Category $category): JsonResponse
    {
        $this->authorize('update', $category);

        $data = $request->validate([
            'name' => ['sometimes', 'string', 'max:255'],
            'slug' => ['sometimes', 'string', 'max:255', 'unique:categories,slug,'.$category->id],
            'sort_order' => ['sometimes', 'integer', 'min:0'],
            'is_featured' => ['sometimes', 'boolean'],
        ]);

        $category->update($data);

        return response()->json(['data' => $category]);
    }

    public function destroy(Category $category): JsonResponse
    {
        $this->authorize('delete', $category);
        $category->delete();

        return response()->json(['message' => 'Deleted.']);
    }
}
