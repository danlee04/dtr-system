<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * What the printed leave form needs that the system did not keep: the
 * employee's position, the Division Head who signs 7.B, and the VL and SL
 * balances the application was charged against, for 7.A.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('employees', function (Blueprint $table) {
            $table->string('position', 100)->nullable()->after('suffix');
        });

        Schema::table('leave_applications', function (Blueprint $table) {
            $table->string('division_head')->nullable()->after('commutation_requested');
            $table->json('credits_at_filing')->nullable()->after('breakdown');
        });
    }

    public function down(): void
    {
        Schema::table('leave_applications', function (Blueprint $table) {
            $table->dropColumn(['division_head', 'credits_at_filing']);
        });

        Schema::table('employees', function (Blueprint $table) {
            $table->dropColumn('position');
        });
    }
};
