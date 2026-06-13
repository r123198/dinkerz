<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('resource_blocks', function (Blueprint $table) {
            $table->id();
            $table->foreignId('tenant_id')->constrained()->cascadeOnDelete();
            $table->foreignId('resource_id')->constrained()->cascadeOnDelete();
            $table->timestamp('starts_at');
            $table->timestamp('ends_at');
            $table->string('reason')->nullable();
            $table->timestamps();

            $table->index(['resource_id', 'starts_at', 'ends_at']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('resource_blocks');
    }
};
