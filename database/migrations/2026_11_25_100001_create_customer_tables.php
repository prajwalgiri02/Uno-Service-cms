<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('errands', function (Blueprint $table) {
            $table->id();
            $table->foreignId('user_id')->constrained()->cascadeOnDelete();
            $table->foreignId('category_id')->constrained()->restrictOnDelete();
            $table->string('title');
            $table->string('variant')->nullable();
            $table->unsignedInteger('max_price')->nullable();
            $table->unsignedSmallInteger('radius_m')->default(1000);
            $table->boolean('only_no_wait')->default(false);
            $table->boolean('offers_only')->default(false);
            $table->boolean('women_run')->default(false);
            $table->boolean('active')->default(true);
            $table->string('paused_note')->nullable();
            $table->timestamp('expires_at')->nullable();
            $table->timestamp('kept_at')->nullable();
            $table->timestamp('done_at')->nullable();
            $table->timestamps();

            $table->index(['user_id', 'active', 'done_at']);
        });

        Schema::create('free_windows', function (Blueprint $table) {
            $table->id();
            $table->foreignId('user_id')->constrained()->cascadeOnDelete();
            $table->string('preset', 30)->nullable();
            $table->string('title');
            $table->json('weekdays');
            $table->time('from_time');
            $table->time('to_time');
            $table->boolean('enabled')->default(true);
            $table->timestamps();

            $table->unique(['user_id', 'preset']);
        });

        Schema::create('blocked_places', function (Blueprint $table) {
            $table->id();
            $table->foreignId('user_id')->constrained()->cascadeOnDelete();
            $table->string('kind', 20)->default('custom');
            $table->string('label');
            $table->decimal('latitude', 10, 7)->nullable();
            $table->decimal('longitude', 10, 7)->nullable();
            $table->unsignedSmallInteger('radius_m')->default(150);
            $table->boolean('enabled')->default(true);
            $table->timestamps();
        });

        Schema::create('device_tokens', function (Blueprint $table) {
            $table->id();
            $table->foreignId('user_id')->constrained()->cascadeOnDelete();
            $table->string('device_id');
            $table->text('token');
            $table->string('platform', 20)->nullable();
            $table->boolean('notifications_allowed')->default(true);
            $table->string('location_permission', 20)->nullable();
            $table->timestamp('last_seen_at')->nullable();
            $table->timestamps();

            $table->unique(['user_id', 'device_id']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('device_tokens');
        Schema::dropIfExists('blocked_places');
        Schema::dropIfExists('free_windows');
        Schema::dropIfExists('errands');
    }
};
