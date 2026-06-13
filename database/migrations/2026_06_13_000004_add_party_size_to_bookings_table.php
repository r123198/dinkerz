<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('bookings', function (Blueprint $table) {
            // Optional headcount for the session. Drives court suggestions and
            // front-desk planning; never required to book.
            $table->unsignedSmallInteger('party_size')->nullable()->after('guest_email');
        });
    }

    public function down(): void
    {
        Schema::table('bookings', function (Blueprint $table) {
            $table->dropColumn('party_size');
        });
    }
};
