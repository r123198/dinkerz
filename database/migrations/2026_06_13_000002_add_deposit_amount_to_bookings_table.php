<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('bookings', function (Blueprint $table) {
            // Amount collected online to confirm this booking. Null is treated
            // as equal to `amount` (full payment) for historical rows.
            $table->unsignedInteger('deposit_amount')->nullable()->after('amount');
        });
    }

    public function down(): void
    {
        Schema::table('bookings', function (Blueprint $table) {
            $table->dropColumn('deposit_amount');
        });
    }
};
