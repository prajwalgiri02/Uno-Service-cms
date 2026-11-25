<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('nudges', function (Blueprint $table) {
            $table->id();
            $table->foreignId('user_id')->constrained()->cascadeOnDelete();
            $table->foreignId('shop_id')->constrained()->cascadeOnDelete();
            $table->json('shop_ids');
            $table->json('errand_ids');
            $table->unsignedSmallInteger('distance_m');
            $table->string('title');
            $table->string('body');
            $table->string('result', 20)->default('sent')->index();
            $table->boolean('push_delivered')->default(false);
            $table->timestamp('opened_at')->nullable();
            $table->timestamp('visited_at')->nullable();
            $table->timestamps();

            $table->index(['user_id', 'created_at']);
            $table->index(['shop_id', 'created_at']);
        });

        Schema::create('nudge_shop', function (Blueprint $table) {
            $table->foreignId('nudge_id')->constrained()->cascadeOnDelete();
            $table->foreignId('shop_id')->constrained()->cascadeOnDelete();

            $table->primary(['nudge_id', 'shop_id']);
            $table->index('shop_id');
        });

        Schema::create('check_ins', function (Blueprint $table) {
            $table->id();
            $table->foreignId('user_id')->constrained()->cascadeOnDelete();
            $table->foreignId('shop_id')->constrained()->cascadeOnDelete();
            $table->foreignId('nudge_id')->nullable()->constrained()->nullOnDelete();
            $table->string('method', 20);
            $table->unsignedInteger('points');
            $table->unsignedInteger('saved_rs')->default(0);
            $table->unsignedSmallInteger('minutes_saved')->default(0);
            $table->boolean('first_visit')->default(false);
            $table->timestamps();

            $table->index(['shop_id', 'created_at']);
            $table->index(['user_id', 'created_at']);
        });

        Schema::create('point_transactions', function (Blueprint $table) {
            $table->id();
            $table->foreignId('user_id')->constrained()->cascadeOnDelete();
            $table->foreignId('check_in_id')->nullable()->constrained()->nullOnDelete();
            $table->integer('points');
            $table->string('reason', 40);
            $table->foreignId('created_by')->nullable()->constrained('users')->nullOnDelete();
            $table->string('note')->nullable();
            $table->timestamps();
        });

        Schema::create('reviews', function (Blueprint $table) {
            $table->id();
            $table->foreignId('check_in_id')->unique()->constrained()->cascadeOnDelete();
            $table->foreignId('user_id')->constrained()->cascadeOnDelete();
            $table->foreignId('shop_id')->constrained()->cascadeOnDelete();
            $table->unsignedTinyInteger('stars');
            $table->text('text')->nullable();
            $table->text('reply')->nullable();
            $table->timestamp('replied_at')->nullable();
            $table->timestamps();
        });

        Schema::create('reports', function (Blueprint $table) {
            $table->id();
            $table->foreignId('user_id')->constrained()->cascadeOnDelete();
            $table->foreignId('shop_id')->constrained()->cascadeOnDelete();
            $table->string('reason', 30);
            $table->text('note')->nullable();
            $table->string('status', 20)->default('open')->index();
            $table->string('resolution', 30)->nullable();
            $table->text('resolution_note')->nullable();
            $table->foreignId('resolved_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamp('resolved_at')->nullable();
            $table->timestamps();
        });

        Schema::create('support_tickets', function (Blueprint $table) {
            $table->id();
            $table->foreignId('user_id')->constrained()->cascadeOnDelete();
            $table->string('type', 20);
            $table->string('subject');
            $table->string('status', 20)->default('open')->index();
            $table->timestamp('due_at');
            $table->timestamp('first_replied_at')->nullable();
            $table->timestamp('resolved_at')->nullable();
            $table->timestamps();
        });

        Schema::create('support_messages', function (Blueprint $table) {
            $table->id();
            $table->foreignId('support_ticket_id')->constrained()->cascadeOnDelete();
            $table->foreignId('user_id')->constrained()->cascadeOnDelete();
            $table->boolean('from_admin')->default(false);
            $table->text('body');
            $table->timestamps();
        });

        Schema::create('settings', function (Blueprint $table) {
            $table->string('key')->primary();
            $table->json('value');
            $table->timestamps();
        });

        Schema::create('shop_status_logs', function (Blueprint $table) {
            $table->id();
            $table->foreignId('shop_id')->constrained()->cascadeOnDelete();
            $table->foreignId('user_id')->nullable()->constrained()->nullOnDelete();
            $table->string('from_status', 30)->nullable();
            $table->string('to_status', 30);
            $table->text('note')->nullable();
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('shop_status_logs');
        Schema::dropIfExists('settings');
        Schema::dropIfExists('support_messages');
        Schema::dropIfExists('support_tickets');
        Schema::dropIfExists('reports');
        Schema::dropIfExists('reviews');
        Schema::dropIfExists('point_transactions');
        Schema::dropIfExists('check_ins');
        Schema::dropIfExists('nudge_shop');
        Schema::dropIfExists('nudges');
    }
};
