<?php

namespace Database\Seeders;

use App\Models\Category;
use Illuminate\Database\Seeder;
use Illuminate\Support\Str;

class CategorySeeder extends Seeder
{
    public function run(): void
    {
        $tree = [
            ['Beauty', 'सौन्दर्य', '💇', [
                ["Men's haircut", 'पुरुष कपाल काट्ने', '💈', ["men's cut", 'beard trim', "kids' cut"], ['haircut', 'cut', 'barber', 'कपाल'], 200, 500],
                ["Women's haircut", 'महिला कपाल काट्ने', '💇', ['cut', 'trim', 'blow dry'], ['haircut', 'salon', 'कपाल'], 300, 1200],
                ['Facial', 'फेसियल', '🧖', ['cleanup', 'facial', 'threading'], ['facial', 'parlour', 'beauty'], 500, 2000],
            ]],
            ['Repairs', 'मर्मत', '🛠️', [
                ['Shoe repair', 'जुत्ता मर्मत', '👟', ['sole', 'stitching', 'polish'], ['shoe fix', 'jutta', 'जुत्ता', 'cobbler'], 100, 300],
                ['Tailoring', 'सिलाइ', '🧵', ['hemming', 'alteration', 'stitch new'], ['tailor', 'darji', 'सिलाइ', 'alteration'], 150, 800],
                ['Key copy', 'साँचो बनाउने', '🔑', ['house key', 'bike key'], ['keys', 'key', 'साँचो'], 50, 200],
                ['Phone fix', 'फोन मर्मत', '📱', ['screen', 'battery', 'charging port'], ['mobile repair', 'phone', 'फोन'], 300, 3000],
            ]],
            ['Vehicle', 'सवारी साधन', '🚗', [
                ['Car wash', 'कार धुलाइ', '🚗', ['outside', 'full wash', 'interior'], ['car wash', 'wash', 'धुलाइ'], 300, 700],
                ['Bike servicing', 'बाइक सर्भिस', '🏍️', ['wash', 'servicing', 'oil change'], ['bike', 'motorbike', 'servicing'], 200, 1500],
            ]],
            ['Food & drink', 'खाना र पेय', '☕', [
                ['Café', 'क्याफे', '☕', ['coffee', 'tea', 'snacks'], ['coffee', 'cafe', 'tea', 'चिया'], 100, 400],
            ]],
        ];

        foreach ($tree as $mainOrder => [$name, $nameNe, $emoji, $children]) {
            $main = Category::query()->updateOrCreate(['slug' => Str::slug($name)], [
                'name' => $name,
                'name_ne' => $nameNe,
                'emoji' => $emoji,
                'status' => true,
                'sort_order' => $mainOrder,
            ]);

            foreach ($children as $order => [$childName, $childNe, $childEmoji, $options, $keywords, $min, $max]) {
                Category::query()->updateOrCreate(['slug' => Str::slug($childName)], [
                    'parent_id' => $main->id,
                    'name' => $childName,
                    'name_ne' => $childNe,
                    'emoji' => $childEmoji,
                    'options' => $options,
                    'keywords' => $keywords,
                    'typical_price_min' => $min,
                    'typical_price_max' => $max,
                    'status' => true,
                    'sort_order' => $order,
                ]);
            }
        }
    }
}
