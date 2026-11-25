<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('categories', function (Blueprint $table) {
            $table->id();
            $table->foreignId('parent_id')->nullable()->constrained('categories')->restrictOnDelete();
            $table->string('name');
            $table->string('name_ne')->nullable();
            $table->string('slug')->unique();
            $table->string('emoji', 16)->nullable();
            $table->json('options')->nullable();
            $table->json('keywords')->nullable();
            $table->unsignedInteger('typical_price_min')->nullable();
            $table->unsignedInteger('typical_price_max')->nullable();
            $table->boolean('status')->default(true);
            $table->unsignedSmallInteger('sort_order')->default(0);
            $table->timestamps();
        });

        Schema::create('category_requests', function (Blueprint $table) {
            $table->id();
            $table->foreignId('user_id')->nullable()->constrained()->nullOnDelete();
            $table->string('query');
            $table->timestamps();
        });

        Schema::create('shops', function (Blueprint $table) {
            $table->id();
            $table->foreignId('user_id')->constrained()->cascadeOnDelete();
            $table->foreignId('category_id')->constrained()->restrictOnDelete();
            $table->string('name');
            $table->string('phone', 20)->nullable();
            $table->string('area')->nullable();
            $table->string('landmark')->nullable();
            $table->decimal('latitude', 10, 7);
            $table->decimal('longitude', 10, 7);
            $table->json('opening_hours')->nullable();
            $table->string('blurb')->nullable();
            $table->json('vibes')->nullable();
            $table->boolean('women_run')->default(false);
            $table->string('status', 30)->default('pending')->index();
            $table->text('status_note')->nullable();
            $table->boolean('check_photos')->default(false);
            $table->boolean('check_pin')->default(false);
            $table->boolean('check_phone')->default(false);
            $table->boolean('check_prices')->default(false);
            $table->foreignId('reviewed_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamp('reviewed_at')->nullable();
            $table->timestamp('submitted_at')->nullable();
            $table->timestamp('auto_paused_at')->nullable();
            $table->string('check_in_code', 12)->unique();
            $table->date('open_today_on')->nullable();
            $table->timestamp('open_confirmed_at')->nullable();
            $table->timestamp('no_wait_until')->nullable();
            $table->timestamps();

            $table->index(['latitude', 'longitude']);
        });

        Schema::create('shop_photos', function (Blueprint $table) {
            $table->id();
            $table->foreignId('shop_id')->constrained()->cascadeOnDelete();
            $table->string('path');
            $table->unsignedSmallInteger('sort_order')->default(0);
            $table->timestamps();
        });

        Schema::create('shop_services', function (Blueprint $table) {
            $table->id();
            $table->foreignId('shop_id')->constrained()->cascadeOnDelete();
            $table->string('name');
            $table->string('for_who', 20)->nullable();
            $table->unsignedSmallInteger('minutes');
            $table->unsignedInteger('price_min');
            $table->unsignedInteger('price_max');
            $table->boolean('walk_ins')->default(true);
            $table->string('photo_path')->nullable();
            $table->unsignedSmallInteger('sort_order')->default(0);
            $table->timestamps();
        });

        Schema::create('offers', function (Blueprint $table) {
            $table->id();
            $table->foreignId('shop_id')->constrained()->cascadeOnDelete();
            $table->unsignedTinyInteger('percent');
            $table->json('weekdays');
            $table->time('from_time');
            $table->time('to_time');
            $table->date('starts_on');
            $table->date('ends_on');
            $table->timestamp('stopped_at')->nullable();
            $table->timestamps();
        });

        Schema::create('shop_views', function (Blueprint $table) {
            $table->id();
            $table->foreignId('shop_id')->constrained()->cascadeOnDelete();
            $table->foreignId('user_id')->nullable()->constrained()->nullOnDelete();
            $table->timestamp('created_at')->useCurrent()->index();
        });

        Schema::create('favorites', function (Blueprint $table) {
            $table->foreignId('user_id')->constrained()->cascadeOnDelete();
            $table->foreignId('shop_id')->constrained()->cascadeOnDelete();
            $table->timestamps();

            $table->primary(['user_id', 'shop_id']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('favorites');
        Schema::dropIfExists('shop_views');
        Schema::dropIfExists('offers');
        Schema::dropIfExists('shop_services');
        Schema::dropIfExists('shop_photos');
        Schema::dropIfExists('shops');
        Schema::dropIfExists('category_requests');
        Schema::dropIfExists('categories');
    }
};
