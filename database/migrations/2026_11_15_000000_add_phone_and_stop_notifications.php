<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('stops', function (Blueprint $table) {
            $table->string('phone', 20)->nullable()->after('address');
        });

        Schema::create('stop_notifications', function (Blueprint $table) {
            $table->id();
            $table->foreignId('stop_id')->constrained()->cascadeOnDelete();
            $table->foreignId('user_id')->constrained()->cascadeOnDelete();
            $table->string('provider', 30);
            $table->string('provider_message_id')->nullable()->index();
            $table->string('phone', 20);
            $table->text('message');
            $table->string('status', 30)->default('pending');
            $table->text('failure_reason')->nullable();
            $table->timestamp('sent_at')->nullable();
            $table->timestamp('delivered_at')->nullable();
            $table->json('provider_response')->nullable();
            $table->timestamps();

            $table->index(['stop_id', 'created_at']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('stop_notifications');

        Schema::table('stops', function (Blueprint $table) {
            $table->dropColumn('phone');
        });
    }
};
