<?php

namespace Database\Seeders;

use App\Models\Category;
use App\Models\Product;
use Illuminate\Database\Seeder;

class CatalogSeeder extends Seeder
{
    /**
     * Demo inventory for local / FYP testing. Safe to re-run: upserts by slug (categories) and SKU (products).
     */
    public function run(): void
    {
        $rings = Category::updateOrCreate(
            ['slug' => 'rings'],
            ['name' => 'Rings', 'sort_order' => 1]
        );

        $necklaces = Category::updateOrCreate(
            ['slug' => 'necklaces'],
            ['name' => 'Necklaces', 'sort_order' => 2]
        );

        $bracelets = Category::updateOrCreate(
            ['slug' => 'bracelets'],
            ['name' => 'Bracelets', 'sort_order' => 3]
        );

        $custom = Category::updateOrCreate(
            ['slug' => 'custom-commissions'],
            ['name' => 'Custom Commissions', 'sort_order' => 10]
        );

        $catalog = [
            [
                'category_id' => $rings->id,
                'sku' => 'RING-CL-SOL-001',
                'title' => 'Classic Solitaire Ring (18K)',
                'description' => 'Hand-finished solitaire setting. Ready-made stock piece.',
                'weight_grams' => 4.25,
                'labor_fee_mmk' => 252000,
                'gem_price_mmk' => 0,
                'is_published' => true,
                'is_ready_made' => true,
                'is_custom_order_template' => false,
            ],
            [
                'category_id' => $rings->id,
                'sku' => 'RING-ET-WED-002',
                'title' => 'Etched Wedding Band',
                'description' => 'Traditional Myanmar-inspired etched pattern.',
                'weight_grams' => 6.10,
                'labor_fee_mmk' => 199500,
                'gem_price_mmk' => null,
                'is_published' => true,
                'is_ready_made' => true,
                'is_custom_order_template' => false,
            ],
            [
                'category_id' => $necklaces->id,
                'sku' => 'NEC-CHAIN-BOX-001',
                'title' => 'Box Chain Necklace 20"',
                'description' => 'High-polish box chain, lobster clasp.',
                'weight_grams' => 12.80,
                'labor_fee_mmk' => 178500,
                'gem_price_mmk' => 0,
                'is_published' => true,
                'is_ready_made' => true,
                'is_custom_order_template' => false,
            ],
            [
                'category_id' => $necklaces->id,
                'sku' => 'NEC-PEND-LOT-002',
                'title' => 'Lotus Pendant',
                'description' => 'Symbolic lotus pendant with satin and polish contrast.',
                'weight_grams' => 8.45,
                'labor_fee_mmk' => 294000,
                'gem_price_mmk' => 94500,
                'is_published' => true,
                'is_ready_made' => true,
                'is_custom_order_template' => false,
            ],
            [
                'category_id' => $bracelets->id,
                'sku' => 'BRC-BANG-001',
                'title' => 'Rigid Bangle (Medium)',
                'description' => 'Oval profile for comfort. Size medium.',
                'weight_grams' => 22.50,
                'labor_fee_mmk' => 231000,
                'gem_price_mmk' => 0,
                'is_published' => true,
                'is_ready_made' => true,
                'is_custom_order_template' => false,
            ],
            [
                'category_id' => $bracelets->id,
                'sku' => 'BRC-LINK-002',
                'title' => 'Figaro Link Bracelet',
                'description' => 'Classic Figaro links, secure box clasp.',
                'weight_grams' => 18.20,
                'labor_fee_mmk' => 210000,
                'gem_price_mmk' => null,
                'is_published' => true,
                'is_ready_made' => true,
                'is_custom_order_template' => false,
            ],
            [
                'category_id' => $custom->id,
                'sku' => 'CUST-BESPOKE-001',
                'title' => 'Bespoke Family Crest (template)',
                'description' => 'Starting point for custom crest work — quote after consultation.',
                'weight_grams' => 15.0000,
                'labor_fee_mmk' => 525000,
                'gem_price_mmk' => null,
                'is_published' => true,
                'is_ready_made' => false,
                'is_custom_order_template' => true,
            ],
        ];

        foreach ($catalog as $row) {
            Product::updateOrCreate(
                ['sku' => $row['sku']],
                $row
            );
        }
    }
}
