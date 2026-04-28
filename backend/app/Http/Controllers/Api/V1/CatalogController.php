<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Controller;
use App\Models\Category;
use App\Models\Product;
use App\Services\CatalogProductPresenter;
use App\Services\PricingService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class CatalogController extends Controller
{
    public function __construct(
        protected PricingService $pricing,
        protected CatalogProductPresenter $presenter
    ) {}

    public function categories(): JsonResponse
    {
        $items = Category::query()
            ->orderBy('sort_order')
            ->orderBy('name')
            ->get(['id', 'name', 'slug', 'sort_order']);

        return response()->json([
            'data' => $items,
            'meta' => [
                'weight_standards' => $this->pricing->burmeseMassStandards(),
            ],
        ]);
    }

    public function products(Request $request): JsonResponse
    {
        $query = Product::query()->published()->with(['images', 'category']);

        if ($request->filled('category_id')) {
            $query->where('category_id', $request->integer('category_id'));
        }

        if ($request->filled('search')) {
            $s = '%'.$request->string('search').'%';
            $query->where(function ($q) use ($s) {
                $q->where('title', 'like', $s)->orWhere('sku', 'like', $s);
            });
        }

        if ($request->has('ready_made')) {
            $raw = $request->input('ready_made');
            $flag = filter_var($raw, FILTER_VALIDATE_BOOLEAN, FILTER_NULL_ON_FAILURE);
            if ($flag !== null) {
                $query->where('is_ready_made', $flag);
            }
        }

        $gold = $this->pricing->currentGoldSnapshot();
        $fx = $this->pricing->currentFxSnapshot();
        $pricingConfigured = $gold !== null && $fx !== null;

        $paginator = $query->orderByDesc('created_at')->paginate(20);

        $paginator->getCollection()->transform(
            fn (Product $product) => $this->presenter->present($product, $pricingConfigured, $gold, $fx)
        );

        $newArrivals = Product::query()
            ->published()
            ->with(['images', 'category'])
            ->orderByDesc('created_at')
            ->limit(10)
            ->get()
            ->map(fn (Product $product) => $this->presenter->present($product, $pricingConfigured, $gold, $fx))
            ->values()
            ->all();

        $popularItems = Product::query()
            ->published()
            ->with(['images', 'category'])
            ->withCount('inquiries')
            ->orderByDesc('inquiries_count')
            ->orderByDesc('created_at')
            ->limit(10)
            ->get()
            ->map(function (Product $product) use ($pricingConfigured, $gold, $fx): array {
                $presented = $this->presenter->present($product, $pricingConfigured, $gold, $fx);
                $presented['inquiry_count'] = (int) ($product->inquiries_count ?? 0);
                // Favorite counts are currently local-only on client; keep explicit key for future sync.
                $presented['favorite_count'] = 0;

                return $presented;
            })
            ->values()
            ->all();

        $featuredCollections = Category::query()
            ->where('is_featured', true)
            ->orderBy('sort_order')
            ->orderBy('name')
            ->limit(8)
            ->get(['id', 'name', 'slug'])
            ->map(function (Category $category): array {
                $coverPath = Product::query()
                    ->published()
                    ->where('category_id', $category->id)
                    ->join('product_images', 'product_images.product_id', '=', 'products.id')
                    ->orderBy('product_images.sort_order')
                    ->value('product_images.storage_path');

                return [
                    'id' => $category->id,
                    'name' => $category->name,
                    'slug' => $category->slug,
                    'cover_image_url' => filled($coverPath)
                        ? app(\App\Services\MediaStorageService::class)->publicUrl((string) $coverPath)
                        : null,
                    'published_products_count' => Product::query()
                        ->published()
                        ->where('category_id', $category->id)
                        ->count(),
                ];
            })
            ->values()
            ->all();

        $payload = $paginator->toArray();
        $payload['meta'] = array_merge(
            $this->presenter->catalogMeta($pricingConfigured),
            [
                'new_arrivals' => $newArrivals,
                'popular_items' => $popularItems,
                'featured_collections' => $featuredCollections,
            ]
        );

        return response()->json($payload);
    }

    public function product(Product $product): JsonResponse
    {
        if (! $product->is_published) {
            abort(404);
        }

        $gold = $this->pricing->currentGoldSnapshot();
        $fx = $this->pricing->currentFxSnapshot();
        $pricingConfigured = $gold !== null && $fx !== null;

        $data = $this->presenter->present($product, $pricingConfigured, $gold, $fx);

        return response()->json([
            'data' => $data,
            'meta' => $this->presenter->catalogMeta($pricingConfigured),
        ]);
    }
}
