<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('resources', function (Blueprint $table) {
            $table->id();
            $table->foreignId('tenant_id')->constrained()->cascadeOnDelete();
            $table->foreignId('facility_id')->constrained()->cascadeOnDelete();
            $table->string('name');
            $table->string('type')->default('pickleball_court');
            $table->unsignedInteger('price_per_slot');
            $table->time('opens_at')->default('06:00');
            $table->time('closes_at')->default('22:00');
            $table->unsignedSmallInteger('slot_minutes')->default(60);
            $table->unsignedSmallInteger('buffer_minutes')->default(0);
            $table->unsignedSmallInteger('booking_window_days')->default(30);
            $table->timestamp('archived_at')->nullable();
            $table->timestamps();

            $table->index(['tenant_id', 'archived_at']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('resources');
    }
};
