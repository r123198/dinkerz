<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('waitlist_entries', function (Blueprint $table) {
            $table->id();
            $table->uuid('token')->unique();
            $table->foreignId('tenant_id')->constrained()->cascadeOnDelete();
            $table->foreignId('resource_id')->constrained()->cascadeOnDelete();
            $table->foreignId('user_id')->nullable()->constrained()->nullOnDelete();
            $table->string('guest_name')->nullable();
            $table->string('guest_email');
            $table->timestamp('starts_at');
            $table->timestamp('ends_at');
            $table->string('status')->default('waiting');
            $table->timestamp('notified_at')->nullable();
            $table->timestamps();

            $table->index(['resource_id', 'starts_at', 'status']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('waitlist_entries');
    }
};
