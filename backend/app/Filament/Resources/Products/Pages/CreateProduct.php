<?php

namespace App\Filament\Resources\Products\Pages;

use App\Filament\Resources\Products\ProductResource;
use App\Models\Product;
use App\Models\ProductImage;
use Filament\Resources\Pages\CreateRecord;
use Illuminate\Database\Eloquent\Model;

class CreateProduct extends CreateRecord
{
    protected static string $resource = ProductResource::class;

    /**
     * @param  array<string, mixed>  $data
     */
    protected function handleRecordCreation(array $data): Model
    {
        $newImages = is_array($data['new_images'] ?? null) ? $data['new_images'] : [];
        unset($data['new_images']);

        /** @var Product $product */
        $product = Product::query()->create($data);

        foreach ($newImages as $img) {
            $path = trim((string) ($img['storage_path'] ?? ''));
            if ($path === '') {
                continue;
            }
            ProductImage::query()->create([
                'product_id' => $product->id,
                'storage_path' => $path,
                'sort_order' => (int) ($img['sort_order'] ?? 0),
            ]);
        }

        return $product;
    }
}
