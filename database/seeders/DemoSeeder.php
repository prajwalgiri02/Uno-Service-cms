<?php

namespace Database\Seeders;

use App\Enums\CheckInMethod;
use App\Enums\NudgeResult;
use App\Enums\ReportReason;
use App\Enums\ShopStatus;
use App\Enums\TicketType;
use App\Models\Category;
use App\Models\CheckIn;
use App\Models\Nudge;
use App\Models\PointTransaction;
use App\Models\Review;
use App\Models\Shop;
use App\Models\User;
use App\Services\ProfileService;
use App\Services\SupportService;
use Illuminate\Database\Seeder;

/**
 * Local sample data around Putalisadak, Kathmandu. Run with:
 * php artisan db:seed --class=DemoSeeder
 */
class DemoSeeder extends Seeder
{
    private const CENTER = [27.7041, 85.3226];

    public function run(ProfileService $profile, SupportService $support): void
    {
        $this->call([RoleSeeder::class, CategorySeeder::class]);

        $hours = array_fill_keys(['sun', 'mon', 'tue', 'wed', 'thu', 'fri'], ['open' => '09:00', 'close' => '19:30']) + ['sat' => ['open' => '10:00', 'close' => '17:00']];

        $shops = [
            ['Style Point Salon', "Men's haircut", 9841004521, 0.0012, 0.0011, ShopStatus::APPROVED, 'NJK-5080', [["Men's haircut", 'men', 25, 250, 400], ['Beard and shave', 'men', 15, 150, 200], ['Haircut and beard', 'men', 40, 350, 500]]],
            ['Classic Gents', "Men's haircut", 9841006618, 0.0035, 0.0018, ShopStatus::APPROVED, 'NJK-2211', [["Men's haircut", 'men', 20, 300, 400], ['Hot towel shave', 'men', 20, 200, 250]]],
            ['Bishnu Shoe Repair', 'Shoe repair', 9851003302, -0.0021, -0.0025, ShopStatus::APPROVED, 'NJK-3471', [['Sole repair', 'all', 30, 150, 300], ['Stitching', 'all', 15, 100, 200], ['Polish', 'all', 10, 60, 100]]],
            ['Quick Keys', 'Key copy', 9841277881, 0.0026, 0.0024, ShopStatus::APPROVED, 'NJK-6612', [['Key copy', 'all', 5, 60, 150]]],
            ['Himalayan Java', 'Café', 9801422110, -0.0010, 0.0028, ShopStatus::APPROVED, 'NJK-9001', [['Coffee', 'all', 5, 160, 350]]],
            ['Shine Car Spa', 'Car wash', 9801055443, -0.0050, -0.0030, ShopStatus::APPROVED, 'NJK-4410', [['Car wash', 'all', 40, 400, 700]]],
            ['Glow Beauty Parlour', 'Facial', 9841007710, 0.0018, -0.0016, ShopStatus::PENDING, 'NJK-7710', [['Cleanup facial', 'women', 45, 800, 1500], ['Threading', 'women', 10, 50, 100]]],
            ['Quick Wash Point', 'Car wash', 9841009054, -0.0031, 0.0040, ShopStatus::PENDING, 'NJK-9054', [['Outside wash', 'all', 25, 300, 450]]],
            ['Sharma Tailoring', 'Tailoring', 9841001189, 0.0042, -0.0008, ShopStatus::CHANGES_REQUESTED, 'NJK-1189', [['Hemming', 'all', 20, 150, 250]]],
        ];

        $createdShops = collect();

        foreach ($shops as $i => [$name, $category, $phone, $dLat, $dLng, $status, $code, $services]) {
            $owner = User::query()->firstOrCreate(['phone' => '+977'.$phone], ['name' => $name.' owner', 'phone_verified_at' => now()]);
            $owner->syncRoles(['customer', 'vendor']);

            $shop = Shop::query()->updateOrCreate(['user_id' => $owner->id], [
                'category_id' => Category::query()->where('name', $category)->value('id'),
                'name' => $name,
                'phone' => '+977'.$phone,
                'area' => $i % 3 === 0 ? 'Kamalpokhari' : 'Putalisadak',
                'landmark' => 'near Shanker Dev Campus gate',
                'latitude' => self::CENTER[0] + $dLat,
                'longitude' => self::CENTER[1] + $dLng,
                'opening_hours' => $hours,
                'vibes' => ['cash or QR'],
                'status' => $status,
                'status_note' => $status === ShopStatus::CHANGES_REQUESTED ? 'Please add a photo of the shop front.' : null,
                'submitted_at' => now()->subDays($i % 5),
                'check_in_code' => $code,
                'open_today_on' => $status === ShopStatus::APPROVED ? today() : null,
                'open_confirmed_at' => $status === ShopStatus::APPROVED ? today()->setTime(8, 12) : null,
                'no_wait_until' => in_array($code, ['NJK-5080', 'NJK-6612'], true) ? now()->addMinutes(40) : null,
                'check_pin' => true,
                'check_prices' => true,
            ]);

            $shop->services()->delete();
            foreach ($services as $order => [$serviceName, $for, $minutes, $min, $max]) {
                $shop->services()->create(['name' => $serviceName, 'for_who' => $for, 'minutes' => $minutes, 'price_min' => $min, 'price_max' => $max, 'sort_order' => $order]);
            }

            $createdShops->push($shop);
        }

        $stylePoint = $createdShops->first();
        $stylePoint->offers()->delete();
        $stylePoint->offers()->create(['percent' => 10, 'weekdays' => [1, 2, 3, 4, 5, 7], 'from_time' => '14:00:00', 'to_time' => '18:00:00', 'starts_on' => today(), 'ends_on' => today()->addDays(6)]);

        $customers = collect([
            ['Sita Sharma', 9841234567],
            ['Sujan Karki', 9841238812],
            ['Pooja Thapa', 9841232290],
            ['Rohan Basnet', 9841237731],
            ['Manisha Rai', 9841231104],
        ])->map(function (array $row) use ($profile) {
            $user = User::query()->firstOrCreate(['phone' => '+977'.$row[1]], ['name' => $row[0], 'area' => 'putalisadak', 'phone_verified_at' => now()]);
            $user->syncRoles(['customer']);
            $profile->freeWindows($user);
            $profile->blockedPlaces($user);

            return $user;
        });

        $sita = $customers->first();
        $sita->errands()->delete();
        foreach ([["men's haircut", "Men's haircut", 400, 500, true], ['shoe repair', 'Shoe repair', 300, 500, false], ['tailoring', 'Tailoring', 600, 1000, false]] as [$title, $category, $max, $radius, $noWait]) {
            $sita->errands()->create([
                'category_id' => Category::query()->where('name', $category)->value('id'),
                'title' => $title,
                'max_price' => $max,
                'radius_m' => $radius,
                'only_no_wait' => $noWait,
                'kept_at' => $title === 'tailoring' ? now()->subDays(18) : now(),
            ]);
        }

        if (CheckIn::query()->doesntExist()) {
            $live = $createdShops->filter->isLive()->values();

            foreach ($customers as $c => $customer) {
                foreach (range(0, 4 - $c) as $v) {
                    $shop = $live[($c + $v) % $live->count()];
                    $at = now()->subDays($v * 3 + $c)->setTime(17, 10 + $v);

                    $nudge = Nudge::query()->create([
                        'user_id' => $customer->id, 'shop_id' => $shop->id, 'shop_ids' => [$shop->id], 'errand_ids' => [],
                        'distance_m' => 200 + 40 * $v, 'title' => "{$shop->category->name} · ".(200 + 40 * $v).' m away',
                        'body' => "{$shop->name} is open now.", 'result' => NudgeResult::VISITED, 'push_delivered' => true,
                        'opened_at' => $at->copy()->subMinutes(20), 'visited_at' => $at,
                    ]);
                    $nudge->shops()->attach($shop->id);
                    $nudge->forceFill(['created_at' => $at->copy()->subMinutes(25)])->save();

                    $checkIn = CheckIn::query()->create([
                        'user_id' => $customer->id, 'shop_id' => $shop->id, 'nudge_id' => $nudge->id,
                        'method' => CheckInMethod::SHOP_CODE, 'points' => 20, 'first_visit' => $v === 0,
                        'saved_rs' => $shop->id === $stylePoint->id ? 25 : 0,
                    ]);
                    $checkIn->forceFill(['created_at' => $at])->save();

                    PointTransaction::query()->create(['user_id' => $customer->id, 'check_in_id' => $checkIn->id, 'points' => 20, 'reason' => PointTransaction::REASON_CHECK_IN]);
                    $customer->increment('points_balance', 20);

                    if ($v < 2) {
                        Review::query()->create([
                            'check_in_id' => $checkIn->id, 'user_id' => $customer->id, 'shop_id' => $shop->id,
                            'stars' => [5, 4, 2][($c + $v) % 3],
                            'text' => ['quick and clean, no wait at all.', 'good, but music was a bit loud.', 'app said no wait but I waited 20 minutes.'][($c + $v) % 3],
                        ]);
                    }
                }
            }

            foreach ([[1, ReportReason::CLOSED_WHEN_OPEN], [2, ReportReason::PRICE_HIGHER]] as [$c, $reason]) {
                $createdShops[1]->reports()->create(['user_id' => $customers[$c]->id, 'reason' => $reason, 'status' => 'open']);
            }

            $support->open($customers[0], TicketType::USER, 'Nudge arrived at a closed shop', 'I got a nudge for Classic Gents but they were closed.');
            $support->open($stylePoint->owner, TicketType::VENDOR, 'Cannot change opening hours', 'Saturday hours will not save.');
            $support->open($customers[1], TicketType::USER, 'Points not added after check-in', 'I checked in at Quick Keys yesterday.');
        }
    }
}
