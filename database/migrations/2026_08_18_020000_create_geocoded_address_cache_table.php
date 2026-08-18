<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('geocoded_address_cache', function (Blueprint $table) {
            $table->id();
            $table->string('normalized_address')->unique();
            $table->string('requested_address');
            $table->string('formatted_address');
            $table->decimal('latitude', 10, 7);
            $table->decimal('longitude', 10, 7);
            $table->string('place_id')->nullable()->index();
            $table->unsignedBigInteger('hit_count')->default(0);
            $table->timestamp('last_used_at')->nullable();
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('geocoded_address_cache');
    }
};
