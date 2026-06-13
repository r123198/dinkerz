<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('payment_events', function (Blueprint $table) {
            $table->id();
            $table->foreignId('payment_id')->nullable()->constrained()->cascadeOnDelete();
            $table->string('provider');
            $table->string('provider_event_id');
            $table->string('type');
            $table->json('payload')->nullable();
            $table->timestamps();

            // Idempotency: a provider event may only ever be processed once.
            $table->unique(['provider', 'provider_event_id']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('payment_events');
    }
};
