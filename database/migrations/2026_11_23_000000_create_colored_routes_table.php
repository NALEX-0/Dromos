<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('colored_routes', function (Blueprint $table) {
            $table->id();
            $table->foreignId('user_id')->constrained()->cascadeOnDelete();
            $table->string('name', 80);
            $table->string('color', 7);
            $table->json('addresses');
            $table->longText('encoded_polyline')->nullable();
            $table->json('path')->nullable();
            $table->unsignedInteger('distance_meters')->default(0);
            $table->unsignedInteger('duration_seconds')->default(0);
            $table->boolean('is_circular')->default(false);
            $table->boolean('is_enabled')->default(true);
            $table->timestamps();

            $table->index(['user_id', 'is_enabled']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('colored_routes');
    }
};
