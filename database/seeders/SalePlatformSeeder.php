<?php

namespace Database\Seeders;

use App\Models\SalePlatform;
use Illuminate\Database\Console\Seeds\WithoutModelEvents;
use Illuminate\Database\Seeder;

class SalePlatformSeeder extends Seeder
{
    /**
     * Run the database seeds.
     */
    public function run(): void
    {
        $platforms = [
            // Top-level channels
            ['id' => 1,  'name' => 'Enorsia',     'slug' => 'enorsia',            'parent_id' => null, 'type' => 'channel',     'sort_order' => 1, 'is_spent' => true, 'is_sales' => true, 'allows_direct_entry' => true],
            ['id' => 2,  'name' => 'Debenhams',   'slug' => 'debenhams',          'parent_id' => null, 'type' => 'channel',     'sort_order' => 2, 'is_spent' => true, 'is_sales' => true, 'allows_direct_entry' => true],
            ['id' => 3,  'name' => 'Amazon',      'slug' => 'amazon',             'parent_id' => null, 'type' => 'channel',     'sort_order' => 3, 'is_spent' => true, 'is_sales' => true, 'allows_direct_entry' => true],
            ['id' => 4,  'name' => 'Spartoo',     'slug' => 'spartoo',            'parent_id' => null, 'type' => 'channel',     'sort_order' => 4, 'is_spent' => true, 'is_sales' => true, 'allows_direct_entry' => true],
            ['id' => 5,  'name' => 'Temu',        'slug' => 'temu',               'parent_id' => null, 'type' => 'channel',     'sort_order' => 5, 'is_spent' => true, 'is_sales' => true, 'allows_direct_entry' => true],
            ['id' => 6,  'name' => 'Rackhams',    'slug' => 'rackhams',           'parent_id' => null, 'type' => 'channel',     'sort_order' => 6, 'is_spent' => true, 'is_sales' => true, 'allows_direct_entry' => true],

            // Enorsia sub-channels
            ['id' => 10, 'name' => 'Google',      'slug' => 'enorsia-google',     'parent_id' => 1,    'type' => 'sub_channel', 'sort_order' => 1, 'is_spent' => true, 'is_sales' => true, 'allows_direct_entry' => true],
            ['id' => 11, 'name' => 'Meta',        'slug' => 'enorsia-meta',       'parent_id' => 1,    'type' => 'sub_channel', 'sort_order' => 2, 'is_spent' => true, 'is_sales' => true, 'allows_direct_entry' => true],
            ['id' => 12, 'name' => 'Klaviyo',     'slug' => 'enorsia-klaviyo',    'parent_id' => 1,    'type' => 'sub_channel', 'sort_order' => 3, 'is_spent' => true, 'is_sales' => true, 'allows_direct_entry' => true],
            ['id' => 13, 'name' => 'Influencer',  'slug' => 'enorsia-influencer', 'parent_id' => 1,    'type' => 'sub_channel', 'sort_order' => 4, 'is_spent' => true, 'is_sales' => true, 'allows_direct_entry' => true],
            ['id' => 14, 'name' => 'SEO',         'slug' => 'enorsia-seo',        'parent_id' => 1,    'type' => 'sub_channel', 'sort_order' => 5, 'is_spent' => true, 'is_sales' => true, 'allows_direct_entry' => true],
            ['id' => 15, 'name' => 'Awin',        'slug' => 'enorsia-awin',       'parent_id' => 1,    'type' => 'sub_channel', 'sort_order' => 6, 'is_spent' => true, 'is_sales' => true, 'allows_direct_entry' => true],
            ['id' => 16, 'name' => 'Others',      'slug' => 'enorsia-others',     'parent_id' => 1,    'type' => 'sub_channel', 'sort_order' => 7, 'is_spent' => true, 'is_sales' => true, 'allows_direct_entry' => true],

            // Amazon sub-channels
            ['id' => 20, 'name' => 'Amazon UK',   'slug' => 'amazon-uk',          'parent_id' => 3,    'type' => 'sub_channel', 'sort_order' => 1, 'is_spent' => true, 'is_sales' => true, 'allows_direct_entry' => true],
            ['id' => 21, 'name' => 'Amazon EU',   'slug' => 'amazon-eu',          'parent_id' => 3,    'type' => 'sub_channel', 'sort_order' => 2, 'is_spent' => true, 'is_sales' => true, 'allows_direct_entry' => false],

            // Amazon EU countries
            ['id' => 30, 'name' => 'Germany',     'slug' => 'amazon-eu-de',       'parent_id' => 21,   'type' => 'region',      'sort_order' => 1, 'is_spent' => true, 'is_sales' => true, 'allows_direct_entry' => true],
            ['id' => 31, 'name' => 'France',      'slug' => 'amazon-eu-fr',       'parent_id' => 21,   'type' => 'region',      'sort_order' => 2, 'is_spent' => true, 'is_sales' => true, 'allows_direct_entry' => true],
            ['id' => 32, 'name' => 'Italy',       'slug' => 'amazon-eu-it',       'parent_id' => 21,   'type' => 'region',      'sort_order' => 3, 'is_spent' => true, 'is_sales' => true, 'allows_direct_entry' => true],
            ['id' => 33, 'name' => 'Spain',       'slug' => 'amazon-eu-es',       'parent_id' => 21,   'type' => 'region',      'sort_order' => 4, 'is_spent' => true, 'is_sales' => true, 'allows_direct_entry' => true],
            ['id' => 34, 'name' => 'Netherlands', 'slug' => 'amazon-eu-nl',       'parent_id' => 21,   'type' => 'region',      'sort_order' => 5, 'is_spent' => true, 'is_sales' => true, 'allows_direct_entry' => true],
            ['id' => 35, 'name' => 'Poland',      'slug' => 'amazon-eu-pl',       'parent_id' => 21,   'type' => 'region',      'sort_order' => 6, 'is_spent' => true, 'is_sales' => true, 'allows_direct_entry' => true],
            ['id' => 36, 'name' => 'Sweden',      'slug' => 'amazon-eu-se',       'parent_id' => 21,   'type' => 'region',      'sort_order' => 7, 'is_spent' => true, 'is_sales' => true, 'allows_direct_entry' => true],
            ['id' => 37, 'name' => 'Belgium',     'slug' => 'amazon-eu-be',       'parent_id' => 21,   'type' => 'region',      'sort_order' => 8, 'is_spent' => true, 'is_sales' => true, 'allows_direct_entry' => true],
            ['id' => 38, 'name' => 'Ireland',     'slug' => 'amazon-eu-ie',       'parent_id' => 21,   'type' => 'region',      'sort_order' => 9, 'is_spent' => true, 'is_sales' => true, 'allows_direct_entry' => true],
        ];

        foreach ($platforms as $platform) {
            SalePlatform::updateOrCreate(
                ['id' => $platform['id']],
                array_merge($platform, ['is_active' => true])
            );
        }
    }
}

