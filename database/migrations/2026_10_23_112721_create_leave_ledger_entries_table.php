<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('leave_ledger_entries', function (Blueprint $table) {
            $table->id();
            $table->foreignId('employee_id')->constrained()->restrictOnDelete();
            $table->string('leave_type', 8);
            $table->date('posted_on');
            // Signed: a credit is positive, a deduction negative.
            $table->decimal('days', 7, 3);
            $table->string('kind', 32);
            $table->string('period', 7)->nullable();
            // Set on rows the system posts and on opening rows, so each is written once.
            $table->string('posting_key')->nullable()->unique();
            $table->text('remarks')->nullable();
            $table->foreignId('created_by_user_id')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamps();

            $table->index(['employee_id', 'leave_type', 'posted_on']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('leave_ledger_entries');
    }
};
