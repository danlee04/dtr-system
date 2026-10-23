<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('leave_ledger_entries', function (Blueprint $table) {
            $table->foreignId('leave_application_id')->nullable()->after('posting_key')->constrained()->restrictOnDelete();
        });
    }

    public function down(): void
    {
        Schema::table('leave_ledger_entries', function (Blueprint $table) {
            $table->dropConstrainedForeignId('leave_application_id');
        });
    }
};
