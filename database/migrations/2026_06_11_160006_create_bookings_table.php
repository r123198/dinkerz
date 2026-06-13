<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('bookings', function (Blueprint $table) {
            $table->id();
            $table->uuid('reference')->unique();
            $table->foreignId('tenant_id')->constrained()->cascadeOnDelete();
            $table->foreignId('resource_id')->constrained()->cascadeOnDelete();
            $table->foreignId('user_id')->nullable()->constrained()->nullOnDelete();
            $table->string('guest_name')->nullable();
            $table->string('guest_email')->nullable();
            $table->timestamp('starts_at');
            $table->timestamp('ends_at');
            $table->string('status')->default('pending_payment');
            $table->unsignedInteger('amount');
            $table->string('currency', 3)->default('PHP');
            $table->timestamp('expires_at')->nullable();
            $table->timestamp('cancelled_at')->nullable();
            $table->timestamp('reminder_sent_at')->nullable();
            $table->timestamps();

            $table->index(['resource_id', 'starts_at', 'ends_at']);
            $table->index(['tenant_id', 'status', 'starts_at']);
            $table->index(['status', 'expires_at']);
        });

        // Database-level overlap guard for inventory-blocking bookings.
        // SQLite (tests) relies on the application-level transactional check instead.
        if (DB::getDriverName() === 'pgsql') {
            DB::statement('CREATE EXTENSION IF NOT EXISTS btree_gist');
            DB::statement(<<<'SQL'
                ALTER TABLE bookings ADD CONSTRAINT bookings_no_overlap
                EXCLUDE USING gist (
                    resource_id WITH =,
                    tsrange(starts_at, ends_at) WITH &&
                )
                WHERE (status IN ('pending_payment', 'confirmed', 'completed'))
            SQL);
        }
    }

    public function down(): void
    {
        Schema::dropIfExists('bookings');
    }
};
