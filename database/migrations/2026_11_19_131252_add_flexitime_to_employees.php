<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('employees', function (Blueprint $table) {
            // HR checks it for an 8–5 employee whose flexitime request was granted (HR, 2026-11-19).
            $table->boolean('flexitime')->default(false)->after('is_active');
        });
    }

    public function down(): void
    {
        Schema::table('employees', function (Blueprint $table) {
            $table->dropColumn('flexitime');
        });
    }
};
