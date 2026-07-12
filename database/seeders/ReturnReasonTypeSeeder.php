<?php

namespace Database\Seeders;

use App\Models\ReturnReasonType;
use Illuminate\Database\Console\Seeds\WithoutModelEvents;
use Illuminate\Database\Seeder;

class ReturnReasonTypeSeeder extends Seeder
{
    /**
     * Run the database seeds.
     */
    public function run(): void
    {
        $returnReasonTypes = [
            [
                'name' => 'Wrong size',
                'slug' => 'wrong-size',
                'description' => 'Customer ordered wrong size',
                'is_active' => true,
                'sort_order' => 1,
            ],
            [
                'name' => 'Defective product',
                'slug' => 'defective-product',
                'description' => 'Item arrived damaged or not working',
                'is_active' => true,
                'sort_order' => 2,
            ],
            [
                'name' => 'Not as described',
                'slug' => 'not-as-described',
                'description' => 'Product differed from listing/photos',
                'is_active' => true,
                'sort_order' => 3,
            ],
            [
                'name' => 'Changed mind',
                'slug' => 'changed-mind',
                'description' => 'Customer no longer wants the item',
                'is_active' => true,
                'sort_order' => 4,
            ],
            [
                'name' => 'Late delivery',
                'slug' => 'late-delivery',
                'description' => 'Item arrived too late',
                'is_active' => true,
                'sort_order' => 5,
            ],
            [
                'name' => 'Wrong item sent',
                'slug' => 'wrong-item-sent',
                'description' => 'Warehouse picked incorrect product',
                'is_active' => true,
                'sort_order' => 6,
            ],
            [
                'name' => 'Other',
                'slug' => 'other',
                'description' => 'Any reason not covered above',
                'is_active' => true,
                'sort_order' => 7,
            ],
        ];

        foreach ($returnReasonTypes as $reasonType) {
            ReturnReasonType::updateOrCreate(
                ['slug' => $reasonType['slug']],
                $reasonType
            );
        }
    }
}
