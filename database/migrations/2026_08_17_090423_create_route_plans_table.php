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
        Schema::create('route_plans', function (Blueprint $table) {
            $table->id();
            $table->string('name')->default('Athens route');
            $table->string('status')->default('draft');
            $table->string('provider')->nullable();
            $table->unsignedInteger('total_distance_meters')->nullable();
            $table->unsignedInteger('total_duration_seconds')->nullable();
            $table->longText('encoded_polyline')->nullable();
            $table->json('provider_payload')->nullable();
            $table->timestamps();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('route_plans');
    }
};
