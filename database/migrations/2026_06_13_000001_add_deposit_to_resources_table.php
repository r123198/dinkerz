<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('resources', function (Blueprint $table) {
            // Online deposit in cents. Null/0 means full payment is required
            // online; a positive value lets players pay the balance on-site.
            $table->unsignedInteger('deposit_per_slot')->nullable()->after('price_per_slot');
        });
    }

    public function down(): void
    {
        Schema::table('resources', function (Blueprint $table) {
            $table->dropColumn('deposit_per_slot');
        });
    }
};
