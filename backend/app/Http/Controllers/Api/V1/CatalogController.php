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

        $payload = $paginator->toArray();
        $payload['meta'] = array_merge(
            $this->presenter->catalogMeta($pricingConfigured),
            ['new_arrivals' => $newArrivals]
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
