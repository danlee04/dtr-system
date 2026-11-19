<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('dtr_uploads', function (Blueprint $table) {
            $table->timestamp('finalized_at')->nullable();
            $table->foreignId('finalized_by_user_id')->nullable()->constrained('users')->nullOnDelete();
        });

        Schema::table('leave_ledger_entries', function (Blueprint $table) {
            // Null on delete: a DTR can be deleted only once reopened, when its rows net to zero and stay in the history.
            $table->foreignId('dtr_upload_id')->nullable()->after('leave_application_id')->constrained()->nullOnDelete();
        });
    }

    public function down(): void
    {
        Schema::table('leave_ledger_entries', function (Blueprint $table) {
            $table->dropConstrainedForeignId('dtr_upload_id');
        });

        Schema::table('dtr_uploads', function (Blueprint $table) {
            $table->dropConstrainedForeignId('finalized_by_user_id');
            $table->dropColumn('finalized_at');
        });
    }
};
