<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * HR disapproves a pending application with the reason, printed in 7.D of the
 * form; until now a disapproval was recorded as a cancellation (HR, 2026-11-23).
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('leave_applications', function (Blueprint $table) {
            $table->timestamp('disapproved_at')->nullable()->after('cancel_reason');
            $table->foreignId('disapproved_by_user_id')->nullable()->after('disapproved_at')->constrained('users')->nullOnDelete();
            $table->text('disapproval_reason')->nullable()->after('disapproved_by_user_id');
        });
    }

    public function down(): void
    {
        Schema::table('leave_applications', function (Blueprint $table) {
            $table->dropConstrainedForeignId('disapproved_by_user_id');
            $table->dropColumn(['disapproved_at', 'disapproval_reason']);
        });
    }
};
