<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        // An official business or a travel order: days, or half a day, an employee works away from the office.
        Schema::create('day_exceptions', function (Blueprint $table) {
            $table->id();
            $table->foreignId('employee_id')->constrained()->restrictOnDelete();
            $table->string('type', 32);
            $table->date('date_from');
            $table->date('date_to');
            $table->string('coverage', 16)->default('whole_day');
            $table->string('reference_no', 50)->nullable();
            $table->string('remarks')->nullable();
            $table->foreignId('created_by_user_id')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamps();

            $table->index(['employee_id', 'date_from']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('day_exceptions');
    }
};
