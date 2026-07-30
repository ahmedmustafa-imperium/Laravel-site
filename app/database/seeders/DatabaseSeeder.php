<?php

namespace Database\Seeders;

use App\Models\Category;
use App\Models\Coupon;
use App\Models\Product;
use App\Models\User;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\Hash;

class DatabaseSeeder extends Seeder
{
    public function run(): void
    {
        if (config('services.seeding.demo_users') && app()->environment(['local', 'testing'])) {
            User::updateOrCreate(['email' => 'admin@snh.local'], [
                'name' => 'SNH Administrator', 'phone' => '+971523993759',
                'password' => Hash::make('password'), 'is_admin' => true,
            ]);
            User::updateOrCreate(['email' => 'customer@example.com'], [
                'name' => 'Demo Customer', 'phone' => '+971500000000',
                'password' => Hash::make('password'), 'is_admin' => false,
            ]);
        }

        $adminEmail = config('services.seeding.admin_email');
        $adminPassword = config('services.seeding.admin_password');
        if ($adminEmail && $adminPassword) {
            if (mb_strlen($adminPassword) < 12) {
                throw new \RuntimeException('SEED_ADMIN_PASSWORD must contain at least 12 characters.');
            }

            User::updateOrCreate(['email' => strtolower($adminEmail)], [
                'name' => 'SNH Administrator',
                'phone' => '+971523993759',
                'password' => Hash::make($adminPassword),
                'is_admin' => true,
            ]);
        }

        if (! config('services.seeding.demo_catalog') || ! app()->environment(['local', 'testing'])) {
            return;
        }

        $topLevels = [
            ['PET Products', 'pet-products'], ['Foods Products', 'food-products'], ['Bagasse Products', 'bagasse-products'],
            ['Tissue Products', 'tissue-products'], ['Paper Pouches & Bags', 'paper-pouches-bags'],
            ['Corrugated Products', 'corrugated-products'], ['Brown Paper Products', 'brown-paper-products'],
            ['White Paper Products', 'white-paper-products'], ['Paper Sweet Boxes', 'paper-sweet-boxes'],
            ['Paper Cups', 'paper-cups'], ['Coffee Products', 'coffee-products'], ['Thermal & Woven Bags', 'thermal-woven-bags'],
            ['Plastic Products', 'plastic-products'], ['Hygiene & Protection', 'hygiene-protection'],
            ['Eco Friendly', 'eco-friendly'], ['Wooden Products', 'wooden-products'], ['Black Base Products', 'black-base-products'],
            ['Aluminium Products', 'aluminium-products'], ['Sealing Machines', 'sealing-machines'],
            ['Charcoal & Wood', 'charcoal-wood'], ['AURASYNC', 'aurasync'],
        ];

        foreach ($topLevels as $index => [$name, $slug]) {
            Category::updateOrCreate(['slug' => $slug], [
                'name' => $name, 'sort_order' => $index + 1, 'is_active' => true,
                'description' => "Browse our {$name} range for hospitality, home and wholesale buyers across the UAE.",
                'is_featured' => in_array($slug, ['aluminium-products', 'eco-friendly'], true),
                'image' => match ($slug) {
                    'aluminium-products' => 'images/snh/category-aluminium.png',
                    'eco-friendly' => 'images/snh/category-eco.png',
                    default => null,
                },
            ]);
        }

        $children = [
            ['Facial Tissues', 'facial-tissues', 'tissue-products', 'images/snh/category-tissue.png', true],
            ['Maxi Rolls', 'maxi-rolls', 'tissue-products', 'images/snh/category-maxi-rolls.png', true],
            ['Grocery', 'grocery', 'food-products', 'images/snh/category-grocery.png', true],
            ['Tea & Coffee', 'tea-coffee', 'food-products', 'images/snh/category-tea-coffee.png', true],
            ['Birds & Small Pets', 'birds-small-pets', 'pet-products', 'images/snh/category-small-pets.png', true],
            ['Dog Food', 'dog-food', 'pet-products', 'images/snh/category-dog-food.png', true],
            ['Cat Food', 'cat-food', 'pet-products', null, false], ['Pet Treats', 'pet-treats', 'pet-products', null, false],
            ['Bagasse Containers', 'bagasse-containers', 'bagasse-products', null, false],
            ['Paper Napkins', 'paper-napkins', 'tissue-products', null, false], ['Toilet Rolls', 'toilet-rolls', 'tissue-products', null, false],
            ['Paper Hot Cups', 'paper-hot-cups', 'paper-cups', null, false], ['Plastic Cups', 'plastic-cups', 'plastic-products', null, false],
            ['Cleaning Tools', 'cleaning-tools', 'hygiene-protection', null, false],
        ];

        foreach ($children as $index => [$name, $slug, $parent, $image, $featured]) {
            Category::updateOrCreate(['slug' => $slug], [
                'name' => $name, 'parent_id' => Category::where('slug', $parent)->value('id'), 'image' => $image,
                'is_featured' => $featured, 'is_active' => true, 'sort_order' => $index + 30,
                'description' => "Shop {$name} with reliable UAE-wide delivery and wholesale-friendly pricing.",
            ]);
        }

        $products = [
            ['bagasse-containers', 'Bagasse Square Food Container with PET Lid 1000ml', 'SNH-BAG-1000', 29.15, 35.00, 76, 'images/snh/product-bagasse.jpg', 'SALE', true],
            ['cleaning-tools', 'Al Fajr 360° Spin Mop with Stainless Steel Basket', 'AF-SPIN-MOP', 130.00, 158.00, 18, 'images/snh/product-cleaning.svg', 'SALE', true],
            ['cleaning-tools', 'Broom & Dustpan Set with Long Handle', 'AF-BROOM-01', 40.00, 49.00, 32, 'images/snh/product-cleaning.svg', 'SALE', true],
            ['plastic-cups', 'PET Dessert Cups with Dome Lids', 'SNH-PET-DESSERT', 25.00, 35.00, 64, 'images/snh/product-bagasse.jpg', 'SAVE 29%', true],
            ['food-products', '300ml Clear Glass Bottle with Lid', 'SNH-GLASS-300', 19.00, 25.00, 110, 'images/snh/product-placeholder.svg', 'SALE', false],
            ['eco-friendly', 'Black PSM Compostable Cutlery Set', 'SNH-PSM-BLK', 19.80, 28.00, 86, 'images/snh/product-placeholder.svg', 'ECO', true],
            ['plastic-products', 'Reusable Zipper Storage Bags', 'SNH-ZIP-01', 25.00, 30.00, 43, 'images/snh/product-placeholder.svg', 'SALE', false],
            ['paper-hot-cups', 'Brown Double Wall Paper Cups', 'SNH-DW-BROWN', 11.25, 17.00, 120, 'images/snh/product-placeholder.svg', 'SAVE 34%', true],
            ['paper-hot-cups', 'Black Ripple Wall Coffee Cups', 'SNH-RIPPLE-BLK', 11.82, 18.00, 94, 'images/snh/product-placeholder.svg', 'SALE', false],
            ['grocery', 'Koita Organic Coconut Milk 1L', 'ATF-KOITA-1L', 14.00, 19.95, 38, 'images/snh/product-grocery.svg', 'SALE', true],
            ['grocery', 'MDH Jal Jeera Masala 100g', 'ATF-MDH-JJ', 11.00, 15.00, 52, 'images/snh/product-grocery.svg', 'SALE', true],
            ['grocery', 'Fresh Whip Dairy Cream', 'ATF-FW-CREAM', 17.50, 21.54, 24, 'images/snh/product-grocery.svg', 'SALE', false],
            ['grocery', 'Rama Professional Cooking Cream', 'ATF-RAMA-01', 16.00, 19.00, 27, 'images/snh/product-grocery.svg', null, false],
            ['grocery', 'Lotus Biscoff Spread', 'ATF-LOTUS-400', 19.00, 25.00, 41, 'images/snh/product-grocery.svg', 'BESTSELLER', true],
            ['facial-tissues', 'Fresh King Facial Tissue 2 Ply', 'AF-FK-TISSUE', 26.00, 33.00, 70, 'images/snh/product-tissue.svg', 'SALE', true],
            ['facial-tissues', 'Al Fajr Facial Tissue 150 Sheets 2 Ply', 'AF-150-2P', 14.70, 18.00, 95, 'images/snh/product-tissue.svg', 'SALE', true, [
                ['name' => '1 PKT', 'price' => 14.70], ['name' => '2 PKT', 'price' => 26.39], ['name' => '6 PKT', 'price' => 60.90],
            ]],
            ['facial-tissues', 'Al Fajr Facial Tissue 200 Sheets Buy 4 Get 1', 'AF-200-B4G1', 15.00, 24.00, 65, 'images/snh/product-tissue.svg', 'SAVE 38%', true],
            ['cat-food', 'Applaws Natural Cat Food Multipack 8×60g', 'ATP-APPLAWS-8', 57.15, null, 22, 'images/snh/product-pet.svg', 'NEW', true],
            ['pet-treats', "Lily's Kitchen Training Treats", 'ATP-LILY-TREAT', 24.90, null, 17, 'images/snh/product-pet.svg', 'NEW', true],
            ['cat-food', 'Kit Cat Complete Cuisine Tin', 'ATP-KITCAT-TIN', 6.19, null, 84, 'images/snh/product-pet.svg', 'NEW', false],
        ];

        foreach ($products as $index => $item) {
            [$categorySlug, $name, $sku, $price, $compare, $stock, $image, $badge, $featured] = array_slice($item, 0, 9);
            Product::updateOrCreate(['sku' => $sku], [
                'category_id' => Category::where('slug', $categorySlug)->value('id'), 'name' => $name,
                'slug' => str($name)->slug().'-'.strtolower(str_replace(['-', ' '], '', $sku)),
                'short_description' => 'Quality everyday essentials for home, hospitality and wholesale customers.',
                'description' => 'Reliable quality, practical pack sizes and quick delivery throughout the UAE. Contact our team for bulk pricing and custom requirements.',
                'price' => $price, 'compare_at_price' => $compare, 'stock' => $stock, 'low_stock_threshold' => 8,
                'image' => $image, 'gallery' => [], 'variants' => $item[9] ?? null, 'badge' => $badge,
                'is_featured' => $featured, 'is_active' => true, 'sort_order' => $index + 1,
            ]);
        }

        Coupon::updateOrCreate(['code' => 'WELCOME10'], [
            'type' => 'percent', 'value' => 10, 'minimum_amount' => 0,
            'usage_limit' => null, 'times_used' => 0, 'is_active' => true,
        ]);
    }
}
