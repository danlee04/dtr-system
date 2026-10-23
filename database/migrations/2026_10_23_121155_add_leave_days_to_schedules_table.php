<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('schedules', function (Blueprint $table) {
            // Days charged to VL or SL for a day of leave on this schedule: 1.250 on the 4-Day Week.
            $table->decimal('leave_days', 4, 3)->default(1)->after('block2_out');
        });
    }

    public function down(): void
    {
        Schema::table('schedules', function (Blueprint $table) {
            $table->dropColumn('leave_days');
        });
    }
};
