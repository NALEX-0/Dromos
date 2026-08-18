<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('route_plans', function (Blueprint $table) {
            $table->string('route_mode')->default('optimized')->after('status');
            $table->json('encoded_polylines')->nullable()->after('encoded_polyline');
        });
    }

    public function down(): void
    {
        Schema::table('route_plans', function (Blueprint $table) {
            $table->dropColumn(['route_mode', 'encoded_polylines']);
        });
    }
};
