<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('leave_applications', function (Blueprint $table) {
            $table->id();
            $table->foreignId('employee_id')->constrained()->restrictOnDelete();
            $table->string('leave_type', 8);
            $table->date('date_from');
            $table->date('date_to');
            $table->string('coverage', 16)->default('whole_day');
            $table->string('detail', 32)->nullable();
            $table->string('detail_text')->nullable();
            $table->boolean('commutation_requested')->default(false);
            $table->decimal('days', 7, 3);
            $table->decimal('days_with_pay', 7, 3);
            $table->decimal('days_without_pay', 7, 3);
            // Each date's days and days without pay, as counted at filing.
            $table->json('breakdown');
            $table->string('status', 16)->default('pending');
            $table->foreignId('filed_by_user_id')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamp('approved_at')->nullable();
            $table->foreignId('approved_by_user_id')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamp('cancelled_at')->nullable();
            $table->foreignId('cancelled_by_user_id')->nullable()->constrained('users')->nullOnDelete();
            $table->text('cancel_reason')->nullable();
            $table->timestamps();

            $table->index(['employee_id', 'date_from']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('leave_applications');
    }
};
