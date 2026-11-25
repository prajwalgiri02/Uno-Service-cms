<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('otps', function (Blueprint $table) {
            $table->id();
            $table->string('channel', 20);
            $table->string('purpose', 40);
            $table->string('destination');
            $table->string('code');
            $table->unsignedSmallInteger('attempts')->default(0);
            $table->unsignedSmallInteger('max_attempts');
            $table->unsignedSmallInteger('send_count')->default(1);
            $table->unsignedSmallInteger('max_sends');
            $table->timestamp('expires_at');
            $table->timestamps();

            $table->unique(['channel', 'purpose', 'destination']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('otps');
    }
};
