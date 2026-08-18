<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        Schema::create('stops', function (Blueprint $table) {
            $table->id();
            $table->foreignId('route_plan_id')->constrained()->cascadeOnDelete();
            $table->foreignId('vehicle_id')->nullable()->constrained()->nullOnDelete();
            $table->string('type')->default('visit');
            $table->unsignedSmallInteger('input_order');
            $table->unsignedSmallInteger('optimized_order')->nullable();
            $table->string('address');
            $table->string('postal_code', 10)->nullable();
            $table->string('city')->default('Athens');
            $table->string('region')->nullable();
            $table->string('formatted_address')->nullable();
            $table->decimal('latitude', 10, 7)->nullable();
            $table->decimal('longitude', 10, 7)->nullable();
            $table->string('place_id')->nullable();
            $table->string('geocoding_status')->default('pending');
            $table->unsignedInteger('leg_distance_meters')->nullable();
            $table->unsignedInteger('leg_duration_seconds')->nullable();
            $table->timestamps();

            $table->index(['route_plan_id', 'optimized_order']);
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('stops');
    }
};
